# Estudio de esfuerzo — qtype AFD (¿cuán grande es?)

> Pregunta: para llevar AFD al **Question Bank nativo de Moodle** (Mundo B) hace
> falta un question type AFD. ¿Qué tan grande es construirlo?
> Fecha: 2026-06-29. Base medida: `qtype/graphitoubb` (solo truth_table hoy).

## TL;DR

**Tamaño: Medio-Grande (M/L), ~3–6 días de trabajo enfocado.** No es un "banco de
preguntas" — es un feature nuevo. La buena noticia: **el PHP es barato** porque el
almacenamiento del qtype ya es genérico por `tool`. La mala: **el costo real y casi
todo el riesgo está en un solo sitio**, el editor Cytoscape de AFD (1145 LOC), que
hoy está cableado al loop de intentos/snapshots del `mod` y debe volverse *stateless*
(escribir la respuesta en un input oculto) para vivir en el question engine.

## ⚠️ Corrección (hallazgo 2026-06-29): el qtype de tablas es un stub que NO funciona

El estudio inicial supuso que "el qtype de truth_table ya funciona, basta espejarlo".
**Falso.** El qtype de tablas es un stub iter1 no funcional:

- `renderer.php` crea `<input hidden name="answer_payload">` y llama
  `truth_table_editor.init('#wrapper', problem_json, input_name)` con 3 args.
- `truth_table_editor.init(rootElement)` acepta **1 arg**, ignora los demás, lee
  `dataset.attemptid` (→ 0 en quiz) y al enviar llama `Repository.submit(0,…)` — un
  web service del **`mod`**, no del question engine.
- **Nadie escribe `answer_payload`** y el editor busca un DOM (`[data-region=...]`)
  que el renderer del qtype no emite. Resultado: jamás corrige una respuesta en quiz.

**Consecuencia para AFD:** no hay editor stateless de referencia. La integración
stateless (input oculto + provisión de DOM + render read-only de revisión) se construye
**desde cero**. Esto confirma que la opción **núcleo + host** (ADR-0003) es obligada,
no opcional, y que el "host" también provee el DOM del editor, no solo la persistencia.

## Lo que YA se reutiliza (0 código nuevo)

| Componente | LOC | Estado |
|---|---|---|
| Tabla `qtype_graphitoubb_options` (`tool`, `problem_payload`, `scoring_config`, `ui_config`) | — | **Genérica, sin cambios de DB** |
| backup/restore moodle2 (copia `problem_payload` sin citar truth_table) | 68+85 | **Sirve tal cual** |
| privacy provider | 145 | Sin cambios |
| `afd_grader` (grading server-side de AFD) | 123 | **Existe — se reutiliza** |
| Dominio AFD (automaton, simulator, validator, serializer, state, transition, trace) | ~775 | **Existe — se reutiliza** |

## Lo que hay que escribir o modificar (PHP — moderado)

| Archivo | Base | Cambio | Δ aprox |
|---|---|---|---|
| `question.php` | 242 | `tool` hardcodeado a `truth_table`; `grade_response` delega a `grader::instance()`. Añadir rama AFD → `afd_grader`. | +80 |
| `questiontype.php` | 381 | `build_problem_array` + validación por tool (AFD usa domain validator, no JSON schema). | +70 |
| `edit_graphitoubb_form.php` | 344 | Selector de tool + campos AFD (consigna, alfabeto, palabras de prueba). Espejo de la rama AFD de `edit_problem.php`. | +120 |
| `renderer.php` | 228 | Contenedor del editor AFD + init AMD + render *read-only* en revisión. | +80 |
| lang en+es | 83×2 | ~30 claves nuevas. | +60 |
| **Subtotal PHP** | | | **~410** |

## El costo dominante — refactor del editor AFD (JS)

`mod/graphitoubb/amd/src/afd_editor.js` = **1145 LOC**. Su `init` es:

```js
init(attemptid, instanceid, schemaversion)  // carga snapshot vía repository WS,
                                             // auto-guarda en graphitoubb_snapshot/attempt
```

El question engine **no tiene** `attemptid`, `instanceid` ni snapshots. El patrón correcto
es el del editor de tablas, que ya es stateless:

```js
truth_table_editor.init(wrapperSelector, problemJson, inputName)  // escribe en <input hidden>
```

Hay que extraer del editor AFD un **modo stateless**:

1. Cargar problema/alfabeto desde `problemJson` (no desde WS).
2. Escribir el JSON del autómata en un `<input type=hidden>` en cada edición.
3. Render **read-only** desde un valor guardado para la revisión del quiz.
4. Eliminar todas las llamadas a `repository`/snapshot/`finish_attempt`.

Riesgo: undo/redo, alphabet UI, simulador y trace-replay asumen el editor "vivo".
Separar "núcleo del editor" de "adaptador de persistencia" sin romperlos es el trabajo real.

**Estimado JS: 250–500 LOC modificadas/nuevas + rebuild `.min.js` + pruebas. ~40% del
esfuerzo, ~80% del riesgo.**

## Tests

| | Δ aprox |
|---|---|
| `question_test`, `qtype_..._test`, `helper` (casos AFD) | +250 |
| behat (añadir/responder/revisar AFD en quiz) | +150 |

## Total

**~1.0k–1.6k LOC nuevas/cambiadas**, dominadas por el refactor del editor.

## Decisión de arquitectura implícita

Dos caminos para el lado PHP:

- **Extender el qtype actual** (`tool=afd` en `qtype_graphitoubb`). Reusa DB, backup,
  privacy, services. **Recomendado** — la tabla ya es tool-genérica.
- **Plugin qtype separado** (`qtype_graphitoubbafd`). Limpio pero duplica todo el andamiaje.

## Comparación con el catálogo de presets (Mundo A)

Un **catálogo de plantillas** que precargue `edit_problem.php` (Mundo A) sirve para
**AFD y tablas hoy mismo, con cero cambios al editor**: es un archivo de datos + un
selector + prefill. Esfuerzo S/M. No da reutilización nativa de Moodle (quizzes,
import/export), pero resuelve "no escribir desde cero" para ambas herramientas ya.
