<?php
// This file is part of the File Trash report by Barry Oosthuizen - http://elearningstudio.co.uk
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
 * report_filetrash upgrade.
 *
 * @package   tool_filecheck
 * @copyright 2013 Barry Oosthuizen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade script
 *
 * @param int $oldversion
 * @return boolean
 */
function xmldb_tool_filecheck_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2021120600) {

        $table = new xmldb_table('tool_filecheck');

        // Adding fields to table local_shop.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('filestodelete', XMLDB_TYPE_TEXT, 'long', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sessionid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);

        // Adding keys to table local_shop.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('usersessid_ix', XMLDB_KEY_UNIQUE, array('userid, sessionid'));

        // Conditionally launch create table for local_shop.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Filecheck savepoint reached.
        upgrade_plugin_savepoint(true, 2021120600, 'tool', 'filecheck');
    }
    return true; // The upgrade is complete.
}
