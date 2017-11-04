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
    @raise_memory_limit(MEMORY_EXTRA);
    $results = checkfiles_all_files();
    $goodcount = $results[0];
    $failures = $results[1];

    echo get_string('goodfiles', 'tool_filecheck').': <span style="color:green">'.$goodcount.'</span><br/><br/>';

    echo get_string('missingfiles', 'tool_filecheck').': <span style="color:red">'.count($failures).'</span><br/><br/>';
    if (!empty($failures)) {
        echo '<div class="checkfiles-report">';
        echo '<pre>';
        foreach ($failures as $f) {
            if ($f->component == 'user' && $f->filearea == 'draft') {
                continue;
            }
            if ($f->component == 'core' && $f->filearea == 'preview') {
                continue;
            }
            echo "Moodle file: ".$f->id.' '.$f->contextid.'/'.$f->component.'$'.$f->filearea."\n";
            $context = context_helper::instance_by_id($f->contextid);
            switch ($context->contextlevel) {
                case CONTEXT_SYSTEM: {
                    $cname = "system";
                    $resinfo = array('fullname' => '', 'shortname' => '', 'courseid' => 0);
                    break;
                }

                case CONTEXT_COURSECAT: {
                    $cname = "coursecat";
                    $sql = "
                        SELECT
                            cc.name shortname,
                            cc1.name as fullname,
                            cc.id as courseid
                        FROM
                            {course_categories} cc
                        LEFT JOIN
                            {course_categories} cc1
                        ON
                            cc1.id = cc.parent
                        WHERE
                            cc.id = ?
                    ";
                    $resinfo = $DB->get_record_sql($sql, array($context->instanceid));
                    break;
                }

                case CONTEXT_COURSE: {
                    $cname = "course";
                    $sql = "
                        SELECT
                            shortname,
                            fullname,
                            c.id as courseid
                        FROM
                            {course} c
                        WHERE
                            c.id = ?
                    ";
                    $resinfo = $DB->get_record_sql($sql, array($context->instanceid));
                    break;
                }

                case CONTEXT_MODULE: {
                    $cname = "module";
                    $sql = "
                        SELECT
                            m.name as restype,
                            cm.id as modid,
                            shortname,
                            fullname,
                            c.id as courseid
                        FROM
                            {course_modules} cm,
                            {course} c,
                            {modules} m
                        WHERE
                            m.id = cm.module AND
                            c.id = cm.course AND
                            cm.id = ?
                    ";
                    $resinfo = $DB->get_record_sql($sql, array($context->instanceid));
                    $resinfo->url = $CFG->wwwroot.'/mod/'.$resinfo->restype.'/view.php?id='.$resinfo->modid;
                    break;
                }

                case CONTEXT_BLOCK: {
                    $cname = "block";
                    $sql = "
                        SELECT
                            bi.blockname as restype,
                            shortname,
                            fullname,
                            c.id as courseid
                        FROM
                            {block_instances} bi,
                            {context} ctx,
                            {course} c
                        WHERE
                            bi.parentcontextid = ctx.id AND
                            c.id = ctx.instanceid AND
                            ctx.contextlevel = 50 AND
                            bi.id = ?
                    ";
                    $resinfo = $DB->get_record_sql($sql, array($context->instanceid));
                    break;
                }

                case CONTEXT_USER: {
                    $cname = "user";
                    break;
                }
            }
            echo "Moodle context: ".$cname."\n";
            echo "Moodle course: ".@$resinfo->shortname.' ['.@$resinfo->courseid.']'."\n";
            echo "Moodle mod info: ".@$resinfo->restype.' '.@$resinfo->url."\n";
            echo "Resource file: ".$f->filepath.'/'.$f->filename."\n";
            echo "Storage: ".str_replace($CFG->dataroot, '', $f->physicalfilepath)."\n\n";
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