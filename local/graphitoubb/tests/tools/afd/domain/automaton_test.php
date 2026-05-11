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
 * Unit tests for the AFD automaton aggregate root.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use local_graphitoubb\tools\afd\domain\automaton;
use local_graphitoubb\tools\afd\domain\state;
use local_graphitoubb\tools\afd\domain\transition;

/**
 * Tests for automaton aggregate root.
 *
 * @covers \local_graphitoubb\tools\afd\domain\automaton
 */
final class automaton_test extends \basic_testcase {
    /**
     * Build a minimal valid automaton: q0 -a-> q1, q1 accepting.
     */
    private function make_simple(): automaton {
        $q0 = new state('q0');
        $q1 = new state('q1', 'Accept');
        return new automaton(
            [$q0, $q1],
            ['a'],
            [new transition('q0', 'a', 'q1')],
            'q0',
            ['q1']
        );
    }

    public function test_get_states_returns_all_states(): void {
        $a = $this->make_simple();
        $states = $a->get_states();
        $this->assertCount(2, $states);
        $this->assertSame('q0', $states[0]->get_id());
        $this->assertSame('q1', $states[1]->get_id());
    }

    public function test_get_alphabet_returns_symbols(): void {
        $a = $this->make_simple();
        $this->assertSame(['a'], $a->get_alphabet());
    }

    public function test_get_transitions_returns_transitions(): void {
        $a = $this->make_simple();
        $ts = $a->get_transitions();
        $this->assertCount(1, $ts);
        $this->assertSame('q0', $ts[0]->get_from());
        $this->assertSame('a', $ts[0]->get_symbol());
        $this->assertSame('q1', $ts[0]->get_to());
    }

    public function test_get_start_returns_start_state_id(): void {
        $a = $this->make_simple();
        $this->assertSame('q0', $a->get_start());
    }

    public function test_get_finals_returns_accepting_state_ids(): void {
        $a = $this->make_simple();
        $this->assertSame(['q1'], $a->get_finals());
    }

    public function test_empty_finals_is_valid(): void {
        $a = new automaton([new state('q0')], ['a'], [], 'q0', []);
        $this->assertSame([], $a->get_finals());
    }

    public function test_multiple_finals(): void {
        $states = [new state('q0'), new state('q1'), new state('q2')];
        $a = new automaton($states, ['a'], [], 'q0', ['q1', 'q2']);
        $this->assertSame(['q1', 'q2'], $a->get_finals());
    }

    public function test_class_is_final(): void {
        $r = new \ReflectionClass(automaton::class);
        $this->assertTrue($r->isFinal());
    }
}
