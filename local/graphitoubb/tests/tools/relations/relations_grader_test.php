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
 * Unit tests for the relations grader (AC6–AC7).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\relations\grader;

/**
 * @covers \local_graphitoubb\tools\relations\grader\relations_grader
 * @covers \local_graphitoubb\tools\relations\domain\relation
 */
final class relations_grader_test extends \basic_testcase {

    /** S={1,2,3}, R={(1,1),(2,2),(3,3),(1,2)}: reflexive✓ symmetric✗ antisym✓ transitive✓. */
    private function problem(): array {
        return ['tool' => 'relations', 'type' => 'analyze', 'config' => [
            'base_set' => ['1', '2', '3'],
            'relation' => [['1', '1'], ['2', '2'], ['3', '3'], ['1', '2']],
            'ask_properties' => ['reflexive', 'symmetric', 'antisymmetric', 'transitive'],
            'scoring' => ['representation_weight' => 40, 'properties_weight' => 60],
        ]];
    }

    private function envelope(array $pairs, array $props): string {
        return json_encode(['answer_kind' => 'relation', 'representation' => 'pairs',
            'pairs' => $pairs, 'properties' => $props]);
    }

    /** AC6 (declared-true-but-false): symmetric=true is wrong → 0.85 + counterexample (1,2). */
    public function test_ac6_wrong_symmetric_with_counterexample(): void {
        $env = $this->envelope(
            [['1', '1'], ['2', '2'], ['3', '3'], ['1', '2']],
            ['reflexive' => true, 'symmetric' => true, 'antisymmetric' => true, 'transitive' => true]
        );
        $r = (new relations_grader())->grade($this->problem(), $env);
        $this->assertEqualsWithDelta(0.85, $r['fraction'], 1e-9);
        $sym = $this->result_for($r, 'property:symmetric');
        $this->assertFalse($sym['correct']);
        $this->assertIsArray($sym['got']);
        $this->assertNotNull($sym['got']['counterexample']);
        $this->assertSame('1', $sym['got']['counterexample']['a']);
        $this->assertSame('2', $sym['got']['counterexample']['b']);
    }

    /** AC6 (declared-false-but-true): transitive=false is wrong → 0.85, NO counterexample. */
    public function test_ac6_wrong_transitive_no_counterexample(): void {
        $env = $this->envelope(
            [['1', '1'], ['2', '2'], ['3', '3'], ['1', '2']],
            ['reflexive' => true, 'symmetric' => false, 'antisymmetric' => true, 'transitive' => false]
        );
        $r = (new relations_grader())->grade($this->problem(), $env);
        $this->assertEqualsWithDelta(0.85, $r['fraction'], 1e-9);
        $trans = $this->result_for($r, 'property:transitive');
        $this->assertFalse($trans['correct']);
        $this->assertNull($trans['got']['counterexample']);
    }

    /** AC7: wrong relation (missing one pair) but correct properties → rep 0.75, props 1.0. */
    public function test_ac7_representation_independent_of_properties(): void {
        $env = $this->envelope(
            [['1', '1'], ['2', '2'], ['3', '3']], // Missing (1,2).
            ['reflexive' => true, 'symmetric' => false, 'antisymmetric' => true, 'transitive' => true]
        );
        $r = (new relations_grader())->grade($this->problem(), $env);
        // rep Jaccard = 3/4; props all correct → 0.4*0.75 + 0.6*1 = 0.9.
        $this->assertEqualsWithDelta(0.9, $r['fraction'], 1e-9);
        $rep = $this->result_for($r, 'representation');
        $this->assertCount(1, $rep['got']['missing']);
    }

    /** All correct ⇒ 1.0. */
    public function test_all_correct(): void {
        $env = $this->envelope(
            [['1', '1'], ['2', '2'], ['3', '3'], ['1', '2']],
            ['reflexive' => true, 'symmetric' => false, 'antisymmetric' => true, 'transitive' => true]
        );
        $r = (new relations_grader())->grade($this->problem(), $env);
        $this->assertEqualsWithDelta(1.0, $r['fraction'], 1e-9);
        $this->assertTrue($r['passed']);
    }

    /** Empty relation R=∅ is vacuously symmetric/antisymmetric/transitive. */
    public function test_empty_relation_properties(): void {
        $problem = ['tool' => 'relations', 'type' => 'analyze', 'config' => [
            'base_set' => ['1', '2'], 'relation' => [],
            'ask_properties' => ['reflexive', 'symmetric', 'antisymmetric', 'transitive'],
            'scoring' => ['representation_weight' => 40, 'properties_weight' => 60],
        ]];
        // Student builds empty R and declares: reflexive=false (S≠∅), others=true.
        $env = $this->envelope([], ['reflexive' => false, 'symmetric' => true,
            'antisymmetric' => true, 'transitive' => true]);
        $r = (new relations_grader())->grade($problem, $env);
        $this->assertEqualsWithDelta(1.0, $r['fraction'], 1e-9);
    }

    /** Null submission ⇒ invalid with full contract shape. */
    public function test_null_submission_invalid(): void {
        $r = (new relations_grader())->grade($this->problem(), null);
        $this->assertTrue($r['invalid']);
        foreach (['graded', 'invalid', 'message', 'score', 'fraction', 'passed',
                  'items_total', 'items_correct', 'results'] as $k) {
            $this->assertArrayHasKey($k, $r);
        }
    }

    /**
     * Find a result row by check name.
     *
     * @param  array  $result
     * @param  string $check
     * @return array
     */
    private function result_for(array $result, string $check): array {
        foreach ($result['results'] as $x) {
            if ($x['check'] === $check) {
                return $x;
            }
        }
        $this->fail("no result row for {$check}");
    }
}
