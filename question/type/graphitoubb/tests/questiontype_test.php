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
 * Tests for qtype_graphitoubb question type class.
 *
 * @package    qtype_graphitoubb
 * @coversNothing
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class questiontype_test extends advanced_testcase {
    /** @var \question_type */
    private \question_type $qtype;
    /** @var int */
    private int $questionid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat  = $qgen->create_question_category();
        // Use shortanswer to get a valid question.id FK reference without triggering our qtype logic.
        $q = $qgen->create_question('shortanswer', null, ['category' => $cat->id]);
        $this->questionid = (int) $q->id;
        $this->qtype = \question_bank::get_qtype('graphitoubb');
    }

    public function test_name_returns_graphitoubb(): void {
        $this->assertSame('graphitoubb', $this->qtype->name());
    }

    public function test_save_options_creates_row_when_missing(): void {
        global $DB;
        $formdata = (object) ['id' => $this->questionid, 'automaton_payload' => '{"states":[]}'];
        $this->qtype->save_question_options($formdata);
        $this->assertTrue($DB->record_exists('qtype_graphitoubb_options', ['questionid' => $this->questionid]));
    }

    public function test_save_and_get_options_roundtrip(): void {
        $payload  = '{"states":["q0"]}';
        $formdata = (object) ['id' => $this->questionid, 'automaton_payload' => $payload];
        $this->qtype->save_question_options($formdata);

        $question = (object) ['id' => $this->questionid];
        $this->qtype->get_question_options($question);
        $this->assertSame($payload, $question->options->automaton_payload);
    }

    public function test_delete_question_removes_options_row(): void {
        global $DB;
        $formdata = (object) ['id' => $this->questionid, 'automaton_payload' => '{}'];
        $this->qtype->save_question_options($formdata);

        $syscontext = \context_system::instance();
        $this->qtype->delete_question($this->questionid, (int) $syscontext->id);
        $this->assertFalse($DB->record_exists('qtype_graphitoubb_options', ['questionid' => $this->questionid]));
    }
}
