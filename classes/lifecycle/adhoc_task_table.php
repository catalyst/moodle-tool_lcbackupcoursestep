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

namespace tool_lcbackupcoursestep\lifecycle;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/tablelib.php');

use html_writer;

/**
 * Shows the table of adhoc tasks, which show courses that will be backed up.
 *
 * @package     tool_lcbackupcoursestep
 * @copyright   2024 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhoc_task_table extends \table_sql {
    /**
     * @var array "cached" lang strings
     */
    private $strings;

    /**
     * Constructor for adhoc_task_table.
     *
     * @param array $filterdata Filter data.
     */
    public function __construct($filterdata = []) {
        parent::__construct('tool_lcbackupcoursestep-adhoc-task');

        // Action buttons string.
        $this->strings = [
            'delete' => get_string('delete'),
        ];

        // Build the SQL.
        $fields = 'ta.id,
                   ta.customdata,
                   ta.faildelay,
                   ta.nextruntime';
        $from = '{task_adhoc} ta';
        $where = 'ta.component = :component';
        $params = [
            'component' => 'tool_lcbackupcoursestep',
        ];

        $this->set_sql($fields, $from, $where, $params);

        // Headers.
        $this->define_headers([
            get_string('course_id_header', 'tool_lcbackupcoursestep'),
            get_string('course_fullname_header', 'tool_lcbackupcoursestep'),
            get_string('workflow_header', 'tool_lcbackupcoursestep'),
            get_string('next_run_header', 'tool_lcbackupcoursestep'),
            get_string('faildelay_header', 'tool_lcbackupcoursestep'),
            get_string('actions'),
        ]);

        $this->define_columns([
            'courseid',
            'coursename',
            'workflow',
            'nextruntime',
            'faildelay',
            'actions',
        ]);
    }

    /**
     * Course ID table.
     *
     * @param object $row The row.
     * @return string
     */
    public function col_courseid($row) {
        $data = json_decode($row->customdata);
        return $data->courseid;
    }

    /**
     * Course name column.
     *
     * @param object $row The row.
     * @return string
     */
    public function col_coursename($row) {
        global $DB;

        $data = json_decode($row->customdata);
        // Get the course name.
        $courseid = $data->courseid;
        if (!$course = $DB->get_record('course', ['id' => $courseid])) {
            return get_string('missing_course', 'tool_lcbackupcoursestep');
        }
        // Return the course name.
        return html_writer::link(new \moodle_url('/course/view.php', ['id' => $courseid]), $course->fullname);
    }

    /**
     * Workflow information.
     *
     * @param object $row The row.
     * @return string
     */
    public function col_workflow($row) {
        global $DB;

        // Find the workflow.
        $data = json_decode($row->customdata);
        $result = $DB->get_record('tool_lifecycle_workflow', ['id' => $data->workflowid]);

        // If no workflow is found, return missing workflow or process message.
        if (!$result) {
            return get_string('missing_workflow', 'tool_lcbackupcoursestep');
        }

        // If workflow is found, return the workflow name and url to the workflow.
        $url = new \moodle_url('/admin/tool/lifecycle/workflowoverview.php?', ['wf' => $result->id]);
        return html_writer::link($url, $result->title, ['class' => 'workflow-link']);
    }

    /**
     * Next run column.
     *
     * @param object $row The row.
     * @return string
     */
    public function col_nextruntime($row) {
        return userdate($row->nextruntime);
    }

    /**
     * Fail delay column.
     *
     * @param object $row The row.
     * @return string
     */
    public function col_faildelay($row) {
        return $row->faildelay;
    }

    /**
     * Actions column.
     *
     * @param object $row The row.
     * @return string
     */
    public function col_actions($row) {
        global $OUTPUT;

        // Only show the delete action if the fail delay is greater than 0.
        if ($row->faildelay > 0) {
            $data = json_decode($row->customdata);
            $actionmenu = new \action_menu();
            $actionmenu->add_primary_action(
                new \action_menu_link_primary(
                    new \moodle_url('', [
                        'action' => 'delete',
                        'id' => $row->id,
                        'sesskey' => sesskey(),
                        'processid' => $data->processid,
                        'workflowid' => $data->workflowid,
                    ]),
                    new \pix_icon('t/delete', $this->strings['delete']),
                    $this->strings['delete']
                )
            );
            return $OUTPUT->render($actionmenu);
        }
        return '';
    }
}
