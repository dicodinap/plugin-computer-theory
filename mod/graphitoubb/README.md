# mod_graphitoubb

Moodle activity module for interactive computer-theory exercises: finite automata (DFA) and propositional logic truth tables.

## Overview

Students open the activity, an attempt is created automatically, and they work in the tool editor. For DFA mode, they use a graph editor (Cytoscape.js). For truth-table mode, they fill in or classify a truth table. Teachers can view a report of all student attempts, per-student breakdowns, heatmaps, and CSV exports.

## Supported exercise types (iter 1)

| Tool        | Mode          | Student action                                     |
|-------------|---------------|----------------------------------------------------|
| `afd`       | —             | Build a DFA and simulate words                     |
| `truth_table` | `complete`  | Fill in missing cells in the truth table           |
| `truth_table` | `equivalence` | Decide whether two formulas are equivalent       |
| `truth_table` | `classify`  | Classify a formula as tautology/contradiction/contingency |

## Dependencies

- **local_graphitoubb** — tool registry, AFD domain, and truth_table domain. Must be installed first.
- **qtype_graphitoubb** — question type integration (optional).

## Installation order

1. Install `local/graphitoubb`
2. Install `mod/graphitoubb`
3. Install `qtype/graphitoubb` (optional)
4. Run `php admin/cli/upgrade.php` or visit the upgrade page.

## Capabilities

| Capability                     | Default roles            |
|--------------------------------|--------------------------|
| mod/graphitoubb:view           | student, teacher         |
| mod/graphitoubb:attempt        | student                  |
| mod/graphitoubb:viewreport     | editingteacher, teacher  |
| mod/graphitoubb:viewreports    | editingteacher, teacher  |
| mod/graphitoubb:submit         | student                  |
| mod/graphitoubb:gradeother     | editingteacher           |
| mod/graphitoubb:manage         | editingteacher           |
| mod/graphitoubb:reattempt      | editingteacher           |

## Multi-attempt policy

The `graphitoubb` instance supports three grading policies configured at activity creation:

- `best` — highest score across all attempts (default)
- `last` — score from the most recent submission
- `average` — mean score across all submissions

## Autosave

Drafts are saved with a 500 ms debounce via the `save_draft` web service. Optimistic locking detects concurrent edits: if a conflict is detected, the student sees a conflict resolver UI.

## Telemetry

Student interactions are logged to `graphitoubb_event`. Events older than 180 days are pruned by the `cleanup_orphans` scheduled task (daily at 03:15).

## Privacy

This plugin is the data owner for all student artifact tables:
- `graphitoubb_attempt` — per-user attempt records (including draft)
- `graphitoubb_snapshot` — periodic state snapshots
- `graphitoubb_wordbank_log` — words simulated against DFA
- `graphitoubb_submission` — final graded submissions
- `graphitoubb_event` — telemetry events
- `graphitoubb_grade_cache` — cached aggregate grades

`graphitoubb_problem` contains instructor-defined content only (no personal data).

## Running Behat tests

Requires a Moodle Behat environment with Selenium or a compatible browser driver.

```bash
# From Moodle root — initialise once
php admin/tool/behat/cli/init.php

# Run all mod_graphitoubb scenarios
vendor/bin/behat --config /path/to/behatconfig.yml \
  --tags @mod_graphitoubb
```

Features are located in `mod/graphitoubb/tests/behat/`:

| File                        | Coverage                                    |
|-----------------------------|---------------------------------------------|
| `student_attempt.feature`   | Student opens activity and sees the editor  |
| `teacher_report.feature`    | Teacher views the attempts report           |
| `capability_gating.feature` | Users without attempt capability are denied |

## Running PHPUnit tests

```bash
# From Moodle root
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite mod_graphitoubb_testsuite
```
