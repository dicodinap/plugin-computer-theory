# Spec — Iter 1: truth_table

Authoritative working spec compiled from `docs/prds/PRD-iter1-logica-tablas.md` (3134 lines).
Use this document — not the raw PRD — as the binding contract for implementation.

---

## 1. Tool entregable: `truth_table` — 3 modos

Tool registrada en `tool_registry` bajo slug `truth_table`. Todos los modos comparten parser, evaluador, motor de tabla y grader. Solo cambia UI.

| Modo | Acción del alumno | Config profesor |
|------|-------------------|-----------------|
| `complete` | Completa celdas de la tabla | `formula` |
| `equivalence` | Radio sí/no equivalencia; opcionalmente tabla justificación | `formula_1`, `formula_2`, `expected_equivalent` (bool), `require_table_justification` (bool), pesos |
| `classify` | Radio tautología/contradicción/contingencia; opcional tabla | `formula`, `expected_class`, `require_table_justification`, pesos |

`expected_*` autocalculado al parsear; profesor puede sobrescribir.

## 2. Gramática y sintaxis (sección 6)

### Operadores (precedencia 1 = más alta)
| Símbolo | Operador | Aridad | Precedencia | Asociatividad |
|---------|----------|--------|-------------|---------------|
| `¬` | Negación | unario prefijo | 1 | derecha |
| `∧` | Conjunción | binario | 2 | izquierda |
| `∨` | Disyunción | binario | 3 | izquierda |
| `⊕` | XOR | binario | 3 | izquierda |
| `→` | Implicación | binario | 4 | derecha |
| `↔` | Bicondicional | binario | 5 | derecha |
| `⊤` `⊥` | Constantes | nullary | — | — |
| `A`–`Z` | Variables | nullary | — | — |

### Sinónimos ASCII → Unicode
`&` `/\` → `∧` | `|` `\/` → `∨` | `~` `!` → `¬` | `->` → `→` | `<->` → `↔`. `⊤`, `⊥`, `⊕` solo vía helper.

### BNF
```
formula       ::= biconditional
biconditional ::= implication ( '↔' implication )*
implication   ::= disjunction ( '→' disjunction )*
disjunction   ::= conjunction ( ( '∨' | '⊕' ) conjunction )*
conjunction   ::= negation ( '∧' negation )*
negation      ::= '¬' negation | primary
primary       ::= '(' formula ')' | variable | constant
```

### Canonicalización
Reserializa con paréntesis explícitos. `A ∧ B ∨ C` → `(A ∧ B) ∨ C`. Usada en preview profesor y headers de columna.

### Parser
Recursive descent handwritten ~250 LOC. `parser.php` + `lexer.php` en `local/graphitoubb/classes/tools/truth_table/domain/`. AST tipado PHP 8.1 readonly: `formula_ast` (abstracta) con subclases `var_node`, `const_node`, `not_node`, `and_node`, `or_node`, `xor_node`, `impl_node`, `iff_node`. Errores con posición 1-indexed y mensaje en español.

## 3. Bounds (sección 7)

### Problem (validados al guardar)
| Parámetro | Máx |
|-----------|-----|
| Variables distintas | 5 |
| Longitud fórmula (chars normalizados) | 128 |
| Profundidad AST | 12 |
| Subfórmulas mostradas | 8 |
| JSON problem | 8 KB |

### Derivados
| Parámetro | Valor |
|-----------|-------|
| Filas máx | 32 (2^5) |
| Celdas máx a completar | 288 (32 × 9) |

### Submission
| Parámetro | Máx |
|-----------|-----|
| JSON submission | 16 KB |

Exceso problem → validation error específico. Exceso submission → HTTP 413.

## 4. JSON Schemas (sección 10)

### Problem (discriminado por `type`)
Campos comunes: `tool` (`"truth_table"`), `schema_version` (int), `type` (`complete|equivalence|classify`), `ui` (`intermediate_subformulas: auto|none|manual`, `manual_subformulas: []`, `row_order: "canonical"`).

`scoring` solo si `type ∈ {equivalence, classify}`: `radio_weight` (int 0-100), `table_weight` (int 0-100), `wrong_radio_policy` (`strict|proportional`).

Config:
- `complete`: `config.formula`
- `equivalence`: `config.formula_1`, `config.formula_2`, `config.expected_equivalent`, `config.require_table_justification`
- `classify`: `config.formula`, `config.expected_class`, `config.require_table_justification`

### Submission
`tool`, `schema_version`, `type`, `table` (opcional según config), `radio_answer` (null|bool|string).
`table.columns` (string[]) y `table.rows` (`[{vars:{A:"F"|"V"}, values:["V"|"F"|""]}]`).

### Archivos schema (JSON Schema draft-07)
En `local/graphitoubb/classes/tools/truth_table/schema/`:
- `problem-complete.v1.json`, `problem-equivalence.v1.json`, `problem-classify.v1.json`
- `submission-complete.v1.json`, `submission-equivalence.v1.json`, `submission-classify.v1.json`

Versionado: campo `schema_version`. Cambio que agrega field required → bump + función `migrate_problem_v{n}_to_v{n+1}()`.

## 5. Grader (sección 9)

### Score base
`score = (cells_correct / cells_total) × max_grade`. Celdas vacías cuentan incorrectas sin penalización adicional.

### Equivalence/classify
`score = (radio_weight/100)·base_radio + (table_weight/100)·base_table`.
- `strict` (default): radio incorrecto → score = 0.
- `proportional`: radio incorrecto → score = (table_weight/100)·base_table.

Presets: Solo conclusión / Solo evidencia / 50-50 / 70-30 / 30-70 / Personalizado.

### `grading_result`
`score` (float 2 dec), `fraction` ∈ [0,1], `passed` (bool, threshold default 0.6), `cells_total`, `cells_correct`, `feedback_items[]`, `error` (bool), `error_message` (?string), `problem_snapshot_hash` (SHA-256).

### `feedback_item`
`row_index`, `col_label`, `cell_kind` (`subformula|final|radio`), `submitted`, `expected`, `is_correct`, `is_root_error`, `explanation`.

### Errores propagados
Para cada celda incorrecta no-hoja: recomputar usando valores marcados en columnas operandos. Si el resultado coincide con lo enviado pero no con lo esperado → `is_root_error = false`. Si difiere de lo enviado → `is_root_error = true`. Ambos pesan igual en score.

### Multi-intento
`best` (default) / `last` / `average`. `problem_snapshot_hash` marca submissions `stale` si problem cambia.

## 6. Autosave (sección 11)

- Debounce 500 ms → POST `/save_draft`.
- Flush en `visibilitychange` y `beforeunload`.
- Rate limit 30 saves/min por (alumno, attempt_id) → 429 + retry 5s.
- Optimistic locking: cliente envía `draft_updated_at`. Match → 200 con nuevo timestamp; diff → 409 con server payload. Modal "Cargar otra versión" vs "Sobrescribir con la mía" (`force_overwrite=true`).
- Restore on reload prioridad: submission del intento actual (readonly) > draft activo (badge "Recuperado HH:MM") > tabla vacía.
- Limpieza: al submit, edición problem (hash cambia), cierre por fecha (auto-submit o discard), borrar instancia.

### Indicador (4 estados)
Sin cambios / Guardando... / Guardado HH:MM / Error — reintentando...

### DB nuevas columnas
`m_graphitoubb_attempt`: `current_draft` (TEXT nullable), `draft_updated_at` (INT10 nullable).

### Endpoints
- `save_draft.php` (POST AJAX)
- `submit.php` (POST AJAX)
- `event.php` (POST telemetría)

## 7. Panel docente (sección 12)

4 tabs, scope per-actividad. Real-time sin caché.

- **Resumen**: inscritos / intentaron / enviaron / con draft; promedio, mediana, σ, bucket por punto; tiempo mediano; top 5 errores con coords + %.
- **Por alumno**: tabla filtrable (alumno, nota, intentos, tiempo, estado). Filtros: todos / con errores / sin enviar. Click → drawer detalle intentos, errores recurrentes (≥2 intentos), ver tabla, resetear.
- **Heatmap**: grilla filas×cols. Colores % correcto: 90-100 verde fuerte / 75-89 verde claro / 50-74 amarillo / 25-49 naranja / 0-24 rojo. Siempre % numérico. Click → alumnos que erraron + valor enviado. Alternativa textual obligatoria.
- **Export**: CSV / JSON / PDF; contenido seleccionable (resumen, intentos, heatmap, errores); alcance configurable.

## 8. Telemetría (sección 13)

| Evento | Trigger | Payload | Storage |
|--------|---------|---------|---------|
| `attempt_started` | abrir view 1ª vez | `timestamp, user_id, problem_id, attempt_id` | POST; `\mod_graphitoubb\event\attempt_started` |
| `draft_saved` | autosave OK | `timestamp, payload_hash` | piggyback en autosave |
| `submission_sent` | Enviar | `timestamp, payload_hash, score` | POST; `\mod_graphitoubb\event\submission_submitted` |
| `feedback_viewed` | ver resultado | `timestamp` | fail silent (SHOULD) |
| `retry_started` | nuevo intento | `attempt_id, retry_count` | POST; fail silent |
| `problem_updated` | profesor edita | `old_hash, new_hash` | POST; `\mod_graphitoubb\event\problem_updated` |

Tabla `m_graphitoubb_event` sin cambios estructurales.

## 9. PHPUnit (sección 16)

### Cobertura objetivo
| Capa | Mín |
|------|-----|
| `local_graphitoubb/tools/truth_table/` | 90% |
| Parser | 100% |
| Grader | 100% |
| Privacy provider | 100% |
| `mod_graphitoubb` adapter | 60% |
| `qtype_graphitoubb` adapter | 60% |

### Suites (~75 tests)
- Parser: 14
- Evaluator: 8
- Grader: 12
- Schema validation: 7
- Canonical serialization: 5
- Privacy provider: 7
- Bounds: 4
- Error handling: 3

Fixtures `local/graphitoubb/tests/fixtures/truth_table/*.json`. Generator `local_graphitoubb_generator`.

### CI gates
`phpunit` (3 suites), `phpcs --standard=moodle` (0), `phpstan level 5` (0), coverage local < 90% falla.

## 10. Behat — 12 features / 14 escenarios

| Plugin | Feature | # |
|--------|---------|---|
| mod | create_complete_activity | 1 |
| mod | solve_complete_activity | 2 (correcto + parcial + retry) |
| mod | create_equivalence_activity | 1 |
| mod | solve_equivalence_activity | 2 (radio OK + radio mal = 0) |
| mod | create_classify_activity | 1 |
| mod | solve_classify_activity | 1 |
| mod | teacher_panel | 1 (4 tabs + heatmap + drawer) |
| mod | autosave_recovery | 1 (edit + reload + restore) |
| mod | multi_attempt_policy | 1 |
| qtype | add_question_to_quiz | 1 |
| qtype | student_answers_in_quiz | 1 |
| qtype | quiz_review | 1 |

Steps en `mod/graphitoubb/tests/behat/behat_mod_graphitoubb.php` (~14 steps).
Tag `@a11y` inyecta axe-core. Gate: 0 critical/serious.

## 11. Migraciones (versión `2026051800`)

`xmldb_local_graphitoubb_upgrade()`:

| Tabla | Campo nuevo | Tipo |
|-------|-------------|------|
| `m_graphitoubb_attempt` | `current_draft` | TEXT nullable |
| `m_graphitoubb_attempt` | `draft_updated_at` | INT10 nullable |
| `m_graphitoubb_submission` | `problem_snapshot_hash` | CHAR(64) nullable |
| `m_graphitoubb_grade_cache` | `attempt_count` | INT10 NOTNULL default 0 |
| `m_graphitoubb_grade_cache` | `policy_applied` | CHAR(32) NOTNULL default 'best' |
| `m_graphitoubb` | `attempts_policy` | CHAR(32) NOTNULL default 'best' |
| `m_graphitoubb` | `attempts_max` | INT10 nullable |
| `m_graphitoubb` | `close_behavior` | CHAR(32) NOTNULL default 'auto_submit' |
| `m_graphitoubb_problem` | índice `idx_tool_type` en `[tool]` | — |

Upgrade: backfill `problem_snapshot_hash`, cleanup huérfanos, FK `fk_submission_attempt`.
`version.php` sincronizados en `2026051800`. `mod/qtype` con `dependencies = ['local_graphitoubb' => 2026051800]`.

## 12. qtype_graphitoubb (sección 19)

- `qtype_graphitoubb_question extends \question_graded_automatically`
- Props: `$tool='truth_table'`, `$exercise_type`, `$problem_payload`, `$scoring_config`, `$ui_config`
- `grade_response()` → delega a `local_graphitoubb\tools\truth_table\grader::instance()->grade()`
- `get_expected_data()` → `['answer_payload' => PARAM_RAW]`
- Steps: `answer_payload` (JSON), `answer_hash` (SHA-256), `schema_version`
- Behaviors MUST: `deferredfeedback`, `immediatefeedback`. SHOULD: `adaptive`, `adaptivenopenalty`. Iter1 NO: `interactive`, `manualgraded`
- `qtype_graphitoubb_renderer extends \qtype_renderer` reusa templates `local_graphitoubb/templates/`
- `qtype_graphitoubb_edit_form extends \question_edit_form`
- `backup_qtype_graphitoubb_plugin` y `restore_qtype_graphitoubb_plugin`
- `qformat_xml::write_graphitoubb()` / `import_graphitoubb()`

## 13. Capabilities (sección 23) `mod/graphitoubb/db/access.php`

| Capability | Context | Archetypes |
|-----------|---------|-----------|
| `mod/graphitoubb:addinstance` | COURSE | editingteacher, manager |
| `mod/graphitoubb:view` | MODULE | guest, student, teacher, editingteacher, manager |
| `mod/graphitoubb:submit` | MODULE | student |
| `mod/graphitoubb:viewreports` | MODULE | teacher, editingteacher, manager |
| `mod/graphitoubb:gradeother` | MODULE | editingteacher, manager |
| `mod/graphitoubb:manage` | MODULE | editingteacher, manager |
| `mod/graphitoubb:reattempt` | MODULE | editingteacher, manager |

Endpoints requieren `require_login`, `require_capability`, `require_sesskey`.

## 14. Performance budgets (sección 24)

| Endpoint | p95 |
|----------|-----|
| Submit (grading, vars=5) | < 500 ms |
| Autosave | < 200 ms |
| Panel Resumen (50 alumnos) | < 1000 ms |
| Panel Heatmap (50 alumnos) | < 1500 ms |
| Editor render inicial | < 300 ms |
| Telemetría endpoint | < 100 ms |
| Form submit profesor (preview) | < 700 ms |

DB queries/req: submit≤10, autosave≤3, resumen≤8, por-alumno(50)≤10, heatmap≤12.

Bundle gzipped: editor ≤60KB, autosave ≤12KB, panel ≤40KB, parser ≤15KB. CSS ≤25KB.

PHP mem: submit 32MB, panel 64MB, upgrade 128MB.

## 15. Definition of Done

### Funcional
- Tool registrada; 3 tipos end-to-end; editor con sinónimos; parser con errores posicionales en es; evaluador correcto; grader con feedback granular y errores propagados; scoring con presets; schemas validando; autosave completo; panel 4 tabs.

### Calidad
- Cobertura ≥90% en truth_table, =100% en parser/grader/privacy; ≥60% en adapters.
- 0 phpcs (moodlehq/moodle-cs); 0 phpstan level 5.
- 14 escenarios Behat OK; 0 violations axe-core critical/serious.

### Performance
- Submit p95 <500ms; autosave <200ms; resumen <1s; heatmap <1.5s; bundle ≤60KB.

### Docs
- READMEs por plugin; dev-guide; user-manual-teacher/student; README-a11y; CHANGELOG; tool README.

### Cross-funcional
- Privacy API 7 tests 100%; migraciones idempotentes + upgrade_test; i18n en/es; capabilities testeadas; cron `cleanup_orphans`; backup/restore qtype; import/export XML.

### Validación
- Demo con Luis Cabrera + ≥3 estudiantes piloto. Acta `docs/iter1-validation-session.md`.
