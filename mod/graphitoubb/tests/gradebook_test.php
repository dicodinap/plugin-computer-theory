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
 * Tests for mod_graphitoubb gradebook integration (lib.php grade hooks + backfill).
 *
 * @package    mod_graphitoubb
 * @coversNothing
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gradebook_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../lib.php');
    }

    /**
     * Create an instance with the given aggregation policy.
     *
     * @param string $policy best|last|average
     * @return array{0: stdClass, 1: stdClass} [course, instance]
     */
    private function make_instance(string $policy): array {
        $course   = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('graphitoubb', [
            'course'          => $course->id,
            'attempts_policy' => $policy,
        ]);
        global $DB;
        $record = $DB->get_record('graphitoubb', ['id' => $instance->id], '*', MUST_EXIST);
        $record->id = (int) $record->id;
        return [$course, $record];
    }

    /**
     * Insert an attempt with a matching grade_cache row.
     *
     * @param int   $instanceid
     * @param int   $userid
     * @param float $fraction
     * @param int   $timefinished
     * @return int attemptid
     */
    private function make_graded_attempt($instanceid, $userid, float $fraction, int $timefinished): int {
        global $DB;
        $attemptid = $DB->insert_record('graphitoubb_attempt', [
            'instanceid'   => (int) $instanceid,
            'userid'       => (int) $userid,
            'status'       => 'finished',
            'timestarted'  => $timefinished - 100,
            'timefinished' => $timefinished,
        ]);
        $DB->insert_record('graphitoubb_grade_cache', [
            'attemptid'      => $attemptid,
            'score'          => $fraction * 100,
            'fraction'       => $fraction,
            'attempt_count'  => 1,
            'policy_applied' => 'best',
            'timemodified'   => $timefinished,
        ]);
        return (int) $attemptid;
    }

    public function test_get_user_grades_best_policy(): void {
        [, $instance] = $this->make_instance('best');
        $user = $this->getDataGenerator()->create_user();

        $this->make_graded_attempt($instance->id, $user->id, 0.50, 1000);
        $this->make_graded_attempt($instance->id, $user->id, 1.00, 1100);
        $this->make_graded_attempt($instance->id, $user->id, 0.75, 1200);

        $grades = graphitoubb_get_user_grades($instance, $user->id);
        $this->assertArrayHasKey($user->id, $grades);
        $this->assertEqualsWithDelta(100.0, $grades[$user->id]->rawgrade, 0.0001);
    }

    public function test_get_user_grades_last_policy(): void {
        [, $instance] = $this->make_instance('last');
        $user = $this->getDataGenerator()->create_user();

        $this->make_graded_attempt($instance->id, $user->id, 1.00, 1000);
        $this->make_graded_attempt($instance->id, $user->id, 0.50, 1100);
        $this->make_graded_attempt($instance->id, $user->id, 0.30, 1200); // Most recent.

        $grades = graphitoubb_get_user_grades($instance, $user->id);
        $this->assertEqualsWithDelta(30.0, $grades[$user->id]->rawgrade, 0.0001);
    }

    public function test_get_user_grades_average_policy(): void {
        [, $instance] = $this->make_instance('average');
        $user = $this->getDataGenerator()->create_user();

        $this->make_graded_attempt($instance->id, $user->id, 1.00, 1000);
        $this->make_graded_attempt($instance->id, $user->id, 0.50, 1100);
        $this->make_graded_attempt($instance->id, $user->id, 0.00, 1200);

        // (1.0 + 0.5 + 0.0) / 3 = 0.5 ⇒ 50.
        $grades = graphitoubb_get_user_grades($instance, $user->id);
        $this->assertEqualsWithDelta(50.0, $grades[$user->id]->rawgrade, 0.0001);
    }

    public function test_get_user_grades_multiuser(): void {
        [, $instance] = $this->make_instance('best');
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        $this->make_graded_attempt($instance->id, $u1->id, 0.80, 1000);
        $this->make_graded_attempt($instance->id, $u2->id, 0.40, 1000);
        $this->make_graded_attempt($instance->id, $u2->id, 0.90, 1100);

        $grades = graphitoubb_get_user_grades($instance, 0);
        $this->assertCount(2, $grades);
        $this->assertEqualsWithDelta(80.0, $grades[$u1->id]->rawgrade, 0.0001);
        $this->assertEqualsWithDelta(90.0, $grades[$u2->id]->rawgrade, 0.0001);
    }

    public function test_get_user_grades_no_submissions_returns_empty(): void {
        [, $instance] = $this->make_instance('best');
        $user = $this->getDataGenerator()->create_user();

        // Attempt without a grade_cache row must NOT produce a grade.
        global $DB;
        $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $instance->id,
            'userid'      => $user->id,
            'status'      => 'inprogress',
            'timestarted' => 1000,
        ]);

        $grades = graphitoubb_get_user_grades($instance, $user->id);
        $this->assertSame([], $grades);
    }

    public function test_update_grades_pushes_to_gradebook(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        [$course, $instance] = $this->make_instance('best');
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->make_graded_attempt($instance->id, $user->id, 0.85, 1000);

        graphitoubb_update_grades($instance, $user->id);

        $grades = grade_get_grades($course->id, 'mod', 'graphitoubb', $instance->id, $user->id);
        $item   = reset($grades->items);
        $this->assertEqualsWithDelta(85.0, (float) $item->grades[$user->id]->grade, 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $item->grademax, 0.0001);
    }

    public function test_update_grades_nullifnone_pushes_null(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        [$course, $instance] = $this->make_instance('best');
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // No graded attempts ⇒ nullifnone should emit a null grade, not an error.
        graphitoubb_update_grades($instance, $user->id, true);

        $grades = grade_get_grades($course->id, 'mod', 'graphitoubb', $instance->id, $user->id);
        $item   = reset($grades->items);
        $this->assertNull($item->grades[$user->id]->grade);
    }

    public function test_grade_item_created_on_add_instance(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        [$course, $instance] = $this->make_instance('best');

        $exists = $DB->record_exists('grade_items', [
            'courseid'     => $course->id,
            'itemtype'     => 'mod',
            'itemmodule'   => 'graphitoubb',
            'iteminstance' => $instance->id,
            'itemnumber'   => 0,
        ]);
        $this->assertTrue($exists);
    }

    public function test_backfill_creates_item_and_grades(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        [$course, $instance] = $this->make_instance('best');
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // Simulate a pre-existing graded attempt (as if emitted before the upgrade).
        $this->make_graded_attempt($instance->id, $user->id, 1.00, 1000);

        // Drop any grade item to emulate the pre-upgrade state, then run the backfill body.
        $DB->delete_records('grade_items', [
            'itemtype'     => 'mod',
            'itemmodule'   => 'graphitoubb',
            'iteminstance' => $instance->id,
        ]);

        // Backfill exactly as db/upgrade.php does.
        graphitoubb_grade_item_update($instance);
        graphitoubb_update_grades($instance, 0, false);

        $grades = grade_get_grades($course->id, 'mod', 'graphitoubb', $instance->id, $user->id);
        $item   = reset($grades->items);
        $this->assertEqualsWithDelta(100.0, (float) $item->grades[$user->id]->grade, 0.0001);
    }
}
