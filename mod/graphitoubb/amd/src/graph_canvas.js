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
 * Shared graph-canvas foundation (ADR-0002 núcleo) for the grafo/arbol tools.
 *
 * Copies the generic canvas behaviours from afd_editor.js into new code
 * (afd_editor.js is not touched — I2/D3). Supports three modes: build (student
 * draws), given (read-only teacher structure + answer control) and authoring
 * (teacher draws, writes a hidden input). Emits the tagged {answer_kind,…}
 * envelope through the existing snapshot path.
 *
 * @module     mod_graphitoubb/graph_canvas
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'mod_graphitoubb/graph_canvas_factory',
    'mod_graphitoubb/snapshot_controller',
    'mod_graphitoubb/repository',
    'core/notification',
    'core/str',
    'core/modal_save_cancel',
    'core/modal_events',
], function(Factory, SnapshotController, Repository, Notification, Str, ModalSaveCancel, ModalEvents) {

    /** Registered host ids (idempotent init). */
    var initialised = {};

    /**
     * Minimal HTML escape for modal bodies.
     *
     * @param {string} s
     * @return {string}
     */
    var esc = function(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };

    /**
     * Show a single-input Moodle modal (no window.prompt).
     *
     * @param {string} title
     * @param {string} label
     * @param {string} initial
     * @param {number} maxlen
     * @param {function} onConfirm receives trimmed value
     */
    var inputModal = function(title, label, initial, maxlen, onConfirm) {
        var body = '<div class="form-group mb-0"><label>' + esc(label) + '</label>'
            + '<input type="text" class="form-control gcanvas-input-field" value="' + esc(initial) + '"'
            + (maxlen ? ' maxlength="' + maxlen + '"' : '') + '></div>';
        ModalSaveCancel.create({title: title, body: body}).then(function(modal) {
            var root = modal.getRoot();
            var field = function() {
                return root[0].querySelector('.gcanvas-input-field');
            };
            root.on(ModalEvents.save, function() {
                var f = field();
                onConfirm(f ? f.value.trim() : '');
            });
            root.on(ModalEvents.shown, function() {
                var f = field();
                if (f) {
                    f.focus();
                    f.select();
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
     * Read the numeric data-* bound with fallback.
     *
     * @param {Element} host
     * @param {string} name dataset key
     * @param {number} fallback
     * @return {number}
     */
    var bound = function(host, name, fallback) {
        var v = parseInt(host.dataset[name], 10);
        return isNaN(v) ? fallback : v;
    };

    /**
     * Next stable id of form prefix+N not already used in cy.
     *
     * @param {object} cy
     * @param {string} prefix 'v' | 'e' | 'n'
     * @param {string} group 'nodes' | 'edges'
     * @return {string}
     */
    var nextId = function(cy, prefix, group) {
        var max = -1;
        var coll = group === 'edges' ? cy.edges() : cy.nodes();
        coll.forEach(function(el) {
            var m = el.id().match(new RegExp('^' + prefix + '(\\d+)$'));
            if (m) {
                max = Math.max(max, parseInt(m[1], 10));
            }
        });
        return prefix + (max + 1);
    };

    /**
     * Extract the canonical grafo structure from a Cytoscape instance.
     * Nodes/edges sorted by id for a reproducible payload hash.
     *
     * @param {object} cy
     * @param {boolean} directed
     * @return {{nodes: Array, edges: Array, directed: boolean}}
     */
    var extractGraph = function(cy, directed) {
        var nodes = [];
        cy.nodes().forEach(function(n) {
            nodes.push({id: n.id(), label: n.data('label') || n.id()});
        });
        var edges = [];
        cy.edges().forEach(function(e) {
            var edge = {id: e.id(), from: e.source().id(), to: e.target().id()};
            var w = e.data('weight');
            if (w !== null && w !== undefined && w !== '') {
                edge.weight = Number(w);
            }
            edges.push(edge);
        });
        nodes.sort(function(a, b) {
            return a.id < b.id ? -1 : (a.id > b.id ? 1 : 0);
        });
        edges.sort(function(a, b) {
            return a.id < b.id ? -1 : (a.id > b.id ? 1 : 0);
        });
        return {nodes: nodes, edges: edges, directed: !!directed};
    };

    /**
     * Extract the canonical arbol structure (L/R children + root).
     *
     * @param {object} cy
     * @return {{nodes: Array, edges: Array, root: (string|null)}}
     */
    var extractTree = function(cy) {
        var root = null;
        var nodes = [];
        cy.nodes().forEach(function(n) {
            if (n.hasClass('tree-root')) {
                root = n.id();
            }
            var node = {id: n.id(), label: n.data('label') || n.id()};
            var val = n.data('value');
            if (val !== null && val !== undefined && val !== '') {
                node.value = Number(val);
            }
            nodes.push(node);
        });
        var edges = [];
        cy.edges().forEach(function(e) {
            edges.push({id: e.id(), parent: e.source().id(), child: e.target().id(), side: e.data('side') || ''});
        });
        nodes.sort(function(a, b) {
            return a.id < b.id ? -1 : (a.id > b.id ? 1 : 0);
        });
        edges.sort(function(a, b) {
            return a.id < b.id ? -1 : (a.id > b.id ? 1 : 0);
        });
        return {nodes: nodes, edges: edges, root: root};
    };

    /**
     * Parse a data-* JSON attribute, returning null on empty/parse error.
     *
     * @param {Element} host
     * @param {string} name dataset key
     * @return {object|null}
     */
    var parseData = function(host, name) {
        var raw = host.dataset[name];
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    };

    /**
     * Initialise a graph canvas host.
     *
     * @param {number} attemptid
     * @param {number} instanceid
     * @param {number} schemaversion
     * @param {string} toolslug 'grafo' | 'arbol'
     */
    var init = function(attemptid, instanceid, schemaversion, toolslug) {
        var host = document.getElementById('graphitoubb-graph-' + instanceid);
        if (!host || initialised[host.id]) {
            return;
        }
        initialised[host.id] = true;

        var tool = host.dataset.tool || toolslug || 'grafo';
        var mode = host.dataset.mode || 'build';
        var type = host.dataset.type || '';
        var directed = host.dataset.directed === '1';
        var finished = host.dataset.finished === '1';
        var canvasEl = host.querySelector('.mod-graphitoubb-graph-canvas');
        var answerEl = host.querySelector('.mod-graphitoubb-graph-answer');
        var toolbarEl = host.querySelector('.mod-graphitoubb-graph-toolbar');

        // Resolve the initial payload for the canvas.
        var given = parseData(host, 'given');
        var snapEnvelope = parseData(host, 'snapshot');
        var initialPayload;
        if (mode === 'given') {
            initialPayload = given || {nodes: [], edges: []};
        } else if (mode === 'authoring') {
            initialPayload = given || {nodes: [], edges: []};
        } else { // build.
            if (snapEnvelope && snapEnvelope.graph) {
                initialPayload = snapEnvelope.graph;
            } else if (snapEnvelope && snapEnvelope.tree) {
                initialPayload = snapEnvelope.tree;
            } else {
                initialPayload = {nodes: [], edges: []};
            }
        }
        if (tool === 'grafo' && initialPayload.directed === undefined) {
            initialPayload.directed = directed;
        }

        var loading = canvasEl.querySelector('.mod-graphitoubb-loading');
        if (loading) {
            loading.parentNode.removeChild(loading);
        }

        var cy = Factory.create(canvasEl, initialPayload, {
            tool: tool,
            directed: directed,
            readonly: (mode === 'given'),
        });

        SnapshotController.init(host);

        // The most recent answer envelope, flushed synchronously before finishing
        // so a quick "Entregar" never grades a stale (debounced) snapshot.
        var latestEnvelope = null;

        /**
         * Emit the current answer envelope through the right sink.
         *
         * @param {object} envelope
         */
        var emit = function(envelope) {
            latestEnvelope = envelope;
            host.dispatchEvent(new CustomEvent('graphitoubb:change', {
                bubbles: true, detail: {envelope: envelope},
            }));
            // qtype sink (C1): write the envelope to the question's hidden input,
            // no autosave / WS (the quiz form owns submission).
            if (host.dataset.answerInput) {
                var qinput = document.querySelector('[name="' + host.dataset.answerInput + '"]');
                if (qinput) {
                    qinput.value = JSON.stringify(envelope);
                }
                return;
            }
            if (mode === 'authoring') {
                // Write the raw structure (not the envelope) to the hidden input.
                var inputName = (tool === 'arbol') ? 'given_tree' : 'given_graph';
                var hidden = document.querySelector('input[name="' + inputName + '"]');
                if (hidden) {
                    var structure = (tool === 'arbol') ? extractTree(cy) : extractGraph(cy, directed);
                    hidden.value = JSON.stringify(structure);
                }
                return;
            }
            // build/given → snapshot autosave path.
            SnapshotController.onchange(attemptid, envelope, schemaversion);
        };

        /**
         * Build + emit the structure envelope for build/authoring canvases.
         */
        var emitStructure = function() {
            if (tool === 'arbol') {
                emit({answer_kind: 'tree', tree: extractTree(cy)});
            } else {
                emit({answer_kind: 'graph', graph: extractGraph(cy, directed)});
            }
        };

        if (mode === 'build' || mode === 'authoring') {
            wireEditable(host, cy, tool, directed, finished, toolbarEl, emitStructure);
            // Persist the restored structure once so an unchanged submit still grades.
            if (mode === 'build') {
                emitStructure();
            }
        } else if (mode === 'given') {
            wireAnswerControl(host, cy, tool, type, answerEl, finished, emit, snapEnvelope);
        }

        wireZoom(host, cy);

        // Student finish button (mod activity only; absent in authoring/qtype).
        // Persist the final answer RELIABLY before finishing: cancel the pending
        // (debounced) autosave, then strict-save the latest envelope with retry —
        // the snapshot rate limit (1/second) would otherwise silently drop the last
        // change and grade a stale snapshot.
        if (!host.dataset.answerInput && !finished) {
            var finishBtn = document.querySelector('[data-region="graph-finish-btn"]');
            if (finishBtn) {
                finishBtn.addEventListener('click', function() {
                    finishBtn.disabled = true;
                    SnapshotController.cancel();
                    flushThenFinish(attemptid, schemaversion, latestEnvelope, finishBtn, 0);
                });
            }
        }
    };

    /**
     * Save the final answer (strict, retrying past the 1/sec rate limit) then finish.
     *
     * @param {number} attemptid
     * @param {number} schemaversion
     * @param {object|null} envelope the authoritative final answer
     * @param {Element} finishBtn
     * @param {number} attempt retry counter
     */
    var flushThenFinish = function(attemptid, schemaversion, envelope, finishBtn, attempt) {
        var save = envelope
            ? Repository.saveSnapshotStrict(attemptid, envelope, schemaversion)
            : Promise.resolve();
        save.then(function() {
            return Repository.finishAttempt(attemptid);
        }).then(function() {
            return Str.get_string('graph_finish_reload', 'mod_graphitoubb');
        }).then(function(msg) {
            Notification.addNotification({message: msg, type: 'success'});
            window.location.reload();
            return msg;
        }).catch(function(err) {
            // Most likely the 1/second snapshot rate limit — wait it out and retry.
            if (attempt < 3) {
                window.setTimeout(function() {
                    flushThenFinish(attemptid, schemaversion, envelope, finishBtn, attempt + 1);
                }, 1100);
                return;
            }
            finishBtn.disabled = false;
            Notification.exception(err);
        });
    };

    /**
     * Wire an editable canvas (build/authoring): add node/edge, delete, rename.
     *
     * @param {Element} host
     * @param {object} cy
     * @param {string} tool
     * @param {boolean} directed
     * @param {boolean} finished
     * @param {Element} toolbarEl
     * @param {function} emit
     */
    var wireEditable = function(host, cy, tool, directed, finished, toolbarEl, emit) {
        var gmode = 'idle';
        var pendingSource = null;

        var setMode = function(next) {
            gmode = next;
            pendingSource = null;
            cy.nodes().removeClass('selected-node');
            if (toolbarEl) {
                toolbarEl.querySelectorAll('[data-gmode]').forEach(function(b) {
                    var active = b.dataset.gmode === next;
                    b.classList.toggle('active', active);
                    b.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }
            var hint = host.querySelector('.mod-graphitoubb-graph-hint');
            if (hint) {
                Str.get_string('graph_hint_' + next, 'mod_graphitoubb').then(function(s) {
                    hint.textContent = s;
                    return s;
                }).catch(function() {
                    return null;
                });
            }
        };

        if (finished) {
            if (toolbarEl) {
                toolbarEl.querySelectorAll('button').forEach(function(b) {
                    b.disabled = true;
                });
            }
            cy.autoungrabify(true);
            return;
        }

        if (toolbarEl) {
            toolbarEl.addEventListener('click', function(e) {
                var btn = e.target.closest('[data-gmode],[data-gaction]');
                if (!btn) {
                    return;
                }
                if (btn.dataset.gaction === 'tidy') {
                    if (cy.nodes().length) {
                        cy.layout({name: 'cose', animate: true, padding: 40, idealEdgeLength: 90,
                            nodeRepulsion: 9000, fit: true}).run();
                    }
                    return;
                }
                if (btn.dataset.gaction === 'clear') {
                    clearConfirm(cy, emit);
                    return;
                }
                var m = btn.dataset.gmode;
                if (m) {
                    setMode(gmode === m ? 'idle' : m);
                }
            });
        }

        var addNode = function(pos) {
            if (cy.nodes().length >= bound(host, 'maxNodes', 20)) {
                notifyMax(host, 'err_graph_max_nodes', bound(host, 'maxNodes', 20));
                return null;
            }
            var id = nextId(cy, tool === 'arbol' ? 'n' : 'v', 'nodes');
            var label = id;
            cy.add({group: 'nodes', data: {id: id, label: label}, position: pos});
            return cy.$('#' + id);
        };

        var addEdge = function(sourceId, targetId, side) {
            if (cy.edges().length >= bound(host, 'maxEdges', 40)) {
                notifyMax(host, 'err_graph_max_edges', bound(host, 'maxEdges', 40));
                return;
            }
            var id = nextId(cy, 'e', 'edges');
            var data = {id: id, source: sourceId, target: targetId};
            if (tool === 'arbol') {
                data.side = side || '';
                data.label = side || '';
            }
            cy.add({group: 'edges', data: data});
            Factory.markParallelEdges(cy);
        };

        cy.on('tap', function(evt) {
            var target = evt.target;
            var isCanvas = (target === cy);
            var isNode = !isCanvas && typeof target.isNode === 'function' && target.isNode();
            var isEdge = !isCanvas && typeof target.isEdge === 'function' && target.isEdge();

            if (gmode === 'addnode' && isCanvas) {
                if (addNode(evt.position)) {
                    emit();
                }
            } else if (gmode === 'addedge' && isNode) {
                if (!pendingSource) {
                    pendingSource = target.id();
                    target.addClass('selected-node');
                } else {
                    var src = pendingSource;
                    pendingSource = null;
                    cy.nodes().removeClass('selected-node');
                    if (tool === 'arbol') {
                        pickSideThenAdd(src, target.id(), cy, addEdge, emit);
                    } else {
                        addEdge(src, target.id());
                        emit();
                    }
                }
            } else if (gmode === 'setroot' && isNode) {
                cy.nodes().removeClass('tree-root');
                target.addClass('tree-root');
                emit();
            } else if (gmode === 'delete' && (isNode || isEdge)) {
                cy.remove(target);
                Factory.markParallelEdges(cy);
                emit();
            }
        });

        // Double-tap a node in idle mode → rename label.
        var lastTap = {id: null, t: 0};
        cy.on('tap', 'node', function(evt) {
            if (gmode !== 'idle') {
                lastTap = {id: null, t: 0};
                return;
            }
            var node = evt.target;
            var now = (new Date()).getTime();
            if (lastTap.id === node.id() && (now - lastTap.t) < 350) {
                lastTap = {id: null, t: 0};
                renameNode(host, node, tool, emit);
            } else {
                lastTap = {id: node.id(), t: now};
            }
        });

        cy.on('dragfree', 'node', function() {
            // Position changes are not part of the graded structure; no emit needed.
        });

        setMode('idle');
    };

    /**
     * Rename a node's label (and, for arbol, its numeric value from the label).
     *
     * @param {Element} host
     * @param {object} node
     * @param {string} tool
     * @param {function} emit
     */
    var renameNode = function(host, node, tool, emit) {
        Str.get_strings([
            {key: 'graph_rename_title', component: 'mod_graphitoubb'},
            {key: 'graph_rename_label', component: 'mod_graphitoubb'},
        ]).then(function(s) {
            inputModal(s[0], s[1], node.data('label') || node.id(), bound(host, 'maxLabel', 12), function(value) {
                if (value === '') {
                    return;
                }
                node.data('label', value);
                if (tool === 'arbol') {
                    var num = parseInt(value, 10);
                    node.data('value', isNaN(num) ? null : num);
                }
                emit();
            });
            return s;
        }).catch(Notification.exception);
    };

    /**
     * Prompt for L/R side (arbol), enforcing ≤2 children and one-per-side.
     *
     * @param {string} parentId
     * @param {string} childId
     * @param {object} cy
     * @param {function} addEdge
     * @param {function} emit
     */
    var pickSideThenAdd = function(parentId, childId, cy, addEdge, emit) {
        var used = {L: false, R: false};
        cy.edges('[source = "' + parentId + '"]').forEach(function(e) {
            var s = e.data('side');
            if (s === 'L') {
                used.L = true;
            }
            if (s === 'R') {
                used.R = true;
            }
        });
        if (used.L && used.R) {
            Str.get_string('err_tree_two_children', 'mod_graphitoubb').then(function(m) {
                Notification.addNotification({message: m, type: 'warning'});
                return m;
            });
            return;
        }
        var body = '<div class="form-group mb-0">'
            + '<button type="button" class="btn btn-outline-primary mr-2 gcanvas-side" data-side="L"'
            + (used.L ? ' disabled' : '') + '>L</button>'
            + '<button type="button" class="btn btn-outline-primary gcanvas-side" data-side="R"'
            + (used.R ? ' disabled' : '') + '></button></div>';
        Str.get_string('graph_pick_side', 'mod_graphitoubb').then(function(title) {
            return ModalSaveCancel.create({title: title, body: body.replace('></button>', '>R</button>')})
                .then(function(modal) {
                    var root = modal.getRoot();
                    root[0].addEventListener('click', function(e) {
                        var b = e.target.closest('.gcanvas-side');
                        if (!b || b.disabled) {
                            return;
                        }
                        addEdge(parentId, childId, b.dataset.side);
                        emit();
                        modal.hide();
                    });
                    root.on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });
                    modal.show();
                    return modal;
                });
        }).catch(Notification.exception);
    };

    /**
     * Confirm-and-clear the whole canvas.
     *
     * @param {object} cy
     * @param {function} emit
     */
    var clearConfirm = function(cy, emit) {
        Str.get_strings([
            {key: 'graph_clear_title', component: 'mod_graphitoubb'},
            {key: 'graph_clear_body', component: 'mod_graphitoubb'},
        ]).then(function(s) {
            return ModalSaveCancel.create({title: s[0], body: s[1]}).then(function(modal) {
                modal.getRoot().on(ModalEvents.save, function() {
                    cy.elements().remove();
                    emit();
                });
                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });
                modal.show();
                return modal;
            });
        }).catch(Notification.exception);
    };

    /**
     * Wire the given-mode answer control (decision radios, traversal edge-picker,
     * arbol traversal_answer numeric field).
     *
     * @param {Element} host
     * @param {object} cy
     * @param {string} tool
     * @param {string} type
     * @param {Element} answerEl
     * @param {boolean} finished
     * @param {function} emit
     */
    var wireAnswerControl = function(host, cy, tool, type, answerEl, finished, emit, saved) {
        if (!answerEl) {
            return;
        }
        if (type === 'decision') {
            var radios = answerEl.querySelectorAll('input[type="radio"]');
            // Restore a saved boolean answer (review / resume).
            if (saved && saved.answer_kind === 'boolean') {
                radios.forEach(function(r) {
                    r.checked = (r.value === (saved.value ? 'true' : 'false'));
                });
            }
            radios.forEach(function(r) {
                r.disabled = finished;
                r.addEventListener('change', function() {
                    emit({answer_kind: 'boolean', value: r.value === 'true'});
                });
            });
        } else if (type === 'traversal') {
            wireTraversalPicker(host, cy, answerEl, finished, emit, saved);
        } else if (type === 'traversal_answer') {
            var field = answerEl.querySelector('.mod-graphitoubb-seq-input');
            if (field) {
                if (saved && saved.answer_kind === 'sequence' && Array.isArray(saved.values)) {
                    field.value = saved.values.join(', ');
                }
                field.disabled = finished;
                field.addEventListener('input', function() {
                    var values = field.value.split(/[\s,]+/).filter(function(x) {
                        return x !== '';
                    }).map(Number);
                    emit({answer_kind: 'sequence', values: values});
                });
            }
        }
    };

    /**
     * Traversal answer builder — TRACE THE WALK by clicking VERTICES in order.
     *
     * The student clicks the big vertex circles in the order they would visit them;
     * each step consumes an (unused) edge between the previous vertex and the new
     * one, so the answer requires actually reading the graph's adjacency (nothing is
     * spelled out). Non-adjacent clicks are rejected with a hint. Produces the
     * authoritative edge-id list the grader consumes.
     *
     * @param {Element} host
     * @param {object} cy
     * @param {Element} answerEl
     * @param {boolean} finished
     * @param {function} emit
     * @param {object|null} saved restored answer envelope (review/resume)
     */
    var wireTraversalPicker = function(host, cy, answerEl, finished, emit, saved) {
        var directed = host.dataset.directed === '1';
        var vpath = []; // vertex ids in visit order.
        var epath = []; // edge ids consumed between consecutive vertices.
        var walkEl = answerEl.querySelector('.mod-graphitoubb-seq-list');
        var hintEl = answerEl.querySelector('.mod-graphitoubb-seq-hint');

        var lbl = function(id) {
            return cy.$('#' + id).data('label') || id;
        };

        var render = function() {
            cy.edges().removeClass('answer-picked');
            cy.nodes().removeClass('answer-visited');
            epath.forEach(function(id, i) {
                var e = cy.$('#' + id);
                e.data('pickorder', i + 1);
                e.addClass('answer-picked');
            });
            vpath.forEach(function(id) {
                cy.$('#' + id).addClass('answer-visited');
            });
            if (walkEl) {
                walkEl.textContent = vpath.map(lbl).join(directed ? ' → ' : ' — ');
            }
            emit({answer_kind: 'sequence', edges: epath.slice(), vertices: vpath.slice()});
        };

        // Find an unused edge connecting vertex a→b (directed) or a—b (undirected).
        var findEdge = function(a, b) {
            var found = null;
            cy.edges().forEach(function(e) {
                if (found || epath.indexOf(e.id()) !== -1) {
                    return;
                }
                var s = e.source().id();
                var t = e.target().id();
                if ((s === a && t === b) || (!directed && s === b && t === a)) {
                    found = e;
                }
            });
            return found;
        };

        var showHint = function(key) {
            if (!hintEl) {
                return;
            }
            Str.get_string(key, 'mod_graphitoubb').then(function(m) {
                hintEl.textContent = m;
                return m;
            }).catch(function() {
                return null;
            });
        };

        // Restore a saved walk (review/resume) from its vertex list.
        if (saved && saved.answer_kind === 'sequence' && Array.isArray(saved.vertices)) {
            saved.vertices.forEach(function(vid) {
                if (cy.$('#' + vid).length === 0) {
                    return;
                }
                if (vpath.length === 0) {
                    vpath.push(vid);
                } else {
                    var e = findEdge(vpath[vpath.length - 1], vid);
                    if (e) {
                        epath.push(e.id());
                        vpath.push(vid);
                    }
                }
            });
        }

        if (!finished) {
            cy.on('tap', 'node', function(evt) {
                var v = evt.target.id();
                if (vpath.length === 0) {
                    vpath.push(v);
                    showHint('graph_walk_hint_next');
                    render();
                    return;
                }
                var last = vpath[vpath.length - 1];
                if (v === last) {
                    return; // Same vertex tapped twice — ignore.
                }
                var e = findEdge(last, v);
                if (!e) {
                    showHint('graph_walk_hint_notedge');
                    return;
                }
                epath.push(e.id());
                vpath.push(v);
                showHint('graph_walk_hint_next');
                render();
            });
            var clearBtn = answerEl.querySelector('.mod-graphitoubb-seq-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    vpath = [];
                    epath = [];
                    if (hintEl) {
                        hintEl.textContent = '';
                    }
                    render();
                });
            }
            var undoBtn = answerEl.querySelector('.mod-graphitoubb-seq-undo');
            if (undoBtn) {
                undoBtn.addEventListener('click', function() {
                    vpath.pop();
                    epath.pop();
                    render();
                });
            }
        }

        render();
    };

    /**
     * Find the edge closest to a model-space point, within a forgiving threshold
     * (~22px on screen, scaled by zoom). Returns null when nothing is close.
     *
     * @param {object} cy
     * @param {{x:number,y:number}} pos model-space tap position
     * @return {object|null} Cytoscape edge or null
     */
    var nearestEdge = function(cy, pos) {
        if (!pos) {
            return null;
        }
        var threshold = 22 / (cy.zoom() || 1); // model units ≈ 22 screen px.
        var best = null;
        var bestDist = Infinity;
        cy.edges().forEach(function(e) {
            var a = e.source().position();
            var b = e.target().position();
            var d = pointToSegment(pos, a, b);
            if (d < bestDist) {
                bestDist = d;
                best = e;
            }
        });
        return (best && bestDist <= threshold) ? best : null;
    };

    /**
     * Distance from point p to the segment ab.
     *
     * @param {{x:number,y:number}} p
     * @param {{x:number,y:number}} a
     * @param {{x:number,y:number}} b
     * @return {number}
     */
    var pointToSegment = function(p, a, b) {
        var dx = b.x - a.x;
        var dy = b.y - a.y;
        var len2 = dx * dx + dy * dy;
        if (len2 === 0) {
            return Math.hypot(p.x - a.x, p.y - a.y);
        }
        var t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / len2;
        t = Math.max(0, Math.min(1, t));
        var projx = a.x + t * dx;
        var projy = a.y + t * dy;
        return Math.hypot(p.x - projx, p.y - projy);
    };

    /**
     * Wire zoom/fit/reset controls if present.
     *
     * @param {Element} host
     * @param {object} cy
     */
    var wireZoom = function(host, cy) {
        var zc = host.querySelector('.mod-graphitoubb-zoom-controls');
        if (!zc) {
            return;
        }
        zc.addEventListener('click', function(e) {
            var btn = e.target.closest('.mod-graphitoubb-zoom-btn');
            if (!btn) {
                return;
            }
            var center = {x: cy.width() / 2, y: cy.height() / 2};
            var action = btn.dataset.zoom;
            if (action === 'in') {
                cy.zoom({level: cy.zoom() * 1.2, renderedPosition: center});
            } else if (action === 'out') {
                cy.zoom({level: cy.zoom() / 1.2, renderedPosition: center});
            } else if (action === 'fit') {
                if (cy.nodes().length) {
                    cy.fit(cy.nodes(), 40);
                }
            } else if (action === 'reset') {
                cy.zoom(1);
                cy.center();
            }
        });
    };

    /**
     * Show a localised "limit reached" warning.
     *
     * @param {Element} host
     * @param {string} key
     * @param {number} param
     */
    var notifyMax = function(host, key, param) {
        Str.get_string(key, 'mod_graphitoubb', param).then(function(m) {
            Notification.addNotification({message: m, type: 'error'});
            return m;
        }).catch(function() {
            return null;
        });
    };

    return {
        init: init,
    };
});
