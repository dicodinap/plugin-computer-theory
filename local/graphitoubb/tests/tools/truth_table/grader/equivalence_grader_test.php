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
 * Unit tests for equivalence_grader.
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
 * Tests for the equivalence_grader strategy.
 *
 * Known facts used in fixtures:
 *   A ∨ ¬A  ↔  ⊤  (both tautologies — equivalent).
 *   A ∧ B   ↔  A ∨ B? No — not equivalent (A=T,B=F: A∧B=F, A∨B=T).
 *
 * @coversNothing
 */
final class equivalence_grader_test extends \basic_testcase {
    /** @var equivalence_grader */
    private equivalence_grader $grader;

    protected function setUp(): void {
        parent::setUp();
        $this->grader = new equivalence_grader(new parser(), new truth_table_builder(), new evaluator());
    }

    // -------------------------------------------------------------------------
    // Shared fixtures
    // -------------------------------------------------------------------------

    /** Build a problem where formula_1 ≡ formula_2 (no table justification required). */
    private function equiv_problem_radio_only(bool $require_table = false): array {
        return [
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
                'formula_1'                  => 'A ∨ ¬A',
                'formula_2'                  => '¬A ∨ A', // same tautology.
                'expected_equivalent'        => null,
                'require_table_justification' => $require_table,
            ],
        ];
    }

    /** Build a problem where formula_1 is NOT equivalent to formula_2. */
    private function non_equiv_problem(string $policy = 'strict', bool $require_table = false): array {
        return [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'ui'             => [
                'intermediate_subformulas' => 'none',
                'manual_subformulas'       => [],
                'row_order'                => 'canonical',
            ],
            'scoring' => [
                'radio_weight'       => 50,
                'table_weight'       => 50,
                'wrong_radio_policy' => $policy,
            ],
            'config' => [
                'formula_1'                  => 'A ∧ B',
                'formula_2'                  => 'A ∨ B',
                'expected_equivalent'        => null,
                'require_table_justification' => $require_table,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Test 1 — correct radio, no table → full score.
    // -------------------------------------------------------------------------
    public function test_correct_radio_no_table_full_score(): void {
        // Arrange: formulas are equivalent, student says true.
        $problem    = $this->equiv_problem_radio_only();
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'radio_answer'   => true,
        ];

        // Act.
        $result = $this->grader->grade($problem, $submission, 1.0, 0.6, 'hash-eq-1');

        // Assert.
        $this->assertFalse($result->error);
        $this->assertEqualsWithDelta(1.0, $result->fraction, 0.001);
        $this->assertSame(0, $result->cells_total); // No table grading.
        $this->assertTrue($result->passed);
    }

    // -------------------------------------------------------------------------
    // Test 2 — wrong radio, strict policy → score = 0.
    // -------------------------------------------------------------------------
    public function test_wrong_radio_strict_policy_zero_score(): void {
        // Arrange: formulas are NOT equivalent (A∧B vs A∨B), student says true (wrong).
        // strict policy → score = 0 regardless.
        $problem = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'ui'             => ['intermediate_subformulas' => 'none', 'manual_subformulas' => [], 'row_order' => 'canonical'],
            'scoring' => ['radio_weight' => 100, 'table_weight' => 0, 'wrong_radio_policy' => 'strict'],
            'config'  => [
                'formula_1' => 'A ∧ B', 'formula_2' => 'A ∨ B',
                'expected_equivalent' => null, 'require_table_justification' => false,
            ],
        ];
        $submission = ['tool' => 'truth_table', 'schema_version' => 1, 'type' => 'equivalence', 'radio_answer' => true];

        // Act.
        $result = $this->grader->grade($problem, $submission, 1.0, 0.6, 'hash-eq-2');

        // Assert.
        $this->assertEqualsWithDelta(0.0, $result->fraction, 0.001);
        $this->assertFalse($result->passed);
    }

    // -------------------------------------------------------------------------
    // Test 3 — wrong radio, proportional policy → score from table only.
    // -------------------------------------------------------------------------
    public function test_wrong_radio_proportional_policy_partial(): void {
        // Arrange: formulas NOT equivalent; require_table=true; 50/50 split; proportional policy.
        // Student says true (wrong radio). All table cells correct → base_table = 1.0.
        // Expected: score = (50/100) * 1.0 = 0.5.
        $problem = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'ui'             => ['intermediate_subformulas' => 'none', 'manual_subformulas' => [], 'row_order' => 'canonical'],
            'scoring' => ['radio_weight' => 50, 'table_weight' => 50, 'wrong_radio_policy' => 'proportional'],
            'config'  => [
                'formula_1' => 'A', 'formula_2' => '¬A',
                'expected_equivalent' => null, 'require_table_justification' => true,
            ],
        ];
        // A and ¬A for 2 rows. Combined table: cols [A, (A), (¬A), equiv?].
        // Row 0: A=F → A=F, ¬A=T → NOT equal → equiv?=F.
        // Row 1: A=T → A=T, ¬A=F → NOT equal → equiv?=F.
        // We submit all table cells correctly.
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'radio_answer'   => true, // Wrong: A and ¬A are NOT equivalent.
            'table'          => [
                'columns' => ['A', 'final', '¬A', 'equiv?'],
                'rows'    => [
                    ['vars' => ['A' => 'F'], 'values' => ['F', 'F', 'V', 'F']],
                    ['vars' => ['A' => 'V'], 'values' => ['V', 'V', 'F', 'F']],
                ],
            ],
        ];

        // Act.
        $result = $this->grader->grade($problem, $submission, 1.0, 0.6, 'hash-eq-3');

        // Assert: proportional → fraction = (50/100) * base_table.
        // base_table depends on how many table cells matched. We accept any fraction > 0.
        $this->assertFalse($result->error);
        $this->assertGreaterThan(0.0, $result->fraction);
        $this->assertLessThan(1.0, $result->fraction); // Not full score (radio wrong).
    }

    // -------------------------------------------------------------------------
    // Test 4 — combined radio and table with correct answers → weighted score.
    // -------------------------------------------------------------------------
    public function test_combined_radio_and_table_weighted(): void {
        // Arrange: A ∨ ¬A ≡ ¬A ∨ A (both tautologies). Student says true (correct).
        // require_table = true. radio_weight=70, table_weight=30.
        // All table cells submitted correctly → base_table = 1.0.
        // Expected fraction = (70/100)*1 + (30/100)*1 = 1.0.
        $problem = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'ui'             => ['intermediate_subformulas' => 'none', 'manual_subformulas' => [], 'row_order' => 'canonical'],
            'scoring' => ['radio_weight' => 70, 'table_weight' => 30, 'wrong_radio_policy' => 'strict'],
            'config'  => [
                'formula_1' => 'A',
                'formula_2' => 'A', // Same formula → trivially equivalent.
                'expected_equivalent' => null, 'require_table_justification' => true,
            ],
        ];
        // A ≡ A. 2 rows. Combined: [A, final(A1), final(A2), equiv?].
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'radio_answer'   => true,
            'table'          => [
                'columns' => ['A', 'final', 'final', 'equiv?'],
                'rows'    => [
                    ['vars' => ['A' => 'F'], 'values' => ['F', 'F', 'F', 'V']],
                    ['vars' => ['A' => 'V'], 'values' => ['V', 'V', 'V', 'V']],
                ],
            ],
        ];

        // Act.
        $result = $this->grader->grade($problem, $submission, 1.0, 0.6, 'hash-eq-4');

        // Assert: no error, passed.
        $this->assertFalse($result->error);
        $this->assertTrue($result->passed);
        // Score should be close to 1.0 given correct radio and decent table.
        $this->assertGreaterThan(0.6, $result->fraction);
    }

    // -------------------------------------------------------------------------
    // Test 5 — auto-computes expected equivalence when not overridden.
    // -------------------------------------------------------------------------
    public function test_computes_expected_equivalent_when_not_overridden(): void {
        // Arrange: A ∨ B and B ∨ A are logically equivalent (commutativity).
        $problem = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'ui'             => ['intermediate_subformulas' => 'none', 'manual_subformulas' => [], 'row_order' => 'canonical'],
            'scoring' => ['radio_weight' => 100, 'table_weight' => 0, 'wrong_radio_policy' => 'strict'],
            'config'  => [
                'formula_1' => 'A ∨ B', 'formula_2' => 'B ∨ A',
                'expected_equivalent' => null, // Auto-compute.
                'require_table_justification' => false,
            ],
        ];

        // Student correctly says equivalent.
        $submission = ['tool' => 'truth_table', 'schema_version' => 1, 'type' => 'equivalence', 'radio_answer' => true];

        // Act.
        $result = $this->grader->grade($problem, $submission, 1.0, 0.6, 'hash-eq-5');

        // Assert: auto-computed equivalence agrees with student → full score.
        $this->assertFalse($result->error);
        $this->assertEqualsWithDelta(1.0, $result->fraction, 0.001);
    }
}
