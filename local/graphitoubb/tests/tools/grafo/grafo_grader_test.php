<?php
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
 * Unit tests for the grafo grader (AC1–AC3).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\grafo\grader;

/**
 * @covers \local_graphitoubb\tools\grafo\grader\grafo_grader
 */
final class grafo_grader_test extends \basic_testcase {

    /**
     * Build a graph envelope JSON for a construct submission.
     *
     * @param  array $nodes
     * @param  array $edges
     * @param  bool  $directed
     * @return string
     */
    private function graph_envelope(array $nodes, array $edges, bool $directed = false): string {
        return json_encode([
            'answer_kind' => 'graph',
            'graph'       => ['nodes' => $nodes, 'edges' => $edges, 'directed' => $directed],
        ]);
    }

    /**
     * AC1: construct partial credit — 6-vertex connected non-bipartite graph
     * against {n_vertices:6, connected:true, bipartite:true} → 2/3, passed.
     */
    public function test_construct_partial_credit(): void {
        $nodes = [];
        for ($i = 0; $i < 6; $i++) {
            $nodes[] = ['id' => 'v' . $i, 'label' => (string) $i];
        }
        // A 5-cycle (odd cycle → non-bipartite) plus v5 attached to v0 (connected, 6 vertices).
        $edges = [
            ['id' => 'e0', 'from' => 'v0', 'to' => 'v1'],
            ['id' => 'e1', 'from' => 'v1', 'to' => 'v2'],
            ['id' => 'e2', 'from' => 'v2', 'to' => 'v3'],
            ['id' => 'e3', 'from' => 'v3', 'to' => 'v4'],
            ['id' => 'e4', 'from' => 'v4', 'to' => 'v0'],
            ['id' => 'e5', 'from' => 'v0', 'to' => 'v5'],
        ];
        $problem = ['type' => 'construct', 'config' => [
            'directed'    => false,
            'constraints' => ['n_vertices' => 6, 'connected' => true, 'bipartite' => true],
        ]];

        $res = (new grafo_grader())->grade($problem, $this->graph_envelope($nodes, $edges));

        $this->assertFalse($res['invalid']);
        $this->assertEqualsWithDelta(2 / 3, $res['fraction'], 0.0001);
        $this->assertTrue($res['passed']);
        // The bipartite check must be the failed one.
        $bip = array_values(array_filter($res['results'], fn($r) => $r['check'] === 'bipartite'))[0];
        $this->assertFalse($bip['correct']);
    }

    /**
     * D18: empty canvas → invalid, fraction 0 (not vacuous credit).
     */
    public function test_construct_empty_canvas_invalid(): void {
        $problem = ['type' => 'construct', 'config' => [
            'constraints' => ['bipartite' => true, 'acyclic' => true],
        ]];
        $res = (new grafo_grader())->grade($problem, $this->graph_envelope([], []));
        $this->assertTrue($res['invalid']);
        $this->assertSame(0.0, $res['fraction']);
        $this->assertSame('empty', $res['message']);
    }

    /**
     * D18: 5-node answer to a 6-vertex prompt passes the gate but fails n_vertices.
     */
    public function test_construct_wrong_vertex_count_still_scored(): void {
        $nodes = [];
        for ($i = 0; $i < 5; $i++) {
            $nodes[] = ['id' => 'v' . $i];
        }
        $edges = [
            ['id' => 'e0', 'from' => 'v0', 'to' => 'v1'],
            ['id' => 'e1', 'from' => 'v1', 'to' => 'v2'],
            ['id' => 'e2', 'from' => 'v2', 'to' => 'v3'],
            ['id' => 'e3', 'from' => 'v3', 'to' => 'v4'],
        ]; // Path: connected, bipartite.
        $problem = ['type' => 'construct', 'config' => [
            'constraints' => ['n_vertices' => 6, 'connected' => true, 'bipartite' => true],
        ]];
        $res = (new grafo_grader())->grade($problem, $this->graph_envelope($nodes, $edges));
        $this->assertFalse($res['invalid']);
        $this->assertEqualsWithDelta(2 / 3, $res['fraction'], 0.0001);
    }

    /**
     * AC2: Königsberg decision — 4-vertex 7-bridge multigraph, has_euler_circuit.
     * All four vertices have odd degree ⇒ correct answer is false.
     */
    public function test_decision_konigsberg(): void {
        // Vertices A,B,C,D. Classic bridges: A-B x2, A-C x2, A-D, B-D, C-D.
        $konig = [
            'nodes' => [['id' => 'A'], ['id' => 'B'], ['id' => 'C'], ['id' => 'D']],
            'edges' => [
                ['id' => 'e0', 'from' => 'A', 'to' => 'B'],
                ['id' => 'e1', 'from' => 'A', 'to' => 'B'],
                ['id' => 'e2', 'from' => 'A', 'to' => 'C'],
                ['id' => 'e3', 'from' => 'A', 'to' => 'C'],
                ['id' => 'e4', 'from' => 'A', 'to' => 'D'],
                ['id' => 'e5', 'from' => 'B', 'to' => 'D'],
                ['id' => 'e6', 'from' => 'C', 'to' => 'D'],
            ],
            'directed' => false,
        ];
        $problem = ['type' => 'decision', 'config' => [
            'given_graph' => $konig,
            'question'    => 'has_euler_circuit',
        ]];

        // Student answers "yes" → wrong.
        $yes = (new grafo_grader())->grade($problem, json_encode(['answer_kind' => 'boolean', 'value' => true]));
        $this->assertSame(0.0, $yes['fraction']);
        $this->assertFalse($yes['passed']);

        // Student answers "no" → correct.
        $no = (new grafo_grader())->grade($problem, json_encode(['answer_kind' => 'boolean', 'value' => false]));
        $this->assertSame(1.0, $no['fraction']);
        $this->assertTrue($no['passed']);
    }

    /**
     * AC3: traversal — any valid Euler circuit passes; reuse/omission fails.
     */
    public function test_traversal_euler_circuit(): void {
        // Triangle A-B-C-A: every vertex even degree, connected ⇒ euler circuit exists.
        $tri = [
            'nodes' => [['id' => 'A'], ['id' => 'B'], ['id' => 'C']],
            'edges' => [
                ['id' => 'e0', 'from' => 'A', 'to' => 'B'],
                ['id' => 'e1', 'from' => 'B', 'to' => 'C'],
                ['id' => 'e2', 'from' => 'C', 'to' => 'A'],
            ],
            'directed' => false,
        ];
        $problem = ['type' => 'traversal', 'config' => [
            'given_graph' => $tri,
            'walk_kind'   => 'euler_circuit',
        ]];

        $ok = (new grafo_grader())->grade($problem,
            json_encode(['answer_kind' => 'sequence', 'edges' => ['e0', 'e1', 'e2']]));
        $this->assertSame(1.0, $ok['fraction']);

        // Omits an edge → invalid walk → 0.
        $short = (new grafo_grader())->grade($problem,
            json_encode(['answer_kind' => 'sequence', 'edges' => ['e0', 'e1']]));
        $this->assertSame(0.0, $short['fraction']);

        // Reuses an edge id → 0.
        $reuse = (new grafo_grader())->grade($problem,
            json_encode(['answer_kind' => 'sequence', 'edges' => ['e0', 'e1', 'e2', 'e0']]));
        $this->assertSame(0.0, $reuse['fraction']);

        // Empty answer → invalid.
        $empty = (new grafo_grader())->grade($problem,
            json_encode(['answer_kind' => 'sequence', 'edges' => []]));
        $this->assertTrue($empty['invalid']);
    }
}
