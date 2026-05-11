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
 * Question type class for qtype_graphitoubb.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The graphitoubb question type embeds the AFD editor inside a Moodle question.
 */
class qtype_graphitoubb extends question_type {
    /**
     * Returns the question type name.
     *
     * @return string
     */
    public function name() {
        return 'graphitoubb';
    }

    /**
     * Loads the question options from DB into $question->options.
     *
     * @param object $question The question object, to be modified in-place.
     * @return bool
     */
    public function get_question_options($question) {
        global $DB;
        $question->options = $DB->get_record('qtype_graphitoubb_options', ['questionid' => $question->id]);
        return parent::get_question_options($question);
    }

    /**
     * Saves question-type specific options to the database.
     *
     * @param object $formdata The submitted form data.
     * @return void
     */
    public function save_question_options($formdata) {
        global $DB;
        $existing = $DB->get_record('qtype_graphitoubb_options', ['questionid' => $formdata->id]);
        $record = (object) [
            'questionid'        => $formdata->id,
            'automaton_payload' => $formdata->automaton_payload ?? '',
            'schema_version'    => 1,
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('qtype_graphitoubb_options', $record);
        } else {
            $DB->insert_record('qtype_graphitoubb_options', $record);
        }
    }

    /**
     * Deletes question-type specific data when a question is deleted.
     *
     * @param int $questionid The id of the question being deleted.
     * @param int $contextid  The context id the question is in.
     * @return void
     */
    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('qtype_graphitoubb_options', ['questionid' => $questionid]);
        parent::delete_question($questionid, $contextid);
    }
}
