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
 * PHPUnit tests for qtype_graphitoubb question type class.
 *
 * Tests the save/load/export/import/delete lifecycle of the question type.
 *
 * @package    qtype_graphitoubb
 * @covers     qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qtype_graphitoubb_test extends \advanced_testcase {
    /** @var \question_type */
    private \question_type $qtype;

    /** @var int A valid question.id to use as FK. */
    private int $questionid;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();

        // qformat_xml is needed by test_export_import_roundtrip — load it once for the suite.
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat  = $qgen->create_question_category();
        // Use shortanswer as a stand-in to get a valid question.id without triggering qtype logic.
        $q                = $qgen->create_question('shortanswer', null, ['category' => $cat->id]);
        $this->questionid = (int) $q->id;
        $this->qtype      = \question_bank::get_qtype('graphitoubb');
    }

    // -------------------------------------------------------------------------
    // save_question_options.
    // -------------------------------------------------------------------------

    /**
     * Saving question options with a valid complete-type formula persists a row
     * in qtype_graphitoubb_options with the expected payload and hash.
     */
    public function test_save_question_options_persists_payload(): void {
        global $DB;

        $formdata = $this->make_complete_formdata($this->questionid, 'A ∧ B');
        $this->qtype->save_question_options($formdata);

        $row = $DB->get_record('qtype_graphitoubb_options', ['questionid' => $this->questionid]);
        $this->assertNotFalse($row, 'Options row should be created.');
        $this->assertSame('truth_table', $row->tool);
        $this->assertSame('complete', $row->exercise_type);
        $this->assertNotEmpty($row->problem_payload);
        $this->assertNotEmpty($row->payload_hash);
        $this->assertSame(64, strlen($row->payload_hash));
        $this->assertSame(1, (int) $row->schema_version);

        $payload = json_decode($row->problem_payload, true);
        $this->assertSame('A ∧ B', $payload['config']['formula'] ?? '');
    }

    // -------------------------------------------------------------------------
    // get_question_options.
    // -------------------------------------------------------------------------

    /**
     * Loading options after a save round-trips the problem_payload intact.
     */
    public function test_get_question_options_loads_payload(): void {
        $formdata = $this->make_complete_formdata($this->questionid, 'A ∨ B');
        $this->qtype->save_question_options($formdata);

        $question     = (object) ['id' => $this->questionid];
        $this->qtype->get_question_options($question);

        $this->assertObjectHasProperty('options', $question);
        $opts = $question->options;
        $this->assertSame('truth_table', $opts->tool);
        $payload = json_decode($opts->problem_payload, true);
        $this->assertSame('A ∨ B', $payload['config']['formula'] ?? '');
    }

    // -------------------------------------------------------------------------
    // export_to_xml / import_from_xml.
    // -------------------------------------------------------------------------

    /**
     * Exporting a question to XML and importing it back produces an equivalent
     * problem_payload and the correct schema_version.
     */
    public function test_export_import_roundtrip(): void {
        $formdata = $this->make_complete_formdata($this->questionid, 'A → B');
        $this->qtype->save_question_options($formdata);

        // Load the question for export.
        $question = \question_bank::load_question($this->questionid);

        // Export to XML string.
        $format = new qformat_xml();
        $xml_fragment = $this->qtype->export_to_xml($question, $format);

        $this->assertStringContainsString('<tool>truth_table</tool>', $xml_fragment);
        $this->assertStringContainsString('<exercise_type>complete</exercise_type>', $xml_fragment);
        $this->assertStringContainsString('A → B', $xml_fragment);

        // Simulate the data structure the XML parser would produce.
        $data = [
            '#' => [
                'tool'            => [['#' => 'truth_table']],
                'exercise_type'   => [['#' => 'complete']],
                'schema_version'  => [['#' => '1']],
                'problem_payload' => [['#' => json_encode([
                    'tool'           => 'truth_table',
                    'schema_version' => 1,
                    'type'           => 'complete',
                    'config'         => ['formula' => 'A → B'],
                    'ui'             => [
                        'intermediate_subformulas' => 'auto',
                        'manual_subformulas'       => [],
                        'row_order'                => 'canonical',
                    ],
                ])]],
                'scoring_config'  => [['#' => '{}']],
                'ui_config'       => [['#' => '{}']],
            ],
        ];

        $imported = $this->qtype->import_from_xml($data, null, $format);
        $this->assertNotFalse($imported, 'Import should succeed for a valid payload.');
        $this->assertSame('graphitoubb', $imported->qtype);
        $this->assertSame('complete', $imported->exercise_type);
        $this->assertSame(64, strlen($imported->payload_hash ?? ''));
    }

    // -------------------------------------------------------------------------
    // delete_question.
    // -------------------------------------------------------------------------

    /**
     * Deleting a question removes its row from qtype_graphitoubb_options.
     */
    public function test_delete_question_removes_options(): void {
        global $DB;

        $formdata = $this->make_complete_formdata($this->questionid, 'A ∧ B');
        $this->qtype->save_question_options($formdata);

        $this->assertTrue(
            $DB->record_exists('qtype_graphitoubb_options', ['questionid' => $this->questionid])
        );

        $syscontext = \context_system::instance();
        $this->qtype->delete_question($this->questionid, (int) $syscontext->id);

        $this->assertFalse(
            $DB->record_exists('qtype_graphitoubb_options', ['questionid' => $this->questionid]),
            'Options row should be deleted with the question.'
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers.
    // -------------------------------------------------------------------------

    /**
     * Build a complete-type form data stdClass for save_question_options().
     *
     * @param  int    $questionid
     * @param  string $formula
     * @return object
     */
    private function make_complete_formdata(int $questionid, string $formula): object {
        return (object) [
            'id'            => $questionid,
            'exercise_type' => 'complete',
            'formula'       => $formula,
        ];
    }
}
