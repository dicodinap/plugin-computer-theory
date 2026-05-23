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
 * Test helper for qtype_graphitoubb.
 *
 * Provides factory methods for fully-hydrated qtype_graphitoubb_question instances
 * used across PHPUnit test suites.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/graphitoubb/question.php');

/**
 * Test helper — factory for qtype_graphitoubb_question instances.
 *
 * Usage in tests:
 *   $helper   = new qtype_graphitoubb_test_helper();
 *   $question = $helper->make_graphitoubb_question_complete();
 */
class qtype_graphitoubb_test_helper extends question_test_helper {
    /**
     * Returns the list of named question variants provided by this helper.
     *
     * @return string[]
     */
    public function get_test_questions(): array {
        return ['complete', 'equivalence', 'classify'];
    }

    /**
     * Build a fully-hydrated complete-type question (fill in the truth table).
     *
     * Formula: A ∧ B (2 variables, 4 rows, single result column).
     *
     * @return qtype_graphitoubb_question
     */
    public function make_graphitoubb_question_complete(): qtype_graphitoubb_question {
        $q = self::make_question_base();

        $q->tool          = 'truth_table';
        $q->exercise_type = 'complete';
        $q->schema_version = 1;

        $q->problem_payload = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'complete',
            'config'         => ['formula' => 'A ∧ B'],
            'ui'             => [
                'intermediate_subformulas' => 'auto',
                'manual_subformulas'       => [],
                'row_order'                => 'canonical',
            ],
        ];

        $q->scoring_config = [];
        $q->ui_config      = [
            'intermediate_subformulas' => 'auto',
            'row_order'                => 'canonical',
        ];

        $q->payload_hash = hash('sha256', json_encode($q->problem_payload, JSON_UNESCAPED_UNICODE));

        return $q;
    }

    /**
     * Build a fully-hydrated equivalence-type question.
     *
     * Formulas: A ∧ B and B ∧ A (equivalent pair).
     *
     * @return qtype_graphitoubb_question
     */
    public function make_graphitoubb_question_equivalence(): qtype_graphitoubb_question {
        $q = self::make_question_base();

        $q->tool          = 'truth_table';
        $q->exercise_type = 'equivalence';
        $q->schema_version = 1;

        $q->problem_payload = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'config'         => [
                'formula_1'                  => 'A ∧ B',
                'formula_2'                  => 'B ∧ A',
                'expected_equivalent'        => true,
                'require_table_justification' => false,
            ],
            'scoring' => [
                'radio_weight'       => 50,
                'table_weight'       => 50,
                'wrong_radio_policy' => 'strict',
            ],
            'ui' => [
                'intermediate_subformulas' => 'auto',
                'manual_subformulas'       => [],
                'row_order'                => 'canonical',
            ],
        ];

        $q->scoring_config = [
            'radio_weight'       => 50,
            'table_weight'       => 50,
            'wrong_radio_policy' => 'strict',
        ];

        $q->ui_config = [
            'intermediate_subformulas' => 'auto',
            'row_order'                => 'canonical',
        ];

        $q->payload_hash = hash('sha256', json_encode($q->problem_payload, JSON_UNESCAPED_UNICODE));

        return $q;
    }

    /**
     * Build a fully-hydrated classify-type question.
     *
     * Formula: A ∨ ¬A (tautology).
     *
     * @return qtype_graphitoubb_question
     */
    public function make_graphitoubb_question_classify(): qtype_graphitoubb_question {
        $q = self::make_question_base();

        $q->tool          = 'truth_table';
        $q->exercise_type = 'classify';
        $q->schema_version = 1;

        $q->problem_payload = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'classify',
            'config'         => [
                'formula'                    => 'A ∨ ¬A',
                'expected_class'             => 'tautology',
                'require_table_justification' => false,
            ],
            'scoring' => [
                'radio_weight'       => 70,
                'table_weight'       => 30,
                'wrong_radio_policy' => 'strict',
            ],
            'ui' => [
                'intermediate_subformulas' => 'auto',
                'manual_subformulas'       => [],
                'row_order'                => 'canonical',
            ],
        ];

        $q->scoring_config = [
            'radio_weight'       => 70,
            'table_weight'       => 30,
            'wrong_radio_policy' => 'strict',
        ];

        $q->ui_config = [
            'intermediate_subformulas' => 'auto',
            'row_order'                => 'canonical',
        ];

        $q->payload_hash = hash('sha256', json_encode($q->problem_payload, JSON_UNESCAPED_UNICODE));

        return $q;
    }

    // -------------------------------------------------------------------------
    // Private helpers.
    // -------------------------------------------------------------------------

    /**
     * Build a bare qtype_graphitoubb_question with minimal required properties.
     *
     * @return qtype_graphitoubb_question
     */
    private static function make_question_base(): qtype_graphitoubb_question {
        question_bank::load_question_definition_classes('graphitoubb');

        $q = new qtype_graphitoubb_question();
        test_question_maker::initialise_a_question($q);
        $q->qtype = question_bank::get_qtype('graphitoubb');
        $q->name  = 'GraphitoUBB Test Question';
        $q->questiontext = 'Complete the truth table.';
        $q->generalfeedback = '';
        $q->penalty = 0;
        $q->defaultmark = 1;

        return $q;
    }
}
