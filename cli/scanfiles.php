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
                                                     'debug' => false,
                                                     'host' => false,
                                                     'output' => false),
                                               array('h' => 'help',
                                                     'H' => 'host',
                                                     'm' => 'mode',
                                                     'd' => 'debug',
                                                     'O' => 'output')
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
-m, --mode            Mode ('orphans', 'deleteorphans', or 'filetypes')
-d, --debug           Debug mode
-O, --output          Output file.

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

if (empty($options['mode'])) {
    $options['mode'] = 'filetypes';
}

if (!empty($options['debug'])) {
    $CFG->debug = DEBUG_DEVELOPER;
}

if ($options['mode'] == 'getorphans') {
    // If mode is orphan, scans the file system and records an orphan list to be further processed by "deleteorphans"
    $stats = [];
    $report = new tool_filecheck\comparator($stats);
    $cache = filecheck_cli_store_options($report->orphanedfiles);

    if (empty($report->orphanedfiles)) {
        die("No orphans found\n");
    } else {
        $bytes = 0;
        $unit = 0;

        foreach ($report->orphanedfiles as $f) {

            if ($unit == 0) {
                $bytes += $f['filesize'];
            } else {
                $bytes += $f['filesize'] / FILECHECK_MEGABYTE;
            }
            if ($unit = 0 && $bytes > FILECHECK_MEGABYTE) {
                $unit = 1;
                $bytes = $bytes / FILECHECK_MEGABYTE;
            }
            echo $f['filepath'].'/'.$f['filename']."\n";

        }

        echo $stats['count']." orphan files found\n";
        echo tool_filecheck_cli_format_size($bytes)." orphan bytes found\n";
    }
} else if ($options['mode'] == 'listorphans') {

    // Get cli cache.
    $cachedorphans = $DB->get_record('tool_filecheck', ['userid' => 0, 'sessionid' => 0]);
    $filestodelete = unserialize($cachedorphans->filestodelete);
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

    $cachedorphans = $DB->get_record('tool_filecheck', ['userid' => 0, 'sessionid' => 0]);
    $filestodelete = unserialize($cachedorphans->filestodelete);
    if (empty($filestodelete)) {
        die("No orphans to delete\n");
    } else {
        $count = count($filestodelete);

        foreach ($filestodelete as $fpath) {
            unlink($fpath);
            echo "$fpath deleted\n";
        }

        echo $count." orphan files deleted\n";
    }

} else if ($options['mode'] == 'filetypes') {

} else {
    die('Unsupported mode '.$options['mode']."\n");
}

die("Done.\n");