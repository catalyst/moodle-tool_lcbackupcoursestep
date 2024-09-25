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
 * Displays backed up courses.
 *
 * @package tool_lcbackupcourselogstep
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_lcbackupcoursestep\lifecycle\course_table;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('tool_lcbackupcoursestep_courses');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new \moodle_url('/admin/tool/lcbackupcoursestep/courses.php'));
$PAGE->set_title(get_string('backedupcourses', 'tool_lcbackupcoursestep'));
$PAGE->set_heading(get_string('backedupcourses', 'tool_lcbackupcoursestep'));

// Download.
$action = optional_param('action', null, PARAM_ALPHANUMEXT);

if ($action) {
    global $DB;
    require_sesskey();

    // Retrieve the file.
    $id = required_param('id', PARAM_INT);
    $fs = get_file_storage();
    $file = $fs->get_file_by_id($id);

    // Check file exists.
    if (!$file) {
        throw new coding_exception("File with id $id not found");
    }

    // Check file component.
    if ($file->get_component() != 'tool_lcbackupcoursestep') {
        throw new coding_exception("File with id $id is not a backup file");
    }

    // Perform the action.
    switch ($action) {
        case 'download':
            // Download the file.
            send_stored_file($file, 0, 0, true);
            break;
        case 'restore':
            $context = \context_system::instance();
            $restoreurl = new \moodle_url('/backup/restore.php',
                array(
                    'contextid' => $context->id,
                    'pathnamehash' => $file->get_pathnamehash(),
                    'contenthash' => $file->get_contenthash(),
                )
            );
            redirect($restoreurl);
        default:
            throw new coding_exception("action '{$action}' is not supported.");
            break;
    }
}

// Filter - Reuse course filter form.
$mform = new \tool_lifecycle\local\form\form_courses_filter();

// Cache handling.
$cache = cache::make('tool_lifecycle', 'mformdata');
if ($mform->is_cancelled()) {
    $cache->delete('coursebackups_filter');
    redirect($PAGE->url);
} else if ($data = $mform->get_data()) {
    $cache->set('coursebackups_filter', $data);
} else {
    $data = $cache->get('coursebackups_filter');
    if ($data) {
        $mform->set_data($data);
    }
}
echo $OUTPUT->header();
$mform->display();

// Show log table.
$table = new course_table($data);
$table->define_baseurl($PAGE->url);
$table->out(100, false);

echo $OUTPUT->footer();
