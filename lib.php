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

define ('FILECHECK_KILOBYTE', 1024);
define ('FILECHECK_MEGABYTE', 1048576);
define ('FILECHECK_GIGABYTE', 1073741824);
define ('FILECHECK_TERABYTE', 1099511627776);

/**
 * Checks inexistant files (missing)
 */
function checkfiles_all_files($from = 0, $fromdate = 0, $plugins = '', $limit = 20000) {
    global $DB, $CFG;

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

    if ($limit > 0) {
        echo "Resulting select: $select from: $from limit: $limit ";
        $allfiles = $DB->get_recordset_select('files', $select, $params, 'id', '*', $from, $limit);
    } else {
        echo "Resulting selecting all files ";
        $allfiles = $DB->get_recordset_select('files', $select, $params, 'id', '*');
    }

    $fs = get_file_storage();

    $failures = array();
    $good = array();
    $directories = 0;
    $firstindex = 0;
    $lastindex = 0;
    $overfiles = 0;
    $storedsize = 0;
    $physicalstoredsize = 0;

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
                $storedsize += $stored->get_filesize();
                $physicalstoredsize += filesize($f->physicalfilepath);
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

    return array(count($good), $failures, $directories, $firstindex, $lastindex, $overfiles, $storedsize, $physicalstoredsize);
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

/**
 * @param $contextid limits scanning to a context subtree.
 *
 */
function filecheck_get_filestats_recordset($contextid = null, $exactcontext = false) {
    global $DB;

    $startpath = $DB->get_field('context', 'path', ['id' => $contextid]);

    $contextclause = '';
    $params = [];
    if (!is_null($contextid)) {
        if ($contextid == 1) {
            // Do NOT catch all the tree. Just the site level.
            $contextclause = ' AND ctx.id = ?';
            $params = [1];
        } else {
            if (!$exactcontext) {
                // $likepath = $DB->sql_like('path', ':path');
                $likepath = ' ctx.path LIKE ? ';
                $params = [$startpath.'/%'];
                $contextclause = ' AND '.$likepath;
            } else {
                $contextclause = ' AND ctx.id = ? ';
                $params = [$contextid];
            }
        }
    }

    $sql = "
        SELECT
            ctx.*,
            f.filesize,
            f.mimetype,
            f.component,
            f.filearea
        FROM
            {context} ctx,
            {files} f
        WHERE
            ctx.id = f.contextid AND
            filesize > 0
            {$contextclause}
    ";

    $filestatsrs = $DB->get_recordset_sql($sql, $params);
    return $filestatsrs;
}

/**
 * store_options
 * 
 * Store selected options (files to delete) in the database
 * 
 * @param object $data
 * @param array $indexedfiles
 * @return object $success
 */
function filecheck_cli_store_options($indexedfiles) {
    global $DB, $USER;

    $files = array();

    foreach ($indexedfiles as $key => $file) {
        $path = $file['filepath'] . '/' . $file['filename'];
        $files[] = $path;
    }

    $data = new stdClass();
    $data->sessionid = 0;
    $data->userid = 0;
    $serializedfiles = serialize($files);
    $data->filestodelete = $serializedfiles;
    $params = ['sessionid' => $data->sessionid, 'userid' => $data->userid];
    if ($oldrec = $DB->get_record('tool_filecheck', $params)) {
        $oldrec->filestodelete = $data->filestodelete;
        $id = $oldrec->id;
        $DB->update_record('tool_filecheck', $oldrec);
    } else {
        $id = $DB->insert_record('tool_filecheck', $data);
    }
    $success = new stdClass();
    $success->id = $id;
    $success->filestodelete = $serializedfiles;
    return $success;
}

function tool_filecheck_cli_format_size($size) {
    if ($size < 100) {
        return $size;
    }
    if ($size < FILECHECK_MEGABYTE) {
        return sprintf('%0.1fk', $size / FILECHECK_KILOBYTE);
    }
    if ($size < FILECHECK_GIGABYTE) {
        return sprintf('%0.2fM', $size / FILECHECK_MEGABYTE);
    }
    if ($size < FILECHECK_TERABYTE) {
        return sprintf('%0.2fG', $size / FILECHECK_GIGABYTE);
    }
    return sprintf('%0.3fT', $size / FILECHECK_TERABYTE);
}

/**
 * Receives an open recordset, scan it, distribute values in counter structures and close the recordset.
 * Stat container must be initialized before using, so multiple recordsets can be agregated successively
 * @param object $filestatsrs a Recordset with entries from "file" table
 * @param objectref $stats A global stat buckets container
 * @param array $counterfieds list of counters
 */
function filecheck_aggregate($filestatsrs, &$stats, $counterfields) {
    global $DB;

    // Find and aggregate by course.
    foreach ($filestatsrs as $fs) {

        if ($fs->contextlevel == CONTEXT_SYSTEM) {
            // All system related files are set in course SITEID.
            $cid = SITEID;
        }

        if ($fs->contextlevel == CONTEXT_COURSECAT) {
            // All categories related files are set as system.
            $cid = SITEID;
        }

        if ($fs->contextlevel == CONTEXT_MODULE) {
            // If a course module scope, find course id.
            $cid = $DB->get_field('course_modules', 'course', ['id' => $fs->instanceid]);
        }

        if ($fs->contextlevel == CONTEXT_COURSE) {
            // Course level files. Trivial mapping.
            $cid = $fs->instanceid;
        }

        if ($fs->contextlevel == CONTEXT_BLOCK) {
            // Get the surrounding course context as course reference.
            $parentcontextid = $DB->get_field('block_instances', 'parentcontextid', ['id' => $fs->instanceid]);
            $cid = $DB->get_field('context', 'instanceid', ['id' => $parentcontextid]);
        }

        // Add counter inputs.
        $fs->storagecount = 1; // one file per record.
        $fs->storage = $fs->filesize;
        if (preg_match('/pdf$/i', $fs->mimetype)) {
            $fs->pdfstorage = $fs->filesize;
        }
        if (preg_match('/^x-application/i', $fs->mimetype)) {
            $fs->appstorage = $fs->filesize;
        }
        if (preg_match('/^video/i', $fs->mimetype)) {
            $fs->videostorage = $fs->filesize;
        }
        if (preg_match('/^image/i', $fs->mimetype)) {
            $fs->imagestorage = $fs->filesize;
        }
        if ($fs->filesize > 10000000) {
            $fs->bigfilesstorage = $fs->filesize;
        }

        if ($fs->contextlevel == CONTEXT_USER) {
            if ($fs->filearea == 'draft') {
                filecheck_add_obj($stats->drafttotalizer, $fs);
            } else {
                if (array_key_exists($fs->id, $stats->usertotalizers)) {
                    filecheck_add_obj($usertotalizers[$fs->id], $fs);
                } else {
                    $stats->usertotalizers[$fs->id] = filecheck_init_obj($counterfields);
                    filecheck_add_obj($stats->usertotalizers[$fs->id], $fs);
                }
            }
            continue;
        }

        $stats->bycourses->detail[$cid][$fs->id] = $fs;

        if (array_key_exists($cid, $stats->coursetotalizers)) {
            filecheck_add_obj($stats->coursetotalizers[$cid], $fs);
        } else {
            $stats->coursetotalizers[$cid] = filecheck_init_obj($counterfields);
            filecheck_add_obj($stats->coursetotalizers[$cid], $fs);
        }

        // By component in course.
        if (!array_key_exists($cid, $stats->bycourses->components)) {
            $stats->bycourses->components[$cid] = [];
        }

        if (array_key_exists($fs->component, $stats->bycourses->components[$cid])) {
            filecheck_add_obj($stats->bycourses->components[$cid][$fs->component], $fs);
        } else {
            $stats->bycourses->components[$cid][$fs->component] = filecheck_init_obj($counterfields);
            filecheck_add_obj($stats->bycourses->components[$cid][$fs->component], $fs);
        }

        // By component (all courses)
        if (array_key_exists($fs->component, $stats->componenttotalizers)) {
            filecheck_add_obj($stats->componenttotalizers[$fs->component], $fs);
        } else {
            $stats->componenttotalizers[$fs->component] = filecheck_init_obj($counterfields);
            filecheck_add_obj($stats->componenttotalizers[$fs->component], $fs);
        }

        filecheck_add_obj($stats->totalizer, $fs);

    }

    // Find and aggregate by component.
    foreach ($filestatsrs as $fs) {
        $stats->bycomponents->detail[$fs->component][$fs->id] = $fs;
    }
    $filestatsrs->close();
}

/**
 * Get direct children subcontexts from a contextid
 * @param int $contextid the contextid
 */
function filecheck_get_subcontexts($contextid) {
    global $DB;

    if (empty($contextid)) {
        return [];
    }

    $context = $DB->get_record('context', ['id' => $contextid]);
    $select = " path LIKE ? AND depth = ? ";
    $params = [$context->path.'/%', $context->depth + 1];
    $subcontexts = $DB->get_records_select('context', $select, $params);

    return $subcontexts;
}

function sort_by_criteria($a, $b) {
    $sort = optional_param('sortby', '', PARAM_TEXT);

    $invert = 0;

    switch ($sort) {
        case 'byname' : {
            $i = 0;
            break;
        }

        case 'bysizedesc' : {
            $i = 2;
            $invert = 1;
            break;
        }

        case 'bybigfilesizedesc' : {
            $i = 8;
            $invert = 1;
            break;
        }

        case 'byqdesc' : {
            $i = 1;
            $invert = 1;
            break;
        }
    }

    if ($a[$i] > $b[$i]) {
        return ($invert) ? -1 : 1;
    }

    if ($a[$i] < $b[$i]) {
        return ($invert) ? 1 : -1;
    }

    return 0;
}

function sort_by_criteria_wide($a, $b) {
    $sort = optional_param('sortby', '', PARAM_TEXT);

    $invert = 0;

    switch ($sort) {
        case 'byname' : {
            $i = 0;
            break;
        }

        case 'bysizedesc' : {
            $i = 5;
            $invert = 1;
            break;
        }

        case 'bybigfilesizedesc' : {
            $i = 11;
            $invert = 1;
            break;
        }

        case 'byqdesc' : {
            $i = 4;
            $invert = 1;
            break;
        }
    }

    if ($a[$i] > $b[$i]) {
        return ($invert) ? -1 : 1;
    }

    if ($a[$i] < $b[$i]) {
        return ($invert) ? 1 : -1;
    }

    return 0;
}

function filecheck_postformat(&$table, $ixs, $renderer) {
    foreach ($ixs as $ix) {
        foreach ($table as &$row) {
            $row[$ix] = $renderer->format_size($row[$ix]).' '.$renderer->size_bar($row[$ix]);
        }
    }
}