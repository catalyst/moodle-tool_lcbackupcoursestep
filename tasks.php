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
 * Displays adhoc tasks for course backup.
 *
 * @package     tool_lcbackupcoursestep
 * @copyright   2024 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use \tool_lcbackupcoursestep\lifecycle\adhoc_task_table;
use \tool_lifecycle\local\manager\delayed_courses_manager;
use \tool_lifecycle\local\manager\process_manager;

require_login();
require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('tool_lcbackupcoursestep_tasks');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new \moodle_url('/admin/tool/lcbackupcoursestep/tasks.php'));
$PAGE->set_title(get_string('adhocbackupstasks', 'tool_lcbackupcoursestep'));
$PAGE->set_heading(get_string('adhocbackupstasks', 'tool_lcbackupcoursestep'));

$action = optional_param('action', '', PARAM_ALPHA);
if (!empty($action)) {
    global $DB;
    require_sesskey();

    // Params.
    $id = required_param('id', PARAM_INT);
    $processid = required_param('processid', PARAM_INT);
    $workflowid = required_param('workflowid', PARAM_INT);

    $returnurl = new \moodle_url('/admin/tool/lcbackupcoursestep/tasks.php');

    if ($action === 'delete') {
        $confirm = optional_param('confirm', 0, PARAM_INT);

        if (!$confirm) {
            $message = get_string('confirmdeletetask', 'tool_lcbackupcoursestep');
            $yesurl = new \moodle_url($PAGE->url, [
                'action' => 'delete',
                'id' => $id,
                'processid' => $processid,
                'workflowid' => $workflowid,
                'sesskey' => sesskey(),
                'confirm' => 1,
            ]);
            echo $OUTPUT->header();
            echo $OUTPUT->confirm($message, $yesurl, $returnurl);
            echo $OUTPUT->footer();
            exit();
        }

        // Check if the process still exists.
        $process = process_manager::get_process_by_id($processid);
        if ($process) {
            process_manager::rollback_process($process);
            delayed_courses_manager::set_course_delayed_for_workflow($process->courseid, true, $workflowid);
        } else {
            $DB->delete_records('task_adhoc', ['id' => $id]);
        }
    }
    redirect($returnurl);
}

echo $OUTPUT->header();

// Description of the page in an info box.
echo $OUTPUT->box_start('generalbox boxaligncenter', 'description');
echo $OUTPUT->box(get_string('adhocbackupstasksdescription', 'tool_lcbackupcoursestep'), 'description');
echo $OUTPUT->box_end();

// Show adhoc task table.
$tasktable = new adhoc_task_table();
$tasktable->define_baseurl($PAGE->url);
$tasktable->out(100, false);

echo $OUTPUT->footer();
