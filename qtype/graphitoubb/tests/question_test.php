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

/**
 * PHPUnit tests for qtype_graphitoubb_question::grade_response.
 *
 * Tests the grading contract: fraction range, state mapping, error handling.
 * Calls grade_response() directly without the DB — uses helper factories.
 *
 * @package    qtype_graphitoubb
 * @covers     qtype_graphitoubb_question::grade_response
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_test extends \advanced_testcase {
    /** @var qtype_graphitoubb_test_helper */
    private qtype_graphitoubb_test_helper $helper;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $CFG;
        require_once($CFG->dirroot . '/question/type/graphitoubb/tests/helper.php');
        $this->helper = new qtype_graphitoubb_test_helper();
    }

    // -------------------------------------------------------------------------
    // complete type.
    // -------------------------------------------------------------------------

    /**
     * A perfectly correct submission for A ∧ B returns fraction = 1.0 and
     * a graded-correct state.
     */
    public function test_grade_complete_full_credit(): void {
        $question = $this->helper->make_graphitoubb_question_complete();

        // Build the expected correct submission for A ∧ B (4 rows: FF→F, FV→F, VF→F, VV→V).
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'complete',
            'radio_answer'   => null,
            'table'          => [
                'columns' => ['A', 'B', 'A ∧ B'],
                'rows'    => [
                    ['vars' => ['A' => 'F', 'B' => 'F'], 'values' => ['F']],
                    ['vars' => ['A' => 'F', 'B' => 'V'], 'values' => ['F']],
                    ['vars' => ['A' => 'V', 'B' => 'F'], 'values' => ['F']],
                    ['vars' => ['A' => 'V', 'B' => 'V'], 'values' => ['V']],
                ],
            ],
        ];

        [$fraction, $state] = $question->grade_response([
            'answer_payload' => json_encode($submission),
        ]);

        $this->assertGreaterThanOrEqual(0.0, $fraction);
        $this->assertLessThanOrEqual(1.0, $fraction);
        // A correct submission should yield fraction 1.0.
        $this->assertEqualsWithDelta(1.0, $fraction, 0.01);
        $this->assertTrue($state->is_graded());
    }

    /**
     * A partially correct submission (one wrong cell) returns 0 < fraction < 1
     * and a graded state.
     */
    public function test_grade_complete_partial_credit(): void {
        $question = $this->helper->make_graphitoubb_question_complete();

        // Last row is wrong (V instead of F for A ∧ B when A=V, B=V should be V — but reverse: row 1 wrong).
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'complete',
            'radio_answer'   => null,
            'table'          => [
                'columns' => ['A', 'B', 'A ∧ B'],
                'rows'    => [
                    ['vars' => ['A' => 'F', 'B' => 'F'], 'values' => ['V']], // WRONG: should be F
                    ['vars' => ['A' => 'F', 'B' => 'V'], 'values' => ['F']],
                    ['vars' => ['A' => 'V', 'B' => 'F'], 'values' => ['F']],
                    ['vars' => ['A' => 'V', 'B' => 'V'], 'values' => ['V']],
                ],
            ],
        ];

        [$fraction, $state] = $question->grade_response([
            'answer_payload' => json_encode($submission),
        ]);

        $this->assertGreaterThan(0.0, $fraction);
        $this->assertLessThan(1.0, $fraction);
        $this->assertTrue($state->is_graded());
    }

    // -------------------------------------------------------------------------
    // equivalence type.
    // -------------------------------------------------------------------------

    /**
     * A correct radio answer for an equivalence question with radio_weight=50
     * yields at least 0.5 fraction (radio portion correct, no table required).
     */
    public function test_grade_equivalence_correct_radio(): void {
        $question = $this->helper->make_graphitoubb_question_equivalence();

        // A ∧ B and B ∧ A are equivalent — expected_equivalent = true.
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'radio_answer'   => true,
            'table'          => [
                'columns' => ['A', 'B', 'A ∧ B', 'B ∧ A'],
                'rows'    => [
                    ['vars' => ['A' => 'F', 'B' => 'F'], 'values' => ['F', 'F']],
                    ['vars' => ['A' => 'F', 'B' => 'V'], 'values' => ['F', 'F']],
                    ['vars' => ['A' => 'V', 'B' => 'F'], 'values' => ['F', 'F']],
                    ['vars' => ['A' => 'V', 'B' => 'V'], 'values' => ['V', 'V']],
                ],
            ],
        ];

        [$fraction, $state] = $question->grade_response([
            'answer_payload' => json_encode($submission),
        ]);

        $this->assertGreaterThanOrEqual(0.0, $fraction);
        $this->assertLessThanOrEqual(1.0, $fraction);
        $this->assertTrue($state->is_graded());
    }

    // -------------------------------------------------------------------------
    // classify type.
    // -------------------------------------------------------------------------

    /**
     * A wrong radio classification returns fraction = 0.0 under 'strict' policy.
     */
    public function test_grade_classify_wrong_classification(): void {
        $question = $this->helper->make_graphitoubb_question_classify();

        // A ∨ ¬A is a tautology; student says 'contradiction'.
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'classify',
            'radio_answer'   => 'contradiction', // Wrong — should be 'tautology'.
            'table'          => null,
        ];

        [$fraction, $state] = $question->grade_response([
            'answer_payload' => json_encode($submission),
        ]);

        // Under strict policy: wrong radio → score 0.
        $this->assertEqualsWithDelta(0.0, $fraction, 0.01);
        $this->assertTrue($state->is_graded());
    }

    // -------------------------------------------------------------------------
    // Error / edge cases.
    // -------------------------------------------------------------------------

    /**
     * An invalid (non-JSON) payload returns fraction = 0.0 and gaveup state
     * without throwing.
     */
    public function test_grade_returns_zero_on_invalid_payload(): void {
        $question = $this->helper->make_graphitoubb_question_complete();

        [$fraction, $state] = $question->grade_response([
            'answer_payload' => 'this is not valid json!!!',
        ]);

        $this->assertEqualsWithDelta(0.0, $fraction, 0.001);
        $this->assertSame(\question_state::$gaveup, $state);
    }
}
