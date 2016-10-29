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
 * Flatfile enrolments plugin settings and presets.
 *
 * @package    tool_filecheck
 * @copyright  2014 Valery Feemaux
 * @author     Valery Fremaux - based on code by Petr Skoda and others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$hasconfig = false;
$hassiteconfig = false;
if (is_dir($CFG->dirroot.'/local/adminsettings')) {
    // Integration driven code 
    if (has_capability('local/adminsettings:nobody', context_system::instance())) {
        $hasconfig = true;
        $hassiteconfig = true;
    }
    if (has_capability('moodle/site:config', context_system::instance())) {
        $hasconfig = false;
        $hassiteconfig = true;
    }
} else {
    // Standard Moodle code
    if ($hassiteconfig) {
        $hasconfig = true;
    }
}

// if ($hassiteconfig) {
if ($hasconfig) {
    //--- general settings -----------------------------------------------------------------------------------
    $ADMIN->add('server', new admin_externalpage('toolcheckfiles', get_string('pluginname', 'tool_filecheck'), new moodle_url("/admin/tool/filecheck/checkfiles.php")));
}
