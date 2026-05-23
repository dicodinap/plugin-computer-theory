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
 * Grader for 'equivalence' type problems.
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

/**
 * Grades an 'equivalence' problem where the student states (radio) whether
 * two formulas are logically equivalent and, optionally, fills a combined table.
 *
 * Scoring (spec §5):
 *   score = (radio_weight/100) * base_radio + (table_weight/100) * base_table.
 *
 *   wrong_radio_policy:
 *     'strict' (default): radio wrong → total score = 0.
 *     'proportional': radio wrong → score = (table_weight/100) * base_table.
 *
 *   When require_table_justification is false (or absent), there is no table
 *   grading and score = base_radio (1.0 or 0.0).
 *
 * Combined table structure (spec does not fully specify):
 *   The combined table has columns for all variables of both formulas (union,
 *   sorted A–Z), then the subformula / final column of formula_1, then the
 *   subformula / final column of formula_2, then a synthetic 'equiv?' column
 *   whose expected value is (formula_1_result ↔ formula_2_result).
 *
 * radio_answer encoding:
 *   JSON bool (true = equivalent, false = not equivalent).
 */
final class equivalence_grader {
    /**
     * Build with domain collaborators.
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
     * Grade an equivalence submission.
     *
     * @param  array  $problem        Decoded problem JSON.
     * @param  array  $submission     Decoded submission JSON.
     * @param  float  $max_grade      Maximum possible score.
     * @param  float  $pass_threshold Fraction threshold to mark passed.
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
        $config  = $problem['config'] ?? [];
        $scoring = $problem['scoring'] ?? [];
        $ui      = $problem['ui'] ?? [];

        $formula1 = $config['formula_1'] ?? '';
        $formula2 = $config['formula_2'] ?? '';

        $require_table = (bool)($config['require_table_justification'] ?? false);

        $radio_weight     = (int)($scoring['radio_weight'] ?? 100);
        $table_weight     = (int)($scoring['table_weight'] ?? 0);
        $wrong_radio_policy = $scoring['wrong_radio_policy'] ?? 'strict';

        // Compute expected equivalence.
        if (array_key_exists('expected_equivalent', $config) && $config['expected_equivalent'] !== null) {
            // Professor explicitly set the expected answer (trap / override).
            $expected_equivalent = (bool)$config['expected_equivalent'];
        } else {
            $expected_equivalent = $this->compute_equivalence($formula1, $formula2);
        }

        // Grade the radio answer.
        $submitted_radio = $submission['radio_answer'] ?? null;
        $radio_correct   = ($submitted_radio !== null && (bool)$submitted_radio === $expected_equivalent);
        $base_radio      = $radio_correct ? 1.0 : 0.0;

        // Build radio feedback item (radio items not counted in cells_total).
        $radio_explanation = $radio_correct
            ? 'Respuesta correcta.'
            : ('Respuesta incorrecta. Las fórmulas ' . ($expected_equivalent ? 'sí son' : 'no son') . ' equivalentes.');

        $radio_item = new feedback_item(
            row_index: -1,
            col_label: 'radio',
            cell_kind: 'radio',
            submitted: $submitted_radio,
            expected: $expected_equivalent,
            is_correct: $radio_correct,
            is_root_error: true,
            explanation: $radio_explanation
        );

        $feedback_items = [$radio_item];
        $cells_total    = 0;
        $cells_correct  = 0;
        $base_table     = 0.0;

        if ($require_table) {
            // Grade the combined table using complete_grader-style logic.
            [$table_cells_total, $table_cells_correct, $table_items] = $this->grade_combined_table(
                $formula1,
                $formula2,
                $submission,
                $ui
            );
            $cells_total   = $table_cells_total;
            $cells_correct = $table_cells_correct;
            $base_table    = ($cells_total > 0) ? $cells_correct / $cells_total : 0.0;
            $feedback_items = array_merge($feedback_items, $table_items);
        }

        // Compute total score.
        if (!$require_table) {
            $fraction = $base_radio;
        } else {
            if (!$radio_correct) {
                if ($wrong_radio_policy === 'strict') {
                    $fraction = 0.0;
                } else {
                    // proportional: ignore radio contribution entirely.
                    $fraction = ($table_weight / 100.0) * $base_table;
                }
            } else {
                $fraction = ($radio_weight / 100.0) * $base_radio + ($table_weight / 100.0) * $base_table;
            }
        }

        $score  = round($fraction * $max_grade, 2);
        $passed = $fraction >= $pass_threshold;

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
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Determine whether formula_1 and formula_2 are logically equivalent by
     * comparing their truth values for every assignment over the union of their
     * variable sets.
     *
     * @param  string $formula1 Raw formula string.
     * @param  string $formula2 Raw formula string.
     * @return bool True iff the two formulas are equivalent.
     */
    private function compute_equivalence(string $formula1, string $formula2): bool {
        $ast1 = $this->parser->parse($formula1);
        $ast2 = $this->parser->parse($formula2);

        $vars1 = $ast1->variables();
        $vars2 = $ast2->variables();
        $all_vars = array_values(array_unique(array_merge($vars1, $vars2)));
        sort($all_vars);

        $n         = count($all_vars);
        $row_count = 1 << $n;

        for ($i = 0; $i < $row_count; $i++) {
            $assignment = [];
            for ($j = 0; $j < $n; $j++) {
                $assignment[$all_vars[$j]] = (bool)(($i >> ($n - 1 - $j)) & 1);
            }
            if ($this->evaluator->evaluate($ast1, $assignment) !== $this->evaluator->evaluate($ast2, $assignment)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Grade the combined table submitted as justification for the equivalence claim.
     *
     * Combined table column layout (design decision — not specified by spec):
     *   [union variables sorted A–Z] [formula_1 subformulas] [formula_1 final] [formula_2 subformulas] [formula_2 final] [equiv?]
     *
     * The 'equiv?' column expected value = formula_1_result XOR formula_2_result inverted
     * (i.e. true when they agree for that row).
     *
     * @param  string $formula1   Formula 1 raw string.
     * @param  string $formula2   Formula 2 raw string.
     * @param  array  $submission Decoded submission array.
     * @param  array  $ui         UI config from problem.
     * @return array{int, int, feedback_item[]}  [cells_total, cells_correct, feedback_items].
     */
    private function grade_combined_table(
        string $formula1,
        string $formula2,
        array $submission,
        array $ui
    ): array {
        $intermediate       = $ui['intermediate_subformulas'] ?? 'auto';
        $manual_subformulas = $ui['manual_subformulas'] ?? [];

        $ast1 = $this->parser->parse($formula1);
        $ast2 = $this->parser->parse($formula2);

        $table1 = $this->builder->build($ast1, [
            'intermediate'       => $intermediate,
            'manual_subformulas' => $manual_subformulas,
        ]);
        $table2 = $this->builder->build($ast2, [
            'intermediate'       => $intermediate,
            'manual_subformulas' => $manual_subformulas,
        ]);

        $vars1 = $table1['variables'];
        $vars2 = $table2['variables'];
        $all_vars = array_values(array_unique(array_merge($vars1, $vars2)));
        sort($all_vars);
        $n_vars = count($all_vars);

        // Submission table.
        $sub_columns = $submission['table']['columns'] ?? [];
        $sub_rows    = $submission['table']['rows'] ?? [];

        // Detect whether sub_columns includes variable headers (builder convention)
        // or only gradeable headers (UI/qtype convention). Mirror complete_grader.
        $first_row_values  = $sub_rows[0]['values'] ?? [];
        $cols_include_vars = (count($sub_columns) > count($first_row_values));
        $sub_offset        = $cols_include_vars ? $n_vars : 0;

        $sub_col_index = [];
        foreach ($sub_columns as $idx => $label) {
            $value_idx = $idx - $sub_offset;
            if ($value_idx >= 0) {
                $sub_col_index[$label] = $value_idx;
            }
        }

        $n_rows = max(count($table1['rows']), count($table2['rows']));

        // Build expected column sequence: cols1 (non-var) + cols2 (non-var) + 'equiv?'.
        $cols1_non_var = array_slice($table1['columns'], count($vars1));
        $cols2_non_var = array_slice($table2['columns'], count($vars2));
        // Builder emits the literal 'final' label for the root column of each formula.
        // In the combined equivalence table both finals would collide on the same key,
        // so disambiguate the last gradeable column of each side to 'final₁' / 'final₂'.
        if (!empty($cols1_non_var)) {
            $cols1_non_var[count($cols1_non_var) - 1] = 'final₁';
        }
        if (!empty($cols2_non_var)) {
            $cols2_non_var[count($cols2_non_var) - 1] = 'final₂';
        }
        // 'equiv?' is a synthetic label for the final combined column.
        $gradeable_cols = array_merge($cols1_non_var, $cols2_non_var, ['equiv?']);

        $feedback_items = [];
        $cells_total    = 0;
        $cells_correct  = 0;

        for ($row_idx = 0; $row_idx < $n_rows; $row_idx++) {
            // Build canonical variable assignment for this row.
            $assignment = [];
            for ($j = 0; $j < $n_vars; $j++) {
                $assignment[$all_vars[$j]] = (bool)(($row_idx >> ($n_vars - 1 - $j)) & 1);
            }

            $exp_row1 = $table1['rows'][$row_idx] ?? null;
            $exp_row2 = $table2['rows'][$row_idx] ?? null;

            $sub_row    = $sub_rows[$row_idx] ?? null;
            $sub_values = $sub_row['values'] ?? [];

            // Collect expected values per column label.
            // Remap the trailing 'final' of each formula to its disambiguated label
            // so the lookup keys match $gradeable_cols.
            $expected_by_label = [];
            if ($exp_row1) {
                $cols1     = $table1['columns'];
                $last_ci_1 = count($cols1) - 1;
                foreach ($cols1 as $ci => $label) {
                    if ($ci >= count($vars1)) {
                        $key = ($ci === $last_ci_1) ? 'final₁' : $label;
                        $expected_by_label[$key] = $exp_row1['values'][$ci];
                    }
                }
            }
            if ($exp_row2) {
                $cols2     = $table2['columns'];
                $last_ci_2 = count($cols2) - 1;
                foreach ($cols2 as $ci => $label) {
                    if ($ci >= count($vars2)) {
                        $key = ($ci === $last_ci_2) ? 'final₂' : $label;
                        $expected_by_label[$key] = $exp_row2['values'][$ci];
                    }
                }
            }

            // Expected 'equiv?' = both finals agree.
            $final1 = $exp_row1 ? (bool)end($exp_row1['values']) : false;
            $final2 = $exp_row2 ? (bool)end($exp_row2['values']) : false;
            $expected_by_label['equiv?'] = ($final1 === $final2);

            // Grade each gradeable column.
            foreach ($gradeable_cols as $col_label) {
                if (!array_key_exists($col_label, $expected_by_label)) {
                    continue;
                }
                $expected_val  = $expected_by_label[$col_label];
                $cell_kind     = ($col_label === 'equiv?') ? 'final' : 'subformula';
                $submitted_raw = null;
                if (isset($sub_col_index[$col_label])) {
                    $idx           = $sub_col_index[$col_label];
                    $submitted_raw = $sub_values[$idx] ?? '';
                }

                $submitted_bool = $this->parse_cell_value($submitted_raw);
                $is_empty       = ($submitted_bool === null);
                $is_correct     = (!$is_empty && $submitted_bool === $expected_val);
                $is_root_error  = true;

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

        return [$cells_total, $cells_correct, $feedback_items];
    }

    /**
     * Parse a raw cell value string into a nullable boolean.
     *
     * @param  mixed $raw 'V', 'F', '', null, or bool.
     * @return bool|null
     */
    private function parse_cell_value(mixed $raw): ?bool {
        if ($raw === '' || $raw === null) {
            return null;
        }
        if (is_bool($raw)) {
            return $raw;
        }
        return match ($raw) {
            'V'     => true,
            'F'     => false,
            default => null,
        };
    }

    /**
     * Build a Spanish explanation string for a table cell.
     *
     * @param  bool $is_empty
     * @param  bool $is_correct
     * @param  bool $expected_val
     * @param  bool $is_root_error
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
