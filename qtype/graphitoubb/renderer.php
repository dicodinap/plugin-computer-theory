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
                    $f1 = htmlspecialchars($config['formula_1'] ?? '', ENT_QUOTES);
                    $f2 = htmlspecialchars($config['formula_2'] ?? '', ENT_QUOTES);
                    $html .= html_writer::tag(
                        'p',
                        html_writer::tag('strong', 'F1: ') . $f1 . ' &nbsp; ' .
                        html_writer::tag('strong', 'F2: ') . $f2,
                        ['class' => 'qtype_graphitoubb_formulas']
                    );
                    break;
                default:
                    $formula = htmlspecialchars($config['formula'] ?? '', ENT_QUOTES);
                    $html .= html_writer::tag(
                        'p',
                        html_writer::tag('strong', get_string('formula', 'qtype_graphitoubb') . ': ') . $formula,
                        ['class' => 'qtype_graphitoubb_formula']
                    );
                    break;
            }
        }

        // Truth table editor container (filled by AMD).
        $html .= html_writer::div('', 'qtype_graphitoubb_editor', ['data-exercise-type' => $exercise_type]);

        // Hidden input carrying the current JSON answer.
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

        // Initialise the AMD truth table editor module.
        if (!$options->readonly) {
            $PAGE->requires->js_call_amd(
                'mod_graphitoubb/truth_table_editor',
                'init',
                [
                    '#' . $wrapper_id,
                    $problem_json,
                    $input_name,
                ]
            );
        }

        return $html;
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
