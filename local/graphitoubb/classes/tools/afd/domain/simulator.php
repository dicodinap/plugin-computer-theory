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
 * AFD simulator — runs an input string through an automaton and returns a trace.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\afd\domain;

/**
 * Executes an automaton on an input string and records the execution trace.
 *
 * Symbols are single characters; the simulator stops immediately when no
 * transition exists for the current (state, symbol) pair (stuck = rejected).
 */
final class simulator {
    /**
     * Run the automaton on the given input and return the execution trace.
     *
     * @param  automaton $automaton
     * @param  string    $input     Characters to consume (one symbol = one char).
     * @return trace
     */
    public function run(automaton $automaton, string $input): trace {
        // Build a transition lookup: ['state:symbol' => 'target'].
        $delta = [];
        foreach ($automaton->get_transitions() as $t) {
            $delta[$t->get_from() . ':' . $t->get_symbol()] = $t->get_to();
        }

        $current = $automaton->get_start();
        $steps   = [];
        $symbols = $input === '' ? [] : str_split($input);

        foreach ($symbols as $symbol) {
            $key = $current . ':' . $symbol;
            if (!isset($delta[$key])) {
                // No transition — stuck, reject immediately.
                return new trace($steps, false);
            }
            $next    = $delta[$key];
            $steps[] = ['current' => $current, 'symbol' => $symbol, 'next' => $next];
            $current = $next;
        }

        $accepted = in_array($current, $automaton->get_finals(), true);
        return new trace($steps, $accepted);
    }
}
