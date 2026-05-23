# Design — Iter 1 truth_table

## AFD pattern map (replicación end-to-end)

Pattern reusable de POC AFD. Marca **COPY** (re-uso mecánico) vs **NEW** (escribir desde cero).

### local_graphitoubb (núcleo)
- `classes/tool_interface.php` — **COPY (sin cambio)**
- `classes/tool_descriptor.php`, `tool_registry.php`, `validation_result.php` — **COPY**
- `classes/bootstrap.php` — **MODIFY**: agregar `$registry->register(new truth_table_tool());`
- `classes/tools/truth_table/truth_table_tool.php` — **NEW**: implementa `tool_interface`, descriptor `('truth_table','Truth Table','1.0.0',['edit','evaluate','snapshot'])`
- `classes/tools/truth_table/domain/` — **NEW**:
  - `formula_ast.php` (abstract) + `var_node.php`, `const_node.php`, `not_node.php`, `and_node.php`, `or_node.php`, `xor_node.php`, `impl_node.php`, `iff_node.php`
  - `lexer.php` — tokenizer + normalize ASCII synonyms
  - `parser.php` — recursive descent, errores posicionales es
  - `canonicalizer.php` — serializa AST con paréntesis explícitos
  - `evaluator.php` — evalúa AST sobre asignación de variables
  - `truth_table_builder.php` — genera tabla completa con subfórmulas
  - `validator.php` — bounds MAX_VARIABLES=5, MAX_FORMULA_LEN=128, MAX_DEPTH=12, MAX_SUBFORMULAS=8
  - `serializer.php` — JSON encode/decode con `schema_version`
- `classes/tools/truth_table/grader/` — **NEW**:
  - `grader.php` (fachada singleton-like via `instance()`), `complete_grader.php`, `equivalence_grader.php`, `classify_grader.php`
  - `grading_result.php`, `feedback_item.php`
- `classes/tools/truth_table/schema/` — **NEW**: 6 JSON Schema v1 files
- `classes/privacy/provider.php` — **COPY (sin cambio)** (null_provider)

### mod_graphitoubb (adapter)
- `classes/tool_factory.php` — **MODIFY**: agregar `get_truth_table_tool()`
- `classes/attempt_service.php` — **COPY**
- `classes/snapshot_service.php` — **MODIFY**: inyectar validator/serializer del tool seleccionado (factory por slug)
- `classes/report_repository.php` — **MODIFY**: query agnóstica de tool, join generic
- `classes/external/` — **COPY** `save_snapshot`, `get_latest_snapshot`, `finish_attempt`; **NEW** `save_draft`, `submit`, `log_event`, `get_problem_stats`
- `classes/event/attempt_started.php`, `submission_submitted.php`, `problem_updated.php` — **NEW**
- `classes/output/renderer.php` — **MODIFY**: render por tool
- `db/install.xml` — **NEW** tabla `m_graphitoubb_problem`, `m_graphitoubb_submission`, `m_graphitoubb_grade_cache` (si no existen); **ALTER** `m_graphitoubb_attempt` (`current_draft`, `draft_updated_at`)
- `db/upgrade.php` — **NEW**: `xmldb_local_graphitoubb_upgrade()` versión `2026051800` con backfill hash
- `db/services.php` — **MODIFY**: nuevos endpoints AJAX
- `db/access.php` — **MODIFY**: capabilities iter1
- `db/tasks.php` — **NEW**: `cleanup_orphans` task
- `amd/src/truth_table_editor.js` — **NEW**: editor de tabla con sinónimos→Unicode, helpers
- `amd/src/autosave.js` — **NEW**: debounce 500ms, optimistic lock, conflict modal, restore
- `amd/src/panel_dashboard.js` — **NEW**: 4 tabs + heatmap
- `amd/src/formula_parser.js` — **NEW**: cliente espejo del parser PHP (validación + canonicalización rápida)
- `templates/` — **NEW**: `truth_table_editor.mustache`, `truth_table_panel.mustache`, `panel_summary.mustache`, `panel_per_student.mustache`, `panel_heatmap.mustache`, `panel_export.mustache`, `conflict_modal.mustache`, `feedback_cell.mustache`
- `save_draft.php`, `submit.php`, `event.php`, `panel.php`, `panel_export.php` — **NEW**

### qtype_graphitoubb (nuevo plugin)
- `version.php` — **NEW**, `dependencies = ['local_graphitoubb' => 2026051800]`
- `question.php` — **NEW**: `qtype_graphitoubb_question extends \question_graded_automatically`
- `questiontype.php` — **NEW**: factory + DB save/load (`m_qtype_graphitoubb_options`)
- `renderer.php` — **NEW**
- `edit_graphitoubb_form.php` — **NEW**
- `db/install.xml` — **NEW**: tabla `m_qtype_graphitoubb_options`
- `db/upgrade.php` — **NEW**
- `db/access.php` — **NEW** (capabilities vacías, usa `mod/quiz:*`)
- `backup/moodle2/backup_qtype_graphitoubb_plugin.class.php`, `restore_qtype_graphitoubb_plugin.class.php` — **NEW**
- `classes/privacy/provider.php` — **NEW**
- `lang/en|es/qtype_graphitoubb.php` — **NEW**
- `tests/` — **NEW**: unit + behat

### lang strings (`lang/en|es/{component}.php`)
- Prefijos: `err_`, `warn_`, `truth_table_`, `panel_`, `autosave_`, `privacy:metadata*`
- Parámetros `{$a}`
- Paridad en/es

### Tests directories
- `local/graphitoubb/tests/tools/truth_table/{domain,grader,schema}/*_test.php`
- `local/graphitoubb/tests/fixtures/truth_table/*.json`
- `local/graphitoubb/tests/generator/lib.php` (extender)
- `mod/graphitoubb/tests/external/save_draft_test.php`, `submit_test.php`
- `mod/graphitoubb/tests/behat/*.feature` + `behat_mod_graphitoubb.php`
- `question/type/graphitoubb/tests/*.php` + `behat/*.feature`

## Flujos clave

### Submit (alumno → grading)
1. AMD `truth_table_editor` builds submission payload.
2. POST `submit.php` con `attempt_id`, `payload`, `sesskey`.
3. Server: `require_capability('mod/graphitoubb:submit')`, validate JSON Schema, hash payload, llamar `grader::instance()->grade($problem, $submission)`.
4. Persistir submission + grading_result + event `submission_submitted`.
5. Devolver `grading_result` al cliente.

### Autosave (debounced)
1. Cambio en celda → cliente arma `payload + draft_updated_at`.
2. Debounce 500ms → POST `save_draft.php`.
3. Server: rate limit (30/min); compare timestamps. Match → update + nuevo ts. Mismatch → 409 con servidor payload.
4. Cliente: 4 estados visuales. Conflicto → modal.

### Restore al recargar
1. `view.php` carga: submission del intento actual readonly > draft activo (badge) > empty.
2. AMD subscribes a `graphitoubb:autosave-status`.

## Capa de validación cross-cutting
- Server: JSON Schema PHP (justinrainbow/json-schema o handwritten ligero) + `validator.php` específico.
- Cliente: `formula_parser.js` para feedback inmediato.

## Diferencias con AFD
- AFD tenía `wordbank` (log de palabras testeadas) → truth_table no lo necesita; reemplazo es `row_result_log` opcional para telemetría (iter5).
- AFD usaba Cytoscape para visualización → truth_table usa tabla HTML pura + ARIA.
- AFD simulator era step-by-step → truth_table evaluator es row-by-row puro.

## Persistencia (cambio)
Iter 0.5 (rename `discretelab → graphitoubb`) asumido completado. Plugin slug, namespaces, tablas con prefijo `m_graphitoubb_*`.
