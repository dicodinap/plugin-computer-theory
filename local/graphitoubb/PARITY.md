# AFD Paridad with POC discretelab

## Baseline

| Item | Value |
|---|---|
| POC | `local/discretelab` (oracle) |
| POC test count | 29 phpunit assertions across 4 test classes |
| Mirror | `local/graphitoubb/tests/paridad_test.php` |
| Mirror test count | 29 methods (groups P-SIM, P-SER, P-VAL, P-INT) |

## Coverage matrix

| AC | POC class / test | graphitoubb mirror | Notes |
|---|---|---|---|
| AC-SIM-1 accepts valid input | `afd_simulator_test::test_accepts_single_a` | `paridad_test::test_p_sim_accepts_single_a` | |
| AC-SIM-2 rejects empty string | `afd_simulator_test::test_rejects_empty_string` | `paridad_test::test_p_sim_rejects_empty_string` | get_final_state()=null (no steps) |
| AC-SIM-3 rejects only-b | `afd_simulator_test::test_rejects_only_b` | `paridad_test::test_p_sim_rejects_only_b` | |
| AC-SIM-4 accepts ba | `afd_simulator_test::test_accepts_ba` | `paridad_test::test_p_sim_accepts_ba` | |
| AC-SIM-5 trace length | `afd_simulator_test::test_trace_length` | `paridad_test::test_p_sim_trace_length_equals_input_length` | |
| AC-SIM-6 unknown symbol → reject | `afd_simulator_test::test_trap_state_on_unknown_symbol` | `paridad_test::test_p_sim_unknown_symbol_causes_rejection` | DIV-1 |
| AC-SIM-7 stays in trap | `afd_simulator_test::test_stays_in_trap` | `paridad_test::test_p_sim_stuck_causes_rejection_even_with_more_input` | DIV-1 |
| AC-SER-1 valid JSON output | `afd_canonical_test::test_serialize_sorts_states` | `paridad_test::test_p_ser_serialize_returns_valid_json` | |
| AC-SER-2 required keys present | `afd_canonical_test::test_serialize_sorts_alphabet` | `paridad_test::test_p_ser_output_contains_required_keys` | |
| AC-SER-3 states in output | `afd_canonical_test::test_serialize_sorts_transitions` | `paridad_test::test_p_ser_states_appear_in_output` | |
| AC-SER-4 transitions count | `afd_canonical_test::test_two_equivalent_afds_byte_identical` | `paridad_test::test_p_ser_transitions_count_preserved` | |
| AC-SER-5 deterministic output | `afd_canonical_test::test_parse_returns_array` | `paridad_test::test_p_ser_serialize_is_deterministic` | |
| AC-SER-6 round-trip fidelity | `afd_canonical_test::test_parse_rejects_unknown_keys` | `paridad_test::test_p_ser_round_trip_preserves_all_fields` | DIV-2 |
| AC-VAL-1 valid AFD → no errors | `afd_schema_test::test_valid_afd_passes` | `paridad_test::test_p_val_valid_afd_has_no_errors` | |
| AC-VAL-2 bad start → error | `afd_schema_test::test_missing_key_fails` | `paridad_test::test_p_val_start_not_in_states_produces_error` | |
| AC-VAL-3 bad transition → error | `afd_schema_test::test_unknown_key_rejected` | `paridad_test::test_p_val_transition_from_unknown_state_produces_error` | |
| AC-VAL-4 bad JSON → exception | `afd_schema_test::test_bad_initial_rejected` | `paridad_test::test_p_val_invalid_json_throws_on_deserialize` | |
| AC-VAL-5 empty finals → valid | `afd_schema_test::test_empty_states_rejected` | `paridad_test::test_p_val_empty_finals_is_valid` | |
| AC-VAL-6 missing transition → valid | `afd_schema_test::test_missing_transition_reported` | `paridad_test::test_p_val_missing_transition_does_not_cause_error` | DIV-3 |
| AC-VAL-7 duplicate → deterministic error | `afd_schema_test::test_duplicate_transition_reported` | `paridad_test::test_p_val_duplicate_transition_is_nondeterministic_error` | |
| AC-VAL-8 no finals → no error | `afd_schema_test::test_no_finals_gives_warning` | `paridad_test::test_p_val_no_finals_gives_no_error` | DIV-4 |
| AC-VAL-9 final not in states → error | `afd_schema_test::test_final_not_in_states` | `paridad_test::test_p_val_final_not_in_states_produces_error` | |
| AC-INT-1 pipeline accept | `afd_grader_test::test_perfect_score` | `paridad_test::test_p_int_pipeline_accept` | |
| AC-INT-2 pipeline reject | `afd_grader_test::test_zero_score` | `paridad_test::test_p_int_pipeline_reject` | |
| AC-INT-3 round-trip preserves simulation | `afd_grader_test::test_partial_score` | `paridad_test::test_p_int_serializer_round_trip_preserves_simulation_result` | |
| AC-INT-4 even-a accepts | `afd_grader_test::test_feedback_on_failure` | `paridad_test::test_p_int_even_a_fixture_accepts_expected_inputs` | |
| AC-INT-5 even-a rejects | `afd_grader_test::test_empty_test_cases_throws` | `paridad_test::test_p_int_even_a_fixture_rejects_expected_inputs` | |
| AC-INT-6 nondeterministic fails | `afd_grader_test::test_passed_flag_true_when_all_pass` | `paridad_test::test_p_int_nondeterministic_fixture_fails_validation` | |
| AC-INT-7 binary div-by-3 | `afd_grader_test::test_passed_flag_false_when_any_fails` | `paridad_test::test_p_int_binary_divisible_by_3_accepts_known_values` | |

## Fixtures

See `tests/fixtures/afd_paridad/MANIFEST.md`.

## Divergences

| ID | Description |
|---|---|
| DIV-1 | **No TRAP_STATE**: graphitoubb simulator stops when stuck (no transition or unknown symbol) instead of entering an explicit trap state. Behavior is equivalent: stuck input is always rejected. |
| DIV-2 | **Round-trip vs byte-identical**: POC `test_two_equivalent_afds_byte_identical` proves canonical sort. graphitoubb serializer test proves round-trip fidelity and determinism; sort behavior untested here. |
| DIV-3 | **Partial transition function allowed**: POC validator requires a total transition function (flags missing pairs). graphitoubb validator does not — partial AFDs are valid. |
| DIV-4 | **No warnings**: POC returns a `no_accepting_states` warning for empty finals. graphitoubb `validator::validate()` returns `string[]` errors only — no warning concept. Empty finals → no errors. |
