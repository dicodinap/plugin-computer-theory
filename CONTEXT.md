# Glosario de dominio — GraphitoUBB

Lenguaje compartido del proyecto. Solo definiciones, sin detalles de implementación.

## Términos

### Herramienta (tool)
El tipo de ejercicio que el motor sabe corregir. **AFD** y **tabla de verdad**
(`truth_table`) están en producción; **grafo** y **árbol** (`arbol`) están en
construcción. Cada artefacto corregible declara su herramienta.

### AFD
Autómata finito determinista. El estudiante construye un autómata sobre un alfabeto
para que acepte exactamente cierto lenguaje. (En inglés del código: DFA.)

### Grafo (grafo)
Herramienta de teoría de grafos. Según el tipo de ejercicio, el estudiante **construye**
un grafo que cumpla ciertas propiedades, **decide** una propiedad de un grafo dado
(p. ej. Königsberg: ¿existe circuito de Euler?), o **halla** un recorrido (Euler /
Hamilton) sobre un grafo dado.

### Árbol (arbol)
Herramienta de árboles. El estudiante **construye** un árbol binario de búsqueda (BST)
a partir de inserciones, **responde** un recorrido (pre/in/post-orden) de un árbol dado,
o **reconstruye** el árbol único a partir de dos recorridos. Un árbol es un caso
particular de grafo, pero es una herramienta distinta con su propia semántica (hijo
izquierdo / derecho).

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
