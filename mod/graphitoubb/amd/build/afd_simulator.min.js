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
 * Client-side DFA simulator. Mirrors simulator_service.php domain logic.
 *
 * v1: minimal — accepts/rejects + returns state trace as array.
 * Transition map key format: "stateId:symbol".
 *
 * @module     mod_graphitoubb/afd_simulator
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Run a DFA against an input string.
     *
     * @param {object} automaton {initialState: string, acceptStates: string[],
     *                            transitions: object<string, string>}
     * @param {string} input
     * @return {{accepted: boolean, trace: string[]}}
     */
    var run = function(automaton, input) {
        var current = automaton.initialState;
        var trace = [current];
        var chars = input.split('');

        for (var i = 0; i < chars.length; i++) {
            var key = current + ':' + chars[i];
            if (!Object.prototype.hasOwnProperty.call(automaton.transitions, key)) {
                return {accepted: false, trace: trace};
            }
            current = automaton.transitions[key];
            trace.push(current);
        }

        return {
            accepted: automaton.acceptStates.indexOf(current) !== -1,
            trace: trace,
        };
    };

    return {
        run: run,
    };
});
