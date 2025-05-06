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
                                                     'contextlevel' => false,
                                                     'instanceid' => false,
                                                     'host' => false,
                                                     'debug' => false),
                                               array('h' => 'help',
                                                     'l' => 'contextlevel',
                                                     'i' => 'instanceid',
                                                     'H' => 'host',
                                                     'd' => 'debug')
                                               );

if ($unrecognized) {
    $unrecognized = implode("\n ", $unrecognized);
    cli_error($unrecognized. " is not a recognized option\n");
}

if ($options['help']) {
    $help = "
Utility to get a context id from instances.

Options:
-h, --help            Print out this help
-H, --host            The virtual moodle to play for. Main moodle install if missing.
-l, --contextlevel    One of 'system', 'coursecat', 'course', 'module', 'block', 'user' or given by numeric level.
-i, --instanceid      Instance id
-d, --debug           Debug mode

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

if (empty($options['contextlevel'])) {
    die("Give a contexte level \n");
}

if (empty($options['instanceid'])) {
    die("Give an instance id \n");
}

if (is_integer($options['instanceid'])) {
    die("Instanceid must be integer \n");
}

$levels = ['system', 'coursecat', 'course', 'module', 'block', 'user'];

if (!is_numeric($options['contextlevel'])) {
    if (in_array($options['contextlevel'], $levels)) {
        switch($options['contextlevel']) {
            case 'system' : {
                $ctxlevel = 10;
                break;
            }
            case 'user' : {
                $ctxlevel = 20;
                break;
            }
            case 'coursecat' : {
                $ctxlevel = 40;
                break;
            }
            case 'course' : {
                $ctxlevel = 50;
                break;
            }
            case 'module' : {
                $ctxlevel = 70;
                break;
            }
            case 'block' : {
                $ctxlevel = 80;
                break;
            }
        }
    }
} else {
    $ctxlevel = $options['contextlevel'];
}

$context = $DB->get_record('context', ['contextlevel' => $ctxlevel, 'instanceid' => $options['instanceid']]);
if (!$context) {
    die("Not found\n");
}
echo $context->id."\n";
