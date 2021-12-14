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
require('../../../config.php');
require_once($CFG->dirroot.'/admin/tool/filecheck/lib.php');

$url = new moodle_url('/admin/tool/filecheck/index.php');

$context = context_system::instance();

$PAGE->set_url($url);
$PAGE->set_context($context);

require_login();
require_capability('moodle/site:config', $context);
$renderer = $PAGE->get_renderer('tool_filecheck');
raise_memory_limit(MEMORY_HUGE);

$filestatsrs = filecheck_get_filestats_recordset();

$agregator = optional_param('agregateby', 'bymoduletype', PARAM_TEXT);

$bycourses = new Stdclass;
$bycourses->detail = [];
$bycourses->totals = [];
$bycourses->components = [];

// A counter object gives global counters (all files) and "by nature" partial results.
$counterfields = ['storagecount', 'storage', 'videostorage', 'imagestorage', 'appstorage', 'pdfstorage', 'bigfiles', 'bigfilesstorage'];

// Overal totalizer that sums everything.
$totalizer = filecheck_init_obj($counterfields);

// By course totalizers that sums one measurement per course.
$coursetotalizers = [];

// By component totalizers that sum one measurement per component type (all fileareas).
$componenttotalizers = [];

// One single record that sums all draft files
$drafttotalizer = filecheck_init_obj($counterfields);

// One single record that sums all other user files (persistant) that are NOT draft.
$usertotalizers = [];

// Preaggregate by some categories.
if (!empty($filestatsrs)) {

    // Find and aggregate by course.
    foreach ($filestatsrs as $fs) {

        if ($fs->contextlevel == CONTEXT_SYSTEM) {
            // All systeme related files are set in course SITEID.
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

        if ($fs->contextlevel == CONTEXT_USER) {
            if ($fs->filearea == 'draft') {
                filecheck_add_obj($drafttotalizer, $fs);
            } else {
                if (array_key_exists($fs->ctxid, $usertotalizers)) {
                    filecheck_add_obj($usertotalizers[$fs->ctxid], $fs);
                } else {
                    $usertotalizers[$fs->ctxid] = filecheck_init_obj($counterfields);
                    filecheck_add_obj($usertotalizers[$fs->ctxid], $fs);
                }
            }
            continue;
        }

        $bycourses->detail[$cid][$fs->ctxid] = $fs;

        if (array_key_exists($cid, $coursetotalizers)) {
            filecheck_add_obj($coursetotalizers[$cid], $fs);
        } else {
            $coursetotalizers[$cid] = filecheck_init_obj($counterfields);
            filecheck_add_obj($coursetotalizers[$cid], $fs);
        }

        // By component in course.
        if (!array_key_exists($cid, $bycourses->components)) {
            $bycourses->components[$cid] = [];
        }

        if (array_key_exists($fs->component, $bycourses->components[$cid])) {
            filecheck_add_obj($bycourses->components[$cid][$fs->component], $fs);
        } else {
            $bycourses->components[$cid][$fs->component] = filecheck_init_obj($counterfields);
            filecheck_add_obj($bycourses->components[$cid][$fs->component], $fs);
        }

        // By component (all courses)
        if (array_key_exists($fs->component, $componenttotalizers)) {
            filecheck_add_obj($componenttotalizers[$fs->component], $fs);
        } else {
            $componenttotalizers[$fs->component] = filecheck_init_obj($counterfields);
            filecheck_add_obj($componenttotalizers[$fs->component], $fs);
        }

        filecheck_add_obj($totalizer, $fs);

    }

    // Find and aggregate by component.
    foreach ($filestatsrs as $fs) {
        $bycomponents->detail[$fs->component][$fs->ctxid] = $fs;
    }
	$filestatsrs->close();
}

// Define this once.
$totalstr = get_string('totalfiles', 'tool_filecheck');
$videostr = get_string('videofiles', 'tool_filecheck');
$imagestr = get_string('imagefiles', 'tool_filecheck');
$appstr = get_string('appfiles', 'tool_filecheck');
$pdfstr = get_string('pdffiles', 'tool_filecheck');
$bigstr = get_string('bigfiles', 'tool_filecheck');

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('filetools', 'tool_filecheck'));

echo $renderer->tabs('index');

echo $renderer->agregation_select();

if (!empty($filestats)) {

    // Overall stats.
    $table = new html_table();
    $table->head = ['Q', $totalstr, $videostr, $imagestr, $appstr, $pdfstr, '', $bigstr];

    $row = [];
    $row[] = $totalizer->storagecount;
    $d = $totalizer->storage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $totalizer->videostorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $totalizer->imagestorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $totalizer->appstorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $totalizer->pdfstorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $row[] = $totalizer->bigfiles;
    $d = $totalizer->bigfilesstorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $table->data[] = $row;

    echo $OUTPUT->heading(get_string('overall', 'tool_filecheck'));
    echo html_writer::table($table);

    // Draft stats.
    $table = new html_table();
    $table->head = ['Q', $totalstr, $videostr, $imagestr, $appstr, $pdfstr, '', $bigstr];

    $row = [];
    $row[] = $totalizer->storagecount;
    $d = $drafttotalizer->storage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $drafttotalizer->videostorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $drafttotalizer->imagestorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $drafttotalizer->appstorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $drafttotalizer->pdfstorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $row[] = $drafttotalizer->bigfiles;
    $d = $drafttotalizer->bigfilesstorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $table->data[] = $row;

    echo $OUTPUT->heading(get_string('drafts', 'tool_filecheck'));
    echo html_writer::table($table);

    // By course table.
    $contextidstr = get_string('contextid', 'tool_filecheck');
    $instanceidstr = get_string('instanceid', 'tool_filecheck');
    $componentstr = get_string('component', 'tool_filecheck');
    $coursestr = get_string('course');

    if ($agregator == 'byinstance') {

        $table = new html_table();
        $table->head = [$coursestr, $contextidstr, $componentstr, $instanceidstr, 'Q', $totalstr, $videostr, $imagestr, $appstr, $pdfstr, '', $bigstr];

        foreach ($bycourses->detail as $cid => $coursefiles) {

            $row = [];
            $row[] = $DB->get_field('course', 'shortname', ['id' => $cid]);
            $row[] = '';
            $row[] = '';
            $row[] = '';
            $row[] = $coursetotalizers[$cid]->storagecount;
            $d = $coursetotalizers[$cid]->storage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $coursetotalizers[$cid]->videostorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $coursetotalizers[$cid]->imagestorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $coursetotalizers[$cid]->appstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $coursetotalizers[$cid]->pdfstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $row[] = $coursetotalizers[$cid]->bigfiles;
            $d = $coursetotalizers[$cid]->bigfilesstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $table->data[] = $row;

            foreach ($coursefiles as $entry) {
                $row = [];
                $row[] = '';
                $row[] = $entry->ctxid;
                $row[] = $entry->component;
                $row[] = $entry->instanceid;

                $row[] = $entry->storagecount;
                $d = $entry->storage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $d = $entry->videostorage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $d = $entry->imagestorage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $d = $entry->appstorage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $d = $entry->pdfstorage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $row[] = $entry->bigfiles;
                $d = $entry->bigfilesstorage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $table->data[] = $row;
            }
        }
    } else if ($agregator == 'bymoduletype') {
        // By plugin type (component)
        $table = new html_table();
        $table->head = [$componentstr, 'Q', $totalstr, $videostr, $imagestr, $appstr, $pdfstr, 'Q', $bigstr];

        foreach ($componenttotalizers as $component => $typestats) {
            $row = [];
            $row[] = $component;
            $row[] = $typestats->storagecount;
            $d = $typestats->storage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $typestats->videostorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $typestats->imagestorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $typestats->appstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $typestats->pdfstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $row[] = $typestats->bigfiles;
            $d = $typestats->bigfilesstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $table->data[] = $row;
        }

    } else {
        // By plugin type (component)
        $table = new html_table();
        $table->head = [$coursestr, $componentstr, 'Q', $totalstr, $videostr, $imagestr, $appstr, $pdfstr, '', $bigstr];

        foreach ($bycourses->components as $cid => $typestats) {

            $row = [];
            $row[] = $DB->get_field('course', 'shortname', ['id' => $cid]);
            $row[] = '';
            $row[] = $coursetotalizers[$cid]->storagecount;
            $d = $coursetotalizers[$cid]->storage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $coursetotalizers[$cid]->videostorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $coursetotalizers[$cid]->imagestorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $coursetotalizers[$cid]->appstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $coursetotalizers[$cid]->pdfstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $row[] = $coursetotalizers[$cid]->bigfiles;
            $d = $coursetotalizers[$cid]->bigfilesstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $table->data[] = $row;

            foreach ($typestats as $component => $entry) {
                $row = [];
                $row[] = '';
                $row[] = $component;

                $row[] = $entry->storagecount;
                $d = $entry->storage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $d = $entry->videostorage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $d = $entry->imagestorage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $d = $entry->appstorage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $d = $entry->pdfstorage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $row[] = $entry->bigfiles;
                $d = $entry->bigfilesstorage;
                $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
                $table->data[] = $row;
            }
        }
    }

    echo $OUTPUT->heading(get_string('detail', 'tool_filecheck'));
    echo html_writer::table($table);
}

echo $OUTPUT->footer();