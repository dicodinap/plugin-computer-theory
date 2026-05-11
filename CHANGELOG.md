# Changelog

All notable changes to GraphitoUBB plugin family.

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
