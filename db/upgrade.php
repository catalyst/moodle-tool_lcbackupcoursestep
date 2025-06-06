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

    $dbman = $DB->get_manager();

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

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2023100401, 'tool', 'lcbackupcoursestep');
    }

    if ($oldversion < 2023100402) {

        // Define field bucketname to be added to tool_lcbackupcoursestep_s3.
        $table = new xmldb_table('tool_lcbackupcoursestep_s3');

        // Conditionally launch add field bucketname.
        $field = new xmldb_field('bucketname', XMLDB_TYPE_CHAR, '512', null, null, null, null, 'contenthash');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Conditionally launch add field timecreated.
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'bucketname');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Lcbackupcoursestep savepoint reached.
        upgrade_plugin_savepoint(true, 2023100402, 'tool', 'lcbackupcoursestep');
    }

    if ($oldversion < 2024101000) {

        $table = new xmldb_table('tool_lcbackupcoursestep_meta');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fullname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('oldcourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fileid_fk', XMLDB_KEY_FOREIGN, ['fileid'], 'files', ['id']);

        if (!$DB->get_manager()->table_exists($table)) {
            $DB->get_manager()->create_table($table);
        }

        $sql1 = "
            INSERT INTO {tool_lcbackupcoursestep_meta} (shortname, fullname, oldcourseid, fileid, timecreated)
            SELECT crs.shortname,
                   crs.fullname,
                   crs.id AS oldcourseid,
                   f.id AS fileid,
                   f.timecreated
              FROM {files} f
              JOIN {context} ctx ON ctx.id = f.contextid
              JOIN {course} crs ON ctx.instanceid = crs.id
             WHERE ctx.contextlevel = :contextlevel
               AND f.component = :component
               AND f.filearea = :filearea
               AND f.filesize > 0
        ";
        $DB->execute($sql1, [
                'contextlevel' => CONTEXT_COURSE,
                'component' => 'tool_lcbackupcoursestep',
                'filearea' => 'course_backup',
        ]);

        $sql2 = "
            UPDATE {files}
               SET contextid = :contextid
             WHERE id IN (
                    SELECT id FROM (
                        SELECT f.id
                          FROM {files} f
                          JOIN {context} ctx ON ctx.id = f.contextid
                         WHERE ctx.contextlevel = :contextlevel
                           AND f.component = :component
                           AND f.filearea = :filearea
                       ) as fs
                  )
        ";
        $DB->execute($sql2, [
                'contextid' => \context_system::instance()->id,
                'contextlevel' => CONTEXT_COURSE,
                'component' => 'tool_lcbackupcoursestep',
                'filearea' => 'course_backup',
        ]);

        upgrade_plugin_savepoint(true, 2024101000, 'tool', 'lcbackupcoursestep');
    }
    if ($oldversion < 2025021900) {
        $table = new xmldb_table('tool_lcbackupcoursestep_metadata');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'tool_lcbackupcoursestep_meta');
        }

        // Extra step mandatory due to the state of the plugin before this upgrade, with 2 branch having different functionality.
        $table = new xmldb_table('tool_lcbackupcoursestep_s3');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('processid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('bucketname', XMLDB_TYPE_CHAR, '512', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('processid_idx', XMLDB_INDEX_NOTUNIQUE, ['processid']);
        $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025021900, 'tool', 'lcbackupcoursestep');
    }

    return true;
}
