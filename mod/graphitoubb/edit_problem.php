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

echo $OUTPUT->header();
echo $OUTPUT->heading('Edit truth_table problem — ' . format_string($instance->name));

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

echo <<<HTML
<form method="post" action="">
    <input type="hidden" name="sesskey" value="{$sesskey}">

    <div class="form-group">
        <label for="exercise_type"><strong>Exercise type</strong></label>
        <select name="exercise_type" id="exercise_type" class="form-control" onchange="this.form.submit()">
HTML;
echo $selopt('complete',    $selected_type, 'Complete table');
echo $selopt('equivalence', $selected_type, 'Equivalence (two formulas)');
echo $selopt('classify',    $selected_type, 'Classify (tautology / contradiction / contingency)');
echo <<<HTML
        </select>
    </div>

    <div class="form-group">
        <label for="formula"><strong>Formula</strong> (single, for complete & classify)</label>
        <input type="text" name="formula" id="formula" class="form-control"
               value="{$cur_formula_safe}"
               placeholder="A ∧ B  (or use ASCII: A & B, A | B, ~A, A -> B, A &lt;-&gt; B)">
        <small class="form-text text-muted">
            Symbols: ¬ ∧ ∨ ⊕ → ↔ ⊤ ⊥. ASCII synonyms accepted.
        </small>
    </div>

    <div class="form-group">
        <label for="formula_1"><strong>Formula 1</strong> (equivalence only)</label>
        <input type="text" name="formula_1" id="formula_1" class="form-control"
               value="{$cur_formula1_safe}" placeholder="A → B">
    </div>
    <div class="form-group">
        <label for="formula_2"><strong>Formula 2</strong> (equivalence only)</label>
        <input type="text" name="formula_2" id="formula_2" class="form-control"
               value="{$cur_formula2_safe}" placeholder="¬A ∨ B">
    </div>

    <div class="form-group">
        <label><strong>Expected equivalent</strong> (equivalence only)</label>
        <select name="expected_equivalent" class="form-control">
HTML;
echo $selopt('1', $cur_expequiv ? '1' : '0', 'Yes — equivalent');
echo $selopt('0', $cur_expequiv ? '1' : '0', 'No — not equivalent');
echo <<<HTML
        </select>
    </div>

    <div class="form-group">
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

    <div class="form-check">
        <input type="checkbox" name="require_table_justification" value="1"
               id="reqjust" class="form-check-input"{$reqjust_attr}>
        <label class="form-check-label" for="reqjust">
            Require table justification (equivalence / classify)
        </label>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Save problem</button>
        <a class="btn btn-secondary" href="{$viewurl}">Back to activity</a>
    </div>
</form>
HTML;

echo $OUTPUT->footer();
