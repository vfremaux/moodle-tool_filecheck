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

// General settings.
$label = get_string('pluginname', 'tool_filecheck');
$pageurl = new moodle_url("/admin/tool/filecheck/checkfiles.php");
$ADMIN->add('server', new admin_externalpage('toolcheckfiles', $label, $pageurl));

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_filecheck', get_string('pluginname', 'tool_filecheck'));
    $ADMIN->add('tools', $settings);

    $key = 'tool_filecheck/showfileinfo';
    $label = get_string('configshowfileinfo', 'tool_filecheck');
    $desc = get_string('configshowfileinfo_desc', 'tool_filecheck');
    $default = 0;
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, $default));

    $key = 'tool_filecheck/ignoreautomatedbackupfolder';
    $label = get_string('configignoreautomatedbackupfolder', 'tool_filecheck');
    $desc = get_string('configignoreautomatedbackupfolder_desc', 'tool_filecheck');
    $default = 1;
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, $default));
}