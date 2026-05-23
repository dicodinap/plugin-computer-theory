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
 * English language strings for qtype_graphitoubb.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname']          = 'GraphitoUBB Truth Table';
$string['pluginname_help']     = 'A question type where students fill in, compare, or classify truth tables for propositional logic formulas.';
$string['pluginnameadding']    = 'Adding a GraphitoUBB Truth Table question';
$string['pluginnameediting']   = 'Editing a GraphitoUBB Truth Table question';
$string['pluginnamesummary']   = 'Students complete, compare, or classify truth tables powered by the GraphitoUBB engine.';
$string['qtype_label']         = 'GraphitoUBB Truth Table';

// Exercise type.
$string['exercise_type']              = 'Exercise type';
$string['exercise_type_complete']     = 'Complete the table';
$string['exercise_type_equivalence']  = 'Equivalence (are the two formulas equivalent?)';
$string['exercise_type_classify']     = 'Classify the formula (tautology / contradiction / contingency)';

// Formula fields.
$string['formula']   = 'Formula';
$string['formula_1'] = 'Formula 1';
$string['formula_2'] = 'Formula 2';

// Equivalence fields.
$string['expected_equivalent'] = 'Are they equivalent? (expected answer)';

// Classification fields.
$string['expected_class']              = 'Expected classification';
$string['expected_class_tautology']    = 'Tautology';
$string['expected_class_contradiction'] = 'Contradiction';
$string['expected_class_contingency']  = 'Contingency';

// Table justification.
$string['require_table_justification'] = 'Require full table justification';

// Scoring section.
$string['scoring_section']             = 'Scoring';
$string['radio_weight']                = 'Radio answer weight (%)';
$string['table_weight']                = 'Table weight (%)';
$string['wrong_radio_policy']          = 'Policy when radio answer is wrong';
$string['wrong_radio_policy_strict']   = 'Strict: score = 0 if radio is wrong';
$string['wrong_radio_policy_proportional'] = 'Proportional: keep table partial credit';

// Feedback section.
$string['feedback_section'] = 'Answer feedback';

// Privacy.
$string['privacy:metadata'] = 'The GraphitoUBB Truth Table question type stores question definitions in qtype_graphitoubb_options. Student response data is stored by the Moodle question engine in question attempt step tables.';
$string['privacy:metadata:qtype_graphitoubb_options'] = 'Stores the truth_table problem payload and scoring configuration for each question. This table contains instructor-authored content only — no personal data.';

// Errors.
$string['err_missing_formula']    = 'You must provide at least one formula for this exercise type.';
$string['err_schema_validation']  = 'The problem payload failed schema validation: {$a}';
$string['err_internal']           = 'An internal error occurred while saving the question. Please try again.';
