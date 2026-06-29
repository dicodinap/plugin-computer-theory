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
 * Toolbar state machine for the AFD editor.
 *
 * 7-state FSM mirroring the AFD structure the student is learning to author.
 * State is module-level (not cy.scratch()) so it outlives Cytoscape re-renders.
 *
 * States: idle | adding_state | adding_transition_source |
 *         adding_transition_target | setting_start | toggling_final | deleting
 *
 * @module     mod_graphitoubb/editor_toolbar
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /** @type {string} Current editor mode. */
    var mode = 'idle';

    /** @type {Element|null} Toolbar root element. */
    var toolbarEl = null;

    /** Valid mode identifiers. */
    var VALID_MODES = [
        'idle',
        'adding_state',
        'adding_transition_source',
        'adding_transition_target',
        'setting_start',
        'toggling_final',
        'deleting',
    ];

    /**
     * Transition to a new mode and update button active states.
     *
     * Dispatches a custom 'graphitoubb:modechange' event on the toolbar element
     * so other modules (afd_editor, cytoscape_factory) can react without coupling.
     *
     * @param {string} next Target mode.
     */
    var setMode = function(next) {
        if (VALID_MODES.indexOf(next) === -1) {
            return;
        }
        var previous = mode;
        mode = next;

        if (toolbarEl) {
            toolbarEl.querySelectorAll('.mod-graphitoubb-tool-btn').forEach(function(btn) {
                var isActive = btn.dataset.mode === mode;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            toolbarEl.dispatchEvent(new CustomEvent('graphitoubb:modechange', {
                bubbles: true,
                detail: {previous: previous, mode: mode},
            }));
        }
    };

    /**
     * Handle a toolbar button click.
     *
     * Clicking the active mode button toggles back to idle (button-toggle cancel).
     * Clicking a different mode button switches to that mode.
     *
     * @param {Event} e Click event.
     */
    var onButtonClick = function(e) {
        var btn = e.target.closest('.mod-graphitoubb-tool-btn');
        if (!btn) {
            return;
        }
        var target = btn.dataset.mode;
        if (!target) {
            return;
        }
        setMode(target === mode ? 'idle' : target);
    };

    /**
     * Handle keydown: Esc cancels any active mode back to idle.
     *
     * @param {KeyboardEvent} e
     */
    var onKeyDown = function(e) {
        if (e.key === 'Escape' && mode !== 'idle') {
            setMode('idle');
        }
    };

    /**
     * Initialise the toolbar FSM.
     *
     * Attaches event listeners to the toolbar element and the document for Esc.
     * Safe to call multiple times — re-attaching is harmless because the
     * previous listeners are replaced on the same element reference.
     *
     * @param {Element} el Toolbar root element (.mod-graphitoubb-toolbar-buttons).
     */
    var init = function(el) {
        toolbarEl = el;
        mode = 'idle';

        el.addEventListener('click', onButtonClick);
        document.addEventListener('keydown', onKeyDown);
    };

    /**
     * Return the current editor mode.
     *
     * @return {string}
     */
    var getMode = function() {
        return mode;
    };

    return {
        init: init,
        getMode: getMode,
        setMode: setMode,
    };
});
