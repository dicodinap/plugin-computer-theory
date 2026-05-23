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
 * Grader for 'classify' type problems.
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
 * Grades a 'classify' problem where the student classifies a formula as
 * tautology, contradiction, or contingency and (optionally) fills the table.
 *
 * Expected class values (design decision — spec does not fix strings):
 *   'tautology'    — all rows evaluate to true.
 *   'contradiction' — all rows evaluate to false.
 *   'contingency'  — at least one true and one false row.
 *
 * These strings are used in the submission radio_answer field and in
 * problem config.expected_class (when the professor overrides auto-detection).
 *
 * Scoring mirrors equivalence_grader:
 *   score = (radio_weight/100) * base_radio + (table_weight/100) * base_table.
 *   wrong_radio_policy: 'strict' → 0 when radio wrong; 'proportional' → table fraction only.
 */
final class classify_grader {
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
     * Grade a classify submission.
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

        $formula       = $config['formula'] ?? '';
        $require_table = (bool)($config['require_table_justification'] ?? false);

        $radio_weight       = (int)($scoring['radio_weight'] ?? 100);
        $table_weight       = (int)($scoring['table_weight'] ?? 0);
        $wrong_radio_policy = $scoring['wrong_radio_policy'] ?? 'strict';

        // Compute expected class.
        if (array_key_exists('expected_class', $config) && $config['expected_class'] !== null) {
            $expected_class = (string)$config['expected_class'];
        } else {
            $expected_class = $this->compute_class($formula);
        }

        // Grade the radio answer.
        $submitted_radio = $submission['radio_answer'] ?? null;
        $radio_correct   = ($submitted_radio !== null && (string)$submitted_radio === $expected_class);
        $base_radio      = $radio_correct ? 1.0 : 0.0;

        // Build radio feedback item.
        $radio_explanation = $radio_correct
            ? 'Clasificación correcta.'
            : 'Clasificación incorrecta. La respuesta esperada es "' . $expected_class . '".';

        $radio_item = new feedback_item(
            row_index: -1,
            col_label: 'radio',
            cell_kind: 'radio',
            submitted: $submitted_radio,
            expected: $expected_class,
            is_correct: $radio_correct,
            is_root_error: true,
            explanation: $radio_explanation
        );

        $feedback_items = [$radio_item];
        $cells_total    = 0;
        $cells_correct  = 0;
        $base_table     = 0.0;

        if ($require_table) {
            $intermediate       = $ui['intermediate_subformulas'] ?? 'auto';
            $manual_subformulas = $ui['manual_subformulas'] ?? [];

            [$table_cells_total, $table_cells_correct, $table_items] = $this->grade_table(
                $formula,
                $intermediate,
                $manual_subformulas,
                $submission
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
     * Compute the class of a formula: 'tautology', 'contradiction', or 'contingency'.
     *
     * @param  string $formula Raw formula string.
     * @return string
     */
    private function compute_class(string $formula): string {
        $ast  = $this->parser->parse($formula);
        $vars = $ast->variables();
        sort($vars);
        $n         = count($vars);
        $row_count = 1 << $n;

        $has_true  = false;
        $has_false = false;

        for ($i = 0; $i < $row_count; $i++) {
            $assignment = [];
            for ($j = 0; $j < $n; $j++) {
                $assignment[$vars[$j]] = (bool)(($i >> ($n - 1 - $j)) & 1);
            }
            $result = $this->evaluator->evaluate($ast, $assignment);
            if ($result) {
                $has_true = true;
            } else {
                $has_false = true;
            }
            if ($has_true && $has_false) {
                break; // Short-circuit: already know it's contingency.
            }
        }

        if ($has_true && !$has_false) {
            return 'tautology';
        }
        if (!$has_true && $has_false) {
            return 'contradiction';
        }
        return 'contingency';
    }

    /**
     * Grade the truth table submitted as justification for classification.
     *
     * Uses the same per-cell grading logic as complete_grader, without
     * propagated-error analysis (classify table is simpler).
     *
     * @param  string   $formula             Raw formula string.
     * @param  string   $intermediate        'auto' | 'none' | 'manual'.
     * @param  string[] $manual_subformulas  Manual subformulas from UI config.
     * @param  array    $submission          Decoded submission array.
     * @return array{int, int, feedback_item[]}  [cells_total, cells_correct, feedback_items].
     */
    private function grade_table(
        string $formula,
        string $intermediate,
        array $manual_subformulas,
        array $submission
    ): array {
        $ast      = $this->parser->parse($formula);
        $expected = $this->builder->build($ast, [
            'intermediate'       => $intermediate,
            'manual_subformulas' => $manual_subformulas,
        ]);

        $variables   = $expected['variables'];
        $exp_columns = $expected['columns'];
        $exp_rows    = $expected['rows'];
        $n_vars      = count($variables);
        $n_columns   = count($exp_columns);

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

        $feedback_items = [];
        $cells_total    = 0;
        $cells_correct  = 0;

        foreach ($exp_rows as $row_idx => $exp_row) {
            $exp_values = $exp_row['values'];

            $sub_row    = $sub_rows[$row_idx] ?? null;
            $sub_values = $sub_row['values'] ?? [];

            for ($col_idx = $n_vars; $col_idx < $n_columns; $col_idx++) {
                $col_label    = $exp_columns[$col_idx];
                $expected_val = $exp_values[$col_idx];
                $cell_kind    = ($col_idx === $n_columns - 1) ? 'final' : 'subformula';

                $lookup_label  = ($cell_kind === 'final') ? 'final' : $col_label;
                $submitted_raw = null;
                if (isset($sub_col_index[$lookup_label])) {
                    $idx           = $sub_col_index[$lookup_label];
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
     * @param  mixed $raw
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
     * Build a Spanish explanation for a table cell.
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
