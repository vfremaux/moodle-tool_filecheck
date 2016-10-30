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

// Security.

$systemcontext = context_system::instance();
require_login();
require_capability('moodle/site:config', $systemcontext);

$pagetitlestr = get_string('checkfiles', 'tool_filecheck');

$url = new moodle_url('/admin/tool/filecheck/checkfiles.php');
$PAGE->set_url($url);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($pagetitlestr);

echo $OUTPUT->header();

$confirm = optional_param('confirm', 0, PARAM_BOOL);
$cleanup = optional_param('cleanup', 0, PARAM_BOOL);

echo $OUTPUT->heading(get_string('checkfiles', 'tool_filecheck'));

if ($confirm) {
    $results = checkfiles_all_files();
    $goodcount = $results[0];
    $failures = $results[1];

    echo get_string('goodfiles', 'tool_filecheck').': <span style="color:green">'.$goodcount.'</span><br/><br/>';

    echo get_string('missingfiles', 'tool_filecheck').': <span style="color:red">'.count($failures).'</span><br/><br/>';
    if (!empty($failures)) {
        echo '<div class="checkfiles-report">';
        echo '<pre>';
        foreach ($failures as $f) {
            $message = $f->id.' '.$f->component.'$'.$f->filearea.' '.$f->filepath.'/'.$f->filename;
            $message .= ' '.get_string('expectedat', 'tool_filecheck').":\n".$f->physicalfilepath;
            mtrace($message);
            if ($cleanup) {
                $DB->delete_records('files', array('id' => $f->id));
                mtrace('File record removed');
            }
            mtrace('');
        }
        echo '</pre>';
        echo '</div>';
    }
}

$buttonurl = new moodle_url('/admin/tool/filecheck/checkfiles.php', array('confirm' => true));
echo $OUTPUT->single_button($buttonurl, get_string('confirm'));
if ($confirm) {
    $buttonurl = new moodle_url('/admin/tool/filecheck/checkfiles.php', array('confirm' => true, 'cleanup' => true));
    echo $OUTPUT->single_button($buttonurl, get_string('cleanup', 'tool_filecheck'));
}

echo $OUTPUT->footer();