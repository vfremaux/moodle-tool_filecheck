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
 * @package     tool_filecheck
 * @category    tool
 * @copyright   2016 Valery Fremaux <valery@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CLI_VMOODLE_PRECHECK;

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);
$CLI_VMOODLE_PRECHECK = true; // Force first config to be minimal.

require(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/lib/clilib.php'); // Cli only functions.

// Now get cli options.

list($options, $unrecognized) = cli_get_params(array('help' => false,
                                                     'mode' => false,
                                                     'contextid' => false,
                                                     'agregator' => false,
                                                     'host' => false,
                                                     'human-readable' => false,
                                                     'force-decompose' => false,
                                                     'debug' => false,
                                                     'output' => false,
                                                     'input' => false,
                                                     'limit' => false),
                                               array('h' => 'help',
                                                     'm' => 'mode',
                                                     'c' => 'contextid',
                                                     'a' => 'agregator',
                                                     'r' => 'human-readable',
                                                     'H' => 'host',
                                                     'd' => 'debug',
                                                     'f' => 'force-decompose',
                                                     'O' => 'output',
                                                     'I' => 'input',
                                                     'l' => 'limit')
                                               );

if ($unrecognized) {
    $unrecognized = implode("\n ", $unrecognized);
    cli_error($unrecognized. " is not a recognized option\n");
}

if ($options['help']) {
    $help = "
Scans file system.

Options:
-h, --help            Print out this help
-H, --host            The virtual moodle to play for. Main moodle install if missing.
-m, --mode            Mode ('listorphans', 'moveorphans', 'deleteorphans', or 'filetypes')
-c, --contextid       Restrict to context sub path
-h, --human-readable  Applies value formatting for better readability.
-a, --agregator       Agregator, in 'bycourse', 'bymoduletypebycourse',
-f, --force-decompose If set, will decompose in subrecordsets using subcontexts. 
-d, --debug           Debug mode
-O, --output          Output file.
-I, --input           Input file for deletion (obtained with --output).
-l, --limit           Limits the number of processed entries (move or delete orphans).

Example:
sudo -uwww-data php admin/tool/filecheck/cli/scanfiles.php -m orphans
";

    echo $help;
    die;
}

if (!empty($options['host'])) {
    // Arms the vmoodle switching.
    echo('Arming for '.$options['host']."\n"); // Mtrace not yet available.
    define('CLI_VMOODLE_OVERRIDE', $options['host']);
}

// Replay full config whenever. If vmoodle switch is armed, will switch now config.

if (!defined('MOODLE_INTERNAL')) {
    // If we are still in precheck, this means this is NOT a VMoodle install and full setup has already run.
    // Otherwise we only have a tiny config at this location, sso run full config again forcing playing host if required.
    require(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php'); // Global moodle config file.
}
echo('Config check : playing for '.$CFG->wwwroot."\n");

require_once($CFG->dirroot.'/admin/tool/filecheck/lib.php');
require_once($CFG->dirroot.'/admin/tool/filecheck/classes/compare.php');
require_once($CFG->dirroot.'/admin/tool/filecheck/renderer.php');

if (empty($options['mode'])) {
    $options['mode'] = 'filetypes';
}

if (!empty($options['debug'])) {
    $CFG->debug = DEBUG_DEVELOPER;
}

if ($options['mode'] == 'getorphans') {
    // If mode is orphan, scans the file system and records an orphan list to be further processed by "deleteorphans", or "moveorphans"
    $stats = [];
    if (empty($options['force-decompose'])) {
        // This is possible on usual volumes. If the script crashes, use --force-decompose option.
        $report = new tool_filecheck\comparator();
        $report->init($stats);
        $cache = filecheck_cli_store_options($report->orphanedfiles);
    } else {
        // Use slower strategy, by querying file by file.
        echo "Force-decompose : Using slow scan method\n";
        $report = new tool_filecheck\comparator();
        if (!empty($options['debug'])) {
            echo "Debug : scanning db matches\n";
        }
        // For scalability reasons, slow_scan performs an iterative scan of filedir subdir, not using a full memory iterator class.
        // then resolves on the file bucket.
        $report->slow_scan($stats, $options);
        if (!$report->invalidatedbysize) {
            // We have a valid orphan list in memory. Save it to cache.
            $cacherec = new StdClass;
            $cacherec->sessionid = 0;
            $cacherec->userid = 0;
            $cacherec->filestodelete = serialize($report->orphanedfiles);
            $DB->delete_records('tool_filecheck', ['userid' => 0, 'sessionid' => 0]);
            $DB->insert_record('tool_filecheck', $cacherec);
        }
        if (!empty($options['debug'])) {
            echo "\n";
        }
    }

    if (empty($report->orphanedfiles) && empty($report->invalidatedbysize)) {
        die("No orphans found\n");
    } else {
        // With normal scan.
        $bytes = 0;
        $unit = 0;

        foreach ($report->orphanedfiles as $f) {

            if ($unit == 0) {
                $bytes += $f->filesize;
            } else {
                $bytes += $f->filesize / FILECHECK_MEGABYTE;
            }
            if ($unit = 0 && $bytes > FILECHECK_MEGABYTE) {
                $unit = 1;
                $bytes = $bytes / FILECHECK_MEGABYTE;
            }
            echo $f->filepath.'/'.$f->filename." (".$f->filesize.")\n";

        }

        echo $stats['count']." orphan files found\n";
        echo tool_filecheck_cli_format_size($stats['bytes'])." orphan bytes found\n";
    }
} else if ($options['mode'] == 'listorphans') {

    // Get cli cache.
    if (empty($options['input'])) {
        $cachedorphans = $DB->get_record('tool_filecheck', ['userid' => 0, 'sessionid' => 0]);
        $filestodelete = unserialize($cachedorphans->filestodelete);
    } else {
        // Process input file.
        if (!file_exists($options['input'])) {
            die ("Input file not exists or not readable\n");
        }

        $filetodelete = file($options['input'], FILE_IGNORE_NEW_LINES & FILE_SKIP_EMPTY_LINES );
    }
    if (empty($filestodelete)) {
        die("No orphans found\n");
    } else {
        $count = count($filestodelete);

        foreach ($filestodelete as $fpath) {
            echo $fpath."\n";
        }

        echo $count." orphans files found\n";
    }

} else if ($options['mode'] == 'deleteorphans') {

    if (empty($options['input'])) {
        $cachedorphans = $DB->get_record('tool_filecheck', ['userid' => 0, 'sessionid' => 0]);
        $filestodelete = unserialize($cachedorphans->filestodelete);
    } else {
        // Process input file.
        if (!file_exists($options['input'])) {
            die ("Input file not exists or not readable\n");
        }

        $filetodelete = file($options['input'], FILE_IGNORE_NEW_LINES & FILE_SKIP_EMPTY_LINES );
    }
    if (empty($filestodelete)) {
        die("No orphans to delete\n");
    } else {
        $count = count($filestodelete);

        $report = new tool_filecheck\comparator();

        $i = 0;
        $j = 0;
        foreach ($filestodelete as $fpath) {

            // Clean the trailing \n
            $fpath = rtrim($fpath, "\n\r");

            if (!file_exists($fpath)) {
                $i++;
                continue;
            }

            unlink($fpath);
            if (!empty($options['verbose'])) {
                echo "$fpath deleted\n";
            }
            $i++;
            $j++;

            if ($i % 100 == 0) {
                $report->print_progress($i, $count, []);
            }

            if (!empty($options['limit'])) {
                if ($j > $options['limit']) {
                    break;
                }
            }
        }

        $report->print_progress($i, $count, []);

        echo $j." orphan files deleted\n";
    }

} else if ($options['mode'] == 'moveorphans') {

    if (empty($options['input'])) {
        $cachedorphans = $DB->get_record('tool_filecheck', ['userid' => 0, 'sessionid' => 0]);
        $filestomove = unserialize($cachedorphans->filestodelete);
    } else {
        // Process input file.
        if (!file_exists($options['input'])) {
            die ("Input file not exists or not readable\n");
        }

        $filestomove = file($options['input'], FILE_IGNORE_NEW_LINES & FILE_SKIP_EMPTY_LINES );
    }
    if (empty($filestomove)) {
        die("No orphans to move\n");
    } else {
        $count = count($filestomove);

        $newlocationbase = $CFG->dataroot.'/filedirbak';
        if (!is_dir($newlocationbase)) {
            mkdir($newlocationbase, 0777);
        }

        $report = new tool_filecheck\comparator();

        $i = 0;
        $j = 0;
        foreach ($filestomove as $fpath) {

            // Clean the trailing \n
            $fpath = rtrim($fpath, "\n\r");

            if (!file_exists($fpath)) {
                // No need to move.. the file is not there.
                $i++;
                continue;
            }

            // Prepare location
            $newpath = str_replace('filedir', 'filedirbak', $fpath);
            $newdir = dirname($newpath);
            if (!is_dir($newdir)) {
                echo "Creating backup dir : $newdir\n";
                mkdir($newdir, 0777, true); // Recursive.
            }

            rename($fpath, $newpath);

            $shortfpath = str_replace($CFG->dataroot.'/filedir', '', $fpath);
            if (!empty($options['verbose'])) {
                echo "$shortfpath moved to bak filedir\n";
            }
            $i++;
            $j++;

            if ($i % 100 == 0) {
                $report->print_progress($i, $count, []);
            }

            if (!empty($options['limit'])) {
                if ($j > $options['limit']) {
                    break;
                }
            }
        }

        $report->print_progress($i, $count, []);

        echo "\n".$j." orphan files moved \n";
    }

} else if ($options['mode'] == 'filetypes') {

    $r = new tool_filecheck_cli_renderer();
    $f = !empty($options['human-readable']);

    if (empty($options['contextid'])) {
        $options['contextid'] = 1;
    }
    if (empty($options['agregator'])) {
        $options['agregator'] = 'bycourse';
    }

    if (empty($options['force-decompose'])) {
        $filestatsrs = filecheck_get_filestats_recordset($options['contextid']);
        $hasdata = true;
    } else {
        echo "Recordset may be too big. You may increase max_allowed_packet in mysql\n";

        if (!empty($options['contextid'])) {
            echo "Force-decompose : Splitting strategy\n";
            // Make an array of recordsets scaning child contexts.

            $filestatsrs = [];

            $context = $DB->get_record('context', ['id' => $options['contextid']]);
            echo "Getting main context $context->id, {$context->contextlevel}/{$context->instanceid}\n";
            $filestatsrs[] = filecheck_get_filestats_recordset($options['contextid'], $exactcontext = true);

            $children = filecheck_get_subcontexts($options['contextid']);
            foreach ($children as $ctxid => $ctx) {
                echo "Getting main context $ctxid, {$ctx->contextlevel}/{$ctx->instanceid}\n";
                $filestatsrs[] = filecheck_get_filestats_recordset($ctxid);
            }
            $hasdata = true;
        }
    }

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
    if (!is_array($filestatsrs)) {
        // We have a single context and could get a recordset on it.
        if ($filestatsrs->valid()) {
            filecheck_aggregate($filestatsrs, $stats, $counterfields);
            $hasdata = true;
        }
    } else {
        // Multiple contexts
        foreach ($filestatsrs as $recordset) {
            filecheck_aggregate($recordset, $stats, $counterfields);
        }
    }

    // Render the data to console.

    if (preg_match('/bymoduletype|all/', $options['agregator'])) {

        echo "By moduletype stats\n";
        echo "Component;Q;storagesize;video;images;apps;pdf;BfQ;bigfiles\n";

        foreach ($stats->componenttotalizers as $component => $typestats) {
            $row = [];
            $row[] = $component;
            $row[] = $typestats->storagecount; // 1
            $row[] = ($f) ? $r->format_size($typestats->storage) : $typestats->storage;
            $row[] = ($f) ? $r->format_size($typestats->videostorage) : $typestats->videostorage;
            $row[] = ($f) ? $r->format_size($typestats->imagestorage) : $typestats->imagestorage;
            $row[] = ($f) ? $r->format_size($typestats->appstorage) : $typestats->appstorage;
            $row[] = ($f) ? $r->format_size($typestats->pdfstorage) : $typestats->pdfstorage; // 6
            $row[] = $typestats->bigfiles;
            $row[] = ($f) ? $r->format_size($typestats->bigfilesstorage) : $typestats->pdfstorage; // 8
            echo implode(";", $row)."\n";
        }
        echo "\n";
    }
    if (preg_match('/bycourse|all/', $options['agregator'])) {

        echo "By Course stats\n";
        echo "course;Q;storagesize;video;images;apps;pdf;BfQ;bigfiles\n";

        foreach ($stats->coursetotalizers as $course => $coursestats) {
            $row = [];
            $row[] = $DB->get_field('course', 'shortname', ['id' => $course]);
            $row[] = $coursestats->storagecount;
            $row[] = ($f) ? $r->format_size($coursestats->storage) : $coursestats->storage;
            $row[] = ($f) ? $r->format_size($coursestats->videostorage) : $coursestats->videostorage;
            $row[] = ($f) ? $r->format_size($coursestats->imagestorage) : $coursestats->imagestorage;
            $row[] = ($f) ? $r->format_size($coursestats->appstorage) : $coursestats->appstorage;
            $row[] = ($f) ? $r->format_size($coursestats->pdfstorage) : $coursestats->pdfstorage;
            $row[] = $coursestats->bigfiles;
            $row[] = ($f) ? $r->format_size($coursestats->bigfilesstorage) : $coursestats->bigfilesstorage;
            echo implode(";", $row)."\n";
        }
        echo "\n";
    }

    echo "Global totals\n";
    echo "Q;storagesize;video;images;apps;pdf;BfQ;bigfiles\n";
    $row = [];
    $row[] = $stats->totalizer->storagecount;
    $row[] = ($f) ? $r->format_size($stats->totalizer->storage) : $stats->totalizer->storage;
    $row[] = ($f) ? $r->format_size($stats->totalizer->videostorage) : $stats->totalizer->videostorage;
    $row[] = ($f) ? $r->format_size($stats->totalizer->imagestorage) : $stats->totalizer->imagestorage;
    $row[] = ($f) ? $r->format_size($stats->totalizer->appstorage) : $stats->totalizer->appstorage;
    $row[] = ($f) ? $r->format_size($stats->totalizer->pdfstorage) : $stats->totalizer->pdfstorage;
    $row[] = $stats->totalizer->bigfiles;
    $row[] = ($f) ? $r->format_size($stats->totalizer->bigfilesstorage) : $stats->totalizer->bigfilesstorage;
    echo implode(";", $row)."\n";

} else {
    die('Unsupported mode '.$options['mode']."\n");
}

die("Done.\n");