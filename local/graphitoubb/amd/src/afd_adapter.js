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
 * Cytoscape ↔ canonical AFD shape adapter.
 *
 * Pure functions — no validation, no IO, no side effects.
 * Canonical shape (persistence format):
 *   { states: [{id, label}], transitions: [{from, symbol, to}],
 *     alphabet: string[], start: string|null, finals: string[] }
 *
 * DEVIATION from orchestrator contract: cyToAfd returns persistence format
 * (not simulator map format) because (a) it must be a drop-in for the
 * inline extractCanonical in afd_editor.js, and (b) snapshot_controller
 * compares .states/.transitions/.alphabet keys. Simulator wiring (S11/S12)
 * will convert via a separate afdToSimulatorInput helper.
 *
 * @module     local_graphitoubb/afd_adapter
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Convert a live Cytoscape instance to the canonical AFD persistence shape.
     *
     * @param {object} cy Cytoscape core instance.
     * @return {{states: Array, transitions: Array, alphabet: Array, start: string|null, finals: Array}}
     */
    var cyToAfd = function(cy) {
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

        return {
            states: states,
            transitions: transitions,
            alphabet: alphabet,
            start: start,
            finals: finals,
        };
    };

    /**
     * Convert a canonical AFD shape to Cytoscape elements format.
     *
     * Edge cases handled:
     * - Empty alphabet: produces no edges (correct — no symbols, no transitions).
     * - Missing initial state (start: null): no node gets start=true.
     * - Orphan transitions (source or target not in states): silently skipped.
     *
     * @param {{states: Array, transitions: Array, alphabet: Array, start: string|null, finals: Array}} afd
     * @return {{nodes: Array, edges: Array}} Cytoscape elements object.
     */
    var afdToCy = function(afd) {
        var stateIds = {};

        var nodes = (afd.states || []).map(function(s) {
            stateIds[s.id] = true;
            return {
                data: {
                    id: s.id,
                    label: s.label || s.id,
                    start: s.id === (afd.start || null),
                    final: (afd.finals || []).indexOf(s.id) !== -1,
                },
            };
        });

        var edges = [];
        (afd.transitions || []).forEach(function(t) {
            if (!stateIds[t.from] || !stateIds[t.to]) {
                return;
            }
            edges.push({
                data: {
                    id: t.from + '__' + t.symbol + '__' + t.to,
                    source: t.from,
                    target: t.to,
                    symbol: t.symbol,
                },
            });
        });

        return {nodes: nodes, edges: edges};
    };

    /**
     * Convert a live Cytoscape instance to the simulator input format.
     *
     * Returns {initialState, acceptStates, alphabet, transitions} where transitions
     * is a map of "stateId:symbol" → "targetStateId" (matching afd_simulator.js).
     * Orphan transitions (source/target not in node set) are silently skipped.
     * Alphabet is read from cy.scratch('alphabet') when set; falls back to [].
     *
     * @param {object} cy Cytoscape core instance.
     * @return {{initialState: string|null, acceptStates: string[], alphabet: string[],
     *           transitions: Object.<string, string>}}
     */
    var cyToAfdSimulator = function(cy) {
        var initialState = null;
        var acceptStates = [];
        var nodeIds = {};

        cy.nodes().forEach(function(n) {
            nodeIds[n.id()] = true;
            if (n.data('start')) {
                initialState = n.id();
            }
            if (n.data('final')) {
                acceptStates.push(n.id());
            }
        });

        var transitions = {};
        cy.edges().forEach(function(e) {
            var sym = e.data('symbol') || '';
            var src = e.source().id();
            var tgt = e.target().id();
            if (sym && nodeIds[src] && nodeIds[tgt]) {
                transitions[src + ':' + sym] = tgt;
            }
        });

        var alphabet = (cy.scratch('alphabet') || []).slice();

        return {
            initialState: initialState,
            acceptStates: acceptStates,
            alphabet: alphabet,
            transitions: transitions,
        };
    };

    return {
        cyToAfd: cyToAfd,
        afdToCy: afdToCy,
        cyToAfdSimulator: cyToAfdSimulator,
    };
});
