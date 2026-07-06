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
 * Dispatches graphitoubb:snapshot-status CustomEvent on the registered target
 * with detail.status = 'saving' | 'saved' | 'error'.
 *
 * @module     mod_graphitoubb/snapshot_controller
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_graphitoubb/repository', 'core/notification'], function(Repository, Notification) {

    var debounceTimer = null;
    var DEBOUNCE_MS = 300;
    var lastSavedState = null;
    var _target = null;

    var dispatchStatus = function(status) {
        if (!_target) {
            return;
        }
        _target.dispatchEvent(new CustomEvent('graphitoubb:snapshot-status', {
            bubbles: true,
            detail: {status: status},
        }));
    };

    /**
     * Returns true if the new state differs from the last saved state.
     *
     * AFD path (unchanged): significant = any change to states, transitions or
     * alphabet. Generic path (grafo/arbol answer envelopes — no AFD triad): diff
     * the whole payload (nodes/edges/answer_kind/value/edges/values). Additive
     * branch; the AFD behaviour is preserved byte-for-byte (I1/AC6).
     *
     * @param {object} state
     * @return {boolean}
     */
    var isSignificant = function(state) {
        if (!lastSavedState) {
            return true;
        }
        var isAfd = function(s) {
            return s && (s.states !== undefined || s.transitions !== undefined || s.alphabet !== undefined);
        };
        if (isAfd(state) || isAfd(lastSavedState)) {
            return JSON.stringify(state.states) !== JSON.stringify(lastSavedState.states) ||
                   JSON.stringify(state.transitions) !== JSON.stringify(lastSavedState.transitions) ||
                   JSON.stringify(state.alphabet) !== JSON.stringify(lastSavedState.alphabet);
        }
        return JSON.stringify(state) !== JSON.stringify(lastSavedState);
    };

    /**
     * Register the DOM element that will receive graphitoubb:snapshot-status CustomEvents.
     *
     * @param {Element|null} target  Editor root element.
     */
    var init = function(target) {
        _target = target || null;
    };

    /**
     * Called on each editor change. Debounces and conditionally saves.
     *
     * @param {number} attemptid
     * @param {object} state Current automaton state {states, transitions, alphabet}.
     * @param {number} schemaversion Tool schema version (forwarded to repository).
     */
    var onchange = function(attemptid, state, schemaversion) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            if (!isSignificant(state)) {
                return;
            }
            dispatchStatus('saving');
            Repository.saveSnapshot(attemptid, state, schemaversion)
                .then(function() {
                    lastSavedState = state;
                    dispatchStatus('saved');
                    return;
                })
                .catch(function(err) {
                    dispatchStatus('error');
                    Notification.exception(err);
                });
        }, DEBOUNCE_MS);
    };

    /**
     * Cancel any pending (debounced) save. Used before the finish flush so a late
     * autosave cannot insert a snapshot after the authoritative final answer.
     */
    var cancel = function() {
        clearTimeout(debounceTimer);
    };

    return {
        init: init,
        onchange: onchange,
        cancel: cancel,
    };
});
