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
 * Grid skeleton — the column/row layout a student fills for a truth_table problem.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

/**
 * Computes the truth-table grid layout (variables, column headers, and the expected
 * rows) for a problem payload, in the exact column order the graders expect:
 *
 *  - complete / classify: the single formula's table (variables, subformulas, final).
 *  - equivalence: union variables + formula 1 non-var columns (last renamed 'final₁')
 *    + formula 2 non-var columns ('final₂') + an 'equiv?' column — matching
 *    equivalence_grader::grade_combined_table.
 *
 * Shared by the mod activity editor and the qtype question renderer so both present
 * (and therefore grade) an identical grid. Pure: no DB, deterministic, unit-testable.
 */
final class grid_skeleton {
    /**
     * Build the grid skeleton for a decoded problem payload.
     *
     * @param  array $payload Decoded canonical problem payload.
     * @return array{variables: string[], columns: string[], rows: array<int, array{vars: array<string, bool>, values: bool[]}>, canonical: string}
     *               On a parse failure, variables/columns/rows are empty and canonical
     *               falls back to the raw formula(s).
     */
    public static function build(array $payload): array {
        $type    = $payload['type']   ?? 'complete';
        $config  = $payload['config'] ?? [];
        $ui      = $payload['ui']     ?? ['intermediate_subformulas' => 'auto'];

        $parser  = new parser();
        $builder = new truth_table_builder(new evaluator());
        $opts    = [
            'intermediate'       => $ui['intermediate_subformulas'] ?? 'auto',
            'manual_subformulas' => $ui['manual_subformulas']       ?? [],
        ];

        if ($type === 'equivalence') {
            return self::build_equivalence($parser, $builder, $opts, $config);
        }

        $formula = (string) ($config['formula'] ?? ($config['formula_1'] ?? ''));
        try {
            $ast      = $parser->parse($formula);
            $table    = $builder->build($ast, $opts);
            return [
                'variables' => $table['variables'],
                'columns'   => $table['columns'],
                'rows'      => $table['rows'],
                'canonical' => $ast->canonical(),
            ];
        } catch (\Throwable $e) {
            return ['variables' => [], 'columns' => [], 'rows' => [], 'canonical' => $formula];
        }
    }

    /**
     * Build the combined equivalence grid for two formulas.
     *
     * @param  parser              $parser
     * @param  truth_table_builder $builder
     * @param  array               $opts
     * @param  array               $config
     * @return array{variables: string[], columns: string[], rows: array, canonical: string}
     */
    private static function build_equivalence(
        parser $parser,
        truth_table_builder $builder,
        array $opts,
        array $config
    ): array {
        $f1 = (string) ($config['formula_1'] ?? '');
        $f2 = (string) ($config['formula_2'] ?? '');
        try {
            $ast1     = $parser->parse($f1);
            $ast2     = $parser->parse($f2);
            $table1   = $builder->build($ast1, $opts);
            $table2   = $builder->build($ast2, $opts);
            $vars1    = $table1['variables'];
            $vars2    = $table2['variables'];
            $all_vars = array_values(array_unique(array_merge($vars1, $vars2)));
            sort($all_vars);

            $cols1_non_var = array_slice($table1['columns'], count($vars1));
            $cols2_non_var = array_slice($table2['columns'], count($vars2));
            // Disambiguate the duplicate 'final' header — must match the rename
            // performed by equivalence_grader::grade_combined_table.
            if (!empty($cols1_non_var)) {
                $cols1_non_var[count($cols1_non_var) - 1] = 'final₁';
            }
            if (!empty($cols2_non_var)) {
                $cols2_non_var[count($cols2_non_var) - 1] = 'final₂';
            }
            $cols = array_merge($all_vars, $cols1_non_var, $cols2_non_var, ['equiv?']);

            $n_vars   = count($all_vars);
            $n_rows   = $n_vars > 0 ? (1 << $n_vars) : max(count($table1['rows']), count($table2['rows']));
            $exp_rows = [];
            for ($r = 0; $r < $n_rows; $r++) {
                $assignment = [];
                for ($j = 0; $j < $n_vars; $j++) {
                    $assignment[$all_vars[$j]] = (bool) (($r >> ($n_vars - 1 - $j)) & 1);
                }
                $row1 = $table1['rows'][$r] ?? null;
                $row2 = $table2['rows'][$r] ?? null;
                $final1 = $row1 ? (bool) end($row1['values']) : false;
                $final2 = $row2 ? (bool) end($row2['values']) : false;

                $values = [];
                foreach ($all_vars as $v) {
                    $values[] = $assignment[$v];
                }
                if ($row1) {
                    foreach (array_slice($row1['values'], count($vars1)) as $bv) {
                        $values[] = (bool) $bv;
                    }
                } else {
                    for ($k = 0, $kmax = count($cols1_non_var); $k < $kmax; $k++) {
                        $values[] = false;
                    }
                }
                if ($row2) {
                    foreach (array_slice($row2['values'], count($vars2)) as $bv) {
                        $values[] = (bool) $bv;
                    }
                } else {
                    for ($k = 0, $kmax = count($cols2_non_var); $k < $kmax; $k++) {
                        $values[] = false;
                    }
                }
                $values[] = ($final1 === $final2);

                $exp_rows[] = ['vars' => $assignment, 'values' => $values];
            }
            $canonical = $ast1->canonical() . '   vs.   ' . $ast2->canonical();

            return [
                'variables' => $all_vars,
                'columns'   => $cols,
                'rows'      => $exp_rows,
                'canonical' => $canonical,
            ];
        } catch (\Throwable $e) {
            return ['variables' => [], 'columns' => [], 'rows' => [], 'canonical' => $f1 . ' / ' . $f2];
        }
    }
}
