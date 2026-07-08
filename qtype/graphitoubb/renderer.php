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

        // grafo/arbol: render the shared graph canvas host bound to the qt field.
        if ($question->tool === 'grafo' || $question->tool === 'arbol') {
            return $this->render_canvas_question($qa, $options, $question);
        }
        // karnaugh/relations: render each tool's own editor host bound to the qt field.
        if ($question->tool === 'karnaugh') {
            return $this->render_karnaugh_question($qa, $options, $question);
        }
        if ($question->tool === 'relations') {
            return $this->render_relations_question($qa, $options, $question);
        }

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
     * Render a grafo/arbol question: the shared graph_canvas host bound to the
     * quiz's hidden answer field (C1 — first working canvas qtype). No autosave/WS.
     *
     * @param  question_attempt         $qa
     * @param  question_display_options $options
     * @param  qtype_graphitoubb_question $question
     * @return string
     */
    private function render_canvas_question(
        question_attempt $qa,
        question_display_options $options,
        qtype_graphitoubb_question $question
    ): string {
        $qaid       = $qa->get_database_id();
        $hostid     = 'graphitoubb-graph-' . $qaid;
        $input_name = $qa->get_qt_field_name('answer_payload');
        $current    = (string) $qa->get_last_qt_var('answer_payload', '');

        $payload = $question->problem_payload;
        $tool    = $question->tool;
        $type    = (string) ($payload['type'] ?? '');
        $config  = $payload['config'] ?? [];

        $givenmodetypes = ['decision', 'traversal', 'traversal_answer'];
        $mode = in_array($type, $givenmodetypes, true) ? 'given' : 'build';
        $directed = !empty($config['directed']) || !empty($config['given_graph']['directed']);
        $given = ($mode === 'given') ? ($config['given_graph'] ?? ($config['given_tree'] ?? null)) : null;

        $maxnodes = $tool === 'arbol' ? 31 : 20;
        $maxedges = $tool === 'arbol' ? 60 : 40;
        $maxlabel = $tool === 'arbol' ? 6 : 12;

        $attr = static function ($v): string {
            return $v === null ? '' : s(json_encode($v, JSON_UNESCAPED_UNICODE));
        };

        // Prompt.
        $out = html_writer::start_div('qtype_graphitoubb_wrapper qtype_graphitoubb_canvas');
        if (!empty($config['prompt'])) {
            $out .= html_writer::tag('p', s((string) $config['prompt']), ['class' => 'qtype_graphitoubb_prompt']);
        }

        // Host.
        $out .= '<div class="mod-graphitoubb-graph mod-graphitoubb-editor" id="' . $hostid . '"'
            . ' data-attemptid="' . (int) $qaid . '"'
            . ' data-instanceid="' . (int) $qaid . '"'
            . ' data-schemaversion="1"'
            . ' data-tool="' . s($tool) . '"'
            . ' data-mode="' . s($mode) . '"'
            . ' data-type="' . s($type) . '"'
            . ' data-directed="' . ($directed ? '1' : '0') . '"'
            . ' data-finished="' . ($options->readonly ? '1' : '0') . '"'
            . ' data-max-nodes="' . $maxnodes . '"'
            . ' data-max-edges="' . $maxedges . '"'
            . ' data-max-label="' . $maxlabel . '"'
            . ' data-given="' . $attr($given) . '"'
            . ' data-snapshot="' . s($current) . '"'
            . ' data-answer-input="' . s($input_name) . '">';

        if ($mode !== 'given') {
            $out .= $this->canvas_toolbar($tool);
            $out .= '<p class="mod-graphitoubb-graph-hint text-muted small" aria-live="polite"></p>';
        }
        $out .= '<div class="mod-graphitoubb-graph-canvas mod-graphitoubb-canvas">'
            . '<p class="mod-graphitoubb-loading">' . get_string('editor_loading', 'mod_graphitoubb') . '</p>'
            . '<div class="mod-graphitoubb-zoom-controls" role="group">'
            . '<button type="button" class="btn btn-sm btn-light mod-graphitoubb-zoom-btn" data-zoom="in">+</button>'
            . '<button type="button" class="btn btn-sm btn-light mod-graphitoubb-zoom-btn" data-zoom="out">−</button>'
            . '<button type="button" class="btn btn-sm btn-light mod-graphitoubb-zoom-btn" data-zoom="fit">⤢</button>'
            . '<button type="button" class="btn btn-sm btn-light mod-graphitoubb-zoom-btn" data-zoom="reset">100%</button>'
            . '</div></div>';
        if ($mode === 'given') {
            $out .= $this->canvas_answer_control($tool, $type, $qaid, $options->readonly);
        }
        $out .= '</div>';

        // Hidden answer field carried by the quiz form.
        $inattrs = ['type' => 'hidden', 'name' => $input_name, 'id' => $input_name,
            'value' => htmlspecialchars($current, ENT_QUOTES)];
        if ($options->readonly) {
            $inattrs['disabled'] = 'disabled';
        }
        $out .= html_writer::empty_tag('input', $inattrs);
        $out .= html_writer::end_div();

        $this->page->requires->js_call_amd('mod_graphitoubb/graph_canvas', 'init',
            [(int) $qaid, (int) $qaid, 1, $tool]);

        return $out;
    }

    /**
     * Render a karnaugh question: the two-stage K-map editor host bound to the
     * quiz's hidden answer field (seed/import-only, D14). No autosave/WS.
     *
     * @param  question_attempt         $qa
     * @param  question_display_options $options
     * @param  qtype_graphitoubb_question $question
     * @return string
     */
    private function render_karnaugh_question(
        question_attempt $qa,
        question_display_options $options,
        qtype_graphitoubb_question $question
    ): string {
        $qaid       = $qa->get_database_id();
        $input_name = $qa->get_qt_field_name('answer_payload');
        $current    = (string) $qa->get_last_qt_var('answer_payload', '');

        $config   = $question->problem_payload['config'] ?? [];
        $nvars    = (int) ($config['n_vars'] ?? 2);
        $varnames = array_values(array_map('strval', $config['var_names'] ?? []));
        $minterms = array_values(array_map('intval', $config['minterms'] ?? []));

        $out = html_writer::start_div('qtype_graphitoubb_wrapper qtype_graphitoubb_karnaugh');
        if (!empty($config['prompt'])) {
            $out .= html_writer::tag('p', s((string) $config['prompt']), ['class' => 'qtype_graphitoubb_prompt']);
        }
        // Given truth table so the student knows f (same as the mod consigna).
        $out .= $this->karnaugh_truthtable($nvars, $minterms, $varnames);

        $out .= '<div class="mod-graphitoubb-karnaugh mod-graphitoubb-editor" id="graphitoubb-karnaugh-' . (int) $qaid . '"'
            . ' data-attemptid="' . (int) $qaid . '" data-instanceid="' . (int) $qaid . '" data-schemaversion="1"'
            . ' data-nvars="' . $nvars . '"'
            . ' data-varnames="' . s(json_encode($varnames, JSON_UNESCAPED_UNICODE)) . '"'
            . ' data-minterms="' . s(json_encode($minterms)) . '"'
            . ' data-finished="' . ($options->readonly ? '1' : '0') . '"'
            . ' data-snapshot="' . s($current) . '"'
            . ' data-answer-input="' . s($input_name) . '">';
        $out .= '<div class="mod-graphitoubb-kmap-modebar mod-graphitoubb-toolbar mb-2" role="toolbar">'
            . '<button type="button" class="btn btn-sm btn-outline-secondary" data-kmode="fill" aria-pressed="true">'
            . get_string('kmap_mode_fill', 'mod_graphitoubb') . '</button>'
            . '<button type="button" class="btn btn-sm btn-outline-secondary" data-kmode="group" aria-pressed="false">'
            . get_string('kmap_mode_group', 'mod_graphitoubb') . '</button></div>';
        $out .= '<div class="mod-graphitoubb-kmap-grid"></div>';
        $out .= '<div class="mod-graphitoubb-kmap-filltools mt-2">'
            . '<p class="text-muted small mb-1">' . get_string('kmap_fill_help', 'mod_graphitoubb') . '</p>'
            . '<button type="button" class="btn btn-sm btn-info mod-graphitoubb-kmap-verify"' . ($options->readonly ? ' disabled' : '') . '>'
            . get_string('kmap_verify_button', 'mod_graphitoubb') . '</button>'
            . '<span class="mod-graphitoubb-kmap-verify-result ml-2" aria-live="polite"></span></div>';
        $out .= '<div class="mod-graphitoubb-kmap-grouptools mt-2" style="display:none">'
            . '<p class="text-muted small mb-1">' . get_string('kmap_group_help', 'mod_graphitoubb') . '</p>'
            . '<button type="button" class="btn btn-sm btn-primary mod-graphitoubb-kmap-addgroup"' . ($options->readonly ? ' disabled' : '') . '>'
            . get_string('kmap_add_group', 'mod_graphitoubb') . '</button> '
            . '<button type="button" class="btn btn-sm btn-outline-secondary mod-graphitoubb-kmap-clearsel"' . ($options->readonly ? ' disabled' : '') . '>'
            . get_string('kmap_clear_selection', 'mod_graphitoubb') . '</button>'
            . '<ul class="mod-graphitoubb-kmap-groups list-unstyled mt-2"></ul>'
            . '<div class="mod-graphitoubb-kmap-minimal font-monospace mt-2 p-2 bg-light rounded">f = —</div></div>';
        $out .= '</div>';

        $inattrs = ['type' => 'hidden', 'name' => $input_name, 'id' => $input_name,
            'value' => htmlspecialchars($current, ENT_QUOTES)];
        if ($options->readonly) {
            $inattrs['disabled'] = 'disabled';
        }
        $out .= html_writer::empty_tag('input', $inattrs);
        $out .= html_writer::end_div();

        $this->page->requires->js_call_amd('mod_graphitoubb/karnaugh_editor', 'init', [(int) $qaid]);
        return $out;
    }

    /**
     * Render the read-only given truth table for a karnaugh qtype question.
     *
     * @param  int   $nvars
     * @param  int[] $minterms
     * @param  string[] $varnames
     * @return string
     */
    private function karnaugh_truthtable(int $nvars, array $minterms, array $varnames): string {
        $mint = array_fill_keys($minterms, true);
        $out = '<div class="table-responsive"><table class="table table-sm table-bordered" style="width:auto">';
        $out .= '<thead><tr>';
        foreach ($varnames as $v) {
            $out .= '<th class="text-center">' . s($v) . '</th>';
        }
        $out .= '<th class="text-center">f</th></tr></thead><tbody>';
        for ($i = 0; $i < (1 << $nvars); $i++) {
            $out .= '<tr>';
            for ($pos = 0; $pos < $nvars; $pos++) {
                $bit = $nvars - 1 - $pos;
                $out .= '<td class="text-center">' . (($i >> $bit) & 1) . '</td>';
            }
            $out .= '<td class="text-center font-weight-bold">' . (isset($mint[$i]) ? 1 : 0) . '</td></tr>';
        }
        $out .= '</tbody></table></div>';
        return $out;
    }

    /**
     * Render a relations question: matrix/pairs/digraph editor host bound to the
     * quiz's hidden answer field (seed/import-only, D14).
     *
     * @param  question_attempt         $qa
     * @param  question_display_options $options
     * @param  qtype_graphitoubb_question $question
     * @return string
     */
    private function render_relations_question(
        question_attempt $qa,
        question_display_options $options,
        qtype_graphitoubb_question $question
    ): string {
        $qaid       = $qa->get_database_id();
        $input_name = $qa->get_qt_field_name('answer_payload');
        $current    = (string) $qa->get_last_qt_var('answer_payload', '');

        $config   = $question->problem_payload['config'] ?? [];
        $baseset  = array_values(array_map('strval', $config['base_set'] ?? []));
        $requiredrep = (string) ($config['required_representation'] ?? 'any');
        $askprops = $config['ask_properties'] ?? ['reflexive', 'symmetric', 'antisymmetric', 'transitive'];

        $proplabels = [
            'reflexive'     => get_string('relations_prop_reflexive', 'mod_graphitoubb'),
            'symmetric'     => get_string('relations_prop_symmetric', 'mod_graphitoubb'),
            'antisymmetric' => get_string('relations_prop_antisymmetric', 'mod_graphitoubb'),
            'transitive'    => get_string('relations_prop_transitive', 'mod_graphitoubb'),
        ];

        $showmatrix  = ($requiredrep === 'any' || $requiredrep === 'matrix');
        $showpairs   = ($requiredrep === 'any' || $requiredrep === 'pairs');
        $showdigraph = ($requiredrep === 'any' || $requiredrep === 'digraph');

        $out = html_writer::start_div('qtype_graphitoubb_wrapper qtype_graphitoubb_relations');
        if (!empty($config['prompt'])) {
            $out .= html_writer::tag('p', s((string) $config['prompt']), ['class' => 'qtype_graphitoubb_prompt']);
        }
        $rel = [];
        foreach (($config['relation'] ?? []) as $p) {
            $rel[] = '(' . (string) ($p[0] ?? '') . ', ' . (string) ($p[1] ?? '') . ')';
        }
        $out .= html_writer::tag('p', 'S = { ' . s(implode(', ', $baseset)) . ' } &nbsp; R = { '
            . s(implode(' ', $rel)) . ' }', ['class' => 'font-monospace']);

        $out .= '<div class="mod-graphitoubb-relations mod-graphitoubb-editor" id="graphitoubb-relations-' . (int) $qaid . '"'
            . ' data-attemptid="' . (int) $qaid . '" data-instanceid="' . (int) $qaid . '" data-schemaversion="1"'
            . ' data-baseset="' . s(json_encode($baseset, JSON_UNESCAPED_UNICODE)) . '"'
            . ' data-required-rep="' . s($requiredrep) . '"'
            . ' data-finished="' . ($options->readonly ? '1' : '0') . '"'
            . ' data-snapshot="' . s($current) . '"'
            . ' data-answer-input="' . s($input_name) . '">';
        $out .= '<div class="mod-graphitoubb-rel-tabs mod-graphitoubb-toolbar mb-2" role="tablist">';
        if ($showmatrix) {
            $out .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-rep-tab="matrix">'
                . get_string('relations_rep_tab_matrix', 'mod_graphitoubb') . '</button>';
        }
        if ($showpairs) {
            $out .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-rep-tab="pairs">'
                . get_string('relations_rep_tab_pairs', 'mod_graphitoubb') . '</button>';
        }
        if ($showdigraph) {
            $out .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-rep-tab="digraph">'
                . get_string('relations_rep_tab_digraph', 'mod_graphitoubb') . '</button>';
        }
        $out .= '</div>';
        $out .= '<div class="mod-graphitoubb-rel-matrix-wrap" style="display:none"></div>';
        $out .= '<div class="mod-graphitoubb-rel-pairs-wrap" style="display:none"></div>';
        $out .= '<div class="mod-graphitoubb-rel-digraph-wrap" style="display:none"></div>';
        $out .= '<fieldset class="mod-graphitoubb-rel-props card card-body mt-3"' . ($options->readonly ? ' disabled' : '') . '>';
        $out .= '<legend class="h6">' . get_string('relations_props_legend', 'mod_graphitoubb') . '</legend>';
        foreach ($askprops as $p) {
            if (!isset($proplabels[$p])) {
                continue;
            }
            $id = 'rel-prop-' . $p . '-' . (int) $qaid;
            $out .= '<div class="form-check"><input class="form-check-input mod-graphitoubb-rel-prop" type="checkbox" id="'
                . $id . '" value="' . $p . '"><label class="form-check-label" for="' . $id . '">'
                . $proplabels[$p] . '</label></div>';
        }
        $out .= '</fieldset>';
        $out .= '</div>';

        $inattrs = ['type' => 'hidden', 'name' => $input_name, 'id' => $input_name,
            'value' => htmlspecialchars($current, ENT_QUOTES)];
        if ($options->readonly) {
            $inattrs['disabled'] = 'disabled';
        }
        $out .= html_writer::empty_tag('input', $inattrs);
        $out .= html_writer::end_div();

        $this->page->requires->js_call_amd('mod_graphitoubb/relations_editor', 'init', [(int) $qaid]);
        return $out;
    }

    /**
     * Build the build/authoring toolbar for a canvas question.
     *
     * @param  string $tool
     * @return string
     */
    private function canvas_toolbar(string $tool): string {
        $btn = static function (string $mode, string $key, string $cls = 'btn-outline-secondary') use (&$btn): string {
            return '<button type="button" class="btn btn-sm ' . $cls . ' mod-graphitoubb-tool-btn" '
                . 'data-gmode="' . $mode . '" aria-pressed="false">'
                . get_string($key, 'mod_graphitoubb') . '</button>';
        };
        $out  = '<div class="mod-graphitoubb-graph-toolbar mod-graphitoubb-toolbar" role="toolbar">';
        $out .= $btn('addnode', 'graph_btn_addnode');
        $out .= $btn('addedge', 'graph_btn_addedge');
        if ($tool === 'arbol') {
            $out .= $btn('setroot', 'graph_btn_setroot');
        }
        $out .= $btn('delete', 'graph_btn_delete', 'btn-outline-danger');
        $out .= '<button type="button" class="btn btn-sm btn-light" data-gaction="tidy">'
            . get_string('graph_btn_tidy', 'mod_graphitoubb') . '</button>';
        $out .= '<button type="button" class="btn btn-sm btn-light" data-gaction="clear">'
            . get_string('graph_btn_clear', 'mod_graphitoubb') . '</button>';
        $out .= '</div>';
        return $out;
    }

    /**
     * Build the given-mode answer control for a canvas question.
     *
     * @param  string $tool
     * @param  string $type
     * @param  int    $qaid
     * @param  bool   $readonly
     * @return string
     */
    private function canvas_answer_control(string $tool, string $type, int $qaid, bool $readonly): string {
        $dis = $readonly ? ' disabled' : '';
        $out = '<div class="mod-graphitoubb-graph-answer card card-body mt-2">';
        if ($type === 'decision') {
            $name = 'graph-decision-' . $qaid;
            $out .= '<fieldset' . $dis . '><legend class="h6">'
                . get_string('graph_answer_decision_legend', 'mod_graphitoubb') . '</legend>';
            $out .= '<div class="form-check"><input class="form-check-input" type="radio" name="' . $name . '"'
                . ' id="' . $name . '-yes" value="true"><label class="form-check-label" for="' . $name . '-yes">'
                . get_string('graph_decision_yes', 'mod_graphitoubb') . '</label></div>';
            $out .= '<div class="form-check"><input class="form-check-input" type="radio" name="' . $name . '"'
                . ' id="' . $name . '-no" value="false"><label class="form-check-label" for="' . $name . '-no">'
                . get_string('graph_decision_no', 'mod_graphitoubb') . '</label></div></fieldset>';
        } else if ($type === 'traversal') {
            $out .= '<p class="h6">' . get_string('graph_answer_traversal_legend', 'mod_graphitoubb') . '</p>';
            $out .= '<p class="text-muted small">' . get_string('graph_answer_traversal_help', 'mod_graphitoubb') . '</p>';
            $out .= '<div class="mod-graphitoubb-seq-wrap mb-2"><span class="font-weight-bold">'
                . get_string('graph_answer_walk_label', 'mod_graphitoubb')
                . '</span> <span class="mod-graphitoubb-seq-list font-monospace" aria-live="polite"></span></div>';
            $out .= '<p class="mod-graphitoubb-seq-hint text-info small" aria-live="polite"></p>';
            $out .= '<button type="button" class="btn btn-sm btn-outline-secondary mod-graphitoubb-seq-undo"' . $dis . '>'
                . get_string('graph_answer_undo', 'mod_graphitoubb') . '</button> ';
            $out .= '<button type="button" class="btn btn-sm btn-outline-secondary mod-graphitoubb-seq-clear"' . $dis . '>'
                . get_string('graph_answer_clear', 'mod_graphitoubb') . '</button>';
        } else if ($type === 'traversal_answer') {
            $out .= '<label class="h6" for="graph-seq-' . $qaid . '">'
                . get_string('graph_answer_sequence_legend', 'mod_graphitoubb') . '</label>';
            $out .= '<input type="text" id="graph-seq-' . $qaid . '" class="form-control mod-graphitoubb-seq-input"'
                . ' placeholder="' . s(get_string('graph_answer_sequence_placeholder', 'mod_graphitoubb')) . '"' . $dis . '>';
        }
        $out .= '</div>';
        return $out;
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
