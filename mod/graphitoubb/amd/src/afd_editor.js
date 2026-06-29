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
    'core/modal_save_cancel',
    'core/modal_events',
], function(CytoscapeFactory, SnapshotController, Repository, Toolbar, AlphabetUI, AfdSimulator, AfdAdapter, Notification, SaveIndicator, Str, ModalSaveCancel, ModalEvents) {

    /** Source node id stored between the two clicks of the add-transition flow. */
    var pendingTransitionSource = null;

    /**
     * Module-level cache of localised UI strings. Populated by loadStrings()
     * during init(); handlers read from it with English fallbacks so the editor
     * never blocks on the async string fetch.
     */
    var _str = {};

    /** Keys prefetched into _str (mod_graphitoubb component). */
    var STRING_KEYS = [
        'mode_hint_idle', 'mode_hint_adding_state', 'mode_hint_adding_transition_source',
        'mode_hint_adding_transition_target', 'mode_hint_setting_start',
        'mode_hint_toggling_final', 'mode_hint_deleting', 'transition_symbol_prompt',
        'run_hint_needs_start', 'run_hint_needs_alphabet', 'run_hint_ready',
        'run_disabled_title', 'sim_accepted', 'sim_rejected', 'word_empty',
    ];

    /**
     * Prefetch the editor UI strings into _str. Resolves even on failure.
     *
     * @return {Promise}
     */
    var loadStrings = function() {
        return Str.get_strings(STRING_KEYS.map(function(key) {
            return {key: key, component: 'mod_graphitoubb'};
        })).then(function(values) {
            STRING_KEYS.forEach(function(key, i) {
                _str[key] = values[i];
            });
            return _str;
        }).catch(function() {
            return _str;
        });
    };

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
     * Show a Moodle save/cancel confirmation modal (no native confirm/prompt).
     *
     * @param {string} titleKey   Lang key for the modal title.
     * @param {string} bodyKey    Lang key for the body.
     * @param {string} confirmKey Lang key for the confirm (save) button.
     * @param {*} bodyParam       Optional {$a} for the body string.
     * @param {function} onConfirm Callback run when the user confirms.
     */
    var confirmModal = function(titleKey, bodyKey, confirmKey, bodyParam, onConfirm) {
        Str.get_strings([
            {key: titleKey, component: 'mod_graphitoubb'},
            {key: bodyKey, component: 'mod_graphitoubb', param: bodyParam},
            {key: confirmKey, component: 'mod_graphitoubb'},
        ]).then(function(s) {
            return ModalSaveCancel.create({title: s[0], body: s[1]}).then(function(modal) {
                modal.setSaveButtonText(s[2]);
                modal.getRoot().on(ModalEvents.save, onConfirm);
                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });
                modal.show();
                return modal;
            });
        }).catch(Notification.exception);
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

        var symbol = window.prompt(_str.transition_symbol_prompt
            || 'Transition symbol (1 alphanumeric character):');
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
     * A12: delete an element, confirming first when removing a state that still
     * has connected transitions (which would be silently destroyed too).
     *
     * @param {object} cy
     * @param {object} element Cytoscape node or edge.
     */
    var confirmAndDelete = function(cy, element) {
        var edgeCount = (element.isNode && element.isNode())
            ? element.connectedEdges().length
            : 0;
        if (edgeCount === 0) {
            handleDelete(cy, element);
            return;
        }
        confirmModal('delete_confirm_title', 'delete_confirm_body', 'delete_confirm_button',
            edgeCount, function() {
                handleDelete(cy, element);
            });
    };

    /**
     * A12: clear the whole automaton (states, transitions, alphabet) after
     * confirmation. Alphabet symbols are removable once their edges are gone.
     *
     * @param {object} cy
     */
    var resetAutomaton = function(cy) {
        confirmModal('reset_automaton_title', 'reset_automaton_body', 'reset_automaton_confirm',
            undefined, function() {
                cy.elements().remove();
                AlphabetUI.getAlphabet().slice().forEach(function(sym) {
                    AlphabetUI.removeSymbol(sym);
                });
                cy.scratch('alphabet', []);
                Toolbar.setMode('idle');
            });
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
        var shown = word === '' ? (_str.word_empty || '\u03b5 (empty)') : word;
        li.textContent = (accepted ? '\u2713 ' : '\u2717 ') + shown;
        list.insertBefore(li, list.firstChild);
        while (list.children.length > 50) {
            list.removeChild(list.lastChild);
        }
    };

    /**
     * A2: switch the editor into the read-only "finished/submitted" state —
     * disable structural tools, the simulator, alphabet editing and the finish
     * button, and flip the status badge to "Finished".
     *
     * @param {Element|null} editorRoot .mod-graphitoubb-editor element.
     */
    var applyFinishedState = function(editorRoot) {
        if (!editorRoot) {
            return;
        }
        editorRoot.setAttribute('data-finished', '1');
        editorRoot.querySelectorAll('.mod-graphitoubb-tool-btn').forEach(function(b) {
            b.disabled = true;
        });
        ['.mod-graphitoubb-run', '.mod-graphitoubb-alphabet-add',
            '.mod-graphitoubb-alphabet-input', '[data-region="finish-btn"]',
            '.mod-graphitoubb-reset-automaton-btn'].forEach(function(sel) {
            var el = editorRoot.querySelector(sel);
            if (el) {
                el.disabled = true;
            }
        });
        var badge = editorRoot.querySelector('[data-region="attempt-status"]');
        if (badge) {
            badge.classList.remove('badge-warning', 'bg-warning', 'text-dark');
            badge.classList.add('badge-success', 'bg-success');
            Str.get_string('status_finished', 'mod_graphitoubb').then(function(s) {
                badge.textContent = s;
                return;
            }).catch(function() {
                return;
            });
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

                // A11: forward-declared so the cy change handler can call the live
                // implementation once the simulator wiring (below) reassigns it.
                var updateRunValidity = function() {};

                // A5: contextual mode hint — reflects the active toolbar mode so
                // the student always knows what the next click does.
                var modeHintEl = editorRoot
                    ? editorRoot.querySelector('.mod-graphitoubb-mode-hint')
                    : null;
                if (modeHintEl && editorRoot) {
                    editorRoot.addEventListener('graphitoubb:modechange', function(evt) {
                        var mode = (evt.detail && evt.detail.mode) || 'idle';
                        var hint = _str['mode_hint_' + mode];
                        if (hint) {
                            modeHintEl.textContent = hint;
                        }
                    });
                }

                // A6: zoom / fit / reset controls. The wheel-zoom and drag-pan that
                // Cytoscape already supports are otherwise undiscoverable.
                var zoomControls = container.querySelector('.mod-graphitoubb-zoom-controls');
                if (zoomControls) {
                    zoomControls.addEventListener('click', function(e) {
                        var btn = e.target.closest('.mod-graphitoubb-zoom-btn');
                        if (!btn) {
                            return;
                        }
                        var action = btn.dataset.zoom;
                        var center = {x: cy.width() / 2, y: cy.height() / 2};
                        if (action === 'in') {
                            cy.zoom({level: cy.zoom() * 1.2, renderedPosition: center});
                        } else if (action === 'out') {
                            cy.zoom({level: cy.zoom() / 1.2, renderedPosition: center});
                        } else if (action === 'fit') {
                            if (cy.nodes().length) {
                                cy.fit(cy.nodes(), 50);
                            }
                        } else if (action === 'reset') {
                            cy.zoom(1);
                            cy.center();
                        }
                    });
                }

                // A12: reset-automaton button (confirmed, no native dialog).
                var resetBtn = editorRoot
                    ? editorRoot.querySelector('.mod-graphitoubb-reset-automaton-btn')
                    : null;
                if (resetBtn) {
                    resetBtn.addEventListener('click', function() {
                        resetAutomaton(cy);
                    });
                }

                // A2: finish/submit the attempt with a confirmation modal, then
                // lock the editor and surface a success toast.
                var doFinish = function() {
                    Repository.finishAttempt(attemptid).then(function(result) {
                        applyFinishedState(editorRoot);
                        // C1: when the AFD exercise is graded, surface the score.
                        if (result && result.graded) {
                            if (result.invalid) {
                                return Str.get_string('afd_result_invalid', 'mod_graphitoubb');
                            }
                            return Str.get_string('afd_finish_graded_toast', 'mod_graphitoubb',
                                {correct: result.words_correct, total: result.words_total});
                        }
                        return Str.get_string('afd_finish_success', 'mod_graphitoubb');
                    }).then(function(msg) {
                        Notification.addNotification({message: msg, type: 'success'});
                        return;
                    }).catch(function() {
                        Str.get_string('afd_finish_error', 'mod_graphitoubb').then(function(msg) {
                            Notification.addNotification({message: msg, type: 'error'});
                            return;
                        });
                    });
                };

                var finishBtn = editorRoot
                    ? editorRoot.querySelector('[data-region="finish-btn"]')
                    : null;
                if (editorRoot && editorRoot.getAttribute('data-finished') === '1') {
                    applyFinishedState(editorRoot);
                } else if (finishBtn) {
                    finishBtn.addEventListener('click', function() {
                        Str.get_strings([
                            {key: 'afd_finish_title', component: 'mod_graphitoubb'},
                            {key: 'afd_finish_body', component: 'mod_graphitoubb'},
                            {key: 'afd_finish_confirm', component: 'mod_graphitoubb'},
                        ]).then(function(s) {
                            return ModalSaveCancel.create({title: s[0], body: s[1]}).then(function(modal) {
                                modal.setSaveButtonText(s[2]);
                                modal.getRoot().on(ModalEvents.save, function() {
                                    doFinish();
                                });
                                modal.getRoot().on(ModalEvents.hidden, function() {
                                    modal.destroy();
                                });
                                modal.show();
                                return modal;
                            });
                        }).catch(Notification.exception);
                    });
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
                        updateRunValidity();
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
                    var runHintEl = simPanel.querySelector('.mod-graphitoubb-run-hint');
                    var traceEl = simPanel.querySelector('.mod-graphitoubb-trace');

                    // A11: disable Run until the automaton can actually run, with an
                    // explicit reason instead of a generic toast after the fact.
                    updateRunValidity = function() {
                        if (!runBtn) {
                            return;
                        }
                        var hasStart = cy.nodes().some(function(n) {
                            return !!n.data('start');
                        });
                        var alpha = AlphabetUI.getAlphabet() || [];
                        var hasAlphabet = alpha.length > 0;
                        var ready = hasStart && hasAlphabet;

                        runBtn.disabled = !ready;
                        if (ready) {
                            runBtn.removeAttribute('title');
                        } else {
                            runBtn.title = _str.run_disabled_title || '';
                        }

                        if (runHintEl) {
                            var msg;
                            if (!hasStart) {
                                msg = _str.run_hint_needs_start;
                            } else if (!hasAlphabet) {
                                msg = _str.run_hint_needs_alphabet;
                            } else {
                                msg = _str.run_hint_ready;
                            }
                            runHintEl.textContent = msg || '';
                            runHintEl.classList.toggle('text-danger', !ready);
                            runHintEl.classList.toggle('text-muted', ready);
                        }
                    };

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

                            // E2: multimodal verdict (icon + text), not colour alone.
                            if (traceEl) {
                                var verdict = result.accepted
                                    ? (_str.sim_accepted || 'Accepted')
                                    : (_str.sim_rejected || 'Rejected');
                                traceEl.textContent = (result.accepted ? '✓ ' : '✗ ') + verdict;
                                traceEl.classList.remove('trace-accept', 'trace-reject');
                                traceEl.classList.add(result.accepted ? 'trace-accept' : 'trace-reject');
                            }

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
                    updateRunValidity();
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
                                confirmAndDelete(cy, target);
                            }
                            break;
                    }
                });

                // Initial validity (button disabled state needs no strings), then
                // refresh hints once the localised strings have loaded.
                updateRunValidity();
                loadStrings().then(function() {
                    if (modeHintEl && Toolbar.getMode() === 'idle') {
                        modeHintEl.textContent = _str.mode_hint_idle || modeHintEl.textContent;
                    }
                    updateRunValidity();
                    return;
                }).catch(function() {
                    return;
                });

                return cy;
            })
            .catch(Notification.exception);
    };

    return {
        init: init,
    };
});
