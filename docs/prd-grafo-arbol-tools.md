# Grafo & Arbol Tools: graph-theory and tree/BST exercise types for GraphitoUBB

**Status:** Planning
**Author:** dicodina
**Created:** 2026-07-05
**Updated:** 2026-07-05 (grill-with-docs: D3 convergence, D14–D18; ADR-0002 amended; ADR-0003 added; UI/UX mock linked. Advisor review (Codex): D20 graph_canvas API contract, D21 directed/Hamiltonian semantics, edge-ids, n_vertices gate fix, AC wave-tags, I1 wording, OQ-6/7 risks)
**Sources:** `CONTEXT.md`; `docs/adr/0001-extend-qtype-for-afd.md`; `docs/adr/0002-afd-editor-core-host-split.md`; `docs/adr/0003-generic-grading-dispatch.md`; `docs/qtype-afd-plan.md`; course syllabus *Estructuras Discretas para Cs. de la Computación* (código 620434/634173, RA1–RA4); verified repo files — `local/graphitoubb/classes/tool_interface.php`, `tool_registry.php`, `bootstrap.php:52`, `tools/afd/afd_tool.php`, `tools/afd/grader/afd_grader.php`, `tools/truth_table/truth_table_tool.php`; `mod/graphitoubb/db/install.xml`, `classes/external/finish_attempt.php:81`, `classes/external/submit.php:109`, `edit_problem.php`, `templates/editor.mustache:104`, `amd/src/cytoscape_factory.js`, `amd/src/afd_editor.js`; `qtype/graphitoubb/question.php:204`.

## Problem

The GraphitoUBB suite grades exactly two exercise families today — `afd` (finite automata) and `truth_table` (propositional logic). Mapped against the four learning outcomes (RA) of the target course, coverage has large holes:

| RA | Syllabus content | Covered today? | Gap |
|---|---|---|---|
| RA1 | Proposiciones, operaciones lógicas | `truth_table` ✅ | — |
| **RA2** | **Grafos** (Königsberg, Euler/Hamilton, grado, conexidad) | ❌ Missing | No graph tool |
| **RA2/RA4** | **Árboles** (caminamientos, BST) | ❌ Missing | No tree tool |
| RA2 | Autómatas / lenguajes | `afd` ✅ | — |

RA2 is only half-served (automata only) and RA4's binary-search-tree content is unserved. The syllabus names concrete exercises the platform cannot pose or grade: the *Puentes de Königsberg* (decide whether an Euler circuit exists), graph traversals, BST construction, and tree traversals (pre/in/post-order). The project is literally named **Graphito** yet has no graph-theory tool.

**Cost of doing nothing:** two of the four learning outcomes stay outside the auto-graded platform, so the course cannot be run end-to-end on GraphitoUBB — the stated thesis goal.

**Success signals (internal plumbing, not business metrics):**
- A teacher can author, and a student can solve and be auto-graded on, a Königsberg-style Euler-decision problem and a BST-construction problem, both inside a `mod_graphitoubb` activity.
- The same two exercise families are reusable as `qtype_graphitoubb` Question Bank questions inside a Moodle quiz.
- A curated preset catalogue ("preguntas tipo") seeds ready-made grafo and arbol exercises.
- The grafo/arbol editors are **mobile responsive** — usable on a phone/tablet (canvas, toolbar, and answer controls adapt to narrow viewports and touch), matching the responsive work already done for AFD (F1–F3).
- Adding the two tools does **not** regress the production-verified `afd` editor or grader.

## Solution Overview

Add two new **tools** (in the `tool_interface` sense) to `local_graphitoubb`: `grafo` and `arbol`. Both reuse a new shared **graph-canvas foundation** extracted as *new* code from the AFD editor's generic parts, leaving `afd_editor.js` untouched (D3). Each tool ships across three surfaces to match the existing AFD/truth_table pattern: the `mod_graphitoubb` activity (authoring + student editor + server-side grader), a `qtype_graphitoubb` Question Bank type, and a preset catalogue.

```
                    ┌─────────────────────────────────────────┐
                    │  graph_canvas foundation (NEW, shared)   │
                    │  - generic Cytoscape node/edge canvas     │
                    │  - undo/redo, autosave, zoom, modals      │
                    │  - opaque-payload snapshot (nodes/edges)  │
                    └───────────────┬─────────────┬────────────┘
                                    │             │
                    ┌───────────────▼──┐   ┌──────▼──────────────┐
                    │  grafo tool       │   │  arbol tool          │
                    │  construct        │   │  bst_build           │
                    │  decision (Euler) │   │  traversal_answer    │
                    │  traversal (walk) │   │  reconstruct         │
                    └───────────────────┘   └──────────────────────┘
   afd_editor.js  ──►  UNCHANGED (keeps its own {states,transitions,alphabet,...} canvas)
```

Grading is **structural/invariant-based** (does the student's graph/tree satisfy the constraints, or is the submitted walk/sequence valid?), mirroring the pure, deterministic, unit-testable shape of `afd_grader` — fraction of satisfied checks, `PASS_THRESHOLD = 0.6`.

> **UI/UX mock:** [`docs/mockups/prd-grafo-arbol-ui.html`](./mockups/prd-grafo-arbol-ui.html) — a static, non-functional sketch (open in a browser) of the seven key screens: grafo build/decision/traversal, arbol bst_build/traversal_answer, the teacher authoring form, and the graded-result panel. It shows the three canvas modes (D16) visually (build/authoring = editable white canvas; given+answer = read-only amber canvas + answer control). Graphs/trees are hand-drawn SVG; the real editor uses Cytoscape.

## Current State

Every row verified by Read/Grep at the cited path.

| Capability | Status | Key Files | Notes |
|---|---|---|---|
| Tool contract | Exists | `local/graphitoubb/classes/tool_interface.php:30` | `descriptor()/validate()/serialize()/render_editor()`; `descriptor()` is static |
| Tool registry | Exists | `tool_registry.php`; `bootstrap.php:52` | Singleton; register a tool by adding `$registry->register(new X_tool())` in `register_default_tools()` |
| AFD tool + grader | Exists | `tools/afd/afd_tool.php`, `tools/afd/grader/afd_grader.php:54` | Grader is pure: `grade(array $config, ?string $snapshotjson): array` → `fraction/passed/…`, threshold 0.6 |
| Truth-table tool + grader | Exists | `tools/truth_table/truth_table_tool.php`, `grader/grader.php` | Facade dispatches on `$problem['type']` (complete/equivalence/classify) |
| Problem persistence | Exists | `db/install.xml:76` `graphitoubb_problem` | Cols `tool`, `type`, `payload`, `payload_hash` — already tool-agnostic |
| Snapshot persistence | Exists | `db/install.xml:48` `graphitoubb_snapshot.payload`; `graphitoubb_attempt.current_draft:35` | Opaque canonical JSON blob — format-agnostic |
| Submission/grade persistence | Exists | `db/install.xml:97` `graphitoubb_submission` (`score/fraction/passed/grading_result`); `graphitoubb_grade_cache:135` | Tool-agnostic; grade_cache aggregated by best/last/average policy |
| Grader dispatch (mod) | **Partial** | `classes/external/finish_attempt.php:81` | Hardcodes `if ($problem->tool === 'afd')`; no generic slug→grader lookup |
| Grader dispatch (mod, truth_table) | Partial | `classes/external/submit.php:109` | Separate endpoint, statically imports truth_table facade |
| Teacher authoring UI | Exists | `mod/graphitoubb/edit_problem.php` | `<select name="tool">` offers `truth_table`/`afd`; per-tool branches build the payload |
| Generic canvas substrate | **Partial** | `amd/src/cytoscape_factory.js`, `afd_editor.js` (~45% generic) | Cytoscape config, zoom, layout, undo/redo, modals reusable; start/final/symbol logic is AFD-only |
| Snapshot/autosave infra (JS) | Exists | `amd/src/repository.js`, `autosave.js`, `snapshot_controller.js`, `editor_toolbar.js` | Repository payload is opaque string → reusable as-is; toolbar is a generic mode FSM |
| Editor init/wiring | Exists | `templates/editor.mustache:104` | `require(['mod_graphitoubb/afd_editor'], Editor.init(attemptid, instanceid, schemaversion))`; limits via `data-*` |
| qtype (Question Bank) | **Partial** | `qtype/graphitoubb/question.php:42,204` | Hardcodes `public string $tool = 'truth_table'`; AFD qtype still a stub |
| Preset catalogue | Exists | `local/graphitoubb/classes/catalog/preset_catalog.php`; `cli/export_questions_xml.php` | Curated templates + XML seeding, used by truth_table |

**What this PRD adds:** a shared graph-canvas foundation plus two new graded tools (`grafo`, `arbol`) delivered across activity + qtype + preset surfaces, and a generic tool→grader dispatch so grading is no longer hardcoded to `afd`.

## Why This Change

**Why two tools reusing one foundation, in one PRD (not two).** The expensive, shared design decision — generalizing the Cytoscape canvas away from AFD assumptions — is identical for both tools and must be decided once; splitting into two PRDs would duplicate that spec or leave the second depending on undocumented choices in the first. Confirmed with the user.

**Why `graph_canvas` is the ADR-0002 núcleo, built for grafo/arbol first (D3).** ADR-0002 already decided to split `afd_editor.js` into a reusable núcleo + thin hosts, and explicitly rejected a permanently separate editor ("duplica el lienzo… divergen en bugs y features"). This PRD honors that: `graph_canvas` is that núcleo — the generic layer both AFD and the new tools sit on. The AFD editor + grader are production-verified, so we do not extract AFD onto the núcleo in these waves (that is the ADR-0002 migration, separate risk-managed work); we build the núcleo now and have grafo/arbol consume it. The result is transitory duplication with a convergence target, not a fork. Rejected: (a) in-place AFD refactor now (higher blast radius on verified code); (b) a permanent parallel canvas (what ADR-0002 rejected).

**Why structural/invariant grading, not compare-to-one-canonical.** Königsberg has no Euler circuit regardless of drawing; "construct a connected bipartite graph on 6 vertices" has many correct answers; an Euler walk is any valid traversal. Grading against a single canonical answer would reject correct variants. Invariant checks give correct partial credit and match `afd_grader`'s test-driven philosophy. Rejected: canonical-diff grading (wrong for open-ended constructions).

**Why generalize grader dispatch now.** We are adding grading for 2 tools × (mod + qtype). The `=== 'afd'` hardcode at `finish_attempt.php:81` does not scale; a slug-keyed grader lookup (mirroring `tool_registry`) is the minimal clean generalization, added additively so the AFD path keeps identical behavior (see Invariants).

## Main Flow

Student solving a grafo/arbol problem inside a `mod_graphitoubb` activity (the arbol wave mirrors this):

```
Teacher: edit_problem.php  ── selects tool=grafo, type=decision ──►  problem_repository.save()
   │                                                                   └─ graphitoubb_problem{tool,type,payload,payload_hash}
   ▼
Student opens activity  ──►  render editor
   │        └─ render_editor() returns {template:'mod_graphitoubb/graph_editor', context}
   │        └─ mustache: require(['mod_graphitoubb/graph_canvas'], Canvas.init(attemptid, instanceid, schemaversion, 'grafo'))
   │
   ├── 1. Student builds graph / answers on canvas
   │        └─ graph_canvas emits the answer envelope {answer_kind, …} as the opaque snapshot
   │           (build mode: {answer_kind:'graph', graph:{nodes,edges(with id),directed}})
   │        └─ Guard: max_nodes / max_edges from data-* bounds
   ├── 2. Autosave  ── Repository.saveSnapshot(payload, schemaversion) ──►  graphitoubb_snapshot.payload
   ├── 3. Student clicks Entregar  ── Repository.finishAttempt() ──►  finish_attempt.php
   │        └─ load problem (find_by_instance) → read $problem->tool
   │        └─ Guard: dispatch = grader_dispatch::for($problem->tool)   ← NEW generic lookup (replaces === 'afd')
   ├── 4. grader->grade($config, $snapshotjson)  (grafo_grader / arbol_grader)
   │        └─ construct  → count satisfied constraints / total
   │        └─ decision   → compare boolean answer (Euler yes/no)
   │        └─ traversal  → validate submitted walk is a legal Euler/Hamilton walk on the graph
   │        └─ returns the shared grader result array (see Core Concepts: graded,
   │           invalid, message, score, fraction, passed, items_total, items_correct, results[])
   ▼
5. submission_repository.save(...) → graphitoubb_submission{score,fraction,passed,grading_result}
   └─ (D15) recompute grade_cache for the attempt under attempts_policy (also fixes AFD)
```

Guards & timing: grading is synchronous inside the WS call, pure and DB-free in the grader itself (only the wrapper reads/writes). Invalid submissions (empty canvas, no root for a tree, malformed walk) return `invalid=true, fraction=0` rather than throwing — same contract as `afd_grader` (`afd_grader.php:77`).

## Core Concepts

### Shared snapshot shapes (opaque to the server, interpreted by graders)

```
grafo snapshot (canonical JSON)
├── nodes[]         -- [{id: "v0", label: "A"}, ...]
├── edges[]         -- [{id: "e0", from: "v0", to: "v1", weight?: number}, ...]
│                      every edge has a STABLE unique id; parallel edges (same from/to)
│                      are allowed and distinguished only by id (Königsberg multigraph)
├── directed (bool) -- default false; grafo problems set it per problem
└── schema_version (int, = 1)

arbol snapshot (canonical JSON)
├── nodes[]         -- [{id: "n0", label: "8", value: 8}, ...]  value = numeric key for BST ordering
├── edges[]         -- [{id: "e0", parent: "n0", child: "n1", side: "L" | "R"}, ...]   L/R explicit (D4)
├── root (id|null)  -- designated root node
└── schema_version (int, = 1)
```

### grafo submission payloads (the `$snapshotjson` the grader receives)

The student answer is NOT always a graph. The snapshot JSON is a tagged envelope
`{ answer_kind, ... }` so `grafo_grader` can read the right field per problem type:

```
construct  → { answer_kind: 'graph',    graph: {nodes[], edges[], directed} }   -- the built graph
decision   → { answer_kind: 'boolean',  value: true|false }                     -- yes/no answer
traversal  → { answer_kind: 'sequence', vertices: [nodeId,...], edges: [edgeId,...] } -- the walk
             `edges` (edge-id list) is REQUIRED — it is the authoritative walk. `vertices`
             is derived/redundant. Edge ids disambiguate parallel edges (Königsberg),
             which a vertex-only list cannot. See D10 / graph_canvas traversal control.
```

### grafo problem types

```
type: construct
  config: { prompt, directed (bool, pinned), constraints: { n_vertices?, n_edges?,
            degree_sequence?[], connected?, bipartite?, acyclic?, is_tree?, eulerian? } }
  grade: VALIDITY GATE FIRST (D18): if the submitted graph is empty (0 nodes) or
         unparseable, result is `invalid` (fraction 0) — not "some constraints
         satisfied". The gate is ONLY the empty/unparseable check; it is not tied to
         `n_vertices`. A non-empty graph passes the gate and is then scored:
         fraction = (#constraints satisfied) / (#constraints).
         `n_vertices` is a NORMAL graded constraint (it counts in the denominator): a
         5-node answer to a "6 vertices" prompt passes the gate but fails the
         `n_vertices` constraint (e.g. 2/3 if connected+bipartite hold). The gate exists
         only so an empty canvas cannot collect free credit from vacuously-true
         constraints (bipartite/acyclic on 0 nodes).
         NOTE: `directed` is a pinned problem setting, NOT a graded constraint; it is
         excluded from the denominator. Only the members of `constraints{}` count.

type: decision            -- e.g. Königsberg
  config: { prompt, given_graph{nodes,edges,directed}, question: 'has_euler_circuit'
            | 'has_euler_path' | 'has_hamiltonian_path' | 'is_connected' | 'is_bipartite' }
  grade: correct := graph_algorithms computes the true value of `question` on given_graph;
         fraction = (student value == correct) ? 1 : 0.
         The grader ALWAYS recomputes from given_graph (never trusts a stored answer).
         Authoring may store an `expected` hint for teacher preview only; it is not read
         at grade time.
  SEMANTICS (D21) — exact per-question rules the grader uses:
    • undirected Euler: circuit ⇔ connected (ignoring isolated vertices) ∧ every vertex
      even degree; path ⇔ connected ∧ exactly 0 or 2 odd-degree vertices.
    • directed Euler: circuit ⇔ every vertex has in=out degree ∧ the graph is
      strongly connected over non-zero-degree vertices; path ⇔ ≤1 vertex with
      out−in=+1, ≤1 with in−out=+1, all others in=out, ∧ weakly connected.
    • is_connected: undirected → connected; directed → **weakly** connected
      (the `question` label is fixed to weak; strong-connectivity is not an authorable
      question in this scope).
    • is_bipartite: 2-colorability (undirected only; rejected at authoring if directed).
    • has_hamiltonian_path: bounded search (see D21 bound); only offered when
      given_graph has ≤ MAX_VERTICES_HAMILTONIAN vertices (authoring enforces it).

type: traversal           -- find a walk
  config: { prompt, given_graph{...}, walk_kind: 'euler_circuit' | 'euler_path'
            | 'hamiltonian_path' | 'hamiltonian_circuit', start_vertex? }
  submission: { answer_kind:'sequence', edges:[edgeId,...], vertices?:[nodeId,...] }
              `edges` is authoritative (see submission payloads). Hamiltonian walk_kinds
              capped at MAX_VERTICES_HAMILTONIAN (D21).
  grade: 1 if the edge-id walk is a VALID walk of walk_kind on given_graph, else 0.
         Validity per walk_kind: euler_* uses every edge id exactly once (circuit returns
         to start, path may have distinct endpoints); hamiltonian_* visits every vertex
         exactly once (circuit closes to start). `start_vertex` (if set) must be the first
         vertex. Any valid walk passes — not compared to a canonical answer.
```

### arbol problem types

```
type: bst_build
  config: { prompt, insertions[]: number[] }   -- e.g. [8,3,10,1,6]
  submission: arbol snapshot the student built on the L/R canvas
  grade: fraction = (#nodes placed correctly vs canonical BST) / (#insertions distinct)

type: traversal_answer
  config: { prompt, given_tree{nodes,edges,root}, order: 'pre'|'in'|'post'|'level' }
  submission: { answer_kind:'sequence', values:[number,...] }   (ordered value sequence)
  grade: fraction = longest-common-prefix ratio vs canonical order (D11):
         (# leading values that match the canonical order, in order) / (canonical length).
         A first-position mistake ⇒ fraction 0 (this is intended; see AC5).

type: reconstruct
  config: { prompt, pair: 'pre_in' | 'post_in', a[]: number[], b[]: number[] }
          -- exactly one of the two uniquely-reconstructible pairs; a = pre|post, b = inorder.
          -- pre+post is NOT allowed (does not yield a unique binary tree).
          -- ALL values must be distinct (D13) — required for unique reconstruction.
  submission: { answer_kind:'tree', tree: arbol snapshot the student built }
  grade: fraction = (#nodes matching the unique reconstructed tree by canonical position) / total (D12)
```

### arbol submission payloads

```
bst_build         → { answer_kind:'tree',     tree: {nodes[], edges[], root} }
traversal_answer  → { answer_kind:'sequence', values: [number,...] }
reconstruct       → { answer_kind:'tree',     tree: {nodes[], edges[], root} }
```

### Grader contract (shared)

All graders implement one interface and return the shared result array (D5, D14):

```php
interface grader_interface {
    public function grade(array $problem, ?string $submissionjson): array;
}
```

Return array (generic count pair; `words_*`/`cells_*` stay as tool UI labels only):

```
{ graded: bool, invalid: bool, message: ?string, score: float, fraction: float,
  passed: bool (fraction >= 0.6), items_total: int, items_correct: int,
  results: [ {check: string, expected: mixed, got: mixed, correct: bool}, ... ] }
```

`grafo_grader`/`arbol_grader` implement this natively. Existing `afd_grader` and the
truth_table `grader` are wrapped in thin adapters (internals untouched → I1/I3);
the adapter maps the stored problem to the grader's private input and normalizes the
output to the array above.

### graph_canvas API contract (D20)

The single AMD module both new tools mount. Contract an implementer can build against:

**DOM host (rendered by the mustache template `graph_editor.mustache`):**
```
<div class="mod-graphitoubb-graph"
     id="graphitoubb-graph-{{instanceid}}"
     data-attemptid   data-instanceid   data-schemaversion
     data-tool        = "grafo" | "arbol"
     data-mode        = "build" | "given" | "authoring"
     data-type        = problem type (construct | decision | traversal | bst_build | …)
     data-directed    = "0" | "1"                         (grafo only)
     data-max-nodes   data-max-edges  data-max-label      (numeric bounds, D9)
     data-given       = JSON of the read-only given_graph/given_tree (given mode; else "")
     data-snapshot    = JSON of the last saved answer envelope to restore ("" if none)>
  <div class="…-toolbar" role="toolbar"> … mode buttons carry data-mode … </div>
  <div class="…-canvas"></div>                            <!-- Cytoscape mounts here -->
  <div class="…-answer"></div>                            <!-- answer control host (given mode) -->
</div>
```

**Init:** `Canvas.init(attemptid, instanceid, schemaversion, toolslug)` reads the rest
from the `data-*` above (no other args). Idempotent per host id.

**Modes (D16):**
- `build` — editable canvas; the answer IS the drawn structure.
- `given` — paints `data-given` read-only (no add/delete/drag of the given structure); renders an answer control in `.…-answer` per `data-type`: boolean radios for decision; **click-edges-in-order on the canvas** for grafo traversal (builds the edge-id list, D10); a numeric sequence field for arbol traversal_answer.
- `authoring` — editable canvas used inside `edit_problem.php`; on change it exposes the drawn structure to the surrounding form (see authoring output below).

**Snapshot in/out (the answer envelope, §"submission payloads"):**
- OUT: on every significant change the module builds the tagged `{answer_kind,…}` envelope (from the canvas in build mode, from the answer control in given mode) and hands it to the existing snapshot path as a JSON string — `snapshot_controller.onchange(attemptid, envelopeJson, schemaversion)` → `repository.saveSnapshot`.
- IN (restore): on init, if `data-snapshot` is non-empty, the module rehydrates it (redraws the graph/tree for `answer_kind:'graph'|'tree'`, or repopulates the control for `'boolean'|'sequence'`).

**Events (CustomEvent on the host, for the toolbar/answer wiring):**
`graphitoubb:modechange` (payload `{mode}`), `graphitoubb:change` (payload `{envelope}` — significant answer change), `graphitoubb:snapshot-status` (saved/saving/error). Same event names AFD's `editor_toolbar.js`/`snapshot_controller.js` already emit, so the shared infra is reused unchanged.

**Authoring output:** in `authoring` mode the module writes the current structure JSON into a hidden `<input name="given_graph">` (grafo) / `<input name="given_tree">` (arbol) on `graphitoubb:change`, so `edit_problem.php` persists it into `graphitoubb_problem.payload` on form submit (no autosave, no WS).

**Canonical JSON rules (shared by snapshots and payload hashing):** object keys serialized in a fixed order (as listed in the shapes below); `nodes`/`edges` arrays sorted by `id`; node/edge `id` generated stably by the module (`v0,v1,…` / `e0,e1,…`, never reused within a canvas); omitted optional fields (`weight`) dropped, not null; numbers are JSON numbers. This makes `payload_hash` (SHA-256) reproducible.

## Detailed Specification

### Wave A — Foundation + grafo

**A1. graph_canvas foundation (new JS module).** New `mod/graphitoubb/amd/src/graph_canvas.js` + `graph_canvas_factory.js`. `graph_canvas_factory.js` **copies** (does not edit) the generic Cytoscape config from `cytoscape_factory.js` and adds two parameterized seams the AFD factory hardcodes — the element-mapping function and the style selectors — so `cytoscape_factory.js` stays byte-for-byte unchanged (I2). Likewise `graph_canvas.js` copies the generic behaviors from `afd_editor.js` (modals, add/delete node, drag, zoom, tidy/relayout, undo/redo, autosave wiring) into new code; `afd_editor.js` is not touched. It supports **three modes** (D16):
- **build** — editable canvas; the student draws the answer structure (construct / bst_build / reconstruct).
- **given+answer** — renders the teacher's `given_graph`/`given_tree` **read-only** in the canvas (zoom, edge highlight available) plus an external answer control (yes/no or sequence box); the student answers *about* the structure without editing it (decision / traversal / traversal_answer).
- **authoring** — editable canvas embedded in `edit_problem.php` for the teacher to draw the "given" structure.

**Answer-as-snapshot:** in every mode the editor emits the tagged `{answer_kind,…}` envelope as the snapshot, whether it came from the canvas or a form control — reusing the existing snapshot path (`repository.saveSnapshot` → `graphitoubb_snapshot.payload` → `finish_attempt` grades the latest). No new storage.

**Mobile responsive (D19).** `graph_canvas` and every grafo/arbol surface must be usable on phone/tablet: the canvas fills the available width and supports touch (pan/zoom, tap-to-add, tap-to-connect), the toolbar collapses/wraps on narrow viewports, and the given+answer controls (radios, sequence input) stack vertically below the canvas instead of beside it. Reuse the responsive patterns already shipped for AFD (F1–F3). Target breakpoints: ≥ desktop (side-by-side), ≤ ~600px (stacked, larger touch targets). Reuses **without modification**: `repository.js` (opaque payload); `editor_toolbar.js` — its mode FSM is data-driven (buttons carry `data-mode`), so a new tool supplies a new button set in its own template with **no JS change** (no shared-file edit, so AFD's toolbar is unaffected). One shared file DOES change safely: `snapshot_controller.js` — generalize `isSignificant` to also diff `nodes/edges` while keeping the existing AFD `states/transitions/alphabet` path intact (additive branch, covered by AC6/I1). Init contract mirrors AFD:
```js
require(['mod_graphitoubb/graph_canvas'], function(Canvas) {
    Canvas.init(attemptid, instanceid, schemaversion, toolslug); // toolslug ∈ {'grafo','arbol'}
});
```

**A2. grafo tool (PHP).** `local/graphitoubb/classes/tools/grafo/grafo_tool.php` implementing `tool_interface`; `descriptor()` → `new tool_descriptor('grafo', 'Graph', '1.0.0', ['edit','snapshot'])`. `serialize()` normalizes `{nodes,edges,directed}`; `validate()` enforces bounds (max nodes/edges/label length). Domain: `domain/graph.php`, `domain/graph_algorithms.php` covering every predicate the grafo types need, with directed/undirected variants where relevant (D21): weak & strong connectivity (BFS/DFS), degree (undirected) and in/out-degree (directed), degree-sequence match (sorted multiset of undirected degrees, counting parallel edges with multiplicity; self-loops count 2), edge count, cycle detection / acyclic, is_tree (connected ∧ acyclic ∧ |E|=|V|−1), Euler existence (undirected: even-degree+connected → circuit, 0-or-2-odd → path; directed: in=out+strongly-connected → circuit, ±1 imbalance+weakly-connected → path), bipartite 2-coloring (undirected), bounded Hamiltonian path/circuit search (backtracking, only invoked for ≤ MAX_VERTICES_HAMILTONIAN vertices — D21), and walk validation over the **edge-id list** (each edge id used exactly once for Euler; each vertex once for Hamilton; endpoints/closure checked per walk_kind). `grader/grafo_grader.php` with `grade(array $config, ?string $snapshotjson): array`, type-dispatched (construct/decision/traversal), pure and DB-free.

**A3. Register + dispatch.** Add `use` + `$registry->register(new grafo_tool());` in `bootstrap.php:52`. Introduce `local/graphitoubb/classes/grader_interface.php` (single `grade(array $problem, ?string $submissionjson): array`) and `local/graphitoubb/classes/grader_dispatch.php` mapping slug → `grader_interface` instance. `grafo_grader` implements it natively; `afd_grader` gets a thin adapter (`tools/afd/grader/afd_grader_adapter.php`) that maps the problem to `$config` and returns afd's array unchanged. Refactor `finish_attempt.php:81` to `grader_dispatch::for($problem->tool)->grade($problem, $snapshotjson)` (AFD's existing output values unchanged; optional `items_*` may be added — I1). truth_table adapter is added later with the qtype refactor (D8), not now.

**A4. Authoring UI.** Extend `edit_problem.php`: add `grafo` to `<select name="tool">`; add a per-type authoring branch (prompt, constraints or given-graph editor, expected answer). Given-graph authoring reuses the graph_canvas in read/teacher mode.

**A5. grafo mod presets.** Add curated grafo templates to `preset_catalog` (Königsberg, small Euler/Hamilton, degree-sequence constructions) so the teacher can preload `edit_problem.php` from a "plantilla". (Question Bank XML seeding is Wave C — it is qtype-scoped.)

### Wave B — arbol (activity)

**B1. arbol L/R canvas mode.** Extend graph_canvas with an arbol mode: parent→child edges carry `side: L|R`; adding a child prompts/toggles side; enforce ≤2 children per node and one-node-per-side. Snapshot emits the arbol shape with `root`. Reuses the three-mode support (D16): build (bst_build/reconstruct), given+answer (traversal_answer), authoring.

**B2. arbol tool (PHP).** `tools/arbol/arbol_tool.php` + `domain/bst.php` (canonical BST from insertions), `domain/tree_traversal.php` (pre/in/post/level), `domain/tree_reconstruct.php` (pre_in / post_in → unique tree, D13). `grader/arbol_grader.php` implementing `grader_interface`, type-dispatched (bst_build/traversal_answer/reconstruct).

**B3. arbol register + authoring.** Register `arbol_tool` in `bootstrap.php`; add `arbol` to `edit_problem.php` (`<select>` + per-type authoring); add curated arbol mod presets to `preset_catalog`. (Grading already flows through the `grader_dispatch`/`finish_attempt` built in Wave A — no new dispatch work.)

### Wave C — qtype (Question Bank) for grafo + arbol

This wave builds the **first working canvas qtype** (D17) — the qtype "host" that ADR-0002 anticipated, delivered via grafo/arbol rather than AFD. Built once, shared by both tools.

**C1. qtype host (new infra).** New `qtype/graphitoubb/amd/src/` host that embeds `graph_canvas` inside the question engine: writes the answer envelope into a hidden `<input>` (no own submit), renders read-only for quiz review. This is the qtype counterpart of the mod host (ADR-0002).

**C2. Tool-aware qtype.** Make `qtype/graphitoubb/question.php` route by the question's `tool` (today hardcodes `truth_table` at `:42,204`): grade via `grader_dispatch` (add the truth_table adapter here, D14), pick the graph_canvas host for grafo/arbol. truth_table path stays behavior-identical (I3).

**C3. Question Bank seeding ("preguntas tipo").** Seed grafo/arbol Question Bank XML via the existing `cli/export_questions_xml.php` path, giving ready-made reusable questions for quizzes.

## Invariants — Must Not Break

- **I1 — AFD grading values unchanged.** After `finish_attempt.php` moves to `grader_dispatch`, an `afd` problem must produce the **same values** for every key it returns today (`fraction`, `passed`, `results`, `words_total`, `words_correct`, `invalid`; threshold 0.6, `afd_grader.php:54`). The adapter MAY add new *optional* keys (`items_total`/`items_correct`, aliasing the word counts) — "unchanged" means no existing key's value changes, not that no key is added. Wrap, do not rewrite.
- **I2 — AFD editor untouched.** `afd_editor.js`, `cytoscape_factory.js`, `afd_adapter`, `afd_simulator.js`, `alphabet_ui.js` are not modified in either wave (D3). The AFD snapshot shape `{states,transitions,alphabet,start,finals}` stays as-is.
- **I3 — truth_table path unchanged.** `submit.php:109` and the truth_table qtype grading must keep working; the Wave C qtype changes (C2) must remain backward-compatible for `tool='truth_table'` questions.
- **I4 — Repository/WS contract.** `save_snapshot`/`get_latest_snapshot`/`finish_attempt` treat `payload` as an opaque string (`repository.js`); no schema change to `graphitoubb_snapshot`/`graphitoubb_submission` is required.
- **I5 — Problem table shape.** `graphitoubb_problem{tool,type,payload,payload_hash}` already accommodates new slugs; no migration needed for new tools.

## Interaction with Existing Systems

- **tool_registry / bootstrap.** New tools register additively at `bootstrap.php:52`; last-registration-wins means no collision with afd/truth_table.
- **grade_cache_service (behavior change — D15).** `finish_attempt` gains a `grade_cache_service.recompute_for_attempt($attemptid, $policy)` call after `submission_repository.save`, for afd/grafo/arbol alike. Consequence: **AFD attempts start populating `graphitoubb_grade_cache`**, which `get_problem_stats.php` and `get_panel_per_student.php` read — fixing incomplete/zero AFD aggregates in teacher panels. This is an intended fix, not covered by I1 (which guards grading output, not the aggregate). No schema change; `recompute_for_attempt` already exists and is used by `submit.php`.
- **qtype question engine.** Making the qtype tool-aware (Wave C, C2) is the first multi-tool use of `qtype_graphitoubb`; the truth_table branch must be preserved unchanged (I3).

## Edge Cases

| Scenario | Behavior |
|---|---|
| Student submits empty canvas (grafo/arbol) | `invalid=true, fraction=0, message='empty'` — not "fails all checks" (parity with `afd_grader` no_start; D18 validity gate) |
| grafo `construct` submission passes the gate but a constraint is vacuously true | Only constraints on a gate-passing graph are scored (D18), so vacuous credit on an empty/degenerate graph is impossible |
| arbol tree with a cycle or a node with 3 children | Grader marks `invalid` (not a valid tree); UI prevents adding a 2nd L/R child where occupied |
| grafo `traversal`: submitted walk reuses an edge in an Euler circuit | Invalid walk → fraction 0 (Euler walks use each edge exactly once) |
| grafo `decision` on Königsberg | 4 vertices all odd degree → `has_euler_circuit=false` and `has_euler_path=false`; grader computes, never trusts a stored drawing |
| BST insertions contain duplicate values | Canonical BST **ignores duplicate insertions** (a repeated value is a no-op); `items_total` = count of distinct inserted values. No config toggle |
| `reconstruct` traversals with repeated values | Rejected at authoring: `reconstruct` requires **all node values distinct** (D13) — repeated values break unique reconstruction |
| `reconstruct` given inconsistent traversals (no valid tree) | Authoring-time validation rejects the problem; at grade time, treat as `invalid` config → teacher-facing error |
| `reconstruct` authored as `pre+post` | Rejected at authoring (D13): only `pre_in`/`post_in` yield a unique tree |
| Directed vs undirected mismatch (student toggles directed) | grafo problems pin `directed` in config; student canvas honors it, cannot toggle |
| Multigraph parallel edges (Königsberg bridges) | Supported — Cytoscape renders parallel edges; grader counts multiplicity in degree/Euler checks |
| Unknown tool slug reaches `grader_dispatch` | Returns `graded=false`/error result; never fatals the WS (mirrors truth_table `default` match arm) |

## Phases

Three waves (D17). All mod-activity value ships in Waves A–B before the new qtype-host infra is built in Wave C; each wave is independently shippable and verifiable.

**Status: ALL WAVES IMPLEMENTED & VERIFIED (2026-07-06).** mod_graphitoubb→2026070501 (0.8.0-alpha), local_graphitoubb→2026070501 (0.6.0-alpha), qtype_graphitoubb→2026070600 (0.5.0-alpha). Tests: local 280/280, mod 93/93, qtype 10 OK. Playwright: all 9 ACs verified end-to-end (0 console errors).

**Wave A — Foundation + grafo activity** (mod only; independently shippable)
- [x] `graph_canvas.js`/`graph_canvas_factory.js` new módulo/núcleo — three modes build/given+answer/authoring (D16), mobile responsive + touch (D19), no AFD edits
- [x] `grafo_tool` + `grafo_grader` + graph domain/algorithms (construct/decision/traversal)
- [x] `grader_interface` + `grader_dispatch` + AFD adapter + refactor `finish_attempt.php` (AFD output identical, I1)
- [x] `finish_attempt` recomputes `grade_cache` (D15 — also fixes AFD aggregate)
- [x] `edit_problem.php` grafo authoring branch; register grafo in `bootstrap.php`
- [x] grafo mod presets in `preset_catalog`

**Wave B — arbol activity** (mod only)
- [x] graph_canvas arbol L/R mode (≤2 children, side toggle, root)
- [x] `arbol_tool` + `arbol_grader` + BST/traversal/reconstruct domain
- [x] `edit_problem.php` arbol authoring; register in bootstrap; arbol mod presets

**Wave C — qtype (Question Bank) for grafo + arbol** (the new canvas-qtype host infra, built once)
- [x] qtype host: embed `graph_canvas` in the question engine (hidden input, read-only review) — first working canvas qtype (D17)
- [x] Tool-aware `qtype/graphitoubb/question.php` routing via `grader_dispatch` (truth_table kept on its existing path, identical, I3)
- [x] grafo + arbol Question Bank XML seeding ("preguntas tipo")

## Key Files to Touch (Wave A)

| Area | File(s) | Change |
|---|---|---|
| Foundation JS | `mod/graphitoubb/amd/src/graph_canvas.js` (new), `graph_canvas_factory.js` (new) | Generic node/edge canvas + init(attemptid,instanceid,schemaversion,toolslug) |
| Shared JS (reuse) | `amd/src/snapshot_controller.js` | Generalize `isSignificant` to diff `nodes/edges` (keep AFD triad path) |
| grafo tool | `local/graphitoubb/classes/tools/grafo/grafo_tool.php` (new) | `tool_interface` impl, descriptor slug `grafo` |
| grafo domain | `.../tools/grafo/domain/graph.php`, `graph_algorithms.php` (new) | Connectivity, degree, Euler/Hamilton existence, bipartite, walk validation |
| grafo grader | `.../tools/grafo/grader/grafo_grader.php` (new) | implements `grader_interface`, type-dispatch, shared result array |
| Grader interface | `local/graphitoubb/classes/grader_interface.php` (new) | `grade(array $problem, ?string $submissionjson): array` |
| Dispatch | `local/graphitoubb/classes/grader_dispatch.php` (new) | slug → `grader_interface` instance |
| AFD adapter | `local/graphitoubb/classes/tools/afd/grader/afd_grader_adapter.php` (new) | Wraps `afd_grader` (internals untouched); maps problem→config |
| Dispatch wire | `mod/graphitoubb/classes/external/finish_attempt.php` | Replace `=== 'afd'` with `grader_dispatch::for($problem->tool)->grade(...)`; add `grade_cache` recompute (D15); add generic `items_*` to `execute_returns()` (VALUE_OPTIONAL, keep `words_*`) |
| Register | `local/graphitoubb/classes/bootstrap.php` | `use` + `$registry->register(new grafo_tool())` |
| Authoring | `mod/graphitoubb/edit_problem.php` | Add `grafo` option + per-type authoring branch |
| Editor template | `mod/graphitoubb/templates/graph_editor.mustache` (new) | Canvas div + `require([...graph_canvas]).init(...,'grafo')` |
| Versions | `mod/graphitoubb/version.php`, `local/graphitoubb/version.php` | Bump; keep `mod` depends `local` |

## Acceptance Criteria

Each criterion is tagged with the wave that must satisfy it. Wave A ships and is verifiable on AC1–3, AC6, AC8–9 alone.

1. **[Wave A] grafo construct — partial credit** — setup: `type=construct`, constraints `{n_vertices:6, connected:true, bipartite:true}` · action: student submits a 6-vertex connected but non-bipartite graph · expect: `fraction = 2/3 ≈ 0.667`, `passed=true`, `results` names the failed `bipartite` check · lives in: `local/graphitoubb/tests/tools/grafo/grafo_grader_test.php`.
2. **[Wave A] grafo decision — Königsberg negative** — setup: `type=decision`, given the 4-vertex 7-bridge multigraph, `question='has_euler_circuit'` (no stored answer trusted) · action: student answers "yes" · expect: grader recomputes `false` from the graph ⇒ `fraction=0, passed=false`; student answers "no" ⇒ `fraction=1` · lives in: same suite. *(negative case)*
3. **[Wave A] grafo traversal — any valid Euler walk passes** — setup: `type=traversal`, a graph with an Euler circuit, `walk_kind='euler_circuit'` · action: student submits an `edges:[edgeId,...]` list that is a valid circuit (different from any single canonical one) · expect: `fraction=1`; an edge-list that reuses an edge id or omits one → `fraction=0` · lives in: same suite. *(negative case)*
4. **[Wave B] arbol bst_build** — setup: `type=bst_build`, `insertions=[8,3,10,1,6]` · action: student submits the correct BST via L/R canvas · expect: `fraction=1, passed=true`; a tree with 1 misplaced node → `fraction=4/5` (per-node, D12) · lives in: `local/graphitoubb/tests/tools/arbol/arbol_grader_test.php`.
5. **[Wave B] arbol traversal_answer — LCP ratio** — setup: given tree, `order='in'`, canonical in-order `[1,3,6,8,10]` · action: student submits `[1,3,6,8,10]` → `fraction=1`; submits `[1,3,8,6,10]` (matches first 2, then diverges) → LCP=2 → `fraction=2/5=0.4`; submits `[8,3,1,6,10]` (first value wrong) → LCP=0 → `fraction=0` · lives in: same suite. *(partial + zero cases)*
6. **[Wave A] AFD grading output unchanged + cache now populated** — setup: an existing `afd` problem + snapshot · action: `finish_attempt` through the new `grader_dispatch` · expect: `fraction/passed/results/words_*` identical to pre-refactor `afd_grader` output (I1); the response MAY carry additive `items_*` keys (D14); **and** a `graphitoubb_grade_cache` row now exists for the attempt under the instance's `attempts_policy` (D15 — was absent before) · lives in: `mod/graphitoubb/tests/external/finish_attempt_test.php`. *(invariant guard I1 + fix guard D15)*
7. **[Wave C] qtype tool routing (regression + new)** — setup: a `truth_table` question and a `grafo` question in the Question Bank · action: grade each via the tool-aware qtype · expect: truth_table result identical to today; grafo routed to `grafo_grader` · lives in: `qtype/graphitoubb/tests/question_test.php`. *(I3)*
8. **[Wave A] Browser end-to-end (grafo)** — setup: teacher authors a Königsberg decision problem from a preset · action: student opens activity, answers, clicks Entregar · expect: graded result shown, `graphitoubb_submission` row written, `grade_cache` recomputed, 0 console errors · verified with Playwright.
9. **[Wave A] Mobile responsive (D19)** — setup: a grafo construct and a grafo decision problem · action: load each editor at a 375×667 viewport (phone) · expect: canvas fills width and responds to touch pan/zoom + tap-to-add, toolbar wraps without overflow, answer controls stack below the canvas, no horizontal scroll, tap targets ≥ 40px · verified with Playwright at desktop + 375px widths.

## Resolved Decisions

| # | Decision | Why |
|---|---|---|
| D1 | One PRD covering a shared foundation + two tools (`grafo`, `arbol`), shipped in three waves (grafo mod → arbol mod → shared qtype) | The generalize-the-canvas decision is shared and must be decided once; two PRDs would duplicate it. User-confirmed. Wave count updated by D17 |
| D2 | Exercise scope = "núcleo + hallar recorrido": grafo {construct, decision, traversal}, arbol {bst_build, traversal_answer, reconstruct} | User choice — covers Königsberg (decision), Euler/Hamilton walks (traversal), BST + tree reconstruction; fuller RA2/RA4 coverage |
| D3 | `graph_canvas` IS the reusable núcleo of ADR-0002 (generic Cytoscape canvas, toolbar/mode-FSM, undo/redo, zoom, modals, autosave). Build it now for grafo/arbol; `afd_editor.js` is not modified in these waves but AFD later **converges** onto the same núcleo (ADR-0002 extraction, separate risk-managed work). Duplication is transitory, not a permanent fork | AFD is production-verified; touching it now would force full re-verification. But a permanently separate canvas is exactly what ADR-0002 rejected ("editor aparte… duplica el lienzo, divergen en bugs"). Convergence honors ADR-0002: one núcleo, tools layer their extras on top. User-confirmed 2026-07-05 |
| D4 | arbol uses the Cytoscape canvas with explicit L/R child designation on parent→child edges | Keeps the "arbol reuses the editor" thesis; a free canvas cannot distinguish left/right child. User-confirmed |
| D5 | grafo/arbol grade through the **`finish_attempt`** endpoint (canvas/snapshot path, like AFD) via generic `grader_dispatch` (slug→grader) replacing the `=== 'afd'` hardcode; truth_table stays on `submit.php` | Scales grading to 2 new tools without touching truth_table's answer-model path (I3). grafo/arbol answers are snapshots, not truth_table payloads |
| D16 | `graph_canvas` supports three modes — build (student draws), given+answer (read-only teacher structure in-canvas + external answer control), authoring (teacher draws) — and always emits the `{answer_kind,…}` envelope as the snapshot | decision/traversal answers are *about* a given structure, not a drawn graph; rendering it live in-canvas (not a static image) keeps zoom/edge-highlight for traversal marking. One storage path (snapshot) for all answer kinds. User-confirmed 2026-07-05 |
| D15 | `finish_attempt` recomputes `grade_cache` for every graded attempt (afd, grafo, arbol) under `attempts_policy`. This deliberately **fixes a latent AFD bug**: today `finish_attempt` skips recompute, so AFD attempts never populate `grade_cache`, which `get_problem_stats.php`/`get_panel_per_student.php` read — AFD aggregates show incomplete/zero in teacher panels | Single canonical grade trigger for canvas tools; closes the asymmetry vs `submit.php`. Not an I1 violation: grading output values are unchanged; only the (previously missing) aggregate is now written. Documented as an intentional fix. User-confirmed 2026-07-05 |
| D6 | Delivery scope = mod activity **+** qtype (Question Bank) **+** preset catalogue for both tools | User choice — syllabus evaluates via platform quizzes; presets give ready-made exercises |
| D19 | grafo/arbol editors must be **mobile responsive** (touch canvas, wrapping toolbar, stacked answer controls ≤ ~600px), reusing AFD's F1–F3 responsive patterns | The platform is used on phones/tablets; the AFD editor already set the responsive bar, and the new tools must match it, not regress it. User-requested 2026-07-05 |
| D18 | grafo `construct` grading = validity gate first (**empty/unparseable only** → `invalid`, fraction 0), then partial credit over ALL constraints of a gate-passing graph. `n_vertices` is a normal graded constraint, NOT the gate | Prevents an empty canvas from collecting vacuously-true credit (bipartite/acyclic on 0 nodes), while still scoring a wrong-but-non-empty answer partially. Tying the gate to `n_vertices` (earlier wording) was ambiguous for "5 vs 6 vertices" — advisor-flagged; resolved. User-confirmed 2026-07-05 |
| D17 | qtype is in scope but sequenced as the **last wave (Wave C)**, building a shared canvas-qtype host once for grafo+arbol; it becomes the **first working canvas qtype** (the AFD qtype is still a stub — ADR-0002 anticipated this "first real qtype", now delivered via grafo/arbol). All mod value (Waves A–B) ships first | Isolates the new/unbuilt qtype-host infra from the lower-risk mod work; avoids building the host twice or blocking the mod on it. AFD adopts the same host later (convergence, Q1/D3). User-confirmed 2026-07-05 |
| D7 | Grading is structural/invariant-based with fraction partial credit, `PASS_THRESHOLD = 0.6` | Open-ended constructions/walks have many correct answers; canonical-diff would reject valid variants. Mirrors `afd_grader` |
| D8 | Make `qtype_graphitoubb` tool-aware (route by question `tool` via `grader_dispatch`) rather than adding separate qtype plugins | Single qtype already exists; multi-tool routing reuses the question engine and keeps truth_table working (I3). AFD qtype stub stays out of scope |
| D9 | Phase-1 default bounds (revisable): grafo `MAX_VERTICES=20`, `MAX_EDGES=40`, `MAX_LABEL=12`, `MAX_VERTICES_HAMILTONIAN=10` (cap for Hamiltonian-question graphs, since exact search is exponential); arbol `MAX_NODES=31` (depth ≤ ~5), `MAX_LABEL=6` | Concrete constants so validators + `data-*` limits are implementable now; sized for pedagogical exercises. The Hamiltonian sub-cap keeps backtracking tractable. Confirm/adjust via OQ-5 |
| D10 | `traversal` answer input = **click edges on the given canvas in order** (produces the authoritative edge-id list); a text vertex-sequence field is a fallback only for simple graphs. Superseded the earlier "text-only" default because edge-marking is needed for multigraphs (Königsberg-style) and the given canvas is already rendered live (D16) | Vertex-only input cannot disambiguate parallel edges; D16 already commits to a live in-canvas given graph with edge highlight, so edge-marking is low marginal cost. Resolves OQ-1 |
| D11 | `traversal_answer` scoring = **longest-common-prefix ratio** (correct leading values / total) | Penalizes the first mistake proportionally, rewards partial recall; matches AC5. Resolves OQ-3 by default |
| D12 | `bst_build` / `reconstruct` grading = **per-node** partial credit, node correspondence by BST/reconstruction position (value at its canonical slot) | Kinder than all-or-nothing; matches AC4's `4/5`. Resolves OQ-2 by default |
| D13 | `reconstruct` accepts only `pre_in` or `post_in` pairs (never `pre+post`), and requires **all node values distinct** | Only these pairs yield a unique binary tree, and uniqueness holds only with distinct values; authoring validates both. Removes the under-determined/ambiguous cases |
| D14 | Common grading contract = `grader_interface { grade(array $problem, ?string $submissionjson): array }` returning `{…, items_total, items_correct, results[]}`. Existing afd/truth_table graders wrapped in adapters; new tools implement natively | Array matches what `submission_repository->save` already consumes and what `afd_grader` already returns; adapters keep verified graders untouched (I1/I3). `grading_result` value object stays truth_table-internal. Generic `items_*` count pair; `words_*`/`cells_*` are UI labels. User-confirmed 2026-07-05 |
| D20 | `graph_canvas` has an explicit API contract (DOM `data-*` host, `Canvas.init` signature, three modes, answer-envelope in/out, CustomEvent names reused from AFD's toolbar/snapshot infra, authoring hidden-input output, canonical-JSON/id rules) — see Core Concepts | Advisor review (Codex) flagged the module as too vague to implement cold; pinning the contract is what makes Wave A self-contained. Reuses AFD's existing event names so shared infra is untouched (I2). Added 2026-07-05 |
| D21 | Precise grafo algorithm semantics: directed vs undirected Euler (degree vs in/out-degree + strong/weak connectivity), `is_connected`=weak for directed, `is_bipartite` undirected-only, `degree_sequence`=sorted undirected multiset with multiplicity; Hamiltonian questions capped at `MAX_VERTICES_HAMILTONIAN=10` and enforced at authoring; walks validated over the edge-id list | Advisor review flagged directed Euler as unspecified (undirected rule only), `is_connected` ambiguous, and exact Hamiltonian on 20 vertices as exponential. These rules make the graders correct and tractable. Added 2026-07-05 |

## Open Questions

None block Wave A: OQ-1–3 are resolved by default decisions; OQ-4 is a Wave-C scope choice; OQ-5 is a revisable default; OQ-6–7 are architectural-risk mitigations to validate during design, not blockers.

1. **OQ-1** ✅ Resolved 2026-07-05 → D10 (Phase-1 = text sequence; canvas click-to-mark deferred). Reopen only if canvas-marking is wanted in Wave A.
2. **OQ-2** ✅ Resolved 2026-07-05 → D12 (per-node by canonical position). Reopen if all-or-nothing is preferred.
3. **OQ-3** ✅ Resolved 2026-07-05 → D11 (longest-common-prefix ratio). Reopen if exact-match or edit-distance is preferred.
4. **OQ-4** — In Wave C, should the qtype host also un-stub the AFD qtype (AFD converges onto the shared host), or strictly leave AFD alone for now? Leaving it alone is safer but keeps AFD activity-only. · affects Wave C scope · owner: user. **Open.**
5. **OQ-5** — Confirm/adjust the Phase-1 default bounds in D9 (grafo 20 vertices / 40 edges; arbol depth ≤ ~5; Hamiltonian ≤ 10). · affects validators + `data-*` limits · owner: user. **Open (non-blocking; D9 defaults apply until changed).**
6. **OQ-6** *(architectural risk — advisor-flagged)* — The `graph_canvas` extension API (element-mapping + style seams + mode/answer hooks) is designed for grafo/arbol but claimed as AFD's future núcleo (D3). Before finalizing the API in Wave A, validate it on paper against AFD's real needs (edge labels/symbols, start/final marking, simulator trace hooks, alphabet UI) so AFD can converge later without a second redesign. · affects graph_canvas API shape · owner: eng. **Open — mitigation, not blocker.**
7. **OQ-7** *(architectural risk — advisor-flagged)* — Wave C's qtype host (C1) is the first canvas qtype. The mod host is autosave/WS-driven; the qtype host is hidden-input/no-autosave/review-render. Confirm the Wave-A `graph_canvas` snapshot in/out API cleanly supports the qtype lifecycle (no autosave, serialize-on-change to hidden input, read-only review) so Wave C does not force a Wave-A API change. · affects graph_canvas init/output API · owner: eng. **Open — validate before Wave C.**

## Out of Scope

- Migrating the existing `afd` editor onto `graph_canvas` (the ADR-0002 núcleo+host extraction) — deferred to separate risk-managed work; convergence is the target, not part of these waves (D3).
- Un-stubbing the AFD qtype (unless OQ-4 says otherwise).
- Weighted-graph algorithms beyond what the listed grafo types need (e.g. shortest-path/Dijkstra, MST) — not in the syllabus rows this PRD targets.
- Other uncovered RAs (recurrence relations RA3, binary relations, formal grammars, boolean-circuit simplification) — separate future tools.
- Pushing grades to the Moodle gradebook (`lib.php` still has no `FEATURE_GRADE_HAS_GRADE`; unchanged by this PRD).
