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
 * Unit tests for the AFD validator.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use local_graphitoubb\tools\afd\domain\automaton;
use local_graphitoubb\tools\afd\domain\state;
use local_graphitoubb\tools\afd\domain\transition;
use local_graphitoubb\tools\afd\domain\validator;

/**
 * Tests for automaton validator.
 *
 * @covers \local_graphitoubb\tools\afd\domain\validator
 */
final class validator_test extends \basic_testcase {
    /**
     * Build a minimal valid automaton: q0 -a-> q1, q1 accepting.
     */
    private function make_valid(): automaton {
        return new automaton(
            [new state('q0'), new state('q1')],
            ['a'],
            [new transition('q0', 'a', 'q1')],
            'q0',
            ['q1']
        );
    }

    public function test_valid_automaton_returns_no_errors(): void {
        $errors = (new validator())->validate($this->make_valid());
        $this->assertSame([], $errors);
    }

    public function test_bounds_constants_values(): void {
        $this->assertSame(64, validator::MAX_STATES);
        $this->assertSame(16, validator::MAX_ALPHABET);
        $this->assertSame(512, validator::MAX_TRANSITIONS);
        $this->assertSame(256, validator::MAX_INPUT_LENGTH);
        $this->assertSame(32, validator::MAX_LABEL_LENGTH);
    }

    public function test_exceeds_max_states_produces_error(): void {
        $states = [];
        for ($i = 0; $i <= validator::MAX_STATES; $i++) {
            $states[] = new state("q{$i}");
        }
        $a = new automaton($states, ['a'], [], 'q0', []);
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('states', $errors[0]);
    }

    public function test_exceeds_max_alphabet_produces_error(): void {
        $alphabet = [];
        for ($i = 0; $i <= validator::MAX_ALPHABET; $i++) {
            $alphabet[] = "s{$i}";
        }
        $a = new automaton([new state('q0')], $alphabet, [], 'q0', []);
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('alphabet', $errors[0]);
    }

    public function test_exceeds_max_transitions_produces_error(): void {
        $transitions = [];
        for ($i = 0; $i <= validator::MAX_TRANSITIONS; $i++) {
            $transitions[] = new transition('q0', "s{$i}", 'q0');
        }
        $a = new automaton([new state('q0')], [], $transitions, 'q0', []);
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('transitions', $errors[0]);
    }

    public function test_start_state_not_in_states_produces_error(): void {
        $a = new automaton([new state('q0')], ['a'], [], 'q_missing', []);
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('start', $errors[0]);
    }

    public function test_final_state_not_in_states_produces_error(): void {
        $a = new automaton([new state('q0')], ['a'], [], 'q0', ['q_missing']);
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('final', $errors[0]);
    }

    public function test_transition_from_unknown_state_produces_error(): void {
        $a = new automaton(
            [new state('q0'), new state('q1')],
            ['a'],
            [new transition('q_ghost', 'a', 'q1')],
            'q0',
            ['q1']
        );
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
    }

    public function test_transition_to_unknown_state_produces_error(): void {
        $a = new automaton(
            [new state('q0'), new state('q1')],
            ['a'],
            [new transition('q0', 'a', 'q_ghost')],
            'q0',
            ['q1']
        );
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
    }

    public function test_transition_symbol_not_in_alphabet_produces_error(): void {
        $a = new automaton(
            [new state('q0'), new state('q1')],
            ['a'],
            [new transition('q0', 'z', 'q1')],
            'q0',
            ['q1']
        );
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('symbol', $errors[0]);
    }

    public function test_duplicate_from_symbol_pair_is_nondeterministic(): void {
        $a = new automaton(
            [new state('q0'), new state('q1'), new state('q2')],
            ['a'],
            [
                new transition('q0', 'a', 'q1'),
                new transition('q0', 'a', 'q2'),
            ],
            'q0',
            ['q1']
        );
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('deterministic', $errors[0]);
    }

    public function test_label_exceeds_max_length_produces_error(): void {
        $longlabel = str_repeat('x', validator::MAX_LABEL_LENGTH + 1);
        $a = new automaton(
            [new state('q0', $longlabel)],
            ['a'],
            [],
            'q0',
            []
        );
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('label', $errors[0]);
    }

    public function test_multiple_errors_are_all_reported(): void {
        // Start missing + final missing — expect at least 2 errors.
        $a = new automaton([new state('q0')], [], [], 'q_bad', ['q_also_bad']);
        $errors = (new validator())->validate($a);
        $this->assertGreaterThanOrEqual(2, count($errors));
    }

    public function test_class_is_final(): void {
        $r = new \ReflectionClass(validator::class);
        $this->assertTrue($r->isFinal());
    }
}
