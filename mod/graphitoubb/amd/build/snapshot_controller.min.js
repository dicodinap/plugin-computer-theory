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
 * D-B client authority: decides significance, debounces saves (300 ms).
 *
 * Client is the authority for deciding whether a change is significant enough
 * to persist (D-B decision). Server persists without re-validating content.
 *
 * @module     mod_graphitoubb/snapshot_controller
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_graphitoubb/repository', 'core/notification'], function(Repository, Notification) {

    var debounceTimer = null;
    var DEBOUNCE_MS = 300;
    var lastSavedState = null;

    /**
     * Returns true if the new state differs from the last saved state.
     * Significant = any change to states, transitions, or alphabet.
     *
     * @param {object} state
     * @return {boolean}
     */
    var isSignificant = function(state) {
        if (!lastSavedState) {
            return true;
        }
        return JSON.stringify(state.states) !== JSON.stringify(lastSavedState.states) ||
               JSON.stringify(state.transitions) !== JSON.stringify(lastSavedState.transitions) ||
               JSON.stringify(state.alphabet) !== JSON.stringify(lastSavedState.alphabet);
    };

    /**
     * Called on each editor change. Debounces and conditionally saves.
     *
     * @param {number} attemptid
     * @param {object} state Current automaton state {states, transitions, alphabet}.
     */
    var onchange = function(attemptid, state) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            if (!isSignificant(state)) {
                return;
            }
            Repository.saveSnapshot(attemptid, state)
                .then(function() {
                    lastSavedState = state;
                    return;
                })
                .catch(Notification.exception);
        }, DEBOUNCE_MS);
    };

    return {
        onchange: onchange,
    };
});
