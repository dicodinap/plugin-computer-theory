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
     * Save a snapshot WITHOUT swallowing errors — the returned promise rejects on
     * failure (e.g. the 1/second rate limit). Used by the finish flush so the final
     * answer is guaranteed to persist (with retry) before grading.
     *
     * @param {number} attemptid
     * @param {object} payload
     * @param {number} schemaversion
     * @return {Promise<object>}
     */
    var saveSnapshotStrict = function(attemptid, payload, schemaversion) {
        return Ajax.call([{
            methodname: 'mod_graphitoubb_save_snapshot',
            args: {
                attemptid: attemptid,
                payload: JSON.stringify(payload),
                schemaversion: schemaversion || 1,
            },
        }])[0];
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

    // -------------------------------------------------------------------------
    // Truth-table methods (iter1).
    // -------------------------------------------------------------------------

    /**
     * Autosave a truth-table draft.
     *
     * @param {number} attemptid
     * @param {string} payload        JSON-stringified submission payload.
     * @param {string} hash           SHA-256 of the payload.
     * @param {number} draft_updated_at  Client's last known server timestamp.
     * @return {Promise<{status: string, draft_updated_at: number, server_payload: string}>}
     */
    var saveDraft = function(attemptid, payload, hash, draft_updated_at) {
        return Ajax.call([{
            methodname: 'mod_graphitoubb_save_draft',
            args: {
                attemptid:        attemptid,
                payload:          payload,
                payload_hash:     hash || '',
                draft_updated_at: draft_updated_at || 0,
            },
        }])[0];
    };

    /**
     * Submit a final truth-table answer.
     *
     * @param {number} attemptid
     * @param {string} payload  JSON-stringified submission.
     * @return {Promise<object>}  Grading result.
     */
    var submit = function(attemptid, payload) {
        return Ajax.call([{
            methodname: 'mod_graphitoubb_submit',
            args: {attemptid: attemptid, payload: payload},
        }])[0];
    };

    /**
     * Mark an attempt as finished (AFD "submit"/"hand in").
     *
     * @param {number} attemptid
     * @return {Promise<{status: string}>}
     */
    var finishAttempt = function(attemptid) {
        return Ajax.call([{
            methodname: 'mod_graphitoubb_finish_attempt',
            args: {attemptid: attemptid},
        }])[0];
    };

    /**
     * Log a client-side telemetry event.
     *
     * Fails silently (does not propagate rejection).
     *
     * @param {string} name        Event name from the allowlist.
     * @param {object} payloadObj  Optional payload object (will be JSON-encoded).
     * @param {number} instanceid
     * @param {number} [attemptid]
     * @return {Promise<object>}
     */
    var logEvent = function(name, payloadObj, instanceid, attemptid) {
        return Ajax.call([{
            methodname: 'mod_graphitoubb_log_event',
            args: {
                attemptid:  attemptid || 0,
                instanceid: instanceid,
                name:       name,
                payload:    payloadObj ? JSON.stringify(payloadObj) : '',
            },
        }])[0].catch(function() {/* fail silent */});
    };

    // -------------------------------------------------------------------------
    // Teacher panel methods (iter1 slice 5).
    // -------------------------------------------------------------------------

    /**
     * Fetch summary-tab data for the teacher panel.
     *
     * @param {number} instanceid
     * @return {Promise<object>} Summary data (enrolled, attempted, avg_score, buckets, top_errors, …).
     */
    var getPanelSummary = function(instanceid) {
        return Ajax.call([{
            methodname: 'mod_graphitoubb_get_panel_summary',
            args: {instanceid: instanceid},
        }])[0].catch(Notification.exception);
    };

    /**
     * Fetch per-student tab data for the teacher panel.
     *
     * @param {number} instanceid
     * @param {string} filter  'all' | 'with_errors' | 'not_submitted'
     * @return {Promise<{students: Array}>}
     */
    var getPanelPerStudent = function(instanceid, filter) {
        return Ajax.call([{
            methodname: 'mod_graphitoubb_get_panel_per_student',
            args: {instanceid: instanceid, filter: filter || 'all'},
        }])[0].catch(Notification.exception);
    };

    /**
     * Fetch heatmap data for the teacher panel.
     *
     * @param {number} instanceid
     * @return {Promise<{columns: string[], rows_count: number, cells: Array}>}
     */
    var getPanelHeatmap = function(instanceid) {
        return Ajax.call([{
            methodname: 'mod_graphitoubb_get_panel_heatmap',
            args: {instanceid: instanceid},
        }])[0].catch(Notification.exception);
    };

    /**
     * Reset attempts for a student (or all students if userid is 0).
     *
     * @param {number} instanceid
     * @param {number} [userid=0]  0 = reset all students.
     * @return {Promise<{reset_count: number}>}
     */
    var resetAttempts = function(instanceid, userid) {
        return Ajax.call([{
            methodname: 'mod_graphitoubb_reset_attempts',
            args: {instanceid: instanceid, userid: userid || 0},
        }])[0].catch(Notification.exception);
    };

    return {
        loadLatestSnapshot:  loadLatestSnapshot,
        saveSnapshot:        saveSnapshot,
        saveSnapshotStrict:  saveSnapshotStrict,
        logWord:             logWord,
        saveDraft:           saveDraft,
        submit:              submit,
        finishAttempt:       finishAttempt,
        logEvent:            logEvent,
        // Panel methods.
        getPanelSummary:     getPanelSummary,
        getPanelPerStudent:  getPanelPerStudent,
        getPanelHeatmap:     getPanelHeatmap,
        resetAttempts:       resetAttempts,
    };
});
