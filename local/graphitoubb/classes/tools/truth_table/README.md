# truth_table tool

Domain library for propositional logic truth-table exercises. Registered under slug `truth_table` in the `local_graphitoubb` tool registry.

---

## Exercise types

| Mode | Student action | Required problem fields |
|---|---|---|
| `complete` | Fill in blank cells in the truth table | `formula` |
| `equivalence` | Choose yes/no equivalence; optionally justify with table | `formula_1`, `formula_2`, `expected_equivalent`, `require_table_justification`, weights |
| `classify` | Choose tautology / contradiction / contingency; optionally justify | `formula`, `expected_class`, `require_table_justification`, weights |

---

## JSON schemas (v1)

Schemas live in `schema/` and are loaded via `schema_loader`. All payloads carry `"schema_version": 1`.

### Problem — complete mode

```json
{
  "schema_version": 1,
  "tool": "truth_table",
  "type": "complete",
  "formula": "A ∧ B → C",
  "variables": ["A", "B", "C"],
  "expected_table": [
    {"row": [true, true, true],  "result": true},
    {"row": [true, true, false], "result": false}
  ]
}
```

`expected_table` is auto-computed from `formula` at problem creation time. Teachers can override individual rows.

### Problem — equivalence mode

```json
{
  "schema_version": 1,
  "tool": "truth_table",
  "type": "equivalence",
  "formula_1": "A → B",
  "formula_2": "¬A ∨ B",
  "expected_equivalent": true,
  "require_table_justification": false,
  "weights": {
    "radio": 0.6,
    "table": 0.4
  }
}
```

`expected_equivalent` is auto-computed (column-wise comparison). When `require_table_justification` is `true`, the student must also fill a truth table; the `table` weight applies.

### Problem — classify mode

```json
{
  "schema_version": 1,
  "tool": "truth_table",
  "type": "classify",
  "formula": "A ∨ ¬A",
  "expected_class": "tautology",
  "require_table_justification": false,
  "weights": {
    "radio": 1.0,
    "table": 0.0
  }
}
```

`expected_class` is one of: `"tautology"`, `"contradiction"`, `"contingency"`. Auto-computed from the truth table.

### Submission — complete mode

```json
{
  "schema_version": 1,
  "tool": "truth_table",
  "type": "complete",
  "cells": [
    {"row": 0, "col": 3, "value": true},
    {"row": 1, "col": 3, "value": false}
  ]
}
```

`col` indexes into the column headers (variables + sub-expressions + result).

### Submission — equivalence mode

```json
{
  "schema_version": 1,
  "tool": "truth_table",
  "type": "equivalence",
  "answer": true,
  "justification_table": {
    "formula_1_cells": [true, true, false, false],
    "formula_2_cells": [true, true, false, false]
  }
}
```

`justification_table` is omitted when `require_table_justification` is false.

### Submission — classify mode

```json
{
  "schema_version": 1,
  "tool": "truth_table",
  "type": "classify",
  "answer": "tautology",
  "justification_table": {
    "cells": [true, true, true, true]
  }
}
```

---

## Grading result

All graders return a `grading_result` value object serialized as:

```json
{
  "score": 0.75,
  "fraction": 0.75,
  "passed": true,
  "items": [
    {
      "type": "cell",
      "row": 0,
      "col": 3,
      "status": "correct",
      "student_value": true,
      "expected_value": true
    },
    {
      "type": "cell",
      "row": 1,
      "col": 3,
      "status": "incorrect",
      "student_value": true,
      "expected_value": false
    }
  ]
}
```

`status` values:
- `correct` — matches expected
- `incorrect` — differs from expected
- `propagated` — this cell is wrong because an upstream cell was wrong (only `complete` mode)
- `empty` — student left the cell blank

---

## Operator precedence

| Symbol | Operator | Precedence | Associativity |
|---|---|---|---|
| `¬` / `~` / `!` | Negation | 1 (highest) | right |
| `∧` / `&` / `/\` | Conjunction | 2 | left |
| `∨` / `⊕` / `\|` / `\/` | Disjunction / XOR | 3 | left |
| `→` / `->` | Implication | 4 | right |
| `↔` / `<->` | Biconditional | 5 | right |

Constants: `⊤` (true), `⊥` (false). Variables: uppercase `A`–`Z`.

ASCII aliases are normalized to Unicode by the lexer before parsing.

---

## Schema versioning

`schema_version` in both problem and submission payloads is a forward-compatibility marker.

Rules:
- Increment `schema_version` in `truth_table_tool::get_version()` when the payload shape changes in a breaking way (field renamed, removed, or semantics changed).
- Additive-only changes (new optional field with default) do NOT require a version bump.
- The grader checks `schema_version` and rejects submissions with a version higher than the current tool version.
- Backfill: if `schema_version` is absent in stored data, treat as `1`.

Current version: `1`.

---

## Bounds enforced by the validator

| Constraint | Limit |
|---|---|
| Max variables per formula | 8 |
| Max formula length (chars) | 512 |
| Max truth-table rows | 256 (= 2^8) |

Submissions exceeding these bounds are rejected before grading.

---

## Gotchas

- **Canonicalization adds parens**: `A ∧ B ∨ C` becomes `(A ∧ B) ∨ C` in column headers. The student sees the canonical form, not the raw teacher input.
- **`expected_table` is immutable after first save**: Changing `formula` on an existing problem does NOT automatically recompute `expected_table`. Teachers must delete and recreate the problem. This is intentional — submissions already graded against the old table would be invalidated.
- **XOR is level 3 alongside `∨`**: `A ⊕ B ∨ C` parses as `(A ⊕ B) ∨ C`. This matches standard logic textbooks but may surprise students expecting XOR to bind tighter.
- **Implication is right-associative**: `A → B → C` parses as `A → (B → C)`.
- **Propagated errors in `complete` mode**: If row 0 has a wrong intermediate column, all downstream columns in that row are marked `propagated`, not `incorrect`. This affects score: only the first wrong cell in a dependency chain subtracts from the score.
- **`problem_snapshot_hash` links submission to problem version**: Stored at grading time. If the teacher changes the problem after submissions exist, the hash mismatch is detectable but not automatically handled — the teacher panel flags these submissions.
- **Pre-attempt events**: `graphitoubb_event.attemptid` is nullable. Events logged before an attempt is created (e.g., page-load telemetry) have `attemptid = null`. Privacy deletion must cover both cases — see `mod_graphitoubb\privacy\provider`.
