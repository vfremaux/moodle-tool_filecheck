<?php

/**
 * Checks inexistant files (missing)
 */
function checkfiles_all_files() {
    global $DB, $CFG;

    $allfiles = $DB->get_records('files');

    $fs = get_file_storage();

    $failures = array();
    $good = array();

    if ($allfiles) {
        foreach ($allfiles as $f) {
            $stored = new stored_file($fs, $f, $CFG->dataroot.'/filedir');
            if ($stored->is_directory()) {
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
            }
        }
    }

    return array(count($good), $failures);
}
