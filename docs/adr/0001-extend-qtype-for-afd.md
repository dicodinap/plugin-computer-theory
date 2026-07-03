# Soportar AFD extendiendo `qtype_graphitoubb`, no en un plugin nuevo

Para llevar AFD al Question Bank nativo de Moodle decidimos **extender el question
type existente `qtype_graphitoubb`** (que hoy solo hace tablas de verdad) con una
herramienta `tool=afd`, en lugar de crear un plugin `qtype` separado.

La razón: la tabla `qtype_graphitoubb_options` ya es tool-genérica (columnas `tool`,
`problem_payload`, `scoring_config`, `ui_config`) y el backup/restore copia
`problem_payload` sin asumir la herramienta. Extender reutiliza DB, backup/restore,
privacy, services y lang; un plugin separado duplicaría todo ese andamiaje y partiría
el mantenimiento en dos.

## Consecuencias

- Un mismo question type mezcla autómatas y lógica proposicional. Un lector futuro
  podría sorprenderse: la pista es la columna `tool`, que discrimina la herramienta
  por pregunta.
- No hay cambios de esquema de DB ni de backup/restore para AFD.
- El editor de edición y el renderer pasan a ramificar por `tool`.
