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
 * Unit tests for the arbol grader (AC4, AC5).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\arbol\grader;

use local_graphitoubb\tools\arbol\domain\bst;

/**
 * @covers \local_graphitoubb\tools\arbol\grader\arbol_grader
 */
final class arbol_grader_test extends \basic_testcase {

    /**
     * Build a tree envelope JSON from a path→value map (paths: '' root, then L/R).
     *
     * @param  array<string,int> $posmap
     * @return string
     */
    private function tree_envelope_from_posmap(array $posmap): string {
        // Assign a node id per path; edges by L/R from the parent path.
        $idfor = [];
        $nodes = [];
        $i = 0;
        foreach ($posmap as $path => $value) {
            $id = 'n' . $i++;
            $idfor[$path] = $id;
            $nodes[] = ['id' => $id, 'label' => (string) $value, 'value' => $value];
        }
        $edges = [];
        $e = 0;
        foreach ($posmap as $path => $value) {
            if ($path === '') {
                continue;
            }
            $parentpath = substr($path, 0, -1);
            $side       = substr($path, -1);
            $edges[] = [
                'id'     => 'e' . $e++,
                'parent' => $idfor[$parentpath],
                'child'  => $idfor[$path],
                'side'   => $side,
            ];
        }
        return json_encode([
            'answer_kind' => 'tree',
            'tree'        => ['nodes' => $nodes, 'edges' => $edges, 'root' => $idfor['']],
        ]);
    }

    /**
     * AC4: bst_build — correct BST for [8,3,10,1,6] → 1.0; 1 misplaced → 4/5.
     */
    public function test_bst_build(): void {
        $insertions = [8, 3, 10, 1, 6];
        $problem = ['type' => 'bst_build', 'config' => ['insertions' => $insertions]];

        // Correct canonical tree.
        $correct = bst::position_value_map($insertions);
        $res = (new arbol_grader())->grade($problem, $this->tree_envelope_from_posmap($correct));
        $this->assertFalse($res['invalid']);
        $this->assertSame(1.0, $res['fraction']);
        $this->assertTrue($res['passed']);

        // Misplace one node: swap the value at position 'LR' (6) to a wrong value.
        // Canonical: ''=8, L=3, R=10, LL=1, LR=6. Change LR to 7 → 4/5 correct.
        $wrong = $correct;
        $wrong['LR'] = 7;
        $res2 = (new arbol_grader())->grade($problem, $this->tree_envelope_from_posmap($wrong));
        $this->assertEqualsWithDelta(4 / 5, $res2['fraction'], 0.0001);
    }

    /**
     * bst_build: empty / invalid tree → invalid.
     */
    public function test_bst_build_empty_invalid(): void {
        $problem = ['type' => 'bst_build', 'config' => ['insertions' => [8, 3, 10]]];
        $res = (new arbol_grader())->grade($problem,
            json_encode(['answer_kind' => 'tree', 'tree' => ['nodes' => [], 'edges' => [], 'root' => null]]));
        $this->assertTrue($res['invalid']);
        $this->assertSame(0.0, $res['fraction']);
    }

    /**
     * AC5: traversal_answer — LCP ratio for in-order [1,3,6,8,10].
     */
    public function test_traversal_answer_lcp(): void {
        // Given tree = canonical BST of [8,3,10,1,6]; in-order = [1,3,6,8,10].
        $posmap = bst::position_value_map([8, 3, 10, 1, 6]);
        // Reuse the envelope builder to construct the given_tree structure.
        $envelope = json_decode($this->tree_envelope_from_posmap($posmap), true);
        $problem = ['type' => 'traversal_answer', 'config' => [
            'given_tree' => $envelope['tree'],
            'order'      => 'in',
        ]];

        $full = (new arbol_grader())->grade($problem,
            json_encode(['answer_kind' => 'sequence', 'values' => [1, 3, 6, 8, 10]]));
        $this->assertSame(1.0, $full['fraction']);

        // Matches first 2, then diverges → LCP 2 → 2/5.
        $partial = (new arbol_grader())->grade($problem,
            json_encode(['answer_kind' => 'sequence', 'values' => [1, 3, 8, 6, 10]]));
        $this->assertEqualsWithDelta(2 / 5, $partial['fraction'], 0.0001);

        // First value wrong → LCP 0 → 0.
        $zero = (new arbol_grader())->grade($problem,
            json_encode(['answer_kind' => 'sequence', 'values' => [8, 3, 1, 6, 10]]));
        $this->assertSame(0.0, $zero['fraction']);
    }

    /**
     * reconstruct: pre_in pair yields a unique tree; correct student tree → 1.0.
     */
    public function test_reconstruct_pre_in(): void {
        // Tree: 8(root) L=3(LL=1,LR=6) R=10. preorder=[8,3,1,6,10], inorder=[1,3,6,8,10].
        $problem = ['type' => 'reconstruct', 'config' => [
            'pair' => 'pre_in',
            'a'    => [8, 3, 1, 6, 10],
            'b'    => [1, 3, 6, 8, 10],
        ]];
        $canonical = \local_graphitoubb\tools\arbol\domain\tree_reconstruct::position_value_map(
            'pre_in', [8, 3, 1, 6, 10], [1, 3, 6, 8, 10]);
        $this->assertNotNull($canonical);
        $res = (new arbol_grader())->grade($problem, $this->tree_envelope_from_posmap($canonical));
        $this->assertSame(1.0, $res['fraction']);
    }
}
