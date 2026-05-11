# mod_graphitoubb

Moodle activity module that lets students build and simulate finite automata (DFA) as exercises.

## Overview

Students open the activity, an attempt is created automatically, and they work in a graph editor (Cytoscape.js) to construct a DFA. Teachers can view a report of all student attempts.

## Dependencies

- **local_graphitoubb** — AFD domain library (simulator, serializer, validator). Must be installed first.
- **qtype_graphitoubb** — question type integration (optional for v1).

## Installation order

1. Install `local/graphitoubb`
2. Install `mod/graphitoubb`
3. Install `qtype/graphitoubb` (optional)
4. Run `php admin/cli/upgrade.php` or visit the upgrade page.

## Capabilities

| Capability                   | Default roles          |
|------------------------------|------------------------|
| mod/graphitoubb:view         | student, teacher       |
| mod/graphitoubb:attempt      | student                |
| mod/graphitoubb:viewreport   | editingteacher, teacher|

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
