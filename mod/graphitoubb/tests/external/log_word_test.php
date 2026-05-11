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
use mod_graphitoubb\external\log_word;

/**
 * Tests for mod_graphitoubb\external\log_word.
 *
 * @package    mod_graphitoubb
 * @covers     \mod_graphitoubb\external\log_word
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class log_word_test extends advanced_testcase {
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
        $result = log_word::execute($this->attemptid, 'abc', true);

        $this->assertSame('ok', $result['status']);
    }

    public function test_word_is_persisted_in_db(): void {
        global $DB;

        log_word::execute($this->attemptid, 'hello', false);

        $row = $DB->get_record('graphitoubb_wordbank_log', ['attemptid' => $this->attemptid]);
        $this->assertNotFalse($row);
        $this->assertSame('hello', $row->word);
        $this->assertSame('0', (string) $row->accepted);
    }

    public function test_student_cannot_log_word_for_another_students_attempt(): void {
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);
        $this->expectException(\moodle_exception::class);
        log_word::execute($this->attemptid, 'test', true);
    }

    public function test_teacher_with_viewreport_can_log_word_on_student_attempt(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $teacher->id, (int) $this->course->id, 'editingteacher');
        $this->setUser($teacher);
        $result = log_word::execute($this->attemptid, 'test', true);
        $this->assertSame('ok', $result['status']);
    }
}
