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
 * Settings page which gives an overview over running lifecycle processes.
 *
 * @package     tool_lcbackupcoursestep
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_lifecycle\local\manager\lib_manager;
use tool_lifecycle\local\manager\step_manager;
use tool_lifecycle\local\manager\trigger_manager;

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    // Lifecycle page has been refactored with the usage of tabs.
    // Changes the below 2 pages into tabs will take significant effort on this plugin and lifecycle plugin.
    // Hence, I leave them as external page for now.
    // TODO: turn these 2 pages into tabs in lifecylce page.

    // Create new category for the plugin.
    $category = new admin_category(
        'tool_lcbackupcoursestep',
        get_string('pluginname', 'tool_lcbackupcoursestep')
    );
    $ADMIN->add('tools', $category);

    // Page to show the list of backed up courses.
    $ADMIN->add('tool_lcbackupcoursestep', new admin_externalpage(
        'tool_lcbackupcoursestep_courses',
        get_string('backedupcourses', 'tool_lcbackupcoursestep'),
        new moodle_url('/admin/tool/lcbackupcoursestep/courses.php')
    ));

    // Page to show the list of adhoc tasks.
    $ADMIN->add('tool_lcbackupcoursestep', new admin_externalpage(
        'tool_lcbackupcoursestep_tasks',
        get_string('adhoc_tasks', 'tool_lcbackupcoursestep'),
        new moodle_url('/admin/tool/lcbackupcoursestep/tasks.php')
    ));
}
