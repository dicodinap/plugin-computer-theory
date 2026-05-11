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
    'mod_graphitoubb/alphabet_ui',
    'mod_graphitoubb/afd_simulator',
    'local_graphitoubb/afd_adapter',
    'core/notification',
    'mod_graphitoubb/save_indicator',
    'core/str',
], function(CytoscapeFactory, SnapshotController, Repository, Toolbar, AlphabetUI, AfdSimulator, AfdAdapter, Notification, SaveIndicator, Str) {

    /** Source node id stored between the two clicks of the add-transition flow. */
    var pendingTransitionSource = null;

    /**
     * Fetch a translated string and show a Moodle notification.
     *
     * @param {string} type  'error'|'warning'|'info'|'success'
     * @param {string} key   Lang string key in mod_graphitoubb.
     * @param {string|undefined} param  Optional {$a} substitution.
     */
    var notify = function(type, key, param) {
        Str.get_string(key, 'mod_graphitoubb', param !== undefined ? String(param) : null)
            .then(function(msg) {
                Notification.addNotification({message: msg, type: type});
                return;
            });
    };

    /**
     * Extract canonical AFD shape from a Cytoscape instance.
     *
     * Reads alphabet from cy.scratch('alphabet') when set (S10 source of truth).
     * Falls back to inferring from edges for backward compatibility with pre-S10 snapshots.
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

        var transitions = [];
        cy.edges().forEach(function(e) {
            var symbol = e.data('symbol') || '';
            transitions.push({from: e.source().id(), symbol: symbol, to: e.target().id()});
        });

        var scratch = cy.scratch('alphabet');
        var alphabet;
        if (Array.isArray(scratch)) {
            alphabet = scratch.slice().sort();
        } else {
            var symbolSeen = {};
            alphabet = [];
            cy.edges().forEach(function(e) {
                var sym = e.data('symbol') || '';
                if (sym && !symbolSeen[sym]) {
                    symbolSeen[sym] = true;
                    alphabet.push(sym);
                }
            });
            alphabet.sort();
        }

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
            notify('error', 'err_max_states', bound(toolbarEl, 'maxStates', 64));
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
     * Auto-adds new symbols via AlphabetUI so the visible list stays in sync.
     *
     * @param {object} cy
     * @param {Element|null} toolbarEl
     * @param {object} targetNode Cytoscape node.
     */
    var handleTransitionTarget = function(cy, toolbarEl, targetNode) {
        var sourceId = pendingTransitionSource;
        pendingTransitionSource = null;

        if (cy.edges().length >= bound(toolbarEl, 'maxTransitions', 512)) {
            notify('error', 'err_max_transitions', bound(toolbarEl, 'maxTransitions', 512));
            Toolbar.setMode('idle');
            return;
        }

        var symbol = window.prompt('Símbolo de transición (1 carácter alfanumérico):');
        if (symbol === null) {
            Toolbar.setMode('idle');
            return;
        }
        symbol = symbol.trim().charAt(0);
        if (!symbol || !/^[a-zA-Z0-9]$/.test(symbol)) {
            Toolbar.setMode('idle');
            return;
        }

        var currentAlphabet = AlphabetUI.getAlphabet();
        var isNewSymbol = currentAlphabet.indexOf(symbol) === -1;
        if (isNewSymbol && currentAlphabet.length >= bound(toolbarEl, 'maxAlphabet', 16)) {
            notify('error', 'err_max_alphabet', bound(toolbarEl, 'maxAlphabet', 16));
            Toolbar.setMode('idle');
            return;
        }

        var isDuplicate = cy.edges().some(function(e) {
            return e.source().id() === sourceId && e.data('symbol') === symbol;
        });
        if (isDuplicate) {
            notify('warning', 'err_duplicate_transition', sourceId + " \u2192 '" + symbol + "'");
            Toolbar.setMode('idle');
            return;
        }

        if (isNewSymbol) {
            AlphabetUI.addSymbol(symbol);
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
     * S9: Remove a node or edge.
     *
     * @param {object} cy
     * @param {object} element Cytoscape node or edge.
     */
    var handleDelete = function(cy, element) {
        cy.remove(element);
        Toolbar.setMode('idle');
    };

    /**
     * Prepend a word result entry to the wordbank list panel.
     *
     * Removes the empty-state placeholder on first entry.
     * Trims to 50 entries (oldest removed from the bottom).
     *
     * @param {Element} panelEl .mod-graphitoubb-wordbank-panel element.
     * @param {string} word
     * @param {boolean} accepted
     */
    var appendWordbankEntry = function(panelEl, word, accepted) {
        var list = panelEl.querySelector('.mod-graphitoubb-wordbank-list');
        if (!list) {
            return;
        }
        var empty = list.querySelector('.mod-graphitoubb-empty');
        if (empty) {
            list.removeChild(empty);
        }
        var li = document.createElement('li');
        li.className = accepted ? 'accepted' : 'rejected';
        li.textContent = (accepted ? '\u2713 ' : '\u2717 ') + word;
        list.insertBefore(li, list.firstChild);
        while (list.children.length > 50) {
            list.removeChild(list.lastChild);
        }
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

                cy.scratch('alphabet', (automaton.alphabet || []).slice());

                var editorRoot = container.closest('.mod-graphitoubb-editor');
                var toolbarEl = editorRoot
                    ? editorRoot.querySelector('.mod-graphitoubb-toolbar-buttons')
                    : null;

                if (toolbarEl) {
                    Toolbar.init(toolbarEl);
                }

                // S13: wire save indicator and give snapshot_controller the dispatch target.
                SnapshotController.init(editorRoot || null);
                var indicatorEl = editorRoot
                    ? editorRoot.querySelector('.mod-graphitoubb-save-indicator')
                    : null;
                SaveIndicator.init(indicatorEl, editorRoot || null);

                // Always init AlphabetUI with cy so getAlphabet()/addSymbol() work in handlers.
                AlphabetUI.init(
                    editorRoot ? editorRoot.querySelector('.mod-graphitoubb-alphabet-panel') : null,
                    cy,
                    function() {
                        SnapshotController.onchange(attemptid, extractCanonical(cy), schemaversion);
                    }
                );

                // S12: wordbank panel.
                var wordbankPanel = editorRoot
                    ? editorRoot.querySelector('.mod-graphitoubb-wordbank-panel')
                    : null;

                // S11: wire simulator Run button.
                var simPanel = editorRoot
                    ? editorRoot.querySelector('.mod-graphitoubb-simulator-panel')
                    : null;

                if (simPanel) {
                    var runBtn = simPanel.querySelector('.mod-graphitoubb-run');
                    var inputEl = simPanel.querySelector('#graphitoubb-simulator-input');

                    if (runBtn && inputEl) {
                        runBtn.addEventListener('click', function() {
                            var word = inputEl.value;
                            var maxLen = bound(toolbarEl, 'maxInputLength', 256);
                            if (word.length > maxLen) {
                                notify('error', 'err_input_too_long', maxLen);
                                return;
                            }

                            var afdSim = AfdAdapter.cyToAfdSimulator(cy);
                            if (!afdSim.initialState) {
                                notify('error', 'err_no_initial_state');
                                return;
                            }
                            if (!afdSim.alphabet.length) {
                                notify('warning', 'err_empty_alphabet');
                                return;
                            }

                            cy.nodes().removeClass('trace-visited');
                            if (editorRoot) {
                                editorRoot.classList.remove('trace-accept', 'trace-reject');
                            }

                            // afd_simulator.run() returns {accepted, trace} — trace is string[].
                            var result = AfdSimulator.run(afdSim, word);

                            // S14: surface rejection reason as a toast.
                            if (!result.accepted) {
                                var rejDetail;
                                if (result.trace.length <= word.length) {
                                    // Stopped mid-input: no transition for this symbol at current state.
                                    var failSym = word[result.trace.length - 1];
                                    var failState = result.trace[result.trace.length - 1];
                                    rejDetail = "'" + failSym + "' @ " + failState;
                                } else {
                                    rejDetail = result.trace[result.trace.length - 1] + ' (not accepting)';
                                }
                                notify('warning', 'err_simulator_reject', rejDetail);
                            }

                            result.trace.forEach(function(nodeId, i) {
                                setTimeout(function() {
                                    cy.$('#' + nodeId).addClass('trace-visited');
                                    if (i === result.trace.length - 1 && editorRoot) {
                                        editorRoot.classList.add(result.accepted ? 'trace-accept' : 'trace-reject');
                                    }
                                }, i * 400);
                            });

                            // S12: log word to WS and update wordbank panel.
                            Repository.logWord(attemptid, word, result.accepted)
                                .then(function() {
                                    if (wordbankPanel) {
                                        appendWordbankEntry(wordbankPanel, word, result.accepted);
                                    }
                                    return;
                                })
                                .catch(function() {
                                    notify('warning', 'warn_logword_failed');
                                });
                        });
                    }
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
                        case 'deleting':
                            if (isNode || (!isCanvas && typeof target.isEdge === 'function' && target.isEdge())) {
                                handleDelete(cy, target);
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
