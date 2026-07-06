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
 * AMD entry point for the truth-table editor.
 *
 * Responsibilities:
 *  1. Read data-* attrs from the root element.
 *  2. Wire formula textarea: normalize ASCII → Unicode in-place, validate,
 *     call formula_parser for canonical preview.
 *  3. Wire helper buttons (Unicode operator palette).
 *  4. Wire table cell selects: emit change events to autosave controller.
 *  5. Wire submit button: call repository.submit, render feedback.
 *  6. Wire restore-draft event (from conflict modal).
 *  7. Initialise autosave and save_indicator.
 *
 * @module     mod_graphitoubb/truth_table_editor
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'mod_graphitoubb/formula_parser',
    'mod_graphitoubb/autosave',
    'mod_graphitoubb/save_indicator',
    'mod_graphitoubb/repository',
    'core/templates',
    'core/notification',
    'core/str',
], function(FormulaParser, Autosave, SaveIndicator, Repository, Templates, Notification, Str) {

    var MAX_FORMULA_LENGTH = 128;

    // B3/G3: localised "Grading…" label for the submit spinner. English fallback
    // until init() prefetches the site language via core/str.
    var _gradingLabel = 'Grading…';

    // -------------------------------------------------------------------------
    // Payload builder — serialize current editor state to JSON string.
    // -------------------------------------------------------------------------
    var buildPayload = function(root, problemType) {
        var table = {columns: [], rows: []};
        var columns = root.querySelectorAll('.mod-graphitoubb-tte__col-header');
        columns.forEach(function(th) { table.columns.push(th.textContent.trim()); });

        var rows = root.querySelectorAll('[data-row-index]');
        rows.forEach(function(tr) {
            var rowIdx = parseInt(tr.dataset.rowIndex, 10);
            var vars = {};
            tr.querySelectorAll('.mod-graphitoubb-tte__cell--var').forEach(function(td, i) {
                var letter = table.columns[i] || String.fromCharCode(65 + i);
                vars[letter] = td.textContent.trim();
            });
            var values = [];
            tr.querySelectorAll('.mod-graphitoubb-tte__cell-select').forEach(function(sel) {
                values.push(sel.value || '');
            });
            table.rows.push({vars: vars, values: values});
            void rowIdx;
        });

        var radioAnswer = null;
        var radioSel = root.querySelector('.mod-graphitoubb-tte__radio:checked');
        if (radioSel) {
            var v = radioSel.value;
            if (v === 'true' || v === 'false') {
                radioAnswer = v === 'true';
            } else {
                radioAnswer = v;
            }
        }

        return JSON.stringify({
            tool:           'truth_table',
            schema_version: 1,
            type:           problemType,
            table:          table,
            radio_answer:   radioAnswer,
        });
    };

    // -------------------------------------------------------------------------
    // Feedback rendering
    // -------------------------------------------------------------------------
    var renderFeedback = function(feedbackArea, feedbackItems) {
        feedbackArea.innerHTML = '';
        var renders = feedbackItems.map(function(item) {
            return Templates.render('mod_graphitoubb/feedback_cell', item);
        });
        Promise.all(renders).then(function(htmls) {
            feedbackArea.innerHTML = htmls.join('');
            feedbackArea.setAttribute('role', 'list');
        }).catch(Notification.exception);
    };

    // -------------------------------------------------------------------------
    // Main init
    // -------------------------------------------------------------------------

    /**
     * Initialise the editor for a given root element.
     *
     * @param {Element} rootElement  The [data-region="truth-table-editor"] element.
     */
    var init = function(rootElement) {
        // Allow callers (js_call_amd) to pass a selector string instead of a node.
        if (typeof rootElement === 'string') {
            rootElement = document.querySelector(rootElement);
        }
        if (!rootElement) {
            return;
        }
        var attemptid   = parseInt(rootElement.dataset.attemptid, 10) || 0;
        var problemType = rootElement.dataset.problemType || 'complete';

        // Prefetch the localised grading label (English fallback stays otherwise).
        Str.get_string('submit_grading', 'mod_graphitoubb').then(function(s) {
            _gradingLabel = s;
            return s;
        }).catch(function() {
            return _gradingLabel;
        });

        // Subregion selectors.
        var formulaInput   = rootElement.querySelector('[data-region="formula-input"]');
        var canonicalEl    = rootElement.querySelector('[data-region="canonical-preview"]');
        var tableBody      = rootElement.querySelector('[data-region="table-body"]');
        var feedbackArea   = rootElement.querySelector('[data-region="feedback-area"]');
        var submitBtn      = rootElement.querySelector('[data-region="submit-btn"]');
        var indicatorEl    = rootElement.querySelector('[data-region="autosave-indicator"]');

        // -----------------------------------------------------------------------
        // Formula textarea: normalize + preview
        // -----------------------------------------------------------------------
        if (formulaInput && canonicalEl) {
            // B4: errors get role="alert" + icon-less inline emphasis; messages are
            // localised via core/str (the parser throws structured {strKey, strParam}).
            var setError = function(text) {
                canonicalEl.textContent = text;
                canonicalEl.classList.add('text-danger');
                canonicalEl.setAttribute('role', 'alert');
            };
            var clearError = function() {
                canonicalEl.classList.remove('text-danger');
                canonicalEl.removeAttribute('role');
            };

            formulaInput.addEventListener('input', function() {
                var raw  = formulaInput.value;
                var norm = FormulaParser.normalize(raw);

                // In-place ASCII→Unicode replacement (non-destructive if already Unicode).
                if (norm !== raw) {
                    var pos = formulaInput.selectionStart;
                    formulaInput.value = norm;
                    formulaInput.setSelectionRange(pos, pos);
                }

                if (norm.length > MAX_FORMULA_LENGTH) {
                    Str.get_string('err_max_formula_length', 'mod_graphitoubb', MAX_FORMULA_LENGTH)
                        .then(setError)
                        .catch(function() { setError('Formula exceeds the maximum length.'); });
                    return;
                }

                try {
                    var ast  = FormulaParser.parse(norm);
                    var cano = FormulaParser.canonical(ast);
                    canonicalEl.textContent = cano;
                    clearError();
                } catch (e) {
                    if (e.strKey) {
                        Str.get_string(e.strKey, 'mod_graphitoubb', e.strParam)
                            .then(setError)
                            .catch(function() { setError(e.message); });
                    } else {
                        setError(e.message);
                    }
                }
            });
        }

        // -----------------------------------------------------------------------
        // Helper buttons — insert symbol at cursor in formula textarea
        // -----------------------------------------------------------------------
        rootElement.querySelectorAll('.mod-graphitoubb-tte__helper').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!formulaInput) {
                    return;
                }
                var sym   = btn.dataset.symbol;
                var start = formulaInput.selectionStart;
                var end   = formulaInput.selectionEnd;
                var val   = formulaInput.value;
                formulaInput.value = val.substring(0, start) + sym + val.substring(end);
                formulaInput.selectionStart = formulaInput.selectionEnd = start + sym.length;
                formulaInput.dispatchEvent(new Event('input', {bubbles: true}));
                formulaInput.focus();
            });
        });

        // -----------------------------------------------------------------------
        // Table cell selects — emit change → autosave
        // -----------------------------------------------------------------------
        if (tableBody) {
            tableBody.addEventListener('change', function(evt) {
                if (evt.target && evt.target.classList.contains('mod-graphitoubb-tte__cell-select')) {
                    Autosave.notify_change();
                }
            });
        }

        // Radio buttons
        rootElement.querySelectorAll('.mod-graphitoubb-tte__radio').forEach(function(radio) {
            radio.addEventListener('change', function() { Autosave.notify_change(); });
        });

        // -----------------------------------------------------------------------
        // Submit button
        // -----------------------------------------------------------------------
        if (submitBtn) {
            submitBtn.addEventListener('click', function() {
                var originalHtml = submitBtn.innerHTML;
                // B3/G3: loading state — spinner + "Grading…", blocks double submit.
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" '
                    + 'role="status" aria-hidden="true"></span> ' + _gradingLabel;
                var payload = buildPayload(rootElement, problemType);

                Repository.submit(attemptid, payload)
                    .then(function(result) {
                        if (result.error) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalHtml;
                            Notification.addNotification({
                                message: result.error_message,
                                type:    'error',
                            });
                            return;
                        }
                        // Restore the label, then disable all inputs after success.
                        submitBtn.innerHTML = originalHtml;
                        rootElement.querySelectorAll('select, input, textarea, button').forEach(function(el) {
                            el.disabled = true;
                        });
                        if (feedbackArea) {
                            renderFeedback(feedbackArea, result.feedback_items || []);
                        }
                        return result;
                    })
                    .catch(function(err) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                        Notification.exception(err);
                    });
            });
        }

        // -----------------------------------------------------------------------
        // Restore-draft event (from conflict modal)
        // -----------------------------------------------------------------------
        rootElement.addEventListener('graphitoubb:restore-draft', function(evt) {
            var detail = evt.detail;
            if (detail && detail.payload) {
                try {
                    var data = JSON.parse(detail.payload);
                    // Restore cell values from draft.
                    if (data.table && data.table.rows) {
                        data.table.rows.forEach(function(row, ri) {
                            var selects = rootElement.querySelectorAll(
                                '[data-row="' + ri + '"].mod-graphitoubb-tte__cell-select'
                            );
                            (row.values || []).forEach(function(val, ci) {
                                if (selects[ci]) {
                                    selects[ci].value = val;
                                }
                            });
                        });
                    }
                    if (data.radio_answer !== undefined && data.radio_answer !== null) {
                        var radioVal = String(data.radio_answer);
                        var radio = rootElement.querySelector(
                            '.mod-graphitoubb-tte__radio[value="' + radioVal + '"]'
                        );
                        if (radio) {
                            radio.checked = true;
                        }
                    }
                } catch (e) {
                    // Silently ignore corrupt draft.
                }
            }
        });

        // -----------------------------------------------------------------------
        // Init autosave and save indicator
        // -----------------------------------------------------------------------
        Autosave.init(rootElement, function() {
            return buildPayload(rootElement, problemType);
        }, 0);

        SaveIndicator.init(indicatorEl, rootElement);
    };

    return {
        init: init,
    };
});
