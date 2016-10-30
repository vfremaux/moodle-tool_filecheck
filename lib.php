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

/**
 * @package    tool_filecheck
 * @category   tool
 * @author     Valery Fremaux <valery.fremaux@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

/**
 * Checks inexistant files (missing)
 */
function checkfiles_all_files() {
    global $DB, $CFG;

    $allfiles = $DB->get_records('files');

    $fs = get_file_storage();

    $failures = array();
    $good = array();

    if ($allfiles) {
        foreach ($allfiles as $f) {
            $stored = new stored_file($fs, $f, $CFG->dataroot.'/filedir');
            if ($stored->is_directory()) {
                continue;
            }

            $contenthash = $stored->get_contenthash();
            $l1 = $contenthash[0].$contenthash[1];
            $l2 = $contenthash[2].$contenthash[3];
            $f->physicalfilepath = $CFG->dataroot.'/filedir/'.$l1.'/'.$l2.'/'.$contenthash;

            if (!file_exists($f->physicalfilepath)) {
                $failures[$f->id] = $f;
            } else {
                $good[$f->id] = $f;
            }
        }
    }

    return array(count($good), $failures);
}
