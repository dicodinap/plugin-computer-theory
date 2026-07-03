# Partir el editor AFD en núcleo + host (no modo-dual ni editor aparte)

Para que el editor AFD funcione tanto en la actividad (`mod_graphitoubb`) como en el
Question Bank (`qtype_graphitoubb`), separamos el `afd_editor.js` (1145 LOC) en un
**núcleo** reutilizable (lienzo Cytoscape, toolbar, undo/redo, simulador, UI de
alfabeto — emite "el autómata cambió" y expone el autómata actual) y dos **hosts**
delgados que lo embeben: el host del `mod` (snapshots/web-services, ya existe) y el
host del qtype (escribe el autómata en un `<input hidden>`, sin submit propio, con
render read-only para la revisión del quiz).

Descartamos el **modo-dual** (un solo módulo con `if (persistMode)` regado por dentro)
porque deja un módulo de 1145 LOC con dos responsabilidades entrelazadas, y el
**editor aparte para el qtype** porque duplica el lienzo y los dos editores divergen
en bugs y features con el tiempo.

## Nota de terminología

"adapter" ya está tomado: `local_graphitoubb/afd_adapter` es un conversor de **forma
de datos** (Cytoscape ↔ JSON canónico ↔ simulador). Por eso la seam de embebido se
llama **host**, no "adapter".

## Consecuencias

- El "host" no solo persiste: también **provee el DOM** del editor. En el `mod` el
  chrome (lienzo, toolbar, simulador) lo renderiza mustache server-side; el renderer
  del qtype deberá emitir un chrome equivalente para que el núcleo se enganche.
- No hay editor stateless de referencia: el qtype de tablas es un stub no funcional
  (ver `docs/qtype-afd-sizing.md`). El núcleo+host de AFD será el primer qtype que
  realmente corrige en el question engine, y sienta el patrón que tablas necesitará.
- Riesgo principal: regresión en el flujo del `mod` (browser-verified) al extraer el
  núcleo. Se acota con las pruebas/behat existentes y verificación en navegador.
