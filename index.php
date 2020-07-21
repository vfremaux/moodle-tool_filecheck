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

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('filetools', 'tool_filecheck'));

echo $renderer->tabs('index');

echo $renderer->agregation_select();

$sql = "
    SELECT
        CONCAT(ctx.id, '-', f.component, '-', ctx.instanceid) as pkey,
        ctx.id as ctxid,
        ctx.contextlevel as contextlevel,
        ctx.instanceid as instanceid,
        f.component as component,
        SUM( CASE WHEN f.filearea != 'draft' THEN 1 ELSE 0 END) as storagecount,
        SUM( CASE WHEN f.filearea != 'draft' THEN f.filesize ELSE 0 END) as storage,
        SUM( CASE WHEN f.filearea = 'draft' THEN f.filesize ELSE 0 END) as draftstorage,
        SUM( CASE WHEN f.mimetype LIKE 'video%' THEN f.filesize ELSE 0 END) as videostorage,
        SUM( CASE WHEN f.mimetype LIKE 'image%' THEN f.filesize ELSE 0 END) as imagestorage,
        SUM( CASE WHEN f.mimetype LIKE 'x-application%' THEN f.filesize ELSE 0 END) as appstorage,
        SUM( CASE WHEN f.mimetype LIKE 'x-application/pdf' THEN f.filesize ELSE 0 END) as pdfstorage,
        SUM( CASE WHEN f.filesize > 1000000 THEN 1 ELSE 0 END) as bigfiles,
        SUM( CASE WHEN f.filesize > 1000000 THEN f.filesize ELSE 0 END) as bigfilesstorage
    FROM
        {files} as f,
        {context} as ctx
    WHERE
        f.contextid = ctx.id AND
        f.filesize > 0
    GROUP BY
        ctx.id, f.component, ctx.instanceid
";

$filestats = $DB->get_records_sql($sql);
$agregator = optional_param('agregateby', 'bymoduletype', PARAM_TEXT);

$renderer = $PAGE->get_renderer('tool_filecheck');

$bycourses = new Stdclass;
$bycourses->detail = [];
$bycourses->totals = [];
$bycourses->components = [];

$bycomponents = new StdClass;
$bycomponents->detail = [];
$bycomponents->totals = [];

$counterfields = ['storagecount', 'storage', 'draftstorage', 'videostorage', 'imagestorage', 'appstorage', 'pdfstorage', 'bigfiles', 'bigfilesstorage'];
$totalizer = filecheck_init_obj($counterfields);
$coursetotalizers = [];
$componenttotalizers = [];

// Preaggregate by some categories.
if (!empty($filestats)) {

    // Find and aggregate by course.
    foreach ($filestats as $fs) {

        if ($fs->contextlevel == CONTEXT_SYSTEM) {
            $cid = SITEID;
        }

        if ($fs->contextlevel == CONTEXT_MODULE) {
            $cid = $DB->get_field('course_modules', 'course', ['id' => $fs->instanceid]);
        }

        if ($fs->contextlevel == CONTEXT_COURSE) {
            $cid = $fs->instanceid;
        }

        if ($fs->contextlevel == CONTEXT_BLOCK) {
            $parentcontextid = $DB->get_field('block_instances', 'parentcontextid', ['id' => $fs->instanceid]);
            $cid = $DB->get_field('context', 'instanceid', ['id' => $parentcontextid]);
        }
        $bycourses->detail[$cid][$fs->ctxid] = $fs;

        if (array_key_exists($cid, $coursetotalizers)) {
            filecheck_add_obj($coursetotalizers[$cid], $fs);
        } else {
            $coursetotalizers[$cid] = filecheck_init_obj($counterfields);
        }

        // By component in course.
        if (!array_key_exists($cid, $bycourses->components)) {
            $bycourses->components[$cid] = [];
        }

        if (array_key_exists($fs->component, $bycourses->components[$cid])) {
            filecheck_add_obj($bycourses->components[$cid][$fs->component], $fs);
        } else {
            $bycourses->components[$cid][$fs->component] = filecheck_init_obj($counterfields);
        }

        filecheck_add_obj($totalizer, $fs);

    }

    // Find and aggregate by component.
    foreach ($filestats as $fs) {
        $bycomponents->detail[$fs->component][$fs->ctxid] = $fs;
    }
}

if (!empty($filestats)) {

    // By course table.
    $table = new html_table();
    $contextidstr = get_string('contextid', 'tool_filecheck');
    $instanceidstr = get_string('instanceid', 'tool_filecheck');
    $componentstr = get_string('component', 'tool_filecheck');
    $coursestr = get_string('course');

    $totalstr = get_string('totalfiles', 'tool_filecheck');
    $draftstr = get_string('fixvsdraftfiles', 'tool_filecheck');
    $videostr = get_string('videofiles', 'tool_filecheck');
    $imagestr = get_string('imagefiles', 'tool_filecheck');
    $appstr = get_string('appfiles', 'tool_filecheck');
    $pdfstr = get_string('pdffiles', 'tool_filecheck');
    $bigstr = get_string('bigfiles', 'tool_filecheck');

    if ($agregator == 'byinstance') {

        $table->head = [$coursestr, $contextidstr, $componentstr, $instanceidstr, '', $totalstr, $draftstr, $videostr, $imagestr, $appstr, $pdfstr, '', $bigstr];

        foreach ($bycourses->detail as $cid => $coursefiles) {

            $row = [];
            $row[] = $DB->get_field('course', 'shortname', ['id' => $cid]);
            $row[] = '';
            $row[] = '';
            $row[] = '';
            $row[] = $coursetotalizers[$cid]->storagecount;
            $d = $coursetotalizers[$cid]->storage;
            $row[] = $renderer->format_size($d).' / '.$renderer->size_bar($d);
            $d = $coursetotalizers[$cid]->draftstorage;
            $row[] = $renderer->format_size($d).' / '.$renderer->size_bar($d);
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
                $row[] = $renderer->format_size($d).' / '.$renderer->size_bar($d);
                $d = $entry->draftstorage;
                $row[] = $renderer->format_size($d).' / '.$renderer->size_bar($d);
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
    } else {
        // By plugin type (component)
        $table->head = [$coursestr, $componentstr, '', $totalstr, $draftstr, $videostr, $imagestr, $appstr, $pdfstr, '', $bigstr];

        foreach ($bycourses->components as $cid => $typestats) {

            $row = [];
            $row[] = $DB->get_field('course', 'shortname', ['id' => $cid]);
            $row[] = '';
            $row[] = $coursetotalizers[$cid]->storagecount;
            $d = $coursetotalizers[$cid]->storage;
            $row[] = $renderer->format_size($d).' / '.$renderer->size_bar($d);
            $d = $coursetotalizers[$cid]->draftstorage;
            $row[] = $renderer->format_size($d).' / '.$renderer->size_bar($d);
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
                $row[] = $renderer->format_size($d).' / '.$renderer->size_bar($d);
                $d = $entry->draftstorage;
                $row[] = $renderer->format_size($d).' / '.$renderer->size_bar($d);
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

    $row = [];
    $row[] = get_string('total');
    $row[] = '';
    if ($agregator == 'byinstance') {
        $row[] = '';
        $row[] = '';
    }
    $row[] = $totalizer->storagecount;
    $d = $totalizer->storage;
    $row[] = $renderer->format_size($d).' / '.$renderer->size_bar($d);
    $d = $totalizer->draftstorage;
    $row[] = $renderer->format_size($d).' / '.$renderer->size_bar($d);
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

    echo html_writer::table($table);
}

echo $OUTPUT->footer();