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
 * Trigger test for end date delay trigger.
 *
 * @package     tool_lcbackupcoursestep
 * @copyright   2024 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lcbackupcoursestep;

use backup;
use restore_controller;
use restore_dbops;
use tool_lifecycle\action;
use tool_lifecycle\local\entity\step_subplugin;
use tool_lifecycle\local\entity\trigger_subplugin;
use tool_lifecycle\local\manager\process_manager;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\manager\trigger_manager;
use tool_lifecycle\local\manager\workflow_manager;
use tool_lifecycle\processor;
use tool_lifecycle\settings_type;

/**
 * Trigger test for start date delay trigger.
 *
 * @package    tool_lcbackupcoursestep
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step_test extends \advanced_testcase {
    /** Icon of the manual trigger. */
    const MANUAL_TRIGGER1_ICON = 't/up';

    /** Display name of the manual trigger. */
    const MANUAL_TRIGGER1_DISPLAYNAME = 'Up';

    /** Capability of the manual trigger. */
    const MANUAL_TRIGGER1_CAPABILITY = 'moodle/course:manageactivities';

    /** @var trigger_subplugin $trigger Instances of the triggers under test. */
    private $trigger;

    /** @var step_subplugin $trigger Instances of the triggers under test. */
    private $step;

    /** @var \stdClass $course Instance of the course under test. */
    private $course;

    /** @var \stdClass $student Instance of the student user. */
    private $student;

    /**
     * Set up the test.
     */
    public function setUp(): void {
        global $USER, $CFG;

        // We do not need a sesskey check in these tests.
        $USER->ignoresesskey = true;

        // Create manual workflow.
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_lifecycle');
        $triggersettings = new \stdClass();
        $triggersettings->icon = self::MANUAL_TRIGGER1_ICON;
        $triggersettings->displayname = self::MANUAL_TRIGGER1_DISPLAYNAME;
        $triggersettings->capability = self::MANUAL_TRIGGER1_CAPABILITY;
        $manualworkflow = $generator->create_manual_workflow($triggersettings);

        // Trigger.
        $this->trigger = trigger_manager::get_triggers_for_workflow($manualworkflow->id)[0];

        // Step.
        $this->step = $generator->create_step("instance1", "tool_lcbackupcoursestep", $manualworkflow->id);
        settings_manager::save_settings($this->step->id, settings_type::STEP, "tool_lcbackupcoursestep",
            [
                "backup_users" => true,
                "backup_anonymize" => false,
                "backup_role_assignments" => true,
                "backup_activities" => true,
                "backup_blocks" => true,
                "backup_files" => true,
                "backup_filters" => true,
                "backup_comments" => true,
                "backup_badges" => true,
                "backup_calendarevents" => true,
                "backup_userscompletion" => true,
                "backup_logs" => true,
                "backup_grade_histories" => true,
                "backup_questionbank" => true,
                "backup_groups" => true,
                "backup_competencies" => true,
                "backup_contentbankcontent" => true,
                "backup_legacyfiles" => true,
            ]
        );

        // Course.
        $this->course = $this->getDataGenerator()->create_course();

        // Create and enrol a student in the course.
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id);

        // Create an assigment in the course.
        $this->getDataGenerator()->create_module('assign', [
            'name' => "Test assign",
            'course' => $this->course->id,
        ]);

        // Activate the workflow.
        workflow_manager::handle_action(action::WORKFLOW_ACTIVATE, $manualworkflow->id);
    }

    /**
     * Test course is backed up.
     * @covers \tool_lcbackupcoursestep\lifecycle\step::process_course
     */
    public function test_backup_course_step() {
        global $DB, $CFG;

        $this->resetAfterTest();

        // Run trigger.
        process_manager::manually_trigger_process($this->course->id, $this->trigger->id);

        // Run processor.
        $processor = new processor();
        $processor->process_courses();

        // Check that the log file is created.
        $contextid = \context_system::instance()->id;
        $sql = "contextid = :contextid
               AND component = :component
               AND filearea = :filearea
               AND filepath = :filepath
               AND filename <> :filename";
        $file = $DB->get_record_select('files', $sql, [
                'contextid' => $contextid,
                'component' => 'tool_lcbackupcoursestep',
                'filearea' => 'course_backup',
                'filepath' => '/',
                'filename' => '.',
            ]
        );

        // File existence.
        $this->assertNotEmpty($file);

        // Check file name pattern.
        $this->assertThat($file->filename, $this->logicalAnd(
            $this->isType('string'),
            $this->matchesRegularExpression('/^backup-moodle2-course-'
                . $this->course->id . '-'
                . $this->course->shortname
                . '-\d{8}-\d{4}.*\.mbz$/')
        ));

        // Extract backup file.
        $fs = get_file_storage();
        $storedfile = $fs->get_file_by_id($file->id);

        $backupdir = "restore_" . uniqid();
        $path = $CFG->tempdir . DIRECTORY_SEPARATOR . "backup" . DIRECTORY_SEPARATOR . $backupdir;
        $storedfile->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $path);

        // Restore course.
        list($fullname, $shortname) = restore_dbops::calculate_course_names(0, get_string('restoringcourse', 'backup'),
            get_string('restoringcourseshortname', 'backup'));

        // A category to restore to.
        $category = $this->getDataGenerator()->create_category();
        $courseid = restore_dbops::create_new_course($fullname, $shortname, $category->id);

        // Run the restoration.
        $rc = new restore_controller($backupdir, $courseid, backup::INTERACTIVE_NO,
            backup::MODE_GENERAL, get_admin()->id, backup::TARGET_NEW_COURSE);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Clean up.
        fulldelete($path);

        // Check if course is restored.
        $this->assertNotEmpty($DB->get_record('course', ['id' => $courseid]));

        // Student is enrolled in the course.
        $newcoursecontext = \context_course::instance($courseid);
        $this->assertTrue(is_enrolled($newcoursecontext, $this->student->id));

        // There is an assignment in the course.
        $this->assertNotEmpty($DB->get_record('assign', ['course' => $courseid]));

        // Check the metadata table for the file backup entry.
        $metadata = $DB->get_record('tool_lcbackupcoursestep_meta', ['fileid' => $file->id]);
        $this->assertNotEmpty($metadata);

        // Check metadata details.
        $this->assertEquals($this->course->id, $metadata->oldcourseid);
        $this->assertEquals($this->course->shortname, $metadata->shortname);
    }

    /**
     * Backup file is recorded after pushed to S3.
     *
     * @covers \tool_lcbackupcoursestep\lifecycle\step::process_course
     */
    public function test_backup_course_step_s3() {
        global $DB, $CFG;

        $this->resetAfterTest();

        // Skip test if AWS SDK is not installed.
        if (!file_exists($CFG->dirroot . '/local/aws/version.php')) {
            $this->markTestSkipped('AWS SDK is not installed.');
        }

        settings_manager::save_settings($this->step->id, settings_type::STEP, "tool_lcbackupcoursestep",
            [
                'uses3' => true,
                's3_bucket' => 'testbucket',
                's3_key' => 'testkey',
                's3_secret' => 'testsecret',
                's3_region' => 'testregion',
                's3_useproxy' => false,
            ]
        );

        // Run trigger.
        $process = process_manager::manually_trigger_process($this->course->id, $this->trigger->id);

        // Run processor.
        $processor = new processor();
        $processor->process_courses();

        // Get the file record.
        $contextid = \context_course::instance($this->course->id)->id;
        $sql = "contextid = :contextid
               AND component = :component
               AND filearea = :filearea
               AND filepath = :filepath
               AND filename <> :filename";
        $file = $DB->get_record_select('files', $sql, [
                'contextid' => $contextid,
                'component' => 'tool_lcbackupcoursestep',
                'filearea' => 'course_backup',
                'filepath' => '/',
                'filename' => '.',
            ]
        );
        $this->assertNotEmpty($file);

        // Check file record is saved.
        $filedetails = $DB->get_record('tool_lcbackupcoursestep_s3', ['processid' => $process->id]);
        $this->assertEquals($filedetails->courseid, $this->course->id);
        $this->assertEquals($filedetails->filename, $file->filename);
    }
}
