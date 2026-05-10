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
 * Orchestrator module for the AFD editor UI.
 *
 * Wires toolbar FSM modes to Cytoscape mutations and snapshot auto-save.
 *
 * @module     mod_graphitoubb/afd_editor
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'mod_graphitoubb/cytoscape_factory',
    'mod_graphitoubb/snapshot_controller',
    'mod_graphitoubb/repository',
    'mod_graphitoubb/editor_toolbar',
    'core/notification',
], function(CytoscapeFactory, SnapshotController, Repository, Toolbar, Notification) {

    /** Alphabet symbols currently in use; kept in sync with cy edges. */
    var currentAlphabet = [];

    /** Source node id stored between the two clicks of the add-transition flow. */
    var pendingTransitionSource = null;

    /**
     * Extract canonical AFD shape from a Cytoscape instance.
     *
     * snapshot_controller.isSignificant() compares {states, transitions, alphabet}.
     * cy.json() uses Cytoscape's own format which lacks those keys, causing
     * isSignificant() to always return false.
     *
     * @param {object} cy Cytoscape instance.
     * @return {{states: Array, transitions: Array, alphabet: Array, start: string|null, finals: Array}}
     */
    var extractCanonical = function(cy) {
        var states = [];
        var finals = [];
        var start = null;

        cy.nodes().forEach(function(n) {
            states.push({id: n.id(), label: n.data('label') || n.id()});
            if (n.data('start')) {
                start = n.id();
            }
            if (n.data('final')) {
                finals.push(n.id());
            }
        });

        var symbolSeen = {};
        var alphabet = [];
        var transitions = [];

        cy.edges().forEach(function(e) {
            var symbol = e.data('symbol') || '';
            transitions.push({from: e.source().id(), symbol: symbol, to: e.target().id()});
            if (symbol && !symbolSeen[symbol]) {
                symbolSeen[symbol] = true;
                alphabet.push(symbol);
            }
        });

        alphabet.sort();

        return {states: states, transitions: transitions, alphabet: alphabet, start: start, finals: finals};
    };

    /**
     * Return the next available qN node id.
     *
     * @param {object} cy
     * @return {string}
     */
    var nextStateId = function(cy) {
        var max = -1;
        cy.nodes().forEach(function(n) {
            var m = n.id().match(/^q(\d+)$/);
            if (m) {
                max = Math.max(max, parseInt(m[1], 10));
            }
        });
        return 'q' + (max + 1);
    };

    /**
     * Read an integer bound from a toolbar data-* attribute, with fallback.
     *
     * @param {Element|null} toolbarEl
     * @param {string} attr camelCase dataset key (e.g. 'maxStates').
     * @param {number} fallback
     * @return {number}
     */
    var bound = function(toolbarEl, attr, fallback) {
        if (!toolbarEl) {
            return fallback;
        }
        var val = parseInt(toolbarEl.dataset[attr], 10);
        return isNaN(val) ? fallback : val;
    };

    /**
     * S5: Create a new state at the canvas click position.
     *
     * @param {object} cy
     * @param {Element|null} toolbarEl
     * @param {object} evt Cytoscape tap event.
     */
    var handleAddState = function(cy, toolbarEl, evt) {
        if (cy.nodes().length >= bound(toolbarEl, 'maxStates', 64)) {
            // eslint-disable-next-line no-console
            console.warn('graphitoubb: max states (' + bound(toolbarEl, 'maxStates', 64) + ') reached');
            Toolbar.setMode('idle');
            return;
        }
        var id = nextStateId(cy);
        cy.add({
            group: 'nodes',
            data: {id: id, label: id, start: false, final: false},
            position: evt.position,
        });
        Toolbar.setMode('idle');
    };

    /**
     * S6a: Store source node; transition FSM to adding_transition_target.
     *
     * @param {object} node Cytoscape node.
     */
    var handleTransitionSource = function(node) {
        pendingTransitionSource = node.id();
        Toolbar.setMode('adding_transition_target');
    };

    /**
     * S6b: Validate and create the transition edge.
     *
     * Determinism rule: no two transitions from the same source on the same symbol.
     * Auto-adds new symbols to currentAlphabet (easier learning curve than
     * forcing the student to define the alphabet before drawing transitions).
     *
     * @param {object} cy
     * @param {Element|null} toolbarEl
     * @param {object} targetNode Cytoscape node.
     */
    var handleTransitionTarget = function(cy, toolbarEl, targetNode) {
        var sourceId = pendingTransitionSource;
        pendingTransitionSource = null;

        if (cy.edges().length >= bound(toolbarEl, 'maxTransitions', 512)) {
            // eslint-disable-next-line no-console
            console.warn('graphitoubb: max transitions (' + bound(toolbarEl, 'maxTransitions', 512) + ') reached');
            Toolbar.setMode('idle');
            return;
        }

        var symbol = window.prompt('Símbolo de transición (1 carácter):');
        if (symbol === null) {
            Toolbar.setMode('idle');
            return;
        }
        symbol = symbol.trim().charAt(0);
        if (!symbol) {
            Toolbar.setMode('idle');
            return;
        }

        var isNewSymbol = currentAlphabet.indexOf(symbol) === -1;
        if (isNewSymbol && currentAlphabet.length >= bound(toolbarEl, 'maxAlphabet', 16)) {
            // eslint-disable-next-line no-console
            console.warn('graphitoubb: max alphabet size (' + bound(toolbarEl, 'maxAlphabet', 16) + ') reached');
            Toolbar.setMode('idle');
            return;
        }

        var isDuplicate = cy.edges().some(function(e) {
            return e.source().id() === sourceId && e.data('symbol') === symbol;
        });
        if (isDuplicate) {
            // eslint-disable-next-line no-console
            console.warn('graphitoubb: transition from ' + sourceId + " on '" + symbol + "' already exists");
            Toolbar.setMode('idle');
            return;
        }

        if (isNewSymbol) {
            currentAlphabet.push(symbol);
        }

        var targetId = targetNode.id();
        cy.add({
            group: 'edges',
            data: {
                id: sourceId + '__' + symbol + '__' + targetId,
                source: sourceId,
                target: targetId,
                symbol: symbol,
                label: symbol,
            },
        });
        Toolbar.setMode('idle');
    };

    /**
     * S7: Mark one node as start state; clear any previous start.
     *
     * Updates both node data (read by extractCanonical) and CSS class
     * (read by cytoscape_factory styles) so both paths stay in sync.
     *
     * @param {object} cy
     * @param {object} node Cytoscape node to become the new start state.
     */
    var handleSetStart = function(cy, node) {
        cy.nodes().forEach(function(n) {
            n.data('start', false);
            n.removeClass('start');
        });
        node.data('start', true);
        node.addClass('start');
        Toolbar.setMode('idle');
    };

    /**
     * S8: Toggle the final flag on a node.
     *
     * Updates both data.final (read by extractCanonical) and CSS class .final
     * (read by cytoscape style selector for double-border rendering).
     *
     * @param {object} node Cytoscape node.
     */
    var handleToggleFinal = function(node) {
        var isFinal = !!node.data('final');
        node.data('final', !isFinal);
        if (isFinal) {
            node.removeClass('final');
        } else {
            node.addClass('final');
        }
        Toolbar.setMode('idle');
    };

    /**
     * Initialise the editor for a given attempt.
     *
     * @param {number} attemptid
     * @param {number} instanceid
     * @param {number} schemaversion
     */
    var init = function(attemptid, instanceid, schemaversion) {
        var container = document.getElementById('graphitoubb-canvas-' + instanceid);
        if (!container) {
            return;
        }

        Repository.loadLatestSnapshot(attemptid)
            .then(function(snapshot) {
                var automaton = {states: [], transitions: [], alphabet: [], start: null, finals: []};
                if (snapshot && snapshot.found && snapshot.payload) {
                    try {
                        automaton = typeof snapshot.payload === 'string'
                            ? JSON.parse(snapshot.payload)
                            : snapshot.payload;
                    } catch (e) {
                        // Empty automaton fallback if payload corrupt.
                    }
                }

                var loading = container.querySelector('.mod-graphitoubb-loading');
                if (loading) {
                    loading.parentNode.removeChild(loading);
                }

                var cy = CytoscapeFactory.create(container, automaton);

                currentAlphabet = (automaton.alphabet || []).slice();

                var editorRoot = container.closest('.mod-graphitoubb-editor');
                var toolbarEl = editorRoot
                    ? editorRoot.querySelector('.mod-graphitoubb-toolbar-buttons')
                    : null;

                if (toolbarEl) {
                    Toolbar.init(toolbarEl);
                }

                cy.on('add remove data', function() {
                    SnapshotController.onchange(attemptid, extractCanonical(cy), schemaversion);
                });

                cy.on('tap', function(evt) {
                    var mode = Toolbar.getMode();
                    var target = evt.target;
                    var isCanvas = (target === cy);
                    var isNode = !isCanvas && typeof target.isNode === 'function' && target.isNode();

                    switch (mode) {
                        case 'adding_state':
                            if (isCanvas) {
                                handleAddState(cy, toolbarEl, evt);
                            }
                            break;
                        case 'adding_transition_source':
                            if (isNode) {
                                handleTransitionSource(target);
                            }
                            break;
                        case 'adding_transition_target':
                            if (isNode) {
                                handleTransitionTarget(cy, toolbarEl, target);
                            }
                            break;
                        case 'setting_start':
                            if (isNode) {
                                handleSetStart(cy, target);
                            }
                            break;
                        case 'toggling_final':
                            if (isNode) {
                                handleToggleFinal(target);
                            }
                            break;
                    }
                });

                return cy;
            })
            .catch(Notification.exception);
    };

    return {
        init: init,
    };
});
