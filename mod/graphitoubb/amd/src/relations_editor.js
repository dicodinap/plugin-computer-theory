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
 * Binary-relations editor (RF_05). Three equivalent surfaces — matrix, ordered
 * pairs and a directed graph — all editing one shared pair set, plus the four-
 * property checklist. Every surface normalises to the same {answer_kind:'relation',
 * representation, pairs, properties} envelope. Emitted through the snapshot path
 * (mod activity) or a hidden input (qtype host).
 *
 * @module     mod_graphitoubb/relations_editor
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'mod_graphitoubb/snapshot_controller',
    'mod_graphitoubb/repository',
    'core/notification',
], function(SnapshotController, Repository, Notification) {

    var initialised = {};
    var SVGNS = 'http://www.w3.org/2000/svg';

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

    var key = function(a, b) {
        return a + '\x1f' + b;
    };

    /**
     * Initialise a relations editor host.
     *
     * @param {number} instanceid host discriminator
     */
    var init = function(instanceid) {
        var host = document.getElementById('graphitoubb-relations-' + instanceid);
        if (!host || initialised[host.id]) {
            return;
        }
        initialised[host.id] = true;

        var attemptid = parseInt(host.dataset.attemptid, 10) || 0;
        var schemaversion = parseInt(host.dataset.schemaversion, 10) || 1;
        var baseset = parseData(host, 'baseset') || [];
        var finished = host.dataset.finished === '1';
        var answerInput = host.dataset.answerInput || null;
        var requiredRep = host.dataset.requiredRep || 'any';

        // Shared state.
        var pairs = {};       // "a\x1fb" → true.
        var props = {reflexive: false, symmetric: false, antisymmetric: false, transitive: false};
        var representation = (requiredRep !== 'any') ? requiredRep : 'matrix';
        var digraphSource = null; // pending source node for the digraph surface.

        // Restore from snapshot.
        var snap = parseData(host, 'snapshot');
        if (snap && snap.answer_kind === 'relation') {
            (snap.pairs || []).forEach(function(p) {
                if (Array.isArray(p) && p.length >= 2) {
                    pairs[key(String(p[0]), String(p[1]))] = true;
                }
            });
            if (snap.properties) {
                Object.keys(props).forEach(function(k) {
                    props[k] = !!snap.properties[k];
                });
            }
            if (snap.representation && requiredRep === 'any') {
                representation = snap.representation;
            }
        }

        var matrixWrap = host.querySelector('.mod-graphitoubb-rel-matrix-wrap');
        var pairsWrap = host.querySelector('.mod-graphitoubb-rel-pairs-wrap');
        var digraphWrap = host.querySelector('.mod-graphitoubb-rel-digraph-wrap');

        var pairsArray = function() {
            return Object.keys(pairs).map(function(k) {
                var parts = k.split('\x1f');
                return [parts[0], parts[1]];
            }).sort(function(a, b) {
                if (a[0] !== b[0]) {
                    return a[0] < b[0] ? -1 : 1;
                }
                return a[1] < b[1] ? -1 : (a[1] > b[1] ? 1 : 0);
            });
        };

        var buildEnvelope = function() {
            return {
                answer_kind: 'relation',
                representation: representation,
                pairs: pairsArray(),
                properties: {
                    reflexive: !!props.reflexive,
                    symmetric: !!props.symmetric,
                    antisymmetric: !!props.antisymmetric,
                    transitive: !!props.transitive,
                },
                schema_version: schemaversion,
            };
        };

        var latestEnvelope = null;
        var emit = function() {
            var env = buildEnvelope();
            latestEnvelope = env;
            if (answerInput) {
                var qinput = document.querySelector('[name="' + answerInput + '"]');
                if (qinput) {
                    qinput.value = JSON.stringify(env);
                }
                return;
            }
            SnapshotController.onchange(attemptid, env, schemaversion);
        };

        var toggle = function(a, b) {
            var k = key(a, b);
            if (pairs[k]) {
                delete pairs[k];
            } else {
                pairs[k] = true;
            }
            renderAll();
            emit();
        };

        // ---- Matrix surface ------------------------------------------------------
        var renderMatrix = function() {
            if (!matrixWrap) {
                return;
            }
            var table = document.createElement('table');
            table.className = 'mod-graphitoubb-rel-matrix';
            var thead = document.createElement('thead');
            var htr = document.createElement('tr');
            var corner = document.createElement('th');
            corner.textContent = 'R';
            htr.appendChild(corner);
            baseset.forEach(function(b) {
                var th = document.createElement('th');
                th.textContent = b;
                htr.appendChild(th);
            });
            thead.appendChild(htr);
            table.appendChild(thead);
            var tbody = document.createElement('tbody');
            baseset.forEach(function(a) {
                var tr = document.createElement('tr');
                var rh = document.createElement('th');
                rh.textContent = a;
                tr.appendChild(rh);
                baseset.forEach(function(b) {
                    var td = document.createElement('td');
                    var on = !!pairs[key(a, b)];
                    td.className = on ? 'rel-on' : '';
                    td.textContent = on ? '1' : '0';
                    td.dataset.a = a;
                    td.dataset.b = b;
                    if (!finished) {
                        td.addEventListener('click', function() {
                            toggle(a, b);
                        });
                    }
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            matrixWrap.innerHTML = '';
            matrixWrap.appendChild(table);
        };

        // ---- Pairs surface -------------------------------------------------------
        var renderPairs = function() {
            if (!pairsWrap) {
                return;
            }
            pairsWrap.innerHTML = '';
            if (!finished) {
                var builder = document.createElement('div');
                builder.className = 'form-inline mb-2';
                var selA = document.createElement('select');
                var selB = document.createElement('select');
                [selA, selB].forEach(function(sel) {
                    sel.className = 'form-control form-control-sm mr-1';
                    baseset.forEach(function(el) {
                        var opt = document.createElement('option');
                        opt.value = el;
                        opt.textContent = el;
                        sel.appendChild(opt);
                    });
                });
                var sep = document.createElement('span');
                sep.textContent = '→';
                sep.className = 'mx-1';
                var addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'btn btn-sm btn-primary ml-1 mod-graphitoubb-rel-addpair';
                addBtn.textContent = '+';
                addBtn.addEventListener('click', function() {
                    var a = selA.value;
                    var b = selB.value;
                    if (a !== '' && b !== '') {
                        pairs[key(a, b)] = true;
                        renderAll();
                        emit();
                    }
                });
                builder.appendChild(selA);
                builder.appendChild(sep);
                builder.appendChild(selB);
                builder.appendChild(addBtn);
                pairsWrap.appendChild(builder);
            }
            var list = document.createElement('ul');
            list.className = 'mod-graphitoubb-rel-pairs-list list-unstyled';
            pairsArray().forEach(function(p) {
                var li = document.createElement('li');
                li.textContent = '(' + p[0] + ', ' + p[1] + ') ';
                if (!finished) {
                    var del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'btn btn-sm btn-link text-danger p-0 mod-graphitoubb-rel-delpair';
                    del.textContent = '✕';
                    del.addEventListener('click', function() {
                        delete pairs[key(p[0], p[1])];
                        renderAll();
                        emit();
                    });
                    li.appendChild(del);
                }
                list.appendChild(li);
            });
            pairsWrap.appendChild(list);
        };

        // ---- Digraph surface (self-contained SVG) --------------------------------
        var renderDigraph = function() {
            if (!digraphWrap) {
                return;
            }
            digraphWrap.innerHTML = '';
            var n = baseset.length;
            var size = 320;
            var cx = size / 2;
            var cy = size / 2;
            var radius = size / 2 - 45;
            var pos = {};
            baseset.forEach(function(el, i) {
                var ang = (2 * Math.PI * i / Math.max(n, 1)) - Math.PI / 2;
                pos[el] = {x: cx + radius * Math.cos(ang), y: cy + radius * Math.sin(ang)};
            });

            var svg = document.createElementNS(SVGNS, 'svg');
            svg.setAttribute('width', size);
            svg.setAttribute('height', size);
            svg.setAttribute('class', 'mod-graphitoubb-rel-digraph');
            // Arrow marker.
            var defs = document.createElementNS(SVGNS, 'defs');
            var marker = document.createElementNS(SVGNS, 'marker');
            marker.setAttribute('id', 'rel-arrow-' + instanceid);
            marker.setAttribute('viewBox', '0 0 10 10');
            marker.setAttribute('refX', '9');
            marker.setAttribute('refY', '5');
            marker.setAttribute('markerWidth', '7');
            marker.setAttribute('markerHeight', '7');
            marker.setAttribute('orient', 'auto-start-reverse');
            var mpath = document.createElementNS(SVGNS, 'path');
            mpath.setAttribute('d', 'M 0 0 L 10 5 L 0 10 z');
            mpath.setAttribute('fill', '#495057');
            marker.appendChild(mpath);
            defs.appendChild(marker);
            svg.appendChild(defs);

            // Arcs.
            pairsArray().forEach(function(p) {
                var a = pos[p[0]];
                var b = pos[p[1]];
                if (!a || !b) {
                    return;
                }
                if (p[0] === p[1]) {
                    // Self-loop.
                    var loop = document.createElementNS(SVGNS, 'circle');
                    loop.setAttribute('cx', a.x);
                    loop.setAttribute('cy', a.y - 26);
                    loop.setAttribute('r', 12);
                    loop.setAttribute('fill', 'none');
                    loop.setAttribute('stroke', '#495057');
                    loop.setAttribute('stroke-width', '2');
                    svg.appendChild(loop);
                    return;
                }
                var dx = b.x - a.x;
                var dy = b.y - a.y;
                var len = Math.hypot(dx, dy) || 1;
                var ux = dx / len;
                var uy = dy / len;
                var line = document.createElementNS(SVGNS, 'line');
                line.setAttribute('x1', a.x + ux * 18);
                line.setAttribute('y1', a.y + uy * 18);
                line.setAttribute('x2', b.x - ux * 20);
                line.setAttribute('y2', b.y - uy * 20);
                line.setAttribute('stroke', '#495057');
                line.setAttribute('stroke-width', '2');
                line.setAttribute('marker-end', 'url(#rel-arrow-' + instanceid + ')');
                svg.appendChild(line);
            });

            // Nodes.
            baseset.forEach(function(el) {
                var p = pos[el];
                var g = document.createElementNS(SVGNS, 'g');
                g.setAttribute('class', 'mod-graphitoubb-rel-node');
                if (!finished) {
                    g.style.cursor = 'pointer';
                }
                var circle = document.createElementNS(SVGNS, 'circle');
                circle.setAttribute('cx', p.x);
                circle.setAttribute('cy', p.y);
                circle.setAttribute('r', 18);
                circle.setAttribute('fill', digraphSource === el ? '#ffd43b' : '#e7f5ff');
                circle.setAttribute('stroke', '#1971c2');
                circle.setAttribute('stroke-width', '2');
                g.appendChild(circle);
                var text = document.createElementNS(SVGNS, 'text');
                text.setAttribute('x', p.x);
                text.setAttribute('y', p.y + 5);
                text.setAttribute('text-anchor', 'middle');
                text.setAttribute('font-size', '14');
                text.textContent = el;
                g.appendChild(text);
                if (!finished) {
                    g.addEventListener('click', function() {
                        if (digraphSource === null) {
                            digraphSource = el;
                            renderDigraph();
                        } else {
                            var src = digraphSource;
                            digraphSource = null;
                            toggle(src, el); // renderAll re-runs.
                        }
                    });
                }
                svg.appendChild(g);
            });
            digraphWrap.appendChild(svg);
            var hint = document.createElement('p');
            hint.className = 'text-muted small mt-1';
            hint.textContent = digraphSource
                ? ('… → ? (' + digraphSource + ' selected, click the target)')
                : '';
            digraphWrap.appendChild(hint);
        };

        // ---- Properties checklist ------------------------------------------------
        var propInputs = host.querySelectorAll('.mod-graphitoubb-rel-prop');
        propInputs.forEach(function(inp) {
            inp.checked = !!props[inp.value];
            inp.disabled = finished;
            inp.addEventListener('change', function() {
                props[inp.value] = inp.checked;
                emit();
            });
        });

        // ---- Tabs ----------------------------------------------------------------
        var tabButtons = host.querySelectorAll('[data-rep-tab]');
        var setRep = function(rep) {
            representation = rep;
            digraphSource = null;
            tabButtons.forEach(function(b) {
                var active = b.dataset.repTab === rep;
                b.classList.toggle('active', active);
                b.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            if (matrixWrap) {
                matrixWrap.style.display = (rep === 'matrix') ? '' : 'none';
            }
            if (pairsWrap) {
                pairsWrap.style.display = (rep === 'pairs') ? '' : 'none';
            }
            if (digraphWrap) {
                digraphWrap.style.display = (rep === 'digraph') ? '' : 'none';
            }
            renderAll();
            emit();
        };
        tabButtons.forEach(function(b) {
            b.addEventListener('click', function() {
                setRep(b.dataset.repTab);
            });
        });

        var renderAll = function() {
            renderMatrix();
            renderPairs();
            renderDigraph();
        };

        renderAll();
        setRep(representation);

        // Seed once so an unchanged submit still grades / the quiz form has a value.
        if (answerInput) {
            var qin = document.querySelector('[name="' + answerInput + '"]');
            if (qin && !qin.value) {
                qin.value = JSON.stringify(buildEnvelope());
            }
        } else if (!finished) {
            emit();
        }

        // Finish button (mod activity student surface only).
        if (!answerInput && !finished) {
            var finishBtn = document.querySelector('[data-region="relations-finish-btn"]');
            if (finishBtn) {
                finishBtn.addEventListener('click', function() {
                    finishBtn.disabled = true;
                    SnapshotController.cancel();
                    flushThenFinish(attemptid, schemaversion, latestEnvelope || buildEnvelope(), finishBtn, 0);
                });
            }
        }
    };

    /**
     * Strict-save then finish (retrying past the 1/sec rate limit).
     *
     * @param {number} attemptid
     * @param {number} schemaversion
     * @param {object} envelope
     * @param {Element} finishBtn
     * @param {number} attempt
     */
    var flushThenFinish = function(attemptid, schemaversion, envelope, finishBtn, attempt) {
        Repository.saveSnapshotStrict(attemptid, envelope, schemaversion).then(function() {
            return Repository.finishAttempt(attemptid);
        }).then(function() {
            window.location.reload();
            return null;
        }).catch(function(err) {
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

    return {
        init: init,
    };
});
