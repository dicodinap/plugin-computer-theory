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

use mod_graphitoubb\external\reset_attempts;

/**
 * Tests for mod_graphitoubb\external\reset_attempts.
 *
 * @package    mod_graphitoubb
 * @covers     \mod_graphitoubb\external\reset_attempts
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reset_attempts_test extends advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Teacher with reattempt capability. */
    private \stdClass $teacher;

    /** @var int Instance id. */
    private int $iid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course  = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $this->teacher->id, (int) $this->course->id, 'editingteacher');

        $module    = $this->getDataGenerator()->create_module('graphitoubb', ['course' => (int) $this->course->id]);
        $this->iid = (int) $module->id;

        $this->setUser($this->teacher);
    }

    // -------------------------------------------------------------------------
    // Helper: create a student with a finished attempt + submission + grade_cache.
    // -------------------------------------------------------------------------
    private function createFinishedAttempt(int $userid): int {
        global $DB;

        $attemptid = (int) $DB->insert_record('graphitoubb_attempt', (object) [
            'instanceid'   => $this->iid,
            'userid'       => $userid,
            'status'       => 'finished',
            'timestarted'  => time() - 300,
            'timefinished' => time(),
        ]);

        $DB->insert_record('graphitoubb_submission', (object) [
            'attemptid'             => $attemptid,
            'payload'               => '{}',
            'payload_hash'          => hash('sha256', 'p' . $userid),
            'problem_snapshot_hash' => hash('sha256', 'problem'),
            'score'                 => 7.0,
            'fraction'              => 0.7,
            'passed'                => 1,
            'grading_result'        => '{}',
            'schema_version'        => 1,
            'timecreated'           => time(),
        ]);

        $DB->insert_record('graphitoubb_grade_cache', (object) [
            'attemptid'      => $attemptid,
            'score'          => 7.0,
            'fraction'       => 0.7,
            'attempt_count'  => 1,
            'policy_applied' => 'best',
            'timemodified'   => time(),
        ]);

        return $attemptid;
    }

    /**
     * Test 1: reset one user resets only that user's attempts, not others.
     */
    public function test_reset_one_user_resets_that_user_only(): void {
        global $DB;

        $s1 = $this->getDataGenerator()->create_user();
        $s2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $s1->id, (int) $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user((int) $s2->id, (int) $this->course->id, 'student');

        $aid1 = $this->createFinishedAttempt((int) $s1->id);
        $aid2 = $this->createFinishedAttempt((int) $s2->id);

        $result = reset_attempts::execute($this->iid, (int) $s1->id);

        $this->assertSame(1, $result['reset_count']);

        // s1's attempt should be inprogress with timefinished = null.
        $attempt1 = $DB->get_record('graphitoubb_attempt', ['id' => $aid1], '*', MUST_EXIST);
        $this->assertSame('inprogress', $attempt1->status);
        $this->assertNull($attempt1->timefinished);

        // s1's submission and grade_cache should be deleted.
        $this->assertFalse($DB->record_exists('graphitoubb_submission', ['attemptid' => $aid1]));
        $this->assertFalse($DB->record_exists('graphitoubb_grade_cache', ['attemptid' => $aid1]));

        // s2's attempt should be untouched.
        $attempt2 = $DB->get_record('graphitoubb_attempt', ['id' => $aid2], '*', MUST_EXIST);
        $this->assertSame('finished', $attempt2->status);
        $this->assertTrue($DB->record_exists('graphitoubb_submission', ['attemptid' => $aid2]));
        $this->assertTrue($DB->record_exists('graphitoubb_grade_cache', ['attemptid' => $aid2]));
    }

    /**
     * Test 2: reset all (userid=0) resets every attempt in the instance.
     */
    public function test_reset_all_resets_every_attempt_in_instance(): void {
        global $DB;

        $s1 = $this->getDataGenerator()->create_user();
        $s2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $s1->id, (int) $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user((int) $s2->id, (int) $this->course->id, 'student');

        $aid1 = $this->createFinishedAttempt((int) $s1->id);
        $aid2 = $this->createFinishedAttempt((int) $s2->id);

        $result = reset_attempts::execute($this->iid, 0);

        $this->assertSame(2, $result['reset_count']);

        foreach ([$aid1, $aid2] as $aid) {
            $attempt = $DB->get_record('graphitoubb_attempt', ['id' => $aid], '*', MUST_EXIST);
            $this->assertSame('inprogress', $attempt->status);
            $this->assertNull($attempt->timefinished);
            $this->assertFalse($DB->record_exists('graphitoubb_submission', ['attemptid' => $aid]));
            $this->assertFalse($DB->record_exists('graphitoubb_grade_cache', ['attemptid' => $aid]));
        }
    }
}
