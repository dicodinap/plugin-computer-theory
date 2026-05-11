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
use mod_graphitoubb\external\finish_attempt;

/**
 * Tests for mod_graphitoubb\external\finish_attempt.
 *
 * @package    mod_graphitoubb
 * @covers     \mod_graphitoubb\external\finish_attempt
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class finish_attempt_test extends advanced_testcase {
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

    public function test_happy_path_returns_ok(): void {
        $result = finish_attempt::execute($this->attemptid);

        $this->assertSame('ok', $result['status']);
    }

    public function test_attempt_status_is_finished_after_call(): void {
        global $DB;

        finish_attempt::execute($this->attemptid);

        $row = $DB->get_record('graphitoubb_attempt', ['id' => $this->attemptid], '*', MUST_EXIST);
        $this->assertSame('finished', $row->status);
        $this->assertGreaterThan(0, (int) $row->timefinished);
    }

    public function test_calling_finish_twice_is_idempotent(): void {
        global $DB;

        finish_attempt::execute($this->attemptid);
        $result = finish_attempt::execute($this->attemptid);

        $this->assertSame('ok', $result['status']);

        $row = $DB->get_record('graphitoubb_attempt', ['id' => $this->attemptid], '*', MUST_EXIST);
        $this->assertSame('finished', $row->status);
    }

    public function test_student_cannot_finish_another_students_attempt(): void {
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);
        $this->expectException(\moodle_exception::class);
        finish_attempt::execute($this->attemptid);
    }

    public function test_teacher_with_viewreport_can_finish_student_attempt(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $teacher->id, (int) $this->course->id, 'editingteacher');
        $this->setUser($teacher);
        $result = finish_attempt::execute($this->attemptid);
        $this->assertSame('ok', $result['status']);
    }
}
