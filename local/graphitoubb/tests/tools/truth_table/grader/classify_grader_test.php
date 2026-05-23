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
 * Unit tests for classify_grader.
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
 * Tests for the classify_grader strategy.
 *
 * Formulas:
 *   'A ∨ ¬A'   — tautology (all rows true).
 *   'A ∧ ¬A'   — contradiction (all rows false).
 *   'A'         — contingency (A=F → false; A=T → true).
 *
 * @coversNothing
 */
final class classify_grader_test extends \basic_testcase {
    /** @var classify_grader */
    private classify_grader $grader;

    protected function setUp(): void {
        parent::setUp();
        $this->grader = new classify_grader(new parser(), new truth_table_builder(), new evaluator());
    }

    // -------------------------------------------------------------------------
    // Helper: build a minimal problem for a given formula (radio only).
    // -------------------------------------------------------------------------
    private function build_problem(string $formula, ?string $expected_class = null): array {
        return [
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
                'formula'                    => $formula,
                'expected_class'             => $expected_class,
                'require_table_justification' => false,
            ],
        ];
    }

    private function build_submission(string $radio_answer): array {
        return [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'classify',
            'radio_answer'   => $radio_answer,
        ];
    }

    // -------------------------------------------------------------------------
    // Test 1 — tautology identified correctly → full score.
    // -------------------------------------------------------------------------
    public function test_tautology_classification(): void {
        // Arrange: A ∨ ¬A is a tautology.
        $problem    = $this->build_problem('A ∨ ¬A');
        $submission = $this->build_submission('tautology');

        // Act.
        $result = $this->grader->grade($problem, $submission, 1.0, 0.6, 'hash-cl-1');

        // Assert.
        $this->assertFalse($result->error);
        $this->assertEqualsWithDelta(1.0, $result->fraction, 0.001);
        $this->assertTrue($result->passed);

        // Radio item should be correct.
        $radio_item = $result->feedback_items[0];
        $this->assertTrue($radio_item->is_correct);
        $this->assertSame('radio', $radio_item->cell_kind);
    }

    // -------------------------------------------------------------------------
    // Test 2 — contradiction identified correctly → full score.
    // -------------------------------------------------------------------------
    public function test_contradiction_classification(): void {
        // Arrange: A ∧ ¬A is a contradiction.
        $problem    = $this->build_problem('A ∧ ¬A');
        $submission = $this->build_submission('contradiction');

        // Act.
        $result = $this->grader->grade($problem, $submission, 1.0, 0.6, 'hash-cl-2');

        // Assert.
        $this->assertFalse($result->error);
        $this->assertEqualsWithDelta(1.0, $result->fraction, 0.001);
        $this->assertTrue($result->passed);
    }

    // -------------------------------------------------------------------------
    // Test 3 — contingency identified correctly → full score.
    // -------------------------------------------------------------------------
    public function test_contingency_classification(): void {
        // Arrange: 'A' is a contingency (false when A=F, true when A=T).
        $problem    = $this->build_problem('A');
        $submission = $this->build_submission('contingency');

        // Act.
        $result = $this->grader->grade($problem, $submission, 1.0, 0.6, 'hash-cl-3');

        // Assert.
        $this->assertFalse($result->error);
        $this->assertEqualsWithDelta(1.0, $result->fraction, 0.001);
        $this->assertTrue($result->passed);
    }

    // -------------------------------------------------------------------------
    // Test 4 — wrong classification with strict policy → score = 0.
    // -------------------------------------------------------------------------
    public function test_wrong_classification_strict_zero(): void {
        // Arrange: 'A ∨ ¬A' is a tautology; student says 'contingency'.
        $problem    = $this->build_problem('A ∨ ¬A');
        $submission = $this->build_submission('contingency');

        // Act.
        $result = $this->grader->grade($problem, $submission, 1.0, 0.6, 'hash-cl-4');

        // Assert.
        $this->assertFalse($result->error);
        $this->assertEqualsWithDelta(0.0, $result->fraction, 0.001);
        $this->assertFalse($result->passed);

        // Radio item should be incorrect.
        $radio_item = $result->feedback_items[0];
        $this->assertFalse($radio_item->is_correct);
        $this->assertStringContainsString('tautology', $radio_item->explanation);
    }
}
