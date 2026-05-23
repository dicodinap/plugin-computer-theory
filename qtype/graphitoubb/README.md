# qtype_graphitoubb — GraphitoUBB Truth Table Question Type

Moodle question type plugin that embeds the GraphitoUBB truth_table engine inside
the Moodle Quiz and Question Bank.

## Overview

Students interact with a truth table editor directly in a Moodle quiz attempt.
The plugin supports three exercise types:

| Type | What the student does |
|------|-----------------------|
| `complete` | Fill in all cells of a truth table for a given formula |
| `equivalence` | State whether two formulas are equivalent (+ optional table justification) |
| `classify` | Classify a formula as tautology, contradiction, or contingency (+ optional table) |

Grading is delegated to `local_graphitoubb\tools\truth_table\grader\grader` — this
plugin contains no custom grading logic.

## Requirements

| Dependency | Minimum version |
|-----------|----------------|
| Moodle    | 4.4 (build 2024100700) |
| `local_graphitoubb` | 2026051800 |

## Installation

1. Copy the plugin root directory to `<moodleroot>/question/type/graphitoubb/`.
2. Navigate to Site administration > Notifications and complete the upgrade.
3. Verify that `local_graphitoubb` version ≥ 2026051800 is installed first.

## Supported quiz behaviours

- `deferredfeedback` (primary, iter1)
- `immediatefeedback` (iter1)
- `adaptive`, `adaptivenopenalty` (SHOULD work, not tested in iter1)
- `interactive`, `manualgraded` — NOT supported in iter1

## Capabilities

This plugin reuses standard `mod/quiz:*` capabilities. No plugin-specific
capabilities are defined.

## Exercise types

### complete

Teacher provides one formula. Student fills in all value cells of the truth table.
Score = `cells_correct / cells_total`.

### equivalence

Teacher provides two formulas and the expected equivalence answer. Student selects
Yes/No and optionally fills in the justification table.
Score = `(radio_weight/100) × radio_score + (table_weight/100) × table_score`.
`wrong_radio_policy: strict` (default) → score 0 if radio is wrong.

### classify

Teacher provides one formula and the expected class. Student selects
tautology / contradiction / contingency and optionally fills in the justification table.
Same scoring formula as equivalence.

## Privacy

`qtype_graphitoubb_options` stores instructor-authored question definitions only.
No personal student data is stored in qtype tables. Student response data is
stored by the Moodle question engine in `question_attempt_step_data` (core_question).

## Tests

```
# PHPUnit
vendor/bin/phpunit --filter qtype_graphitoubb

# Behat (requires Behat environment)
php admin/tool/behat/cli/run.php --tags=@qtype_graphitoubb
```

## Version history

| Version     | Date       | Notes |
|-------------|------------|-------|
| 0.2.0-alpha | 2026-05-18 | Iter1 — truth_table qtype plugin |

## License

GNU GPL v3 or later — see https://www.gnu.org/copyleft/gpl.html
