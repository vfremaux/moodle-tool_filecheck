<?php

require('../../../config.php');
require_once($CFG->dirroot.'/admin/tool/filecheck/lib.php');

$systemcontext = context_system::instance();
require_capability('moodle/site:config', $systemcontext);

$pagetitlestr = get_string('checkfiles', 'tool_filecheck');

$url = $CFG->wwwroot.'/admin/tool/filecheck/checkfiles.php';
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
            mtrace($f->id.' '.$f->component.'$'.$f->filearea.' '.$f->filepath.'/'.$f->filename.' '.get_string('expectedat', 'tool_filecheck').":\n".$f->physicalfilepath);
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

echo $OUTPUT->single_button(new moodle_url('/admin/tool/filecheck/checkfiles.php', array('confirm' => true)), get_string('confirm'));
if ($confirm) {
    echo $OUTPUT->single_button(new moodle_url('/admin/tool/filecheck/checkfiles.php', array('confirm' => true, 'cleanup' => true)), get_string('cleanup', 'tool_filecheck'));
}

echo $OUTPUT->footer();