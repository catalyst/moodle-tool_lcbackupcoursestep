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
 * String definitions for the tool_lcbackupcoursestep plugin.
 *
 * @package     tool_lcbackupcoursestep
 * @copyright   2024 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Adhoc course backup step';
$string['plugindescription'] = 'Backup courses using adhoc tasks.';
$string['privacy:metadata'] = 'The plugin does not store any personal data.';
$string['adhoc_tasks'] = 'Adhoc tasks for course backup (tool_lcbackupcoursestep)';
$string['adhocbackupstasks'] = 'Pending adhoc tasks for course backup';
$string['adhocbackupstasksdescription'] = 'This page show a list of adhoc tasks that are waiting to be processed. You can also delete any failed task if required.';
$string['backedupcourses'] = 'List of backed up courses (tool_lcbackupcoursestep)';
$string['backupsettings'] = 'Backup settings';
$string['confirmdeletetask'] = 'Are you sure you want to delete this task?';
$string['course_id_header'] = 'Course ID';
$string['course_shortname_header'] = 'Course short name';
$string['course_fullname_header'] = 'Course full name';
$string['filename_header'] = 'File name';
$string['filesize_header'] = 'File size';
$string['createdat_header'] = 'Created at';
$string['faildelay_header'] = 'Fail delay';
$string['actions_header'] = 'Actions';
$string['missing_course'] = 'Missing course';
$string['missing_workflow'] = 'Missing workflow';
$string['next_run_header'] = 'Next run time';
$string['s3_bucket'] = 'Bucket';
$string['s3_connection_error'] = 'Connection error: {$a}';
$string['s3_connection_success'] = 'Connection successful';
$string['s3_key'] = 'Key';
$string['s3_region'] = 'Region';
$string['s3_secret'] = 'Secret';
$string['s3_unmet_dependency'] = 'This Amazon S3 feature is optional and requires the local_aws plugin to be installed.';
$string['s3_useproxy'] = 'Use proxy';
$string['s3_usesdkcreds'] = 'Use the default credential provider chain to find AWS credentials';
$string['s3settings'] = 'Amazon S3 settings';
$string['taskfailed'] = 'Adhoc task failed';
$string['uses3'] = 'Push backups to Amazon S3 bucket';
$string['workflow_header'] = 'Workflow';
