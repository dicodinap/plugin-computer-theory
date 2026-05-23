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
 * Unit tests for the grader facade.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\grader;

/**
 * Tests that the grader facade dispatches to the correct strategy and handles errors.
 *
 * @coversNothing
 */
final class grader_test extends \basic_testcase {
    /** @var grader */
    private grader $grader;

    protected function setUp(): void {
        parent::setUp();
        $this->grader = grader::instance();
    }

    // -------------------------------------------------------------------------
    // Test 1 — dispatches to complete_grader for type=complete.
    // -------------------------------------------------------------------------
    public function test_dispatches_to_complete_grader_for_type_complete(): void {
        // Arrange: minimal complete problem with formula A ∧ B (2 vars, 4 rows, 1 subformula col + 1 final col).
        $problem = [
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
        // A ∧ B with no intermediate, 4 rows, final column only.
        // Row 0: A=F,B=F → F; Row 1: A=F,B=T → F; Row 2: A=T,B=F → F; Row 3: A=T,B=T → V.
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'complete',
            'table'          => [
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

        // Act.
        $result = $this->grader->grade($problem, $submission);

        // Assert: no error, dispatched to complete grader (score > 0, error = false).
        $this->assertFalse($result->error);
        $this->assertNull($result->error_message);
        // All 4 final cells correct → fraction = 1.0.
        $this->assertEqualsWithDelta(1.0, $result->fraction, 0.001);
    }

    // -------------------------------------------------------------------------
    // Test 2 — dispatches to equivalence_grader for type=equivalence.
    // -------------------------------------------------------------------------
    public function test_dispatches_to_equivalence_grader_for_type_equivalence(): void {
        // Arrange: A ∨ B is NOT equivalent to A ∧ B.
        $problem = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'ui'             => [
                'intermediate_subformulas' => 'none',
                'manual_subformulas'       => [],
                'row_order'                => 'canonical',
            ],
            'scoring' => [
                'radio_weight'       => 100,
                'table_weight'       => 0,
                'wrong_radio_policy' => 'strict',
            ],
            'config' => [
                'formula_1'                  => 'A ∨ B',
                'formula_2'                  => 'A ∧ B',
                'expected_equivalent'        => null,
                'require_table_justification' => false,
            ],
        ];
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'radio_answer'   => false, // Correct: they are NOT equivalent.
        ];

        // Act.
        $result = $this->grader->grade($problem, $submission);

        // Assert: dispatched to equivalence grader, no error, radio correct → full score.
        $this->assertFalse($result->error);
        $this->assertEqualsWithDelta(1.0, $result->fraction, 0.001);
    }

    // -------------------------------------------------------------------------
    // Test 3 — dispatches to classify_grader for type=classify.
    // -------------------------------------------------------------------------
    public function test_dispatches_to_classify_grader_for_type_classify(): void {
        // Arrange: A ∨ ¬A is a tautology.
        $problem = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'classify',
            'ui'             => [
                'intermediate_subformulas' => 'none',
                'manual_subformulas'       => [],
                'row_order'                => 'canonical',
            ],
            'scoring' => [
                'radio_weight'       => 100,
                'table_weight'       => 0,
                'wrong_radio_policy' => 'strict',
            ],
            'config' => [
                'formula'                    => 'A ∨ ¬A',
                'expected_class'             => null,
                'require_table_justification' => false,
            ],
        ];
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'classify',
            'radio_answer'   => 'tautology',
        ];

        // Act.
        $result = $this->grader->grade($problem, $submission);

        // Assert: dispatched to classify grader, no error, correct classification → full score.
        $this->assertFalse($result->error);
        $this->assertEqualsWithDelta(1.0, $result->fraction, 0.001);
    }

    // -------------------------------------------------------------------------
    // Test 4 — returns error grading_result for an invalid / corrupted payload.
    // -------------------------------------------------------------------------
    public function test_returns_error_grading_when_problem_invalid(): void {
        // Arrange: problem with unknown type — grader cannot dispatch.
        $problem    = ['type' => 'unknown_type', 'tool' => 'truth_table'];
        $submission = [];

        // Act.
        $result = $this->grader->grade($problem, $submission);

        // Assert: error result, score 0, no cells graded.
        $this->assertTrue($result->error);
        $this->assertNotNull($result->error_message);
        $this->assertEqualsWithDelta(0.0, $result->score, 0.001);
        $this->assertSame(0, $result->cells_total);
    }
}
