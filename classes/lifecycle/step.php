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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/lifecycle/step/lib.php');

use admin_externalpage;
use backup_plan_dbops;
use moodle_url;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\response\step_response;
use tool_lifecycle\settings_type;
use tool_lifecycle\step\instance_setting;
use tool_lifecycle\step\libbase;

/**
 * Defines the backup course step.
 *
 * @package     tool_lcbackupcoursestep
 * @copyright   2024 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step extends libbase {
    /**
     * Returns the subplugin name.
     *
     * @return string
     */
    public function get_subpluginname() {
        return 'tool_lcbackupcoursestep';
    }


    /**
     * Returns the description.
     *
     * @return string
     */
    public function get_plugin_description() {
        return "Backup course";
    }

    /**
     * Processes the course.
     *
     * @param int $processid the process id.
     * @param int $instanceid step instance id.
     * @param object $course the course object.
     * @return step_response
     */
    public function process_course($processid, $instanceid, $course) {
        $courseid = $course->id;

        // Get backup settings.
        $settings = settings_manager::get_settings($instanceid, settings_type::STEP);

        // Backup course.
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $courseid, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, get_admin()->id);

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
            $fs->create_file_from_storedfile($filerecord, $file);

            // Delete file.
            $file->delete();
        }

        // Clean up.
        $bc->destroy();
        unset($bc);

        return step_response::proceed();
    }

    /**
     * Returns the instance settings.
     *
     * @return array
     */
    public function instance_settings() {
        return [
            new instance_setting('backup_users', PARAM_BOOL, true),
            new instance_setting('backup_anonymize', PARAM_BOOL, true),
            new instance_setting('backup_role_assignments', PARAM_BOOL, true),
            new instance_setting('backup_activities', PARAM_BOOL, true),
            new instance_setting('backup_blocks', PARAM_BOOL, true),
            new instance_setting('backup_files', PARAM_BOOL, true),
            new instance_setting('backup_filters', PARAM_BOOL, true),
            new instance_setting('backup_comments', PARAM_BOOL, true),
            new instance_setting('backup_badges', PARAM_BOOL, true),
            new instance_setting('backup_calendarevents', PARAM_BOOL, true),
            new instance_setting('backup_userscompletion', PARAM_BOOL, true),
            new instance_setting('backup_logs', PARAM_BOOL, true),
            new instance_setting('backup_grade_histories', PARAM_BOOL, true),
            new instance_setting('backup_questionbank', PARAM_BOOL, true),
            new instance_setting('backup_groups', PARAM_BOOL, true),
            new instance_setting('backup_competencies', PARAM_BOOL, true),
            new instance_setting('backup_contentbankcontent', PARAM_BOOL, true),
            new instance_setting('backup_legacyfiles', PARAM_BOOL, true),
        ];
    }

    /**
     * Adds the instance form definition.
     *
     * @param \moodleform $mform the form.
     */
    public function extend_add_instance_form_definition($mform) {
        // Backup settings.

        // Users.
        $mform->addElement('advcheckbox', 'backup_users', get_string('generalusers', 'backup'));
        $mform->setType('backup_users', PARAM_BOOL);
        $mform->setDefault('backup_users', true);

        // Anonymize.
        $mform->addElement('advcheckbox', 'backup_anonymize', get_string('generalanonymize', 'backup'));
        $mform->setType('backup_anonymize', PARAM_BOOL);
        $mform->setDefault('backup_anonymize', false);

        // Role assignments.
        $mform->addElement('advcheckbox', 'backup_role_assignments', get_string('generalroleassignments', 'backup'));
        $mform->setType('backup_role_assignments', PARAM_BOOL);
        $mform->setDefault('backup_role_assignments', true);

        // Activities.
        $mform->addElement('advcheckbox', 'backup_activities', get_string('generalactivities', 'backup'));
        $mform->setType('backup_activities', PARAM_BOOL);
        $mform->setDefault('backup_activities', true);

        // Blocks.
        $mform->addElement('advcheckbox', 'backup_blocks', get_string('generalblocks', 'backup'));
        $mform->setType('backup_blocks', PARAM_BOOL);
        $mform->setDefault('backup_blocks', true);

        // Files.
        $mform->addElement('advcheckbox', 'backup_files', get_string('generalfiles', 'backup'));
        $mform->setType('backup_files', PARAM_BOOL);
        $mform->setDefault('backup_files', true);

        // Filters.
        $mform->addElement('advcheckbox', 'backup_filters', get_string('generalfilters', 'backup'));
        $mform->setType('backup_filters', PARAM_BOOL);
        $mform->setDefault('backup_filters', true);

        // Comments.
        $mform->addElement('advcheckbox', 'backup_comments', get_string('generalcomments', 'backup'));
        $mform->setType('backup_comments', PARAM_BOOL);
        $mform->setDefault('backup_comments', true);

        // Badges.
        $mform->addElement('advcheckbox', 'backup_badges', get_string('generalbadges', 'backup'));
        $mform->setType('backup_badges', PARAM_BOOL);
        $mform->setDefault('backup_badges', true);

        // Calendar events.
        $mform->addElement('advcheckbox', 'backup_calendarevents', get_string('generalcalendarevents', 'backup'));
        $mform->setType('backup_calendarevents', PARAM_BOOL);
        $mform->setDefault('backup_calendarevents', true);

        // Users completion.
        $mform->addElement('advcheckbox', 'backup_userscompletion', get_string('generaluserscompletion', 'backup'));
        $mform->setType('backup_userscompletion', PARAM_BOOL);
        $mform->setDefault('backup_userscompletion', true);

        // Logs.
        $mform->addElement('advcheckbox', 'backup_logs', get_string('generallogs', 'backup'));
        $mform->setType('backup_logs', PARAM_BOOL);
        $mform->setDefault('backup_logs', true);

        // Grade histories.
        $mform->addElement('advcheckbox', 'backup_grade_histories', get_string('generalhistories', 'backup'));
        $mform->setType('backup_grade_histories', PARAM_BOOL);
        $mform->setDefault('backup_grade_histories', true);

        // Question bank.
        $mform->addElement('advcheckbox', 'backup_questionbank', get_string('generalquestionbank', 'backup'));
        $mform->setType('backup_questionbank', PARAM_BOOL);
        $mform->setDefault('backup_questionbank', true);

        // Groups.
        $mform->addElement('advcheckbox', 'backup_groups', get_string('generalgroups', 'backup'));
        $mform->setType('backup_groups', PARAM_BOOL);
        $mform->setDefault('backup_groups', true);

        // Competencies.
        $mform->addElement('advcheckbox', 'backup_competencies', get_string('generalcompetencies', 'backup'));
        $mform->setType('backup_competencies', PARAM_BOOL);
        $mform->setDefault('backup_competencies', true);

        // Content bank.
        $mform->addElement('advcheckbox', 'backup_contentbankcontent', get_string('generalcontentbankcontent', 'backup'));
        $mform->setType('backup_contentbankcontent', PARAM_BOOL);
        $mform->setDefault('backup_contentbankcontent', true);

        // Legacy files.
        $mform->addElement('advcheckbox', 'backup_legacyfiles', get_string('generallegacyfiles', 'backup'));
        $mform->setType('backup_legacyfiles', PARAM_BOOL);
        $mform->setDefault('backup_legacyfiles', true);
    }

    /**
     * Returns the instance settings.
     *
     * @return void
     */
    public function get_plugin_settings() {
        global $ADMIN;
        // Page to show the list of backed up courses.
        $ADMIN->add('lifecycle_category', new admin_externalpage('tool_lcbackupcoursestep_courses',
            get_string('backedupcourses', 'tool_lcbackupcoursestep'),
            new moodle_url('/admin/tool/lcbackupcoursestep/courses.php')));
    }
}
