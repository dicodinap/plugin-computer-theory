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

use mod_graphitoubb\external\get_panel_per_student;

/**
 * Tests for mod_graphitoubb\external\get_panel_per_student.
 *
 * @package    mod_graphitoubb
 * @covers     \mod_graphitoubb\external\get_panel_per_student
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_panel_per_student_test extends advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Teacher. */
    private \stdClass $teacher;

    /** @var int Instance id. */
    private int $iid;

    /** @var \context_module Module context. */
    private \context_module $ctx;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course  = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $this->teacher->id, (int) $this->course->id, 'editingteacher');

        $module    = $this->getDataGenerator()->create_module('graphitoubb', ['course' => (int) $this->course->id]);
        $this->iid = (int) $module->id;
        $cm        = get_coursemodule_from_instance('graphitoubb', $this->iid);
        $this->ctx = \context_module::instance((int) $cm->id);

        $this->setUser($this->teacher);
    }

    // -------------------------------------------------------------------------
    // Helpers.
    // -------------------------------------------------------------------------

    /**
     * Create an enrolled student with an attempt and optionally a submission.
     *
     * @param float|null $fraction  Submission fraction; null = no submission.
     * @return \stdClass  Student user record.
     */
    private function createStudentWithAttempt(?float $fraction = null): \stdClass {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $student->id, (int) $this->course->id, 'student');

        $attemptid = $DB->insert_record('graphitoubb_attempt', (object) [
            'instanceid'   => $this->iid,
            'userid'       => $student->id,
            'status'       => $fraction !== null ? 'finished' : 'inprogress',
            'timestarted'  => time() - 300,
            'timefinished' => $fraction !== null ? time() : null,
        ]);

        if ($fraction !== null) {
            $DB->insert_record('graphitoubb_submission', (object) [
                'attemptid'             => $attemptid,
                'payload'               => '{}',
                'payload_hash'          => hash('sha256', 'p' . $student->id),
                'problem_snapshot_hash' => hash('sha256', 'problem'),
                'score'                 => $fraction * 10,
                'fraction'              => $fraction,
                'passed'                => $fraction >= 0.6 ? 1 : 0,
                'grading_result'        => json_encode(['fraction' => $fraction, 'feedback_items' => []]),
                'schema_version'        => 1,
                'timecreated'           => time(),
            ]);
            $DB->insert_record('graphitoubb_grade_cache', (object) [
                'attemptid'      => $attemptid,
                'score'          => $fraction * 10,
                'fraction'       => $fraction,
                'attempt_count'  => 1,
                'policy_applied' => 'best',
                'timemodified'   => time(),
            ]);
        }

        return $student;
    }

    // -------------------------------------------------------------------------
    // Tests.
    // -------------------------------------------------------------------------

    /**
     * Test 1: filter=all returns all enrolled students (including those with no attempt).
     */
    public function test_filter_all_returns_all_enrolled(): void {
        $s1 = $this->createStudentWithAttempt(0.8); // Submitted, passed.
        $s2 = $this->createStudentWithAttempt(0.4); // Submitted, failed (has errors).
        $s3 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $s3->id, (int) $this->course->id, 'student');
        // s3 has no attempt (not_started).

        $result  = get_panel_per_student::execute($this->iid, 'all');
        $userids = array_column($result['students'], 'userid');

        $this->assertContains((int) $s1->id, $userids);
        $this->assertContains((int) $s2->id, $userids);
        $this->assertContains((int) $s3->id, $userids);
        $this->assertCount(3, $result['students']);
    }

    /**
     * Test 2: filter=with_errors excludes students with perfect scores.
     */
    public function test_filter_with_errors_excludes_perfect_scores(): void {
        $perfect  = $this->createStudentWithAttempt(1.0); // Perfect — should be excluded.
        $imperfect = $this->createStudentWithAttempt(0.5); // Has errors — should be included.
        $no_sub   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $no_sub->id, (int) $this->course->id, 'student');
        // no_sub has no submission — excluded by filter.

        $result  = get_panel_per_student::execute($this->iid, 'with_errors');
        $userids = array_column($result['students'], 'userid');

        $this->assertNotContains((int) $perfect->id, $userids, 'Perfect score student should be excluded');
        $this->assertNotContains((int) $no_sub->id, $userids, 'No submission student should be excluded');
        $this->assertContains((int)   $imperfect->id, $userids, 'Imperfect score student should be included');
    }

    /**
     * Test 3: filter=not_submitted returns only users who have no submission.
     */
    public function test_filter_not_submitted_returns_only_no_submission_users(): void {
        $submitted    = $this->createStudentWithAttempt(0.7);
        $not_submitted = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $not_submitted->id, (int) $this->course->id, 'student');
        // not_submitted has no attempt at all.

        $result  = get_panel_per_student::execute($this->iid, 'not_submitted');
        $userids = array_column($result['students'], 'userid');

        $this->assertNotContains((int) $submitted->id, $userids, 'Submitted student should be excluded');
        $this->assertContains((int)    $not_submitted->id, $userids, 'Not-submitted student should be included');
    }
}
