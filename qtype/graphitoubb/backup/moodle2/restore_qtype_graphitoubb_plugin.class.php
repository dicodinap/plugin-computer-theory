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
 * Restore plugin class for qtype_graphitoubb.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provides the information needed to restore one graphitoubb question.
 *
 * Reads the backup XML element created by backup_qtype_graphitoubb_plugin
 * and inserts the restored row into qtype_graphitoubb_options.
 */
class restore_qtype_graphitoubb_plugin extends restore_qtype_plugin {
    /**
     * Returns the paths to be handled by the plugin during restore.
     *
     * @return restore_path_element[]
     */
    protected function define_question_plugin_structure(): array {
        return [
            new restore_path_element(
                'graphitoubb',
                $this->get_pathfor('/graphitoubb')
            ),
        ];
    }

    /**
     * Process a restored graphitoubb options element.
     *
     * Maps the backed-up data to the current question id and inserts
     * (or updates) a row in qtype_graphitoubb_options.
     *
     * @param  array|object $data The decoded backup element.
     * @return void
     */
    public function process_graphitoubb($data): void {
        global $DB;

        $data = (object) $data;

        // Replace the backup question id with the new restored question id.
        $data->questionid = $this->get_new_parentid('question');

        // Ensure defaults for fields that may be absent in older backups.
        $data->tool          = $data->tool ?? 'truth_table';
        $data->exercise_type  = $data->exercise_type ?? 'complete';
        $data->scoring_config = $data->scoring_config ?? '{}';
        $data->ui_config      = $data->ui_config ?? '{}';
        $data->schema_version = (int) ($data->schema_version ?? 1);

        // Upsert: a question should not have two options rows.
        $existing = $DB->get_record(
            'qtype_graphitoubb_options',
            ['questionid' => $data->questionid]
        );

        if ($existing) {
            $data->id = $existing->id;
            $DB->update_record('qtype_graphitoubb_options', $data);
        } else {
            $DB->insert_record('qtype_graphitoubb_options', $data);
        }
    }
}
