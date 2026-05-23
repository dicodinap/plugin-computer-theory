# Verification Status — Iter 1 truth_table

## Offline verification (this session)

| Check | Result |
|-------|--------|
| PHP 8.3 syntax (all `.php` in local/mod/qtype) | **PASS** — 0 errors |
| JSON schemas (6 files, draft-07) | **PASS** — 0 errors |
| XMLDB install.xml well-formed (xmllint) | **PASS** |
| File inventory match spec coverage | **PASS** |

## File inventory

| Category | Count |
|----------|-------|
| PHP files (local + mod + qtype) | 160 |
| PHPUnit test files | 47 |
| Behat feature files | 16 |
| Mustache templates | 16 |
| AMD JS modules | 15 |
| Lang files (en + es per plugin) | 7 |
| JSON Schema files | 6 |
| Total LOC added/modified | ~12,300 |

## Online verification required (Moodle Docker offline this session)

Bring up `moodle-docker` (`~/moodle-docker/`) with plugins symlinked into wwwroot:

```bash
cd ~/moodle/local && ln -sf ~/Documents/Universidad/uni/tesis/informes/plugin-computer-theory/local/graphitoubb .
cd ~/moodle/mod && ln -sf ~/Documents/Universidad/uni/tesis/informes/plugin-computer-theory/mod/graphitoubb .
cd ~/moodle/question/type && ln -sf ~/Documents/Universidad/uni/tesis/informes/plugin-computer-theory/qtype/graphitoubb .
```

Then start docker stack with env vars from `moodle-docker/README.md`. Run:

| Gate | Command | Target |
|------|---------|--------|
| PHPUnit init | `php admin/tool/phpunit/cli/init.php` | — |
| PHPUnit unit + integration | `vendor/bin/phpunit --testsuite local_graphitoubb_testsuite` (and mod, qtype) | All pass |
| Coverage | `vendor/bin/phpunit --coverage-clover` | ≥ 90% in `local/graphitoubb/tools/truth_table/`; = 100% in parser, grader, privacy provider; ≥ 60% in adapters |
| phpcs | `vendor/bin/phpcs --standard=moodle local/graphitoubb mod/graphitoubb qtype/graphitoubb` | 0 errors |
| phpstan | `vendor/bin/phpstan analyse --level 5` | 0 errors |
| Behat init | `php admin/tool/behat/cli/init.php` | — |
| Behat | `vendor/bin/behat --tags=@mod_graphitoubb && --tags=@qtype_graphitoubb` | 14 scenarios pass |
| axe-core | `vendor/bin/behat --tags=@a11y` (axe.min.js vendored per `tests/behat/fixtures/README.md`) | 0 critical / 0 serious |
| Bundle size | `grunt amd && ls -la amd/build/*.min.js.gz` | editor ≤ 60KB gzipped |
| p95 latencies | Apache benchmark or `wrk` against `save_draft.php`, `submit.php`, `panel.php` after seeding 50 users | submit < 500ms, autosave < 200ms, panel < 1500ms |

## Manual demo gate

Spec §29 DoD: end-to-end session with profesor guía + ≥ 3 estudiantes piloto, no bug bloqueante. Acta en `docs/iter1-validation-session.md`.

## Production-ready gate

Marked **production-ready** ONLY when:
1. All gates above PASS.
2. Demo session executed and acta committed.
3. Bugs no-bloqueantes triageados.

Until then: code complete, offline-verifiable green, runtime-untested.

## Known TODOs left intentionally

- `axe.min.js` not vendored — manual download per `mod/graphitoubb/tests/behat/fixtures/README.md`.
- Heatmap PDF export uses TCPDF fallback (if Moodle TCPDF unavailable, downloads as JSON).
- `top_errors` in `get_problem_stats.php` parses grading_result JSON in PHP — heavy for >500 submissions; iter2 optimization.
- Cron `cleanup_orphans`: 180-day retention default; configurable via admin setting deferred to iter2.
- Reset-attempts event class deferred to iter2 (currently logged via `debugging()`).
- F-LOGIC-06 (simplification w/ De Morgan) intentionally out of scope per spec §2.2.
