<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Cadenas de idioma en español para qtype_graphitoubb.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Identidad del plugin.
$string['pluginname']          = 'Tabla de verdad GraphitoUBB';
$string['pluginname_help']     = 'Tipo de pregunta en la que los estudiantes completan, comparan o clasifican tablas de verdad para fórmulas de lógica proposicional.';
$string['pluginnameadding']    = 'Añadiendo una pregunta de tabla de verdad GraphitoUBB';
$string['pluginnameediting']   = 'Editando una pregunta de tabla de verdad GraphitoUBB';
$string['pluginnamesummary']   = 'Los estudiantes completan, comparan o clasifican tablas de verdad usando el motor GraphitoUBB.';
$string['qtype_label']         = 'Tabla de verdad GraphitoUBB';

// Tipo de ejercicio.
$string['exercise_type']              = 'Tipo de ejercicio';
$string['exercise_type_complete']     = 'Completar la tabla';
$string['exercise_type_equivalence']  = 'Equivalencia (¿son equivalentes las dos fórmulas?)';
$string['exercise_type_classify']     = 'Clasificar la fórmula (tautología / contradicción / contingencia)';

// Campos de fórmula.
$string['formula']   = 'Fórmula';
$string['formula_1'] = 'Fórmula 1';
$string['formula_2'] = 'Fórmula 2';

// Campos de equivalencia.
$string['expected_equivalent'] = '¿Son equivalentes? (respuesta esperada)';

// Campos de clasificación.
$string['expected_class']              = 'Clasificación esperada';
$string['expected_class_tautology']    = 'Tautología';
$string['expected_class_contradiction'] = 'Contradicción';
$string['expected_class_contingency']  = 'Contingencia';

// Justificación con tabla.
$string['require_table_justification'] = 'Requerir justificación con tabla completa';

// Sección de puntuación.
$string['scoring_section']             = 'Puntuación';
$string['radio_weight']                = 'Peso de la respuesta de opción múltiple (%)';
$string['table_weight']                = 'Peso de la tabla (%)';
$string['wrong_radio_policy']          = 'Política cuando la respuesta de opción múltiple es incorrecta';
$string['wrong_radio_policy_strict']   = 'Estricto: puntaje = 0 si la respuesta es incorrecta';
$string['wrong_radio_policy_proportional'] = 'Proporcional: conservar crédito parcial de la tabla';

// Sección de retroalimentación.
$string['feedback_section'] = 'Retroalimentación de la respuesta';

// Privacidad.
$string['privacy:metadata'] = 'El tipo de pregunta Tabla de verdad GraphitoUBB almacena definiciones de preguntas en qtype_graphitoubb_options. Los datos de respuesta de los estudiantes son almacenados por el motor de preguntas de Moodle en las tablas de pasos de intentos.';
$string['privacy:metadata:qtype_graphitoubb_options'] = 'Almacena el payload del problema truth_table y la configuración de puntuación de cada pregunta. Esta tabla contiene solo contenido creado por el docente — sin datos personales.';

// Errores.
$string['err_missing_formula']    = 'Debe proporcionar al menos una fórmula para este tipo de ejercicio.';
$string['err_schema_validation']  = 'El payload del problema no pasó la validación de esquema: {$a}';
$string['err_internal']           = 'Ocurrió un error interno al guardar la pregunta. Por favor, inténtelo de nuevo.';

// Resumen de respuesta (sweep i18n §7.1).
$string['summary_equivalence'] = 'Equivalencia: {$a}';
$string['summary_classify']    = 'Clasificación: {$a}';
$string['summary_complete']    = 'Tabla de verdad enviada con {$a} filas';
$string['summary_rows']        = ' ({$a} filas)';
$string['summary_no_answer']   = '(sin respuesta)';
