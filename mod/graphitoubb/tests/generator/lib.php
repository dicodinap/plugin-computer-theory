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
 * Test data generator for mod_graphitoubb.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generates instances of mod_graphitoubb for unit and behat tests.
 */
class mod_graphitoubb_generator extends testing_module_generator {
    /**
     * Create a graphitoubb activity instance.
     *
     * Defaults added for iter1 fields: attempts_policy, attempts_max, close_behavior.
     *
     * @param array|stdClass|null $record Instance record overrides.
     * @param array|null $options Module generator options.
     * @return stdClass The created instance.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) ($record ?? []);

        if (!isset($record->intro)) {
            $record->intro = '';
        }
        if (!isset($record->introformat)) {
            $record->introformat = FORMAT_HTML;
        }

        // iter1 defaults — multi-attempt policy fields.
        if (!isset($record->attempts_policy)) {
            $record->attempts_policy = 'best';
        }
        if (!isset($record->close_behavior)) {
            $record->close_behavior = 'auto_submit';
        }
        // attempts_max is nullable — leave unset unless explicitly provided.

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Returns the methods exposed via Moodle's "the following X exist" Behat table syntax.
     *
     * Allows:
     *   Given the following "mod_graphitoubb > problems" exist:
     *     | activity | type     | formula |
     *     | My TT    | complete | A ∧ B   |
     *
     * @return array<string, string> Map of entity name → method name on this class.
     */
    public function get_create_methods(): array {
        return [
            'problems' => 'create_problem',
        ];
    }

    /**
     * Creates a graphitoubb_problem row for a named activity instance.
     *
     * Required fields in $record:
     *   - activity (string): instance name within the current course context.
     *   - type (string): complete|equivalence|classify.
     *
     * Optional fields (with defaults):
     *   - formula (string): for complete and classify types.
     *   - formula_1, formula_2 (string): for equivalence type.
     *   - expected_equivalent (bool|int): for equivalence type.
     *   - expected_class (string): tautology|contradiction|contingency for classify.
     *   - require_table_justification (bool): default false.
     *   - schema_version (int): default 1.
     *   - ui_intermediate_subformulas (string): auto|none|manual — default 'auto'.
     *
     * @param array|stdClass $record Problem record overrides (must include 'activity' and 'type').
     * @return stdClass The created graphitoubb_problem row.
     * @throws coding_exception When required fields are missing or type is invalid.
     */
    public function create_problem($record): \stdClass {
        global $DB;

        $record = (object) (array) $record;

        // Resolve activity instance ID from its name.
        if (empty($record->activity)) {
            throw new \coding_exception('create_problem() requires the "activity" field (instance name).');
        }
        $instance = $DB->get_record('graphitoubb', ['name' => $record->activity], 'id', MUST_EXIST);

        // Validate type.
        $validTypes = ['complete', 'equivalence', 'classify'];
        if (empty($record->type) || !in_array($record->type, $validTypes, true)) {
            throw new \coding_exception(
                sprintf('create_problem() "type" must be one of: %s.', implode(', ', $validTypes))
            );
        }

        // Build the problem config JSON payload based on type.
        $config = [];
        switch ($record->type) {
            case 'complete':
                $config['formula'] = $record->formula ?? 'A ∧ B';
                break;

            case 'equivalence':
                $config['formula_1']           = $record->formula_1 ?? 'A → B';
                $config['formula_2']           = $record->formula_2 ?? '¬A ∨ B';
                $config['expected_equivalent'] = isset($record->expected_equivalent)
                    ? (bool)(int)$record->expected_equivalent
                    : true;
                $config['require_table_justification'] = !empty($record->require_table_justification);
                break;

            case 'classify':
                $config['formula']         = $record->formula ?? 'A ∨ ¬A';
                $config['expected_class']  = $record->expected_class ?? 'tautology';
                $config['require_table_justification'] = !empty($record->require_table_justification);
                break;
        }

        // Build problem payload.
        $payload = [
            'tool'           => 'truth_table',
            'schema_version' => (int)($record->schema_version ?? 1),
            'type'           => $record->type,
            'config'         => $config,
            'ui'             => [
                'intermediate_subformulas' => $record->ui_intermediate_subformulas ?? 'auto',
                'manual_subformulas'       => [],
                'row_order'                => 'canonical',
            ],
        ];

        // Scoring block for equivalence/classify.
        if (in_array($record->type, ['equivalence', 'classify'], true)) {
            $payload['scoring'] = [
                'radio_weight'       => (int)($record->radio_weight ?? 100),
                'table_weight'       => (int)($record->table_weight ?? 0),
                'wrong_radio_policy' => $record->wrong_radio_policy ?? 'strict',
            ];
        }

        $problemRow = new \stdClass();
        $problemRow->instanceid    = $instance->id;
        $problemRow->tool          = 'truth_table';
        $problemRow->type          = $record->type;
        $problemRow->payload       = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $problemRow->timecreated   = time();
        $problemRow->timemodified  = time();

        $problemRow->id = $DB->insert_record('graphitoubb_problem', $problemRow);

        return $problemRow;
    }
}
