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
 * Wires Cytoscape graph events to snapshot_controller.
 *
 * @module     mod_graphitoubb/afd_editor
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'mod_graphitoubb/cytoscape_factory',
    'mod_graphitoubb/snapshot_controller',
    'mod_graphitoubb/repository',
    'core/notification',
], function(CytoscapeFactory, SnapshotController, Repository, Notification) {

    /**
     * Extract canonical AFD shape from a Cytoscape instance.
     *
     * snapshot_controller.isSignificant() compares {states, transitions, alphabet}.
     * cy.json() uses Cytoscape's own format which lacks those keys, causing
     * isSignificant() to always return false. This function produces the
     * canonical shape that both the comparator and the server expect.
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

        return {states: states, transitions: transitions, alphabet: alphabet, start: start, finals: finals};
    };

    /**
     * Extract the canonical AFD shape from a Cytoscape instance.
     *
     * snapshot_controller.isSignificant() compares state.states / transitions / alphabet.
     * cy.json() uses Cytoscape's own format which lacks these keys, so auto-save
     * never fired (P0 bug). This helper produces the canonical shape instead.
     *
     * @param {object} cy Cytoscape instance.
     * @return {{states: Array, transitions: Array, alphabet: Array, start: string|null, finals: Array}}
     */
    var extractCanonical = function(cy) {
        var symbolSeen = {};
        var alphabet = [];

        cy.edges().forEach(function(e) {
            var sym = e.data('symbol') || '';
            if (sym && !symbolSeen[sym]) {
                symbolSeen[sym] = true;
                alphabet.push(sym);
            }
        });
        alphabet.sort();

        var states = cy.nodes().map(function(n) {
            return {id: n.id(), label: n.data('label') || n.id()};
        });

        var transitions = cy.edges().map(function(e) {
            return {from: e.source().id(), symbol: e.data('symbol') || '', to: e.target().id()};
        });

        var start = null;
        var finals = [];
        cy.nodes().forEach(function(n) {
            if (n.data('start')) {
                start = n.id();
            }
            if (n.data('final')) {
                finals.push(n.id());
            }
        });

        return {states: states, transitions: transitions, alphabet: alphabet, start: start, finals: finals};
    };

    /**
     * Initialise the editor for a given attempt.
     *
     * Called from the editor.mustache {{#js}} block.
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

                cy.on('add remove data', function() {
                    SnapshotController.onchange(attemptid, extractCanonical(cy), schemaversion);
                });

                return cy;
            })
            .catch(Notification.exception);
    };

    return {
        init: init,
    };
});
