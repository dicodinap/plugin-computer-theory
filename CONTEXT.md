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

### Mapa de Karnaugh (karnaugh)
Herramienta (*tool*) de simplificación booleana (RF_04). El profesor define una función
booleana de **hasta 4 variables** por su **tabla de verdad** (qué combinaciones dan 1).
El estudiante trabaja en dos etapas: (1) **transfiere** los valores de la tabla a las
celdas del mapa —respetando el orden Gray/adyacencia—, y (2) **agrupa** las celdas en 1
y propone la **forma mínima**. La corrección es *server-side*. La tabla de verdad es la
fuente de la verdad canónica de la función.

### Grupo (group)
En un *Mapa de Karnaugh*: un rectángulo de celdas en 1, de tamaño **potencia de 2**,
adyacentes (con *wrap* de bordes), que el estudiante dibuja. Cada grupo válido genera un
**término producto** (las variables que se mantienen constantes en el grupo). El error de
corrección *"en qué grupo hay un error"* se ancla a un grupo concreto.

### Forma mínima (minimal form)
La expresión que propone el estudiante como resultado de la simplificación: el **OR de los
términos** de sus grupos. El sistema verifica que sea **lógicamente equivalente** a la
función original (fuerza bruta sobre las 2ⁿ asignaciones, como el corrector de equivalencia
de *tabla de verdad*).

### Relación (relations)
Herramienta (*tool*) de teoría de relaciones (RF_05). El profesor define un **conjunto
base** `S` y una **relación** `R ⊆ S×S` concreta (pares ordenados). El estudiante (1)
**construye** esa relación en una **representación** y (2) **declara** cuáles de las cuatro
**propiedades** tiene. La corrección es *server-side* y evalúa ambas cosas.

### Representación (de una relación)
Una de las tres formas equivalentes de expresar la misma relación `R`: **matriz** (booleana
|S|×|S|), **grafo dirigido** (un arco `a→b` por cada par `(a,b)∈R`) o **pares ordenados**
(el listado explícito). El estudiante construye la relación en una de ellas.

### Propiedad (de una relación)
Una de las cuatro propiedades que el estudiante declara y el sistema verifica:
**reflexiva**, **simétrica**, **antisimétrica**, **transitiva**. Cada declaración incorrecta
recibe un **contraejemplo** (los pares concretos que la violan).
