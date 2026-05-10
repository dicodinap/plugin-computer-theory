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
 * AFD tool — implements tool_interface for deterministic finite automata.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\afd;

use local_graphitoubb\tool_descriptor;
use local_graphitoubb\tool_interface;
use local_graphitoubb\tools\afd\domain\validator;
use local_graphitoubb\validation_result;

/**
 * Entry point for the AFD tool; registered with tool_registry via bootstrap.
 */
final class afd_tool implements tool_interface {
    /**
     * Return the descriptor for this tool.
     *
     * @return tool_descriptor
     */
    public static function descriptor(): tool_descriptor {
        return new tool_descriptor(
            'afd',
            'Deterministic Finite Automaton',
            '1.0.0',
            ['edit', 'simulate', 'snapshot', 'wordbank']
        );
    }

    /**
     * Validate a canonical AFD payload against D-A bounds.
     *
     * @param  array $payload
     * @return validation_result
     */
    public function validate(array $payload): validation_result {
        $errors = [];

        foreach (['states', 'alphabet', 'transitions'] as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[] = 'missing_field: ' . $key;
            }
        }

        if ($errors) {
            return validation_result::fail($errors);
        }

        if (count($payload['states']) > validator::MAX_STATES) {
            $errors[] = 'max_states: ' . count($payload['states']) . ' > ' . validator::MAX_STATES;
        }
        if (count($payload['alphabet']) > validator::MAX_ALPHABET) {
            $errors[] = 'max_alphabet: ' . count($payload['alphabet']) . ' > ' . validator::MAX_ALPHABET;
        }
        if (count($payload['transitions']) > validator::MAX_TRANSITIONS) {
            $errors[] = 'max_transitions: ' . count($payload['transitions']) . ' > ' . validator::MAX_TRANSITIONS;
        }

        foreach ($payload['states'] as $s) {
            $label = $s['label'] ?? ($s['id'] ?? '');
            if (strlen($label) > validator::MAX_LABEL_LENGTH) {
                $errors[] = 'max_label_length: state "' . ($s['id'] ?? '') . '" label too long';
            }
        }

        return $errors ? validation_result::fail($errors) : validation_result::pass();
    }

    /**
     * Normalise a canonical AFD array into the persistence-ready shape.
     *
     * @param  array $automaton
     * @return array
     */
    public function serialize(array $automaton): array {
        return [
            'schema_version' => 1,
            'states'         => $automaton['states'] ?? [],
            'alphabet'       => $automaton['alphabet'] ?? [],
            'transitions'    => $automaton['transitions'] ?? [],
            'start'          => $automaton['start'] ?? null,
            'finals'         => $automaton['finals'] ?? [],
        ];
    }

    /**
     * Return Mustache template name and render context for the AFD editor.
     *
     * @return array{template: string, context: array}
     */
    public function render_editor(): array {
        return [
            'template' => 'mod_graphitoubb/editor',
            'context'  => [],
        ];
    }
}
