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

$agregator = optional_param('agregateby', 'bymoduletype', PARAM_TEXT);
$contextid = optional_param('contextid', 1, PARAM_INT);

$filestatsrs = filecheck_get_filestats_recordset($contextid);

$stats = new StdClass;

$stats->bycomponents = new StdClass;
$stats->bycomponents->detail = [];
$stats->bycourses = new Stdclass;
$stats->bycourses->detail = [];
$stats->bycourses->totals = [];
$stats->bycourses->components = [];

// A counter object gives global counters (all files) and "by nature" partial results.
$counterfields = ['storagecount', 'storage', 'videostorage', 'imagestorage', 'appstorage', 'pdfstorage', 'bigfiles', 'bigfilesstorage'];

// Overal totalizer that sums everything.
$stats->totalizer = filecheck_init_obj($counterfields);

// By course totalizers that sums one measurement per course.
$stats->coursetotalizers = [];

// By component totalizers that sum one measurement per component type (all fileareas).
$stats->componenttotalizers = [];

// One single record that sums all draft files
$stats->drafttotalizer = filecheck_init_obj($counterfields);

// One single record that sums all other user files (persistant) that are NOT draft.
$stats->usertotalizers = [];

// Preaggregate by some categories.
$hasdata = false;
if ($filestatsrs->valid()) {
    filecheck_aggregate($filestatsrs, $stats, $counterfields);
    $hasdata = true;
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

echo '<div id="scan-options" class="selectors d-flex">';
echo $renderer->agregation_select();
echo $renderer->context_select();
echo $renderer->sort_select();
echo '</div>';

if ($hasdata) {

    // Overall stats.
    $table = new html_table();
    $table->head = ['Q', $totalstr, $videostr, $imagestr, $appstr, $pdfstr, 'BfQ', $bigstr];

    $row = [];
    $row[] = $stats->totalizer->storagecount;
    $d = $stats->totalizer->storage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $stats->totalizer->videostorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $stats->totalizer->imagestorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $stats->totalizer->appstorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $stats->totalizer->pdfstorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $row[] = $stats->totalizer->bigfiles;
    $d = $totalizer->bigfilesstorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $table->data[] = $row;

    echo $OUTPUT->heading(get_string('overall', 'tool_filecheck'));
    echo html_writer::table($table);

    // Draft stats.
    $table = new html_table();
    $table->head = ['Q', $totalstr, $videostr, $imagestr, $appstr, $pdfstr, 'BfQ', $bigstr];

    $row = [];
    $row[] = $totalizer->storagecount;
    $d = $stats->drafttotalizer->storage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $stats->drafttotalizer->videostorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $stats->drafttotalizer->imagestorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $stats->drafttotalizer->appstorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $d = $stats->drafttotalizer->pdfstorage;
    $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
    $row[] = $stats->drafttotalizer->bigfiles;
    $d = $stats->drafttotalizer->bigfilesstorage;
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
        $table->head = [$coursestr, $contextidstr, $componentstr, $instanceidstr, 'Q', $totalstr, $videostr, $imagestr, $appstr, $pdfstr, 'BfQ', $bigstr];

        foreach ($stats->bycourses->detail as $cid => $coursefiles) {

            $row = [];
            $row[] = $DB->get_field('course', 'shortname', ['id' => $cid]);
            $row[] = '';
            $row[] = '';
            $row[] = '';
            $row[] = $stats->coursetotalizers[$cid]->storagecount;
            $row[] = $stats->coursetotalizers[$cid]->storage;
            $row[] = $stats->coursetotalizers[$cid]->videostorage;
            $row[] = $stats->coursetotalizers[$cid]->imagestorage;
            $row[] = $stats->coursetotalizers[$cid]->appstorage;
            $row[] = $stats->coursetotalizers[$cid]->pdfstorage;
            $row[] = $stats->coursetotalizers[$cid]->bigfiles;
            $row[] = $stats->coursetotalizers[$cid]->bigfilesstorage;
            $table->data[] = $row;

            foreach ($coursefiles as $entry) {
                $row = [];
                $row[] = ''; // 0
                $row[] = $entry->id;
                $row[] = $entry->component;
                $row[] = $entry->instanceid;

                $row[] = $entry->storagecount;
                $row[] = $entry->storage; // 5
                $row[] = $entry->videostorage;
                $row[] = $entry->imagestorage;
                $row[] = $entry->appstorage;
                $row[] = $entry->pdfstorage; // 9
                $row[] = $entry->bigfiles;
                $row[] = $entry->bigfilesstorage; //11
                $table->data[] = $row;
            }
        }

        usort($table->data, 'sort_by_criteria_wide');
        filecheck_postformat($table->data, [5,6,7,8,9,11], $renderer);

    } else if ($agregator == 'bymoduletype') {
        // By plugin type (component)
        $table = new html_table();
        $table->head = [$componentstr, 'Q', $totalstr, $videostr, $imagestr, $appstr, $pdfstr, 'BfQ', $bigstr];

        foreach ($stats->componenttotalizers as $component => $typestats) {
            $row = [];
            $row[] = $component;
            $row[] = $typestats->storagecount;
            $row[] = $typestats->storage; // 2
            $row[] = $typestats->videostorage;
            $row[] = $typestats->imagestorage;
            $row[] = $typestats->appstorage;
            $row[] = $typestats->pdfstorage; // 6
            $row[] = $typestats->bigfiles;
            $row[] = $typestats->bigfilesstorage; // 8
            $table->data[] = $row;
        }

        usort($table->data, 'sort_by_criteria');
        filecheck_postformat($table->data, [2,3,4,5,6,8], $renderer);

    } else if ($agregator == 'bycourse') {
        // By plugin type (component)
        $table = new html_table();
        $table->head = [$coursestr, 'Q', $totalstr, $videostr, $imagestr, $appstr, $pdfstr, 'BfQ', $bigstr];

        foreach ($stats->coursetotalizers as $course => $coursestats) {
            $row = [];
            $row[] = $DB->get_field('course', 'shortname', ['id' => $course]);
            $row[] = $coursestats->storagecount;
            $row[] = $coursestats->storage;
            $row[] = $coursestats->videostorage;
            $row[] = $coursestats->imagestorage;
            $row[] = $coursestats->appstorage;
            $row[] = $coursestats->pdfstorage;
            $row[] = $coursestats->bigfiles;
            $row[] = $coursestats->bigfilesstorage;
            $table->data[] = $row;
        }

        usort($table->data, 'sort_by_criteria');
        filecheck_postformat($table->data, [2,3,4,5,6,8], $renderer);

    } else {
        // By plugin coursetype (component)
        $table = new html_table();
        $table->head = [$componentstr, 'Q', $totalstr, $videostr, $imagestr, $appstr, $pdfstr, 'BfQ', $bigstr];

        foreach ($stats->bycourses->components as $cid => $typestats) {

            $row = [];
            $row[] = $DB->get_field('course', 'shortname', ['id' => $cid]);
            $row[] = $stats->coursetotalizers[$cid]->storagecount;
            $d = $stats->coursetotalizers[$cid]->storage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $stats->coursetotalizers[$cid]->videostorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $stats->coursetotalizers[$cid]->imagestorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $stats->coursetotalizers[$cid]->appstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $d = $stats->coursetotalizers[$cid]->pdfstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $row[] = $stats->coursetotalizers[$cid]->bigfiles;
            $d = $stats->coursetotalizers[$cid]->bigfilesstorage;
            $row[] = $renderer->format_size($d).' '.$renderer->size_bar($d);
            $table->data[] = $row;

            foreach ($typestats as $component => $entry) {
                $row = [];
                $row[] = $DB->get_field('course', 'shortname', ['id' => $cid]).'/'.$component;

                $row[] = $entry->storagecount;
                $row[] = $entry->storage;
                $row[] = $entry->videostorage;
                $row[] = $entry->imagestorage;
                $row[] = $entry->appstorage;
                $row[] = $entry->pdfstorage;
                $row[] = $entry->bigfiles;
                $row[] = $entry->bigfilesstorage;
                $table->data[] = $row;
            }
        }

        usort($table->data, 'sort_by_criteria');
        filecheck_postformat($table->data, [2,3,4,5,6,8], $renderer);
    }

    echo $OUTPUT->heading(get_string('detail', 'tool_filecheck'));
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nofilesfound', 'tool_filecheck'));
}

echo $OUTPUT->footer();