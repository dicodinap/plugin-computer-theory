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
 * AFD validator — well-formedness and bounds checks.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\afd\domain;

/**
 * Validates an automaton against structural rules and D-A bounds.
 *
 * Returns an array of human-readable error strings; empty array means valid.
 */
final class validator {
    /** Maximum number of states allowed per automaton. */
    public const MAX_STATES = 64;

    /** Maximum alphabet size. */
    public const MAX_ALPHABET = 16;

    /** Maximum number of transitions. */
    public const MAX_TRANSITIONS = 512;

    /** Maximum input string length accepted by the simulator. */
    public const MAX_INPUT_LENGTH = 256;

    /** Maximum character length for a state label. */
    public const MAX_LABEL_LENGTH = 32;

    /**
     * Validate an automaton and return a list of error messages.
     *
     * @param  automaton $a
     * @return string[]  Error messages; empty = valid.
     */
    public function validate(automaton $a): array {
        $errors = [];

        $states      = $a->get_states();
        $alphabet    = $a->get_alphabet();
        $transitions = $a->get_transitions();
        $start       = $a->get_start();
        $finals      = $a->get_finals();

        // Build index of valid state ids for O(1) lookups.
        $stateids = [];
        foreach ($states as $s) {
            $stateids[$s->get_id()] = true;
        }

        // Bounds checks.
        if (count($states) > self::MAX_STATES) {
            $errors[] = 'Too many states: limit is ' . self::MAX_STATES . '.';
        }
        if (count($alphabet) > self::MAX_ALPHABET) {
            $errors[] = 'Too many alphabet symbols: limit is ' . self::MAX_ALPHABET . '.';
        }
        if (count($transitions) > self::MAX_TRANSITIONS) {
            $errors[] = 'Too many transitions: limit is ' . self::MAX_TRANSITIONS . '.';
        }

        // Label length check.
        foreach ($states as $s) {
            if (strlen($s->get_label()) > self::MAX_LABEL_LENGTH) {
                $errors[] = 'State "' . $s->get_id() . '" label exceeds MAX_LABEL_LENGTH (' . self::MAX_LABEL_LENGTH . ').';
            }
        }

        // Start state must exist.
        if (!isset($stateids[$start])) {
            $errors[] = 'Invalid start state "' . $start . '": not defined in states.';
        }

        // Final states must all exist.
        foreach ($finals as $f) {
            if (!isset($stateids[$f])) {
                $errors[] = 'Invalid final state "' . $f . '": not defined in states.';
            }
        }

        // Alphabet index for O(1) symbol lookups.
        $alphaindex = array_flip($alphabet);

        // Transition well-formedness and determinism check.
        $seen = [];
        foreach ($transitions as $t) {
            $from   = $t->get_from();
            $symbol = $t->get_symbol();
            $to     = $t->get_to();

            if (!isset($stateids[$from])) {
                $errors[] = 'Transition source "' . $from . '" is not defined in states.';
            }
            if (!isset($stateids[$to])) {
                $errors[] = 'Transition target "' . $to . '" is not defined in states.';
            }
            if (!isset($alphaindex[$symbol])) {
                $errors[] = 'Transition symbol "' . $symbol . '" is not in the alphabet.';
            }

            $key = $from . ':' . $symbol;
            if (isset($seen[$key])) {
                $errors[] = 'Nondeterministic: duplicate transition for ("' . $from . '", "' . $symbol . '").';
            }
            $seen[$key] = true;
        }

        return $errors;
    }
}
