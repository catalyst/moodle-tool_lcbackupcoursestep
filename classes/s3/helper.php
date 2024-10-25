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

namespace tool_lcbackupcoursestep\s3;

use Aws\MockHandler;
use Aws\Result;
use stdClass;
use stored_file;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\settings_type;

/**
 * Helper class to work with s3 bucket.
 *
 * @package     tool_lcbackupcoursestep
 * @copyright   2024 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Check if local/aws plugin installed.
     *
     * return bool true if dependency is met.
     */
    public static function met_dependency(): bool {
        global $CFG;
        if (!file_exists($CFG->dirroot . '/local/aws/version.php')) {
            return false;
        }
        require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');
        return true;
    }

    /**
     * Create a new S3 client.
     *
     * @param array $settings amazon s3 settings.
     * @return \Aws\S3\S3Client
     */
    private static function create_client(array $settings) {
        // Connection options.
        $options = [
            'version' => 'latest',
            'region' => $settings['s3_region'] ?? 'ap-southeast-2',
        ];

        // Credentials.
        $usesdkcreds = $settings['s3_usesdkcreds'] ?? false;
        if (!$usesdkcreds) {
            $options['credentials'] = [
                'key' => $settings['s3_key'],
                'secret' => $settings['s3_secret'],
            ];
        }

        // Proxy.
        if ($settings['s3_useproxy']) {
            $options['http'] = ['proxy' => \local_aws\local\aws_helper::get_proxy_string()];
        }

        // Test only.
        if (PHPUNIT_TEST) {
            $mock = new MockHandler();
            $mock->append(new Result([]));
            $options['handler'] = $mock;
        }

        return new \Aws\S3\S3Client($options);
    }

    /**
     * Test connection to S3 bucket.
     *
     * @param array $settings amazon s3 settings.
     * @return stdClass
     */
    public static function check_connection(array $settings): stdClass {
        $connection = new stdClass();
        $connection->success = true;
        $connection->details = '';

        // Check dependency.
        if (!self::met_dependency()) {
            $connection->success = false;
            $connection->details = get_string('s3_unmet_dependency', 'tool_lcbackupcoursestep');
            return $connection;
        }

        try {
            $client = self::create_client($settings);
            $client->headBucket(['Bucket' => $settings['s3_bucket']]);
        } catch (\Exception $e) {
            $connection->success = false;
            $connection->details = $e->getMessage();
        }
        return $connection;
    }

    /**
     * Upload file to S3 bucket.
     *
     * @param int $processid process id.
     * @param int $instanceid step instance id.
     * @param int $courseid course id.
     * @param stored_file $file file to upload.
     */
    public static function upload_file(int $processid, int $instanceid, int $courseid, stored_file $file) {
        global $DB;

        // Get S3 settings.
        $settings = settings_manager::get_settings($instanceid, settings_type::STEP);

        // Do nothing if s3 is not enabled.
        if (!$settings['uses3']) {
            return;
        }

        // Check connection.
        $connection = self::check_connection($settings);

        if (!$connection->success) {
            throw new \moodle_exception('s3_connection_error', 'tool_lcbackupcoursestep', '', $connection->details);
        }

        // Upload file.
        $client = self::create_client($settings);
        $client->putObject([
            'Bucket' => $settings['s3_bucket'],
            'Key' => $file->get_filename(),
            'Body' => $file->get_content_file_handle(),
        ]);

        // Save backed up file details.
        $filedetails = new stdClass();
        $filedetails->processid = $processid;
        $filedetails->instanceid = $instanceid;
        $filedetails->courseid = $courseid;
        $filedetails->filename = $file->get_filename();
        $filedetails->contenthash = $file->get_contenthash();
        $DB->insert_record('tool_lcbackupcoursestep_s3', $filedetails);
    }

}
