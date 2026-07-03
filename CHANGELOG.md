# Changelog

All notable changes to GraphitoUBB plugin family.

## [Unreleased] — Pre-installed exercise catalogue + Question Bank seed + qtype grid — 2026-06-29

Banco de ejercicios pre-instalados para AFD y tablas de verdad. Verified in browser
(teacher picker + quiz preview, all three exercise types graded 1.00/1.00) and via CLI.

### Added
- **Preset catalogue (shared `local_graphitoubb`)**: curated, ready-to-use exercises
  shipped as data — `catalog/afd.json` (22 language exercises) and
  `catalog/truth_table.json` (28: complete / equivalence / classify), each with
  bilingual title/summary and a canonical problem payload. Loaded via
  `catalog\preset_catalog` (+ `catalog\preset`). A CLI sanity check
  (`cli/verify_catalog.php`) parses every formula with the real domain layer, asserts
  `expected_equivalent`/`expected_class` match the computed truth values, and that AFD
  test sets are two-sided — "Checked 50 presets. ALL OK".
- **Activity-Problem template picker (`mod_graphitoubb/edit_problem.php`)**: a
  collapsible catalogue browser preloads the problem form from a preset
  (`?preset=KEY`). The list is **tool-dependent** — it shows only the AFD or only the
  truth-table group following the selected tool (live, via the existing toggle).
- **Question Bank seeding (`qtype_graphitoubb`)**: the 28 truth-table presets ship as a
  Moodle XML import file (`db/preset_questions.xml`, regenerable via
  `local/.../cli/export_questions_xml.php`) and are auto-seeded into a system-context
  question category on install/upgrade by `classes/catalog_seeder.php` (idempotent +
  defensive). Dev helper: `cli/reseed_presets.php`.
- **Interactive truth-table grid in the qtype**: `renderer.php` now renders the
  fill-in grid server-side from the new shared `truth_table\domain\grid_skeleton`
  (column/row layout that matches the graders, incl. the equivalence combined table
  `… final₁ … final₂ equiv?`), plus the verdict radio. A small inline serializer writes
  the answer payload to the hidden quiz field — no AMD build needed. Verified end-to-end:
  complete, equivalence and classify questions all graded 1.00/1.00.

### Fixed
- **qtype question load TypeError**: `extra_question_fields()` no longer lists the
  JSON columns (the engine was assigning their raw strings to array-typed properties);
  `get_question_options()` now loads them explicitly for decoding.
- **qtype XML import lost the payload**: `import_from_xml()` now hydrates the form-style
  fields from the decoded payload so `save_question_options()` rebuilds the correct
  problem instead of empty formulas.
- **Missing qtype icon (404)**: added `qtype/graphitoubb/pix/icon.svg` (a truth-table
  glyph) — the question type previously 404'd on its icon on every render.

### Verified (Playwright, production paths)
- Real student attempt of a real quiz with two seeded questions (complete + classify
  with radio): filled the interactive grid → **10.00/10.00 (100%)**, both Correct,
  review page hydrated the submitted cells/radio (disabled).
- Grading edge cases on seeded questions: full correct `1.00`, partial table `0.25`,
  radio-only with empty table `0.50` (Partially correct) — no exceptions.
- All three exercise types render + grade in the question preview; seeded category is
  visible/selectable in the quiz question-bank picker.
- Teacher: loading an AFD preset and saving it surfaces the consigna + visible examples
  in the student activity view.

### Migration
- Bump `local_graphitoubb` to `2026062901` (release `0.4.0-alpha`)
- Bump `mod_graphitoubb` to `2026062915` (release `0.6.0-alpha`)
- Bump `qtype_graphitoubb` to `2026062906` (release `0.4.1-alpha`)

## [Unreleased] — Keyboard-operable AFD editor (A14/E1) — 2026-06-29

`docs/UX_UI_IMPROVEMENTS.md` A14 / E1. Verified in browser.

### Added
- **Keyboard form alternative (A14/E1)**: a collapsible "Keyboard controls" panel
  lets a keyboard/screen-reader user build the whole automaton without the pointer
  canvas — add states, set start/final, delete, and add transitions by choosing
  source/target/symbol from standard form controls. The selects auto-populate from
  the graph and stay in sync; locked once an attempt is submitted. The pointer flow
  is refactored to share the same `addState` / `createTransition` logic.
  Verified: built q0(start) → q1(final) with transition `a` and alphabet entirely
  via the form, no canvas clicks.

### Migration
- Bump `mod_graphitoubb` to `2026062913` (release `0.5.1-alpha`)

## [Unreleased] — Truth-table preview & canonical qtype formula — 2026-06-29

`docs/UX_UI_IMPROVEMENTS.md` C4 + C6. Verified in browser / via CLI.

### Added
- **Truth-table preview (C4)**: the teacher authoring form now renders the actual
  truth table a formula produces (built server-side via the domain builder) so the
  teacher sees exactly what the student will get. Verified: `A & B` → A/B/final
  with rows FFF/FVF/VFV/VVV.

### Changed
- **Canonical formula in the quiz (C6)**: `qtype_graphitoubb` now renders the
  formula in the same canonical form as the activity editor (explicit parentheses,
  Unicode operators) instead of the raw stored string, falling back to raw on a
  parse error. Verified: `A <-> B & C` → `(A ↔ (B ∧ C))`.

### Migration
- Bump `qtype_graphitoubb` to `2026062903` (release `0.2.2-alpha`)

## [Unreleased] — Undo / redo (A4) — 2026-06-29

`docs/UX_UI_IMPROVEMENTS.md` A4. Verified end-to-end in browser.

### Added
- **Undo / redo (A4)**: the AFD editor now keeps a 50-deep history of graph and
  alphabet mutations. Undo/Redo toolbar buttons (auto enabled/disabled) plus
  `Ctrl+Z` / `Ctrl+Y` / `Ctrl+Shift+Z`. One user action = one history step
  (debounced), and undo restores states, transitions, labels, positions **and**
  the alphabet (via a new `alphabet_ui.refresh()`); disabled once an attempt is
  submitted. Verified: add nodes + symbol → undo×3 peels symbol, then each node;
  redo replays; keyboard shortcuts work.

### Migration
- Bump `mod_graphitoubb` to `2026062912` (release `0.5.0-alpha`)

## [Unreleased] — Trace replay & responsive — 2026-06-29

`docs/UX_UI_IMPROVEMENTS.md` A10 + F1–F3. Verified in browser (desktop + 375px).

### Added
- **Step-by-step trace playback (A10)**: running a word now shows playback controls
  (first / prev / play-pause / next / last + "Step i of n"). The student can pause,
  step manually and replay; the current state and the transition used are both
  highlighted (the edge in amber), not just the node. Auto-plays once on Run.

### Changed
- **Responsive layout (F1–F3)**: the AFD canvas height now adapts on small screens
  (420px → 340/280px), the toolbar wraps, the submission bar stacks the finish
  button full-width, and wide panel tables/heatmap scroll horizontally instead of
  breaking the page. Verified at 375px with no horizontal page scroll.
- **Correct panel loading label (D5)**: the teacher-panel spinners said "Loading
  editor…" (wrong context) — they now use a dedicated "Loading…" string.

### Migration
- Bump `mod_graphitoubb` to `2026062910` (release `0.4.5-alpha`)

## [Unreleased] — AFD transitions modal, rename, test-set guard — 2026-06-29

`docs/UX_UI_IMPROVEMENTS.md` A3 (completes G1), A8 + a grading-robustness guard.
Verified in browser.

### Changed
- **Transition symbol via Moodle modal (A3 / G1)**: the last `window.prompt` in the
  AFD editor is gone — the transition symbol is now entered in a Moodle modal with
  a text input (focus-managed, localised). No native dialogs remain in the editor.

### Added
- **Rename states (A8)**: double-click a state to rename its label (e.g. "even",
  "odd") via the shared input modal, respecting `MAX_LABEL_LENGTH`. Documented in
  the help panel.
- **Weak-test-set warning (grading robustness)**: when authoring an AFD exercise,
  saving a small (<4) or one-sided (all-accept / all-reject) test set shows a
  non-blocking warning, since a wrong automaton can otherwise pass — grading is
  only as strong as the hidden words.

### Migration
- Bump `mod_graphitoubb` to `2026062908` (release `0.4.3-alpha`)

## [Unreleased] — AFD editor onboarding & affordances — 2026-06-29

`docs/UX_UI_IMPROVEMENTS.md` A13, A7, H3 + the consigna-examples fix for A1.
Verified in browser.

### Added
- **Onboarding help (A13)**: a collapsible "How does this editor work?" panel
  (5 steps) above the toolbar, plus a tooltip on every toolbar button describing
  what it does.
- **Tidy layout (A7)**: a toolbar button that re-runs the force-directed layout
  on demand to spread overlapping states (verified: min node distance 1px → 126px).
- **Toolbar icons (H3)**: FontAwesome icons on each toolbar action for faster
  scanning.
- **Visible task examples (A1 follow-up)**: teachers can mark AFD test words with
  a leading `*` to show them to students as accept/reject examples in the
  consigna, plus a "you need 60% to pass" line — closing the "is the task clear?"
  gap without revealing the hidden grading set.

### Migration
- Bump `mod_graphitoubb` to `2026062907` (release `0.4.2-alpha`)

## [Unreleased] — AFD authoring & grading (C1) — 2026-06-29

The strategic investment from `docs/UX_UI_IMPROVEMENTS.md` C1 + A1: AFD exercises
are now authorable and auto-graded. Verified end-to-end in browser + a unit test.

### Added
- **AFD problem authoring**: `edit_problem.php` gains a Tool selector
  (Truth table / AFD). For AFD the teacher defines a prompt (consigna), an
  alphabet, and a list of expected-verdict test words (`accept:aa`, `reject:b`,
  `accept:` for ε). Prefix a line with `*` to also show it to students as a
  worked example (`*accept:aa`) without revealing the whole hidden test set.
- **Server-side grading**: new `local_graphitoubb\tools\afd\grader\afd_grader`
  runs every test word through the student's latest automaton (via the existing
  PHP `simulator`) and scores the fraction classified correctly; an automaton
  with no start state is reported as ungradeable rather than silently 0. Wired
  into `finish_attempt` — submitting an AFD attempt grades it, persists a
  `graphitoubb_submission`, and returns the score. Unit test
  `afd_grader_test.php` covers full/partial/ε/invalid cases.
- **Task prompt for students (A1)**: AFD activities now render a "Your task"
  consigna above the editor — the prompt, the alphabet, the teacher's chosen
  **accept/reject example words**, and a line stating the pass threshold — so the
  student no longer lands on a blank canvas guessing the exercise.
- **Grade result panel**: after finishing, the student sees their score
  (e.g. "Score: 6/6 test words correct (100%) — Passed"), both as a toast and a
  persistent panel on reload.

### Migration
- Bump `mod_graphitoubb` to `2026062906` (release `0.4.1-alpha`)
- Bump `local_graphitoubb` to `2026062900` (release `0.3.1-alpha`)

## [Unreleased] — Teacher authoring form — 2026-06-29

`docs/UX_UI_IMPROVEMENTS.md` C3 + C5 on `edit_problem.php`. Verified in browser.

### Fixed
- **No more auto-submit data loss (C3)**: changing the exercise type no longer
  reloads the page via `onchange="this.form.submit()"` (which discarded typed
  formulas). Type-specific fields now show/hide client-side; switching types and
  back preserves what was typed.

### Added
- **Formula syntax help (C5)**: a collapsible operator legend (symbol, ASCII
  synonym, example) on the authoring form.

## [Unreleased] — AFD submission & error tolerance — 2026-06-29

Adds the missing "hand-in" loop and removes the accidental-data-loss risk in the
AFD editor (`docs/UX_UI_IMPROVEMENTS.md` A2, A12, G1). Verified end-to-end in browser.

### Added
- **AFD "Mark as finished" / submission bar (A2)**: a status badge (In progress /
  Finished) plus a primary "Mark as finished" button that confirms via a Moodle
  modal, calls `mod_graphitoubb_finish_attempt`, locks the editor read-only and
  shows a success toast. `render_editor` now receives the attempt status and a
  finished attempt re-opens locked. New `repository.finishAttempt`.
- **Destructive-action confirmations (A12 / G1)**: deleting a state that still has
  connected transitions now asks for confirmation (Moodle modal, naming the count)
  before removing them; a new **Reset automaton** toolbar button clears all states,
  transitions and alphabet symbols behind a confirmation. No native `confirm`.

### Migration
- Bump `mod_graphitoubb` to `2026062904` (release `0.3.4-alpha`)

## [Unreleased] — i18n sweep (§7.1) — 2026-06-29

Eliminates the hardcoded-string debt from `docs/UX_UI_IMPROVEMENTS.md` §7.1 so the
bilingual (en/es) promise holds across both tools. Verified end-to-end in browser
with the Spanish language pack installed (server `{{#str}}`/`get_string` **and**
JS `core/str`, including `{$a}` parameter substitution).

### Changed — moved hardcoded strings to `get_string` / `core/str`
- **truth_table editor**: operator palette `aria-label`s + new tooltips (B7), the
  `Logical operators` toolbar label, and each cell's `Row N, column M` aria-label
  (computed server-side via `cell_aria`).
- **Radio section (B5)**: equivalence/classify legends and option labels
  (`Yes/No`, `Tautology/Contradiction/Contingency`) now localised in `renderer.php`.
- **feedback_cell (B5)**: `Row N`, `submitted`, `expected` localised.
- **conflict_modal (B2)**: `Server version` / `Your version` localised.
- **formula_parser (B4)**: the four parse errors are thrown as structured
  `{strKey, strParam}` errors and rendered via `core/str` with `role="alert"` +
  `text-danger` in the canonical preview (was hardcoded Spanish + colour-only).
- **save_indicator max-length error**: uses `core/str` instead of `M.util.get_string`.
- **panel_dashboard**: residual Spanish (`Fila`, `sin datos`, the
  coming-soon student list) moved to localised `STR` labels.
- **qtype_graphitoubb**: `summarise_response` (`Equivalence/Classification/Truth
  table…`, `(no answer)`, row counts) localised; uses core `yes`/`no`.

### Migration
- Bump `mod_graphitoubb` to `2026062902` (release `0.3.2-alpha`)
- Bump `qtype_graphitoubb` to `2026062902` (release `0.2.1-alpha`)

## [Unreleased] — UX/UI quick wins — 2026-06-29

Implements the quick-wins wave from `docs/UX_UI_IMPROVEMENTS.md` (all verified in
browser with Playwright across student/teacher roles).

### Added
- **AFD editor — contextual mode hint (A5)**: a live-region hint under the toolbar
  tells the student what the active tool does ("Click on the canvas to place a new
  state.", etc.); toolbar buttons now expose `aria-pressed`.
- **AFD editor — zoom controls (A6)**: floating `+ / − / fit / 100%` cluster over the
  canvas, respecting the existing `maxZoom` clamp. Makes the previously hidden
  wheel-zoom/pan discoverable.
- **AFD editor — legend (A9)**: start / final / visited swatches beside the canvas so
  state roles are not conveyed by colour alone.
- **AFD editor — Run validity (A11)**: the Run button is disabled with an explicit
  reason ("Set a start state…", "Add at least one alphabet symbol…") until the
  automaton can actually run, instead of a generic after-the-fact toast.
- **AFD editor — simulation verdict (E2)**: the trace zone shows `✓ Accepted` /
  `✗ Rejected` (icon + text), not background colour alone.
- **Teacher panel — reset confirmation modal (D1)**: replaces the native
  `window.confirm` with a Moodle modal that names the student and spells out the
  destructive impact; a success/error toast follows the action (D2).

### Changed
- **Save indicator (B1)**: `Saving… / Saved ✓ / Save failed ✕` now come from
  `get_string` via `core/str` (were hardcoded English), so they follow the site
  language in both the AFD and truth-table editors.
- **Transition symbol prompt (A3, i18n part of G1)**: the `window.prompt` label is
  localised (`transition_symbol_prompt`) instead of hardcoded Spanish. (Full
  popover replacement remains deferred.)
- **truth_table submit (B3/G3)**: the submit button shows a spinner + "Grading…"
  and blocks double-submit while the answer is being graded.
- **"Last word tested" empty string (D3)**: an empty word now renders as
  `ε (empty)` in the teacher report and the student wordbank, instead of a blank
  cell that read as a bug.

### Migration
- Bump `mod_graphitoubb` to version `2026062901` (release `0.3.1-alpha`)

## [Unreleased] — AFD prod-readiness fixes — 2026-06-29

### Fixed
- **mod_graphitoubb**: ship plugin activity icons (`pix/monologo.svg`, `pix/icon.svg`).
  Previously every page emitted a 404 for `theme/image.php/.../monologo` and the
  activity rendered a broken icon.
- **AFD editor**: cap Cytoscape zoom (`maxZoom: 1.5`, conditional layout) so a
  saved automaton with few states no longer auto-fits to an unusable ~2.7× zoom
  with overlapping nodes; a fresh editor now opens at zoom 1.
- **AFD editor**: restore the wordbank history on resume. `render_editor` now
  pre-populates the tested-words panel from `graphitoubb_wordbank_log` instead of
  showing an empty list after reload.
- **reset_attempts**: also delete `graphitoubb_snapshot` and
  `graphitoubb_wordbank_log` rows. Resetting an AFD student now actually clears
  their automaton and tested words (previously a no-op for AFD attempts).
- **Teacher panel**: replace hardcoded Spanish labels (`Distribución de notas`,
  `Rango`, `Cantidad`, `Nota`, `Intentos`, `Tiempo`, `Estado`, `Borrador`) with
  localised strings so the panel follows the site language.

### Migration
- Bump `mod_graphitoubb` to version `2026062900`

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
