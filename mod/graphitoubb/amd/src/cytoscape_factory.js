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
 * Factory for Cytoscape graph instances.
 *
 * Builds a Cytoscape instance from an automaton payload
 * ({states, transitions, alphabet, start, finals}).
 *
 * @module     mod_graphitoubb/cytoscape_factory
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_graphitoubb/cytoscape'], function(cytoscape) {

    /**
     * Build a Cytoscape instance from an automaton payload.
     *
     * @param {HTMLElement} container  DOM element for the canvas.
     * @param {object|string} automatonpayload  Automaton descriptor or JSON string.
     * @return {object} Cytoscape instance.
     */
    var create = function(container, automatonpayload) {
        var automaton = automatonpayload;
        if (typeof automatonpayload === 'string') {
            automaton = JSON.parse(automatonpayload);
        }
        if (!automaton) {
            automaton = {states: [], transitions: [], alphabet: [], start: null, finals: []};
        }

        var elements = [];

        (automaton.states || []).forEach(function(s) {
            var classes = '';
            if (automaton.start === s.id) {
                classes += 'start ';
            }
            if ((automaton.finals || []).indexOf(s.id) !== -1) {
                classes += 'final';
            }
            elements.push({
                data: {
                    id: s.id,
                    label: s.label || s.id,
                    start: (automaton.start === s.id),
                    final: ((automaton.finals || []).indexOf(s.id) !== -1),
                },
                classes: classes.trim(),
            });
        });

        (automaton.transitions || []).forEach(function(t, i) {
            elements.push({
                data: {id: 't' + i, source: t.from, target: t.to, label: t.symbol, symbol: t.symbol},
            });
        });

        // Only run the force-directed layout when there is something to lay out.
        // For a fresh (empty) automaton, `cose` with the default `fit: true` would
        // zoom the empty viewport to an extreme factor, leaving the first states a
        // student adds huge and overlapping. A preset/no-op layout keeps zoom at 1.
        var hasElements = elements.length > 0;

        var cy = cytoscape({
            container: container,
            elements: elements,
            // Clamp zoom so auto-fit (on a saved automaton with few nodes) never
            // overshoots into an unusable 2-3x zoom.
            minZoom: 0.3,
            maxZoom: 1.5,
            style: [
                {
                    selector: 'node',
                    style: {
                        'label': 'data(label)',
                        'text-valign': 'center',
                        'background-color': '#ddd',
                    },
                },
                {
                    selector: 'node.start',
                    style: {
                        'border-width': 3,
                        'border-color': '#1e88e5',
                    },
                },
                {
                    selector: 'node.final',
                    style: {
                        'border-style': 'double',
                        'border-width': 4,
                    },
                },
                {
                    selector: 'node.trace-visited',
                    style: {
                        'background-color': '#ffe066',
                        'transition-property': 'background-color',
                        'transition-duration': '300ms',
                    },
                },
                {
                    selector: 'edge',
                    style: {
                        'label': 'data(label)',
                        'curve-style': 'bezier',
                        'target-arrow-shape': 'triangle',
                    },
                },
            ],
            layout: hasElements
                ? {
                    name: 'cose',
                    fit: true,
                    padding: 60,
                    animate: false,
                    // Spread states apart so they do not pile up / overlap.
                    idealEdgeLength: 90,
                    nodeRepulsion: 9000,
                    nodeOverlap: 24,
                }
                : {name: 'preset'},
        });

        // Belt-and-braces: after the initial layout settles, guarantee a usable
        // viewport even if a saved automaton fit pushed zoom to the clamp ceiling.
        cy.ready(function() {
            if (!isFinite(cy.zoom()) || cy.zoom() <= 0) {
                cy.zoom(1);
                cy.center();
            }
        });

        return cy;
    };

    return {
        create: create,
    };
});
