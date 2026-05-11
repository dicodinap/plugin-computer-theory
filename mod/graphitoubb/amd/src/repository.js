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
 * Data access layer for AFD snapshots via Moodle web services.
 *
 * @module     mod_graphitoubb/repository
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    /**
     * Load the latest snapshot for an attempt.
     *
     * @param {number} attemptid
     * @return {Promise<object|null>} Snapshot payload or null.
     */
    var loadLatestSnapshot = function(attemptid) {
        var request = {
            methodname: 'mod_graphitoubb_get_latest_snapshot',
            args: {attemptid: attemptid},
        };
        return Ajax.call([request])[0]
            .catch(Notification.exception);
    };

    /**
     * Save a snapshot payload for an attempt.
     *
     * @param {number} attemptid
     * @param {object} payload Serialisable automaton state.
     * @param {number} schemaversion Schema version for the payload format.
     * @return {Promise<object>} Saved snapshot record.
     */
    var saveSnapshot = function(attemptid, payload, schemaversion) {
        var request = {
            methodname: 'mod_graphitoubb_save_snapshot',
            args: {
                attemptid: attemptid,
                payload: JSON.stringify(payload),
                schemaversion: schemaversion || 1,
            },
        };
        return Ajax.call([request])[0]
            .catch(Notification.exception);
    };

    /**
     * Log a word tested against the automaton.
     *
     * @param {number} attemptid
     * @param {string} word
     * @param {boolean} accepted
     * @return {Promise<object>} {status: 'ok'}
     */
    var logWord = function(attemptid, word, accepted) {
        var request = {
            methodname: 'mod_graphitoubb_log_word',
            args: {attemptid: attemptid, word: word, accepted: accepted},
        };
        return Ajax.call([request])[0];
    };

    return {
        loadLatestSnapshot: loadLatestSnapshot,
        saveSnapshot: saveSnapshot,
        logWord: logWord,
    };
});
