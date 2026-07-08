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
 * Instance settings form for mod_graphitoubb.
 *
 * Not declared strict_types — moodleform_mod inclusion chain requires loose typing.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity instance form — name + standard intro.
 */
class mod_graphitoubb_mod_form extends moodleform_mod {
    /**
     * Defines the form elements.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('graphitoubbname', 'mod_graphitoubb'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // RF_04 submission gate (D13): availability window + attempts policy.
        $mform->addElement('header', 'gatehdr', get_string('gate_header', 'mod_graphitoubb'));

        $mform->addElement('date_time_selector', 'timeopen', get_string('gate_timeopen', 'mod_graphitoubb'),
            ['optional' => true]);
        $mform->addHelpButton('timeopen', 'gate_timeopen', 'mod_graphitoubb');

        $mform->addElement('date_time_selector', 'timeclose', get_string('gate_timeclose', 'mod_graphitoubb'),
            ['optional' => true]);

        // Attempts allowed: 0 = unlimited (stored as NULL), 1..10 concrete.
        $attemptoptions = [0 => get_string('gate_attempts_unlimited', 'mod_graphitoubb')];
        for ($i = 1; $i <= 10; $i++) {
            $attemptoptions[$i] = (string) $i;
        }
        $mform->addElement('select', 'attempts_max', get_string('gate_attempts_max', 'mod_graphitoubb'), $attemptoptions);
        $mform->setDefault('attempts_max', 1);
        $mform->addHelpButton('attempts_max', 'gate_attempts_max', 'mod_graphitoubb');

        $mform->addElement('select', 'attempts_policy', get_string('gate_attempts_policy', 'mod_graphitoubb'), [
            'best'    => get_string('gate_policy_best', 'mod_graphitoubb'),
            'last'    => get_string('gate_policy_last', 'mod_graphitoubb'),
            'average' => get_string('gate_policy_average', 'mod_graphitoubb'),
        ]);
        $mform->setDefault('attempts_policy', 'best');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Normalise form data before it is persisted: 0 attempts_max ⇒ NULL (unlimited).
     *
     * @param  \stdClass $data Submitted form data.
     * @return void
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (isset($data->attempts_max) && (int) $data->attempts_max === 0) {
            $data->attempts_max = null;
        }
    }
}
