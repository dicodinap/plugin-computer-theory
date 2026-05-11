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

/**
 * Tests for mod_graphitoubb attempt_service.
 *
 * @package    mod_graphitoubb
 * @covers     \mod_graphitoubb\attempt_service
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_service_test extends advanced_testcase {
    /** @var attempt_service */
    private attempt_service $service;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->service = new attempt_service();
    }

    /**
     * Creates a graphitoubb instance row and returns its id.
     *
     * @return int Instance id.
     */
    private function make_instance(): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        return (int) $DB->insert_record('graphitoubb', [
            'course'       => (int) $course->id,
            'name'         => 'Test instance',
            'intro'        => '',
            'introformat'  => FORMAT_HTML,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_start_creates_attempt(): void {
        $instanceid = $this->make_instance();
        $user = $this->getDataGenerator()->create_user();

        $attempt = $this->service->start_or_resume($instanceid, (int) $user->id);

        $this->assertSame('inprogress', $attempt->status);
        $this->assertSame($instanceid, (int) $attempt->instanceid);
        $this->assertSame((int) $user->id, (int) $attempt->userid);
    }

    public function test_start_or_resume_is_idempotent(): void {
        $instanceid = $this->make_instance();
        $user = $this->getDataGenerator()->create_user();

        $first  = $this->service->start_or_resume($instanceid, (int) $user->id);
        $second = $this->service->start_or_resume($instanceid, (int) $user->id);

        $this->assertSame((int) $first->id, (int) $second->id);
    }

    public function test_finish_marks_attempt_as_finished(): void {
        global $DB;
        $instanceid = $this->make_instance();
        $user = $this->getDataGenerator()->create_user();

        $attempt = $this->service->start_or_resume($instanceid, (int) $user->id);
        $this->service->finish((int) $attempt->id);

        $record = $DB->get_record('graphitoubb_attempt', ['id' => $attempt->id], '*', MUST_EXIST);
        $this->assertSame('finished', $record->status);
        $this->assertGreaterThan(0, (int) $record->timefinished);
    }

    public function test_get_attempt_returns_record(): void {
        $instanceid = $this->make_instance();
        $user = $this->getDataGenerator()->create_user();

        $attempt = $this->service->start_or_resume($instanceid, (int) $user->id);
        $fetched  = $this->service->get_attempt((int) $attempt->id);

        $this->assertNotNull($fetched);
        $this->assertSame((int) $attempt->id, (int) $fetched->id);
    }

    public function test_get_attempt_returns_null_for_missing(): void {
        $result = $this->service->get_attempt(999999);
        $this->assertNull($result);
    }

    public function test_belongs_to_returns_true_for_owner(): void {
        $instanceid = $this->make_instance();
        $user = $this->getDataGenerator()->create_user();

        $attempt = $this->service->start_or_resume($instanceid, (int) $user->id);

        $this->assertTrue($this->service->belongs_to((int) $attempt->id, (int) $user->id));
    }

    public function test_belongs_to_returns_false_for_other_user(): void {
        $instanceid = $this->make_instance();
        $user  = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $attempt = $this->service->start_or_resume($instanceid, (int) $user->id);

        $this->assertFalse($this->service->belongs_to((int) $attempt->id, (int) $other->id));
    }

    public function test_unique_constraint_respected_via_start_or_resume(): void {
        $instanceid = $this->make_instance();
        $user = $this->getDataGenerator()->create_user();

        // Two calls must not throw; both return same attempt.
        $a = $this->service->start_or_resume($instanceid, (int) $user->id);
        $b = $this->service->start_or_resume($instanceid, (int) $user->id);

        $this->assertSame((int) $a->id, (int) $b->id);
    }

    public function test_different_users_get_separate_attempts(): void {
        $instanceid = $this->make_instance();
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();

        $a = $this->service->start_or_resume($instanceid, (int) $usera->id);
        $b = $this->service->start_or_resume($instanceid, (int) $userb->id);

        $this->assertNotSame((int) $a->id, (int) $b->id);
    }
}
