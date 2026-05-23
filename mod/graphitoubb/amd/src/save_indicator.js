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
 * Save indicator — subscribes to graphitoubb:snapshot-status events and
 * updates a badge element with saving / saved / error feedback.
 *
 * @module     mod_graphitoubb/save_indicator
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var _el = null;
    var _fadeTimer = null;

    var CLASSES = {
        saving: 'mod-graphitoubb-save-indicator--saving',
        saved:  'mod-graphitoubb-save-indicator--saved',
        error:  'mod-graphitoubb-save-indicator--error',
    };

    var LABELS = {
        saving: 'Saving\u2026',
        saved:  'Saved \u2713',
        error:  'Save failed \u2715',
    };

    var clearStatus = function() {
        if (!_el) {
            return;
        }
        Object.keys(CLASSES).forEach(function(k) {
            _el.classList.remove(CLASSES[k]);
        });
        _el.textContent = '';
    };

    var setStatus = function(status) {
        if (!_el) {
            return;
        }
        clearTimeout(_fadeTimer);
        clearStatus();
        if (CLASSES[status]) {
            _el.classList.add(CLASSES[status]);
        }
        _el.textContent = LABELS[status] || '';
        if (status === 'saved') {
            _fadeTimer = setTimeout(clearStatus, 2000);
        }
    };

    /**
     * Initialise the save indicator.
     *
     * Listens to both `graphitoubb:snapshot-status` (AFD autosave) and
     * `graphitoubb:autosave-status` (truth-table autosave) on the target element.
     *
     * @param {Element|null} el  The .mod-graphitoubb-save-indicator span.
     * @param {Element|null} target  Element that dispatches the status events.
     */
    var init = function(el, target) {
        _el = el || null;
        if (!target) {
            return;
        }
        var handler = function(evt) {
            setStatus(evt.detail && evt.detail.status);
        };
        target.addEventListener('graphitoubb:snapshot-status', handler);
        target.addEventListener('graphitoubb:autosave-status', handler);
    };

    return {
        init: init,
    };
});
