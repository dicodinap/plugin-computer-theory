# qtype_graphitoubb — Question Type

Moodle question type that embeds the GraphitoUBB AFD editor inside a quiz question.

## Purpose

Allows instructors to author questions where students must build a finite automaton (DFA) as their answer. v1 is a minimal scaffold — the question type is installable and functional but grading and student-facing rendering are post-v1 features.

## Dependency

Requires `local_graphitoubb` to be installed first. The question type delegates tool rendering and validation to `local_graphitoubb\tools\afd\afd_tool`.

## Installation order

1. `local/graphitoubb`
2. `mod/graphitoubb` (optional for quiz-only use)
3. `question/type/graphitoubb`
4. Run `php admin/cli/upgrade.php` or visit the upgrade page.

## v1 scope

| Feature | Status |
|---|---|
| Plugin scaffolding (questiontype.php, edit form, DB) | ✓ v1 |
| Instructor can save automaton definition (JSON) | ✓ v1 |
| Student-facing AFD editor in quiz | post-v1 |
| Automatic grading | post-v1 |
| Behat scenarios | post-v1 |

## Privacy

`null_provider` — no per-student data is stored in question type tables. Instructor-owned question definitions only.

## Running PHPUnit

```bash
# From Moodle root
vendor/bin/phpunit --testsuite qtype_graphitoubb_testsuite
```
