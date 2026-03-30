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

namespace tool_lcbackupcoursestep\task;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

use backup_controller;
use backup_plan_dbops;
use tool_lcbackupcoursestep\s3\helper;
use tool_lifecycle\settings_type;
use tool_lifecycle\local\manager\settings_manager;

/**
 * Defines task for course backup.
 *
 * @package     tool_lcbackupcoursestep
 * @copyright   2024 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_backup_s3_task extends \core\task\adhoc_task {
    /**
     * Return the component name.
     */
    public function get_component() {
        return 'tool_lcbackupcoursestep';
    }

    /**
     * Run the adhoc task and preform the backup.
     */
    public function execute() {
        global $DB;
        $customdata = $this->get_custom_data();

        // Workflow id.
        $workflowid = $customdata->workflowid;

        // Process ID of a lifecycle process.
        $processid = $customdata->processid;

        // Instance ID of the step.
        $stepinstanceid = $customdata->stepinstanceid;

        // Course ID to backup.
        $courseid = $customdata->courseid;

        // Check the IDs.
        // Check if the course exists.
        if (!$courseid || !$course = $DB->get_record('course', ['id' => $courseid])) {
            mtrace('Invalid course id: ' . $courseid . ', task aborted.');
            return;
        }
        // Check if the workflow exists.
        if (!$workflowid || !$DB->record_exists('tool_lifecycle_workflow', ['id' => $workflowid])) {
            mtrace('Invalid workflow id: ' . $workflowid . ', task aborted.');
            return;
        }

        // Process course.
        try {
            mtrace('Processing backup for course: ' . $course->fullname);
            $this->process_course($processid, $stepinstanceid, $course);
        } catch (\Exception $e) {
            mtrace('Error processing course: ' . $course->fullname . ', ' . $e->getMessage());
        }
        mtrace('Backup and s3 adhoc task for: ' . $course->fullname . ' completed.');
    }

    /**
     * Processes the course.
     *
     * @param int $processid the process id.
     * @param int $instanceid step instance id.
     * @param object $course the course object.
     */
    private function process_course($processid, $instanceid, $course) {
        $courseid = $course->id;

        raise_memory_limit(MEMORY_HUGE);

        // Get backup settings.
        $settings = settings_manager::get_settings($instanceid, settings_type::STEP);

        // Backup course.
        $bc = new backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            get_admin()->id
        );

        // Settings.
        $backupplan = $bc->get_plan();
        $keyprefix = "backup_";
        foreach ($settings as $key => $value) {
            // The keys are prefixed with backup_, check then remove.
            if (strpos($key, $keyprefix) !== 0) {
                continue;
            }

            $key = substr($key, strlen($keyprefix));
            $setting = $backupplan->get_setting($key);

            if ($setting->get_status() === \base_setting::NOT_LOCKED) {
                $setting->set_value($value);
            }
        }

        // Set the default filename.
        $format = $bc->get_format();
        $type = $bc->get_type();
        $id = $bc->get_id();
        $users = $bc->get_plan()->get_setting('users')->get_value();
        $anonymised = $bc->get_plan()->get_setting('anonymize')->get_value();
        $filename = backup_plan_dbops::get_default_backup_filename($format, $type, $id, $users, $anonymised);
        $backupplan->get_setting('filename')->set_value($filename);

        // Run backup.
        $bc->execute_plan();
        $results = $bc->get_results();

        // Copy backup file.
        $file = $results['backup_destination'];
        if (!empty($file)) {
            // Prepare file record.
            $filerecord = [
                'contextid' => \context_course::instance($courseid)->id,
                'component' => 'tool_lcbackupcoursestep',
                'filearea' => 'course_backup',
                'itemid' => $instanceid,
                'filepath' => "/",
                'filename' => $filename,
            ];

            // Save file.
            $fs = get_file_storage();
            $newfile = $fs->create_file_from_storedfile($filerecord, $file);

            // Upload file to S3.
            helper::upload_file($processid, $instanceid, $courseid, $newfile);

            // Delete file.
            $file->delete();
        }

        // Clean up.
        $bc->destroy();
        unset($bc);
    }
}
