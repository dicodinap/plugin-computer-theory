# Changelog

All notable changes to GraphitoUBB plugin family.

## [v0.2.0] (Iter 1 — truth_table) — 2026-05-18

### Added
- truth_table tool with three exercise types (`complete`, `equivalence`, `classify`)
- `qtype_graphitoubb` plugin for Moodle Quiz integration
- Teacher panel with 4 tabs (summary, per_student, heatmap, export)
- Autosave with debounce 500 ms, optimistic locking, conflict resolution
- Privacy API for all iter1 tables (`graphitoubb_submission`, `graphitoubb_event`, `graphitoubb_grade_cache`)
- Cron task `cleanup_orphans` (daily at 03:15) — prunes events >180 days and orphaned submission/grade/snapshot rows
- Web services: `save_draft`, `submit`, `log_event`, `get_problem_stats`, `get_panel_*`
- Behat steps for student attempts, teacher panel, autosave recovery
- `local/graphitoubb/lang/es/local_graphitoubb.php` — Spanish parity for local plugin

### Changed
- `graphitoubb_attempt` now supports drafts (`current_draft`, `draft_updated_at`)
- `graphitoubb` instance now supports multi-attempt policy (best / last / average)
- Privacy provider for `mod_graphitoubb` extended to cover all iter1 tables; `graphitoubb_problem` explicitly excluded (instructor data only)

### Migration
- Bump to version `2026051800`
- Backfill `problem_snapshot_hash` for legacy submissions
- Idempotent XMLDB upgrade; all `CREATE TABLE` and `ADD FIELD` guarded with `table_exists` / `field_exists` checks

## [0.2.0-alpha] — 2026-05-11

### Added — mod_graphitoubb (AFD editor UX)

- S2: Promoted `tool_interface` to v2 — added `validate(array): validation_result`, `serialize(array): array`, `render_editor(): array` contracts; `validation_result` value object with `::pass()` / `::fail(array)` factories
- S3: Toolbar Mustache template (`editor_toolbar.mustache`) + i18n strings (en/es) for all toolbar buttons
- S4.A: 7-state editor FSM in `editor_toolbar.js` — states: idle, adding_state, adding_transition_source, adding_transition_target, setting_start, toggling_final, deleting; emits `graphitoubb:modechange` CustomEvent
- S4.B: Cytoscape ↔ AFD canonical adapter in `local/graphitoubb/amd/src/afd_adapter.js` — `cyToAfd`, `afdToCy`, `cyToAfdSimulator`
- S4.C: Fixed `isSignificant` comparison bug in `snapshot_controller.js` (silent data-loss on JSON payload comparison)
- S5: State creation mode — click-to-add node, D-A bounds check (MAX_STATES=64), visual feedback
- S6: Transition creation mode — two-click source→target flow, deterministic check (no duplicate symbol on same source)
- S7: Start state selection mode — enforces single start state invariant
- S8: Toggle final state mode — click to mark/unmark accepting states
- S9: Delete mode — cascading edge removal when deleting a node
- S10: Alphabet management UI (`alphabet_ui.js`) — add/remove symbols, bounds enforcement (MAX_ALPHABET=16)
- S11: Simulator wiring (`afd_simulator.js`) — step/run trace animation on Cytoscape nodes, `.trace-visited` / `.trace-accept` / `.trace-reject` CSS classes, input bounds check (MAX_INPUT_LENGTH=256)
- S12: Wordbank wired to `log_word` WS endpoint with debounce and error handling
- hotfix: `data-max-*` attributes emitted on editor root (fc568d1); `.trace-accept` / `.trace-reject` background rules in `styles.css`
- hotfix: Transition symbol persisted on Cytoscape edge `data.symbol` (35ecb0f)
- S13: Save indicator badge (`save_indicator.js`) — subscribes to `graphitoubb:snapshot-status` CustomEvent; shows Saving… / Saved ✓ / Save failed ✕; auto-fades after 2s
- S14: Toast notifications via `core/notification` — replaced all `console.warn` with `Notification.addNotification`; surfaces simulator reject reason with stuck state + symbol; lang strings via `core/str`
- S15.A: AMD build pipeline (`tools/amd-build.sh`) — iterates mod + local AMD trees; terser minification for source files; copy-as-is for third-party libs >100KB; `cytoscape.js` committed to `amd/src` as canonical dev-mode source
- S15.B: Behat spike — `editor_opens.feature` authored; execution deferred to v0.3 (requires Moodle webserver container + Selenium)

### Known issues / deferred to v0.3
- Label-edit mode (S9 deferral): inline DOM `<input>` overlay for node labels — not implemented in v0.2
- Behat execution: `editor_opens.feature` written but not verified; requires moodle-docker or CI with Selenium
- Snapshot rate-limit precision remains 1-second (BIGINT migration still pending)

## [0.1.0-alpha] — 2026-05-10

### Added — local_graphitoubb (registry + AFD domain)
- Tool registry contract (tool_interface, tool_descriptor, tool_registry singleton, bootstrap callback)
- AfdTool implementation
- AFD domain: state, transition, automaton, validator (with bounds D-A: MAX_STATES=64...), simulator, trace, serializer (with schema_version)
- Privacy null_provider
- 121 phpunit tests including 29-method paridad battery vs POC discretelab

### Added — mod_graphitoubb (activity)
- Activity scaffold (Moodle 4.5)
- DB schema: 4 tables (graphitoubb, attempt, snapshot, wordbank_log) with R-5 single-attempt UNIQUE
- Privacy full_provider (mod owns student artifact data)
- Backend services: attempt_service, snapshot_service (D-B client snapshot authority + server rate-limit), wordbank_service
- WS endpoints: save_snapshot, log_word, finish_attempt — with capability + ownership guards (S14 audit hardening)
- AMD modules + Mustache templates: editor + simulator + wordbank panels
- Cytoscape.js 3.30.2 vendored (UMD, MIT)
- Renderer + view.php (student) + report.php (teacher) with capability gating
- Backup/restore moodle2 hooks
- Test data generator
- 75 phpunit tests + 1 integration test + 3 behat features

### Added — qtype_graphitoubb (placeholder)
- Minimal scaffold for AFD-as-question (full features post-v1)
- DB: qtype_graphitoubb_options
- Privacy null_provider
- 4 phpunit tests

### Architecture
- Horizontal architecture: tool_interface lives in local_graphitoubb; mod and qtype consume
- Greenfield (POC discretelab is paridad oracle only)
- Install order: local → mod → qtype

### Known issues
- Snapshot rate-limit precision is 1-second (timecreated INT). 200ms target documented as tech debt; needs BIGINT migration in v0.2.
- Tool_interface only has descriptor() — additional methods (validate, serialize, render_editor) implemented in AfdTool but not in interface contract. Promote in v0.2 if needed.
- Backup/restore lacks unit test coverage (Moodle backup framework hard to unit-test). Manual smoke test via course backup recommended.
- AMD build outputs are unminified copies (grunt unavailable in dev container). Production packaging requires real minification step.
