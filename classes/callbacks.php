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

use stdClass;

/**
 * Class callbacks
 *
 * @package    report_fileusage
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callbacks {
    /**
     * Check called files and store its data.
     * @param \core\hook\after_config $hook
     * @return void
     */
    public static function storedata(\core\hook\after_config $hook) {
        global $ME, $SCRIPT;

        try {
            files::get_called_pluginfile();
        } catch (\Throwable $e) {
            // Testing only.
            $filename = __DIR__ . DIRECTORY_SEPARATOR . 'testdata.txt';
            $oldcontent = @file_get_contents($filename) ?? '';
            $error = $relativepath ?? '';
            $error .= "\n" . $e->getCode() . " " . $e->getMessage() . " " . $e->getTraceAsString() . " \n";
            file_put_contents($filename, "{$oldcontent}{$error} $ME $SCRIPT\n" );
        }
    }
}
