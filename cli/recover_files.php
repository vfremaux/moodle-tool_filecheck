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
 *
 * This script tries to recover missing files in a specific context (and its subcontexts)
 * from backup locations. Backup locations are files stubs organised as filedir, containin trashed
 * files or backuped filesets. Recover process will try to find a matching file in any of those
 * filesets and copy it back into moodledata.
 *
 * @package     tool_filecheck
 * @category    tool
 * @copyright   2016 Valery Fremaux <valery@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$recover_locations = [
];

global $CLI_VMOODLE_PRECHECK;

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);
$CLI_VMOODLE_PRECHECK = true; // Force first config to be minimal.

require(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/lib/clilib.php'); // Cli only functions.

// Now get cli options.

list($options, $unrecognized) = cli_get_params(array('help' => false,
                                                     'contextid' => false,
                                                     'host' => false,
                                                     'debug' => false,
                                                     'verbose' => false,
                                                     'run' => false,
                                                     'limit' => false),
                                               array('h' => 'help',
                                                     'c' => 'contextid',
                                                     'H' => 'host',
                                                     'r' => 'run',
                                                     'd' => 'debug',
                                                     'v' => 'verbose',
                                                     'l' => 'limit')
                                               );

if ($unrecognized) {
    $unrecognized = implode("\n ", $unrecognized);
    cli_error($unrecognized. " is not a recognized option\n");
}

if ($options['help']) {
    $help = "
Recover files from some locations. Locations must be hard encoded at start of the script. Edit
the script to locate recover sources.

Options:
-h, --help            Print out this help
-H, --host            The virtual moodle to play for. Main moodle install if missing.
-c, --contextid       Restrict to context sub path
-r, --run             Run it really.
-v, --verbose         Verbose mode
-d, --debug           Debug mode
-l, --limit           Limits the number of processed entries (move or delete orphans).

Example:
sudo -uwww-data php admin/tool/filecheck/cli/recover_files.php --host=<hostwwwroot> --run --verbose
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

// Add local trash as standard.
if (!in_array($CFG->dataroot.'/trashdir', $recover_locations)) {
    $recover_locations[] = $CFG->dataroot.'/trashdir';
}

if (empty($options['contextid'])) {
    die ("You must provide a context id. Use get_context tool to find the context for a course or coursecategory\n");
}

if (empty($options['run'])) {
    echo "Running in DRY MODE\n";
}

$context = context::instance_by_id($options['contextid']);

if (!empty($options['debug'])) {
    echo "Debug checks :\n";
    echo "\tTarget dir : {$CFG->dataroot}\n";

    switch($sub->contextlevel) {
        case CONTEXT_COURSECAT : {
            $contexttable = "course_categories";
            break;
        }
        case CONTEXT_COURSE : {
            $contexttable = "course";
            break;
        }
        case CONTEXT_MODULE : {
            $contexttable = "course_modules";
            break;
        }
        case CONTEXT_BLOCK : {
            $contexttable = "block_instances";
            break;
        }

        default : {
            die("Error : unknown or unsupported context level\n");
        }

        $object = $DB->get_record($contexttable, ['id' => $context->instanceid]);
        echo "Starting context : \n";
        print_object($object);
    }
}


$fs = get_file_storage();
$C = 0;
$R = 0;
$F = 0;

// Scan context proper files.

$select = " filesize > 0 AND contextid = :contextid ";
$filesrecs = $DB->get_records_select('files', $select, ['contextid' => $context->id]);

$report = recover_files($filesrecs, $recover_locations, $options, $C, $R, $F);

// Get all sub contexts
$select = ' path LIKE ? ';
$subcontexts = $DB->get_records_select('context', $select, [$context->path.'/%']);

// Process subcontexts
if (!empty($subcontexts)) {
    $subcount = count($subcontexts);

    echo "Subcontexts to recover : $subcount\n";

    foreach ($subcontexts as $sub) {
        if (!empty($options['verbose'])) {
            switch($sub->contextlevel) {
                case CONTEXT_COURSECAT : {
                    $contextlvl = "course category";
                    break;
                }
                case CONTEXT_COURSE : {
                    $contextlvl = "course ";
                    break;
                }
                case CONTEXT_MODULE : {
                    $contextlvl = "course module ";
                    break;
                }
                case CONTEXT_BLOCK : {
                    $contextlvl = "block ";
                    break;
                }

                default : {
                    $contextlvl = die("Error : unknown context\n");
                }
            }
            echo "Recovering $contextlvl subcontext $sub->path\n";
        }
        $select = " filesize > 0 AND contextid = :contextid ";
        $filesrecs = $DB->get_records_select('files', $select, ['contextid' => $sub->id]);
        $report .= recover_files($filesrecs, $recover_locations, $options, $C, $R, $F);
    }
}

if (!empty($options['verbose'])) {
    echo $report."\n";
}

echo "\n";
echo "OVERALL RECOVER STATS\n";
echo "To recover : $C\n";
echo "Recovered : $R\n";
echo "Failed : $F\n";
echo "\n";

echo "Done.\n";
exit(0);

// functions

/**
 * Recover file from some absolute locations in server.
 * @param $filerecs files to recover (as DB recs)
 * @param $recover_locations (as absolute paths in server)
 * @param $options script options
 */
function recover_files($filerecs, $recover_locations, $options, &$C, &$R, &$F) {
    global $CFG;

    if (empty($filerecs) && !empty($options['verbose'])) {
        echo "No files in context\n";
        return;
    }

    $report = '';
    $filestorecover = count($filerecs);
    $C += $filestorecover;
    $r = 0;
    $f = 0;

    foreach ($filerecs as $frec) {
        $contenthash = $frec->contenthash;
        preg_match('/^([0-9-a-fA-F]{2})([0-9-a-fA-F]{2})/', $contenthash, $matches);
        $dir1 = $matches[1];
        $dir2 = $matches[2];

        $wantedfile = $CFG->dataroot.'/filedir/'.$dir1.'/'.$dir2.'/'.$contenthash;
        if (file_exists($wantedfile)) {
            $report .= $contenthash." : exists in repo\n";
            $r++;
            continue;
        }

        // File is NOT in repo, try recover locations
        foreach ($recover_locations as $recloc) {
            $reclocfile = $recloc.'/'.$dir1.'/'.$dir2.'/'.$contenthash;
            if (file_exists($reclocfile)) {
                if (!empty($options['run'])) {
                    copy($reclocfile, $wantedfile);
                    $report .= $contenthash." recovered from $recloc\n";
                    $r++;
                    continue 2;
                } else {
                    $report .= "DRYRUN: ".$contenthash." will be recovered from $recloc\n";
                    $r++;
                    continue 2;
                }
            }
        }

        // No source for recovering.
        $report .= $contenthash." could not be recovered\n";
        $f++;
    }

    $R += $r;
    $F += $f;

    echo "\n";
    echo "To recover : $filestorecover\n";
    echo "Recovered : $r\n";
    echo "Failed : $f\n";
    echo "\n";

    return $report;
}