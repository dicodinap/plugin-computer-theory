<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

declare(strict_types=1);

/**
 * Tests for mod_graphitoubb lib.php hooks (add / update / delete instance round-trip).
 *
 * @package    mod_graphitoubb
 * @coversNothing
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class lib_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../lib.php');
    }

    public function test_add_instance_returns_id(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        $data               = new stdClass();
        $data->course       = $course->id;
        $data->name         = 'Test GraphitoUBB';
        $data->intro        = 'Intro text';
        $data->introformat  = FORMAT_HTML;

        $id = graphitoubb_add_instance($data);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $record = $DB->get_record('graphitoubb', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Test GraphitoUBB', $record->name);
        $this->assertGreaterThan(0, $record->timecreated);
        $this->assertSame($record->timecreated, $record->timemodified);
    }

    public function test_update_instance(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        $data              = new stdClass();
        $data->course      = $course->id;
        $data->name        = 'Original';
        $data->intro       = '';
        $data->introformat = FORMAT_HTML;

        $id = graphitoubb_add_instance($data);

        $data->id       = $id;
        $data->instance = $id;
        $data->name     = 'Updated';

        $result = graphitoubb_update_instance($data);

        $this->assertTrue($result);
        $record = $DB->get_record('graphitoubb', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Updated', $record->name);
        $this->assertGreaterThanOrEqual($record->timecreated, $record->timemodified);
    }

    public function test_delete_instance_removes_record(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        $data              = new stdClass();
        $data->course      = $course->id;
        $data->name        = 'To delete';
        $data->intro       = '';
        $data->introformat = FORMAT_HTML;

        $id = graphitoubb_add_instance($data);

        $result = graphitoubb_delete_instance($id);

        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('graphitoubb', ['id' => $id]));
    }

    public function test_delete_instance_cascades_attempts_snapshots_wordbank(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();

        $data              = new stdClass();
        $data->course      = $course->id;
        $data->name        = 'Cascade';
        $data->intro       = '';
        $data->introformat = FORMAT_HTML;

        $instanceid = graphitoubb_add_instance($data);

        $attemptid = $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $instanceid,
            'userid'      => $user->id,
            'status'      => 'inprogress',
            'timestarted' => time(),
        ]);

        $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $attemptid,
            'payload'        => '{}',
            'schema_version' => 1,
            'timecreated'    => time(),
        ]);

        $DB->insert_record('graphitoubb_wordbank_log', [
            'attemptid'   => $attemptid,
            'word'        => 'ab',
            'accepted'    => 1,
            'timecreated' => time(),
        ]);

        graphitoubb_delete_instance($instanceid);

        $this->assertFalse($DB->record_exists('graphitoubb_attempt', ['instanceid' => $instanceid]));
        $this->assertFalse($DB->record_exists('graphitoubb_snapshot', ['attemptid'  => $attemptid]));
        $this->assertFalse($DB->record_exists('graphitoubb_wordbank_log', ['attemptid'  => $attemptid]));
    }

    public function test_delete_nonexistent_instance_returns_false(): void {
        $result = graphitoubb_delete_instance(999999);
        $this->assertFalse($result);
    }

    public function test_supports_known_features(): void {
        $this->assertTrue(graphitoubb_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(graphitoubb_supports(FEATURE_SHOW_DESCRIPTION));
        $this->assertTrue(graphitoubb_supports(FEATURE_BACKUP_MOODLE2));
    }

    public function test_supports_unknown_feature_returns_null(): void {
        $this->assertNull(graphitoubb_supports('nonexistent_feature_xyz'));
    }

    public function test_get_coursemodule_info_returns_cached_cm_info(): void {
        $course   = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('graphitoubb', [
            'course'      => $course->id,
            'intro'       => '<p>Hello world</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $cm   = get_coursemodule_from_id('graphitoubb', $activity->cmid, 0, false, MUST_EXIST);
        $info = graphitoubb_get_coursemodule_info($cm);

        $this->assertInstanceOf(cached_cm_info::class, $info);
        $this->assertSame($activity->name, $info->name);
        $this->assertIsString($info->customdata);
        $this->assertStringNotContainsString('<p>', $info->customdata);
    }
}
