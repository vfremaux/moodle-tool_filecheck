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
 * This file generates live view of recent logs. Thanks to Barry Oosthuizen.
 *
 * @package    tool_filecheck
 * @copyright  2013 Barry Oosthuizen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot.'/admin/tool/filecheck/classes/files.php');

$confirmdelete = optional_param('confirmdelete', false, PARAM_BOOL);
$context = context_system::instance();
$PAGE->set_context($context);
require_login(null, false);
require_capability('moodle/site:config', $context);
raise_memory_limit(MEMORY_HUGE);

$title = get_string('orphans', 'tool_filecheck');

$continueurl = new moodle_url('/admin/tool/filecheck/index.php');

if ($confirmdelete) {
    $url = new moodle_url('/admin/tool/filecheck/deleteorphans.php');

    $PAGE->set_url($url);
    $PAGE->set_pagelayout('report');
    $PAGE->set_title($title);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('orphans', 'tool_filecheck'));
    $files = new \tool_filecheck\orphan_files();
    $errors = $files->delete();
    if (count($errors) > 0) {
        echo html_writer::tag('p', get_string('deletedfailed', 'tool_filecheck'));
        foreach ($errors as $key => $error) {
            echo html_writer::tag('p', $error);
        }
    } else {
        echo html_writer::tag('p', get_string('deleted', 'tool_filecheck'));
    }

    $link = $OUTPUT->continue_button($continueurl);
    echo html_writer::tag('p', $link);
} else {
    redirect($continueurl);
}

echo $OUTPUT->footer();
