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

use mod_graphitoubb\attempt_service;
use mod_graphitoubb\external\save_snapshot;

/**
 * Tests for mod_graphitoubb\external\save_snapshot.
 *
 * @package    mod_graphitoubb
 * @covers     \mod_graphitoubb\external\save_snapshot
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class save_snapshot_test extends advanced_testcase {
    /** @var int Attempt id created in setUp. */
    private int $attemptid;
    /** @var \stdClass Course. */
    private \stdClass $course;
    /** @var \stdClass Student user who owns the attempt. */
    private \stdClass $user;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $this->user->id, (int) $this->course->id, 'student');

        $module     = $this->getDataGenerator()->create_module('graphitoubb', ['course' => (int) $this->course->id]);
        $instanceid = (int) $module->id;

        $this->setUser($this->user);
        $service = new attempt_service();
        $attempt = $service->start_or_resume($instanceid, (int) $this->user->id);
        $this->attemptid = (int) $attempt->id;
    }

    public function test_happy_path_returns_ok_with_positive_id(): void {
        $result = save_snapshot::execute($this->attemptid, '{"states":[]}', 1);

        $this->assertSame('ok', $result['status']);
        $this->assertGreaterThan(0, $result['snapshotid']);
    }

    public function test_rate_limited_returns_rate_limited_status(): void {
        global $DB;

        // Simulate a snapshot saved in the current second.
        $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $this->attemptid,
            'payload'        => '{"first":true}',
            'schema_version' => 1,
            'timecreated'    => time(),
        ]);

        $result = save_snapshot::execute($this->attemptid, '{"second":true}', 1);

        $this->assertSame('rate_limited', $result['status']);
        $this->assertSame(0, $result['snapshotid']);
    }

    public function test_student_cannot_save_snapshot_for_another_students_attempt(): void {
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);
        $this->expectException(\moodle_exception::class);
        save_snapshot::execute($this->attemptid, '{"states":[]}', 1);
    }

    public function test_teacher_with_viewreport_can_save_snapshot_on_student_attempt(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $teacher->id, (int) $this->course->id, 'editingteacher');
        $this->setUser($teacher);
        $result = save_snapshot::execute($this->attemptid, '{"states":[]}', 1);
        $this->assertSame('ok', $result['status']);
    }
}
