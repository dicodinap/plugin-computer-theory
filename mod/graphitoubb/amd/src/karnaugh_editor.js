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
 * Two-stage Karnaugh-map editor (RF_04). Stage 1: transfer the given truth table
 * into the Gray-coded grid with a "Verificar mapa" self-check. Stage 2: select
 * 1-cells and create groups; the minimal SOP form assembles live as the OR of the
 * group terms. Emits the {answer_kind:'kmap', map, groups} envelope through the
 * existing snapshot path (mod activity) or a hidden input (qtype host).
 *
 * @module     mod_graphitoubb/karnaugh_editor
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'mod_graphitoubb/snapshot_controller',
    'mod_graphitoubb/repository',
    'core/notification',
    'core/str',
], function(SnapshotController, Repository, Notification, Str) {

    /** Idempotent init guard by host id. */
    var initialised = {};

    /** Group colour palette (cycled). */
    var PALETTE = ['#e74c3c', '#3498db', '#2ecc71', '#e67e22', '#9b59b6',
        '#1abc9c', '#f39c12', '#34495e', '#c0392b', '#16a085'];

    /**
     * Reflected Gray-code sequence of 2^b integers.
     *
     * @param {number} b bit count
     * @return {number[]}
     */
    var gray = function(b) {
        var out = [];
        var n = 1 << b;
        for (var k = 0; k < n; k++) {
            out.push(k ^ (k >> 1));
        }
        return out;
    };

    /**
     * Zero-padded binary string of value over width bits.
     *
     * @param {number} value
     * @param {number} width
     * @return {string}
     */
    var bin = function(value, width) {
        var s = value.toString(2);
        while (s.length < width) {
            s = '0' + s;
        }
        return s;
    };

    /**
     * Population count.
     *
     * @param {number} x
     * @return {number}
     */
    var popcount = function(x) {
        var c = 0;
        while (x > 0) {
            c += x & 1;
            x >>= 1;
        }
        return c;
    };

    /**
     * Is the cell set a legal power-of-two axis-aligned sub-cube (Gray + wrap)?
     *
     * @param {number[]} cells
     * @return {boolean}
     */
    var isSubcube = function(cells) {
        var size = cells.length;
        if (size === 0 || (size & (size - 1)) !== 0) {
            return false;
        }
        var and = cells[0];
        var or = cells[0];
        cells.forEach(function(c) {
            and &= c;
            or |= c;
        });
        return size === (1 << popcount(or ^ and));
    };

    /**
     * Derive the product-term text of a sub-cube group.
     *
     * @param {number[]} cells
     * @param {string[]} varnames MSB→LSB
     * @param {number} nvars
     * @return {string}
     */
    var termText = function(cells, varnames, nvars) {
        var and = cells[0];
        var or = cells[0];
        cells.forEach(function(c) {
            and &= c;
            or |= c;
        });
        var free = or ^ and;
        var parts = [];
        for (var pos = 0; pos < nvars; pos++) {
            var bit = nvars - 1 - pos;
            if (free & (1 << bit)) {
                continue;
            }
            var negated = ((and >> bit) & 1) === 0;
            parts.push((negated ? '¬' : '') + (varnames[pos] || String.fromCharCode(65 + pos)));
        }
        return parts.length ? parts.join('') : '1';
    };

    /**
     * Parse a JSON data-* attribute; null on empty/parse error.
     *
     * @param {Element} host
     * @param {string} name
     * @return {*}
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
     * Initialise a karnaugh editor host.
     *
     * @param {number} instanceid host discriminator
     */
    var init = function(instanceid) {
        var host = document.getElementById('graphitoubb-karnaugh-' + instanceid);
        if (!host || initialised[host.id]) {
            return;
        }
        initialised[host.id] = true;

        var attemptid = parseInt(host.dataset.attemptid, 10) || 0;
        var schemaversion = parseInt(host.dataset.schemaversion, 10) || 1;
        var nvars = parseInt(host.dataset.nvars, 10) || 2;
        var varnames = parseData(host, 'varnames') || [];
        var minterms = parseData(host, 'minterms') || [];
        var finished = host.dataset.finished === '1';
        var answerInput = host.dataset.answerInput || null;
        var mintset = {};
        minterms.forEach(function(m) {
            mintset[m] = true;
        });

        // State.
        var cells = {};       // index → 1|0 (student fill).
        var groups = [];      // [{id, cells:[]}].
        var selection = {};   // index → true (stage 2 pending selection).
        var groupSeq = 0;
        var editorMode = 'fill'; // 'fill' | 'group'.

        // Restore from snapshot envelope.
        var snap = parseData(host, 'snapshot');
        if (snap && snap.answer_kind === 'kmap') {
            if (snap.map && snap.map.cells) {
                Object.keys(snap.map.cells).forEach(function(k) {
                    cells[parseInt(k, 10)] = parseInt(snap.map.cells[k], 10);
                });
            }
            if (Array.isArray(snap.groups)) {
                groups = snap.groups.map(function(g, i) {
                    var id = g.id || ('g' + i);
                    var m = /g(\d+)/.exec(id);
                    if (m) {
                        groupSeq = Math.max(groupSeq, parseInt(m[1], 10) + 1);
                    }
                    return {id: id, cells: (g.cells || []).map(Number)};
                });
            }
        }

        var latestEnvelope = null;

        var buildEnvelope = function() {
            var cellmap = {};
            Object.keys(cells).forEach(function(k) {
                cellmap[k] = cells[k];
            });
            return {
                answer_kind: 'kmap',
                map: {cells: cellmap},
                groups: groups.map(function(g) {
                    return {id: g.id, cells: g.cells.slice()};
                }),
                schema_version: schemaversion,
            };
        };

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

        // ---- Grid geometry -------------------------------------------------------
        var rowbits = Math.floor(nvars / 2);
        var colbits = nvars - rowbits;
        var rowSeq = gray(rowbits);
        var colSeq = gray(colbits);
        var rowVars = varnames.slice(0, rowbits);
        var colVars = varnames.slice(rowbits);

        var gridHost = host.querySelector('.mod-graphitoubb-kmap-grid');
        var groupsList = host.querySelector('.mod-graphitoubb-kmap-groups');
        var minimalForm = host.querySelector('.mod-graphitoubb-kmap-minimal');
        var verifyResult = host.querySelector('.mod-graphitoubb-kmap-verify-result');

        var cellEls = {}; // index → td element.

        var renderGrid = function() {
            var table = document.createElement('table');
            table.className = 'mod-graphitoubb-kmap-table';
            // Header row.
            var thead = document.createElement('thead');
            var htr = document.createElement('tr');
            var corner = document.createElement('th');
            corner.className = 'mod-graphitoubb-kmap-corner';
            corner.textContent = (rowVars.join('') || '') + '\\' + (colVars.join('') || '');
            htr.appendChild(corner);
            colSeq.forEach(function(cg) {
                var th = document.createElement('th');
                th.textContent = bin(cg, colbits);
                htr.appendChild(th);
            });
            thead.appendChild(htr);
            table.appendChild(thead);
            var tbody = document.createElement('tbody');
            rowSeq.forEach(function(rg) {
                var tr = document.createElement('tr');
                var rh = document.createElement('th');
                rh.textContent = bin(rg, rowbits);
                tr.appendChild(rh);
                colSeq.forEach(function(cg) {
                    var index = (rg << colbits) | cg;
                    var td = document.createElement('td');
                    td.className = 'mod-graphitoubb-kmap-cell';
                    td.dataset.index = index;
                    cellEls[index] = td;
                    tr.appendChild(td);
                    if (!finished) {
                        td.addEventListener('click', function() {
                            onCellClick(index);
                        });
                    }
                });
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            gridHost.innerHTML = '';
            gridHost.appendChild(table);
            refreshCells();
        };

        var refreshCells = function() {
            Object.keys(cellEls).forEach(function(idxStr) {
                var index = parseInt(idxStr, 10);
                var td = cellEls[index];
                var val = cells[index];
                td.textContent = (val === undefined || val === null) ? '' : String(val);
                td.classList.toggle('kmap-one', val === 1);
                td.classList.toggle('kmap-zero', val === 0);
                td.classList.toggle('kmap-selected', !!selection[index]);
                // Colour badges for groups covering this cell.
                td.classList.remove('kmap-grouped');
                var badge = td.querySelector('.kmap-badges');
                if (badge) {
                    badge.parentNode.removeChild(badge);
                }
                var covering = [];
                groups.forEach(function(g, gi) {
                    if (g.cells.indexOf(index) !== -1) {
                        covering.push(gi);
                    }
                });
                if (covering.length) {
                    td.classList.add('kmap-grouped');
                    var b = document.createElement('span');
                    b.className = 'kmap-badges';
                    covering.forEach(function(gi) {
                        var dot = document.createElement('span');
                        dot.className = 'kmap-dot';
                        dot.style.background = PALETTE[gi % PALETTE.length];
                        b.appendChild(dot);
                    });
                    td.appendChild(b);
                }
            });
        };

        var onCellClick = function(index) {
            if (editorMode === 'fill') {
                // Cycle blank → 1 → 0 → blank.
                var cur = cells[index];
                if (cur === undefined || cur === null) {
                    cells[index] = 1;
                } else if (cur === 1) {
                    cells[index] = 0;
                } else {
                    delete cells[index];
                }
                if (verifyResult) {
                    verifyResult.textContent = '';
                }
                refreshCells();
                emit();
            } else {
                // Group mode: toggle selection.
                if (selection[index]) {
                    delete selection[index];
                } else {
                    selection[index] = true;
                }
                refreshCells();
            }
        };

        var renderGroups = function() {
            if (!groupsList) {
                return;
            }
            groupsList.innerHTML = '';
            groups.forEach(function(g, gi) {
                var li = document.createElement('li');
                li.className = 'mod-graphitoubb-kmap-group-item';
                var dot = document.createElement('span');
                dot.className = 'kmap-dot';
                dot.style.background = PALETTE[gi % PALETTE.length];
                li.appendChild(dot);
                var label = document.createElement('span');
                var legal = isSubcube(g.cells);
                var allOnes = g.cells.every(function(c) {
                    return mintset[c];
                });
                var term = legal ? termText(g.cells, varnames, nvars) : '—';
                label.className = 'kmap-group-term' + ((legal && allOnes) ? '' : ' text-danger');
                label.textContent = term + ' {' + g.cells.join(',') + '}'
                    + ((legal && allOnes) ? '' : ' ⚠');
                li.appendChild(label);
                if (!finished) {
                    var del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'btn btn-sm btn-link text-danger mod-graphitoubb-kmap-delgroup';
                    del.textContent = '✕';
                    del.addEventListener('click', function() {
                        groups.splice(gi, 1);
                        renderGroups();
                        refreshCells();
                        emit();
                    });
                    li.appendChild(del);
                }
                groupsList.appendChild(li);
            });
            renderMinimal();
        };

        var renderMinimal = function() {
            if (!minimalForm) {
                return;
            }
            var terms = [];
            groups.forEach(function(g) {
                if (isSubcube(g.cells) && g.cells.every(function(c) {
                    return mintset[c];
                })) {
                    terms.push(termText(g.cells, varnames, nvars));
                }
            });
            var f = terms.length ? terms.join(' + ') : '—';
            minimalForm.textContent = 'f = ' + f;
        };

        // ---- Toolbar wiring ------------------------------------------------------
        var modeButtons = host.querySelectorAll('[data-kmode]');
        var setMode = function(m) {
            editorMode = m;
            selection = {};
            modeButtons.forEach(function(b) {
                var active = b.dataset.kmode === m;
                b.classList.toggle('active', active);
                b.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            var groupTools = host.querySelector('.mod-graphitoubb-kmap-grouptools');
            if (groupTools) {
                groupTools.style.display = (m === 'group') ? '' : 'none';
            }
            var fillTools = host.querySelector('.mod-graphitoubb-kmap-filltools');
            if (fillTools) {
                fillTools.style.display = (m === 'fill') ? '' : 'none';
            }
            refreshCells();
        };
        if (!finished) {
            modeButtons.forEach(function(b) {
                b.addEventListener('click', function() {
                    setMode(b.dataset.kmode);
                });
            });

            var verifyBtn = host.querySelector('.mod-graphitoubb-kmap-verify');
            if (verifyBtn) {
                verifyBtn.addEventListener('click', function() {
                    doVerify();
                });
            }
            var addGroupBtn = host.querySelector('.mod-graphitoubb-kmap-addgroup');
            if (addGroupBtn) {
                addGroupBtn.addEventListener('click', function() {
                    var sel = Object.keys(selection).map(Number).sort(function(a, b) {
                        return a - b;
                    });
                    if (!sel.length) {
                        return;
                    }
                    groups.push({id: 'g' + (groupSeq++), cells: sel});
                    selection = {};
                    renderGroups();
                    refreshCells();
                    emit();
                });
            }
            var clearSelBtn = host.querySelector('.mod-graphitoubb-kmap-clearsel');
            if (clearSelBtn) {
                clearSelBtn.addEventListener('click', function() {
                    selection = {};
                    refreshCells();
                });
            }
        }

        var doVerify = function() {
            var wrong = 0;
            var blank = 0;
            var total = 1 << nvars;
            for (var i = 0; i < total; i++) {
                var expected = mintset[i] ? 1 : 0;
                var got = cells[i];
                var td = cellEls[i];
                td.classList.remove('kmap-verify-ok', 'kmap-verify-bad');
                if (got === undefined || got === null) {
                    blank++;
                    td.classList.add('kmap-verify-bad');
                } else if (got === expected) {
                    td.classList.add('kmap-verify-ok');
                } else {
                    wrong++;
                    td.classList.add('kmap-verify-bad');
                }
            }
            if (verifyResult) {
                var key = (wrong === 0 && blank === 0)
                    ? 'kmap_verify_ok'
                    : 'kmap_verify_errors';
                Str.get_string(key, 'mod_graphitoubb', {wrong: wrong, blank: blank}).then(function(s) {
                    verifyResult.textContent = s;
                    verifyResult.className = 'mod-graphitoubb-kmap-verify-result '
                        + ((wrong === 0 && blank === 0) ? 'text-success' : 'text-danger');
                    return s;
                }).catch(function() {
                    return null;
                });
            }
        };

        // Initial paint.
        renderGrid();
        renderGroups();
        setMode('fill');
        // Persist restored state once so an unchanged submit still grades.
        if (!finished && !answerInput) {
            emit();
        } else if (answerInput) {
            // Seed the hidden input for the quiz form.
            var qin = document.querySelector('[name="' + answerInput + '"]');
            if (qin && !qin.value) {
                qin.value = JSON.stringify(buildEnvelope());
            }
        }

        // Finish button (mod activity student surface only).
        if (!answerInput && !finished) {
            var finishBtn = document.querySelector('[data-region="karnaugh-finish-btn"]');
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
     * Strict-save the final envelope (retrying past the 1/sec rate limit) then finish.
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
