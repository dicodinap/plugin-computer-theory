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

namespace mod_graphitoubb\output;

/**
 * Renderer for mod_graphitoubb.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the student editor shell (Cytoscape canvas placeholder until S11).
     *
     * @param int $attemptid
     * @param int $instanceid
     * @param int $schemaversion
     * @return string HTML.
     */
    public function render_editor(int $attemptid, int $instanceid, int $schemaversion): string {
        $context = [
            'attemptid'        => $attemptid,
            'instanceid'       => $instanceid,
            'schemaversion'    => $schemaversion,
            'max_states'       => \local_graphitoubb\tools\afd\domain\validator::MAX_STATES,
            'max_alphabet'     => \local_graphitoubb\tools\afd\domain\validator::MAX_ALPHABET,
            'max_transitions'  => \local_graphitoubb\tools\afd\domain\validator::MAX_TRANSITIONS,
            'max_label_length' => \local_graphitoubb\tools\afd\domain\validator::MAX_LABEL_LENGTH,
            'max_input_length' => \local_graphitoubb\tools\afd\domain\validator::MAX_INPUT_LENGTH,
        ];
        return $this->render_from_template('mod_graphitoubb/editor', $context);
    }

    /**
     * Render a read-only teacher summary of an attempt.
     *
     * @param \stdClass $attempt Attempt row.
     * @return string HTML.
     */
    public function render_attempt_summary(\stdClass $attempt): string {
        return $this->render_from_template('mod_graphitoubb/attempt_summary', (array) $attempt);
    }

    /**
     * Render the teacher-facing attempt list table for an instance.
     *
     * @param \stdClass[] $attempts Rows from report_repository::list_attempts_for_instance.
     * @param \context    $context  Module context (used for fullname capability check).
     * @return string HTML.
     */
    public function render_attempt_list(array $attempts, \context $context): string {
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $context);
        $rows = [];
        foreach ($attempts as $attempt) {
            $row                     = (array) $attempt;
            $row['fullname']         = fullname($attempt, $canviewfullnames);
            $row['timestarted_fmt']  = userdate((int) $attempt->timestarted);
            $row['timefinished_fmt'] = $attempt->timefinished ? userdate((int) $attempt->timefinished) : '';
            $rows[]                  = $row;
        }
        return $this->render_from_template('mod_graphitoubb/attempt_list', [
            'attempts'     => $rows,
            'has_attempts' => !empty($rows),
        ]);
    }

    /**
     * Render capability-aware navigation links for view.php.
     *
     * @param int  $cmid          Course-module id.
     * @param bool $canviewreport User holds mod/graphitoubb:viewreport.
     * @param bool $canattempt    User holds mod/graphitoubb:attempt.
     * @return string HTML — empty when neither capability applies.
     */
    public function render_view_links(int $cmid, bool $canviewreport, bool $canattempt): string {
        if (!$canviewreport) {
            return '';
        }
        $url = new \moodle_url('/mod/graphitoubb/report.php', ['id' => $cmid]);
        return $this->single_button($url, get_string('view_report', 'mod_graphitoubb'), 'get');
    }

    /**
     * Render the teacher panel (4-tab dashboard).
     *
     * @param teacher_panel_renderable $r
     * @return string HTML.
     */
    public function render_teacher_panel(teacher_panel_renderable $r): string {
        return $this->render_from_template('mod_graphitoubb/teacher_panel', $r->export_for_template($this));
    }

    /**
     * Render the truth_table editor for a given attempt + problem.
     *
     * Builds the expected truth table from the problem payload, renders a blank
     * skeleton (or the prior submission values), and emits the AMD init call.
     *
     * @param int       $attemptid   Attempt row id.
     * @param int       $instanceid  Instance row id.
     * @param \stdClass $problem     graphitoubb_problem row.
     * @param ?array    $submission  Decoded prior submission payload, or null.
     * @param ?array    $grading     Decoded prior grading_result, or null.
     * @return string HTML.
     */
    public function render_truth_table_editor(
        int $attemptid,
        int $instanceid,
        \stdClass $problem,
        ?array $submission = null,
        ?array $grading = null
    ): string {
        $payload = json_decode($problem->payload, true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $type    = $payload['type']           ?? 'complete';
        $config  = $payload['config']         ?? [];
        $ui      = $payload['ui']             ?? ['intermediate_subformulas' => 'auto'];
        $formula = $config['formula']         ?? ($config['formula_1'] ?? '');

        // Build expected table (using local domain layer).
        $parser  = new \local_graphitoubb\tools\truth_table\domain\parser();
        $builder = new \local_graphitoubb\tools\truth_table\domain\truth_table_builder(
            new \local_graphitoubb\tools\truth_table\domain\evaluator()
        );

        // Equivalence: build BOTH formulas and merge into a single combined table
        // matching equivalence_grader::grade_combined_table column layout —
        // union vars + cols1_non_var + cols2_non_var + 'equiv?'.
        if ($type === 'equivalence') {
            $f1 = (string) ($config['formula_1'] ?? '');
            $f2 = (string) ($config['formula_2'] ?? '');
            try {
                $ast1     = $parser->parse($f1);
                $ast2     = $parser->parse($f2);
                $opts     = [
                    'intermediate'       => $ui['intermediate_subformulas'] ?? 'auto',
                    'manual_subformulas' => $ui['manual_subformulas']       ?? [],
                ];
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
                $vars = $all_vars;

                // Compose rows by canonical assignment over union variables —
                // mirrors equivalence_grader row indexing.
                $n_vars   = count($all_vars);
                $n_rows   = $n_vars > 0 ? (1 << $n_vars) : max(count($table1['rows']), count($table2['rows']));
                $exp_rows = [];
                for ($r = 0; $r < $n_rows; $r++) {
                    $assignment = [];
                    for ($j = 0; $j < $n_vars; $j++) {
                        $assignment[$all_vars[$j]] = (bool)(($r >> ($n_vars - 1 - $j)) & 1);
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
                        $sub_vals1 = array_slice($row1['values'], count($vars1));
                        foreach ($sub_vals1 as $bv) {
                            $values[] = (bool) $bv;
                        }
                    } else {
                        for ($k = 0, $kmax = count($cols1_non_var); $k < $kmax; $k++) {
                            $values[] = false;
                        }
                    }
                    if ($row2) {
                        $sub_vals2 = array_slice($row2['values'], count($vars2));
                        foreach ($sub_vals2 as $bv) {
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
            } catch (\Throwable $e) {
                $vars      = [];
                $cols      = [];
                $exp_rows  = [];
                $canonical = $f1 . ' / ' . $f2;
            }
        } else {
            try {
                $ast      = $parser->parse((string) $formula);
                $table    = $builder->build($ast, [
                    'intermediate'       => $ui['intermediate_subformulas'] ?? 'auto',
                    'manual_subformulas' => $ui['manual_subformulas']       ?? [],
                ]);
                $vars     = $table['variables'];
                $cols     = $table['columns'];
                $exp_rows = $table['rows'];
                $canonical = $ast->canonical();
            } catch (\Throwable $e) {
                $vars      = [];
                $cols      = [];
                $exp_rows  = [];
                $canonical = $formula;
            }
        }

        $nvars = count($vars);

        // Build mustache columns (header labels for ALL columns, including variables).
        $mcolumns = [];
        foreach ($cols as $label) {
            $mcolumns[] = ['label' => (string) $label];
        }

        // Build skeleton rows. Each row exposes the variable cells (pre-filled, read-only)
        // plus one input cell per gradeable column (subformulas + final).
        $sub_rows_by_idx = [];
        if ($submission && isset($submission['table']['rows'])) {
            foreach ($submission['table']['rows'] as $i => $r) {
                $sub_rows_by_idx[$i] = $r['values'] ?? [];
            }
        }

        $mrows = [];
        foreach ($exp_rows as $i => $erow) {
            // Variable cells render as static V/F text.
            $vcells = [];
            foreach ($vars as $v) {
                $vcells[] = ['val' => $erow['vars'][$v] ? 'V' : 'F'];
            }
            // Input cells = one per gradeable column.
            $icells = [];
            $sub_values = $sub_rows_by_idx[$i] ?? [];
            for ($ci = $nvars; $ci < count($cols); $ci++) {
                $vsel = false;
                $fsel = false;
                $sub_value_idx = $ci - $nvars;
                if (isset($sub_values[$sub_value_idx])) {
                    $vsel = ($sub_values[$sub_value_idx] === 'V');
                    $fsel = ($sub_values[$sub_value_idx] === 'F');
                }
                $icells[] = [
                    'col_index'  => $ci,
                    'value'      => '',
                    'v_selected' => $vsel,
                    'f_selected' => $fsel,
                ];
            }
            $mrows[] = [
                'index' => $i,
                'vars'  => $vcells,
                'cells' => $icells,
            ];
        }

        // Variables list for the badge bar.
        $mvars = [];
        foreach ($vars as $v) {
            $mvars[] = ['label' => $v];
        }

        // Radio section for equivalence / classify.
        $show_radio    = ($type !== 'complete');
        $radio_options = [];
        $radio_legend  = '';
        if ($type === 'equivalence') {
            $radio_legend  = '¿Son lógicamente equivalentes?';
            $radio_options = [
                ['value' => 'true',  'label' => 'Sí'],
                ['value' => 'false', 'label' => 'No'],
            ];
        } else if ($type === 'classify') {
            $radio_legend  = 'Clasificación de la fórmula';
            $radio_options = [
                ['value' => 'tautology',     'label' => 'Tautología'],
                ['value' => 'contradiction', 'label' => 'Contradicción'],
                ['value' => 'contingency',   'label' => 'Contingencia'],
            ];
        }

        $readonly = ($submission !== null);

        // Build feedback items (if grading available).
        $feedback = [];
        if ($grading && !empty($grading['feedback_items'])) {
            foreach ($grading['feedback_items'] as $fi) {
                $feedback[] = [
                    'row_index'     => $fi['row_index'] ?? '',
                    'col_label'     => $fi['col_label'] ?? '',
                    'cell_kind'     => $fi['cell_kind'] ?? '',
                    'submitted'     => is_bool($fi['submitted'] ?? null)
                        ? ($fi['submitted'] ? 'V' : 'F')
                        : (string) ($fi['submitted'] ?? ''),
                    'expected'      => is_bool($fi['expected'] ?? null)
                        ? ($fi['expected'] ? 'V' : 'F')
                        : (string) ($fi['expected'] ?? ''),
                    'is_correct'    => !empty($fi['is_correct']),
                    'is_root_error' => !empty($fi['is_root_error']),
                    'explanation'   => $fi['explanation'] ?? '',
                ];
            }
        }

        $context = [
            'attemptid'        => $attemptid,
            'instanceid'       => $instanceid,
            'problem_type'     => $type,
            'problem_payload'  => htmlspecialchars(
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                ENT_QUOTES,
                'UTF-8'
            ),
            'variables'        => $mvars,
            'formula'          => $canonical,
            'columns'          => $mcolumns,
            'rows'             => $mrows,
            'show_radio'       => $show_radio,
            'radio_legend'     => $radio_legend,
            'radio_options'    => $radio_options,
            'readonly'         => $readonly,
            'submitted'        => $readonly,
            'feedback'         => $feedback,
            'grading_score'    => $grading['score']    ?? null,
            'grading_fraction' => $grading['fraction'] ?? null,
        ];

        $this->page->requires->js_call_amd('mod_graphitoubb/truth_table_editor', 'init', [
            '[data-region="truth-table-editor"]',
        ]);

        return $this->render_from_template('mod_graphitoubb/truth_table_editor', $context);
    }
}
