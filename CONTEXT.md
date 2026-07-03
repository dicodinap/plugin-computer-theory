# Glosario de dominio — GraphitoUBB

Lenguaje compartido del proyecto. Solo definiciones, sin detalles de implementación.

## Términos

### Herramienta (tool)
El tipo de ejercicio que el motor sabe corregir. Hoy hay dos: **AFD** y **tabla de
verdad** (`truth_table`). Cada artefacto corregible declara su herramienta.

### AFD
Autómata finito determinista. El estudiante construye un autómata sobre un alfabeto
para que acepte exactamente cierto lenguaje. (En inglés del código: DFA.)

### Tabla de verdad (truth_table)
Ejercicio de lógica proposicional: completar una tabla, decidir equivalencia de dos
fórmulas, o clasificar una fórmula (tautología / contradicción / contingencia).

### Problema (activity problem)
El **único** enunciado corregible que un profesor adjunta a una **instancia de
actividad** `mod_graphitoubb`. Relación 1-a-1 con la instancia. El profesor lo escribe
a mano en el editor de problemas de la actividad. No es reutilizable entre actividades.

### Pregunta (question)
Una entrada del **Question Bank nativo de Moodle**, del tipo `qtype_graphitoubb`.
A diferencia del *Problema*, es reutilizable: se comparte entre cursos, se importa /
exporta y se inserta en cuestionarios (quizzes). Hoy el qtype tiene un stub no
funcional de tablas de verdad (no corrige respuestas en el question engine); el
trabajo en curso es construir el primer qtype que funciona de verdad, para **AFD**.

> **Problema ≠ Pregunta.** Mismo contenido corregible, dos hogares distintos: el
> *Problema* vive pegado a una actividad; la *Pregunta* vive en el banco de Moodle.

### Question Bank (banco de preguntas)
Término **reservado de Moodle**: su repositorio nativo de preguntas reutilizables.
No usar "banco de preguntas" para referirse a nuestro catálogo curado (ver *Catálogo
de plantillas*), para no colisionar con este significado fijo.

### Catálogo de plantillas (preset catalog)
Biblioteca curada de enunciados listos para usar, de la que el profesor elige uno para
**precargar** el editor de *Problema* en vez de escribir desde cero. Es conveniencia de
autoría, no reutilización nativa de Moodle. (Planificado en sesión aparte; aplica a
ambas herramientas.)

### Palabra de prueba (test word)
Para un *Problema*/*Pregunta* de AFD: una cadena con su veredicto esperado
(acepta / rechaza) que el corrector usa para evaluar el autómata del estudiante.
Normalmente ocultas; algunas pueden marcarse como ejemplo visible para el estudiante.

### Veredicto (verdict)
El resultado esperado de una palabra de prueba: **acepta** o **rechaza**.
