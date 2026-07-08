# Karnaugh & Relations Tools: boolean-simplification and binary-relations exercise types for GraphitoUBB

**Status:** Planning
**Author:** dicodina
**Created:** 2026-07-06
**Updated:** 2026-07-06 (grill-with-docs: scope, Karnaugh two-stage flow + strict-minimality, Relations Option A; reconciled against the built grafo/arbol foundation. `/prd` cold-implementer review — 10 findings fixed: 0-covering-group ⇄ equivalence made consistent, `grouping_fraction` fully determined (`validity_score`/`min_score`/`used` defined), relations counterexample false-negative branch specified, weight-sum-100 authoring validation, contradiction rejected at authoring, AC1 given a concrete expected score, AC5/OQ-1 dependency noted, mock result aligned to AC1 math). OQ-1 resolved → D13 (gate schema). Foundation merged to `main` (289ce7d) + code-reviewed 2026-07-06: sound to build on; OQ-5 resolved → D14 (qtype seed/import-only — the qtype edit form has no tool selector); added Wave 0 pre-work (shared `result_builder` + contract-shape/empty-answer-key test guards); corrected the seed-constant target (`2026062904`, not the plugin version) and `close_behavior='auto_submit'` default handling
**Depends on:** the grafo/arbol foundation (`grader_interface`, `grader_dispatch`, tool-aware `qtype`, `finish_attempt` as canonical grade trigger, `graph_canvas`) — **merged to `main` and present in the worktree** (commit `289ce7d`, PR #2). Reviewed 2026-07-06: the foundation is sound to build on; the review corrected two Wave C assumptions (see D14 and the seed-constant note).

**Sources:** `CONTEXT.md`; `docs/adr/0001-extend-qtype-for-afd.md`; `docs/adr/0003-generic-grading-dispatch.md`; `docs/prd-grafo-arbol-tools.md` (the template this PRD mirrors; it lists "boolean-circuit simplification" and "binary relations" in *Out of Scope* as the future tools this PRD delivers); verified repo files — `local/graphitoubb/classes/grader_interface.php`, `grader_dispatch.php`, `tool_interface.php`, `tool_registry.php`, `bootstrap.php`, `tools/truth_table/domain/{lexer,parser,evaluator}.php`, `tools/truth_table/grader/equivalence_grader.php`, `tools/grafo/grafo_tool.php`; `mod/graphitoubb/classes/external/{finish_attempt,submit}.php`, `edit_problem.php`, `view.php`, `classes/output/renderer.php`, `db/install.xml`; `qtype/graphitoubb/{question,questiontype,renderer}.php`, `classes/catalog_seeder.php`; `local/graphitoubb/catalog/*.json`, `cli/export_questions_xml.php`. Requirements RF_04 (Karnaugh simplification, must) and RF_05 (binary relations, must) from *Especificación de Requerimientos* §Cap. 3.

## Problem

The GraphitoUBB suite now grades four exercise families — `afd`, `truth_table`, `grafo`, `arbol`. Two required exercise types from the requirements spec are still uncovered:

| RF | Content | Covered today? | Gap |
|---|---|---|---|
| **RF_04** (must) | **Simplificación booleana** con mapa de Karnaugh (≤4 variables), forma mínima, verificación de equivalencia | ❌ Missing | No Karnaugh tool |
| **RF_05** (must) | **Relaciones binarias**: representación (matriz / dígrafo / pares) + propiedades (reflexiva, simétrica, antisimétrica, transitiva) con contraejemplo | ❌ Missing | No relations tool |

`truth_table` grades propositional formulas but cannot pose a *simplification* exercise (K-map grouping, minimal form). Nothing in the suite models a binary relation or checks its algebraic properties. Both requirements are marked **must** and both are named, gradable exercise families the platform cannot currently pose.

**Cost of doing nothing:** two must-have requirements stay outside the auto-graded platform.

**Success signals (internal plumbing, not business metrics):**
- A teacher can author, and a student can solve and be auto-graded on, (a) a Karnaugh simplification of a ≤4-variable function defined by its truth table, and (b) a relations exercise (build a representation + declare the four properties), both inside a `mod_graphitoubb` activity.
- The same two families are reusable as `qtype_graphitoubb` Question Bank questions inside a Moodle quiz.
- A curated preset catalogue seeds ready-made Karnaugh and relations exercises (banco de actividades), and the Question Bank is seeded with ready-made questions (banco de preguntas).
- The RF_04 submission gate ("enviar sólo si la actividad está activa y tiene intentos disponibles") is enforced for real.
- Adding the two tools does **not** regress the production-verified `afd`, `truth_table`, `grafo`, or `arbol` graders/editors.

## Solution Overview

Add two new **tools** (in the `tool_interface` sense) to `local_graphitoubb`: `karnaugh` and `relations`. Both plug into the **already-built** generic grading foundation (`grader_interface` + `grader_dispatch`, ADR-0003) and the **already-tool-aware** `qtype`. Each ships across three surfaces to match the existing pattern: the `mod_graphitoubb` activity (authoring + student editor + server-side grader), a `qtype_graphitoubb` Question Bank type, and a preset catalogue + Question Bank seeding.

```
        ┌──────────────────────────────────────────────────────────────┐
        │  EXISTING, BUILT foundation (ADR-0003, grafo/arbol waves)     │
        │  grader_interface · grader_dispatch(slug→grader)              │
        │  finish_attempt = canonical grade trigger + grade_cache       │
        │  tool-aware qtype (routes by `tool`) · preset catalog + seeder │
        │  graph_canvas (generic Cytoscape node/edge canvas)            │
        └───────────────┬───────────────────────────┬──────────────────┘
                        │  add case                  │  add case
        ┌───────────────▼────────────┐   ┌───────────▼──────────────────┐
        │  karnaugh tool (NEW)        │   │  relations tool (NEW)         │
        │  type: simplify             │   │  type: analyze                │
        │  - grid editor (NEW JS)     │   │  - matrix + pairs (NEW JS)    │
        │  - fill + group, 2 stages   │   │  - digraph → reuses graph_canvas
        │  - equiv + validity + min   │   │  - 4 properties + counterex.  │
        └─────────────────────────────┘   └───────────────────────────────┘
        reuses truth_table lexer/parser/evaluator (formula shortcut, equivalence)
```

Grading is **structural/invariant-based** (pure, deterministic, unit-testable), mirroring the shape of the existing graders: `grade(array $problem, ?string $submissionjson): array` returning the shared result array, `PASS_THRESHOLD = 0.6`.

> **UI/UX mock:** [`docs/mockups/prd-karnaugh-relations-ui.html`](./mockups/prd-karnaugh-relations-ui.html) — static, non-functional sketch (open in a browser) of the key screens: Karnaugh two-stage student editor (Stage 1 fill + "Verificar mapa", Stage 2 group + live minimal form with classic K-map loops), relations student editor (matrix/pairs/digraph tabs + property checklist), teacher authoring for both, the graded-result panels (with per-group / per-property feedback + counterexample), and the RF_04 submission gate. K-map is a grid (new editor); the digraph reuses the Cytoscape canvas (here hand-drawn SVG). Mirrors `docs/mockups/prd-grafo-arbol-ui.html`.

## Current State

Every row verified by Read/Grep at the cited path (in the `feat/afd-grader-ux-improvements` branch, where the grafo/arbol foundation lives).

| Capability | Status | Key Files | Notes |
|---|---|---|---|
| Tool contract | Exists | `local/graphitoubb/classes/tool_interface.php:30` | `descriptor()/validate()/serialize()/render_editor()` |
| Tool registry | Exists | `tool_registry.php`; `bootstrap.php` | Register by adding `$registry->register(new X_tool())` |
| **Grader interface** | **Exists (built)** | `local/graphitoubb/classes/grader_interface.php` | `grade(array $problem, ?string $submissionjson): array`; `PASS_THRESHOLD=0.6` |
| **Grader dispatch** | **Exists (built)** | `local/graphitoubb/classes/grader_dispatch.php:47` | slug→grader `switch`; `default→null`+`unsupported_result()`. **Add `karnaugh`/`relations` cases here** |
| Canonical grade trigger | Exists | `mod/graphitoubb/classes/external/finish_attempt.php` | Dispatches via `grader_dispatch::for($tool)`, recomputes `grade_cache` (D15 of grafo PRD) |
| truth_table domain (reusable) | Exists | `tools/truth_table/domain/{lexer,parser,evaluator}.php` | Boolean-formula lexer/parser/AST + evaluator — reused by Karnaugh formula shortcut + equivalence |
| Equivalence-by-bruteforce | Exists | `tools/truth_table/grader/equivalence_grader.php:194` | Compares two ASTs over 2ⁿ assignments — pattern reused for K-map equivalence |
| Problem persistence | Exists | `db/install.xml` `graphitoubb_problem` | Cols `tool,type,payload,payload_hash` — already tool-agnostic |
| Snapshot persistence | Exists | `db/install.xml` `graphitoubb_snapshot.payload`; `graphitoubb_attempt.current_draft` | Opaque JSON blob — answer envelope stored here |
| Submission/grade persistence | Exists | `db/install.xml` `graphitoubb_submission`; `graphitoubb_grade_cache` | Tool-agnostic; aggregated by best/last/average policy |
| Attempts/active gate | **Missing** | `graphitoubb.attempts_max`, `close_behavior` cols declared, **never read**; `attempt_service` = 1 attempt/user | RF_04 requires it; **this PRD builds it** (D9) |
| Teacher authoring UI | Exists | `mod/graphitoubb/edit_problem.php` | `<select name="tool">` + per-tool payload branches |
| graph_canvas (generic canvas) | Exists (built) | `mod/graphitoubb/amd/src/graph_canvas.js` | Generic Cytoscape canvas; **relations digraph mode reuses it** |
| tool-aware qtype | Exists (built) | `qtype/graphitoubb/question.php`,`questiontype.php`,`renderer.php` route by `tool`; `edit_graphitoubb_form.php` is truth_table-only | Grade/save/render are tool-aware for grafo/arbol; **the qtype edit form has NO tool selector** — canvas tools are seed/import-only. Add karnaugh/relations grade+render branches (D14), not a form |
| Preset catalogue + seeding | Exists | `classes/catalog/preset_catalog.php`; `cli/export_questions_xml.php`; `qtype/.../catalog_seeder.php` | Curated templates + XML seeding |

**What this PRD adds:** two graded tools (`karnaugh`, `relations`) across activity + qtype + preset surfaces, the Karnaugh grid editor and relations matrix/pairs editors (net-new JS), and the RF_04 submission gate.

## Why This Change

**Why two tools in one PRD.** They share the same scaffolding decisions (grader_dispatch case + tool-aware qtype wiring + authoring/preset/seeding hooks + the submission gate); deciding them once avoids the second tool depending on undocumented choices in the first. Mirrors the grafo/arbol PRD's D1. User-confirmed.

**Why plug into the existing foundation, not rebuild it.** `grader_interface`, `grader_dispatch`, the tool-aware qtype, and `finish_attempt` as the canonical grade trigger are already built and verified (grafo/arbol waves, ADR-0003). Karnaugh/relations add a `case` and implement the interface natively — the expensive generalization is done.

**Why Karnaugh grades via `finish_attempt` + `grader_dispatch` (not `submit.php`).** ADR-0003 made `finish_attempt` the canonical grade trigger for snapshot-based tools; `submit.php` stays truth_table-only. A Karnaugh answer (filled map + groups) and a relations answer (built relation + declared properties) are snapshots, not truth_table cell payloads — the same reasoning as grafo/arbol (D5 of that PRD).

**Why start Karnaugh exercises from the truth table.** Pedagogically the flow is truth table → K-map → groups → minimal form. Defining the function by its truth table (minterm set) is unambiguous and needs no parser for edge cases; a boolean-formula shortcut (reusing the truth_table lexer/parser) is offered as a convenience that auto-fills the table. User-confirmed.

**Why strict minimality (configurable).** A Karnaugh exercise is about *simplifying*; an equivalent-but-not-minimal answer (e.g. one 1-cell group per minterm) technically passes equivalence but defeats the exercise. The grader computes the optimal cover (prime implicants — trivial on ≤16 cells) and rewards fewer/larger groups, behind a `require_minimal` flag (default on) the teacher can disable for introductory practice. User-confirmed.

**Why structural grading with counterexamples for relations.** Properties (reflexive/…/transitive) are decidable predicates on the relation; the grader computes the true value and, on a wrong declaration, emits the concrete violating pairs. This gives precise, teachable feedback rather than a bare score. User-confirmed (Option A).

**Why build the submission gate now.** RF_04 explicitly requires "enviar sólo si la actividad está activa y tiene intentos disponibles"; the columns exist but are never enforced (1 fixed attempt, no dates). This PRD implements the gate as shared cross-cutting work (D9).

## Main Flow

Student solving a Karnaugh problem inside a `mod_graphitoubb` activity (relations mirrors this — build representation + tick properties instead of fill+group):

```
Teacher: edit_problem.php ── tool=karnaugh, define f by truth table (or formula shortcut) ──► problem_repository.save()
   │                                                                └─ graphitoubb_problem{tool='karnaugh',type='simplify',payload,payload_hash}
   ▼
Student opens activity ──► render_editor() → {template:'mod_graphitoubb/karnaugh_editor', context}
   │        └─ mustache: require(['mod_graphitoubb/karnaugh_editor'], Editor.init(attemptid, instanceid, schemaversion))
   │
   ├── STAGE 1 — fill: student transfers the given truth table into the K-map cells (Gray-code layout labelled)
   │        └─ "Verificar mapa" (self-check, no attempt consumed): highlights mis-placed cells; student may re-check freely
   ├── STAGE 2 — group: student draws groups over the 1-cells; the minimal form auto-assembles as OR of group terms (live)
   │        └─ answer envelope {answer_kind:'kmap', map:{...}, groups:[...]} emitted as the opaque snapshot
   ├── autosave ── Repository.saveSnapshot(envelope, schemaversion) ──► graphitoubb_snapshot.payload
   ├── Student clicks Entregar ── Repository.finishAttempt() ──► finish_attempt.php
   │        └─ GATE (D9): reject if activity closed (outside open/close window) OR no attempts left (submissions ≥ attempts_max)
   │        └─ load problem → grader_dispatch::for('karnaugh')->grade($problem, $snapshotjson)
   ├── karnaugh_grader:
   │        └─ fill_fraction   = correct cells / 2ⁿ                     (vs the function's truth table)
   │        └─ grouping: validity gate (non-empty) → equivalence(OR-of-groups ≡ minterms) + group validity + minimality
   │        └─ fraction = (fill_weight/100)·fill_fraction + (grouping_weight/100)·grouping_fraction
   │        └─ results[] name each mis-placed cell and each faulty group ("en qué grupo hay un error")
   ▼
submission_repository.save(...) → graphitoubb_submission{score,fraction,passed,grading_result}
   └─ finish_attempt recomputes grade_cache under attempts_policy (best/last/average)
```

Grading is synchronous inside the WS call, pure and DB-free in the grader (only the wrapper reads/writes). Invalid submissions (empty map, no groups) return `invalid=true, fraction=0` rather than throwing — same contract as the other graders.

## Core Concepts

### karnaugh — problem config & answer envelope

```
problem (graphitoubb_problem.payload)
├── tool: 'karnaugh'
├── type: 'simplify'
└── config:
    ├── prompt        : {es,en}   -- consigna
    ├── n_vars        : 2 | 3 | 4
    ├── var_names     : ["A","B","C"]         -- display labels, MSB→LSB
    ├── minterms      : [int,...]             -- assignment indices (0..2ⁿ-1) where f=1  ← canonical truth of f
    ├── require_minimal : bool (default true) -- D5
    └── scoring       : { fill_weight: 40, grouping_weight: 60 }   -- percentages (default 40/60)

answer envelope (graphitoubb_snapshot.payload — the $submissionjson the grader receives)
├── answer_kind : 'kmap'
├── map         : { cells: { "0":1, "1":0, "2":1, ... } }   -- student-filled value per assignment index (STAGE 1)
├── groups      : [ { id:"g0", cells:[int,...] }, ... ]      -- each group = the assignment indices it covers (STAGE 2)
└── schema_version : 1
```

- The **minimal form is derived**, never typed: each valid group → a product term (the variables constant across its cells); the proposed form = OR of those terms (D4, "grupos = respuesta").
- `cells` in a group are **assignment indices**; adjacency (Gray) and edge-wrap are computed from index bit-patterns, so the envelope is layout-independent.

### karnaugh — grading (D4, D5)

```
STAGE 1 (fill)  fill_fraction = |{ i ∈ [0,2ⁿ) : map.cells[i] == f(i) }| / 2ⁿ
                where f(i)=1 ⇔ i ∈ config.minterms. A cell index ABSENT from map.cells
                (unfilled) counts as INCORRECT. results[] flags each mis-placed/blank cell
                (index + expected/got).

STAGE 2 (group)
  validity gate: groups empty ⇒ the whole submission is invalid, fraction 0.
  per-group legality: a group is LEGAL iff its size is a power of 2 (1,2,4,8,16) AND its cells
     form an axis-aligned sub-cube under Gray adjacency with edge-wrap AND every covered cell is a
     1 of f. A group covering a 0-cell is one way to be illegal; each illegal group is named in
     results[] ("grupo gX: cubre un 0"). Let  total_groups = all submitted groups,
     valid_groups = legal groups,  validity_score = valid_groups / total_groups (= 1.0 when all legal).
  equivalence: e = 1 if (OR of the terms of ALL submitted groups) ≡ f over the 2ⁿ assignments, else 0
     (reusing the equivalence_grader brute-force pattern). A 0-covering group's term evaluates to 1 on
     that 0-cell, so it OVER-COVERS ⇒ e = 0 — i.e. an illegal 0-group also fails equivalence. Uncovered
     1-cells ⇒ e = 0 too. results[] names the over-covered 0-cells and uncovered 1-cells → "en qué
     grupo hay un error".
  minimality (only when require_minimal): optimal = size of a minimum prime-implicant cover of f
     (Quine–McCluskey / prime-implicant chart, ≤16 cells). used = number of LEGAL groups in the student's submitted cover.
     min_score = min(1, optimal / max(used,1)); feedback "se puede con {optimal} grupos (usaste {used})".

grouping_fraction = (e == 0)        ? 0
                  : require_minimal ? average( validity_score , min_score )
                  :                   validity_score        // minimality off ⇒ grade = validity only
fraction = (fill_weight/100)·fill_fraction + (grouping_weight/100)·grouping_fraction
passed   = fraction ≥ 0.6
```

### relations — problem config & answer envelope

```
problem (graphitoubb_problem.payload)
├── tool: 'relations'
├── type: 'analyze'
└── config:
    ├── prompt                 : {es,en}
    ├── base_set               : [ "1","2","3" ]        -- elements of S (|S| ≤ 6, D7)
    ├── relation               : [ [a,b], ... ]          -- R ⊆ S×S, the canonical relation to analyze
    ├── required_representation : 'matrix'|'pairs'|'digraph'|'any'   (default 'any')  -- D7
    ├── ask_properties         : ['reflexive','symmetric','antisymmetric','transitive']  (default: all four)
    └── scoring                : { representation_weight: 40, properties_weight: 60 }   (default 40/60)

answer envelope (graphitoubb_snapshot.payload)
├── answer_kind   : 'relation'
├── representation: 'matrix' | 'pairs' | 'digraph'      -- which surface the student used
├── pairs         : [ [a,b], ... ]                       -- the relation the student built (normalized from any rep)
├── properties    : { reflexive:bool, symmetric:bool, antisymmetric:bool, transitive:bool }
└── schema_version: 1
```

### relations — grading & counterexamples (D7)

```
representation_fraction = (student.pairs == config.relation) ? 1
                          : |student.pairs ∩ config.relation| / |student.pairs ∪ config.relation|  (Jaccard, partial credit)
                          results[] lists missing/extra pairs.

properties_fraction     = |{ p ∈ ask_properties : student.properties[p] == true_property(config.relation, p) }|
                          / |ask_properties|
                          A counterexample exists ONLY when the property is FALSE. Two wrong-declaration cases:
                            (a) declared TRUE but property is FALSE → results[] carries the witnessing pair(s):
                                  reflexive     → an a∈S with (a,a)∉R                (e.g. "falta (2,2)")
                                  symmetric     → (a,b)∈R but (b,a)∉R
                                  antisymmetric → (a,b),(b,a)∈R with a≠b
                                  transitive    → (a,b),(b,c)∈R but (a,c)∉R
                            (b) declared FALSE but property is TRUE → results[] marks it correct=false with NO
                                  counterexample (message "la propiedad sí se cumple; no hay contraejemplo").
                          (true_property is computed on the CANONICAL config.relation, independent of what the student built.)

fraction = (representation_weight/100)·representation_fraction + (properties_weight/100)·properties_fraction
passed   = fraction ≥ 0.6
```

- The two dimensions are **independent**: a student who mis-builds the relation *and* declares properties consistent with their wrong build is still marked against the canonical relation on both axes.
- **Representations are equivalent input modes.** matrix = |S|×|S| boolean grid; pairs = an explicit set editor; digraph = a Cytoscape canvas (reuses `graph_canvas`) where an arc `a→b` ⇔ `(a,b)∈R`. All three normalize to the same `pairs` list before grading.

### Grader contract (shared, already built)

Both new graders implement the existing interface natively:

```php
interface grader_interface {
    public const PASS_THRESHOLD = 0.6;
    public function grade(array $problem, ?string $submissionjson): array; // → shared result array
}
```

Shared result array: `{ graded, invalid, message, score, fraction, passed, items_total, items_correct, results[] }` where each `results[]` entry is `{check, expected, got, correct}`. `items_total`/`items_correct` = the generic count pair (Karnaugh: cells+groups checked; relations: representation + properties checked).

## Invariants — Must Not Break

- **I1 — Existing graders unchanged.** `afd`, `truth_table`, `grafo`, `arbol` graders and their adapters produce identical output; adding `karnaugh`/`relations` to `grader_dispatch` is purely additive (new `case`s).
- **I2 — Existing editors untouched.** `afd_editor.js`, `truth_table_editor.js`, `graph_canvas.js` are not modified except that relations' digraph mode **consumes `graph_canvas` via its public API** (no edits to it; if a seam is missing, add it additively, mirroring how grafo/arbol mount it).
- **I3 — qtype backward compatible.** truth_table/grafo/arbol questions keep grading identically; the qtype tool router gains `karnaugh`/`relations` branches only.
- **I4 — Repository/WS contract.** `save_snapshot`/`get_latest_snapshot`/`finish_attempt` treat `payload` as an opaque string; no schema change to `graphitoubb_snapshot`/`graphitoubb_submission`.
- **I5 — Problem table shape.** `graphitoubb_problem{tool,type,payload,payload_hash}` already accommodates new slugs; no migration for the tools themselves.
- **I6 — Gate is additive & opt-in-safe.** The RF_04 gate (D9/D13) must not retroactively lock existing activities. Existing instances keep `timeopen=timeclose=0` (no window) and `attempts_max=NULL` (unlimited) ⇒ behavior identical to today. The RF_04 "default 1 attempt" applies only to **new** instances, set by the form default — never by backfilling existing rows.

## Interaction with Existing Systems

- **grader_dispatch / bootstrap.** New tools register additively; `grader_dispatch::for()` gains two `case`s.
- **finish_attempt (gate lands here — D9).** The submission gate is enforced in `finish_attempt` (and reflected in the editor UI). Because `finish_attempt` already recomputes `grade_cache` under `attempts_policy`, the best/last/average aggregation across multiple submissions ("intentos") is already supported.
- **mod_form.php / activity settings (D13).** The `graphitoubb` instance table already has `attempts_policy` (best|last|average), `attempts_max` (NULL=unlimited), `close_behavior` — but `mod_form.php` today exposes only name+intro, so none are teacher-settable. The gate adds **two new columns** `timeopen`/`timeclose` (int, default 0 = no restriction) and exposes all four settings in `mod_form.php` (persisted in `lib.php` add/update_instance). "Attempts used" = `COUNT(graphitoubb_submission)` for the user's attempt(s) on the instance (submissions are the tries; `grade_cache` already aggregates them by policy). Enforcement lives in a shared `submission_gate::check($instance,$userid)` called by **both** `finish_attempt.php` (karnaugh/relations/grafo/arbol/afd) and `submit.php` (truth_table).
- **qtype question engine.** Adding `karnaugh`/`relations` is the next multi-tool use of the tool-aware qtype; existing tool branches stay unchanged (I3). Note: the qtype has no availability/attempts gate of its own — that is the Moodle quiz's job — so the RF_04 gate is a **mod-activity** concern only.
- **graph_canvas.** Relations' digraph representation mounts `graph_canvas` in build mode with `directed=1`; the answer envelope's `pairs` is derived from the drawn arcs.

## Edge Cases

| Scenario | Behavior |
|---|---|
| Karnaugh: empty map or no groups submitted | `invalid=true, fraction=0, message='empty'` (parity with other graders' validity gate) |
| Karnaugh: group covers a 0-cell | Group flagged invalid in `results[]` ("grupo gX cubre un 0"); not counted toward equivalence |
| Karnaugh: group size not a power of 2 / non-rectangular under Gray adjacency | Group flagged illegal; UI should also prevent it live, but the grader is authoritative |
| Karnaugh: equivalent but non-minimal, `require_minimal=false` | Full grouping credit; no minimality penalty |
| Karnaugh: equivalent but non-minimal, `require_minimal=true` | Partial credit + feedback naming the optimal group count |
| Karnaugh: correct map fill but wrong groups | fill credit awarded; grouping scored on its own (weighted) |
| Karnaugh: teacher enters a formula shortcut that fails to parse | Authoring-time validation error (reuses truth_table lexer/parser); problem not saved |
| Karnaugh: function is a tautology (all 1s) | Allowed: one full-map group (term = 1) is the correct minimal form |
| Karnaugh: function is a contradiction (`minterms=[]`, all 0s) | **Rejected at authoring** as degenerate — nothing to simplify/group; the correct answer (no groups) would trip the empty-groups validity gate |
| Either tool: teacher weights do not sum to 100 | **Rejected at authoring** — `fill_weight+grouping_weight` (Karnaugh) / `representation_weight+properties_weight` (relations) must equal 100 |
| Relations: student builds relation wrong but declares properties correctly for the canonical R | representation partial/zero; properties full — the two axes are independent |
| Relations: property declared wrong | Counterexample from canonical R shown; property marked incorrect |
| Relations: `required_representation` set, student uses another | UI offers only the required surface; envelope's `representation` is validated against config |
| Relations: |S| beyond bound at authoring | Rejected at authoring (`|S| ≤ 6`, D7) |
| Relations: empty relation R = ∅ | Valid; vacuously symmetric/antisymmetric/transitive, reflexive only if S=∅ — grader computes truthfully |
| Gate: no open/close dates and default `attempts_max=1` | Behaves exactly like today (one submission, always open) — I6 |
| Gate: student already at `attempts_max`, or activity closed | `finish_attempt` rejects with a clear reason; editor disables Entregar and shows why |
| Unknown tool slug reaches `grader_dispatch` | `unsupported_result()` (never fatals the WS) — existing behavior |

## Phases

Three waves, mirroring the grafo/arbol delivery shape. All mod-activity value ships in Waves A–B before the qtype surface in Wave C; each wave is independently shippable and verifiable.

**Wave 0 — Pre-work (do once, before mirroring the grader pattern 4×)**
- [ ] Extract a shared grading `result_builder` (trait or `base_grader`) from the `scored_result`/`invalid_result`/`check` helpers duplicated verbatim in `grafo_grader`/`arbol_grader`, so karnaugh/relations don't become copies 3 and 4. Additive refactor; existing graders keep byte-identical output (I1).
- [ ] A shared test helper asserting the 9-key contract shape, plus a `null`/malformed-`submissionjson` case and an **empty-answer-key guard** (grafo/arbol currently mis-handle an empty canonical answer — see Edge Cases) — apply to every new grader.

**Wave A — Karnaugh activity + submission gate** (mod only; independently shippable)
- [ ] `karnaugh_tool` (`tool_interface`) + `karnaugh_grader` (`grader_interface`) + domain: `kmap.php` (Gray layout, group legality, term derivation), `minimize.php` (prime implicants / optimal cover). Reuse truth_table `lexer/parser/evaluator` for the formula shortcut + equivalence.
- [ ] `karnaugh_editor.js` (NEW): two-stage grid editor — STAGE 1 fill cells + "Verificar mapa" self-check; STAGE 2 draw groups (rubber-band rectangles with edge-wrap) + live minimal-form readout. Mobile responsive (reuse AFD F1–F3 patterns). Emits the `{answer_kind:'kmap',...}` envelope via the existing snapshot path.
- [ ] Register in `bootstrap.php`; add `case 'karnaugh'` to `grader_dispatch`.
- [ ] `edit_problem.php` karnaugh authoring branch: define f by truth table (toggle 1s) or formula shortcut; set `n_vars`, `require_minimal`, weights.
- [ ] **Submission gate (D9/D13):** add `timeopen`/`timeclose` columns (install.xml + upgrade.php); expose the 4 settings in `mod_form.php`; enforce via shared `submission_gate::check()` called by `finish_attempt.php` + `submit.php`; reflect in editor. Treat the existing schema default `close_behavior='auto_submit'` as lock-after-close in v1. Default = today's behavior for existing rows (I6).
- [ ] Karnaugh mod presets (`local/graphitoubb/catalog/karnaugh.json`) + `preset_catalog` tool loop + `edit_problem.php` group map + mod lang strings.

**Wave B — Relations activity** (mod only)
- [ ] `relations_tool` + `relations_grader` + domain: `relation.php` (normalize matrix/pairs/digraph → pairs; property predicates + counterexample extraction).
- [ ] `relations_editor.js` (NEW matrix + pairs surfaces) + digraph mode mounting `graph_canvas` (directed); property checklist. Mobile responsive. Emits `{answer_kind:'relation',...}`.
- [ ] Register in `bootstrap.php`; add `case 'relations'` to `grader_dispatch`.
- [ ] `edit_problem.php` relations authoring: base set + relation editor + `required_representation` + `ask_properties` + weights.
- [ ] Relations mod presets (`relations.json`) + hooks + lang strings. (Gate already built in Wave A.)

**Wave C — qtype (Question Bank) + seeding for both tools**
- [ ] Tool-aware qtype (**seed/import-only authoring — D14**, mirroring grafo/arbol; NO qtype edit form): add `karnaugh`/`relations` branches to `question.php` (grade via `grader_dispatch`), `questiontype.php` (`save_canvas_question_options` + `import_from_xml`, payload stored verbatim), `renderer.php` (embed each tool's editor host + hidden answer input + read-only review). **`edit_graphitoubb_form.php` is NOT touched** — it stays truth_table-only (grafo/arbol set this precedent). truth_table/grafo/arbol paths unchanged (I3).
- [ ] qtype host: embed `karnaugh_editor` / `relations_editor` in the question engine (hidden input, no autosave, read-only review), mirroring how `renderer.php` mounts `graph_canvas` for grafo/arbol.
- [ ] Question Bank seeding: add `all('karnaugh')`/`all('relations')` to the `cli/export_questions_xml.php` merge (they follow the canvas branch — verbatim JSON + sha256, no serializer); regenerate `qtype/graphitoubb/db/preset_questions.xml`; **bump the seed constant `2026062904` → a new value in BOTH `qtype/graphitoubb/db/install.php:34` and `db/upgrade.php:46`** (this also back-fills grafo/arbol preset questions, which never re-seeded on upgraded installs because the constant was not bumped when the export was generalized).
- [ ] qtype lang strings.

## Key Files to Touch (Wave A)

| Area | File(s) | Change |
|---|---|---|
| karnaugh tool | `local/graphitoubb/classes/tools/karnaugh/karnaugh_tool.php` (new) | `tool_interface` impl, descriptor slug `karnaugh` |
| karnaugh domain | `.../tools/karnaugh/domain/kmap.php`, `minimize.php` (new) | Gray layout + group legality + term derivation; prime-implicant optimal cover |
| karnaugh grader | `.../tools/karnaugh/grader/karnaugh_grader.php` (new) | implements `grader_interface`; fill + grouping (equiv/validity/minimality) |
| Reuse | `tools/truth_table/domain/{lexer,parser,evaluator}.php` | formula shortcut + equivalence brute force (no edits) |
| Dispatch | `local/graphitoubb/classes/grader_dispatch.php` | add `case 'karnaugh': return new karnaugh_grader();` |
| Register | `local/graphitoubb/classes/bootstrap.php` | `use` + `$registry->register(new karnaugh_tool())` |
| Editor JS | `mod/graphitoubb/amd/src/karnaugh_editor.js` (new) + `amd/build/*` | Two-stage grid editor + self-check + live minimal form |
| Editor template | `mod/graphitoubb/templates/karnaugh_editor.mustache` (new) | Grid host + `require([...karnaugh_editor]).init(...)` |
| Authoring | `mod/graphitoubb/edit_problem.php` | Add `karnaugh` option + truth-table/formula authoring branch |
| View dispatch | `mod/graphitoubb/view.php` | `else if ($problem->tool === 'karnaugh')` → renderer |
| Renderer | `mod/graphitoubb/classes/output/renderer.php` | `render_karnaugh_editor(...)` + `js_call_amd` |
| Gate schema (D13) | `mod/graphitoubb/db/install.xml`, `db/upgrade.php` | Add `timeopen`/`timeclose` (int, default 0) to `graphitoubb`; upgrade guarded by `$oldversion < 2026070600` + `upgrade_mod_savepoint(true, 2026070600, 'graphitoubb')` (current version `2026070501`) |
| Gate settings (D13) | `mod/graphitoubb/mod_form.php`, `lib.php` | Expose `timeopen`/`timeclose` (date_selector), `attempts_max` (default **1** for new instances), `attempts_policy`; persist in `add_instance`/`update_instance` |
| Gate enforcement (D9/D13) | `mod/graphitoubb/classes/submission_gate.php` (new); `classes/external/finish_attempt.php` + `submit.php`; `classes/output/renderer.php` | `submission_gate::check($instance,$userid)→{allowed,reason}` called by both submit endpoints; renderer disables Entregar + shows reason |
| Presets | `local/graphitoubb/catalog/karnaugh.json` (new); `classes/catalog/preset_catalog.php`; `mod/graphitoubb/edit_problem.php` group map | Curated Karnaugh templates |
| Lang | `mod/graphitoubb/lang/{en,es}/graphitoubb.php` | Editor/authoring/feedback/gate strings |
| Versions | `mod/graphitoubb/version.php`, `local/graphitoubb/version.php` | Bump |

## Acceptance Criteria

Each criterion is tagged with the wave that must satisfy it.

1. **[Wave A] Karnaugh — fill + grouping partial credit** — setup: `n_vars=3`, `minterms=[0,2,3,4,7]` (optimal cover = **3** groups: `B'C' + BC + A'B`), weights 40/60, `require_minimal=true` · action: student fills the map correctly and submits a valid, equivalent, but non-minimal cover of **4** legal groups (one redundant) · expect: `fill_fraction=1`; `validity_score=1`, `min_score=min(1, 3/4)=0.75`, `grouping_fraction=avg(1,0.75)=0.875`, `fraction=0.4·1+0.6·0.875=0.925`, `passed=true`; `results[]` names the redundant group and `optimal=3` · lives in: `local/graphitoubb/tests/tools/karnaugh/karnaugh_grader_test.php`.
2. **[Wave A] Karnaugh — group covers a 0 ⇒ flagged + not equivalent** — setup: same function · action: student draws a group that includes a 0-cell · expect: that group flagged in `results[]` ("cubre un 0"), OR-of-groups ≠ f ⇒ grouping fraction 0 · same suite. *(negative case)*
3. **[Wave A] Karnaugh — `require_minimal=false`** — setup: `require_minimal=false` · action: equivalent non-minimal grouping (all valid groups) · expect: full grouping credit, no minimality penalty · same suite.
4. **[Wave A] Karnaugh — mis-placed fill flagged** — setup: any function · action: student mis-places 2 of 8 cells · expect: `fill_fraction=6/8`, `results[]` names the 2 cells with expected/got · same suite.
5. **[Wave A] Submission gate (D9/D13)** — setup: activity with `timeclose` in the past (reason `closed`) OR `timeopen` in the future (reason `not_open`) OR `attempts_max=1` with one submission already recorded (reason `no_attempts`) · action: student clicks Entregar · expect: `submission_gate::check` returns `{allowed:false, reason}`, `finish_attempt` rejects with that reason, no submission written · positive: with `timeopen=timeclose=0` and an attempt available, submit succeeds · negative/I6: an existing instance with `attempts_max=NULL` and no dates accepts repeated submits, behaving exactly like today · lives in: `mod/graphitoubb/tests/external/finish_attempt_test.php` and `mod/graphitoubb/tests/submission_gate_test.php`.
6. **[Wave B] Relations — properties + counterexample (both wrong-declaration directions)** — setup: `S={1,2,3}`, `R={(1,1),(2,2),(3,3),(1,2)}` (true props: reflexive✓ symmetric✗ antisymmetric✓ transitive✓) · action: student builds R correctly (any representation) and declares reflexive=true, symmetric=**true** (wrong: declared-true-but-false), antisymmetric=true, transitive=true · expect: representation full; `symmetric` marked incorrect **with** counterexample "(1,2)∈R pero (2,1)∉R"; `properties_fraction=3/4` · negative (declared-false-but-true): the same student instead declaring transitive=**false** is marked incorrect **with no counterexample** (message "la propiedad sí se cumple") · lives in: `local/graphitoubb/tests/tools/relations/relations_grader_test.php`.
7. **[Wave B] Relations — representation independent of properties** — setup: same R · action: student builds a wrong relation (missing one pair) but declares all four properties matching canonical R · expect: representation partial (Jaccard, missing pair listed), properties full · same suite.
8. **[Wave C] qtype tool routing (regression + new)** — setup: a truth_table question and a karnaugh question in the Question Bank · action: grade each via the tool-aware qtype · expect: truth_table identical to today; karnaugh routed to `karnaugh_grader` · lives in: `qtype/graphitoubb/tests/question_test.php`. *(I3)*
9. **[Wave A/B] Browser end-to-end** — setup: teacher authors a Karnaugh (from truth table) and a relations problem from presets · action: student solves each and clicks Entregar · expect: graded result shown, `graphitoubb_submission` written, `grade_cache` recomputed, 0 console errors · verified with Playwright at desktop + 375px widths (mobile responsive).

## Resolved Decisions

| # | Decision | Why |
|---|---|---|
| D1 | One PRD covering two tools (`karnaugh`, `relations`) plugged into the existing grader/dispatch/qtype foundation; shipped in three waves (karnaugh mod → relations mod → shared qtype+seeding) | Shared scaffolding decided once; mirrors grafo/arbol D1. User-confirmed |
| D2 | Both tools live in `local_graphitoubb` + `mod_graphitoubb` (+ `qtype`) as sibling **tools**, not new subplugins; single type each — `karnaugh:simplify`, `relations:analyze` | The architecture has no unit between plugin and tool; afd/truth_table/grafo/arbol set the pattern. User-clarified |
| D3 | Karnaugh exercises **start from the truth table**: teacher defines f by its minterm set (toggle 1s); a boolean-formula shortcut (reusing truth_table lexer/parser) auto-fills the table but the persisted truth is the minterm set | Unambiguous, no parser edge cases; pedagogically truth table → map → groups. User-confirmed |
| D4 | Student flow = **Option B, two stages**: (1) transfer truth table into the K-map (learns Gray adjacency) with a "Verificar mapa" self-check; (2) draw groups — the minimal form auto-assembles as OR of group terms ("grupos = respuesta", student never types the expression). One weighted grade (`fill_weight`/`grouping_weight`, default 40/60); a wrong fill lowers the score but does not hard-block submit | User chose B over A (pre-filled map); self-check keeps students from grouping blindly; grouping/equivalence evaluated against the canonical f, not the student's fill |
| D5 | Minimality is graded, **strict but configurable** (`require_minimal`, default ON): grader computes the optimal prime-implicant cover (≤16 cells) and gives partial credit + feedback when the student uses more groups than optimal; teacher can disable for intro practice | Karnaugh is about simplifying; equivalence alone lets a 1-cell-per-minterm answer pass. User-confirmed |
| D6 | Karnaugh defaults: **SOP only** (no POS / grouping 0s), **no don't-cares (X)** in v1, **2/3/4 variables** chosen by the teacher | Keep v1 simple and clear; POS/don't-cares are future extensions. User-confirmed as defaults |
| D7 | Relations = **Option A**: teacher defines base set S + canonical relation R (pairs); student **builds R in one representation** (matrix/pairs/digraph — free choice by default, `required_representation` can pin it) **and declares the four properties**; both graded (weights default 40/60), with counterexamples from canonical R. Relation shown to the student as **pairs**. Defaults: all four properties, `|S| ≤ 6`, auto counterexamples | Gives real value to "construir en representación" without Option C's rule-deduction complexity; matches "verifica cada propiedad + contraejemplo". User-confirmed |
| D8 | Both tools grade via **`finish_attempt` + `grader_dispatch`** (add `case`s), implementing `grader_interface` natively; `submit.php` stays truth_table-only | Follows ADR-0003 (finish_attempt = canonical snapshot-tool grade trigger); their answers are snapshots. **Supersedes an earlier session note that said "generalize submit.php"** — reconciled after reading ADR-0003 |
| D9 | Build the **RF_04 submission gate**: teacher-set open/close window (= "activa") + `attempts_max` (default 1, = "intentos disponibles"); enforced in `finish_attempt` + reflected in the editor; multiple submissions aggregated by `attempts_policy` (best/last/average, already in `grade_cache`). Applies to all mod tools. Default = today's behavior for existing activities (I6) | RF_04 requires it and it is declared-but-unenforced today; "intentos" ≈ submissions in the existing model. User-confirmed "gate completo, default 1" |
| D10 | Delivery scope = mod activity **+** qtype **+** preset catalogue **+** Question Bank seeding for both tools ("banco de actividades y de preguntas") | User-confirmed: both surfaces, both banks |
| D11 | Karnaugh uses a **new grid editor** (`karnaugh_editor.js`), not `graph_canvas` (a K-map is a grid, not a node/edge graph); Relations reuses `graph_canvas` for the **digraph** representation and adds new matrix/pairs surfaces | Fit the substrate to the artifact; reuse where the shape matches (relations digraph) |
| D12 | Grader result uses the shared array + generic `items_*` count pair; Karnaugh/relations specifics live in `results[]` entries (`{check,expected,got,correct}`) | Matches `grader_interface` and what `submission_repository->save` consumes; keeps feedback tool-specific without a new contract |
| D13 (resolves OQ-1) | Gate storage = **add two columns** `timeopen`/`timeclose` (int, NOTNULL, default 0 = no restriction) to the `graphitoubb` instance table; **reuse** existing `attempts_max` (NULL=unlimited; form defaults new instances to **1**), `attempts_policy` (best/last/average), `close_behavior`. "Attempts used" = `COUNT(graphitoubb_submission)` for the user's attempt(s) on the instance. Enforcement in a shared `submission_gate::check($instance,$userid)→{allowed,reason∈{not_open,closed,no_attempts}}`, called by both `finish_attempt.php` and `submit.php`; `mod_form.php` exposes the four settings. v1 `close_behavior` = lock-after-close — the existing schema default `'auto_submit'` is treated as lock (no cron); cron auto-submit deferred | Only the dates are genuinely missing (`attempts_*`/`close_behavior` already exist but were never exposed/read); submissions already model "tries" and `grade_cache` already aggregates them by policy, so no new attempt/tries table is needed. Existing rows keep `timeopen=timeclose=0` + `attempts_max=NULL` ⇒ no retroactive lock (I6). Sharing the check across both submit endpoints makes the gate uniform for every mod tool |
| D14 (resolves OQ-5) | qtype authoring for karnaugh/relations = **seed/import-only**, like grafo/arbol: questions enter the Question Bank via the catalog→XML→seeder pipeline (+ manual XML import), NOT a qtype edit form. `edit_graphitoubb_form.php` stays truth_table-only. Teachers author NEW karnaugh/relations exercises in the **mod activity** (`edit_problem.php`); the qtype only renders + grades + serves seeded questions | The merged review showed grafo/arbol reached the bank with zero qtype-form work (`questiontype.php:95`: "payload arrives ready-made via XML import/seeding"). Building the first-ever qtype tool selector is net-new with no precedent and out of proportion for v1. User-confirmed 2026-07-06 |

## Open Questions

None block Wave A.

1. **OQ-1** ✅ Resolved 2026-07-06 → D13. Gate storage = add `timeopen`/`timeclose` to `graphitoubb`; reuse `attempts_max`/`attempts_policy`/`close_behavior`; "attempts used" = submission count; shared `submission_gate` enforced in both submit endpoints.
2. **OQ-2** — Karnaugh minimality feedback granularity: is "you used M groups, optimal is N" enough, or should the grader also *suggest* a specific minimal grouping? Suggesting one is more work and there can be ties. Default: report counts only. · owner: user. **Open (default applies).**
3. **OQ-3** — Relations partial-credit for representation: Jaccard over pairs (default, D7) vs all-or-nothing vs per-cell (matrix). Default Jaccard. · owner: user. **Open (default applies).**
4. **OQ-4** — Karnaugh scoring weights + relations weights defaults (40/60): confirm or adjust. · owner: user. **Open (defaults apply).**
5. **OQ-5** ✅ Resolved 2026-07-06 → D14. qtype authoring = seed/import-only (no qtype edit form). The qtype host embeds each tool's own editor (karnaugh grid needs no Cytoscape; relations reuses `graph_canvas` for the digraph representation).

## Out of Scope

- **POS (product-of-sums) / grouping 0s** and **don't-care (X) cells** for Karnaugh — future extension (D6).
- **Karnaugh maps beyond 4 variables** (5–6 var maps) — outside RF_04's "hasta 4 variables".
- **Relations beyond the four properties** (equivalence-relation / partial-order classification, closures, composition) — future extension; v1 declares the four named properties only (D7).
- **Rule-based relation construction** (Option C: derive R from "x divides y") — rejected in favor of Option A (D7).
- **Migrating existing tools** onto anything new, or touching afd/truth_table/grafo/arbol graders/editors (I1/I2/I3).
- **Un-stubbing / re-hosting the AFD qtype** — separate work (grafo/arbol PRD OQ-4).
- **Pushing grades to the Moodle gradebook** (`lib.php` still has no `FEATURE_GRADE_HAS_GRADE`; unchanged by this PRD).
