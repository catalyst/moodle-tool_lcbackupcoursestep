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
 * Upgrade script for tool_lcbackupcoursestep.
 *
 * This script handles any necessary upgrades to the tool_lcbackupcoursestep plugin.
 *
 * @package   tool_lcbackupcoursestep
 * @copyright 2024 Catalyst
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Performs the upgrade steps for tool_lcbackupcoursestep.
 *
 * This function manages all upgrade tasks such as updating database schema,
 * data migrations, or other necessary steps for upgrading to a new version.
 *
 * @param int $oldversion The version of the plugin we are upgrading from.
 * @return bool True if the upgrade was successful, false otherwise.
 */
function xmldb_tool_lcbackupcoursestep_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2023100401) {
        // New table to be created.
        $table = new xmldb_table('tool_lcbackupcoursestep_s3');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('processid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('processid_idx', XMLDB_INDEX_NOTUNIQUE, ['processid']);
        $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        if (!$DB->get_manager()->table_exists($table)) {
            $DB->get_manager()->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2023100401, 'tool', 'lcbackupcoursestep');
    }

    return true;
}
