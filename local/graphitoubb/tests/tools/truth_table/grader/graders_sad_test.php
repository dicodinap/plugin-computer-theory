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
 * Sad-path tests for the grader trio (complete, equivalence, classify) and the facade.
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
 * Sad-path coverage: corrupted submissions, invalid problem payloads,
 * unknown problem types via the facade.
 *
 * @coversNothing
 */
final class graders_sad_test extends \basic_testcase {
    // -------------------------------------------------------------------------
    // Facade — unknown type / invalid formula
    // -------------------------------------------------------------------------

    public function test_facade_returns_error_for_unknown_type(): void {
        $problem    = ['type' => 'frobnicate', 'config' => []];
        $submission = ['radio_answer' => null];
        $r = grader::instance()->grade($problem, $submission);
        $this->assertTrue($r->error);
        $this->assertStringContainsString('Tipo de problema desconocido', (string)$r->error_message);
        $this->assertSame(0.0, $r->fraction);
    }

    public function test_facade_returns_error_when_problem_formula_unparseable(): void {
        $problem = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'complete',
            'ui'   => ['intermediate_subformulas' => 'none', 'manual_subformulas' => [], 'row_order' => 'canonical'],
            'config' => ['formula' => '(A'], // unclosed paren.
        ];
        $submission = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'complete',
            'radio_answer' => null,
            'table' => ['columns' => ['A', 'final'], 'rows' => []],
        ];
        $r = grader::instance()->grade($problem, $submission);
        $this->assertTrue($r->error);
        $this->assertStringContainsString('Error interno', (string)$r->error_message);
    }

    public function test_facade_returns_error_when_classify_formula_lex_invalid(): void {
        $problem = [
            'type' => 'classify',
            'config' => ['formula' => 'a', 'require_table_justification' => false],
            'scoring' => ['radio_weight' => 100, 'table_weight' => 0, 'wrong_radio_policy' => 'strict'],
            'ui' => ['intermediate_subformulas' => 'none'],
        ];
        $submission = ['radio_answer' => 'tautology'];
        $r = grader::instance()->grade($problem, $submission);
        $this->assertTrue($r->error);
    }

    // -------------------------------------------------------------------------
    // complete_grader — corrupted submissions
    // -------------------------------------------------------------------------

    private function complete_problem(): array {
        return [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'complete',
            'ui' => ['intermediate_subformulas' => 'none', 'manual_subformulas' => [], 'row_order' => 'canonical'],
            'config' => ['formula' => 'A ∧ B'],
        ];
    }

    public function test_complete_empty_rows_yields_zero_score_no_crash(): void {
        $grader = new complete_grader(new parser(), new truth_table_builder(), new evaluator());
        $submission = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'complete',
            'radio_answer' => null,
            'table' => ['columns' => ['A', 'B', 'final'], 'rows' => []],
        ];
        $r = $grader->grade($this->complete_problem(), $submission, 1.0, 0.6, 'h');
        $this->assertSame(0.0, $r->fraction);
        $this->assertSame(0, $r->cells_correct);
        $this->assertSame(4, $r->cells_total); // 2^2 rows × 1 gradeable col.
        $this->assertFalse($r->passed);
        $this->assertFalse($r->error);
    }

    public function test_complete_reordered_columns_still_correct_by_label(): void {
        // Reorder columns: 'final' before variables. Grader looks up by label so order shouldn't matter.
        $grader = new complete_grader(new parser(), new truth_table_builder(), new evaluator());
        $submission = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'complete',
            'radio_answer' => null,
            'table' => [
                'columns' => ['final', 'A', 'B'],
                'rows' => [
                    ['vars' => ['A' => 'F', 'B' => 'F'], 'values' => ['F', 'F', 'F']],
                    ['vars' => ['A' => 'F', 'B' => 'V'], 'values' => ['F', 'F', 'V']],
                    ['vars' => ['A' => 'V', 'B' => 'F'], 'values' => ['F', 'V', 'F']],
                    ['vars' => ['A' => 'V', 'B' => 'V'], 'values' => ['V', 'V', 'V']],
                ],
            ],
        ];
        $r = $grader->grade($this->complete_problem(), $submission, 1.0, 0.6, 'h');
        $this->assertSame(4, $r->cells_correct);
        $this->assertSame(4, $r->cells_total);
        $this->assertSame(1.0, $r->fraction);
    }

    public function test_complete_garbage_values_treated_as_empty(): void {
        // Values outside {V, F, ''} are normalised to null (empty) — no crash, all wrong.
        $grader = new complete_grader(new parser(), new truth_table_builder(), new evaluator());
        $submission = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'complete',
            'radio_answer' => null,
            'table' => [
                'columns' => ['A', 'B', 'final'],
                'rows' => [
                    ['vars' => ['A' => 'F', 'B' => 'F'], 'values' => ['F', 'F', 'maybe']],
                    ['vars' => ['A' => 'F', 'B' => 'V'], 'values' => ['F', 'V', 42]],
                    ['vars' => ['A' => 'V', 'B' => 'F'], 'values' => ['V', 'F', null]],
                    ['vars' => ['A' => 'V', 'B' => 'V'], 'values' => ['V', 'V', '']],
                ],
            ],
        ];
        $r = $grader->grade($this->complete_problem(), $submission, 1.0, 0.6, 'h');
        $this->assertSame(0, $r->cells_correct);
        $this->assertSame(4, $r->cells_total);
        // All 4 feedback items are empty-or-incorrect; explanations are Spanish.
        foreach ($r->feedback_items as $fi) {
            $this->assertNotSame('Celda correcta.', $fi->explanation);
        }
    }

    // -------------------------------------------------------------------------
    // equivalence_grader
    // -------------------------------------------------------------------------

    private function equivalence_problem(bool $require_table = false): array {
        return [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'equivalence',
            'ui' => ['intermediate_subformulas' => 'none', 'manual_subformulas' => [], 'row_order' => 'canonical'],
            'scoring' => ['radio_weight' => 50, 'table_weight' => 50, 'wrong_radio_policy' => 'strict'],
            'config' => [
                'formula_1' => 'A',
                'formula_2' => 'A',
                'expected_equivalent' => true,
                'require_table_justification' => $require_table,
            ],
        ];
    }

    public function test_equivalence_null_radio_is_incorrect(): void {
        $grader = new equivalence_grader(new parser(), new truth_table_builder(), new evaluator());
        $submission = ['radio_answer' => null];
        $r = $grader->grade($this->equivalence_problem(false), $submission, 1.0, 0.6, 'h');
        $this->assertSame(0.0, $r->fraction);
        $this->assertFalse($r->passed);
        // Radio feedback item must be present and marked incorrect.
        $this->assertSame('radio', $r->feedback_items[0]->cell_kind);
        $this->assertFalse($r->feedback_items[0]->is_correct);
    }

    public function test_equivalence_empty_table_with_require_table_strict_policy(): void {
        // Wrong radio + strict → fraction = 0 regardless of table state.
        $grader = new equivalence_grader(new parser(), new truth_table_builder(), new evaluator());
        $submission = [
            'radio_answer' => false, // expected true.
            'table' => ['columns' => [], 'rows' => []],
        ];
        $r = $grader->grade($this->equivalence_problem(true), $submission, 1.0, 0.6, 'h');
        $this->assertSame(0.0, $r->fraction);
        $this->assertGreaterThan(0, $r->cells_total); // table was still graded.
        $this->assertSame(0, $r->cells_correct);
    }

    public function test_equivalence_reordered_columns_match_by_label(): void {
        // formula_1 = A∨B, formula_2 = B∨A. Combined cols: ['A','B','final₁','final₂','equiv?'].
        $problem = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'equivalence',
            'ui' => ['intermediate_subformulas' => 'none', 'manual_subformulas' => [], 'row_order' => 'canonical'],
            'scoring' => ['radio_weight' => 50, 'table_weight' => 50, 'wrong_radio_policy' => 'strict'],
            'config' => [
                'formula_1' => 'A ∨ B',
                'formula_2' => 'B ∨ A',
                'expected_equivalent' => true,
                'require_table_justification' => true,
            ],
        ];
        $grader = new equivalence_grader(new parser(), new truth_table_builder(), new evaluator());
        // Submit columns in reversed order; values inserted accordingly.
        // Per row, expected (final₁, final₂, equiv?) = derived from A∨B.
        // Row order = canonical (A,B): 00→FFT, 01→VVT, 10→VVT, 11→VVT.
        $submission = [
            'radio_answer' => true,
            'table' => [
                'columns' => ['equiv?', 'final₂', 'final₁'],
                'rows' => [
                    ['vars' => ['A' => 'F', 'B' => 'F'], 'values' => ['V', 'F', 'F']],
                    ['vars' => ['A' => 'F', 'B' => 'V'], 'values' => ['V', 'V', 'V']],
                    ['vars' => ['A' => 'V', 'B' => 'F'], 'values' => ['V', 'V', 'V']],
                    ['vars' => ['A' => 'V', 'B' => 'V'], 'values' => ['V', 'V', 'V']],
                ],
            ],
        ];
        $r = $grader->grade($problem, $submission, 1.0, 0.6, 'h');
        $this->assertFalse($r->error);
        $this->assertSame(12, $r->cells_total); // 4 rows × 3 gradeable cols.
        $this->assertSame(12, $r->cells_correct);
        $this->assertSame(1.0, $r->fraction);
    }

    public function test_equivalence_garbage_value_in_table(): void {
        $problem = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'equivalence',
            'ui' => ['intermediate_subformulas' => 'none', 'manual_subformulas' => [], 'row_order' => 'canonical'],
            'scoring' => ['radio_weight' => 50, 'table_weight' => 50, 'wrong_radio_policy' => 'proportional'],
            'config' => [
                'formula_1' => 'A',
                'formula_2' => 'A',
                'expected_equivalent' => true,
                'require_table_justification' => true,
            ],
        ];
        $grader = new equivalence_grader(new parser(), new truth_table_builder(), new evaluator());
        // Radio wrong + proportional means score = table_weight * base_table.
        $submission = [
            'radio_answer' => false,
            'table' => [
                'columns' => ['final₁', 'final₂', 'equiv?'],
                'rows' => [
                    ['vars' => ['A' => 'F'], 'values' => ['nope', 'nope', 'nope']],
                    ['vars' => ['A' => 'V'], 'values' => ['nope', 'nope', 'nope']],
                ],
            ],
        ];
        $r = $grader->grade($problem, $submission, 1.0, 0.6, 'h');
        // base_table = 0 (all garbage), so fraction = 0.
        $this->assertSame(0.0, $r->fraction);
        $this->assertSame(6, $r->cells_total);
        $this->assertSame(0, $r->cells_correct);
    }

    // -------------------------------------------------------------------------
    // classify_grader
    // -------------------------------------------------------------------------

    private function classify_problem(string $expected = 'tautology', bool $require_table = false): array {
        return [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'classify',
            'ui' => ['intermediate_subformulas' => 'none', 'manual_subformulas' => [], 'row_order' => 'canonical'],
            'scoring' => ['radio_weight' => 100, 'table_weight' => 0, 'wrong_radio_policy' => 'strict'],
            'config' => [
                'formula' => 'A ∨ ¬A',
                'expected_class' => $expected,
                'require_table_justification' => $require_table,
            ],
        ];
    }

    public function test_classify_null_radio_is_incorrect(): void {
        $grader = new classify_grader(new parser(), new truth_table_builder(), new evaluator());
        $submission = ['radio_answer' => null];
        $r = $grader->grade($this->classify_problem(), $submission, 1.0, 0.6, 'h');
        $this->assertSame(0.0, $r->fraction);
        $this->assertFalse($r->feedback_items[0]->is_correct);
    }

    public function test_classify_reordered_columns_match_by_label(): void {
        // formula A ∨ ¬A — tautology. With intermediate='auto' the builder emits subformulas.
        $problem = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'classify',
            'ui' => ['intermediate_subformulas' => 'auto', 'manual_subformulas' => [], 'row_order' => 'canonical'],
            'scoring' => ['radio_weight' => 50, 'table_weight' => 50, 'wrong_radio_policy' => 'strict'],
            'config' => [
                'formula' => 'A ∨ ¬A',
                'expected_class' => 'tautology',
                'require_table_justification' => true,
            ],
        ];
        $grader = new classify_grader(new parser(), new truth_table_builder(), new evaluator());
        // Build correct submission with cols in reverse order; lookup is by label.
        // Expected cols include 'A', '¬A' and 'final'. Reverse to ['final','¬A'].
        $submission = [
            'radio_answer' => 'tautology',
            'table' => [
                'columns' => ['final', '¬A'],
                'rows' => [
                    ['vars' => ['A' => 'F'], 'values' => ['V', 'V']],
                    ['vars' => ['A' => 'V'], 'values' => ['V', 'F']],
                ],
            ],
        ];
        $r = $grader->grade($problem, $submission, 1.0, 0.6, 'h');
        $this->assertFalse($r->error);
        $this->assertSame(1.0, $r->fraction); // radio + all table cells correct.
    }

    public function test_classify_garbage_radio_treated_as_wrong(): void {
        $grader = new classify_grader(new parser(), new truth_table_builder(), new evaluator());
        $submission = ['radio_answer' => 'banana'];
        $r = $grader->grade($this->classify_problem('tautology'), $submission, 1.0, 0.6, 'h');
        $this->assertSame(0.0, $r->fraction);
        $this->assertFalse($r->feedback_items[0]->is_correct);
    }

    public function test_classify_empty_rows_with_require_table(): void {
        $grader = new classify_grader(new parser(), new truth_table_builder(), new evaluator());
        $submission = [
            'radio_answer' => 'tautology',
            'table' => ['columns' => [], 'rows' => []],
        ];
        $r = $grader->grade($this->classify_problem('tautology', true), $submission, 1.0, 0.6, 'h');
        // Radio correct → still earns radio_weight fraction of the score.
        $this->assertGreaterThan(0, $r->cells_total);
        $this->assertSame(0, $r->cells_correct);
    }
}
