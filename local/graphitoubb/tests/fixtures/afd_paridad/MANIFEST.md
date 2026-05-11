# AFD Paridad Fixtures

Frozen fixtures for `paridad_test.php`. Format: graphitoubb canonical (schema_version=1).

| File | Language | States | Accepts | Rejects |
|---|---|---|---|---|
| `at_least_one_a.json` | Σ={a,b}, at least one 'a' | q0(start), q1(final) | a, ba, aab | ε, b, bb |
| `accepts_even_a.json` | Σ={a,b}, even count of 'a' | q0(start+final), q1 | ε, aa, bb, aabaa | a, aaa, bab |
| `accepts_ab_suffix.json` | Σ={a,b}, ends in "ab" | q0(start), q1, q2(final) | ab, aab, bab | ε, a, b, ba |
| `empty_language.json` | Σ={a}, no finals → L=∅ | q0(start) | (none) | all strings |
| `accepts_empty_string.json` | Σ={a}, q0 is start+final | q0(start+final), q1(final) | ε, a, aa | (none — all accepted) |
| `binary_divisible_by_3.json` | Σ={0,1}, binary div-by-3 | r0(start+final), r1, r2 | 0, 11, 110, 1001 | 1, 10, 100 |
| `at_least_one_b.json` | Σ={a,b}, at least one 'b' | q0(start), q1(final) | b, ab, aab | ε, a, aa |
| `accepts_only_a_plus.json` | Σ={a}, one or more 'a' | q0(start), q1(final) | a, aa, aaa | ε (q0 not final) |
| `nondeterministic_invalid.json` | INVALID: q0-a→q1 and q0-a→q2 | q0, q1, q2 | — | fails validator |
| `final_not_in_states.json` | INVALID: finals=[q_missing] not in states | q0 | — | fails validator |

## Notes
- All valid fixtures pass `validator->validate()` with no errors.
- Invalid fixtures (`nondeterministic_invalid`, `final_not_in_states`) are used exclusively in validator/integration tests that assert errors.
- `accepts_empty_string.json`: q0 is final, so ε is accepted; q1 is also final so all strings of 'a*' are accepted.
