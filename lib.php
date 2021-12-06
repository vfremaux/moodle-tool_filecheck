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
function checkfiles_all_files($from = 0) {
    global $DB, $CFG;

    $fromdate = optional_param('fromdate', 0, PARAM_INT);
    $plugins = optional_param('plugins', '', PARAM_TEXT);
    $limit = optional_param('limit', 20000, PARAM_INT);

    $params = [];
    $selects = [];
    $overselects = [];
    $overparams = [];
    $inclcomponents = [];

    if ($fromdate) {
        $selects[] = ' timecreated < ? ';
        $params = [time() - $fromdate * DAYSECS * 30];
    }

    if ($plugins) {
        $pluginlist = explode(',', $plugins);
        foreach ($pluginlist as $plugin) {
            if (strpos($plugin, '^') === false) {
                $inclcomponents[] = ' component = ? ';
                $params[] = $plugin;
                $overinclcomponents[] = ' component = ? ';
                $overparams[] = $plugin;
            } else {
                $selects[] = ' component <> ? ';
                $plugin = str_replace('^', '', $plugin);
                $params[] = $plugin;
                $overselects[] = ' component = ? ';
                $overparams[] = $plugin;
            }
        }
    }
    if (count($inclcomponents) > 1) {
        // Odd behaviour of implode.
        $ored = implode(' OR ', $înclcomponents);
        $selects[] = ' ('.$ored.') ';
    } else if (count($inclcomponents) == 1) {
        $selects[] = $inclcomponents[0];
    }
    $select = implode(' AND ', $selects);

    echo "Resulting select: $select from: $from limit: $limit ";

    $allfiles = $DB->get_recordset_select('files', $select, $params, 'id', '*', $from, $limit);

    $fs = get_file_storage();

    $failures = array();
    $good = array();
    $directories = 0;
    $firstindex = 0;
    $lastindex = 0;
    $overfiles = 0;

    if ($allfiles) {
        foreach ($allfiles as $f) {
            $stored = new stored_file($fs, $f, $CFG->dataroot.'/filedir');

            if ($firstindex == 0) {
                $firstindex = $stored->get_id();
            }

            $lastindex = $stored->get_id();

            if ($stored->is_directory()) {
                $directories++;
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

        $overselects[] = ' id > ? ';
        $overparams[] = $lastindex;
        $overselects[] = '(filename IS NOT NULL AND filename <> ".")';
        if (!empty($înclovercomponents)) {
            $overselects[] = ' ('.implode(' OR ', $înclovercomponents).') ';
        }

        $overselect = implode(' AND ', $overselects);
        $overfiles = $DB->count_records_select('files', $overselect, $overparams);

        $allfiles->close();
    }

    return array(count($good), $failures, $directories, $firstindex, $lastindex, $overfiles);
}

function filecheck_init_obj($registers) {

    $totalizer = new StdClass();

    foreach ($registers as $reg) {
        $totalizer->$reg = 0;
    }

    return $totalizer;
}

function filecheck_add_obj(&$toobject, $totalizer) {
    foreach ($totalizer as $reg => $value) {
        if (isset($toobject->$reg)) {
            $toobject->$reg += $value;
        }
    }
}