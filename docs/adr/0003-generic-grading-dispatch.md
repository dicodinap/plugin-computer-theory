# Grading genérico: `finish_attempt` canónico + `grader_dispatch` con adaptadores

Para gradear los nuevos tools de lienzo (`grafo`, `arbol`) sin reescribir los graders
ya verificados (AFD, tabla de verdad), introducimos un contrato común
`grader_interface { grade(array $problem, ?string $submissionjson): array }` y un
`grader_dispatch` que mapea el slug de la herramienta a su grader. `finish_attempt`
pasa a ser el **disparador canónico de grading para todos los tools de lienzo**
(AFD/grafo/arbol) vía ese dispatch, reemplazando el `if ($problem->tool === 'afd')`
hardcodeado. Los graders existentes se envuelven en **adaptadores delgados** (sus
internals no se tocan); grafo/arbol implementan la interfaz nativamente.

## Considerado y descartado

- **Refactorizar `afd_grader` / grader de tablas a la interfaz común.** Descartado:
  ambos están browser/CLI-verificados; adaptarlos in-place obliga a re-verificar código
  que ya funciona. Los adaptadores preservan la salida byte-idéntica (invariantes I1/I3
  del PRD).
- **Estandarizar el retorno en el value object `grading_result`.** Descartado: el borde
  de persistencia (`submission_repository->save`) ya consume un **array plano**, y
  `afd_grader` ya devuelve array. El array es el mínimo común; `grading_result` queda
  como detalle interno de tablas de verdad.
- **Dejar que grafo/arbol tengan su propio endpoint** (o reusar `submit.php`, el de
  tablas). Descartado: duplica lógica o fuerza generalizar el path de tablas (más blast
  radius). `submit.php` sigue siendo exclusivo de tablas.

## Consecuencias

- **`finish_attempt` recomputa `grade_cache` siempre** (bajo `attempts_policy`). Esto
  **corrige un bug latente de AFD**: hoy `finish_attempt` salta el recompute, así que los
  intentos de AFD nunca poblaban `graphitoubb_grade_cache`, que
  `get_problem_stats.php` y `get_panel_per_student.php` leen — los agregados de AFD
  salían incompletos/en cero en los paneles del profesor. No cambia la *salida* de
  grading (protegida por I1), solo escribe el agregado que antes faltaba.
- El contrato usa un par de conteo genérico `items_total`/`items_correct`; `words_*`
  (AFD) y `cells_*` (tablas) quedan como etiquetas de UI, no del contrato.
- El adaptador de tablas de verdad se añade recién en la Wave C (refactor del qtype),
  no antes, para no tocar el path del `mod` de tablas hasta que haga falta.
- Un lector futuro que vea adaptadores en vez de una jerarquía uniforme, o el recompute
  de cache recién agregado en `finish_attempt`, encuentra aquí el porqué.

Ver `docs/prd-grafo-arbol-tools.md` (D5, D14, D15).
