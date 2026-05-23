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
 * Test data generator for local_graphitoubb (iter1).
 *
 * Provides factory helpers that return valid problem payload arrays for use in
 * PHPUnit fixtures and Behat generators.
 *
 * @package    local_graphitoubb
 * @category   test
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generator for local_graphitoubb test data.
 *
 * Usage in PHPUnit:
 *   $gen = $this->getDataGenerator()->get_plugin_generator('local_graphitoubb');
 *   $payload = $gen->create_problem_payload('complete', ['formula' => 'A ∧ B']);
 *   $json = json_encode($payload);
 */
class local_graphitoubb_generator extends testing_data_generator {
    /**
     * Creates a valid problem payload array ready to JSON-encode.
     *
     * The returned array matches the JSON Schema in
     * local/graphitoubb/classes/tools/truth_table/schema/problem-{type}.v1.json.
     *
     * @param string $type Exercise type: complete|equivalence|classify.
     * @param array  $opts Override values for any payload field (flat or nested).
     *                     Recognised keys:
     *                       formula, formula_1, formula_2,
     *                       expected_equivalent, expected_class,
     *                       require_table_justification,
     *                       radio_weight, table_weight, wrong_radio_policy,
     *                       schema_version,
     *                       ui_intermediate_subformulas, manual_subformulas.
     * @return array Problem payload array (not JSON-encoded).
     * @throws \coding_exception When $type is not one of the three valid values.
     */
    public function create_problem_payload(string $type, array $opts = []): array {
        $validTypes = ['complete', 'equivalence', 'classify'];
        if (!in_array($type, $validTypes, true)) {
            throw new \coding_exception(
                sprintf(
                    'create_problem_payload(): type must be one of %s; got "%s".',
                    implode(', ', $validTypes),
                    $type
                )
            );
        }

        // Build type-specific config block.
        $config = [];
        switch ($type) {
            case 'complete':
                $config['formula'] = $opts['formula'] ?? 'A ∧ B';
                break;

            case 'equivalence':
                $config['formula_1']           = $opts['formula_1'] ?? 'A → B';
                $config['formula_2']           = $opts['formula_2'] ?? '¬A ∨ B';
                $config['expected_equivalent'] = array_key_exists('expected_equivalent', $opts)
                    ? (bool)$opts['expected_equivalent']
                    : true;
                $config['require_table_justification'] =
                    (bool)($opts['require_table_justification'] ?? false);
                break;

            case 'classify':
                $config['formula']        = $opts['formula'] ?? 'A ∨ ¬A';
                $config['expected_class'] = $opts['expected_class'] ?? 'tautology';
                $config['require_table_justification'] =
                    (bool)($opts['require_table_justification'] ?? false);
                break;
        }

        // Common payload structure.
        $payload = [
            'tool'           => 'truth_table',
            'schema_version' => (int)($opts['schema_version'] ?? 1),
            'type'           => $type,
            'config'         => $config,
            'ui'             => [
                'intermediate_subformulas' => $opts['ui_intermediate_subformulas'] ?? 'auto',
                'manual_subformulas'       => $opts['manual_subformulas'] ?? [],
                'row_order'                => 'canonical',
            ],
        ];

        // Scoring block — only for equivalence and classify.
        if (in_array($type, ['equivalence', 'classify'], true)) {
            $payload['scoring'] = [
                'radio_weight'       => (int)($opts['radio_weight'] ?? 100),
                'table_weight'       => (int)($opts['table_weight'] ?? 0),
                'wrong_radio_policy' => $opts['wrong_radio_policy'] ?? 'strict',
            ];
        }

        return $payload;
    }

    /**
     * Shorthand: creates a JSON-encoded problem payload string.
     *
     * @param string $type Exercise type: complete|equivalence|classify.
     * @param array  $opts Override values (same as create_problem_payload).
     * @return string JSON string suitable for direct insertion into the DB.
     */
    public function create_problem_json(string $type, array $opts = []): string {
        return json_encode(
            $this->create_problem_payload($type, $opts),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
