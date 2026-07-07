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
$string['consigna_accepts'] = 'Debe aceptar';
$string['consigna_rejects'] = 'Debe rechazar';
$string['consigna_grading_info'] = 'Al entregar, tu autómata se prueba contra varias palabras ocultas. Necesitas {$a}% correcto para aprobar.';
$string['afd_result_score'] = 'Puntaje: {$a->correct}/{$a->total} palabras de prueba correctas ({$a->pct}%)';
$string['afd_result_passed'] = 'Aprobado';
$string['afd_result_invalid'] = 'No se pudo calificar tu autómata — asegúrate de definir un estado inicial.';
$string['afd_finish_graded_toast'] = 'Entregado — {$a->correct}/{$a->total} palabras de prueba correctas.';

// Onboarding y affordances del editor AFD (A13 / A7 / H3).
$string['help_panel_title'] = '¿Cómo funciona este editor?';
$string['help_step_states'] = 'Agrega estados con "Agregar estado" y haz clic en el lienzo para colocar cada uno.';
$string['help_step_transition'] = 'Para una transición, elige "Agregar transición", haz clic en el estado de origen, luego en el de destino, y escribe el símbolo.';
$string['help_step_start'] = 'Marca el estado inicial con "Definir estado inicial".';
$string['help_step_final'] = 'Marca los estados de aceptación con "Alternar estado final".';
$string['help_step_run'] = 'Escribe una palabra y pulsa "Ejecutar" para probar si tu autómata la acepta.';
$string['help_step_rename'] = 'Haz doble clic en un estado para renombrarlo (p. ej. "par", "impar").';
$string['tidy_button'] = 'Ordenar';
$string['tidy_tooltip'] = 'Reorganiza los estados automáticamente para que no se solapen.';
$string['tooltip_reset'] = 'Elimina todos los estados, transiciones y símbolos del alfabeto.';
$string['rename_state_title'] = 'Renombrar estado';
$string['rename_state_label'] = 'Etiqueta del estado';

// A10: controles de reproducción de la traza paso a paso.
$string['trace_controls_label'] = 'Reproducción de la traza';
$string['trace_first'] = 'Primer paso';
$string['trace_prev'] = 'Paso anterior';
$string['trace_play'] = 'Reproducir';
$string['trace_pause'] = 'Pausar';
$string['trace_next'] = 'Paso siguiente';
$string['trace_last'] = 'Último paso';
$string['trace_step'] = 'Paso {$a->i} de {$a->n}';

// A4: deshacer / rehacer.
$string['undo_button'] = 'Deshacer';
$string['redo_button'] = 'Rehacer';
$string['undo_tooltip'] = 'Deshacer (Ctrl+Z)';
$string['redo_tooltip'] = 'Rehacer (Ctrl+Y)';

// A14/E1: alternativa por formulario, operable por teclado, al lienzo con puntero.
$string['kbd_panel_title'] = 'Controles por teclado';
$string['kbd_panel_hint'] = 'Construye el autómata sin ratón: agrega estados y luego define inicial/final, elimina o agrega transiciones eligiéndolos aquí.';
$string['kbd_state_label'] = 'Estado';
$string['kbd_from'] = 'Desde';
$string['kbd_to'] = 'Hasta';
$string['kbd_symbol'] = 'Símbolo';

// H2: encabezados de sección para jerarquía visual.
$string['section_simulator'] = 'Simulador';
$string['section_wordbank'] = 'Palabras probadas';

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
$string['panel_loading']                 = 'Cargando…';
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

// Catálogo de plantillas (banco de ejercicios pre-instalados).
$string['preset_catalog_title'] = 'Catálogo de ejercicios (plantillas listas para usar)';
$string['preset_catalog_help'] = 'Elige un ejercicio curado para precargar el formulario de abajo. Puedes editarlo antes de guardar.';
$string['preset_group_afd'] = 'AFD — autómatas finitos';
$string['preset_group_truth_table'] = 'Tablas de verdad (lógica)';
$string['preset_load'] = 'Cargar';
$string['preset_loaded'] = 'Plantilla cargada: «{$a}». Revisa los campos de abajo y pulsa Guardar problema.';
$string['preset_difficulty_easy'] = 'Fácil';
$string['preset_difficulty_medium'] = 'Media';
$string['preset_difficulty_hard'] = 'Difícil';

// grafo / arbol (herramientas de grafos y árboles).
$string['preset_group_grafo'] = 'Grafo (teoría de grafos)';
$string['preset_group_arbol'] = 'Árbol (árboles y ABB)';
$string['graph_consigna_title'] = 'Consigna';
$string['graph_consigna_grading_info'] = 'Al entregar, tu respuesta se califica automáticamente. Necesitas un {$a}% para aprobar.';
$string['graph_type_construct'] = 'Construcción';
$string['graph_type_decision'] = 'Decisión';
$string['graph_type_traversal'] = 'Recorrido';
$string['graph_type_bst_build'] = 'Construcción de ABB';
$string['graph_type_traversal_answer'] = 'Recorrido de árbol';
$string['graph_type_reconstruct'] = 'Reconstrucción de árbol';
$string['graph_result_score'] = 'Puntaje: {$a->correct} / {$a->total} comprobaciones ({$a->pct}%)';
$string['graph_result_passed'] = 'Aprobado';
$string['graph_result_invalid'] = 'Tu respuesta no se pudo calificar (vacía o incompleta). Puntaje 0.';
$string['graph_decision_yes'] = 'Sí';
$string['graph_decision_no'] = 'No';
$string['graph_help_title'] = 'Cómo usar el editor de grafos';
$string['graph_help_addnode'] = 'Agregar vértice: pulsa «Agregar vértice» y luego haz clic en un lugar vacío del lienzo.';
$string['graph_help_addedge'] = 'Agregar arista: pulsa «Agregar arista» y luego haz clic en los dos vértices a conectar.';
$string['graph_help_setroot'] = 'Fijar raíz: pulsa «Fijar raíz» y luego haz clic en el nodo raíz.';
$string['graph_help_rename'] = 'Renombrar: haz doble clic en un vértice para cambiar su etiqueta.';
$string['graph_help_delete'] = 'Eliminar: pulsa «Eliminar» y luego haz clic en el vértice o arista a quitar.';
$string['graph_toolbar_label'] = 'Herramientas del editor de grafos';
$string['graph_btn_addnode'] = 'Agregar vértice';
$string['graph_btn_addedge'] = 'Agregar arista';
$string['graph_btn_setroot'] = 'Fijar raíz';
$string['graph_btn_delete'] = 'Eliminar';
$string['graph_btn_tidy'] = 'Ordenar';
$string['graph_btn_clear'] = 'Limpiar';
$string['graph_hint_idle'] = 'Elige una herramienta arriba y luego haz clic en el lienzo.';
$string['graph_hint_addnode'] = 'Haz clic en un lugar vacío para agregar un vértice.';
$string['graph_hint_addedge'] = 'Haz clic en el vértice de origen y luego en el de destino.';
$string['graph_hint_setroot'] = 'Haz clic en el vértice que será la raíz.';
$string['graph_hint_delete'] = 'Haz clic en un vértice o arista para eliminarlo.';
$string['graph_rename_title'] = 'Renombrar vértice';
$string['graph_rename_label'] = 'Etiqueta del vértice';
$string['graph_pick_side'] = '¿Qué lado del hijo?';
$string['graph_clear_title'] = 'Limpiar lienzo';
$string['graph_clear_body'] = '¿Quitar todos los vértices y aristas? Esto no se puede deshacer.';
$string['graph_answer_decision_legend'] = 'Tu respuesta';
$string['graph_answer_traversal_legend'] = 'Traza tu recorrido';
$string['graph_answer_traversal_help'] = 'Haz clic en los vértices del lienzo en el orden en que los visitarías. Cada paso usa una arista entre los dos vértices. Usa Deshacer para retroceder.';
$string['graph_answer_walk_label'] = 'Tu recorrido:';
$string['graph_answer_undo'] = 'Deshacer último';
$string['graph_answer_clear'] = 'Limpiar recorrido';
$string['graph_walk_hint_next'] = 'Ahora haz clic en el siguiente vértice conectado al último.';
$string['graph_walk_hint_notedge'] = 'No hay una arista sin usar entre esos dos vértices — elige un vértice adyacente.';
$string['graph_answer_sequence_legend'] = 'Tu secuencia';
$string['graph_answer_sequence_placeholder'] = 'p. ej. 1, 3, 6, 8, 10';
$string['graph_finish_reload'] = 'Respuesta entregada y calificada.';
$string['err_graph_max_nodes'] = 'Has alcanzado el máximo de {$a} vértices.';
$string['err_graph_max_edges'] = 'Has alcanzado el máximo de {$a} aristas.';
$string['err_tree_two_children'] = 'Un nodo puede tener como máximo dos hijos (izquierdo y derecho).';
