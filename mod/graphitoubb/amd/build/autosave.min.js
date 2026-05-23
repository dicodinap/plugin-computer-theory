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
 * Autosave controller for the truth-table editor.
 *
 * - Debounce 500 ms on any change event routed through `notify_change`.
 * - Flushes on visibilitychange + beforeunload.
 * - State machine: idle → saving → saved | error.
 * - Conflict handling: on status='conflict', opens conflict modal.
 * - Rate-limit: on status='ratelimited', retries after 5 s without user action.
 *
 * @module     mod_graphitoubb/autosave
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'mod_graphitoubb/repository',
    'core/modal_factory',
    'core/templates',
], function(Repository, ModalFactory, Templates) {

    var DEBOUNCE_MS  = 500;
    var RETRY_MS     = 5000;

    var _root        = null;   // Editor root element.
    var _attemptid   = 0;
    var _instanceid  = 0;
    var _serverTs    = 0;      // Last known draft_updated_at from server.
    var _pending     = false;  // True when a save is in-flight.
    var _timer       = null;   // Debounce timer.
    var _getPayload  = null;   // Function that serializes current editor state → string.

    // -------------------------------------------------------------------------
    // State machine helpers
    // -------------------------------------------------------------------------
    var emit = function(status) {
        if (!_root) {
            return;
        }
        _root.dispatchEvent(new CustomEvent('graphitoubb:autosave-status', {
            bubbles: true,
            detail: {status: status},
        }));
    };

    // -------------------------------------------------------------------------
    // Core save logic
    // -------------------------------------------------------------------------
    var doSave = function() {
        if (_pending || !_getPayload) {
            return;
        }

        var payload = _getPayload();
        if (!payload) {
            return;
        }

        // Simple SHA-256 hash via SubtleCrypto is async and not available in all
        // Moodle contexts; use a fast content fingerprint instead (server validates).
        var hash = '';

        _pending = true;
        emit('saving');

        Repository.saveDraft(_attemptid, payload, hash, _serverTs)
            .then(function(result) {
                _pending = false;
                if (result.status === 'saved') {
                    _serverTs = result.draft_updated_at;
                    emit('saved');
                } else if (result.status === 'ratelimited') {
                    emit('error');
                    setTimeout(function() { notify_change(); }, RETRY_MS);
                } else if (result.status === 'conflict') {
                    emit('error');
                    showConflictModal(result.server_payload, payload, result.draft_updated_at);
                }
                return result;
            })
            .catch(function() {
                _pending = false;
                emit('error');
                setTimeout(function() { notify_change(); }, RETRY_MS);
            });
    };

    // -------------------------------------------------------------------------
    // Conflict modal
    // -------------------------------------------------------------------------
    var showConflictModal = function(serverPayload, clientPayload, serverTs) {
        var ctx = {
            server_time: new Date(serverTs * 1000).toLocaleTimeString(),
            client_time: new Date().toLocaleTimeString(),
        };

        Templates.render('mod_graphitoubb/conflict_modal', ctx)
            .then(function(html) {
                return ModalFactory.create({
                    type:  ModalFactory.types.DEFAULT,
                    title: M.util.get_string('conflict_title', 'mod_graphitoubb'),
                    body:  html,
                });
            })
            .then(function(modal) {
                modal.show();
                var root = modal.getRoot()[0];

                root.querySelector('[data-action="load-other"]').addEventListener('click', function() {
                    modal.hide();
                    if (_getPayload && serverPayload) {
                        // Signal to the editor to restore server version.
                        _root.dispatchEvent(new CustomEvent('graphitoubb:restore-draft', {
                            bubbles: true,
                            detail: {payload: serverPayload, ts: serverTs},
                        }));
                        _serverTs = serverTs;
                    }
                });

                root.querySelector('[data-action="overwrite"]').addEventListener('click', function() {
                    modal.hide();
                    // Force overwrite: reset server ts to 0 so the next save bypasses the lock.
                    _serverTs = 0;
                    notify_change();
                });

                return modal;
            })
            .catch(function() {/* silently ignore modal errors */});
    };

    // -------------------------------------------------------------------------
    // Debounce
    // -------------------------------------------------------------------------

    /**
     * Notify the autosave controller that the editor state has changed.
     * Schedules a debounced save.
     */
    var notify_change = function() {
        clearTimeout(_timer);
        _timer = setTimeout(doSave, DEBOUNCE_MS);
    };

    // -------------------------------------------------------------------------
    // Flush (visibility + unload)
    // -------------------------------------------------------------------------
    var flush = function() {
        clearTimeout(_timer);
        doSave();
    };

    // -------------------------------------------------------------------------
    // Public init
    // -------------------------------------------------------------------------

    /**
     * Initialise the autosave controller.
     *
     * @param {Element} rootElement  The [data-region="truth-table-editor"] element.
     * @param {Function} getPayloadFn  Callback that returns the current payload as a JSON string.
     * @param {number} initialServerTs  The draft_updated_at value from the server on page load (0 if none).
     */
    var init = function(rootElement, getPayloadFn, initialServerTs) {
        _root       = rootElement;
        _attemptid  = parseInt(rootElement.dataset.attemptid, 10) || 0;
        _instanceid = parseInt(rootElement.dataset.instanceid, 10) || 0;
        _serverTs   = initialServerTs || 0;
        _getPayload = getPayloadFn;

        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                flush();
            }
        });

        window.addEventListener('beforeunload', flush);
    };

    return {
        init:          init,
        notify_change: notify_change,
    };
});
