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
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * GraphitoUBB question editing form.
 */
class qtype_graphitoubb_edit_form extends question_edit_form {
    /**
     * Add the question-type specific form elements.
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    protected function definition_inner($mform) {
        $mform->addElement('textarea', 'automaton_payload', get_string('automaton_payload', 'qtype_graphitoubb'));
        $mform->setType('automaton_payload', PARAM_RAW);
    }

    /**
     * Returns the question type name.
     *
     * @return string
     */
    public function qtype() {
        return 'graphitoubb';
    }
}
