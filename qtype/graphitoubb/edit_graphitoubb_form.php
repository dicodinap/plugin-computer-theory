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
 * Edit form for qtype_graphitoubb questions.
 *
 * Loaded by Moodle's question editing infrastructure via the filename convention.
 * Non-autoloaded; no namespace, no strict_types (procedural form file).
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_graphitoubb\tools\truth_table\schema\schema_loader;

/**
 * Editing form for GraphitoUBB truth_table questions.
 *
 * Renders exercise-type selector, formula inputs, and scoring options.
 * Conditional field visibility is controlled via MoodleQuickForm::disabledIf().
 */
class qtype_graphitoubb_edit_form extends question_edit_form {
    /**
     * Add the question-type specific form elements.
     *
     * Layout:
     * - tool (hidden, always 'truth_table').
     * - exercise_type select (complete / equivalence / classify).
     * - formula (visible for complete and classify).
     * - formula_1, formula_2 (visible for equivalence).
     * - expected_equivalent checkbox (visible for equivalence).
     * - expected_class select (visible for classify).
     * - require_table_justification checkbox (visible for equivalence and classify).
     * - Scoring section (visible for equivalence and classify).
     *
     * @param  MoodleQuickForm $mform
     * @return void
     */
    protected function definition_inner($mform): void {
        // Hidden tool slug — always truth_table.
        $mform->addElement('hidden', 'tool', 'truth_table');
        $mform->setType('tool', PARAM_ALPHA);
        $mform->setConstant('tool', 'truth_table');

        // ---- Exercise type ----
        $type_options = [
            'complete'    => get_string('exercise_type_complete', 'qtype_graphitoubb'),
            'equivalence' => get_string('exercise_type_equivalence', 'qtype_graphitoubb'),
            'classify'    => get_string('exercise_type_classify', 'qtype_graphitoubb'),
        ];
        $mform->addElement(
            'select',
            'exercise_type',
            get_string('exercise_type', 'qtype_graphitoubb'),
            $type_options
        );
        $mform->setDefault('exercise_type', 'complete');

        // ---- Single formula (complete + classify) ----
        $mform->addElement(
            'text',
            'formula',
            get_string('formula', 'qtype_graphitoubb'),
            ['size' => 60]
        );
        $mform->setType('formula', PARAM_RAW);
        $mform->addHelpButton('formula', 'formula', 'qtype_graphitoubb');
        // Hide when exercise_type == 'equivalence'.
        $mform->hideIf('formula', 'exercise_type', 'eq', 'equivalence');

        // ---- Equivalence: formula_1 and formula_2 ----
        $mform->addElement(
            'text',
            'formula_1',
            get_string('formula_1', 'qtype_graphitoubb'),
            ['size' => 60]
        );
        $mform->setType('formula_1', PARAM_RAW);
        $mform->hideIf('formula_1', 'exercise_type', 'neq', 'equivalence');

        $mform->addElement(
            'text',
            'formula_2',
            get_string('formula_2', 'qtype_graphitoubb'),
            ['size' => 60]
        );
        $mform->setType('formula_2', PARAM_RAW);
        $mform->hideIf('formula_2', 'exercise_type', 'neq', 'equivalence');

        // ---- Equivalence: expected_equivalent ----
        $mform->addElement(
            'advcheckbox',
            'expected_equivalent',
            get_string('expected_equivalent', 'qtype_graphitoubb')
        );
        $mform->setDefault('expected_equivalent', 0);
        $mform->hideIf('expected_equivalent', 'exercise_type', 'neq', 'equivalence');

        // ---- Classify: expected_class ----
        $class_options = [
            'tautology'     => get_string('expected_class_tautology', 'qtype_graphitoubb'),
            'contradiction' => get_string('expected_class_contradiction', 'qtype_graphitoubb'),
            'contingency'   => get_string('expected_class_contingency', 'qtype_graphitoubb'),
        ];
        $mform->addElement(
            'select',
            'expected_class',
            get_string('expected_class', 'qtype_graphitoubb'),
            $class_options
        );
        $mform->setDefault('expected_class', 'tautology');
        $mform->hideIf('expected_class', 'exercise_type', 'neq', 'classify');

        // ---- Table justification (equivalence + classify) ----
        $mform->addElement(
            'advcheckbox',
            'require_table_justification',
            get_string('require_table_justification', 'qtype_graphitoubb')
        );
        $mform->setDefault('require_table_justification', 0);
        $mform->hideIf('require_table_justification', 'exercise_type', 'eq', 'complete');

        // ---- Scoring section (equivalence + classify) ----
        $mform->addElement('header', 'scoring_section', get_string('scoring_section', 'qtype_graphitoubb'));
        $mform->hideIf('scoring_section', 'exercise_type', 'eq', 'complete');

        $mform->addElement(
            'text',
            'radio_weight',
            get_string('radio_weight', 'qtype_graphitoubb'),
            ['size' => 5]
        );
        $mform->setType('radio_weight', PARAM_INT);
        $mform->setDefault('radio_weight', 50);
        $mform->hideIf('radio_weight', 'exercise_type', 'eq', 'complete');

        $mform->addElement(
            'text',
            'table_weight',
            get_string('table_weight', 'qtype_graphitoubb'),
            ['size' => 5]
        );
        $mform->setType('table_weight', PARAM_INT);
        $mform->setDefault('table_weight', 50);
        $mform->hideIf('table_weight', 'exercise_type', 'eq', 'complete');

        $policy_options = [
            'strict'       => get_string('wrong_radio_policy_strict', 'qtype_graphitoubb'),
            'proportional' => get_string('wrong_radio_policy_proportional', 'qtype_graphitoubb'),
        ];
        $mform->addElement(
            'select',
            'wrong_radio_policy',
            get_string('wrong_radio_policy', 'qtype_graphitoubb'),
            $policy_options
        );
        $mform->setDefault('wrong_radio_policy', 'strict');
        $mform->hideIf('wrong_radio_policy', 'exercise_type', 'eq', 'complete');
    }

    /**
     * Return the question type name.
     *
     * @return string
     */
    public function qtype(): string {
        return 'graphitoubb';
    }

    /**
     * Validate the submitted form data.
     *
     * - At least one formula must be present for the selected exercise type.
     * - Validates problem payload against JSON Schema via schema_loader.
     *
     * @param  array $data  Submitted form values.
     * @param  array $files Uploaded files (unused).
     * @return array        Associative array of field => error message. Empty on success.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $exercise_type = $data['exercise_type'] ?? 'complete';

        switch ($exercise_type) {
            case 'complete':
            case 'classify':
                if (empty(trim($data['formula'] ?? ''))) {
                    $errors['formula'] = get_string('err_missing_formula', 'qtype_graphitoubb');
                }
                break;

            case 'equivalence':
                if (empty(trim($data['formula_1'] ?? ''))) {
                    $errors['formula_1'] = get_string('err_missing_formula', 'qtype_graphitoubb');
                }
                if (empty(trim($data['formula_2'] ?? ''))) {
                    $errors['formula_2'] = get_string('err_missing_formula', 'qtype_graphitoubb');
                }
                break;
        }

        // Only run schema validation if no formula errors to avoid cascading noise.
        if (empty($errors)) {
            $problem = $this->build_problem_for_validation($data, $exercise_type);
            try {
                $loader = new schema_loader();
                $result = $loader->validate($problem, $exercise_type, 'problem');
                if (!$result->ok) {
                    $errors['formula'] = get_string(
                        'err_schema_validation',
                        'qtype_graphitoubb',
                        implode('; ', $result->errors)
                    );
                }
            } catch (\Throwable $e) {
                $errors['formula'] = get_string('err_internal', 'qtype_graphitoubb');
            }
        }

        return $errors;
    }

    /**
     * Pre-process question data when editing an existing question.
     *
     * Unpacks the stored DB fields back into individual form fields so they
     * appear pre-filled in the edit form.
     *
     * @param  object $question The question object with ->options.
     * @return object
     */
    protected function data_preprocessing($question): object {
        $question = parent::data_preprocessing($question);

        if (isset($question->options)) {
            $opts = $question->options;
            $question->tool          = $opts->tool ?? 'truth_table';
            $question->exercise_type = $opts->exercise_type ?? 'complete';

            // Decode problem_payload back to form fields.
            $payload = json_decode($opts->problem_payload ?? '{}', true);
            if (is_array($payload)) {
                $config = $payload['config'] ?? [];
                switch ($question->exercise_type) {
                    case 'complete':
                    case 'classify':
                        $question->formula         = $config['formula'] ?? '';
                        $question->expected_class  = $config['expected_class'] ?? 'tautology';
                        $question->require_table_justification = (int) ($config['require_table_justification'] ?? 0);
                        break;

                    case 'equivalence':
                        $question->formula_1              = $config['formula_1'] ?? '';
                        $question->formula_2              = $config['formula_2'] ?? '';
                        $question->expected_equivalent    = (int) ($config['expected_equivalent'] ?? 0);
                        $question->require_table_justification = (int) ($config['require_table_justification'] ?? 0);
                        break;
                }

                // Scoring fields.
                $scoring = $payload['scoring'] ?? [];
                $question->radio_weight       = (int) ($scoring['radio_weight'] ?? 50);
                $question->table_weight       = (int) ($scoring['table_weight'] ?? 50);
                $question->wrong_radio_policy = $scoring['wrong_radio_policy'] ?? 'strict';
            }
        }

        return $question;
    }

    // -------------------------------------------------------------------------
    // Private helpers.
    // -------------------------------------------------------------------------

    /**
     * Build a minimal problem array from form data for schema validation.
     *
     * @param  array  $data
     * @param  string $exercise_type
     * @return array
     */
    private function build_problem_for_validation(array $data, string $exercise_type): array {
        $problem = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => $exercise_type,
            'ui'             => [
                'intermediate_subformulas' => 'auto',
                'manual_subformulas'       => [],
                'row_order'                => 'canonical',
            ],
        ];

        switch ($exercise_type) {
            case 'complete':
                $problem['config'] = ['formula' => trim($data['formula'] ?? '')];
                break;
            case 'equivalence':
                $problem['config'] = [
                    'formula_1'                  => trim($data['formula_1'] ?? ''),
                    'formula_2'                  => trim($data['formula_2'] ?? ''),
                    'expected_equivalent'        => (bool) ($data['expected_equivalent'] ?? false),
                    'require_table_justification' => (bool) ($data['require_table_justification'] ?? false),
                ];
                $problem['scoring'] = [
                    'radio_weight'       => (int) ($data['radio_weight'] ?? 50),
                    'table_weight'       => (int) ($data['table_weight'] ?? 50),
                    'wrong_radio_policy' => $data['wrong_radio_policy'] ?? 'strict',
                ];
                break;
            case 'classify':
                $problem['config'] = [
                    'formula'                    => trim($data['formula'] ?? ''),
                    'expected_class'             => $data['expected_class'] ?? 'tautology',
                    'require_table_justification' => (bool) ($data['require_table_justification'] ?? false),
                ];
                $problem['scoring'] = [
                    'radio_weight'       => (int) ($data['radio_weight'] ?? 50),
                    'table_weight'       => (int) ($data['table_weight'] ?? 50),
                    'wrong_radio_policy' => $data['wrong_radio_policy'] ?? 'strict',
                ];
                break;
        }

        return $problem;
    }
}
