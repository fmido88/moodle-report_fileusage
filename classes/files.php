<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace report_fileusage;

use stored_file;

/**
 * Class files
 *
 * @package    report_fileusage
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class files {
    /**
     * Get the relative path for the file.
     * @return bool|string
     */
    public static function get_pluginfile_relativepath() {
        try {
            if (!$relativepath = get_file_argument()) {
                return false;
            }
        } catch (\Throwable $e) {
            if ($e instanceof \coding_exception) {
                return false;
            }
            throw $e;
        }

        if (!self::is_pluginfile_script()) {
            return false;
        }

        if (0 === strpos($relativepath, '/token/')) {
            $relativepath = ltrim($relativepath, '/');
            $pathparts = explode('/', $relativepath, 2);
            $relativepath = "/{$pathparts[1]}";
        }

        if (empty($relativepath)) {
            return false;
        }

        return $relativepath;
    }

    /**
     * Get the args from the relative path of the file.
     * @param string $relativepath
     * @param ?array $args
     * @return array{contextid:int, component: string, filearea: string, filename:string, pathbase: string}
     */
    public static function get_args_from_relative_path($relativepath, ?array &$args = null) {
        $args = explode('/', ltrim($relativepath, '/'));

        $contextid = (int)array_shift($args);
        $component = clean_param(array_shift($args), PARAM_COMPONENT);
        $filearea  = clean_param(array_shift($args), PARAM_AREA);
        $filename = array_pop($args) ?? '.';
        $pathbase = "/$contextid/$component/$filearea/";
        return compact('contextid', 'component', 'filearea', 'filename', 'pathbase');
    }
    /**
     * Check if the script calling this file is a pluginfile.php script.
     * @return bool
     */
    public static function is_pluginfile_script() {
        global $SCRIPT, $FULLME;
        $valid = [
                "/tokenpluginfile.php",
                "/pluginfile.php",
                "/webservice/pluginfile.php",
            ];
        return (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/pluginfile.php/') !== false)
                    || (!empty($SCRIPT) && in_array($SCRIPT, $valid))
                    || (!empty($FULLME) && strpos($FULLME, '/pluginfile.php/') !== false);
    }

    /**
     * Trying to directly get the store file by the relative path.
     * @param string $path
     * @return bool|\stored_file
     */
    public static function get_file_by_path(string $path) {
        $fs = get_file_storage();
        $pathhash = sha1($path);
        return $fs->get_file_by_hash($pathhash);
    }

    /**
     * Get the file by parameters, this will try to form different paths from the
     * remaining args and check the existence of the file by these paths.
     * @param array $params
     * @param array $args
     * @return bool|\stored_file
     */
    public static function get_file_by_parameters(array &$params, array &$args) {
        ['patbase' => $pathbase, 'filename' => $filename] = $params;
        $file = self::get_file_by_path($pathbase . $filename);

        if ($file) {
            return $file;
        }
        if (!$file && !empty($args)) {
            $fs = get_file_storage();

            $itemid = array_shift($args);
            if (is_number($itemid)) {
                $params['itemid'] = $itemid;
                $pathhash = sha1("$pathbase/$itemid/$filename");
                $file = $fs->get_file_by_hash($pathhash);
                if (!$file && !empty($args)) {
                    $path1 = implode('/', $args);
                    $pathhash = sha1("$pathbase/$itemid/$path1/$filename");
                    $file = $fs->get_file_by_hash($pathhash);
                }
            } else {
                $path1 = implode('/', $args);
                array_unshift($args, $itemid);
                unset($itemid);

                $pathhash = sha1("$pathbase/$path1/$filename");
                if (!$file = $fs->get_file_by_hash($pathhash)) {
                    $path1 = implode('/', $args);
                    $pathhash = sha1("$pathbase/$path1/$filename");
                    $file = $fs->get_file_by_hash($pathhash);
                }
            }
        }

        return $file;
    }

    /**
     * Final hope and the most solid method to get the files
     * this checks all the files in the area by contextid, filearea and component
     * It checks if the file name is the same with one of these files or not.
     * @param array $params
     * @return stored_file|false
     */
    public static function get_file_from_area_files($params) {
        [
            'contextid' => $contextid,
            'filearea'  => $filearea,
            'component' => $component,
            'filename'  => $filename,
            'itemid'    => $itemid,
        ] = $params;

        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, $component, $filearea);
        $found = [];

        foreach ($files as $id => $foundfile) {
            if ($foundfile->is_directory()) {
                unset($files[$id]);
                continue;
            }

            if ($foundfile->get_filename() === $filename) {
                $found[$foundfile->get_id()] = $foundfile;
                unset($files[$id]);
            }
        }

        if (empty($found)) {
            foreach ($files as $id => $foundfile) {
                if (pathinfo($foundfile->get_filename())['filename'] === $filename) {
                    $found[$foundfile->get_id()] = $foundfile;
                    unset($files[$id]);
                }
            }
        }

        if (count($found) === 1) {
            $file = reset($found);
        } else {
            foreach ($found as $id => $foundfile) {
                if (!empty($itemid)) {
                    if ($itemid != $foundfile->get_itemid()) {
                        unset($found[$id]);
                    }
                } else {
                    $path = "/" . implode('/', $args ?? []) . "/";
                    $path = str_replace("//", "/", $path);
                    if ($foundfile->get_filepath() != $path) {
                        unset($found[$id]);
                    }
                }
            }

            if (count($found) === 1) {
                $file = reset($found);
            } else if (count($found) > 1) {
                $file = false;
            }
        }
        return $file;
    }

    /**
     * Save the log record of the file.
     * @param ?stored_file $file
     * @param string $relativepath
     * @return void
     */
    public static function save_file_log(?stored_file $file, string $relativepath = '') {
        global $DB;
        if ($file) {
            $fileid = $file->get_id();

            $record = new \stdClass;
            $record->fileid = $fileid;
            $record->timecreated = time();
            $DB->insert_record('report_fileusage', $record);
        } else if (!empty($relativepath)) {
            $record = new \stdClass;
            $record->pathhash = sha1($relativepath);
            $record->relativepath = $relativepath;
            $record->timecreated = time();
            $DB->insert_record('report_fileusage_broken', $record);
        }
    }
    /**
     * Check if the called script is for pluginfile and save the log.
     * @param bool $savelog
     * @return ?stored_file
     */
    public static function get_called_pluginfile($savelog = true) {

        $file = null;
        if ($relativepath = self::get_pluginfile_relativepath()) {
            $file = self::get_file_by_path($relativepath);
            if (!$file) {
                $params = self::get_args_from_relative_path($relativepath, $args);
                $file = self::get_file_by_parameters($params, $args);
            }

            if (!$file) {
                $file = self::get_file_from_area_files($params);
            }

            if (!$file) {
                $file = null;
            }

            if ($savelog) {
                self::save_file_log($file, $relativepath);
            }
        }

        return $file;
    }
}
