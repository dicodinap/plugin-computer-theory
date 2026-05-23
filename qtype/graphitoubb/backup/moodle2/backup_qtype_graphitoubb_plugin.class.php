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
 * Backup plugin class for qtype_graphitoubb.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provides the information needed to backup one graphitoubb question.
 *
 * Registers qtype_graphitoubb_options so Moodle's backup engine can
 * serialise the problem_payload and scoring/ui config alongside the question.
 */
class backup_qtype_graphitoubb_plugin extends backup_qtype_plugin {
    /**
     * Returns the qtype information to attach to the question element.
     *
     * @return backup_plugin_element
     */
    protected function define_question_plugin_structure(): backup_plugin_element {
        // The first element to backup is the options row.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'graphitoubb');

        // Create the graphitoubb_options element mapped to the DB table.
        $options = new backup_nested_element(
            'graphitoubb',
            ['id'],
            [
                'tool',
                'exercise_type',
                'problem_payload',
                'scoring_config',
                'ui_config',
                'payload_hash',
                'schema_version',
            ]
        );

        // Annotate the source table.
        $options->set_source_table(
            'qtype_graphitoubb_options',
            ['questionid' => backup::VAR_PARENTID]
        );

        $plugin->add_child($options);

        return $plugin;
    }
}
