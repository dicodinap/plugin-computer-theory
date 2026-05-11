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
 * AFD serializer — JSON encode/decode for automaton.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\afd\domain;

/**
 * Serializes and deserializes an automaton to/from JSON.
 * Adds schema_version field (R-4) for forward-compatible migration.
 */
final class serializer {
    /** Current JSON schema version. */
    private const SCHEMA_VERSION = 1;

    /** Required top-level keys in the JSON payload. */
    private const REQUIRED_KEYS = ['states', 'alphabet', 'transitions', 'start', 'finals'];

    /**
     * Serialize an automaton to a JSON string.
     *
     * @param  automaton $a
     * @return string    JSON representation.
     */
    public function serialize(automaton $a): string {
        $states = [];
        foreach ($a->get_states() as $s) {
            $states[] = ['id' => $s->get_id(), 'label' => $s->get_label()];
        }

        $transitions = [];
        foreach ($a->get_transitions() as $t) {
            $transitions[] = [
                'from'   => $t->get_from(),
                'symbol' => $t->get_symbol(),
                'to'     => $t->get_to(),
            ];
        }

        $payload = [
            'schema_version' => self::SCHEMA_VERSION,
            'states'         => $states,
            'alphabet'       => $a->get_alphabet(),
            'transitions'    => $transitions,
            'start'          => $a->get_start(),
            'finals'         => $a->get_finals(),
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * Deserialize a JSON string back to an automaton.
     *
     * @param  string    $json
     * @return automaton
     * @throws \InvalidArgumentException On malformed JSON or missing required fields.
     */
    public function deserialize(string $json): automaton {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Invalid JSON: ' . $e->getMessage(), 0, $e);
        }

        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException('Missing required field: "' . $key . '".');
            }
        }

        $states = [];
        foreach ($data['states'] as $sdata) {
            $states[] = new state($sdata['id'], $sdata['label'] ?? '');
        }

        $transitions = [];
        foreach ($data['transitions'] as $tdata) {
            $transitions[] = new transition($tdata['from'], $tdata['symbol'], $tdata['to']);
        }

        return new automaton(
            $states,
            $data['alphabet'],
            $transitions,
            $data['start'],
            $data['finals']
        );
    }
}
