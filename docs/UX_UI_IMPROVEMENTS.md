# GraphitoUBB — Plan de mejoras UX/UI

> **Documento de producto (PM)** · Autor: PM UX/UI · Fecha: 2026-06-29 · Estado: propuesta
>
> Alcance: los tres plugins de la suite — `mod_graphitoubb` (actividad), `qtype_graphitoubb`
> (pregunta de quiz) y `local_graphitoubb` (dominio/herramientas). Foco en las dos
> herramientas activas: **AFD** (editor de autómatas) y **truth_table** (tablas de verdad).

---

## 1. Resumen ejecutivo

GraphitoUBB es funcionalmente sólido: el editor de AFD construye y simula autómatas
correctamente, las tablas de verdad se corrigen bien, el panel docente y las exportaciones
funcionan. **El problema no es la funcionalidad, es la experiencia.** El producto se siente
como un prototipo de ingeniería expuesto al usuario final: faltan andamiajes de UX que un
estudiante universitario espera (instrucciones, feedback inmediato claro, deshacer, controles
visibles), hay inconsistencias de patrón (diálogos nativos del navegador mezclados con modales
de Moodle), y hay deuda de internacionalización y accesibilidad que rompe la promesa bilingüe
y WCAG del propio proyecto.

Este documento inventaría **34 mejoras** priorizadas (P0–P2) con esfuerzo estimado (S/M/L),
agrupadas por superficie y por tema transversal, y cierra con un roadmap de quick-wins y de
inversiones estratégicas.

**Los 3 problemas más importantes (la "foto" del producto hoy):**

1. **El estudiante de AFD trabaja a ciegas y sin red.** Aterriza en un lienzo en blanco sin
   saber qué construir, ingresa símbolos de transición por un `window.prompt` nativo, no puede
   deshacer, no tiene control de zoom/encaje visible, y **no hay forma de "entregar"** ni de
   recibir una nota. Es un sandbox, no un ejercicio.
2. **No existe autoría docente para AFD.** El profesor no puede definir el lenguaje objetivo,
   las palabras de prueba ni el autómata esperado. Sin esto, AFD no es evaluable y el panel
   muestra siempre 0%.
3. **Deuda de i18n y a11y que contradice las metas declaradas.** Hay ~14 strings hardcodeados
   (mezcla de español e inglés) que ignoran `get_string`, el lienzo de AFD no es operable por
   teclado, y el feedback de simulación es solo por color.

> **Contexto: ya resuelto en esta iteración** (no repetir en el roadmap): iconos del plugin
> (404 del `monologo`), zoom desbocado del lienzo (cap a 1.5×), restauración del historial de
> palabras al recargar, i18n del panel docente, y `reset_attempts` que ahora sí limpia el
> trabajo del alumno en AFD. Ver `CHANGELOG.md`.

---

## 2. Método y principios

**Cómo se obtuvieron los hallazgos.** Recorrido end-to-end con Playwright en navegador real,
con los 4 niveles de rol (anónimo, estudiante, profesor/editingteacher, manager), más auditoría
del código de templates, AMD (JS) y renderers. Cada hallazgo está anclado a una superficie real.

**North star de UX para este producto.** GraphitoUBB es una **herramienta de aprendizaje de
teoría de la computación** para estudiantes universitarios. Las heurísticas que deben guiar las
decisiones:

- **Claridad de tarea > densidad de herramientas.** El estudiante debe saber en todo momento
  *qué* se le pide y *cómo va*.
- **Feedback inmediato y multimodal.** Correcto/incorrecto nunca solo por color; siempre con
  icono + texto + (cuando aplica) explicación.
- **Tolerancia al error.** Deshacer, confirmar acciones destructivas, validar entradas con
  mensajes útiles. Construir un autómata es iterativo.
- **Bajo costo de entrada.** Onboarding ligero, affordances visibles, sin "modos ocultos".
- **Accesibilidad y bilingüismo reales**, no aspiracionales: teclado, lectores de pantalla,
  contraste, y todo string traducible.
- **Consistencia con Moodle.** Usar los patrones nativos (modales, toasts, formularios) en vez
  de reinventar o caer en diálogos nativos del navegador.

**Escala de prioridad y esfuerzo.**

- **P0** — Bloqueante para una buena experiencia / rompe expectativas básicas o de evaluación.
- **P1** — Alto impacto, mejora sustancial percibida.
- **P2** — Pulido, consistencia, deleite.
- Esfuerzo: **S** (≤0.5 día) · **M** (1–3 días) · **L** (>3 días / requiere diseño).

---

## 3. AFD — Editor de autómatas (estudiante)

Es la superficie más rica y la más necesitada de UX. El editor tiene barra de herramientas
(Add state / Add transition / Set start / Toggle final / Delete), panel de alfabeto, lienzo
Cytoscape, simulador (input + Run + traza) y wordbank.

| # | Mejora | Problema (impacto en el usuario) | Recomendación | P | Esf |
|---|--------|----------------------------------|---------------|---|-----|
| A1 | **Enunciado/consigna visible en el editor** | El alumno aterriza en un lienzo en blanco sin saber qué autómata construir. Cognición desperdiciada en adivinar la tarea. | Panel de consigna fijo arriba del editor (lenguaje objetivo, alfabeto sugerido, ejemplos de palabras a aceptar/rechazar). Depende de la autoría docente (ver §5, C1). | P0 | M |
| A2 | **Botón "Entregar" + estado de avance** | No hay forma de finalizar el intento ni señal de "listo". El `finish_attempt` existe pero no tiene botón. La actividad se siente inacabada. | Botón primario "Entregar"/"Marcar como terminado" con confirmación; badge de estado (en progreso / entregado). Acoplar a grading cuando exista. | P0 | M |
| A3 | **Reemplazar `window.prompt` del símbolo de transición** | `afd_editor.js:191` usa un prompt nativo en español hardcodeado. Rompe el foco, tapa el grafo, sin validación inline, inaccesible, no traducible. | Popover/mini-form anclado a la arista en creación, con input de 1 char, validación contra alfabeto, y selección rápida de símbolos ya existentes. Usar `core/modal` o inline. | P0 | M |
| A4 | **Deshacer / Rehacer (Ctrl+Z / Ctrl+Y)** | No hay undo. "Delete" borra al instante sin confirmación ni vuelta atrás → pérdida de trabajo, ansiedad. | Stack de undo/redo sobre las mutaciones de Cytoscape (add/remove/move/data). Botones en toolbar + atajos. | P0 | L |
| A5 | **Indicador de modo activo + cancelar (Esc)** | La barra es una FSM: tras "Add transition" quedas en ese modo sin señal visual clara y sin forma evidente de salir. Modo oculto = errores. | Resaltar el botón del modo activo (`aria-pressed`), mostrar hint contextual ("Click en el estado origen…"), y `Esc`/click en vacío para volver a "idle". | P0 | S |
| A6 | **Controles de zoom / encaje / reset de vista** | No hay botones de zoom ni "ajustar a pantalla". El wheel-zoom y el pan por arrastre existen pero son **invisibles/no descubribles**. El usuario puede perder el grafo fuera de vista. | Cluster de controles flotante: `+ / − / Ajustar / 100%`. "Ajustar" hace `cy.fit()` respetando el cap. | P1 | S |
| A7 | **Auto-organizar / Tidy + anti-solape** | Estados recién creados pueden quedar encimados; no hay forma de re-ordenar el grafo. | Botón "Ordenar" que corre el layout `cose` on-demand; opcional snap-to-grid. | P1 | M |
| A8 | **Editar/renombrar etiquetas de estado** | Los estados son `q0, q1…` inmutables. El alumno no puede nombrarlos según la consigna (p.ej. "par", "impar"). (Diferido en docs.) | Doble-click en el nodo → editar etiqueta inline (respetar `MAX_LABEL_LENGTH`). | P1 | M |
| A9 | **Leyenda visual de estado inicial/final** | Borde azul = inicial, doble anillo = final: sin leyenda, el alumno debe inferirlo. Inicial se apoya casi solo en color. | Mini-leyenda junto al lienzo + flecha de entrada explícita para el estado inicial (convención estándar de AFD). | P1 | S |
| A10 | **Simulador: traza paso a paso + replay** | La animación es fija a 400 ms/símbolo; en palabras largas es lenta y no se puede pausar/avanzar manualmente. | Controles de traza: paso adelante/atrás, play/pausa, velocidad; resaltar la arista usada en cada paso, no solo el nodo. | P1 | M |
| A11 | **Explicar por qué "Run" no corre** | Si falta estado inicial o alfabeto, sale un toast genérico; el alumno no relaciona la causa. | Deshabilitar "Run" con tooltip explicativo ("Define un estado inicial") y checklist de validez del autómata siempre visible. | P1 | S |
| A12 | **Confirmar acciones destructivas / borrar todo** | "Delete" sin confirmación; no hay "limpiar lienzo". | Confirmación ligera al borrar nodos con aristas; acción "Reiniciar autómata" con confirmación (modal Moodle, no `confirm` nativo). | P2 | S |
| A13 | **Onboarding / ayuda contextual** | Cero tooltips o tour. Un alumno nuevo no sabe el flujo "click-click para transición". | Tooltips en cada herramienta + primer-uso opcional (User Tour de Moodle) o panel "¿Cómo funciona?" colapsable. | P2 | M |
| A14 | **Operabilidad por teclado del lienzo** | Las interacciones del grafo requieren puntero (deuda reconocida en README-a11y). Excluye a usuarios de teclado/lector. | Modo teclado: navegar nodos con flechas, crear/conectar con atajos, panel-formulario alternativo para definir transiciones. | P2 | L |

---

## 4. Truth table — Editor (estudiante)

Tabla con selects V/F por celda, input de fórmula con helpers Unicode (¬ ∧ ∨ → ↔ ⊕ ⊤ ⊥),
radios para equivalence/classify, autosave con resolución de conflictos, y feedback por celda.

| # | Mejora | Problema | Recomendación | P | Esf |
|---|--------|----------|---------------|---|-----|
| B1 | **Indicador de guardado localizado** | `save_indicator.js:36-38` hardcodea `'Saving…' / 'Saved ✓' / 'Save failed ✕'` en inglés, ignorando `get_string`. En un sitio en español se ve en inglés. | Mover a `get_string` vía `core/str` (mismo patrón ya aplicado al panel docente). | P0 | S |
| B2 | **Modal de conflicto: i18n + foco + zona horaria** | `conflict_modal.mustache:20-21` hardcodea español ("Versión del servidor / Tu versión"); timestamps con `toLocaleTimeString` sin TZ del usuario; sin focus-trap explícito. | Strings vía `{{#str}}`; usar `userdate`/formato Moodle; verificar focus-trap del `modal_factory`. | P1 | M |
| B3 | **Estado de carga al enviar** | Al enviar, el botón solo se deshabilita; sin spinner. En conexiones lentas parece colgado. | Spinner + "Corrigiendo…" en el botón; bloquear doble envío. | P1 | S |
| B4 | **Errores de parseo de fórmula más visibles** | Los errores se muestran como texto rojo en el preview canónico (solo color), sin rol de alerta. `formula_parser.js` además hardcodea mensajes en español. | Mensaje inline con icono + `role="alert"`, ancla al carácter problemático; localizar los mensajes del parser. | P1 | M |
| B5 | **Feedback por celda: i18n** | `feedback_cell.mustache:26,29` y los legends de radios en `renderer.php:319-329` están hardcodeados en español. | Migrar a `get_string` (en + es). | P1 | S |
| B6 | **Estado vacío de la tabla** | Si la tabla tiene 0 filas no hay mensaje. | Empty state explicativo. | P2 | S |
| B7 | **Affordance de los helpers Unicode** | Botones de operadores útiles, pero su `aria-label` está hardcodeado en español y no hay leyenda de qué hace cada símbolo. | Localizar labels + tooltip con nombre del operador y ejemplo. | P2 | S |
| B8 | **Reforzar feedback más allá del color** | Celdas correctas/incorrectas se distinguen por clase CSS + icono (bien), validar contraste y que el icono no sea `aria-hidden` sin texto alternativo equivalente. | Auditar contraste y alternativa textual de cada celda. | P2 | S |

---

## 5. Autoría docente (edit_problem.php + qtype form)

| # | Mejora | Problema | Recomendación | P | Esf |
|---|--------|----------|---------------|---|-----|
| C1 | **Autoría de ejercicios AFD (no existe)** | El profesor no puede definir lenguaje objetivo, palabras de prueba (aceptar/rechazar) ni autómata esperado. AFD es solo un sandbox → no evaluable, panel siempre 0%. | Formulario de autoría AFD: consigna, alfabeto, set de palabras de test con veredicto esperado, y (opcional) autómata oracle. Habilita grading por equivalencia/test-set. **Es la inversión estratégica #1.** | P0 | L |
| C2 | **`edit_problem.php` es HTML procedural, no `moodleform`** | Form construido a mano: validación por notificación global (sin errores por campo), labels hardcodeadas, sin estilos/UX estándar de Moodle. | Reescribir como `moodleform` (mod_form ya lo es): validación inline por campo, labels vía `get_string`, accesibilidad y consistencia gratis. | P1 | L |
| C3 | **Auto-submit al cambiar tipo de ejercicio** | `onchange="this.form.submit()"` recarga y **pierde lo escrito** en los campos de fórmula. Frustrante. | Mostrar/ocultar campos vía JS (como hace el qtype con `hideIf`) sin recargar. | P1 | M |
| C4 | **Previsualización de la tabla resultante** | El profesor no ve la tabla que verá el alumno; debe imaginarla. | Preview en vivo de la tabla de verdad esperada al validar la fórmula. | P1 | M |
| C5 | **Ayuda de sintaxis de fórmulas** | El placeholder son ejemplos ASCII; sin leyenda de operadores ni link de ayuda. | Help-button de Moodle con leyenda de símbolos y ejemplos; botones de inserción de operadores como en el editor del alumno. | P2 | S |
| C6 | **qtype: fórmula en el quiz se muestra como HTML crudo** | `qtype/renderer.php` muestra `<strong>` + fórmula cruda, no la forma canónica del editor → inconsistencia entre lo que el alumno ve al editar vs en el quiz. | Unificar el renderizado de fórmula (canónico) entre actividad y qtype. | P2 | S |

---

## 6. Panel docente y reportes

Ya en buen estado tras los fixes (4 tabs, export CSV/JSON/PDF, i18n corregida). Mejoras de pulido:

| # | Mejora | Problema | Recomendación | P | Esf |
|---|--------|----------|---------------|---|-----|
| D1 | **Reset usa `window.confirm` nativo** | `panel_dashboard.js` confirma el reseteo con `confirm()` nativo: inconsistente con Moodle, no traducible del todo, sin detalle del impacto. | Modal de Moodle con nombre del alumno y qué se borrará (snapshots, palabras, envíos). | P1 | S |
| D2 | **Feedback tras acciones del panel** | El reset recarga la fila sin toast de éxito; el profesor no tiene confirmación clara. | Toast de éxito/error tras reset y export. | P2 | S |
| D3 | **"Última palabra probada" vacía es confusa** | Cuando la última palabra fue la cadena vacía, la columna se ve en blanco (parece bug). | Renderizar cadena vacía como "ε (vacía)". | P2 | S |
| D4 | **Heatmap/tabla por-alumno en móvil** | Celdas con `min-width:60px` inline y tablas anchas → scroll horizontal incómodo en móvil. | Patrón responsive (scroll contenido + encabezados sticky o vista apilada). | P2 | M |
| D5 | **Estados de carga de tabs** | Al cambiar de tab hay un parpadeo "Loading editor…" reutilizado de otro contexto. | Skeleton/spinner propio y mensaje correcto por tab. | P2 | S |

---

## 7. Temas transversales

### 7.1 Internacionalización (i18n) — **P0 como bloque**

El proyecto es bilingüe (`lang/en` + `lang/es`), pero hay ~14 strings que ignoran `get_string`
y quedan fijos en un idioma. Esto rompe el bilingüismo de forma visible.

| Archivo | Texto hardcodeado |
|---------|-------------------|
| `amd/src/save_indicator.js` | `Saving…` / `Saved ✓` / `Save failed ✕` (inglés) |
| `amd/src/afd_editor.js:191` | `Símbolo de transición (1 carácter alfanumérico):` |
| `amd/src/formula_parser.js` | `Carácter inesperado`, `Fórmula incompleta` |
| `templates/conflict_modal.mustache` | `Versión del servidor:` / `Tu versión:` |
| `templates/truth_table_editor.mustache` | `Operadores lógicos`, labels `Negación ¬`…, aria `Fila…, columna…` |
| `templates/feedback_cell.mustache` | `enviado` / `esperado`, `Fila…` |
| `classes/output/renderer.php` | `¿Son lógicamente equivalentes?`, `Clasificación de la fórmula`, `Tautología/Contradicción/Contingencia` |
| `qtype/.../question.php` | `Sí`, `No`, `Equivalencia:`, `Clasificación:`, `filas` |

**Recomendación:** un *i18n sweep* dedicado — mover todo a `get_string`/`{{#str}}`/`core/str`,
agregar claves en `en` + `es`. Esfuerzo **M**; impacto alto y transversal. (Patrón ya validado
en el panel docente en esta iteración.)

### 7.2 Accesibilidad (WCAG 2.1 AA — meta declarada)

**Bien:** landmarks, `role="toolbar"`/`aria-label`, `aria-live="polite"` en autosave/feedback/traza,
`scope="col"`, iconos + texto en varios feedbacks.

| # | Gap | Recomendación | P | Esf |
|---|-----|---------------|---|-----|
| E1 | Lienzo AFD no operable por teclado | Ver A14 (modo teclado / formulario alternativo). | P1 | L |
| E2 | Feedback de simulación AFD solo por color (`.trace-accept`/`.trace-reject`) | Añadir icono + texto ("✓ Aceptada" / "✗ Rechazada") en la zona de traza, no solo recolorear. | P1 | S |
| E3 | aria-labels hardcodeados en español (celdas, helpers) | Parte del i18n sweep (§7.1). | P1 | S |
| E4 | Foco no gestionado explícitamente en modales | Verificar focus-trap y retorno de foco al cerrar. | P2 | S |
| E5 | Estado inicial casi solo por color (borde azul) | Flecha de entrada explícita (A9). | P1 | S |
| E6 | Auditoría axe-core en CI no automatizada (axe.min.js manual) | Vendorizar axe y correrlo en Behat a11y. | P2 | M |

### 7.3 Responsive / móvil

| # | Gap | Recomendación | P | Esf |
|---|-----|---------------|---|-----|
| F1 | `styles.css` sin media queries; lienzo AFD `min-height:420px` fijo | Reducir alto y permitir zoom/scroll contenido en breakpoints; revisar uso táctil de Cytoscape. | P1 | M |
| F2 | Tablas anchas (heatmap, per-student, tabla de verdad) en móvil | `table-responsive` + encabezados sticky o vista apilada. | P2 | M |
| F3 | Form docente con dos fórmulas lado a lado no apila en móvil | Grid responsive que apile en xs. | P2 | S |

### 7.4 Feedback, notificaciones y consistencia de patrones

| # | Gap | Recomendación | P | Esf |
|---|-----|---------------|---|-----|
| G1 | Diálogos nativos (`prompt`/`confirm`) mezclados con toasts/modales de Moodle | Erradicar `window.prompt`/`confirm` (A3, D1); estandarizar en `core/modal` + `core/notification`. | P0 | M |
| G2 | Errores de parseo como texto plano vs toasts en otros lados | Unificar: errores de validación inline con `role="alert"`; éxitos como toast. | P1 | S |
| G3 | Sin estado de carga en envíos (truth_table y futuro AFD submit) | Spinner consistente en todos los botones de acción async. | P1 | S |
| G4 | Falta de "empty states" consistentes | Mensajes vacíos con icono + acción sugerida en lienzo, wordbank, tablas, feedback. | P2 | M |

### 7.5 Diseño visual y consistencia

| # | Mejora | Recomendación | P | Esf |
|---|--------|---------------|---|-----|
| H1 | Sistema visual del editor AFD | Definir tokens (colores de estado inicial/final/aceptación, grosores, tipografía de etiquetas) coherentes con el tema Boost y con buen contraste. | P2 | M |
| H2 | Jerarquía visual del editor | Agrupar visualmente toolbar / alfabeto / lienzo / simulador / wordbank con secciones y títulos claros; hoy son bloques apilados sin jerarquía. | P2 | M |
| H3 | Iconografía en botones de toolbar | Hoy son solo texto; añadir iconos (FontAwesome de Moodle) para escaneo rápido y soporte móvil. | P2 | S |

---

## 8. Roadmap propuesto

### Quick wins (1 sprint, casi todo S — máximo impacto/esfuerzo)
- B1 indicador de guardado i18n · A5 indicador de modo + Esc · A6 controles de zoom/encaje ·
  A9 leyenda + flecha de inicial · A11 por qué Run no corre · E2 feedback de simulación no-solo-color ·
  D1 reset con modal Moodle · D3 cadena vacía como "ε" · B3/G3 spinner de envío.

### Ola 1 — Experiencia del estudiante AFD (P0 funcional)
- A1 consigna · A2 botón Entregar + estado · A3 popover de transición (mata el `prompt`) ·
  A4 undo/redo · G1 erradicar diálogos nativos.

### Ola 2 — i18n + a11y (P0/P1 de calidad)
- §7.1 i18n sweep completo · B2/B4/B5 i18n+UX de truth_table · E1/E3/E5 accesibilidad.

### Ola 3 — Evaluación y autoría (estratégico, P0/P1, esfuerzo L)
- C1 autoría de ejercicios AFD + grading · C2 `edit_problem` como `moodleform` ·
  C3 sin auto-submit · C4 preview de tabla.

### Ola 4 — Pulido y móvil (P2)
- A7/A8/A10/A13 mejoras del editor · F1–F3 responsive · H1–H3 diseño visual · D2/D4/D5 panel.

---

## 9. Métricas de éxito sugeridas

- **Time-to-first-correct** (AFD/truth_table): tiempo desde abrir la actividad hasta el primer
  veredicto correcto. ↓ esperado con consigna (A1) y feedback claro.
- **Tasa de intentos sin entrega** en AFD → 0 una vez exista "Entregar" (A2).
- **Errores recuperados con undo** (A4) y abandono tras un borrado accidental → ↓.
- **% de strings cubiertos por `get_string`** → 100% (§7.1).
- **Score axe-core** sin violaciones críticas en las vistas clave (§7.2).
- **Usabilidad móvil**: tareas completables en viewport ≤ 768px sin scroll horizontal.

---

## 10. Anexo — Resumen por prioridad

**P0 (8):** A1, A2, A3, A4, A5, B1, C1, G1 + bloque i18n (§7.1).
**P1 (15):** A6, A7, A8, A9, A10, A11, B2, B3, B4, B5, C2, C3, C4, D1, E1, E2, E5, F1, G2, G3.
**P2 (resto):** A12, A13, A14, B6, B7, B8, C5, C6, D2, D3, D4, D5, E4, E6, F2, F3, G4, H1, H2, H3.

> Nota de confianza: los hallazgos de las superficies de truth_table/qtype provienen de auditoría
> de código (referencias `archivo:línea` pueden moverse entre versiones); los de AFD y panel
> docente están verificados en navegador en esta sesión. Antes de planificar cada ítem, validar
> la línea exacta en el código vigente.
