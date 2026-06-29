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
 * Strings for component mod_graphitoubb — Spanish (es).
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addinstance'] = 'Agregar una nueva actividad GraphitoUBB';
$string['alphabet_add'] = 'Agregar';
$string['alphabet_input_aria'] = 'Nuevo símbolo (un carácter alfanumérico)';
$string['alphabet_label'] = 'Alfabeto:';
$string['attempt'] = 'Intento';
$string['attempt_finished'] = 'Intento finalizado.';
$string['attempt_inprogress'] = 'Intento en curso.';
$string['attempts_header'] = 'Intentos de estudiantes';
$string['back_to_activity'] = 'Volver a la actividad';
$string['col_finished'] = 'Finalizado';
$string['col_lastword'] = 'Última palabra probada';
$string['col_snapshots'] = 'Capturas';
$string['col_started'] = 'Iniciado';
$string['col_status'] = 'Estado';
$string['col_user'] = 'Estudiante';
$string['editor_loading'] = 'Cargando editor...';
$string['err_duplicate_transition'] = 'La transición {$a} ya existe (autómata determinista).';
$string['err_empty_alphabet'] = 'El alfabeto está vacío. Agrega símbolos antes de ejecutar el simulador.';
$string['err_input_too_long'] = 'La entrada excede la longitud máxima ({$a}).';
$string['err_max_alphabet'] = 'Se alcanzó el tamaño máximo del alfabeto ({$a}).';
$string['err_max_states'] = 'Se alcanzó el límite máximo de estados ({$a}).';
$string['err_max_transitions'] = 'Se alcanzó el límite máximo de transiciones ({$a}).';
$string['err_no_initial_state'] = 'No hay estado inicial definido. Define un estado inicial primero.';
$string['err_simulator_reject'] = 'Rechazado: {$a}';
$string['err_symbol_in_use'] = 'No se puede eliminar el símbolo: está siendo usado por transiciones existentes.';
$string['graphitoubb:addinstance'] = 'Agregar una nueva actividad GraphitoUBB';
$string['graphitoubb:attempt'] = 'Realizar un intento en una actividad GraphitoUBB';
$string['graphitoubb:view'] = 'Ver una actividad GraphitoUBB';
$string['graphitoubb:viewreport'] = 'Ver informe de actividad GraphitoUBB';
$string['graphitoubbname'] = 'Nombre de la actividad GraphitoUBB';
$string['graphitoubbname_help'] = 'Nombre que los estudiantes verán en esta actividad.';
$string['invalid_snapshot'] = 'El payload del snapshot no es JSON AFD válido.';
$string['modulename'] = 'GraphitoUBB';
$string['modulename_help'] = 'GraphitoUBB permite a los estudiantes construir y simular autómatas finitos.';
$string['modulenameplural'] = 'Actividades GraphitoUBB';
$string['no_attempt'] = 'Sin intento todavía.';
$string['no_attempts'] = 'Sin intentos todavía.';
$string['not_attempt_owner'] = 'Solo puedes modificar tu propio intento.';
$string['pluginname'] = 'GraphitoUBB';
$string['pluginadministration'] = 'Administración de la actividad GraphitoUBB';
$string['modulename'] = 'GraphitoUBB';
$string['modulenameplural'] = 'Actividades GraphitoUBB';
$string['modulename_help'] = 'Herramientas GraphitoUBB para Matemáticas Discretas y Teoría de la Computación.';
$string['privacy:metadata'] = 'GraphitoUBB almacena los intentos de los estudiantes en ejercicios de autómatas.';
$string['simulator_input_label'] = 'Cadena de entrada:';
$string['simulator_run'] = 'Ejecutar';
$string['snapshot_rate_limited'] = 'Snapshot guardado muy frecuentemente. Por favor espera.';
$string['start_your_attempt'] = 'Iniciar tu intento';
$string['toolbar_add_state'] = 'Agregar estado';
$string['toolbar_add_transition'] = 'Agregar transición';
$string['toolbar_delete'] = 'Eliminar';
$string['toolbar_label'] = 'Barra de herramientas del editor';
$string['toolbar_set_start'] = 'Definir estado inicial';
$string['toolbar_toggle_final'] = 'Alternar estado final';
$string['view'] = 'Ver';
$string['view_attempt'] = 'Ver intento';
$string['view_report'] = 'Ver informe';
$string['viewreport'] = 'Ver informe';
$string['warn_logword_failed'] = 'Error al registrar la palabra. Revisa tu conexión.';
$string['wordbank_empty'] = 'Ninguna palabra probada todavía.';

// Mejoras UX/UI — editor AFD + panel docente.
$string['save_indicator_saving'] = 'Guardando…';
$string['save_indicator_saved'] = 'Guardado ✓';
$string['save_indicator_error'] = 'Error al guardar ✕';
$string['mode_hint_idle'] = 'Elige una herramienta para empezar a construir tu autómata.';
$string['mode_hint_adding_state'] = 'Haz clic en el lienzo para colocar un nuevo estado.';
$string['mode_hint_adding_transition_source'] = 'Haz clic en el estado de origen de la transición.';
$string['mode_hint_adding_transition_target'] = 'Ahora haz clic en el estado de destino.';
$string['mode_hint_setting_start'] = 'Haz clic en el estado que será el estado inicial.';
$string['mode_hint_toggling_final'] = 'Haz clic en un estado para marcarlo como final (de aceptación).';
$string['mode_hint_deleting'] = 'Haz clic en un estado o transición para eliminarlo.';
$string['transition_symbol_prompt'] = 'Símbolo de transición (1 carácter alfanumérico):';
$string['zoom_in'] = 'Acercar';
$string['zoom_out'] = 'Alejar';
$string['zoom_fit'] = 'Ajustar autómata a la vista';
$string['zoom_reset'] = 'Restablecer zoom (100%)';
$string['legend_title'] = 'Leyenda';
$string['legend_start'] = 'Estado inicial (borde azul)';
$string['legend_final'] = 'Estado final (doble anillo)';
$string['legend_visited'] = 'Visitado durante la simulación';
$string['run_hint_needs_start'] = 'Define un estado inicial para ejecutar el simulador.';
$string['run_hint_needs_alphabet'] = 'Agrega al menos un símbolo al alfabeto para ejecutar el simulador.';
$string['run_hint_ready'] = 'Listo — escribe una palabra y ejecuta el simulador.';
$string['run_disabled_title'] = 'El autómata aún no está listo para ejecutarse.';
$string['sim_accepted'] = 'Aceptada';
$string['sim_rejected'] = 'Rechazada';
$string['word_empty'] = 'ε (vacía)';
$string['reset_modal_title'] = 'Reiniciar intentos del estudiante';
$string['reset_modal_body'] = '¿Reiniciar todos los intentos de {$a}? Esto elimina permanentemente sus capturas, palabras probadas y envíos en esta actividad. No se puede deshacer.';
$string['reset_confirm_button'] = 'Reiniciar intentos';
$string['reset_success'] = 'Intentos reiniciados para {$a}.';
$string['reset_error'] = 'No se pudieron reiniciar los intentos. Inténtalo de nuevo.';
$string['submit_grading'] = 'Corrigiendo…';

// Sweep i18n (§7.1) — editor de tabla de verdad, feedback, modal de conflicto, parser, panel.
$string['helpers_label'] = 'Operadores lógicos';
$string['op_negation'] = 'Negación ¬';
$string['op_conjunction'] = 'Conjunción ∧';
$string['op_disjunction'] = 'Disyunción ∨';
$string['op_implication'] = 'Implicación →';
$string['op_biconditional'] = 'Bicondicional ↔';
$string['op_xor'] = 'Disyunción exclusiva ⊕';
$string['op_true'] = 'Verdad ⊤';
$string['op_false'] = 'Falsedad ⊥';
$string['cell_aria'] = 'Fila {$a->row}, columna {$a->col}';
$string['radio_equivalence_legend'] = '¿Son lógicamente equivalentes?';
$string['radio_classify_legend'] = 'Clasificación de la fórmula';
$string['radio_yes'] = 'Sí';
$string['radio_no'] = 'No';
$string['class_tautology'] = 'Tautología';
$string['class_contradiction'] = 'Contradicción';
$string['class_contingency'] = 'Contingencia';
$string['feedback_location'] = 'Fila {$a}';
$string['feedback_submitted'] = 'enviado';
$string['feedback_expected'] = 'esperado';
$string['conflict_server_version'] = 'Versión del servidor: {$a}';
$string['conflict_your_version'] = 'Tu versión: {$a}';
$string['parse_unexpected_char'] = 'Carácter inesperado "{$a->ch}" en la posición {$a->pos}.';
$string['parse_expected_token'] = 'Se esperaba {$a->type} en la posición {$a->pos}, se encontró "{$a->val}".';
$string['parse_expected_operand'] = 'Se esperaba una variable, constante o "(" en la posición {$a->pos}, se encontró "{$a->val}".';
$string['parse_incomplete'] = 'Fórmula incompleta: carácter inesperado en la posición {$a}.';
$string['panel_row'] = 'Fila';
$string['panel_no_data'] = 'sin datos';
$string['panel_students_soon'] = '(Lista de alumnos disponible próximamente)';

// Entrega del AFD / finalizar intento (A2).
$string['afd_status_label'] = 'Estado:';
$string['afd_finish_button'] = 'Marcar como terminado';
$string['afd_finish_title'] = '¿Entregar tu autómata?';
$string['afd_finish_body'] = 'Una vez entregado no podrás editar este autómata.';
$string['afd_finish_confirm'] = 'Entregar';
$string['afd_finish_success'] = 'Autómata entregado.';
$string['afd_finish_error'] = 'No se pudo entregar. Inténtalo de nuevo.';

// Confirmaciones de acciones destructivas del AFD (A12 / G1).
$string['delete_confirm_title'] = '¿Eliminar estado?';
$string['delete_confirm_body'] = 'Este estado tiene {$a} transición(es) conectada(s). Al eliminarlo también se eliminarán.';
$string['delete_confirm_button'] = 'Eliminar';
$string['reset_automaton_button'] = 'Reiniciar autómata';
$string['reset_automaton_title'] = '¿Reiniciar el autómata?';
$string['reset_automaton_body'] = 'Esto elimina todos los estados, transiciones y símbolos del alfabeto. No se puede deshacer.';
$string['reset_automaton_confirm'] = 'Reiniciar';

// Autoría + calificación de AFD (C1).
$string['afd_consigna_title'] = 'Tu tarea';
$string['afd_result_score'] = 'Puntaje: {$a->correct}/{$a->total} palabras de prueba correctas ({$a->pct}%)';
$string['afd_result_passed'] = 'Aprobado';
$string['afd_result_invalid'] = 'No se pudo calificar tu autómata — asegúrate de definir un estado inicial.';
$string['afd_finish_graded_toast'] = 'Entregado — {$a->correct}/{$a->total} palabras de prueba correctas.';

// Cadenas iter1 — tabla de verdad.
$string['truth_table_editor_label'] = 'Editor de tabla de verdad';
$string['formula_label'] = 'Fórmula:';
$string['submit_button'] = 'Enviar respuesta';
$string['autosave_idle'] = 'Sin cambios sin guardar';
$string['autosave_saving'] = 'Guardando…';
$string['autosave_saved'] = 'Guardado {$a}';
$string['autosave_error'] = 'Error — reintentando…';
$string['conflict_title'] = 'Conflicto de versiones';
$string['conflict_load_other'] = 'Cargar la otra versión';
$string['conflict_overwrite'] = 'Sobrescribir con la mía';
$string['cap_submit_desc'] = 'Enviar una respuesta final en una actividad GraphitoUBB de tabla de verdad';
$string['cap_viewreports_desc'] = 'Ver informes de una actividad GraphitoUBB';
$string['cap_gradeother_desc'] = 'Calificar o sobrescribir manualmente una respuesta de estudiante';
$string['cap_manage_desc'] = 'Administrar (editar problema, configuración) una actividad GraphitoUBB';
$string['cap_reattempt_desc'] = 'Reiniciar los intentos de un estudiante en una actividad GraphitoUBB';
$string['err_max_variables'] = 'Se superó el número máximo de variables ({$a}).';
$string['err_max_formula_length'] = 'La fórmula supera la longitud máxima ({$a} caracteres).';
$string['err_invalid_formula'] = 'Fórmula inválida: {$a}';
$string['err_invalid_class'] = 'Clasificación inválida: {$a}';
$string['err_radio_required'] = 'Por favor selecciona una respuesta antes de enviar.';
$string['err_rate_limited'] = 'Demasiadas solicitudes de guardado automático. Espera un momento.';
$string['err_optimistic_lock'] = 'Tu borrador fue modificado en otro lugar. Por favor resuelve el conflicto.';
$string['feedback_cell_correct'] = 'Correcto';
$string['feedback_cell_incorrect'] = 'Incorrecto';
$string['feedback_cell_propagated'] = 'Error propagado desde otra celda';
$string['feedback_cell_empty'] = 'Sin respuesta';
$string['event_attempt_started'] = 'Intento GraphitoUBB iniciado';
$string['event_submission_submitted'] = 'Respuesta GraphitoUBB enviada';
$string['event_problem_updated'] = 'Problema GraphitoUBB actualizado';
$string['graphitoubb:submit'] = 'Enviar respuesta final en una actividad GraphitoUBB';
$string['graphitoubb:viewreports'] = 'Ver informes en una actividad GraphitoUBB';
$string['graphitoubb:gradeother'] = 'Calificar otra respuesta de estudiante en GraphitoUBB';
$string['graphitoubb:manage'] = 'Administrar una actividad GraphitoUBB';
$string['graphitoubb:reattempt'] = 'Reiniciar intentos de estudiante en GraphitoUBB';

// Metadatos de privacidad — extensiones iter1.
$string['privacy:metadata:graphitoubb_attempt:current_draft'] = 'Borrador de respuesta guardado automáticamente para un intento en curso (JSON).';
$string['privacy:metadata:graphitoubb_attempt:draft_updated_at'] = 'Marca de tiempo del último guardado automático del borrador.';

$string['privacy:metadata:graphitoubb_attempt'] = 'Intentos por usuario en una instancia de GraphitoUBB.';
$string['privacy:metadata:graphitoubb_attempt:status'] = 'Estado actual del intento.';
$string['privacy:metadata:graphitoubb_attempt:timefinished'] = 'Momento en que se finalizó el intento.';
$string['privacy:metadata:graphitoubb_attempt:timestarted'] = 'Momento en que se inició el intento.';
$string['privacy:metadata:graphitoubb_attempt:userid'] = 'Usuario que realizó el intento.';

$string['privacy:metadata:graphitoubb_snapshot'] = 'Capturas periódicas del estado del autómata construido por el estudiante.';
$string['privacy:metadata:graphitoubb_snapshot:payload'] = 'Estado del autómata serializado (JSON).';
$string['privacy:metadata:graphitoubb_snapshot:schema_version'] = 'Versión del esquema del payload.';
$string['privacy:metadata:graphitoubb_snapshot:timecreated'] = 'Momento en que se registró la captura.';

$string['privacy:metadata:graphitoubb_wordbank_log'] = 'Registro por intento de palabras ejecutadas sobre el autómata.';
$string['privacy:metadata:graphitoubb_wordbank_log:accepted'] = 'Si el autómata aceptó la palabra.';
$string['privacy:metadata:graphitoubb_wordbank_log:timecreated'] = 'Momento en que se registró la palabra.';
$string['privacy:metadata:graphitoubb_wordbank_log:word'] = 'Palabra de entrada simulada.';

$string['privacy:metadata:graphitoubb_submission'] = 'Respuestas finales calificadas enviadas por estudiantes en una actividad GraphitoUBB.';
$string['privacy:metadata:graphitoubb_submission:attemptid'] = 'Intento al que pertenece esta respuesta.';
$string['privacy:metadata:graphitoubb_submission:payload'] = 'Payload de la respuesta enviada (JSON).';
$string['privacy:metadata:graphitoubb_submission:payload_hash'] = 'Hash SHA-256 del payload enviado.';
$string['privacy:metadata:graphitoubb_submission:problem_snapshot_hash'] = 'SHA-256 del problema en el momento de la calificación.';
$string['privacy:metadata:graphitoubb_submission:score'] = 'Puntaje bruto otorgado por la respuesta.';
$string['privacy:metadata:graphitoubb_submission:fraction'] = 'Fracción de calificación (0–1) otorgada.';
$string['privacy:metadata:graphitoubb_submission:passed'] = 'Si la respuesta superó el umbral de aprobación.';
$string['privacy:metadata:graphitoubb_submission:grading_result'] = 'Detalle completo del resultado de calificación (JSON con retroalimentación por celda).';
$string['privacy:metadata:graphitoubb_submission:schema_version'] = 'Versión del esquema del payload de la respuesta.';
$string['privacy:metadata:graphitoubb_submission:timecreated'] = 'Momento en que se registró la respuesta.';

$string['privacy:metadata:graphitoubb_event'] = 'Eventos de telemetría registrados durante la interacción de un estudiante con una actividad GraphitoUBB.';
$string['privacy:metadata:graphitoubb_event:userid'] = 'Usuario que desencadenó el evento.';
$string['privacy:metadata:graphitoubb_event:instanceid'] = 'Instancia de la actividad donde ocurrió el evento.';
$string['privacy:metadata:graphitoubb_event:attemptid'] = 'Intento durante el cual se registró el evento (nulo para eventos previos al intento).';
$string['privacy:metadata:graphitoubb_event:name'] = 'Nombre del evento en formato legible por máquina.';
$string['privacy:metadata:graphitoubb_event:payload'] = 'Payload JSON opcional del evento.';
$string['privacy:metadata:graphitoubb_event:timecreated'] = 'Momento en que se registró el evento.';

// Panel docente — slice 5.
$string['panel_title']                   = 'Panel docente';
$string['panel_tab_summary']             = 'Resumen';
$string['panel_tab_per_student']         = 'Por alumno';
$string['panel_tab_heatmap']             = 'Mapa de calor';
$string['panel_tab_export']              = 'Exportar';
$string['kpi_enrolled']                  = 'Inscritos';
$string['kpi_attempted']                 = 'Intentaron';
$string['kpi_submitted']                 = 'Enviaron';
$string['kpi_with_draft']                = 'Con borrador';
$string['stat_avg']                      = 'Promedio';
$string['stat_median']                   = 'Mediana';
$string['stat_stddev']                   = 'Desviación estándar';
$string['stat_time_median']              = 'Tiempo mediano';
$string['stat_top_errors']               = 'Celdas con más errores';
$string['filter_all']                    = 'Todos';
$string['filter_with_errors']            = 'Con errores';
$string['filter_not_submitted']          = 'Sin enviar';
$string['col_student']                   = 'Alumno';
$string['col_score']                     = 'Nota';
$string['col_attempts']                  = 'Intentos';
$string['col_time']                      = 'Tiempo';
$string['col_status']                    = 'Estado';
$string['status_inprogress']             = 'En curso';
$string['status_finished']              = 'Finalizado';
$string['status_not_started']            = 'No iniciado';
$string['action_view_table']             = 'Ver tabla';
$string['action_reset_attempts']         = 'Resetear';
$string['action_reset_confirm']          = '¿Seguro que quieres resetear todos los intentos de';
$string['heatmap_legend']                = 'Leyenda de colores';
$string['heatmap_no_data']               = 'Aún no hay datos de envíos para mostrar.';
$string['heatmap_textual_alternative']   = 'Alternativa textual (tabla)';
$string['export_format']                 = 'Formato de exportación';
$string['export_scope']                  = 'Contenido a exportar';
$string['export_csv']                    = 'CSV';
$string['export_json']                   = 'JSON';
$string['export_pdf']                    = 'PDF';
$string['export_button']                 = 'Descargar exportación';
$string['error_loading_panel']           = 'Error al cargar los datos del panel. Actualiza la página e intenta de nuevo.';
$string['panel_hist_title']              = 'Distribución de notas';
$string['panel_hist_range']              = 'Rango';
$string['panel_hist_count']              = 'Cantidad';
$string['panel_drawer_score']            = 'Nota';
$string['panel_drawer_attempts']         = 'Intentos';
$string['panel_drawer_time']             = 'Tiempo';
$string['panel_drawer_status']           = 'Estado';
$string['panel_drawer_draft']            = 'Borrador';

$string['privacy:metadata:graphitoubb_grade_cache'] = 'Calificación agregada en caché por intento de estudiante tras aplicar la política de calificación.';
$string['privacy:metadata:graphitoubb_grade_cache:attemptid'] = 'Intento cuya calificación está en caché.';
$string['privacy:metadata:graphitoubb_grade_cache:score'] = 'Puntaje agregado después de aplicar la política.';
$string['privacy:metadata:graphitoubb_grade_cache:fraction'] = 'Fracción de calificación agregada después de aplicar la política.';
$string['privacy:metadata:graphitoubb_grade_cache:attempt_count'] = 'Número de respuestas consideradas en el agregado.';
$string['privacy:metadata:graphitoubb_grade_cache:policy_applied'] = 'Política de calificación aplicada (best, last o average).';
$string['privacy:metadata:graphitoubb_grade_cache:timemodified'] = 'Momento en que se actualizó por última vez la calificación en caché.';

// Tarea cron.
$string['task_cleanup_orphans'] = 'GraphitoUBB limpieza de registros huérfanos';

// Helpers para Behat — iter1.
$string['behat_invalid_cell_value'] = 'Valor de celda inválido "{$a}". Los valores aceptados son "V", "F" o "" (vacío).';
$string['behat_reset_confirm'] = '¿Resetear todos los intentos de este estudiante?';
