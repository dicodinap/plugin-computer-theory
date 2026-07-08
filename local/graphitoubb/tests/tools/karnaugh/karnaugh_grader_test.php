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
 * Unit tests for the karnaugh grader (AC1–AC4).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\karnaugh\grader;

/**
 * @covers \local_graphitoubb\tools\karnaugh\grader\karnaugh_grader
 * @covers \local_graphitoubb\tools\karnaugh\domain\kmap
 * @covers \local_graphitoubb\tools\karnaugh\domain\minimize
 */
final class karnaugh_grader_test extends \basic_testcase {

    /** The AC1 problem: n=3, minterms {0,2,3,4,7}, optimal cover = 3. */
    private function problem(bool $requireminimal = true): array {
        return ['tool' => 'karnaugh', 'type' => 'simplify', 'config' => [
            'n_vars' => 3, 'var_names' => ['A', 'B', 'C'], 'minterms' => [0, 2, 3, 4, 7],
            'require_minimal' => $requireminimal,
            'scoring' => ['fill_weight' => 40, 'grouping_weight' => 60],
        ]];
    }

    /** Correct fill for {0,2,3,4,7}. */
    private function correct_cells(): array {
        $cells = [];
        for ($i = 0; $i < 8; $i++) {
            $cells[(string) $i] = in_array($i, [0, 2, 3, 4, 7], true) ? 1 : 0;
        }
        return $cells;
    }

    private function envelope(array $cells, array $groups): string {
        return json_encode(['answer_kind' => 'kmap', 'map' => ['cells' => $cells], 'groups' => $groups]);
    }

    /** AC1: full fill + 4 legal equivalent groups (one redundant) → 0.925, passed. */
    public function test_ac1_fill_and_grouping_partial_credit(): void {
        $groups = [
            ['id' => 'g0', 'cells' => [0, 4]],
            ['id' => 'g1', 'cells' => [3, 7]],
            ['id' => 'g2', 'cells' => [2, 3]],
            ['id' => 'g3', 'cells' => [0, 2]],
        ];
        $r = (new karnaugh_grader())->grade($this->problem(), $this->envelope($this->correct_cells(), $groups));
        $this->assertEqualsWithDelta(0.925, $r['fraction'], 1e-9);
        $this->assertTrue($r['passed']);
        $this->assertFalse($r['invalid']);
        // Minimality result names optimal=3, used=4.
        $min = null;
        foreach ($r['results'] as $x) {
            if ($x['check'] === 'minimality') {
                $min = $x;
            }
        }
        $this->assertNotNull($min);
        $this->assertSame(3, $min['expected']);
        $this->assertSame(4, $min['got']);
    }

    /** AC2: a group covering a 0-cell ⇒ not equivalent ⇒ grouping 0 (fraction 0.4). */
    public function test_ac2_group_covers_zero_not_equivalent(): void {
        $groups = [['id' => 'g0', 'cells' => [0, 4]], ['id' => 'g1', 'cells' => [1, 3]]];
        $r = (new karnaugh_grader())->grade($this->problem(), $this->envelope($this->correct_cells(), $groups));
        $this->assertEqualsWithDelta(0.4, $r['fraction'], 1e-9);
        // g1 (cells 1,3) covers cell 1 which is a 0.
        $flagged = false;
        foreach ($r['results'] as $x) {
            if ($x['check'] === 'group:g1' && $x['correct'] === false) {
                $flagged = true;
            }
        }
        $this->assertTrue($flagged);
    }

    /** AC3: require_minimal=false ⇒ full grouping credit for a valid equivalent cover. */
    public function test_ac3_minimality_off_full_credit(): void {
        $groups = [
            ['id' => 'g0', 'cells' => [0, 4]],
            ['id' => 'g1', 'cells' => [3, 7]],
            ['id' => 'g2', 'cells' => [2, 3]],
            ['id' => 'g3', 'cells' => [0, 2]],
        ];
        $r = (new karnaugh_grader())->grade($this->problem(false), $this->envelope($this->correct_cells(), $groups));
        $this->assertEqualsWithDelta(1.0, $r['fraction'], 1e-9);
    }

    /** AC4: two mis-placed fill cells are flagged (fill_fraction 6/8). */
    public function test_ac4_misplaced_fill_flagged(): void {
        $cells = $this->correct_cells();
        $cells['0'] = 0; // Was 1.
        $cells['1'] = 1; // Was 0.
        $groups = [
            ['id' => 'g0', 'cells' => [0, 4]],
            ['id' => 'g1', 'cells' => [3, 7]],
            ['id' => 'g2', 'cells' => [2, 3]],
        ];
        $r = (new karnaugh_grader())->grade($this->problem(), $this->envelope($cells, $groups));
        $flags = array_filter($r['results'], static fn($x) => strpos($x['check'], 'cell:') === 0);
        $this->assertCount(2, $flags);
    }

    /** Empty groups ⇒ invalid (validity gate). */
    public function test_empty_groups_invalid(): void {
        $r = (new karnaugh_grader())->grade($this->problem(), $this->envelope($this->correct_cells(), []));
        $this->assertTrue($r['invalid']);
        $this->assertEqualsWithDelta(0.0, $r['fraction'], 1e-9);
    }

    /** Null submission ⇒ invalid (contract-shape guard). */
    public function test_null_submission_invalid(): void {
        $r = (new karnaugh_grader())->grade($this->problem(), null);
        $this->assertTrue($r['invalid']);
        foreach (['graded', 'invalid', 'message', 'score', 'fraction', 'passed',
                  'items_total', 'items_correct', 'results'] as $k) {
            $this->assertArrayHasKey($k, $r);
        }
    }

    /** Tautology (all 1s) ⇒ optimal cover of 1 full-map group. */
    public function test_tautology_optimal_one(): void {
        $problem = ['tool' => 'karnaugh', 'type' => 'simplify', 'config' => [
            'n_vars' => 2, 'var_names' => ['A', 'B'], 'minterms' => [0, 1, 2, 3],
            'require_minimal' => true, 'scoring' => ['fill_weight' => 40, 'grouping_weight' => 60],
        ]];
        $cells = ['0' => 1, '1' => 1, '2' => 1, '3' => 1];
        $groups = [['id' => 'g0', 'cells' => [0, 1, 2, 3]]];
        $r = (new karnaugh_grader())->grade($problem, $this->envelope($cells, $groups));
        $this->assertEqualsWithDelta(1.0, $r['fraction'], 1e-9);
    }
}
