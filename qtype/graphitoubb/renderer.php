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
 * Renderer for qtype_graphitoubb.
 *
 * Loaded by Moodle's renderer system via the filename convention (non-autoloaded).
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for GraphitoUBB truth_table questions inside the Moodle question engine.
 *
 * Renders the truth table editor form and feedback regions.
 * Reuses the AMD module mod_graphitoubb/truth_table_editor for interactive behaviour.
 */
class qtype_graphitoubb_renderer extends qtype_renderer {
    /**
     * Render the question body: formula display and the truth table editor.
     *
     * Outputs:
     * - A question wrapper div with a unique id for the AMD module.
     * - A hidden input named 'answer_payload' pre-filled with any saved response.
     * - An AMD module init call to mod_graphitoubb/truth_table_editor.
     *
     * @param  question_attempt         $qa      The question attempt.
     * @param  question_display_options $options Display options (read-only, marks visible, etc.).
     * @return string HTML fragment.
     */
    public function formulation_and_controls(
        question_attempt $qa,
        question_display_options $options
    ): string {
        global $PAGE;

        /** @var qtype_graphitoubb_question $question */
        $question = $qa->get_question();

        $wrapper_id  = 'qtype_graphitoubb_' . $qa->get_database_id();
        $input_name  = $qa->get_qt_field_name('answer_payload');
        $current_val = $qa->get_last_qt_var('answer_payload', '');

        $readonly = $options->readonly ? ' readonly' : '';

        // Build problem context for the editor JS.
        $problem_json = json_encode($question->problem_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $exercise_type = htmlspecialchars($question->exercise_type, ENT_QUOTES);

        $html = html_writer::start_div('qtype_graphitoubb_wrapper', ['id' => $wrapper_id]);

        // Exercise type badge.
        $html .= html_writer::tag(
            'p',
            html_writer::tag(
                'span',
                get_string('exercise_type_' . $question->exercise_type, 'qtype_graphitoubb'),
                ['class' => 'badge badge-info qtype_graphitoubb_type_badge']
            )
        );

        // Formula display block.
        if (!empty($question->problem_payload)) {
            $config = $question->problem_payload['config'] ?? [];
            switch ($question->exercise_type) {
                case 'equivalence':
                    $f1 = htmlspecialchars($this->canonical_formula($config['formula_1'] ?? ''), ENT_QUOTES);
                    $f2 = htmlspecialchars($this->canonical_formula($config['formula_2'] ?? ''), ENT_QUOTES);
                    $html .= html_writer::tag(
                        'p',
                        html_writer::tag('strong', 'F1: ') . $f1 . ' &nbsp; ' .
                        html_writer::tag('strong', 'F2: ') . $f2,
                        ['class' => 'qtype_graphitoubb_formulas']
                    );
                    break;
                default:
                    $formula = htmlspecialchars($this->canonical_formula($config['formula'] ?? ''), ENT_QUOTES);
                    $html .= html_writer::tag(
                        'p',
                        html_writer::tag('strong', get_string('formula', 'qtype_graphitoubb') . ': ') . $formula,
                        ['class' => 'qtype_graphitoubb_formula']
                    );
                    break;
            }
        }

        // Interactive grid: rendered server-side from the shared skeleton so the
        // student fills a fixed-formula table. The same CSS hooks the mod editor uses
        // let a small inline serializer build the answer payload on change.
        $html .= html_writer::div(
            get_string('fill_table_instruction', 'qtype_graphitoubb'),
            'qtype_graphitoubb_instruction text-muted small mb-2'
        );
        $html .= $this->render_grid($question, $qa, $options);

        // Hidden input carrying the current JSON answer (submitted by the quiz form).
        $input_attrs = [
            'type'  => 'hidden',
            'name'  => $input_name,
            'id'    => $input_name,
            'value' => htmlspecialchars($current_val, ENT_QUOTES),
        ];
        if ($options->readonly) {
            $input_attrs['disabled'] = 'disabled';
        }
        $html .= html_writer::empty_tag('input', $input_attrs);

        $html .= html_writer::end_div();

        // Wire the grid to the hidden input. No AMD build needed; this replicates the
        // mod editor's buildPayload() exactly so the shared grader receives an
        // identical submission shape.
        if (!$options->readonly) {
            $rootjs  = json_encode($wrapper_id);
            $inputjs = json_encode($input_name);
            $typejs  = json_encode($question->exercise_type);
            $PAGE->requires->js_amd_inline(<<<JS
require([], function() {
    var root = document.getElementById($rootjs);
    var input = document.getElementById($inputjs);
    if (!root || !input) { return; }
    var problemType = $typejs;
    var build = function() {
        var table = {columns: [], rows: []};
        root.querySelectorAll('.mod-graphitoubb-tte__col-header').forEach(function(th) {
            table.columns.push(th.textContent.trim());
        });
        root.querySelectorAll('[data-row-index]').forEach(function(tr) {
            var vars = {};
            tr.querySelectorAll('.mod-graphitoubb-tte__cell--var').forEach(function(td, i) {
                var letter = table.columns[i] || String.fromCharCode(65 + i);
                vars[letter] = td.textContent.trim();
            });
            var values = [];
            tr.querySelectorAll('.mod-graphitoubb-tte__cell-select').forEach(function(sel) {
                values.push(sel.value || '');
            });
            table.rows.push({vars: vars, values: values});
        });
        var radioAnswer = null;
        var r = root.querySelector('.mod-graphitoubb-tte__radio:checked');
        if (r) {
            radioAnswer = (r.value === 'true' || r.value === 'false') ? (r.value === 'true') : r.value;
        }
        input.value = JSON.stringify({
            tool: 'truth_table',
            schema_version: 1,
            type: problemType,
            table: table,
            radio_answer: radioAnswer
        });
    };
    root.addEventListener('change', function(e) {
        if (e.target.matches('.mod-graphitoubb-tte__cell-select, .mod-graphitoubb-tte__radio')) {
            build();
        }
    });
});
JS);
        }

        return $html;
    }

    /**
     * Render the interactive (or read-only) truth-table grid plus the radio answer.
     *
     * Uses the shared grid_skeleton so the column/row layout matches the grader, and
     * pre-fills cells/radio from any saved response for review.
     *
     * @param  qtype_graphitoubb_question $question
     * @param  question_attempt           $qa
     * @param  question_display_options   $options
     * @return string
     */
    private function render_grid(
        qtype_graphitoubb_question $question,
        question_attempt $qa,
        question_display_options $options
    ): string {
        $skeleton = \local_graphitoubb\tools\truth_table\domain\grid_skeleton::build($question->problem_payload);
        $vars  = $skeleton['variables'];
        $cols  = $skeleton['columns'];
        $rows  = $skeleton['rows'];
        $nvars = count($vars);

        if (empty($cols) || empty($rows)) {
            return html_writer::div(
                get_string('err_internal', 'qtype_graphitoubb'),
                'alert alert-warning'
            );
        }

        // Pre-fill from a saved response (review / regrade / redisplay).
        $saved       = json_decode((string) $qa->get_last_qt_var('answer_payload', ''), true);
        $saved_rows  = (is_array($saved) && isset($saved['table']['rows'])) ? $saved['table']['rows'] : [];
        $saved_radio = is_array($saved) ? ($saved['radio_answer'] ?? null) : null;
        $disabled    = $options->readonly;

        $out  = html_writer::start_div('mod-graphitoubb-tte__table-wrapper table-responsive');
        $out .= html_writer::start_tag('table', [
            'class' => 'table table-bordered mod-graphitoubb-tte__table',
            'style' => 'width:auto',
        ]);

        $out .= '<thead><tr>';
        foreach ($cols as $label) {
            $out .= html_writer::tag('th', s((string) $label), [
                'scope' => 'col',
                'class' => 'mod-graphitoubb-tte__col-header text-center',
            ]);
        }
        $out .= '</tr></thead><tbody>';

        foreach ($rows as $i => $erow) {
            $out .= '<tr data-row-index="' . (int) $i . '">';
            foreach ($vars as $v) {
                $out .= html_writer::tag(
                    'td',
                    !empty($erow['vars'][$v]) ? 'V' : 'F',
                    ['class' => 'mod-graphitoubb-tte__cell mod-graphitoubb-tte__cell--var text-center']
                );
            }
            $saved_values = $saved_rows[$i]['values'] ?? [];
            for ($ci = $nvars; $ci < count($cols); $ci++) {
                $cur  = $saved_values[$ci - $nvars] ?? '';
                $opts_html = '<option value=""></option>'
                    . '<option value="V"' . ($cur === 'V' ? ' selected' : '') . '>V</option>'
                    . '<option value="F"' . ($cur === 'F' ? ' selected' : '') . '>F</option>';
                $selattrs = [
                    'class'      => 'form-control form-control-sm mod-graphitoubb-tte__cell-select',
                    'data-row'   => (int) $i,
                    'data-col'   => $ci,
                    'aria-label' => get_string('cell_aria_label', 'qtype_graphitoubb',
                        (object) ['row' => $i + 1, 'col' => (string) $cols[$ci]]),
                ];
                if ($disabled) {
                    $selattrs['disabled'] = 'disabled';
                }
                $out .= html_writer::tag(
                    'td',
                    html_writer::tag('select', $opts_html, $selattrs),
                    ['class' => 'mod-graphitoubb-tte__cell']
                );
            }
            $out .= '</tr>';
        }
        $out .= '</tbody></table>';
        $out .= html_writer::end_div();

        // Radio answer for equivalence / classify.
        if ($question->exercise_type !== 'complete') {
            $out .= $this->render_radio($question, $qa, $saved_radio, $disabled);
        }

        return $out;
    }

    /**
     * Render the verdict radio group for equivalence / classify questions.
     *
     * @param  qtype_graphitoubb_question $question
     * @param  question_attempt           $qa
     * @param  mixed                      $saved_radio Saved radio answer (bool|string|null).
     * @param  bool                       $disabled
     * @return string
     */
    private function render_radio(
        qtype_graphitoubb_question $question,
        question_attempt $qa,
        $saved_radio,
        bool $disabled
    ): string {
        if ($question->exercise_type === 'equivalence') {
            $legend  = get_string('radio_equivalence_prompt', 'qtype_graphitoubb');
            $choices = [
                ['value' => 'true',  'label' => get_string('yes')],
                ['value' => 'false', 'label' => get_string('no')],
            ];
            $current = is_bool($saved_radio) ? ($saved_radio ? 'true' : 'false') : null;
        } else {
            $legend  = get_string('radio_classify_prompt', 'qtype_graphitoubb');
            $choices = [
                ['value' => 'tautology',     'label' => get_string('expected_class_tautology', 'qtype_graphitoubb')],
                ['value' => 'contradiction', 'label' => get_string('expected_class_contradiction', 'qtype_graphitoubb')],
                ['value' => 'contingency',   'label' => get_string('expected_class_contingency', 'qtype_graphitoubb')],
            ];
            $current = is_string($saved_radio) ? $saved_radio : null;
        }

        $name = 'qtype_graphitoubb_radio_' . $qa->get_database_id();
        $out  = html_writer::start_tag('fieldset', ['class' => 'mod-graphitoubb-tte__radio-group mt-3']);
        $out .= html_writer::tag('legend', s($legend), ['class' => 'h6']);
        foreach ($choices as $idx => $choice) {
            $id = $name . '_' . $choice['value'];
            $attrs = [
                'class' => 'form-check-input mod-graphitoubb-tte__radio',
                'type'  => 'radio',
                'name'  => $name,
                'id'    => $id,
                'value' => $choice['value'],
            ];
            if ($current === $choice['value']) {
                $attrs['checked'] = 'checked';
            }
            if ($disabled) {
                $attrs['disabled'] = 'disabled';
            }
            $out .= html_writer::start_div('form-check');
            $out .= html_writer::empty_tag('input', $attrs);
            $out .= html_writer::tag('label', s($choice['label']), [
                'class' => 'form-check-label',
                'for'   => $id,
            ]);
            $out .= html_writer::end_div();
        }
        $out .= html_writer::end_tag('fieldset');

        return $out;
    }

    /**
     * C6: render a formula in the same canonical form the student sees in the
     * editor (explicit parentheses, Unicode operators). Falls back to the raw
     * formula if it cannot be parsed, so a malformed problem never breaks output.
     *
     * @param  string $raw Raw stored formula.
     * @return string Canonical formula, or the trimmed raw on parse failure.
     */
    private function canonical_formula(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        try {
            return (new \local_graphitoubb\tools\truth_table\domain\parser())->parse($raw)->canonical();
        } catch (\Throwable $e) {
            return $raw;
        }
    }

    /**
     * Render per-cell feedback after grading.
     *
     * Retrieves the grading_result from the question attempt's step data
     * and renders a bulleted list of feedback items (in Spanish).
     *
     * @param  question_attempt $qa The question attempt.
     * @return string HTML fragment (empty when no grading data).
     */
    public function specific_feedback(question_attempt $qa): string {
        $state = $qa->get_state();
        if (!$state->is_graded()) {
            return '';
        }

        // Attempt to read grading_result from step data.
        $grading_json = $qa->get_last_qt_var('grading_result', '');
        if ($grading_json === '') {
            return '';
        }

        $result = json_decode($grading_json, true);
        if (!is_array($result)) {
            return '';
        }

        $items = $result['feedback_items'] ?? [];
        if (empty($items)) {
            return '';
        }

        $html = html_writer::tag(
            'p',
            html_writer::tag('strong', get_string('feedback_section', 'qtype_graphitoubb'))
        );
        $list_items = [];
        foreach ($items as $item) {
            if (!($item['is_correct'] ?? true)) {
                $row   = (int) ($item['row_index'] ?? 0) + 1;
                $col   = htmlspecialchars($item['col_label'] ?? '', ENT_QUOTES);
                $sub   = htmlspecialchars($item['submitted'] ?? '', ENT_QUOTES);
                $exp   = htmlspecialchars($item['expected'] ?? '', ENT_QUOTES);
                $expl  = htmlspecialchars($item['explanation'] ?? '', ENT_QUOTES);
                $list_items[] = html_writer::tag(
                    'li',
                    "Fila {$row}, columna «{$col}»: enviado «{$sub}», esperado «{$exp}». {$expl}"
                );
            }
        }

        if (empty($list_items)) {
            return '';
        }

        $html .= html_writer::tag('ul', implode('', $list_items));
        return $html;
    }

    /**
     * Render the correct response display.
     *
     * The truth table's correct response is the full computed table, which is
     * rendered by the grader. In iter1 we return an empty string — the grader
     * feedback already provides per-cell corrections.
     *
     * @param  question_attempt $qa
     * @return string
     */
    public function correct_response(question_attempt $qa): string {
        return '';
    }
}
