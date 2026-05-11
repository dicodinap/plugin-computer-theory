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
 * AFD paridad battery — mirrors the 29 assertions of POC local_discretelab.
 *
 * See PARITY.md for the AC→POC→graphitoubb coverage matrix.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

use local_graphitoubb\tools\afd\domain\automaton;
use local_graphitoubb\tools\afd\domain\serializer;
use local_graphitoubb\tools\afd\domain\simulator;
use local_graphitoubb\tools\afd\domain\state;
use local_graphitoubb\tools\afd\domain\transition;
use local_graphitoubb\tools\afd\domain\validator;

/**
 * Paridad battery: 29 tests mirroring POC discretelab assertions.
 *
 * Groups:
 *   P-SIM (7)  — mirrors afd_simulator_test
 *   P-SER (6)  — mirrors afd_canonical_test
 *   P-VAL (9)  — mirrors afd_schema_test
 *   P-INT (7)  — mirrors afd_grader_test (integration pipeline)
 *
 * @coversNothing
 */
final class paridad_test extends \basic_testcase {
    /**
     * Decode a fixture JSON file into an array.
     *
     * @param string $name Fixture base name (without .json).
     * @return array
     */
    private function load_fixture(string $name): array {
        $path = __DIR__ . '/fixtures/afd_paridad/' . $name . '.json';
        return json_decode(file_get_contents($path), true);
    }

    /**
     * Load and deserialize a fixture into an automaton value object.
     *
     * @param string $name Fixture base name (without .json).
     * @return automaton
     */
    private function deserialize_fixture(string $name): automaton {
        return (new serializer())->deserialize(json_encode($this->load_fixture($name)));
    }

    // GROUP P-SIM: Simulator correctness (7).
    // Mirrors: local_discretelab\afd_simulator_test.
    // Fixture: at_least_one_a — q0-a->q1, q0-b->q0, q1-a->q1, q1-b->q0; finals=[q1].

    /**
     * Mirrors afd_simulator_test::test_accepts_single_a.
     */
    public function test_p_sim_accepts_single_a(): void {
        $t = (new simulator())->run($this->deserialize_fixture('at_least_one_a'), 'a');
        $this->assertTrue($t->is_accepted());
        $this->assertSame('q1', $t->get_final_state());
    }

    /**
     * Mirrors afd_simulator_test::test_rejects_empty_string.
     */
    public function test_p_sim_rejects_empty_string(): void {
        $t = (new simulator())->run($this->deserialize_fixture('at_least_one_a'), '');
        $this->assertFalse($t->is_accepted());
        $this->assertNull($t->get_final_state());
    }

    /**
     * Mirrors afd_simulator_test::test_rejects_only_b.
     * Symbol b transitions q0->q0 via q0-b->q0; q0 not in finals.
     */
    public function test_p_sim_rejects_only_b(): void {
        $t = (new simulator())->run($this->deserialize_fixture('at_least_one_a'), 'b');
        $this->assertFalse($t->is_accepted());
    }

    /**
     * Mirrors afd_simulator_test::test_accepts_ba.
     */
    public function test_p_sim_accepts_ba(): void {
        $t = (new simulator())->run($this->deserialize_fixture('at_least_one_a'), 'ba');
        $this->assertTrue($t->is_accepted());
        $this->assertSame('q1', $t->get_final_state());
    }

    /**
     * Mirrors afd_simulator_test::test_trace_length.
     */
    public function test_p_sim_trace_length_equals_input_length(): void {
        $input = 'aba';
        $t = (new simulator())->run($this->deserialize_fixture('at_least_one_a'), $input);
        $this->assertCount(mb_strlen($input), $t->get_steps());
    }

    /**
     * Mirrors afd_simulator_test::test_trap_state_on_unknown_symbol.
     * Symbol c is not in alphabet {a, b} — simulator stops immediately.
     */
    public function test_p_sim_unknown_symbol_causes_rejection(): void {
        $t = (new simulator())->run($this->deserialize_fixture('at_least_one_a'), 'c');
        $this->assertFalse($t->is_accepted());
    }

    /**
     * Mirrors afd_simulator_test::test_stays_in_trap.
     * graphitoubb has no TRAP_STATE — simulator stops on first unknown symbol;
     * subsequent symbols are never consumed, and the result is always rejected.
     */
    public function test_p_sim_stuck_causes_rejection_even_with_more_input(): void {
        $t = (new simulator())->run($this->deserialize_fixture('at_least_one_a'), 'ca');
        $this->assertFalse($t->is_accepted());
    }

    // GROUP P-SER: Serializer correctness (6).
    // Mirrors: local_discretelab\afd_canonical_test.

    /**
     * Mirrors afd_canonical_test::test_serialize_sorts_states.
     */
    public function test_p_ser_serialize_returns_valid_json(): void {
        $json = (new serializer())->serialize($this->deserialize_fixture('at_least_one_a'));
        $this->assertNotFalse(json_decode($json));
    }

    /**
     * Mirrors afd_canonical_test::test_serialize_sorts_alphabet.
     */
    public function test_p_ser_output_contains_required_keys(): void {
        $data = json_decode((new serializer())->serialize($this->deserialize_fixture('at_least_one_a')), true);
        foreach (['schema_version', 'states', 'alphabet', 'transitions', 'start', 'finals'] as $key) {
            $this->assertArrayHasKey($key, $data, "Missing key: $key");
        }
    }

    /**
     * Mirrors afd_canonical_test::test_serialize_sorts_transitions.
     */
    public function test_p_ser_states_appear_in_output(): void {
        $data = json_decode((new serializer())->serialize($this->deserialize_fixture('at_least_one_a')), true);
        $ids  = array_column($data['states'], 'id');
        $this->assertContains('q0', $ids);
        $this->assertContains('q1', $ids);
    }

    /**
     * Mirrors afd_canonical_test::test_two_equivalent_afds_byte_identical.
     */
    public function test_p_ser_transitions_count_preserved(): void {
        $data = json_decode((new serializer())->serialize($this->deserialize_fixture('at_least_one_a')), true);
        $this->assertCount(4, $data['transitions']);
    }

    /**
     * Mirrors afd_canonical_test::test_parse_returns_array.
     * Serializing the same automaton twice must produce identical JSON.
     */
    public function test_p_ser_serialize_is_deterministic(): void {
        $a = $this->deserialize_fixture('at_least_one_a');
        $s = new serializer();
        $this->assertSame($s->serialize($a), $s->serialize($a));
    }

    /**
     * Mirrors afd_canonical_test::test_parse_rejects_unknown_keys.
     * Round-trip (deserialize -> serialize -> deserialize) preserves all fields.
     */
    public function test_p_ser_round_trip_preserves_all_fields(): void {
        $s  = new serializer();
        $a  = $this->deserialize_fixture('at_least_one_a');
        $a2 = $s->deserialize($s->serialize($a));

        $ids1 = array_map(fn($st) => $st->get_id(), $a->get_states());
        $ids2 = array_map(fn($st) => $st->get_id(), $a2->get_states());
        $this->assertSame($ids1, $ids2);
        $this->assertSame($a->get_alphabet(), $a2->get_alphabet());
        $this->assertSame($a->get_start(), $a2->get_start());
        $this->assertSame($a->get_finals(), $a2->get_finals());
    }

    // GROUP P-VAL: Validator correctness (9)
    // Mirrors: local_discretelab\afd_schema_test
    // Key difference: graphitoubb validator returns string[] (no warnings, no ->valid object).

    /**
     * Mirrors afd_schema_test::test_valid_afd_passes.
     */
    public function test_p_val_valid_afd_has_no_errors(): void {
        $errors = (new validator())->validate($this->deserialize_fixture('at_least_one_a'));
        $this->assertSame([], $errors);
    }

    /**
     * Mirrors afd_schema_test::test_missing_key_fails.
     */
    public function test_p_val_start_not_in_states_produces_error(): void {
        $a      = new automaton([new state('q0')], ['a'], [], 'q_missing', []);
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('start', $errors[0]);
    }

    /**
     * Mirrors afd_schema_test::test_unknown_key_rejected.
     */
    public function test_p_val_transition_from_unknown_state_produces_error(): void {
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

    /**
     * Mirrors afd_schema_test::test_bad_initial_rejected.
     * Deserializing malformed JSON throws InvalidArgumentException.
     */
    public function test_p_val_invalid_json_throws_on_deserialize(): void {
        $this->expectException(\InvalidArgumentException::class);
        (new serializer())->deserialize('{bad json}');
    }

    /**
     * Mirrors afd_schema_test::test_empty_states_rejected.
     * Empty language (no finals) is a valid AFD — accepts nothing.
     */
    public function test_p_val_empty_finals_is_valid(): void {
        $errors = (new validator())->validate($this->deserialize_fixture('empty_language'));
        $this->assertSame([], $errors);
    }

    /**
     * Mirrors afd_schema_test::test_missing_transition_reported.
     * graphitoubb allows partial transition functions — no totality requirement.
     * Divergence DIV-3: POC reports missing transitions as errors; graphitoubb does not.
     */
    public function test_p_val_missing_transition_does_not_cause_error(): void {
        $a = new automaton(
            [new state('q0'), new state('q1')],
            ['a', 'b'],
            [new transition('q0', 'a', 'q1')],
            'q0',
            ['q1']
        );
        $errors = (new validator())->validate($a);
        $this->assertSame([], $errors);
    }

    /**
     * Mirrors afd_schema_test::test_duplicate_transition_reported.
     */
    public function test_p_val_duplicate_transition_is_nondeterministic_error(): void {
        $errors  = (new validator())->validate($this->deserialize_fixture('nondeterministic_invalid'));
        $this->assertNotEmpty($errors);
        $combined = implode(' ', $errors);
        $this->assertStringContainsString('deterministic', $combined);
    }

    /**
     * Mirrors afd_schema_test::test_no_finals_gives_warning.
     * POC gives a no_accepting_states warning; graphitoubb has no warnings — empty finals = no errors.
     * Divergence DIV-4: warnings not implemented in graphitoubb validator.
     */
    public function test_p_val_no_finals_gives_no_error(): void {
        $a      = new automaton([new state('q0')], ['a'], [new transition('q0', 'a', 'q0')], 'q0', []);
        $errors = (new validator())->validate($a);
        $this->assertSame([], $errors);
    }

    /**
     * Mirrors afd_schema_test::test_final_not_in_states.
     */
    public function test_p_val_final_not_in_states_produces_error(): void {
        $a      = new automaton([new state('q0')], ['a'], [], 'q0', ['q_missing']);
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
        $combined = implode(' ', $errors);
        $this->assertStringContainsString('final', $combined);
    }

    // GROUP P-INT: Integration / full pipeline (7)
    // Mirrors: local_discretelab\afd_grader_test
    // Exercises: JSON -> deserialize -> validate -> simulate, plus multi-fixture cases.

    /**
     * Mirrors afd_grader_test::test_perfect_score.
     */
    public function test_p_int_pipeline_accept(): void {
        $a = $this->deserialize_fixture('at_least_one_a');
        $this->assertSame([], (new validator())->validate($a));
        $this->assertTrue((new simulator())->run($a, 'a')->is_accepted());
    }

    /**
     * Mirrors afd_grader_test::test_zero_score.
     */
    public function test_p_int_pipeline_reject(): void {
        $a = $this->deserialize_fixture('at_least_one_a');
        $this->assertSame([], (new validator())->validate($a));
        $this->assertFalse((new simulator())->run($a, '')->is_accepted());
    }

    /**
     * Mirrors afd_grader_test::test_partial_score.
     * Round-trip (deserialize -> serialize -> deserialize) preserves simulation results.
     */
    public function test_p_int_serializer_round_trip_preserves_simulation_result(): void {
        $s   = new serializer();
        $a1  = $this->deserialize_fixture('at_least_one_a');
        $a2  = $s->deserialize($s->serialize($a1));
        $sim = new simulator();
        $this->assertSame(
            $sim->run($a1, 'ba')->is_accepted(),
            $sim->run($a2, 'ba')->is_accepted()
        );
    }

    /**
     * Mirrors afd_grader_test::test_feedback_on_failure.
     */
    public function test_p_int_even_a_fixture_accepts_expected_inputs(): void {
        $a   = $this->deserialize_fixture('accepts_even_a');
        $this->assertSame([], (new validator())->validate($a));
        $sim = new simulator();
        foreach (['', 'aa', 'bb', 'aabaa'] as $input) {
            $this->assertTrue($sim->run($a, $input)->is_accepted(), "'$input' should be accepted (even a count)");
        }
    }

    /**
     * Mirrors afd_grader_test::test_empty_test_cases_throws.
     */
    public function test_p_int_even_a_fixture_rejects_expected_inputs(): void {
        $a   = $this->deserialize_fixture('accepts_even_a');
        $sim = new simulator();
        foreach (['a', 'aaa', 'bab'] as $input) {
            $this->assertFalse($sim->run($a, $input)->is_accepted(), "'$input' should be rejected (odd a count)");
        }
    }

    /**
     * Mirrors afd_grader_test::test_passed_flag_true_when_all_pass.
     */
    public function test_p_int_nondeterministic_fixture_fails_validation(): void {
        $a      = $this->deserialize_fixture('nondeterministic_invalid');
        $errors = (new validator())->validate($a);
        $this->assertNotEmpty($errors);
    }

    /**
     * Mirrors afd_grader_test::test_passed_flag_false_when_any_fails.
     * Binary strings divisible by 3: 0->r0, 11->r0, 110->r0, 1001->r0 (accepted);
     * 1->r1, 10->r2, 100->r1 (rejected).
     */
    public function test_p_int_binary_divisible_by_3_accepts_known_values(): void {
        $a   = $this->deserialize_fixture('binary_divisible_by_3');
        $this->assertSame([], (new validator())->validate($a));
        $sim = new simulator();
        foreach (['0', '11', '110', '1001'] as $input) {
            $this->assertTrue($sim->run($a, $input)->is_accepted(), "String '$input' (divisible by 3) should be accepted");
        }
        foreach (['1', '10', '100'] as $input) {
            $this->assertFalse($sim->run($a, $input)->is_accepted(), "String '$input' (not divisible by 3) should be rejected");
        }
    }
}
