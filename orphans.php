<?php
// This file is part of the File Trash report by Barry Oosthuizen - http://elearningstudio.co.uk
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
 * Displays live view of recent logs
 *
 * This file generates live view of recent logs.
 *
 * @package    tool_filecheck
 * @copyright  2013 Barry Oosthuizen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/admin/tool/filecheck/forms/form.php');
require_once($CFG->dirroot . '/admin/tool/filecheck/classes/compare.php');

$confirmdelete = optional_param('confirmdelete', null, PARAM_TEXT);

$context = context_system::instance();
$PAGE->set_context($context);
require_login(null, false);
require_capability('moodle/site:config', $context);
raise_memory_limit(MEMORY_HUGE);

if ($confirmdelete) {
    $deleteurl = new moodle_url('/admin/tool/filecheck/deleteorphans.php', array('confirmdelete' => $confirmdelete));
    redirect($deleteurl);
}

$filetrash = get_string('orphans', 'tool_filecheck');

$url = new moodle_url('/admin/tool/filecheck/orphans.php');

$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title($filetrash);

$renderer = $PAGE->get_renderer('tool_filecheck');

echo $OUTPUT->header();

echo $renderer->tabs('orphans');

echo $OUTPUT->heading(get_string('orphans', 'tool_filecheck'));

$stats = [];
$report = new tool_filecheck\comparator($stats);

echo $renderer->orphan_stats($stats);

$customdata = array('orphanedfiles' => $report->orphanedfiles);
$form = new tool_filecheck_orphans_form(null, $customdata);

if ($form->is_submitted()) {
    $data = $form->get_data();
    $cache = $form->store_options($data, $report->orphanedfiles);
    $filestodelete = unserialize($cache->filestodelete);
    $confirmurl = new moodle_url('/admin/tool/filecheck/index.php', array(
        'confirmdelete' => true,
        'cacheid' => $cache->id));
    echo html_writer::tag('p', get_string('confirm_delete', 'report_filetrash'));
    $i = 0;
    foreach ($filestodelete as $key => $file) {
        if ($file == '/') {
            continue;
        }
        $i++;
        echo html_writer::tag('p', $i . '. ' . $file);
    }
    echo html_writer::link($confirmurl, get_string('delete'));

} else {
    $form->display();
    $PAGE->requires->js_call_amd('tool_filecheck/orphans', 'init');
}

echo $OUTPUT->footer();
