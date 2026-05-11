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
 * Alphabet management UI for the AFD editor.
 *
 * Source of truth: cy.scratch('alphabet') (string[]).
 * init() must be called before getAlphabet/addSymbol/removeSymbol are safe to use.
 *
 * @module     mod_graphitoubb/alphabet_ui
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification', 'core/str'], function(Notification, Str) {

    var _cy = null;
    var _maxAlphabet = 16;
    var _onchange = null;
    var _listEl = null;

    /**
     * Return current alphabet array (live copy from scratch).
     *
     * @return {string[]}
     */
    var getAlphabet = function() {
        return (_cy && Array.isArray(_cy.scratch('alphabet'))) ? _cy.scratch('alphabet') : [];
    };

    var renderList = function() {
        if (!_listEl) {
            return;
        }
        _listEl.innerHTML = '';
        getAlphabet().forEach(function(s) {
            var li = document.createElement('li');
            li.className = 'mod-graphitoubb-alphabet-symbol';
            li.textContent = s + '\u00a0';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mod-graphitoubb-alphabet-remove';
            btn.textContent = '\u00d7';
            btn.setAttribute('data-symbol', s);
            btn.addEventListener('click', function() {
                removeSymbol(s);
            });
            li.appendChild(btn);
            _listEl.appendChild(li);
        });
    };

    /**
     * Add a symbol to the alphabet.
     *
     * @param {string} s Single alphanumeric character.
     * @return {boolean} True if added; false if rejected (duplicate, max, or invalid).
     */
    var addSymbol = function(s) {
        if (!s || !/^[a-zA-Z0-9]$/.test(s)) {
            return false;
        }
        var current = getAlphabet();
        if (current.indexOf(s) !== -1) {
            return false;
        }
        if (current.length >= _maxAlphabet) {
            return false;
        }
        _cy.scratch('alphabet', current.concat([s]));
        renderList();
        if (_onchange) {
            _onchange();
        }
        return true;
    };

    /**
     * Remove a symbol from the alphabet.
     *
     * Refuses if any edge in cy uses that symbol (logs warning).
     *
     * @param {string} s
     * @return {boolean} True if removed; false if refused.
     */
    var removeSymbol = function(s) {
        if (!_cy) {
            return false;
        }
        var inUse = _cy.edges().some(function(e) {
            return e.data('symbol') === s;
        });
        if (inUse) {
            Str.get_string('err_symbol_in_use', 'mod_graphitoubb').then(function(msg) {
                Notification.addNotification({message: msg, type: 'warning'});
                return;
            });
            return false;
        }
        _cy.scratch('alphabet', getAlphabet().filter(function(x) {
            return x !== s;
        }));
        renderList();
        if (_onchange) {
            _onchange();
        }
        return true;
    };

    /**
     * Initialise the alphabet panel.
     *
     * Safe to call with rootEl=null: _cy is still set so getAlphabet/addSymbol work.
     *
     * @param {Element|null} rootEl .mod-graphitoubb-alphabet-panel element.
     * @param {object} cy Cytoscape instance.
     * @param {Function|null} onchange Called after every alphabet mutation.
     */
    var init = function(rootEl, cy, onchange) {
        _cy = cy;
        _onchange = onchange || null;

        if (!Array.isArray(_cy.scratch('alphabet'))) {
            _cy.scratch('alphabet', []);
        }

        if (!rootEl) {
            return;
        }

        var maxAttr = parseInt(rootEl.getAttribute('data-max-alphabet'), 10);
        _maxAlphabet = isNaN(maxAttr) ? 16 : maxAttr;

        _listEl = rootEl.querySelector('.mod-graphitoubb-alphabet-list');

        renderList();

        var inputEl = rootEl.querySelector('.mod-graphitoubb-alphabet-input');
        var addBtn = rootEl.querySelector('.mod-graphitoubb-alphabet-add');

        if (!addBtn || !inputEl) {
            return;
        }

        addBtn.addEventListener('click', function() {
            var s = inputEl.value.trim().charAt(0);
            addSymbol(s);
            inputEl.value = '';
        });

        inputEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                var s = inputEl.value.trim().charAt(0);
                addSymbol(s);
                inputEl.value = '';
            }
        });
    };

    return {
        init: init,
        getAlphabet: getAlphabet,
        addSymbol: addSymbol,
        removeSymbol: removeSymbol,
    };
});
