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
 * Teacher-facing problem editor for mod_graphitoubb.
 *
 * Sets the tool, exercise type, formula(s), and scoring config for a graphitoubb
 * activity instance. Inserts or updates the graphitoubb_problem row, validates the
 * payload against the JSON Schema and the domain validator.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

$cm       = get_coursemodule_from_id('graphitoubb', $id, 0, false, MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('graphitoubb', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/mod/graphitoubb/edit_problem.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title('Edit problem — ' . format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

$existing = $DB->get_record('graphitoubb_problem', ['instanceid' => $instance->id]);

// Pre-fill from existing payload if present.
$prevpayload = null;
if ($existing) {
    $prevpayload = json_decode($existing->payload, true);
}

$error = null;
$savedmsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $tool = optional_param('tool', 'truth_table', PARAM_ALPHA);
}

// C1: AFD authoring branch — prompt + alphabet + expected-verdict test words.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($tool ?? '') === 'afd') {
    $afd_prompt       = optional_param('afd_prompt', '', PARAM_TEXT);
    $afd_alphabet_raw = optional_param('afd_alphabet', '', PARAM_RAW_TRIMMED);
    $afd_words_raw    = optional_param('afd_test_words', '', PARAM_RAW);

    // Alphabet: distinct single alphanumeric characters, in input order.
    preg_match_all('/[a-zA-Z0-9]/', $afd_alphabet_raw, $am);
    $alphabet = array_values(array_unique($am[0]));

    // Test words: one per line, "VERDICT:WORD" (accept|reject|+|-|t|f|1|0…).
    $testwords  = [];
    $wordserror = [];
    foreach (preg_split('/\r\n|\r|\n/', $afd_words_raw) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts   = explode(':', $line, 2);
        $verdict = strtolower(trim($parts[0]));
        $word    = isset($parts[1]) ? trim($parts[1]) : '';
        $accept  = in_array($verdict, ['accept', 'a', '+', 't', 'true', '1', 'yes'], true);
        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if (!in_array($ch, $alphabet, true)) {
                $wordserror[] = '"' . $word . '" (symbol "' . $ch . '")';
                break;
            }
        }
        $testwords[] = ['word' => $word, 'accept' => $accept];
    }

    if ($afd_prompt === '') {
        $error = 'The prompt (consigna) is required.';
    } else if (empty($alphabet)) {
        $error = 'Define at least one alphabet symbol.';
    } else if (empty($testwords)) {
        $error = 'Add at least one test word (format: accept:WORD or reject:WORD).';
    } else if (!empty($wordserror)) {
        $error = 'Some test words use symbols outside the alphabet: ' . implode('; ', $wordserror);
    }

    if (!$error) {
        $payload = [
            'tool'           => 'afd',
            'schema_version' => 1,
            'type'           => 'language',
            'config'         => [
                'prompt'     => $afd_prompt,
                'alphabet'   => $alphabet,
                'test_words' => $testwords,
            ],
        ];
        (new \mod_graphitoubb\problem_repository())->save((int) $instance->id, 'afd', 'language', $payload, 1);
        $savedmsg    = 'Problem saved.';
        $prevpayload = $payload;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($tool ?? 'truth_table') !== 'afd') {
    $type     = required_param('exercise_type', PARAM_ALPHA);
    $formula  = optional_param('formula',   '', PARAM_RAW_TRIMMED);
    $formula1 = optional_param('formula_1', '', PARAM_RAW_TRIMMED);
    $formula2 = optional_param('formula_2', '', PARAM_RAW_TRIMMED);
    $expequiv = optional_param('expected_equivalent', 1, PARAM_INT);
    $expclass = optional_param('expected_class', 'tautology', PARAM_ALPHA);
    $reqjust  = optional_param('require_table_justification', 0, PARAM_INT);

    $config = [];
    if ($type === 'complete') {
        $config['formula'] = $formula;
    } else if ($type === 'equivalence') {
        $config['formula_1'] = $formula1;
        $config['formula_2'] = $formula2;
        $config['expected_equivalent'] = (bool) $expequiv;
        $config['require_table_justification'] = (bool) $reqjust;
    } else if ($type === 'classify') {
        $config['formula'] = $formula;
        $config['expected_class'] = $expclass;
        $config['require_table_justification'] = (bool) $reqjust;
    }

    $payload = [
        'tool'           => 'truth_table',
        'schema_version' => 1,
        'type'           => $type,
        'config'         => $config,
        'ui'             => [
            'intermediate_subformulas' => 'auto',
            'manual_subformulas'       => [],
            'row_order'                => 'canonical',
        ],
    ];

    if ($type !== 'complete') {
        $payload['scoring'] = [
            'radio_weight'       => 50,
            'table_weight'       => 50,
            'wrong_radio_policy' => 'strict',
        ];
    }

    // Schema-validate.
    $loader = new \local_graphitoubb\tools\truth_table\schema\schema_loader();
    $result = $loader->validate($payload, $type, 'problem');
    if (!$result->ok) {
        $error = 'Schema validation failed: ' . implode('; ', $result->errors);
    } else {
        // Domain validate (parses formula).
        $validator = new \local_graphitoubb\tools\truth_table\domain\validator();
        $vres = $validator->validate_problem($payload);
        if (!$vres->ok) {
            $error = 'Domain validation failed: ' . implode('; ', $vres->errors);
        }
    }

    if (!$error) {
        $serializer = new \local_graphitoubb\tools\truth_table\domain\serializer();
        $jsonenc    = $serializer->encode($payload);
        $hash       = $serializer->hash($payload);
        $now        = time();

        if ($existing) {
            $existing->tool           = 'truth_table';
            $existing->type           = $type;
            $existing->payload        = $jsonenc;
            $existing->payload_hash   = $hash;
            $existing->schema_version = 1;
            $existing->timemodified   = $now;
            $DB->update_record('graphitoubb_problem', $existing);
        } else {
            $DB->insert_record('graphitoubb_problem', (object) [
                'instanceid'     => $instance->id,
                'tool'           => 'truth_table',
                'type'           => $type,
                'payload'        => $jsonenc,
                'payload_hash'   => $hash,
                'schema_version' => 1,
                'timecreated'    => $now,
                'timemodified'   => $now,
            ]);
        }
        $savedmsg    = 'Problem saved.';
        $prevpayload = $payload;
    }
}

$selected_type = $prevpayload['type']                            ?? 'complete';
$cur_formula   = $prevpayload['config']['formula']               ?? 'A ∧ B';
$cur_formula1  = $prevpayload['config']['formula_1']             ?? '';
$cur_formula2  = $prevpayload['config']['formula_2']             ?? '';
$cur_expequiv  = $prevpayload['config']['expected_equivalent']   ?? true;
$cur_expclass  = $prevpayload['config']['expected_class']        ?? 'tautology';
$cur_reqjust   = $prevpayload['config']['require_table_justification'] ?? false;

// C1: AFD authoring prefill.
$cur_tool       = $prevpayload['tool'] ?? 'truth_table';
$cur_afd_prompt = $prevpayload['config']['prompt'] ?? '';
$cur_afd_alpha  = isset($prevpayload['config']['alphabet'])
    ? implode(' ', $prevpayload['config']['alphabet'])
    : 'a b';
$cur_afd_words  = '';
if (($prevpayload['tool'] ?? '') === 'afd' && !empty($prevpayload['config']['test_words'])) {
    $lines = [];
    foreach ($prevpayload['config']['test_words'] as $tw) {
        $lines[] = (!empty($tw['accept']) ? 'accept' : 'reject') . ':' . ($tw['word'] ?? '');
    }
    $cur_afd_words = implode("\n", $lines);
}

echo $OUTPUT->header();
echo $OUTPUT->heading('Edit problem — ' . format_string($instance->name));

if ($error) {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}
if ($savedmsg) {
    echo $OUTPUT->notification($savedmsg, \core\output\notification::NOTIFY_SUCCESS);
}

$sesskey = sesskey();
$viewurl = (new \moodle_url('/mod/graphitoubb/view.php', ['id' => $cm->id]))->out(false);

$selopt = function(string $value, string $current, string $label): string {
    $sel = ($value === $current) ? ' selected' : '';
    return '<option value="' . s($value) . '"' . $sel . '>' . s($label) . '</option>';
};

$checked = function(bool $b): string {
    return $b ? ' checked' : '';
};

$cur_formula_safe  = s($cur_formula);
$cur_formula1_safe = s($cur_formula1);
$cur_formula2_safe = s($cur_formula2);
$cur_afd_prompt_safe = s($cur_afd_prompt);
$cur_afd_alpha_safe  = s($cur_afd_alpha);
$cur_afd_words_safe  = s($cur_afd_words);

echo <<<HTML
<form method="post" action="">
    <input type="hidden" name="sesskey" value="{$sesskey}">

    <div class="form-group">
        <label for="tool"><strong>Tool</strong></label>
        <select name="tool" id="tool" class="form-control">
HTML;
echo $selopt('truth_table', $cur_tool, 'Truth table (logic)');
echo $selopt('afd',         $cur_tool, 'AFD — finite automaton');
echo <<<HTML
        </select>
    </div>

    <div class="mod-graphitoubb-tool-section" data-tool="truth_table">
    <div class="form-group">
        <label for="exercise_type"><strong>Exercise type</strong></label>
        <select name="exercise_type" id="exercise_type" class="form-control">
HTML;
echo $selopt('complete',    $selected_type, 'Complete table');
echo $selopt('equivalence', $selected_type, 'Equivalence (two formulas)');
echo $selopt('classify',    $selected_type, 'Classify (tautology / contradiction / contingency)');
echo <<<HTML
        </select>
    </div>

    <details class="mod-graphitoubb-syntax-help card card-body mb-3">
        <summary><strong>Formula syntax help</strong></summary>
        <table class="table table-sm mt-2 mb-0">
            <thead><tr><th>Operator</th><th>Symbol</th><th>ASCII</th><th>Example</th></tr></thead>
            <tbody>
                <tr><td>Negation</td><td>¬</td><td><code>~</code> <code>!</code></td><td><code>~A</code> → ¬A</td></tr>
                <tr><td>Conjunction</td><td>∧</td><td><code>&amp;</code> <code>/\\</code></td><td><code>A &amp; B</code> → A ∧ B</td></tr>
                <tr><td>Disjunction</td><td>∨</td><td><code>|</code> <code>\\/</code></td><td><code>A | B</code> → A ∨ B</td></tr>
                <tr><td>Exclusive or</td><td>⊕</td><td>—</td><td><code>A ⊕ B</code></td></tr>
                <tr><td>Implication</td><td>→</td><td><code>-&gt;</code></td><td><code>A -&gt; B</code> → A → B</td></tr>
                <tr><td>Biconditional</td><td>↔</td><td><code>&lt;-&gt;</code></td><td><code>A &lt;-&gt; B</code> → A ↔ B</td></tr>
                <tr><td>True / False</td><td>⊤ / ⊥</td><td>—</td><td><code>⊤</code>, <code>⊥</code></td></tr>
            </tbody>
        </table>
        <small class="form-text text-muted">Variables are single uppercase letters (A–Z). Use parentheses to group.</small>
    </details>

    <div class="form-group mod-graphitoubb-field-group" data-types="complete classify">
        <label for="formula"><strong>Formula</strong> (single, for complete &amp; classify)</label>
        <input type="text" name="formula" id="formula" class="form-control"
               value="{$cur_formula_safe}"
               placeholder="A ∧ B  (or use ASCII: A & B, A | B, ~A, A -> B, A &lt;-&gt; B)">
        <small class="form-text text-muted">
            Symbols: ¬ ∧ ∨ ⊕ → ↔ ⊤ ⊥. ASCII synonyms accepted.
        </small>
    </div>

    <div class="form-group mod-graphitoubb-field-group" data-types="equivalence">
        <label for="formula_1"><strong>Formula 1</strong> (equivalence only)</label>
        <input type="text" name="formula_1" id="formula_1" class="form-control"
               value="{$cur_formula1_safe}" placeholder="A → B">
    </div>
    <div class="form-group mod-graphitoubb-field-group" data-types="equivalence">
        <label for="formula_2"><strong>Formula 2</strong> (equivalence only)</label>
        <input type="text" name="formula_2" id="formula_2" class="form-control"
               value="{$cur_formula2_safe}" placeholder="¬A ∨ B">
    </div>

    <div class="form-group mod-graphitoubb-field-group" data-types="equivalence">
        <label><strong>Expected equivalent</strong> (equivalence only)</label>
        <select name="expected_equivalent" class="form-control">
HTML;
echo $selopt('1', $cur_expequiv ? '1' : '0', 'Yes — equivalent');
echo $selopt('0', $cur_expequiv ? '1' : '0', 'No — not equivalent');
echo <<<HTML
        </select>
    </div>

    <div class="form-group mod-graphitoubb-field-group" data-types="classify">
        <label><strong>Expected class</strong> (classify only)</label>
        <select name="expected_class" class="form-control">
HTML;
echo $selopt('tautology',     $cur_expclass, 'Tautology');
echo $selopt('contradiction', $cur_expclass, 'Contradiction');
echo $selopt('contingency',   $cur_expclass, 'Contingency');
$reqjust_attr = $checked((bool) $cur_reqjust);
echo <<<HTML
        </select>
    </div>

    <div class="form-check mod-graphitoubb-field-group" data-types="equivalence classify">
        <input type="checkbox" name="require_table_justification" value="1"
               id="reqjust" class="form-check-input"{$reqjust_attr}>
        <label class="form-check-label" for="reqjust">
            Require table justification (equivalence / classify)
        </label>
    </div>
    </div><!-- /truth_table tool section -->

    <div class="mod-graphitoubb-tool-section" data-tool="afd">
        <div class="form-group">
            <label for="afd_prompt"><strong>Prompt (consigna)</strong></label>
            <textarea name="afd_prompt" id="afd_prompt" class="form-control" rows="3"
                      placeholder="Build a DFA over {a, b} that accepts exactly the words containing at least one 'a'.">{$cur_afd_prompt_safe}</textarea>
            <small class="form-text text-muted">Shown to the student above the editor.</small>
        </div>
        <div class="form-group">
            <label for="afd_alphabet"><strong>Alphabet</strong></label>
            <input type="text" name="afd_alphabet" id="afd_alphabet" class="form-control"
                   value="{$cur_afd_alpha_safe}" placeholder="a b">
            <small class="form-text text-muted">Single alphanumeric symbols, separated by spaces or commas.</small>
        </div>
        <div class="form-group">
            <label for="afd_test_words"><strong>Test words</strong> (one per line)</label>
            <textarea name="afd_test_words" id="afd_test_words" class="form-control" rows="6"
                      placeholder="accept:a&#10;accept:aa&#10;accept:ba&#10;reject:&#10;reject:b">{$cur_afd_words_safe}</textarea>
            <small class="form-text text-muted">
                Format <code>verdict:word</code> — e.g. <code>accept:aa</code>, <code>reject:b</code>,
                <code>accept:</code> (empty word ε). Verdicts: accept / reject (also + / -). These are
                hidden from students and used to grade the automaton on submission.
            </small>
        </div>
    </div><!-- /afd tool section -->

    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Save problem</button>
        <a class="btn btn-secondary" href="{$viewurl}">Back to activity</a>
    </div>
</form>
HTML;

// C3: show/hide the type-specific fields client-side instead of reloading the
// whole form on change (the old onchange="this.form.submit()" lost typed input).
$PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    var toolSel = document.getElementById('tool');
    var typeSel = document.getElementById('exercise_type');

    var toggleTool = function() {
        var t = toolSel ? toolSel.value : 'truth_table';
        document.querySelectorAll('.mod-graphitoubb-tool-section').forEach(function(s) {
            s.style.display = (s.getAttribute('data-tool') === t) ? '' : 'none';
        });
    };
    var toggleType = function() {
        if (!typeSel) {
            return;
        }
        var t = typeSel.value;
        document.querySelectorAll('.mod-graphitoubb-field-group').forEach(function(g) {
            var types = (g.getAttribute('data-types') || '').split(' ');
            g.style.display = (types.indexOf(t) !== -1) ? '' : 'none';
        });
    };
    if (toolSel) {
        toolSel.addEventListener('change', toggleTool);
    }
    if (typeSel) {
        typeSel.addEventListener('change', toggleType);
    }
    toggleTool();
    toggleType();
});
JS);

echo $OUTPUT->footer();
