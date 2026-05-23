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
 * Grader for 'complete' type problems — student fills all table cells.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\grader;

use local_graphitoubb\tools\truth_table\domain\evaluator;
use local_graphitoubb\tools\truth_table\domain\parser;
use local_graphitoubb\tools\truth_table\domain\truth_table_builder;
use local_graphitoubb\tools\truth_table\domain\ast\formula_ast;

/**
 * Grades a 'complete' truth table submission.
 *
 * Score = (cells_correct / cells_total) * max_grade.
 * Variable columns are never graded; only subformula and final columns count.
 * Empty cells ('') count as incorrect with no extra penalty.
 *
 * Propagated-error detection (spec §5):
 *   For each incorrect non-leaf cell, extract the expected operand values from
 *   the *student's submitted* values in the corresponding operand columns.
 *   Re-evaluate the operator on those values. If the result matches what the
 *   student submitted (but still disagrees with expected) → is_root_error = false
 *   (the error propagated from an upstream cell). Otherwise → is_root_error = true.
 *
 *   When an operand's column is absent from the submission columns (e.g. the
 *   problem used intermediate=none and didn't show that column) we fall back to
 *   evaluating the operand from row.vars (the canonical variable assignment),
 *   because the student had no opportunity to be "wrong" about that operand.
 */
final class complete_grader {
    /**
     * Build the grader with its domain collaborators.
     *
     * @param parser              $parser
     * @param truth_table_builder $builder
     * @param evaluator           $evaluator
     */
    public function __construct(
        private readonly parser $parser,
        private readonly truth_table_builder $builder,
        private readonly evaluator $evaluator
    ) {
    }

    /**
     * Grade a complete-type submission.
     *
     * @param  array  $problem        Decoded problem JSON (must contain config.formula, ui).
     * @param  array  $submission     Decoded submission JSON (must contain table.columns and table.rows).
     * @param  float  $max_grade      Maximum possible score.
     * @param  float  $pass_threshold Fraction threshold to mark passed (e.g. 0.6).
     * @param  string $hash           SHA-256 of the problem payload.
     * @return grading_result
     */
    public function grade(
        array $problem,
        array $submission,
        float $max_grade,
        float $pass_threshold,
        string $hash
    ): grading_result {
        $formula = $problem['config']['formula'] ?? '';
        $ui      = $problem['ui'] ?? [];

        $intermediate        = $ui['intermediate_subformulas'] ?? 'auto';
        $manual_subformulas  = $ui['manual_subformulas'] ?? [];

        // Build the expected truth table.
        $ast      = $this->parser->parse($formula);
        $expected = $this->builder->build($ast, [
            'intermediate'       => $intermediate,
            'manual_subformulas' => $manual_subformulas,
        ]);

        // Extract variables from the expected table — these are not graded.
        $variables   = $expected['variables'];
        $exp_columns = $expected['columns']; // includes variable cols + subformula cols + 'final'.
        $exp_rows    = $expected['rows'];

        // Determine which columns are gradeable (non-variable).
        // Column label 'final' maps to the root formula canonical in the expected row values array.
        // Column index in expected: 0..(n_vars-1) are variables, then subformulas, then 'final'.
        $n_vars    = count($variables);
        $n_columns = count($exp_columns);

        // Submission table.
        $sub_columns = $submission['table']['columns'] ?? [];
        $sub_rows    = $submission['table']['rows'] ?? [];

        // Determine if submission.columns includes variable columns or only gradeables.
        // Two valid conventions exist in iter1: builder emits all columns (variables + gradeable),
        // while the UI helper and qtype renderer may emit only gradeable columns.
        // Detect by comparing the count of values in the first row to the length of columns.
        $first_row_values = $sub_rows[0]['values'] ?? [];
        $cols_include_vars = (count($sub_columns) > count($first_row_values));
        $sub_offset = $cols_include_vars ? $n_vars : 0;

        // Build a column-label → values-index map for lookup.
        $sub_col_index = [];
        foreach ($sub_columns as $idx => $label) {
            $value_idx = $idx - $sub_offset;
            if ($value_idx >= 0) {
                $sub_col_index[$label] = $value_idx;
            }
        }

        $feedback_items = [];
        $cells_total    = 0;
        $cells_correct  = 0;

        foreach ($exp_rows as $row_idx => $exp_row) {
            $assignment  = $exp_row['vars'];   // ['A' => bool, ...]
            $exp_values  = $exp_row['values']; // bool[] indexed to match $exp_columns.

            $sub_row    = $sub_rows[$row_idx] ?? null;
            $sub_values = $sub_row['values'] ?? [];

            // For each gradeable column (skip variable columns).
            for ($col_idx = $n_vars; $col_idx < $n_columns; $col_idx++) {
                $col_label    = $exp_columns[$col_idx];
                $expected_val = $exp_values[$col_idx]; // bool.
                $cell_kind    = ($col_idx === $n_columns - 1) ? 'final' : 'subformula';

                // Resolve the submitted value for this column.
                // Submissions may label the final column either 'final' (builder default)
                // or with the root formula's canonical form (UI helper / qtype renderer).
                // As a last resort, fall back to positional lookup (values[col_idx - n_vars])
                // when the submission's column count matches the expected gradeable count.
                $submitted_raw = null;
                if ($cell_kind === 'final') {
                    $root_canonical = $ast->canonical();
                    $root_unwrapped = (strlen($root_canonical) > 1
                        && $root_canonical[0] === '(' && $root_canonical[strlen($root_canonical) - 1] === ')')
                        ? substr($root_canonical, 1, -1) : $root_canonical;
                    foreach (['final', $root_canonical, $root_unwrapped, $col_label] as $candidate) {
                        if ($candidate !== null && isset($sub_col_index[$candidate])) {
                            $submitted_raw = $sub_values[$sub_col_index[$candidate]] ?? '';
                            break;
                        }
                    }
                } else if (isset($sub_col_index[$col_label])) {
                    $submitted_raw = $sub_values[$sub_col_index[$col_label]] ?? '';
                }

                // Positional fallback — submission's gradeable values array aligned by index.
                if ($submitted_raw === null) {
                    $expected_gradeable_count = $n_columns - $n_vars;
                    if (is_array($sub_values) && count($sub_values) === $expected_gradeable_count) {
                        $submitted_raw = $sub_values[$col_idx - $n_vars] ?? '';
                    }
                }

                // Normalise: 'V' → true, 'F' → false, '' / null → null (empty).
                $submitted_bool = $this->parse_cell_value($submitted_raw);
                $is_empty       = ($submitted_bool === null);
                $is_correct     = (!$is_empty && $submitted_bool === $expected_val);

                // Propagation analysis for incorrect non-leaf cells.
                $is_root_error = true;
                if (!$is_correct && !$is_empty && $cell_kind === 'subformula') {
                    $is_root_error = $this->is_root_error(
                        $col_label,
                        $submitted_bool,
                        $assignment,
                        $sub_col_index,
                        $sub_values,
                        $n_vars,
                        $exp_columns
                    );
                }

                // Build explanation in Spanish.
                $explanation = $this->build_explanation($is_empty, $is_correct, $expected_val, $is_root_error);

                $feedback_items[] = new feedback_item(
                    row_index: $row_idx,
                    col_label: $col_label,
                    cell_kind: $cell_kind,
                    submitted: $submitted_raw,
                    expected: $expected_val ? 'V' : 'F',
                    is_correct: $is_correct,
                    is_root_error: $is_root_error,
                    explanation: $explanation
                );

                $cells_total++;
                if ($is_correct) {
                    $cells_correct++;
                }
            }
        }

        $fraction = ($cells_total > 0) ? $cells_correct / $cells_total : 0.0;
        $score    = round($fraction * $max_grade, 2);
        $passed   = $fraction >= $pass_threshold;

        return new grading_result(
            score: $score,
            fraction: $fraction,
            passed: $passed,
            cells_total: $cells_total,
            cells_correct: $cells_correct,
            feedback_items: $feedback_items,
            error: false,
            error_message: null,
            problem_snapshot_hash: $hash
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Determine whether an incorrect subformula cell is a root error or a
     * propagated error from an upstream (operand) cell.
     *
     * Algorithm:
     *   1. Parse the column's formula label to get the AST.
     *   2. Walk the immediate operand ASTs of that node.
     *   3. For each operand, look up the student's submitted value in the
     *      corresponding column (by canonical label). If that column isn't
     *      present in the submission, use the canonical variable assignment.
     *   4. Evaluate the operator on those resolved operand values.
     *   5. If result == submitted_bool → is_root_error = false (propagated).
     *      Otherwise → is_root_error = true.
     *
     * @param  string       $col_label       Column label (canonical formula of this cell).
     * @param  bool         $submitted_bool  What the student submitted (normalised bool).
     * @param  array        $assignment      Variable assignment for this row.
     * @param  array        $sub_col_index   Map label → submission value index.
     * @param  array        $sub_values      Student submitted values for this row.
     * @param  int          $n_vars          Number of variable columns.
     * @param  string[]     $exp_columns     All expected column labels.
     * @return bool True when is_root_error, false when propagated.
     */
    private function is_root_error(
        string $col_label,
        bool $submitted_bool,
        array $assignment,
        array $sub_col_index,
        array $sub_values,
        int $n_vars,
        array $exp_columns
    ): bool {
        try {
            $cell_ast = $this->parser->parse($col_label);

            // Build a modified assignment using the student's submitted values
            // for each operand column that exists in the submission.
            // For missing columns fall back to the canonical variable assignment.
            $modified_assignment = $assignment;

            // Collect the student's submitted boolean per column label.
            $sub_bool_by_label = [];
            foreach ($sub_col_index as $label => $idx) {
                $raw = $sub_values[$idx] ?? '';
                $val = $this->parse_cell_value($raw);
                if ($val !== null) {
                    $sub_bool_by_label[$label] = $val;
                }
            }

            // Override variable-assignment with submitted values for any variable
            // columns where the student explicitly submitted a value.
            // (Variables are not graded but may be submitted by the UI.)
            foreach ($assignment as $var => $canonical_val) {
                if (isset($sub_bool_by_label[$var])) {
                    $modified_assignment[$var] = $sub_bool_by_label[$var];
                }
            }

            // Build a composite assignment that also includes submitted
            // subformula values so the evaluator can resolve complex sub-expressions.
            // We achieve this by building a closure-based evaluation: re-evaluate
            // the cell AST, but for each immediate subformula of cell_ast, if the
            // student submitted a value for that subformula column, use that value
            // instead of computing it recursively.
            $recomputed = $this->evaluate_with_student_subformulas(
                $cell_ast,
                $modified_assignment,
                $sub_bool_by_label
            );

            // If the recomputed value (using the student's own submitted operand values)
            // equals what the student submitted, the error propagated from an operand.
            return $recomputed !== $submitted_bool;
        } catch (\Throwable $e) {
            // If we cannot analyse, conservatively classify as root error.
            return true;
        }
    }

    /**
     * Evaluate an AST, using submitted subformula values where available.
     *
     * For each binary / unary node: if the student has submitted a value for
     * the operand's canonical label in their table, use that submitted value
     * (reflecting their choice). Otherwise evaluate recursively from variables.
     *
     * @param  formula_ast $ast
     * @param  array       $assignment         Variable assignment (may be overridden by submitted).
     * @param  array       $sub_bool_by_label  Map canonical-label → bool for submitted columns.
     * @return bool
     */
    private function evaluate_with_student_subformulas(
        formula_ast $ast,
        array $assignment,
        array $sub_bool_by_label
    ): bool {
        // Leaf nodes (var_node, const_node) just evaluate normally.
        $canonical = $ast->canonical();

        // If this exact canonical label was submitted by the student, use it.
        if (isset($sub_bool_by_label[$canonical])) {
            return $sub_bool_by_label[$canonical];
        }

        // Otherwise evaluate from the variable assignment.
        return $this->evaluator->evaluate($ast, $assignment);
    }

    /**
     * Parse a raw cell value string into a nullable boolean.
     *
     * @param  mixed $raw 'V', 'F', '', null, or bool.
     * @return bool|null True for 'V'/true, false for 'F'/false, null for '' or null.
     */
    private function parse_cell_value(mixed $raw): ?bool {
        if ($raw === '' || $raw === null) {
            return null;
        }
        if (is_bool($raw)) {
            return $raw;
        }
        if ($raw === 'V') {
            return true;
        }
        if ($raw === 'F') {
            return false;
        }
        return null;
    }

    /**
     * Build the Spanish explanation string for a cell.
     *
     * @param  bool   $is_empty      Whether the cell was left blank.
     * @param  bool   $is_correct    Whether the answer is correct.
     * @param  bool   $expected_val  The expected boolean value.
     * @param  bool   $is_root_error Whether the error is a root error (vs propagated).
     * @return string
     */
    private function build_explanation(
        bool $is_empty,
        bool $is_correct,
        bool $expected_val,
        bool $is_root_error
    ): string {
        if ($is_correct) {
            return 'Celda correcta.';
        }
        if ($is_empty) {
            return 'Celda vacía.';
        }
        if (!$is_root_error) {
            return 'Error propagado desde una celda anterior.';
        }
        $expected_str = $expected_val ? 'V' : 'F';
        return 'Valor incorrecto. Esperado: ' . $expected_str . '.';
    }
}
