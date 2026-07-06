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
        'toolbar_add_transition', 'rename_state_title', 'rename_state_label',
        'trace_play', 'trace_pause', 'trace_step',
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
     * Minimal HTML-escape for values injected into a modal body.
     *
     * @param {string} s
     * @return {string}
     */
    var escapeHtml = function(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };

    /**
     * Show a Moodle modal containing a single text input (replaces window.prompt,
     * completing G1). Calls onConfirm(value) when the user saves.
     *
     * @param {string} title    Resolved modal title.
     * @param {string} label    Resolved input label.
     * @param {string} initial  Initial input value.
     * @param {number} maxlen   maxlength attribute (0 = none).
     * @param {function} onConfirm Receives the trimmed input value.
     */
    var inputModal = function(title, label, initial, maxlen, onConfirm) {
        var body = '<div class="form-group mb-0">'
            + '<label class="mod-graphitoubb-input-modal-label">' + escapeHtml(label) + '</label>'
            + '<input type="text" class="form-control mod-graphitoubb-input-modal-field" '
            + 'value="' + escapeHtml(initial) + '"' + (maxlen ? ' maxlength="' + maxlen + '"' : '') + '>'
            + '</div>';
        ModalSaveCancel.create({title: title, body: body}).then(function(modal) {
            var root = modal.getRoot();
            var getField = function() {
                return root[0].querySelector('.mod-graphitoubb-input-modal-field');
            };
            root.on(ModalEvents.save, function() {
                var field = getField();
                onConfirm(field ? field.value.trim() : '');
            });
            root.on(ModalEvents.shown, function() {
                var field = getField();
                if (field) {
                    field.focus();
                    field.select();
                }
            });
            root.on(ModalEvents.hidden, function() {
                modal.destroy();
            });
            modal.show();
            return modal;
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
    var addState = function(cy, toolbarEl, position) {
        if (cy.nodes().length >= bound(toolbarEl, 'maxStates', 64)) {
            notify('error', 'err_max_states', bound(toolbarEl, 'maxStates', 64));
            return null;
        }
        var id = nextStateId(cy);
        cy.add({
            group: 'nodes',
            data: {id: id, label: id, start: false, final: false},
            position: position,
        });
        return id;
    };

    var handleAddState = function(cy, toolbarEl, evt) {
        addState(cy, toolbarEl, evt.position);
        Toolbar.setMode('idle');
    };

    /**
     * Validate and create a transition edge (shared by the pointer modal flow and
     * the keyboard form alternative, A14). Returns true on success.
     *
     * @param {object} cy
     * @param {Element|null} toolbarEl
     * @param {string} sourceId
     * @param {string} targetId
     * @param {string} rawSymbol
     * @return {boolean}
     */
    var createTransition = function(cy, toolbarEl, sourceId, targetId, rawSymbol) {
        var symbol = (rawSymbol || '').charAt(0);
        if (!symbol || !/^[a-zA-Z0-9]$/.test(symbol)) {
            return false;
        }
        if (!sourceId || !targetId || cy.$('#' + sourceId).length === 0 || cy.$('#' + targetId).length === 0) {
            return false;
        }
        if (cy.edges().length >= bound(toolbarEl, 'maxTransitions', 512)) {
            notify('error', 'err_max_transitions', bound(toolbarEl, 'maxTransitions', 512));
            return false;
        }
        var currentAlphabet = AlphabetUI.getAlphabet();
        var isNewSymbol = currentAlphabet.indexOf(symbol) === -1;
        if (isNewSymbol && currentAlphabet.length >= bound(toolbarEl, 'maxAlphabet', 16)) {
            notify('error', 'err_max_alphabet', bound(toolbarEl, 'maxAlphabet', 16));
            return false;
        }
        var isDuplicate = cy.edges().some(function(e) {
            return e.source().id() === sourceId && e.data('symbol') === symbol;
        });
        if (isDuplicate) {
            notify('warning', 'err_duplicate_transition', sourceId + " → '" + symbol + "'");
            return false;
        }
        if (isNewSymbol) {
            AlphabetUI.addSymbol(symbol);
        }
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
        return true;
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
        Toolbar.setMode('idle');
        var targetId = targetNode.id();

        // A3/G1: collect the symbol via a Moodle modal input, not window.prompt.
        inputModal(
            _str.toolbar_add_transition || 'Add transition',
            _str.transition_symbol_prompt || 'Transition symbol (1 alphanumeric character):',
            '',
            1,
            function(value) {
                createTransition(cy, toolbarEl, sourceId, targetId, value);
            }
        );
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
            '.mod-graphitoubb-reset-automaton-btn', '.mod-graphitoubb-tidy-btn',
            '.mod-graphitoubb-undo-btn', '.mod-graphitoubb-redo-btn'].forEach(function(sel) {
            var el = editorRoot.querySelector(sel);
            if (el) {
                el.disabled = true;
            }
        });
        // A14: lock the keyboard form alternative too.
        editorRoot.querySelectorAll('.mod-graphitoubb-kbd-panel button, '
            + '.mod-graphitoubb-kbd-panel select, .mod-graphitoubb-kbd-panel input')
            .forEach(function(el) {
                el.disabled = true;
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

                // A14: forward-declared; the keyboard panel reassigns it to repopulate
                // its state/transition selects whenever the graph changes.
                var refreshKbdSelects = function() {};

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

                // A7: tidy / auto-arrange the graph on demand (cose layout).
                var tidyBtn = editorRoot
                    ? editorRoot.querySelector('.mod-graphitoubb-tidy-btn')
                    : null;
                if (tidyBtn) {
                    tidyBtn.addEventListener('click', function() {
                        if (cy.nodes().length) {
                            cy.layout({
                                name: 'cose',
                                animate: true,
                                padding: 60,
                                idealEdgeLength: 90,
                                nodeRepulsion: 9000,
                                nodeOverlap: 24,
                                fit: true,
                            }).run();
                        }
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

                // A4: undo/redo over graph + alphabet mutations. `onMutation` is
                // referenced by the alphabet callback below (hoisted var) and the
                // cy change handler; it debounces so one user action = one history entry.
                var undoStack = [];
                var redoStack = [];
                var historyRestoring = false;
                var lastState = null;
                var mutationTimer = null;
                var undoBtn = editorRoot ? editorRoot.querySelector('.mod-graphitoubb-undo-btn') : null;
                var redoBtn = editorRoot ? editorRoot.querySelector('.mod-graphitoubb-redo-btn') : null;

                var captureState = function() {
                    return JSON.stringify({
                        elements: cy.elements().jsons(),
                        alphabet: (cy.scratch('alphabet') || []).slice(),
                    });
                };
                var updateHistoryButtons = function() {
                    if (undoBtn) {
                        undoBtn.disabled = undoStack.length === 0;
                    }
                    if (redoBtn) {
                        redoBtn.disabled = redoStack.length === 0;
                    }
                };
                var restoreState = function(stateStr) {
                    var state;
                    try {
                        state = JSON.parse(stateStr);
                    } catch (e) {
                        return;
                    }
                    historyRestoring = true;
                    cy.elements().remove();
                    cy.add(state.elements);
                    cy.scratch('alphabet', (state.alphabet || []).slice());
                    historyRestoring = false;
                    AlphabetUI.refresh();
                    updateRunValidity();
                    updateHistoryButtons();
                    SnapshotController.onchange(attemptid, extractCanonical(cy), schemaversion);
                };
                var recordMutation = function() {
                    if (lastState !== null) {
                        undoStack.push(lastState);
                        if (undoStack.length > 50) {
                            undoStack.shift();
                        }
                    }
                    lastState = captureState();
                    redoStack = [];
                    updateHistoryButtons();
                };
                var onMutation = function() {
                    if (historyRestoring) {
                        return;
                    }
                    clearTimeout(mutationTimer);
                    mutationTimer = setTimeout(recordMutation, 80);
                };
                var undo = function() {
                    if (!undoStack.length) {
                        return;
                    }
                    redoStack.push(captureState());
                    var prev = undoStack.pop();
                    lastState = prev;
                    restoreState(prev);
                };
                var redo = function() {
                    if (!redoStack.length) {
                        return;
                    }
                    undoStack.push(captureState());
                    var next = redoStack.pop();
                    lastState = next;
                    restoreState(next);
                };
                if (undoBtn) {
                    undoBtn.addEventListener('click', undo);
                }
                if (redoBtn) {
                    redoBtn.addEventListener('click', redo);
                }
                document.addEventListener('keydown', function(e) {
                    if (!(e.ctrlKey || e.metaKey)) {
                        return;
                    }
                    var tag = (e.target && e.target.tagName) || '';
                    if (tag === 'INPUT' || tag === 'TEXTAREA') {
                        return;
                    }
                    var k = (e.key || '').toLowerCase();
                    if (k === 'z') {
                        if (e.shiftKey) {
                            redo();
                        } else {
                            undo();
                        }
                        e.preventDefault();
                    } else if (k === 'y') {
                        redo();
                        e.preventDefault();
                    }
                });
                cy.on('dragfree', 'node', onMutation);

                // Always init AlphabetUI with cy so getAlphabet()/addSymbol() work in handlers.
                AlphabetUI.init(
                    editorRoot ? editorRoot.querySelector('.mod-graphitoubb-alphabet-panel') : null,
                    cy,
                    function() {
                        SnapshotController.onchange(attemptid, extractCanonical(cy), schemaversion);
                        updateRunValidity();
                        onMutation();
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

                    // A10: step-by-step trace playback over the simulation result.
                    var traceControlsEl = simPanel.querySelector('.mod-graphitoubb-trace-controls');
                    var traceStepEl = simPanel.querySelector('.mod-graphitoubb-trace-step');
                    var playBtn = simPanel.querySelector('.mod-graphitoubb-trace-play');
                    var traceState = null;
                    var traceTimer = null;

                    var clearTraceHighlights = function() {
                        cy.nodes().removeClass('trace-visited');
                        cy.edges().removeClass('trace-edge');
                    };
                    var findEdge = function(from, sym, to) {
                        var match = null;
                        cy.edges().forEach(function(e) {
                            if (!match && e.source().id() === from && e.target().id() === to
                                    && e.data('symbol') === sym) {
                                match = e;
                            }
                        });
                        return match;
                    };
                    var setPlayLabel = function(playing) {
                        if (!playBtn) {
                            return;
                        }
                        playBtn.textContent = playing ? '⏸' : '▶';
                        var key = playing ? 'trace_pause' : 'trace_play';
                        if (_str[key]) {
                            playBtn.setAttribute('aria-label', _str[key]);
                            playBtn.setAttribute('title', _str[key]);
                        }
                    };
                    var renderStep = function(i) {
                        if (!traceState) {
                            return;
                        }
                        var trace = traceState.trace;
                        i = Math.max(0, Math.min(i, trace.length - 1));
                        traceState.pos = i;
                        clearTraceHighlights();
                        for (var k = 0; k <= i; k++) {
                            cy.$('#' + trace[k]).addClass('trace-visited');
                        }
                        if (i > 0) {
                            var edge = findEdge(trace[i - 1], traceState.word[i - 1], trace[i]);
                            if (edge) {
                                edge.addClass('trace-edge');
                            }
                        }
                        if (traceStepEl) {
                            // Synchronous, order-safe formatting from the prefetched
                            // template (avoids out-of-order async updates while stepping).
                            var tpl = _str.trace_step || 'Step {$a->i} of {$a->n}';
                            traceStepEl.textContent = tpl
                                .replace('{$a->i}', i).replace('{$a->n}', trace.length - 1);
                        }
                        if (editorRoot) {
                            editorRoot.classList.remove('trace-accept', 'trace-reject');
                            if (i === trace.length - 1) {
                                editorRoot.classList.add(traceState.accepted ? 'trace-accept' : 'trace-reject');
                            }
                        }
                    };
                    var pauseTrace = function() {
                        if (traceTimer) {
                            clearInterval(traceTimer);
                            traceTimer = null;
                        }
                        setPlayLabel(false);
                    };
                    var playTrace = function() {
                        if (!traceState) {
                            return;
                        }
                        pauseTrace();
                        if (traceState.pos >= traceState.trace.length - 1) {
                            renderStep(0);
                        }
                        setPlayLabel(true);
                        traceTimer = setInterval(function() {
                            if (!traceState || traceState.pos >= traceState.trace.length - 1) {
                                pauseTrace();
                                return;
                            }
                            renderStep(traceState.pos + 1);
                        }, 600);
                    };

                    if (traceControlsEl) {
                        traceControlsEl.addEventListener('click', function(e) {
                            var btn = e.target.closest('[data-step]');
                            if (!btn || !traceState) {
                                return;
                            }
                            var action = btn.dataset.step;
                            if (action === 'playpause') {
                                if (traceTimer) {
                                    pauseTrace();
                                } else {
                                    playTrace();
                                }
                                return;
                            }
                            pauseTrace();
                            if (action === 'first') {
                                renderStep(0);
                            } else if (action === 'prev') {
                                renderStep(traceState.pos - 1);
                            } else if (action === 'next') {
                                renderStep(traceState.pos + 1);
                            } else if (action === 'last') {
                                renderStep(traceState.trace.length - 1);
                            }
                        });
                    }

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

                            // A10: drive the trace through the step controls, auto-playing
                            // once; the student can then pause, step and replay.
                            pauseTrace();
                            traceState = {
                                trace: result.trace,
                                word: word,
                                accepted: result.accepted,
                                pos: 0,
                            };
                            if (traceControlsEl) {
                                traceControlsEl.hidden = false;
                            }
                            renderStep(0);
                            playTrace();

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
                    onMutation();
                    refreshKbdSelects();
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

                // A8: double-tap a state (in idle mode) to rename its label via the
                // shared input modal — respecting MAX_LABEL_LENGTH.
                var promptRenameNode = function(node) {
                    inputModal(
                        _str.rename_state_title || 'Rename state',
                        _str.rename_state_label || 'State label',
                        node.data('label') || node.id(),
                        bound(toolbarEl, 'maxLabelLength', 32),
                        function(value) {
                            if (value) {
                                node.data('label', value);
                            }
                        }
                    );
                };
                var lastTap = {id: null, t: 0};
                cy.on('tap', 'node', function(evt) {
                    if (Toolbar.getMode() !== 'idle') {
                        lastTap = {id: null, t: 0};
                        return;
                    }
                    var node = evt.target;
                    var now = (new Date()).getTime();
                    if (lastTap.id === node.id() && (now - lastTap.t) < 350) {
                        lastTap = {id: null, t: 0};
                        promptRenameNode(node);
                    } else {
                        lastTap = {id: node.id(), t: now};
                    }
                });

                // A14/E1: keyboard-accessible form alternative to the pointer canvas.
                var kbdPanel = editorRoot ? editorRoot.querySelector('.mod-graphitoubb-kbd-panel') : null;
                if (kbdPanel) {
                    var kbdState = kbdPanel.querySelector('.mod-graphitoubb-kbd-state');
                    var kbdFrom = kbdPanel.querySelector('.mod-graphitoubb-kbd-from');
                    var kbdTo = kbdPanel.querySelector('.mod-graphitoubb-kbd-to');
                    var kbdSymbol = kbdPanel.querySelector('.mod-graphitoubb-kbd-symbol');

                    var fillSelect = function(sel) {
                        if (!sel) {
                            return;
                        }
                        var prev = sel.value;
                        sel.innerHTML = '';
                        cy.nodes().forEach(function(n) {
                            var opt = document.createElement('option');
                            opt.value = n.id();
                            opt.textContent = n.data('label') || n.id();
                            sel.appendChild(opt);
                        });
                        if (prev) {
                            sel.value = prev;
                        }
                    };
                    refreshKbdSelects = function() {
                        fillSelect(kbdState);
                        fillSelect(kbdFrom);
                        fillSelect(kbdTo);
                    };
                    refreshKbdSelects();

                    kbdPanel.addEventListener('click', function(e) {
                        var btn = e.target.closest('[data-kbd]');
                        if (!btn) {
                            return;
                        }
                        var action = btn.dataset.kbd;
                        if (action === 'add-state') {
                            var n = cy.nodes().length;
                            addState(cy, toolbarEl, {x: 80 + (n % 5) * 90, y: 80 + Math.floor(n / 5) * 90});
                        } else if (action === 'set-start' && kbdState && kbdState.value) {
                            var s = cy.$('#' + kbdState.value);
                            if (s.length) {
                                handleSetStart(cy, s);
                            }
                        } else if (action === 'toggle-final' && kbdState && kbdState.value) {
                            var f = cy.$('#' + kbdState.value);
                            if (f.length) {
                                handleToggleFinal(f);
                            }
                        } else if (action === 'delete-state' && kbdState && kbdState.value) {
                            var d = cy.$('#' + kbdState.value);
                            if (d.length) {
                                confirmAndDelete(cy, d);
                            }
                        } else if (action === 'add-transition') {
                            if (createTransition(cy, toolbarEl, kbdFrom.value, kbdTo.value, kbdSymbol.value)) {
                                kbdSymbol.value = '';
                            }
                        }
                    });
                }

                // A4: set the undo baseline AFTER the saved automaton is loaded, so
                // the initial state is not itself an undoable step.
                lastState = captureState();
                updateHistoryButtons();

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
