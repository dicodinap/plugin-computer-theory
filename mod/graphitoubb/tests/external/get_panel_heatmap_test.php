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

use mod_graphitoubb\external\get_panel_heatmap;

/**
 * Tests for mod_graphitoubb\external\get_panel_heatmap.
 *
 * @package    mod_graphitoubb
 * @covers     \mod_graphitoubb\external\get_panel_heatmap
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_panel_heatmap_test extends advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Teacher. */
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

    /**
     * Insert a problem record with a known payload.
     *
     * @param string[] $variables
     * @param string[] $columns
     */
    private function insertProblem(array $variables, array $columns): void {
        global $DB;
        $DB->insert_record('graphitoubb_problem', (object) [
            'instanceid'     => $this->iid,
            'tool'           => 'truth_table',
            'type'           => 'complete',
            'payload'        => json_encode(['variables' => $variables, 'columns' => $columns]),
            'payload_hash'   => hash('sha256', 'p'),
            'schema_version' => 1,
            'timecreated'    => time(),
            'timemodified'   => time(),
        ]);
    }

    /**
     * Insert a submission with specific feedback_items.
     *
     * @param int   $userid
     * @param array $feedback_items
     */
    private function insertSubmission(int $userid, array $feedback_items): void {
        global $DB;

        $attemptid = $DB->insert_record('graphitoubb_attempt', (object) [
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
            'score'                 => 0,
            'fraction'              => 0.0,
            'passed'                => 0,
            'grading_result'        => json_encode([
                'fraction'       => 0.0,
                'cells_total'    => count($feedback_items),
                'feedback_items' => $feedback_items,
            ]),
            'schema_version' => 1,
            'timecreated'    => time(),
        ]);
    }

    /**
     * Test 1: empty instance returns rows_count from problem + empty cells.
     */
    public function test_empty_returns_rows_count_and_empty_cells(): void {
        // 2 variables → 4 rows.
        $this->insertProblem(['A', 'B'], ['A', 'B', 'A∧B']);

        $result = get_panel_heatmap::execute($this->iid);

        $this->assertSame(4, $result['rows_count']);
        $this->assertIsArray($result['columns']);
        $this->assertCount(3, $result['columns']);
        $this->assertIsArray($result['cells']);
        $this->assertEmpty($result['cells'], 'No submissions should produce empty cells');
    }

    /**
     * Test 2: with submissions returns correct aggregated pct_correct per cell.
     */
    public function test_with_submissions_returns_aggregated_pct_correct(): void {
        $this->insertProblem(['A', 'B'], ['A', 'B', 'A∧B']);

        $s1 = $this->getDataGenerator()->create_user();
        $s2 = $this->getDataGenerator()->create_user();

        // s1: row=0, col=A∧B correct; row=1, col=A∧B incorrect.
        $this->insertSubmission((int) $s1->id, [
            ['row_index' => 0, 'col_label' => 'A∧B', 'is_correct' => true, 'submitted' => 'F', 'expected' => 'F'],
            ['row_index' => 1, 'col_label' => 'A∧B', 'is_correct' => false, 'submitted' => 'V', 'expected' => 'F'],
        ]);

        // s2: row=0, col=A∧B correct; row=1, col=A∧B correct.
        $this->insertSubmission((int) $s2->id, [
            ['row_index' => 0, 'col_label' => 'A∧B', 'is_correct' => true, 'submitted' => 'F', 'expected' => 'F'],
            ['row_index' => 1, 'col_label' => 'A∧B', 'is_correct' => true, 'submitted' => 'F', 'expected' => 'F'],
        ]);

        $result = get_panel_heatmap::execute($this->iid);

        $this->assertSame(4, $result['rows_count']);
        $this->assertNotEmpty($result['cells']);

        // Find cell (row=0, col_index for A∧B = 2).
        $colIndex = array_search('A∧B', $result['columns'], true);
        $this->assertNotFalse($colIndex, 'Column A∧B must be in columns list');

        $cell_r0 = null;
        $cell_r1 = null;
        foreach ($result['cells'] as $c) {
            if ($c['row'] === 0 && $c['col_index'] === $colIndex) {
                $cell_r0 = $c;
            }
            if ($c['row'] === 1 && $c['col_index'] === $colIndex) {
                $cell_r1 = $c;
            }
        }

        // row=0: 2 correct / 2 total = 100%.
        $this->assertNotNull($cell_r0);
        $this->assertEqualsWithDelta(100.0, $cell_r0['pct_correct'], 0.01);
        $this->assertSame(2, $cell_r0['count_submissions']);

        // row=1: 1 correct / 2 total = 50%.
        $this->assertNotNull($cell_r1);
        $this->assertEqualsWithDelta(50.0, $cell_r1['pct_correct'], 0.01);
        $this->assertSame(2, $cell_r1['count_submissions']);
    }
}
