# Plan — qtype AFD (Mundo B: AFD en el Question Bank de Moodle)

> Resultado de la sesión de grilling (2026-06-29). Estimación: ver
> `docs/qtype-afd-sizing.md`. Decisiones de arquitectura: ADR-0001, ADR-0002.

## Objetivo

Hacer que un ejercicio de **AFD** sea una **Pregunta** reutilizable del Question Bank
nativo de Moodle (insertable en quizzes, compartible, importable/exportable), corregida
automáticamente por `afd_grader`. Es el primer qtype de la suite que realmente corrige
en el question engine (el de tablas es un stub no funcional).

## Decisiones (cerradas)

1. **Mundo B, AFD.** Esta sesión construye el qtype AFD; el catálogo de presets
   (Mundo A) va a otra sesión.
2. **Extender `qtype_graphitoubb` con `tool=afd`** (ADR-0001). La tabla
   `qtype_graphitoubb_options` ya es tool-genérica → **sin cambios de DB ni de
   backup/restore**.
3. **Editor = núcleo + host** (ADR-0002). Se extrae `afd_editor_core` (Cytoscape,
   toolbar, undo/redo, simulador, alfabeto); dos hosts delgados lo embeben: host
   `mod` (snapshots/WS, ya existe) y host qtype (input oculto). Descartado el modo-dual
   y el editor aparte.
4. **DOM del qtype**: el renderer del qtype renderiza el mismo mustache
   `mod_graphitoubb/editor` en modo host-neutral (id de lienzo parametrizable,
   `data-attemptid/instanceid` opcionales) + un `<input hidden name="answer_payload">`.
5. **Simulador**: se mantiene dentro del quiz (depuración del propio autómata; no
   filtra el set oculto). Se quita solo la telemetría `logWord`.
6. **Nota**: crédito parcial = `fraction` por default, con toggle por pregunta
   `grading_mode` (`partial` | `all_or_nothing`) guardado en `scoring_config`.
7. **Revisión**: autómata del estudiante read-only + nota; del detalle por palabra,
   solo **agregado** ("N de M pruebas correctas") + palabras-ejemplo (`*`) tras el flag
   de feedback específico; **nunca** las palabras ocultas. `get_correct_response` →
   nulo (no hay AFD canónico único).
8. **Consigna → `questiontext`** estándar de Moodle (texto enriquecido, Moodle la
   muestra arriba). El `problem_payload.config` guarda **solo `alphabet` + `test_words`**.

## Modelo de datos (sin cambios de esquema)

`qtype_graphitoubb_options` por pregunta:
- `tool = 'afd'`, `exercise_type = 'language'`
- `problem_payload` = `{tool, schema_version, type:'language', config:{alphabet[], test_words[]}}`
  (las `test_words` = `[{word, accept, example}]`, mismo formato que el `mod`).
- `scoring_config` = `{grading_mode: 'partial'|'all_or_nothing'}`
- `ui_config` = reservado.
- `payload_hash`, `schema_version` como hoy.

## Desglose de trabajo y secuencia (de-riskear el editor primero)

### Fase 1 — Extracción del núcleo del editor (la pieza de riesgo)
1.1 Definir el **contrato del host**: `host.loadInitial() → automaton|null`,
    `host.onChange(automaton)`, `host.providesSubmit?` (mod sí / qtype no),
    `host.container`, strings. (El simulador y el alfabeto son del núcleo.)
1.2 Extraer `afd_editor_core` desde `afd_editor.js`. Reescribir `afd_editor.js` como
    **host `mod`** delgado que delega al núcleo (snapshots/`finishAttempt`/`logWord`/
    `SaveIndicator` quedan en el host `mod`).
1.3 **Verificar que el `mod` no cambió**: behat existente + verificación en navegador
    (el flujo del `mod` está browser-verified; es la principal fuente de regresión).
1.4 Rebuild de `.min.js`.

### Fase 2 — Host qtype + pegamento PHP
2.1 `question.php`: rama `tool=afd` en `grade_response` → `afd_grader` (aplicar
    `grading_mode`); `get_expected_data` (`answer_payload`), `summarise_response`,
    `is_complete_response`, `get_correct_response` → null para afd.
2.2 `questiontype.php`: `build_problem_array` afd (alfabeto + test_words del form),
    validar con el **domain validator** de afd (no hay JSON schema afd; truth_table sí),
    construir `scoring_config`.
2.3 `edit_graphitoubb_form.php`: selector de tool + campos afd (alfabeto, textarea de
    palabras de prueba con el mismo parseo `verdict:word` y `*` de `edit_problem.php`,
    toggle `grading_mode`). La consigna usa el `questiontext` estándar.
2.4 `renderer.php`: render del mustache `mod_graphitoubb/editor` host-neutral + hidden
    `answer_payload`; init del **host qtype**; render de revisión (read-only + agregado).
2.5 Nuevo módulo AMD **host qtype** (`qtype_graphitoubb/afd_host` o en `mod`): carga
    desde el input, escribe el autómata canónico en el input en cada cambio, sin submit
    propio, render read-only en revisión, simulador sin `logWord`.

### Fase 3 — i18n + pruebas
3.1 Strings en+es del qtype (campos del form, feedback de revisión, errores).
3.2 PHPUnit: `question_test` (grade afd parcial/all-or-nothing, invalid, ε),
    `qtype_..._test` (save/load options afd), `helper` (fixture afd).
3.3 Behat: añadir pregunta afd a un quiz, responder construyendo un autómata, revisar.
3.4 Verificación en navegador (Playwright) rol profesor+estudiante.

## Riesgos

- **Regresión del `mod`** al extraer el núcleo (Fase 1.3 lo acota).
- El editor read-only de revisión: el núcleo debe poder montar un autómata desde
  `answer_payload` sin toolbar interactiva.
- Behat de quiz con un editor Cytoscape (interacción de canvas) puede requerir helpers
  específicos (ya existe `behat_qtype_graphitoubb.php` como base).

## Diferido (no esta sesión)

- Catálogo de presets (Mundo A) para AFD y tablas — PRD en otra sesión.
- Arreglar el qtype de tablas para que funcione de verdad (reusaría este patrón).
- Flag "examen a ciegas" (ocultar simulador) por pregunta.
