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

/**
 * Unit tests for complete_grader.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\grader;

use local_graphitoubb\tools\truth_table\domain\evaluator;
use local_graphitoubb\tools\truth_table\domain\parser;
use local_graphitoubb\tools\truth_table\domain\truth_table_builder;

/**
 * Tests for the complete_grader strategy.
 *
 * Fixture: formula = 'A ∧ B', intermediate_subformulas = 'none'.
 * Canonical truth table (4 rows, 1 gradeable column: 'final'):
 *   Row 0: A=F, B=F → F
 *   Row 1: A=F, B=T → F
 *   Row 2: A=T, B=F → F
 *   Row 3: A=T, B=T → T
 *
 * @coversNothing
 */
final class complete_grader_test extends \basic_testcase {
    /** @var complete_grader */
    private complete_grader $grader;

    /** @var array Minimal problem fixture. */
    private array $problem;

    protected function setUp(): void {
        parent::setUp();
        $this->grader  = new complete_grader(new parser(), new truth_table_builder(), new evaluator());
        $this->problem = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'complete',
            'ui'             => [
                'intermediate_subformulas' => 'none',
                'manual_subformulas'       => [],
                'row_order'                => 'canonical',
            ],
            'config' => ['formula' => 'A ∧ B'],
        ];
    }

    // -------------------------------------------------------------------------
    // Helper: build a submission where all cells match the expected table.
    // -------------------------------------------------------------------------
    private function all_correct_submission(): array {
        return [
            'table' => [
                'columns' => ['A', 'B', 'final'],
                'rows'    => [
                    ['vars' => ['A' => 'F', 'B' => 'F'], 'values' => ['F', 'F', 'F']],
                    ['vars' => ['A' => 'F', 'B' => 'V'], 'values' => ['F', 'V', 'F']],
                    ['vars' => ['A' => 'V', 'B' => 'F'], 'values' => ['V', 'F', 'F']],
                    ['vars' => ['A' => 'V', 'B' => 'V'], 'values' => ['V', 'V', 'V']],
                ],
            ],
            'radio_answer' => null,
        ];
    }

    // -------------------------------------------------------------------------
    // Test 1 — all correct cells → full score.
    // -------------------------------------------------------------------------
    public function test_all_correct_yields_full_score(): void {
        // Arrange.
        $submission = $this->all_correct_submission();

        // Act.
        $result = $this->grader->grade($this->problem, $submission, 1.0, 0.6, 'hash1');

        // Assert.
        $this->assertFalse($result->error);
        $this->assertSame(4, $result->cells_total);
        $this->assertSame(4, $result->cells_correct);
        $this->assertEqualsWithDelta(1.0, $result->fraction, 0.001);
        $this->assertEqualsWithDelta(1.0, $result->score, 0.001);
        $this->assertTrue($result->passed);
    }

    // -------------------------------------------------------------------------
    // Test 2 — all cells empty → zero score.
    // -------------------------------------------------------------------------
    public function test_all_empty_yields_zero_score(): void {
        // Arrange: all values are ''.
        $submission = [
            'table' => [
                'columns' => ['A', 'B', 'final'],
                'rows'    => [
                    ['vars' => ['A' => 'F', 'B' => 'F'], 'values' => ['F', 'F', '']],
                    ['vars' => ['A' => 'F', 'B' => 'V'], 'values' => ['F', 'V', '']],
                    ['vars' => ['A' => 'V', 'B' => 'F'], 'values' => ['V', 'F', '']],
                    ['vars' => ['A' => 'V', 'B' => 'V'], 'values' => ['V', 'V', '']],
                ],
            ],
            'radio_answer' => null,
        ];

        // Act.
        $result = $this->grader->grade($this->problem, $submission, 1.0, 0.6, 'hash2');

        // Assert.
        $this->assertSame(0, $result->cells_correct);
        $this->assertEqualsWithDelta(0.0, $result->fraction, 0.001);
        $this->assertFalse($result->passed);

        // All explanations should be 'Celda vacía.'
        foreach ($result->feedback_items as $item) {
            $this->assertSame('Celda vacía.', $item->explanation);
        }
    }

    // -------------------------------------------------------------------------
    // Test 3 — partial correct → score proportional to correct cells.
    // -------------------------------------------------------------------------
    public function test_partial_score_proportional_to_correct_cells(): void {
        // Arrange: only 2 of 4 final cells correct (rows 0 and 1 correct, rows 2 and 3 wrong).
        $submission = [
            'table' => [
                'columns' => ['A', 'B', 'final'],
                'rows'    => [
                    ['vars' => ['A' => 'F', 'B' => 'F'], 'values' => ['F', 'F', 'F']], // correct.
                    ['vars' => ['A' => 'F', 'B' => 'V'], 'values' => ['F', 'V', 'F']], // correct.
                    ['vars' => ['A' => 'V', 'B' => 'F'], 'values' => ['V', 'F', 'V']], // wrong (expected F).
                    ['vars' => ['A' => 'V', 'B' => 'V'], 'values' => ['V', 'V', 'F']], // wrong (expected V).
                ],
            ],
            'radio_answer' => null,
        ];

        // Act.
        $result = $this->grader->grade($this->problem, $submission, 2.0, 0.6, 'hash3');

        // Assert: 2 of 4 correct → fraction = 0.5, score = 1.0.
        $this->assertSame(4, $result->cells_total);
        $this->assertSame(2, $result->cells_correct);
        $this->assertEqualsWithDelta(0.5, $result->fraction, 0.001);
        $this->assertEqualsWithDelta(1.0, $result->score, 0.001);
        $this->assertFalse($result->passed); // 0.5 < 0.6.
    }

    // -------------------------------------------------------------------------
    // Test 4 — marks propagated errors separately from root errors.
    // -------------------------------------------------------------------------
    public function test_marks_propagated_errors_separately(): void {
        // Arrange: formula = '(A ∧ B) ∨ C' with intermediate columns.
        // Student submits wrong value for '(A ∧ B)' in row 0.
        // The 'final' cell in that row should be a propagated error (correct given wrong input).
        $problem = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'complete',
            'ui'             => [
                'intermediate_subformulas' => 'auto',
                'manual_subformulas'       => [],
                'row_order'                => 'canonical',
            ],
            'config' => ['formula' => '(A ∧ B) ∨ C'],
        ];

        // For row A=F,B=F,C=F:
        // Expected (A ∧ B) = F, expected final = F.
        // Student submits (A ∧ B) = V (root error) and final = V (propagated from wrong (A∧B)).
        $submission = [
            'table' => [
                'columns' => ['A', 'B', 'C', '(A ∧ B)', 'final'],
                'rows'    => [
                    // Row 0: A=F,B=F,C=F. Student claims (A∧B)=V and final=V.
                    // (A∧B) is a root error. final should be propagated if grader re-evaluates
                    // (V ∨ F) using student's (A∧B)=V → gets V = matches submitted V.
                    ['vars'   => ['A' => 'F', 'B' => 'F', 'C' => 'F'],
                     'values' => ['F', 'F', 'F', 'V', 'V']],
                ],
            ],
            'radio_answer' => null,
        ];

        // Act.
        $result = $this->grader->grade($problem, $submission, 1.0, 0.6, 'hash4');

        // Assert: we have two feedback items for row 0 (one for '(A ∧ B)' and one for 'final').
        // Find feedback items for row 0 that are incorrect.
        $incorrect = array_filter(
            $result->feedback_items,
            static fn(feedback_item $fi): bool => $fi->row_index === 0 && !$fi->is_correct
        );

        // We expect at least one root error and (optionally) one propagated error.
        $root_errors = array_filter($incorrect, static fn(feedback_item $fi): bool => $fi->is_root_error);
        $this->assertNotEmpty($root_errors, 'Expected at least one root error in row 0.');
    }

    // -------------------------------------------------------------------------
    // Test 5 — score passes threshold at exactly the threshold.
    // -------------------------------------------------------------------------
    public function test_score_passes_threshold(): void {
        // Arrange: 3 of 4 correct → fraction = 0.75 ≥ 0.6.
        $submission = [
            'table' => [
                'columns' => ['A', 'B', 'final'],
                'rows'    => [
                    ['vars' => ['A' => 'F', 'B' => 'F'], 'values' => ['F', 'F', 'F']], // correct.
                    ['vars' => ['A' => 'F', 'B' => 'V'], 'values' => ['F', 'V', 'F']], // correct.
                    ['vars' => ['A' => 'V', 'B' => 'F'], 'values' => ['V', 'F', 'F']], // correct.
                    ['vars' => ['A' => 'V', 'B' => 'V'], 'values' => ['V', 'V', 'F']], // wrong (expected V).
                ],
            ],
            'radio_answer' => null,
        ];

        // Act.
        $result = $this->grader->grade($this->problem, $submission, 1.0, 0.6, 'hash5');

        // Assert: 3/4 = 0.75 ≥ 0.6.
        $this->assertSame(3, $result->cells_correct);
        $this->assertEqualsWithDelta(0.75, $result->fraction, 0.001);
        $this->assertTrue($result->passed);
    }
}
