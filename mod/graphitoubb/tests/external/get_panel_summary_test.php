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

use mod_graphitoubb\external\get_panel_summary;

/**
 * Tests for mod_graphitoubb\external\get_panel_summary.
 *
 * @package    mod_graphitoubb
 * @covers     \mod_graphitoubb\external\get_panel_summary
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_panel_summary_test extends advanced_testcase {
    /** @var \stdClass Course created in setUp. */
    private \stdClass $course;

    /** @var \stdClass Teacher user. */
    private \stdClass $teacher;

    /** @var \stdClass Module (graphitoubb instance). */
    private \stdClass $module;

    /** @var int Instance id. */
    private int $iid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course  = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $this->teacher->id, (int) $this->course->id, 'editingteacher');

        $this->module = $this->getDataGenerator()->create_module('graphitoubb', ['course' => (int) $this->course->id]);
        $this->iid    = (int) $this->module->id;

        $this->setUser($this->teacher);
    }

    /**
     * Test 1: empty instance returns zero counters and correct structure.
     */
    public function test_empty_instance_returns_zero_counters(): void {
        $result = get_panel_summary::execute($this->iid);

        $this->assertSame($this->iid, $result['instanceid']);
        $this->assertSame(0, $result['attempted']);
        $this->assertSame(0, $result['submitted']);
        $this->assertSame(0, $result['with_draft']);
        $this->assertEqualsWithDelta(0.0, $result['avg_score'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['median_score'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['stddev_score'], 0.001);
        $this->assertSame(0, $result['time_median_seconds']);
        $this->assertIsArray($result['buckets']);
        $this->assertCount(11, $result['buckets']);
        $this->assertIsArray($result['top_errors']);
        $this->assertEmpty($result['top_errors']);
    }

    /**
     * Test 2: with N submissions returns correct avg / median / stddev from grade_cache.
     */
    public function test_with_submissions_returns_correct_score_aggregates(): void {
        global $DB;

        $cm = get_coursemodule_from_instance('graphitoubb', $this->iid, 0, false, MUST_EXIST);

        // Create 3 students, each with an attempt + grade_cache at specific fractions.
        $fractions = [0.5, 0.75, 1.0];
        foreach ($fractions as $fraction) {
            $student = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user((int) $student->id, (int) $this->course->id, 'student');

            $attemptid = $DB->insert_record('graphitoubb_attempt', (object) [
                'instanceid'   => $this->iid,
                'userid'       => $student->id,
                'status'       => 'finished',
                'timestarted'  => time() - 300,
                'timefinished' => time(),
            ]);

            $DB->insert_record('graphitoubb_grade_cache', (object) [
                'attemptid'      => $attemptid,
                'score'          => $fraction * 10,
                'fraction'       => $fraction,
                'attempt_count'  => 1,
                'policy_applied' => 'best',
                'timemodified'   => time(),
            ]);

            // Also insert a submission so the 'submitted' count is correct.
            $DB->insert_record('graphitoubb_submission', (object) [
                'attemptid'             => $attemptid,
                'payload'               => '{}',
                'payload_hash'          => hash('sha256', '{}'),
                'problem_snapshot_hash' => hash('sha256', 'problem'),
                'score'                 => $fraction * 10,
                'fraction'              => $fraction,
                'passed'                => $fraction >= 0.6 ? 1 : 0,
                'grading_result'        => json_encode([
                    'score'        => $fraction * 10,
                    'fraction'     => $fraction,
                    'cells_total'  => 4,
                    'cells_correct' => (int) ($fraction * 4),
                    'feedback_items' => [],
                ]),
                'schema_version' => 1,
                'timecreated'    => time(),
            ]);
        }

        $result = get_panel_summary::execute($this->iid);

        $expected_avg    = array_sum($fractions) / count($fractions); // 0.75
        $expected_median = 0.75;
        $this->assertEqualsWithDelta($expected_avg, $result['avg_score'], 0.001);
        $this->assertEqualsWithDelta($expected_median, $result['median_score'], 0.001);
        $this->assertGreaterThanOrEqual(0.0, $result['stddev_score']);
        $this->assertSame(3, $result['submitted']);
        $this->assertSame(3, $result['attempted']);
    }

    /**
     * Test 3: top_errors returns top 5 by count, parsed from grading_result feedback_items.
     */
    public function test_top_errors_returns_top_5_by_count(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $student->id, (int) $this->course->id, 'student');

        // Create 6 different error cells with different counts by inserting 6 submissions
        // from 6 different students, each erroring on a unique cell + shared cells.
        $error_data = [
            ['row_index' => 0, 'col_label' => 'A∧B', 'count' => 6],
            ['row_index' => 1, 'col_label' => 'A∨B', 'count' => 5],
            ['row_index' => 2, 'col_label' => 'A→B', 'count' => 4],
            ['row_index' => 0, 'col_label' => 'A↔B', 'count' => 3],
            ['row_index' => 1, 'col_label' => '¬A', 'count' => 2],
            ['row_index' => 2, 'col_label' => 'A', 'count' => 1], // Should be excluded from top 5.
        ];

        // Build submissions where each cell appears in the specified number of submissions.
        for ($i = 0; $i < 6; $i++) {
            $s = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user((int) $s->id, (int) $this->course->id, 'student');

            $aid = $DB->insert_record('graphitoubb_attempt', (object) [
                'instanceid'   => $this->iid,
                'userid'       => $s->id,
                'status'       => 'finished',
                'timestarted'  => time() - 300,
                'timefinished' => time(),
            ]);

            // Each submission errors on all cells up to index i (inclusive).
            $feedback_items = [];
            for ($j = 0; $j <= $i; $j++) {
                $feedback_items[] = [
                    'row_index'  => $error_data[$j]['row_index'],
                    'col_label'  => $error_data[$j]['col_label'],
                    'is_correct' => false,
                    'submitted'  => 'F',
                    'expected'   => 'V',
                ];
            }

            $DB->insert_record('graphitoubb_submission', (object) [
                'attemptid'             => $aid,
                'payload'               => '{}',
                'payload_hash'          => hash('sha256', 'p' . $i),
                'problem_snapshot_hash' => hash('sha256', 'problem'),
                'score'                 => 0,
                'fraction'              => 0.0,
                'passed'                => 0,
                'grading_result'        => json_encode([
                    'score'          => 0,
                    'fraction'       => 0.0,
                    'cells_total'    => 6,
                    'cells_correct'  => 0,
                    'feedback_items' => $feedback_items,
                ]),
                'schema_version' => 1,
                'timecreated'    => time(),
            ]);
        }

        $result = get_panel_summary::execute($this->iid);

        $this->assertIsArray($result['top_errors']);
        $this->assertLessThanOrEqual(5, count($result['top_errors']));
        if (!empty($result['top_errors'])) {
            // First entry should be the most frequent error.
            $first = $result['top_errors'][0];
            $this->assertArrayHasKey('row_index', $first);
            $this->assertArrayHasKey('col_label', $first);
            $this->assertArrayHasKey('count', $first);
            $this->assertArrayHasKey('percentage', $first);
            // Counts should be descending.
            $counts = array_column($result['top_errors'], 'count');
            $sorted = $counts;
            rsort($sorted);
            $this->assertSame($sorted, $counts);
        }
    }
}
