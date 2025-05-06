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

raise_memory_limit(MEMORY_HUGE);

$pagetitlestr = get_string('checkfiles', 'tool_filecheck');

$url = new moodle_url('/admin/tool/filecheck/checkfiles.php');
$PAGE->set_url($url);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($pagetitlestr);
$renderer = $PAGE->get_renderer('tool_filecheck');

echo $OUTPUT->header();

$confirm = optional_param('confirm', 0, PARAM_BOOL);
$cleanup = optional_param('cleanup', 0, PARAM_BOOL);
$fromdate = optional_param('fromdate', 0, PARAM_INT);
$plugins = optional_param('plugins', '', PARAM_TEXT);
$limit = optional_param('limit', 20000, PARAM_INT);

echo $OUTPUT->heading(get_string('checkfiles', 'tool_filecheck'));

echo $renderer->tabs('integrity');

if ($confirm) {
    $from = optional_param('from', 0, PARAM_INT);

    // Get all results with limit and plugins filter.

    $results = checkfiles_all_files($from, $fromdate, $plugins, $limit);
    $goodcount = $results[0];
    $failures = $results[1];
    $directories = $results[2];
    $firstindex = $results[3];
    $lastindex = $results[4];
    $overfiles = $results[5];
    $storedsize = $results[6];
    $physicalstoredsize = $results[7];

    echo get_string('files', 'tool_filecheck').': <span style="color:green">'.($goodcount + count($failures)).'</span><br/><br/>';
    echo get_string('directories', 'tool_filecheck').': <span style="color:green">'.$directories.'</span><br/><br/>';
    echo get_string('goodfiles', 'tool_filecheck').': <span style="color:green">'.$goodcount.'</span><br/><br/>';
    echo get_string('storedsize', 'tool_filecheck').': <span style="color:green">'.($storedsize).'</span><br/><br/>';
    $color = ($storedsize == $physicalstoredsize) ? 'green' : 'orange';
    echo get_string('physicalstoredsize', 'tool_filecheck').': <span style="color:'.$color.'">'.($physicalstoredsize).'</span><br/><br/>';
    echo get_string('firstindex', 'tool_filecheck').': <span style="color:green">'.$firstindex.'</span><br/><br/>';
    echo get_string('lastindex', 'tool_filecheck').': <span style="color:green">'.$lastindex.'</span><br/><br/>';
    echo get_string('overfiles', 'tool_filecheck').': <span style="color:green">'.$overfiles.'</span><br/><br/>';
    echo get_string('missingfiles', 'tool_filecheck').': <span style="color:red">'.count($failures).'</span><br/><br/>';

    echo $OUTPUT->notification(get_string('additionalparams_help', 'tool_filecheck'), 'info');

    if (!empty($failures)) {
        echo '<div class="checkfiles-report">';
        echo '<pre>';
        foreach ($failures as $f) {
            $message = $f->id.' '.$f->component.'$'.$f->filearea.' '.$f->filepath.'/'.$f->filename;
            $message .= ' '.get_string('expectedat', 'tool_filecheck').":\n".$f->physicalfilepath;
            mtrace($message);
            if ($cleanup) {
                // Fix only if cleanup was asked for.
                // Cleanup will only affect filtered plugins.
                $DB->delete_records('files', array('id' => $f->id));
                mtrace('File record removed');
            }
            mtrace('');
        }
        echo '</pre>';
        echo '</div>';
    }
}

$params = [
    'confirm' => true,
    'cleanup' => false,
    'plugins' => $plugins,
    'fromdate' => $fromdate,
    'limit' => $limit,
];
$buttonurl = new moodle_url('/admin/tool/filecheck/checkfiles.php', $params);
echo $OUTPUT->single_button($buttonurl, get_string('confirm'));
if ($confirm) {
    $params = [
        'confirm' => true,
        'cleanup' => true,
        'plugins' => $plugins,
        'fromdate' => $fromdate,
        'limit' => $limit,
    ];
    $buttonurl = new moodle_url('/admin/tool/filecheck/checkfiles.php', $params);
    echo $OUTPUT->single_button($buttonurl, get_string('cleanup', 'tool_filecheck'));
}

echo $OUTPUT->footer();