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
 * Unit tests for the AFD simulator.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use local_graphitoubb\tools\afd\domain\automaton;
use local_graphitoubb\tools\afd\domain\simulator;
use local_graphitoubb\tools\afd\domain\state;
use local_graphitoubb\tools\afd\domain\trace;
use local_graphitoubb\tools\afd\domain\transition;

/**
 * Tests for simulator.
 *
 * Automaton used in most tests: q0 -a-> q1 -b-> q1; start=q0; finals={q1}.
 * Accepted by: any string matching a(b)* — e.g. "a", "ab", "abb".
 * Rejected by: "", "b", "ba", "c".
 *
 * @covers \local_graphitoubb\tools\afd\domain\simulator
 */
final class simulator_test extends \basic_testcase {
    /**
     * Build the test automaton: q0 -a-> q1 -b-> q1; finals={q1}.
     *
     * @return automaton
     */
    private function make_automaton(): automaton {
        return new automaton(
            [new state('q0'), new state('q1')],
            ['a', 'b'],
            [
                new transition('q0', 'a', 'q1'),
                new transition('q1', 'b', 'q1'),
            ],
            'q0',
            ['q1']
        );
    }

    /**
     * Build a simulator instance.
     *
     * @return simulator
     */
    private function make_simulator(): simulator {
        return new simulator();
    }

    // Return type check.

    public function test_run_returns_trace(): void {
        $result = $this->make_simulator()->run($this->make_automaton(), 'a');
        $this->assertInstanceOf(trace::class, $result);
    }

    // Accepted inputs.

    public function test_single_a_is_accepted(): void {
        $t = $this->make_simulator()->run($this->make_automaton(), 'a');
        $this->assertTrue($t->is_accepted());
    }

    public function test_ab_is_accepted(): void {
        $t = $this->make_simulator()->run($this->make_automaton(), 'ab');
        $this->assertTrue($t->is_accepted());
    }

    public function test_abb_is_accepted(): void {
        $t = $this->make_simulator()->run($this->make_automaton(), 'abb');
        $this->assertTrue($t->is_accepted());
    }

    // Rejected inputs.

    public function test_empty_input_is_rejected(): void {
        $t = $this->make_simulator()->run($this->make_automaton(), '');
        $this->assertFalse($t->is_accepted());
    }

    public function test_b_alone_is_rejected(): void {
        $t = $this->make_simulator()->run($this->make_automaton(), 'b');
        $this->assertFalse($t->is_accepted());
    }

    public function test_unknown_symbol_causes_rejection(): void {
        $t = $this->make_simulator()->run($this->make_automaton(), 'c');
        $this->assertFalse($t->is_accepted());
    }

    public function test_no_transition_from_state_causes_rejection(): void {
        // State q0 has no 'b' transition — simulator gets stuck.
        $t = $this->make_simulator()->run($this->make_automaton(), 'ba');
        $this->assertFalse($t->is_accepted());
    }

    // Trace step assertions.

    public function test_trace_has_one_step_per_symbol(): void {
        $t = $this->make_simulator()->run($this->make_automaton(), 'ab');
        $this->assertCount(2, $t->get_steps());
    }

    public function test_trace_step_fields(): void {
        $t     = $this->make_simulator()->run($this->make_automaton(), 'a');
        $steps = $t->get_steps();
        $this->assertSame('q0', $steps[0]['current']);
        $this->assertSame('a', $steps[0]['symbol']);
        $this->assertSame('q1', $steps[0]['next']);
    }

    public function test_empty_input_has_no_steps(): void {
        $t = $this->make_simulator()->run($this->make_automaton(), '');
        $this->assertSame([], $t->get_steps());
    }

    public function test_stuck_transition_stops_trace(): void {
        // State q0 has no 'b' transition; simulator stops after 0 consumed symbols.
        $t = $this->make_simulator()->run($this->make_automaton(), 'b');
        $this->assertCount(0, $t->get_steps());
    }

    // Start state as accepting state.

    public function test_start_state_accepting_empty_input(): void {
        $a = new automaton(
            [new state('q0')],
            ['a'],
            [],
            'q0',
            ['q0']
        );
        $t = $this->make_simulator()->run($a, '');
        $this->assertTrue($t->is_accepted());
    }

    public function test_class_is_final(): void {
        $r = new \ReflectionClass(simulator::class);
        $this->assertTrue($r->isFinal());
    }
}
