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
 * Factory for Cytoscape instances used by the shared graph_canvas foundation.
 *
 * This is NEW code (D3/I2): it copies the generic Cytoscape config from
 * cytoscape_factory.js and adds two parameterised seams the AFD factory hardcodes
 * — the element-mapping function and the style selectors — so cytoscape_factory.js
 * stays byte-for-byte unchanged. Supports both grafo ({nodes,edges,directed}) and
 * arbol ({nodes,edges(parent/child,side),root}) payloads.
 *
 * @module     mod_graphitoubb/graph_canvas_factory
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_graphitoubb/cytoscape'], function(cytoscape) {

    /**
     * Map a grafo payload to Cytoscape elements.
     *
     * @param {object} payload {nodes, edges, directed}
     * @return {Array}
     */
    var grafoElements = function(payload) {
        var elements = [];
        (payload.nodes || []).forEach(function(n) {
            elements.push({data: {id: n.id, label: (n.label !== undefined && n.label !== '') ? n.label : n.id}});
        });
        (payload.edges || []).forEach(function(e) {
            elements.push({
                data: {
                    id: e.id,
                    source: e.from,
                    target: e.to,
                    label: (e.weight !== undefined && e.weight !== null) ? String(e.weight) : '',
                    weight: (e.weight !== undefined && e.weight !== null) ? e.weight : null,
                },
            });
        });
        return elements;
    };

    /**
     * Map an arbol payload to Cytoscape elements. Parent→child edges carry side.
     *
     * @param {object} payload {nodes, edges, root}
     * @return {Array}
     */
    var arbolElements = function(payload) {
        var elements = [];
        (payload.nodes || []).forEach(function(n) {
            elements.push({
                data: {
                    id: n.id,
                    label: (n.label !== undefined && n.label !== '') ? n.label : n.id,
                    value: (n.value !== undefined) ? n.value : null,
                },
                classes: (payload.root === n.id) ? 'tree-root' : '',
            });
        });
        (payload.edges || []).forEach(function(e) {
            elements.push({
                data: {
                    id: e.id,
                    source: e.parent,
                    target: e.child,
                    side: e.side || '',
                    label: e.side || '',
                },
                classes: e.side === 'L' ? 'edge-left' : (e.side === 'R' ? 'edge-right' : ''),
            });
        });
        return elements;
    };

    /**
     * Shared style list. Directed graphs get target arrows; undirected do not.
     *
     * @param {string} tool 'grafo' | 'arbol'
     * @param {boolean} directed
     * @return {Array}
     */
    var buildStyle = function(tool, directed) {
        var edgeStyle = {
            'label': 'data(label)',
            'curve-style': 'bezier',
            'width': 4,
            'line-color': '#8aa0c8',
            'font-size': '11px',
            'text-background-color': '#fff',
            'text-background-opacity': 1,
            'text-background-padding': '2px',
        };
        if (tool === 'grafo' && directed) {
            edgeStyle['target-arrow-shape'] = 'triangle';
        }
        if (tool === 'arbol') {
            edgeStyle['target-arrow-shape'] = 'triangle';
            edgeStyle['curve-style'] = 'straight';
        }
        return [
            {
                selector: 'node',
                style: {
                    'label': 'data(label)',
                    'text-valign': 'center',
                    'text-halign': 'center',
                    'background-color': '#cfe2ff',
                    'border-width': 1,
                    'border-color': '#6c8cbf',
                    'width': 34,
                    'height': 34,
                    'font-size': '13px',
                },
            },
            {
                selector: 'node.tree-root',
                style: {'border-width': 3, 'border-color': '#1e88e5', 'background-color': '#9ec5fe'},
            },
            {
                selector: 'node.selected-node',
                style: {'background-color': '#ffe066', 'border-color': '#f39c12', 'border-width': 3},
            },
            {
                selector: 'node.answer-visited',
                style: {'background-color': '#ffe066'},
            },
            {selector: 'edge', style: edgeStyle},
            {
                selector: 'edge.parallel',
                style: {'curve-style': 'bezier', 'control-point-step-size': 40},
            },
            {
                selector: 'edge.answer-picked',
                style: {
                    'line-color': '#f39c12',
                    'target-arrow-color': '#f39c12',
                    'width': 4,
                    'label': 'data(pickorder)',
                    'color': '#b5560c',
                    'font-weight': 'bold',
                },
            },
        ];
    };

    /**
     * Build a Cytoscape instance from a graph/tree payload.
     *
     * @param {HTMLElement} container
     * @param {object} payload  grafo/arbol descriptor.
     * @param {object} options  {tool, directed, readonly}
     * @return {object} Cytoscape instance.
     */
    var create = function(container, payload, options) {
        options = options || {};
        var tool = options.tool || 'grafo';
        var directed = !!options.directed;
        if (!payload) {
            payload = {nodes: [], edges: []};
        }
        var elements = (tool === 'arbol') ? arbolElements(payload) : grafoElements(payload);
        var hasElements = elements.length > 0;

        var cy = cytoscape({
            container: container,
            elements: elements,
            minZoom: 0.3,
            maxZoom: 2.0,
            // Read-only (given/review) canvases lock structure editing.
            userPanningEnabled: true,
            boxSelectionEnabled: false,
            autoungrabify: !!options.readonly,
            style: buildStyle(tool, directed),
            layout: hasElements
                ? (tool === 'arbol'
                    ? {name: 'breadthfirst', directed: true, padding: 30, spacingFactor: 1.1, fit: true,
                       roots: payload.root ? ['#' + payload.root] : undefined}
                    : {name: 'cose', fit: true, padding: 50, animate: false,
                       idealEdgeLength: 90, nodeRepulsion: 9000, nodeOverlap: 24})
                : {name: 'preset'},
        });

        markParallelEdges(cy);

        cy.ready(function() {
            if (!isFinite(cy.zoom()) || cy.zoom() <= 0) {
                cy.zoom(1);
                cy.center();
            }
        });
        return cy;
    };

    /**
     * Tag parallel edges (same undirected endpoints) so Cytoscape bows them apart.
     *
     * @param {object} cy
     */
    var markParallelEdges = function(cy) {
        var groups = {};
        cy.edges().forEach(function(e) {
            var a = e.source().id();
            var b = e.target().id();
            var key = (a < b) ? (a + '|' + b) : (b + '|' + a);
            (groups[key] = groups[key] || []).push(e);
        });
        Object.keys(groups).forEach(function(k) {
            if (groups[k].length > 1) {
                groups[k].forEach(function(e) {
                    e.addClass('parallel');
                });
            }
        });
    };

    return {
        create: create,
        markParallelEdges: markParallelEdges,
    };
});
