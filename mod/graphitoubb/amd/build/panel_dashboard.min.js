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
 * Teacher panel — 4-tab dashboard controller.
 *
 * Wires ARIA-compliant tab switching, lazy-loads WS data per tab,
 * renders heatmap cells with colour scale, and opens student detail drawer.
 *
 * @module     mod_graphitoubb/panel_dashboard
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/str',
    'mod_graphitoubb/repository',
    'core/modal_save_cancel',
    'core/modal_events',
    'core/notification',
], function(Str, Repository, ModalSaveCancel, ModalEvents, Notification) {

    'use strict';

    // -------------------------------------------------------------------------
    // Colour scale per spec §7.
    // -------------------------------------------------------------------------
    var HEATMAP_SCALE = [
        {min:  0, max: 24, bg: '#c0392b', fg: '#fff'},
        {min: 25, max: 49, bg: '#e67e22', fg: '#fff'},
        {min: 50, max: 74, bg: '#f1c40f', fg: '#000'},
        {min: 75, max: 89, bg: '#7dcea0', fg: '#000'},
        {min: 90, max: 100, bg: '#27ae60', fg: '#fff'},
    ];

    /**
     * Return the background/foreground colour for a percentage value.
     *
     * @param {number} pct  0–100
     * @return {{bg: string, fg: string}}
     */
    function heatColour(pct) {
        for (var i = 0; i < HEATMAP_SCALE.length; i++) {
            if (pct >= HEATMAP_SCALE[i].min && pct <= HEATMAP_SCALE[i].max) {
                return HEATMAP_SCALE[i];
            }
        }
        return HEATMAP_SCALE[HEATMAP_SCALE.length - 1];
    }

    // -------------------------------------------------------------------------
    // Utility: format seconds as m:ss.
    // -------------------------------------------------------------------------
    function fmtSeconds(sec) {
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    // -------------------------------------------------------------------------
    // Utility: format fraction as percentage string.
    // -------------------------------------------------------------------------
    function fmtFraction(f) {
        return (f * 100).toFixed(1) + '%';
    }

    // -------------------------------------------------------------------------
    // Module state.
    // -------------------------------------------------------------------------
    var state = {
        root: null,
        instanceid: 0,
        contextid: 0,
        wsfunctions: {},
        loaded: {summary: false, student: false, heatmap: false},
        currentFilter: 'all',
        heatmapData: null,
    };

    // -------------------------------------------------------------------------
    // Localised UI labels. English defaults keep synchronous rendering correct;
    // init() overwrites them with the site language via core/str before the
    // first panel render. (Previously these were hardcoded Spanish, which clashed
    // with the otherwise-localised panel UI.)
    // -------------------------------------------------------------------------
    var STR = {
        histTitle: 'Score distribution',
        histRange: 'Range',
        histCount: 'Count',
        drawerScore: 'Score',
        drawerAttempts: 'Attempts',
        drawerTime: 'Time',
        drawerStatus: 'Status',
        drawerDraft: 'Draft',
        yes: 'Yes',
        no: 'No',
        row: 'Row',
        noData: 'no data',
        studentsSoon: '(Student list coming soon)',
    };

    /**
     * Prefetch localised panel labels into STR. Resolves even on failure
     * (English defaults remain in place).
     *
     * @return {Promise}
     */
    function loadStrings() {
        return Str.get_strings([
            {key: 'panel_hist_title', component: 'mod_graphitoubb'},
            {key: 'panel_hist_range', component: 'mod_graphitoubb'},
            {key: 'panel_hist_count', component: 'mod_graphitoubb'},
            {key: 'panel_drawer_score', component: 'mod_graphitoubb'},
            {key: 'panel_drawer_attempts', component: 'mod_graphitoubb'},
            {key: 'panel_drawer_time', component: 'mod_graphitoubb'},
            {key: 'panel_drawer_status', component: 'mod_graphitoubb'},
            {key: 'panel_drawer_draft', component: 'mod_graphitoubb'},
            {key: 'yes', component: 'core'},
            {key: 'no', component: 'core'},
            {key: 'panel_row', component: 'mod_graphitoubb'},
            {key: 'panel_no_data', component: 'mod_graphitoubb'},
            {key: 'panel_students_soon', component: 'mod_graphitoubb'},
        ]).then(function(s) {
            STR.histTitle = s[0];
            STR.histRange = s[1];
            STR.histCount = s[2];
            STR.drawerScore = s[3];
            STR.drawerAttempts = s[4];
            STR.drawerTime = s[5];
            STR.drawerStatus = s[6];
            STR.drawerDraft = s[7];
            STR.yes = s[8];
            STR.no = s[9];
            STR.row = s[10];
            STR.noData = s[11];
            STR.studentsSoon = s[12];
            return STR;
        }).catch(function() {
            return STR;
        });
    }

    // -------------------------------------------------------------------------
    // Error display.
    // -------------------------------------------------------------------------
    function showError(msg) {
        var el = state.root.querySelector('[data-region="panel-error"]');
        if (el) {
            el.classList.remove('d-none');
            if (msg) {
                el.textContent = msg;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Tab switching (ARIA-compliant keyboard nav).
    // -------------------------------------------------------------------------
    function initTabs() {
        var tablist = state.root.querySelector('[role="tablist"]');
        if (!tablist) {
            return;
        }
        var buttons = Array.from(tablist.querySelectorAll('[role="tab"]'));

        function activateTab(btn) {
            buttons.forEach(function(b) {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
                b.setAttribute('tabindex', '-1');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
            btn.setAttribute('tabindex', '0');

            // Deactivate all tab panels then activate the target.
            state.root.querySelectorAll('[role="tabpanel"]').forEach(function(p) {
                p.classList.remove('show', 'active');
            });
            var targetId = btn.getAttribute('data-bs-target');
            if (targetId) {
                var panel = state.root.querySelector(targetId);
                if (panel) {
                    panel.classList.add('show', 'active');
                }
            }
        }

        tablist.addEventListener('click', function(e) {
            var btn = e.target.closest('[role="tab"]');
            if (!btn) {
                return;
            }
            activateTab(btn);
            onTabActivated(btn.id);
        });

        // Keyboard navigation: ArrowLeft/Right cycle tabs, Home/End jump.
        tablist.addEventListener('keydown', function(e) {
            var idx = buttons.indexOf(document.activeElement);
            if (idx === -1) {
                return;
            }
            var next = -1;
            if (e.key === 'ArrowRight') {
                next = (idx + 1) % buttons.length;
            } else if (e.key === 'ArrowLeft') {
                next = (idx - 1 + buttons.length) % buttons.length;
            } else if (e.key === 'Home') {
                next = 0;
            } else if (e.key === 'End') {
                next = buttons.length - 1;
            }
            if (next !== -1) {
                e.preventDefault();
                buttons[next].focus();
                activateTab(buttons[next]);
                onTabActivated(buttons[next].id);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Tab activation dispatcher — lazy-loads WS data once per tab.
    // -------------------------------------------------------------------------
    function onTabActivated(btnId) {
        if (btnId === 'tab-summary-btn' && !state.loaded.summary) {
            loadSummary();
        } else if (btnId === 'tab-student-btn' && !state.loaded.student) {
            loadStudents(state.currentFilter);
        } else if (btnId === 'tab-heatmap-btn' && !state.loaded.heatmap) {
            loadHeatmap();
        } else if (btnId === 'tab-export-btn') {
            initExportForm();
        }
    }

    // -------------------------------------------------------------------------
    // Summary tab.
    // -------------------------------------------------------------------------
    function loadSummary() {
        Repository.getPanelSummary(state.instanceid)
        .then(function(data) {
            state.loaded.summary = true;
            renderSummary(data);
        })
        .catch(function() {
            Str.get_string('error_loading_panel', 'mod_graphitoubb').then(showError);
        });
    }

    function renderSummary(data) {
        var section = state.root.querySelector('[data-region="panel-summary-content"]');
        if (!section) {
            return;
        }

        // Hide spinner.
        var loading = section.querySelector('[data-region="summary-loading"]');
        if (loading) {
            loading.classList.add('d-none');
        }

        // KPI cards.
        var kpis = section.querySelector('[data-region="summary-kpis"]');
        if (kpis) {
            kpis.classList.remove('d-none');
            setField(kpis, 'enrolled',    data.enrolled);
            setField(kpis, 'attempted',   data.attempted);
            setField(kpis, 'submitted',   data.submitted);
            setField(kpis, 'with_draft',  data.with_draft);
        }

        // Score stats.
        var stats = section.querySelector('[data-region="summary-stats"]');
        if (stats) {
            stats.classList.remove('d-none');
            setField(stats, 'avg_score',           fmtFraction(data.avg_score));
            setField(stats, 'median_score',        fmtFraction(data.median_score));
            setField(stats, 'stddev_score',        fmtFraction(data.stddev_score));
            setField(stats, 'time_median_seconds', fmtSeconds(data.time_median_seconds));
        }

        // Buckets — simple bar chart via inline div widths.
        renderBuckets(section, data.buckets);

        // Top errors.
        renderTopErrors(section, data.top_errors);
    }

    function setField(container, fieldName, value) {
        var el = container.querySelector('[data-field="' + fieldName + '"]');
        if (el) {
            el.textContent = String(value);
        }
    }

    function renderBuckets(section, buckets) {
        var wrapper = section.querySelector('[data-region="summary-buckets"]');
        if (!wrapper || !buckets || buckets.length === 0) {
            return;
        }
        wrapper.classList.remove('d-none');

        var chart = wrapper.querySelector('[data-region="bucket-chart"]');
        if (!chart) {
            return;
        }

        var maxCount = Math.max.apply(null, buckets.map(function(b) { return b.count; }));
        if (maxCount === 0) {
            maxCount = 1;
        }

        chart.innerHTML = '';
        var chartInner = document.createElement('div');
        chartInner.style.cssText = 'display:flex;align-items:flex-end;height:100%;gap:2px;';

        buckets.forEach(function(bucket) {
            var pct = ((bucket.bucket * 10) + 5); // midpoint of the bucket range.
            var colour = heatColour(pct);
            var barHeight = (bucket.count / maxCount * 100).toFixed(1) + '%';
            var bar = document.createElement('div');
            bar.style.cssText = [
                'flex:1',
                'background:' + colour.bg,
                'height:' + barHeight,
                'position:relative',
                'cursor:default',
            ].join(';');
            bar.title = (bucket.bucket * 10) + '–' + Math.min(100, (bucket.bucket + 1) * 10) + '% : ' + bucket.count;
            var label = document.createElement('span');
            label.style.cssText = 'position:absolute;bottom:2px;left:0;right:0;text-align:center;font-size:10px;color:' + colour.fg;
            label.textContent = bucket.count;
            bar.appendChild(label);
            chartInner.appendChild(bar);
        });

        chart.appendChild(chartInner);

        // Accessible textual alternative.
        var accessible = wrapper.querySelector('[data-region="bucket-table-accessible"]');
        if (accessible) {
            var table = document.createElement('table');
            table.className = 'sr-only visually-hidden';
            table.setAttribute('aria-label', STR.histTitle);
            var thead = '<thead><tr><th>' + escHtml(STR.histRange) + '</th><th>' + escHtml(STR.histCount) + '</th></tr></thead>';
            var rows = buckets.map(function(b) {
                return '<tr><td>' + (b.bucket * 10) + '-' + Math.min(100, (b.bucket + 1) * 10) + '%</td><td>' + b.count + '</td></tr>';
            }).join('');
            table.innerHTML = thead + '<tbody>' + rows + '</tbody>';
            accessible.appendChild(table);
        }
    }

    function renderTopErrors(section, topErrors) {
        var wrapper = section.querySelector('[data-region="summary-top-errors"]');
        if (!wrapper) {
            return;
        }
        if (!topErrors || topErrors.length === 0) {
            return;
        }
        wrapper.classList.remove('d-none');
        var list = wrapper.querySelector('[data-region="top-errors-list"]');
        if (!list) {
            return;
        }
        list.innerHTML = '';
        topErrors.forEach(function(err) {
            var li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            var label = STR.row + ' ' + err.row_index + ' — ' + err.col_label;
            li.textContent = label;
            var badge = document.createElement('span');
            badge.className = 'badge bg-danger rounded-pill';
            badge.textContent = err.count + ' (' + err.percentage + '%)';
            li.appendChild(badge);
            list.appendChild(li);
        });
    }

    // -------------------------------------------------------------------------
    // Per-student tab.
    // -------------------------------------------------------------------------
    function loadStudents(filter) {
        state.currentFilter = filter;
        Repository.getPanelPerStudent(state.instanceid, filter)
        .then(function(data) {
            state.loaded.student = true;
            renderStudents(data.students);
        })
        .catch(function() {
            Str.get_string('error_loading_panel', 'mod_graphitoubb').then(showError);
        });
    }

    function renderStudents(students) {
        var section = state.root.querySelector('[data-region="panel-student-content"]');
        if (!section) {
            return;
        }
        var loading = section.querySelector('[data-region="student-loading"]');
        if (loading) {
            loading.classList.add('d-none');
        }
        var wrapper = section.querySelector('[data-region="student-table-wrapper"]');
        if (wrapper) {
            wrapper.classList.remove('d-none');
        }
        var tbody = section.querySelector('[data-region="student-tbody"]');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';

        students.forEach(function(s) {
            var tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.dataset.userid = s.userid;
            tr.innerHTML = [
                '<td>' + escHtml(s.fullname) + '</td>',
                '<td class="text-end">' + fmtFraction(s.fraction) + '</td>',
                '<td class="text-end">' + s.attempts_count + '</td>',
                '<td class="text-end">' + fmtSeconds(s.time_spent_seconds) + '</td>',
                '<td><span class="badge ' + statusBadgeClass(s.status) + '">' + escHtml(s.status) + '</span></td>',
                '<td><button class="btn btn-sm btn-outline-danger" data-action="reset" data-userid="' + s.userid + '" data-name="' + escHtml(s.fullname) + '">Reset</button></td>',
            ].join('');
            tr.addEventListener('click', function(e) {
                if (e.target.closest('[data-action="reset"]')) {
                    return; // Handled separately.
                }
                openStudentDrawer(s);
            });
            tbody.appendChild(tr);
        });

        // Wire reset buttons.
        tbody.querySelectorAll('[data-action="reset"]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var uid  = parseInt(btn.dataset.userid, 10);
                var name = btn.dataset.name;
                confirmReset(uid, name);
            });
        });
    }

    /**
     * D1: confirm an attempt reset with a Moodle modal (replacing window.confirm),
     * spelling out the destructive impact and surfacing a success/error toast.
     *
     * @param {number} uid  Target user id.
     * @param {string} name Student full name (for the prompt and toast).
     */
    function confirmReset(uid, name) {
        Str.get_strings([
            {key: 'reset_modal_title', component: 'mod_graphitoubb'},
            {key: 'reset_modal_body', component: 'mod_graphitoubb', param: name},
            {key: 'reset_confirm_button', component: 'mod_graphitoubb'},
        ]).then(function(strs) {
            return ModalSaveCancel.create({
                title: strs[0],
                body: strs[1],
            }).then(function(modal) {
                modal.setSaveButtonText(strs[2]);
                modal.getRoot().on(ModalEvents.save, function() {
                    doReset(uid, name);
                });
                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });
                modal.show();
                return modal;
            });
        }).catch(Notification.exception);
    }

    /**
     * Perform the reset WS call and refresh the student list, with toast feedback.
     *
     * @param {number} uid  Target user id.
     * @param {string} name Student full name (for the success toast).
     */
    function doReset(uid, name) {
        Repository.resetAttempts(state.instanceid, uid)
        .then(function() {
            state.loaded.student = false;
            loadStudents(state.currentFilter);
            return Str.get_string('reset_success', 'mod_graphitoubb', name);
        })
        .then(function(msg) {
            Notification.addNotification({message: msg, type: 'success'});
            return;
        })
        .catch(function() {
            Str.get_string('reset_error', 'mod_graphitoubb').then(function(msg) {
                Notification.addNotification({message: msg, type: 'error'});
                return;
            });
        });
    }

    function statusBadgeClass(status) {
        if (status === 'finished')    { return 'bg-success'; }
        if (status === 'inprogress')  { return 'bg-warning text-dark'; }
        return 'bg-secondary';
    }

    function openStudentDrawer(student) {
        var drawer = state.root.querySelector('[data-region="student-drawer"]');
        if (!drawer) {
            return;
        }
        var nameEl = drawer.querySelector('[data-region="drawer-student-name"]');
        if (nameEl) {
            nameEl.textContent = student.fullname;
        }
        var body = drawer.querySelector('[data-region="drawer-body"]');
        if (body) {
            body.innerHTML = [
                '<dl class="row">',
                '<dt class="col-5">' + escHtml(STR.drawerScore) + '</dt><dd class="col-7">' + fmtFraction(student.fraction) + '</dd>',
                '<dt class="col-5">' + escHtml(STR.drawerAttempts) + '</dt><dd class="col-7">' + student.attempts_count + '</dd>',
                '<dt class="col-5">' + escHtml(STR.drawerTime) + '</dt><dd class="col-7">' + fmtSeconds(student.time_spent_seconds) + '</dd>',
                '<dt class="col-5">' + escHtml(STR.drawerStatus) + '</dt><dd class="col-7">' + escHtml(student.status) + '</dd>',
                '<dt class="col-5">' + escHtml(STR.drawerDraft) + '</dt><dd class="col-7">' + (student.has_draft ? escHtml(STR.yes) : escHtml(STR.no)) + '</dd>',
                '</dl>',
            ].join('');
        }
        // Bootstrap offcanvas — show() if available, else add CSS class manually.
        if (window.bootstrap && window.bootstrap.Offcanvas) {
            var oc = window.bootstrap.Offcanvas.getOrCreateInstance(drawer);
            oc.show();
        } else {
            drawer.classList.add('show');
        }
    }

    // -------------------------------------------------------------------------
    // Heatmap tab.
    // -------------------------------------------------------------------------
    function loadHeatmap() {
        Repository.getPanelHeatmap(state.instanceid)
        .then(function(data) {
            state.loaded.heatmap = true;
            state.heatmapData    = data;
            renderHeatmap(data);
        })
        .catch(function() {
            Str.get_string('error_loading_panel', 'mod_graphitoubb').then(showError);
        });
    }

    function renderHeatmap(data) {
        var section = state.root.querySelector('[data-region="panel-heatmap-content"]');
        if (!section) {
            return;
        }
        var loading = section.querySelector('[data-region="heatmap-loading"]');
        if (loading) {
            loading.classList.add('d-none');
        }

        if (!data.cells || data.cells.length === 0) {
            var noData = section.querySelector('[data-region="heatmap-no-data"]');
            if (noData) {
                noData.classList.remove('d-none');
            }
            return;
        }

        // Build lookup: cell[row][col_index] -> cell data.
        var cellMap = {};
        data.cells.forEach(function(c) {
            if (!cellMap[c.row]) { cellMap[c.row] = {}; }
            cellMap[c.row][c.col_index] = c;
        });

        // Show legend.
        var legend = section.querySelector('[data-region="heatmap-legend"]');
        if (legend) { legend.classList.remove('d-none'); }

        // Build visual grid.
        var grid = section.querySelector('[data-region="heatmap-grid"]');
        if (grid) {
            grid.classList.remove('d-none');
            grid.innerHTML = '';

            // Header row with column labels.
            var headerRow = document.createElement('div');
            headerRow.className = 'graphitoubb-heatmap-row d-flex';
            var cornerCell = document.createElement('div');
            cornerCell.className = 'graphitoubb-heatmap-cell graphitoubb-heatmap-header';
            cornerCell.style.cssText = 'width:40px;min-width:40px;';
            cornerCell.textContent = '#';
            headerRow.appendChild(cornerCell);
            data.columns.forEach(function(col) {
                var th = document.createElement('div');
                th.className = 'graphitoubb-heatmap-cell graphitoubb-heatmap-header';
                th.style.cssText = 'min-width:60px;padding:2px 4px;font-size:11px;font-weight:bold;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;';
                th.title = col;
                th.textContent = col;
                headerRow.appendChild(th);
            });
            grid.appendChild(headerRow);

            // Data rows.
            for (var r = 0; r < data.rows_count; r++) {
                var row = document.createElement('div');
                row.className = 'graphitoubb-heatmap-row d-flex';

                var rowLabel = document.createElement('div');
                rowLabel.className = 'graphitoubb-heatmap-cell';
                rowLabel.style.cssText = 'width:40px;min-width:40px;background:#f8f9fa;font-size:11px;';
                rowLabel.textContent = r;
                row.appendChild(rowLabel);

                data.columns.forEach(function(col, ci) {
                    var cellData = (cellMap[r] && cellMap[r][ci]) ? cellMap[r][ci] : null;
                    var pct     = cellData ? cellData.pct_correct : null;
                    var count   = cellData ? cellData.count_submissions : 0;
                    var colour  = pct !== null ? heatColour(pct) : {bg: '#ecf0f1', fg: '#7f8c8d'};

                    var cell = document.createElement('div');
                    cell.className = 'graphitoubb-heatmap-cell';
                    cell.dataset.row = r;
                    cell.dataset.col = ci;
                    cell.style.cssText = [
                        'min-width:60px',
                        'background:' + colour.bg,
                        'color:' + colour.fg,
                        'font-size:11px',
                        'text-align:center',
                        'padding:4px',
                        'cursor:' + (count > 0 ? 'pointer' : 'default'),
                    ].join(';');
                    cell.textContent = pct !== null ? pct.toFixed(1) + '%' : '—';
                    cell.title = col + ' | ' + STR.row.toLowerCase() + ' ' + r
                        + (count > 0 ? ': ' + pct.toFixed(1) + '% (' + count + ')' : ': ' + STR.noData);

                    if (count > 0) {
                        cell.addEventListener('click', function() {
                            showCellDetail(section, r, ci, col, cellData);
                        });
                    }
                    row.appendChild(cell);
                });
                grid.appendChild(row);
            }
        }

        // Build textual alternative.
        var details = section.querySelector('[data-region="heatmap-table-details"]');
        if (details) {
            details.classList.remove('d-none');
            var tHead = details.querySelector('[data-region="heatmap-table-head"]');
            var tBody = details.querySelector('[data-region="heatmap-table-body"]');
            if (tHead) {
                var thRow = '<tr><th scope="col">' + escHtml(STR.row) + '</th>';
                data.columns.forEach(function(col) {
                    thRow += '<th scope="col">' + escHtml(col) + '</th>';
                });
                thRow += '</tr>';
                tHead.innerHTML = thRow;
            }
            if (tBody) {
                var bodyHtml = '';
                for (var ri = 0; ri < data.rows_count; ri++) {
                    bodyHtml += '<tr><th scope="row">' + ri + '</th>';
                    data.columns.forEach(function(col, ci) {
                        var cd = cellMap[ri] && cellMap[ri][ci] ? cellMap[ri][ci] : null;
                        bodyHtml += '<td>' + (cd ? cd.pct_correct.toFixed(1) + '% (' + cd.count_submissions + ')' : '—') + '</td>';
                    });
                    bodyHtml += '</tr>';
                }
                tBody.innerHTML = bodyHtml;
            }
        }
    }

    function showCellDetail(section, row, colIdx, colLabel, cellData) {
        var detail = section.querySelector('[data-region="heatmap-cell-detail"]');
        if (!detail) { return; }
        detail.classList.remove('d-none');
        var title = detail.querySelector('[data-region="cell-detail-title"]');
        if (title) {
            title.textContent = colLabel + ' | ' + STR.row.toLowerCase() + ' ' + row + ' — '
                + cellData.pct_correct.toFixed(1) + '%';
        }
        var list = detail.querySelector('[data-region="cell-detail-students"]');
        if (list) {
            list.innerHTML = '<li class="list-group-item text-muted">' + escHtml(STR.studentsSoon) + '</li>';
        }
    }

    // -------------------------------------------------------------------------
    // Export tab.
    // -------------------------------------------------------------------------
    function initExportForm() {
        var section = state.root.querySelector('[data-region="panel-export-content"]');
        if (!section) { return; }
        var form = section.querySelector('[data-region="export-form"]');
        if (!form) { return; }

        // Set the form action to panel_export.php.
        form.action = M.cfg.wwwroot + '/mod/graphitoubb/panel_export.php';

        // Fill the hidden cmid (AMD receives instanceid but panel_export.php needs cmid).
        // We stash the contextid in data attribute; AMD cannot convert instance→cmid
        // without another WS call. Use a data attribute set by panel.php as a fallback.
        var cmidEl = form.querySelector('[data-region="export-cmid"]');
        if (cmidEl) {
            // panel.php passes cmid via URL; read from the current page URL.
            var urlParams = new URLSearchParams(window.location.search);
            cmidEl.value = urlParams.get('id') || '';
        }
    }

    // -------------------------------------------------------------------------
    // Student filter change handler.
    // -------------------------------------------------------------------------
    function initStudentFilter() {
        var filterEl = state.root.querySelector('[data-region="student-filter"]');
        if (!filterEl) { return; }
        filterEl.addEventListener('change', function() {
            state.loaded.student = false;
            loadStudents(filterEl.value);
        });
    }

    // -------------------------------------------------------------------------
    // Escape HTML helper.
    // -------------------------------------------------------------------------
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // -------------------------------------------------------------------------
    // Public init.
    // -------------------------------------------------------------------------

    /**
     * Initialize the panel dashboard.
     *
     * @param {Array} opts  [selector, instanceid, contextid]
     */
    var init = function(opts) {
        var selector   = opts[0] || '#graphitoubb-panel';
        state.instanceid = parseInt(opts[1], 10);
        state.contextid  = parseInt(opts[2], 10);

        state.root = document.querySelector(selector);
        if (!state.root) {
            return;
        }

        var wsAttr = state.root.dataset.wsfunctions;
        if (wsAttr) {
            try {
                state.wsfunctions = JSON.parse(wsAttr);
            } catch (e) {
                state.wsfunctions = {};
            }
        }

        initTabs();
        initStudentFilter();

        // Prefetch localised labels, then load summary eagerly (the default tab).
        loadStrings().then(function() {
            loadSummary();
            return;
        }).catch(function() {
            loadSummary();
        });
    };

    return {init: init};
});
