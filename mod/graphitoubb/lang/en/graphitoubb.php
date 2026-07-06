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
 * Strings for component mod_graphitoubb.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addinstance'] = 'Add a new GraphitoUBB activity';
$string['alphabet_add'] = 'Add';
$string['alphabet_input_aria'] = 'New symbol (single alphanumeric character)';
$string['alphabet_label'] = 'Alphabet:';
$string['attempt'] = 'Attempt';
$string['attempt_finished'] = 'Attempt finished.';
$string['attempt_inprogress'] = 'Attempt in progress.';
$string['attempts_header'] = 'Student attempts';
$string['back_to_activity'] = 'Back to activity';
$string['col_finished'] = 'Finished';
$string['col_lastword'] = 'Last word tested';
$string['col_snapshots'] = 'Snapshots';
$string['col_started'] = 'Started';
$string['col_status'] = 'Status';
$string['col_user'] = 'Student';
$string['editor_loading'] = 'Loading editor...';
$string['err_duplicate_transition'] = 'Transition {$a} already exists (deterministic automaton).';
$string['err_empty_alphabet'] = 'Alphabet is empty. Add symbols before running the simulator.';
$string['err_input_too_long'] = 'Input exceeds maximum length ({$a}).';
$string['err_max_alphabet'] = 'Maximum alphabet size ({$a}) reached.';
$string['err_max_states'] = 'Maximum states ({$a}) reached.';
$string['err_max_transitions'] = 'Maximum transitions ({$a}) reached.';
$string['err_no_initial_state'] = 'No initial state set. Please set a start state first.';
$string['err_simulator_reject'] = 'Rejected: {$a}';
$string['err_symbol_in_use'] = 'Cannot remove symbol: it is used by existing transitions.';
$string['graphitoubb:addinstance'] = 'Add a new GraphitoUBB activity';
$string['graphitoubb:attempt'] = 'Attempt on a GraphitoUBB activity';
$string['graphitoubb:view'] = 'View a GraphitoUBB activity';
$string['graphitoubb:viewreport'] = 'View GraphitoUBB activity report';
$string['graphitoubbname'] = 'GraphitoUBB activity name';
$string['graphitoubbname_help'] = 'Name shown to students for this GraphitoUBB activity.';
$string['invalid_snapshot'] = 'Snapshot payload is invalid AFD JSON.';
$string['modulename'] = 'GraphitoUBB';
$string['modulename_help'] = 'GraphitoUBB lets students build and simulate finite automata as exercises.';
$string['modulenameplural'] = 'GraphitoUBB activities';
$string['no_attempt'] = 'No attempt yet.';
$string['no_attempts'] = 'No attempts yet.';
$string['not_attempt_owner'] = 'You can only modify your own attempt.';
$string['pluginname'] = 'GraphitoUBB';
$string['pluginadministration'] = 'GraphitoUBB activity administration';
$string['modulename'] = 'GraphitoUBB';
$string['modulenameplural'] = 'GraphitoUBB activities';
$string['modulename_help'] = 'GraphitoUBB tools for Discrete Mathematics and Computer Theory.';
$string['privacy:metadata'] = 'GraphitoUBB stores student attempts on automata exercises.';
$string['privacy:metadata:graphitoubb_attempt'] = 'Per-user attempts on a GraphitoUBB instance.';
$string['privacy:metadata:graphitoubb_attempt:status'] = 'Current status of the attempt.';
$string['privacy:metadata:graphitoubb_attempt:timefinished'] = 'When the attempt was finished.';
$string['privacy:metadata:graphitoubb_attempt:timestarted'] = 'When the attempt was started.';
$string['privacy:metadata:graphitoubb_attempt:userid'] = 'The user who made the attempt.';
$string['privacy:metadata:graphitoubb_snapshot'] = 'Periodic snapshots of the student-built automaton.';
$string['privacy:metadata:graphitoubb_snapshot:payload'] = 'Serialized automaton payload (JSON).';
$string['privacy:metadata:graphitoubb_snapshot:schema_version'] = 'Schema version of the payload.';
$string['privacy:metadata:graphitoubb_snapshot:timecreated'] = 'When the snapshot was recorded.';
$string['privacy:metadata:graphitoubb_wordbank_log'] = 'Per-attempt log of words run against the automaton.';
$string['privacy:metadata:graphitoubb_wordbank_log:accepted'] = 'Whether the word was accepted by the automaton.';
$string['privacy:metadata:graphitoubb_wordbank_log:timecreated'] = 'When the word was logged.';
$string['privacy:metadata:graphitoubb_wordbank_log:word'] = 'The input word that was simulated.';
$string['simulator_input_label'] = 'Input string:';
$string['simulator_run'] = 'Run';
$string['snapshot_rate_limited'] = 'Snapshot saved too frequently. Please wait.';
$string['start_your_attempt'] = 'Start your own attempt';
$string['toolbar_add_state'] = 'Add state';
$string['toolbar_add_transition'] = 'Add transition';
$string['toolbar_delete'] = 'Delete';
$string['toolbar_label'] = 'Editor toolbar';
$string['toolbar_set_start'] = 'Set start state';
$string['toolbar_toggle_final'] = 'Toggle final state';
$string['view'] = 'View';
$string['view_attempt'] = 'View attempt';
$string['view_report'] = 'View report';
$string['viewreport'] = 'View report';
$string['toolbar_add_state'] = 'Add state';
$string['toolbar_add_transition'] = 'Add transition';
$string['toolbar_delete'] = 'Delete';
$string['toolbar_label'] = 'Editor toolbar';
$string['toolbar_set_start'] = 'Set start state';
$string['toolbar_toggle_final'] = 'Toggle final state';
$string['warn_logword_failed'] = 'Word log failed. Check your connection.';
$string['wordbank_empty'] = 'No words tested yet.';

// UX/UI improvements — AFD editor + teacher panel.
$string['save_indicator_saving'] = 'Saving…';
$string['save_indicator_saved'] = 'Saved ✓';
$string['save_indicator_error'] = 'Save failed ✕';
$string['mode_hint_idle'] = 'Pick a tool to start building your automaton.';
$string['mode_hint_adding_state'] = 'Click on the canvas to place a new state.';
$string['mode_hint_adding_transition_source'] = 'Click the source state of the transition.';
$string['mode_hint_adding_transition_target'] = 'Now click the target state.';
$string['mode_hint_setting_start'] = 'Click the state to mark as the start state.';
$string['mode_hint_toggling_final'] = 'Click a state to toggle it as a final (accepting) state.';
$string['mode_hint_deleting'] = 'Click a state or transition to delete it.';
$string['transition_symbol_prompt'] = 'Transition symbol (1 alphanumeric character):';
$string['zoom_in'] = 'Zoom in';
$string['zoom_out'] = 'Zoom out';
$string['zoom_fit'] = 'Fit automaton to view';
$string['zoom_reset'] = 'Reset zoom (100%)';
$string['legend_title'] = 'Legend';
$string['legend_start'] = 'Start state (blue border)';
$string['legend_final'] = 'Final state (double ring)';
$string['legend_visited'] = 'Visited during simulation';
$string['run_hint_needs_start'] = 'Set a start state to run the simulator.';
$string['run_hint_needs_alphabet'] = 'Add at least one alphabet symbol to run the simulator.';
$string['run_hint_ready'] = 'Ready — type a word and run the simulator.';
$string['run_disabled_title'] = 'The automaton is not ready to run yet.';
$string['sim_accepted'] = 'Accepted';
$string['sim_rejected'] = 'Rejected';
$string['word_empty'] = 'ε (empty)';
$string['reset_modal_title'] = 'Reset student attempts';
$string['reset_modal_body'] = 'Reset all attempts for {$a}? This permanently deletes their snapshots, tested words and submissions for this activity. This cannot be undone.';
$string['reset_confirm_button'] = 'Reset attempts';
$string['reset_success'] = 'Attempts reset for {$a}.';
$string['reset_error'] = 'Could not reset attempts. Please try again.';
$string['submit_grading'] = 'Grading…';

// i18n sweep (§7.1) — truth_table editor, feedback, conflict modal, parser, panel.
$string['helpers_label'] = 'Logical operators';
$string['op_negation'] = 'Negation ¬';
$string['op_conjunction'] = 'Conjunction ∧';
$string['op_disjunction'] = 'Disjunction ∨';
$string['op_implication'] = 'Implication →';
$string['op_biconditional'] = 'Biconditional ↔';
$string['op_xor'] = 'Exclusive or ⊕';
$string['op_true'] = 'True ⊤';
$string['op_false'] = 'False ⊥';
$string['cell_aria'] = 'Row {$a->row}, column {$a->col}';
$string['radio_equivalence_legend'] = 'Are they logically equivalent?';
$string['radio_classify_legend'] = 'Classify the formula';
$string['radio_yes'] = 'Yes';
$string['radio_no'] = 'No';
$string['class_tautology'] = 'Tautology';
$string['class_contradiction'] = 'Contradiction';
$string['class_contingency'] = 'Contingency';
$string['feedback_location'] = 'Row {$a}';
$string['feedback_submitted'] = 'submitted';
$string['feedback_expected'] = 'expected';
$string['conflict_server_version'] = 'Server version: {$a}';
$string['conflict_your_version'] = 'Your version: {$a}';
$string['parse_unexpected_char'] = 'Unexpected character "{$a->ch}" at position {$a->pos}.';
$string['parse_expected_token'] = 'Expected {$a->type} at position {$a->pos}, found "{$a->val}".';
$string['parse_expected_operand'] = 'Expected a variable, constant or "(" at position {$a->pos}, found "{$a->val}".';
$string['parse_incomplete'] = 'Incomplete formula: unexpected character at position {$a}.';
$string['panel_row'] = 'Row';
$string['panel_no_data'] = 'no data';
$string['panel_students_soon'] = '(Student list coming soon)';

// AFD submission / finish attempt (A2).
$string['afd_status_label'] = 'Status:';
$string['afd_finish_button'] = 'Mark as finished';
$string['afd_finish_title'] = 'Submit your automaton?';
$string['afd_finish_body'] = 'Once submitted you will not be able to edit this automaton.';
$string['afd_finish_confirm'] = 'Submit';
$string['afd_finish_success'] = 'Automaton submitted.';
$string['afd_finish_error'] = 'Could not submit. Please try again.';

// AFD destructive-action confirmations (A12 / G1).
$string['delete_confirm_title'] = 'Delete state?';
$string['delete_confirm_body'] = 'This state has {$a} connected transition(s). Deleting it will also remove them.';
$string['delete_confirm_button'] = 'Delete';
$string['reset_automaton_button'] = 'Reset automaton';
$string['reset_automaton_title'] = 'Reset the automaton?';
$string['reset_automaton_body'] = 'This removes all states, transitions and alphabet symbols. This cannot be undone.';
$string['reset_automaton_confirm'] = 'Reset';

// AFD authoring + grading (C1).
$string['afd_consigna_title'] = 'Your task';
$string['consigna_accepts'] = 'Should accept';
$string['consigna_rejects'] = 'Should reject';
$string['consigna_grading_info'] = 'When you submit, your automaton is checked against several hidden test words. You need {$a}% correct to pass.';
$string['afd_result_score'] = 'Score: {$a->correct}/{$a->total} test words correct ({$a->pct}%)';
$string['afd_result_passed'] = 'Passed';
$string['afd_result_invalid'] = 'Your automaton could not be graded — make sure it has a start state.';
$string['afd_finish_graded_toast'] = 'Submitted — {$a->correct}/{$a->total} test words correct.';

// AFD editor onboarding + affordances (A13 / A7 / H3).
$string['help_panel_title'] = 'How does this editor work?';
$string['help_step_states'] = 'Add states with "Add state", then click the canvas to place each one.';
$string['help_step_transition'] = 'For a transition, choose "Add transition", click the source state, then the target state, and type the symbol.';
$string['help_step_start'] = 'Mark the start state with "Set start state".';
$string['help_step_final'] = 'Mark accepting states with "Toggle final state".';
$string['help_step_run'] = 'Type a word and press "Run" to test whether your automaton accepts it.';
$string['help_step_rename'] = 'Double-click a state to rename it (e.g. "even", "odd").';
$string['tidy_button'] = 'Tidy layout';
$string['tidy_tooltip'] = 'Auto-arrange the states so they do not overlap.';
$string['tooltip_reset'] = 'Clear all states, transitions and alphabet symbols.';
$string['rename_state_title'] = 'Rename state';
$string['rename_state_label'] = 'State label';

// A10: step-by-step trace playback controls.
$string['trace_controls_label'] = 'Trace playback';
$string['trace_first'] = 'First step';
$string['trace_prev'] = 'Previous step';
$string['trace_play'] = 'Play';
$string['trace_pause'] = 'Pause';
$string['trace_next'] = 'Next step';
$string['trace_last'] = 'Last step';
$string['trace_step'] = 'Step {$a->i} of {$a->n}';

// A4: undo / redo.
$string['undo_button'] = 'Undo';
$string['redo_button'] = 'Redo';
$string['undo_tooltip'] = 'Undo (Ctrl+Z)';
$string['redo_tooltip'] = 'Redo (Ctrl+Y)';

// A14/E1: keyboard-accessible form alternative to the pointer-driven canvas.
$string['kbd_panel_title'] = 'Keyboard controls';
$string['kbd_panel_hint'] = 'Build the automaton without the mouse: add states, then set start/final, delete, or add transitions by choosing them here.';
$string['kbd_state_label'] = 'State';
$string['kbd_from'] = 'From';
$string['kbd_to'] = 'To';
$string['kbd_symbol'] = 'Symbol';

// H2: section headings for visual hierarchy.
$string['section_simulator'] = 'Simulator';
$string['section_wordbank'] = 'Tested words';

// Truth-table iter1 strings.
$string['truth_table_editor_label'] = 'Truth table editor';
$string['formula_label'] = 'Formula:';
$string['submit_button'] = 'Submit answer';
$string['autosave_idle'] = 'No unsaved changes';
$string['autosave_saving'] = 'Saving…';
$string['autosave_saved'] = 'Saved {$a}';
$string['autosave_error'] = 'Error — retrying…';
$string['conflict_title'] = 'Unsaved changes conflict';
$string['conflict_load_other'] = 'Load the other version';
$string['conflict_overwrite'] = 'Overwrite with mine';
$string['cap_submit_desc'] = 'Submit a final answer on a GraphitoUBB truth-table activity';
$string['cap_viewreports_desc'] = 'View reports for a GraphitoUBB activity';
$string['cap_gradeother_desc'] = 'Manually grade or override a student submission';
$string['cap_manage_desc'] = 'Manage (edit problem, settings) a GraphitoUBB activity';
$string['cap_reattempt_desc'] = 'Reset a student\'s attempts on a GraphitoUBB activity';
$string['err_max_variables'] = 'Maximum number of variables ({$a}) exceeded.';
$string['err_max_formula_length'] = 'Formula exceeds maximum length ({$a} characters).';
$string['err_invalid_formula'] = 'Invalid formula: {$a}';
$string['err_invalid_class'] = 'Invalid classification: {$a}';
$string['err_radio_required'] = 'Please select an answer before submitting.';
$string['err_rate_limited'] = 'Too many autosave requests. Please wait a moment.';
$string['err_optimistic_lock'] = 'Your draft was modified elsewhere. Please resolve the conflict.';
$string['feedback_cell_correct'] = 'Correct';
$string['feedback_cell_incorrect'] = 'Incorrect';
$string['feedback_cell_propagated'] = 'Error propagated from another cell';
$string['feedback_cell_empty'] = 'No answer provided';
$string['event_attempt_started'] = 'GraphitoUBB attempt started';
$string['event_submission_submitted'] = 'GraphitoUBB answer submitted';
$string['event_problem_updated'] = 'GraphitoUBB problem updated';
$string['graphitoubb:submit'] = 'Submit a final answer on a GraphitoUBB activity';
$string['graphitoubb:viewreports'] = 'View reports on a GraphitoUBB activity';
$string['graphitoubb:gradeother'] = 'Grade another student\'s GraphitoUBB submission';
$string['graphitoubb:manage'] = 'Manage a GraphitoUBB activity';
$string['graphitoubb:reattempt'] = 'Reset student attempts on a GraphitoUBB activity';

// Privacy metadata — iter1 extensions.
$string['privacy:metadata:graphitoubb_attempt:current_draft'] = 'Autosaved draft answer JSON for an in-progress attempt.';
$string['privacy:metadata:graphitoubb_attempt:draft_updated_at'] = 'Timestamp of the last autosave for the attempt draft.';

// Teacher panel — slice 5.
$string['panel_title']                   = 'Teacher panel';
$string['panel_tab_summary']             = 'Summary';
$string['panel_tab_per_student']         = 'Per student';
$string['panel_tab_heatmap']             = 'Heatmap';
$string['panel_tab_export']              = 'Export';
$string['kpi_enrolled']                  = 'Enrolled';
$string['kpi_attempted']                 = 'Attempted';
$string['kpi_submitted']                 = 'Submitted';
$string['kpi_with_draft']                = 'With draft';
$string['stat_avg']                      = 'Average score';
$string['stat_median']                   = 'Median score';
$string['stat_stddev']                   = 'Std deviation';
$string['stat_time_median']              = 'Median time';
$string['stat_top_errors']               = 'Top error cells';
$string['filter_all']                    = 'All';
$string['filter_with_errors']            = 'With errors';
$string['filter_not_submitted']          = 'Not submitted';
$string['col_student']                   = 'Student';
$string['col_score']                     = 'Score';
$string['col_attempts']                  = 'Attempts';
$string['col_time']                      = 'Time spent';
$string['col_status']                    = 'Status';
$string['status_inprogress']             = 'In progress';
$string['status_finished']               = 'Finished';
$string['status_not_started']            = 'Not started';
$string['action_view_table']             = 'View table';
$string['action_reset_attempts']         = 'Reset';
$string['action_reset_confirm']          = 'Are you sure you want to reset all attempts for';
$string['heatmap_legend']                = 'Colour legend';
$string['heatmap_no_data']               = 'No submission data to display yet.';
$string['heatmap_textual_alternative']   = 'Textual alternative (table)';
$string['export_format']                 = 'Export format';
$string['export_scope']                  = 'Content scope';
$string['export_csv']                    = 'CSV';
$string['export_json']                   = 'JSON';
$string['export_pdf']                    = 'PDF';
$string['export_button']                 = 'Download export';
$string['error_loading_panel']           = 'Error loading panel data. Please refresh and try again.';
$string['panel_loading']                 = 'Loading…';
$string['panel_hist_title']              = 'Score distribution';
$string['panel_hist_range']              = 'Range';
$string['panel_hist_count']              = 'Count';
$string['panel_drawer_score']            = 'Score';
$string['panel_drawer_attempts']         = 'Attempts';
$string['panel_drawer_time']             = 'Time';
$string['panel_drawer_status']           = 'Status';
$string['panel_drawer_draft']            = 'Draft';

$string['privacy:metadata:graphitoubb_submission'] = 'Final graded submissions made by students on a GraphitoUBB activity.';
$string['privacy:metadata:graphitoubb_submission:attemptid'] = 'The attempt this submission belongs to.';
$string['privacy:metadata:graphitoubb_submission:payload'] = 'The submitted answer payload (JSON).';
$string['privacy:metadata:graphitoubb_submission:payload_hash'] = 'SHA-256 hash of the submitted payload.';
$string['privacy:metadata:graphitoubb_submission:problem_snapshot_hash'] = 'SHA-256 of the problem definition at grading time.';
$string['privacy:metadata:graphitoubb_submission:score'] = 'Raw score awarded for the submission.';
$string['privacy:metadata:graphitoubb_submission:fraction'] = 'Grade fraction (0–1) awarded for the submission.';
$string['privacy:metadata:graphitoubb_submission:passed'] = 'Whether the submission met the passing threshold.';
$string['privacy:metadata:graphitoubb_submission:grading_result'] = 'Full grading result detail JSON (per-cell feedback).';
$string['privacy:metadata:graphitoubb_submission:schema_version'] = 'Schema version of the submission payload.';
$string['privacy:metadata:graphitoubb_submission:timecreated'] = 'When the submission was recorded.';

$string['privacy:metadata:graphitoubb_event'] = 'Telemetry events logged during a student interaction with a GraphitoUBB activity.';
$string['privacy:metadata:graphitoubb_event:userid'] = 'The user who triggered the event.';
$string['privacy:metadata:graphitoubb_event:instanceid'] = 'The activity instance where the event occurred.';
$string['privacy:metadata:graphitoubb_event:attemptid'] = 'The attempt during which the event was logged (nullable for pre-attempt events).';
$string['privacy:metadata:graphitoubb_event:name'] = 'Machine-readable event name.';
$string['privacy:metadata:graphitoubb_event:payload'] = 'Optional event payload JSON.';
$string['privacy:metadata:graphitoubb_event:timecreated'] = 'When the event was logged.';

$string['privacy:metadata:graphitoubb_grade_cache'] = 'Cached aggregate grade per student attempt after the grading policy is applied.';
$string['privacy:metadata:graphitoubb_grade_cache:attemptid'] = 'The attempt whose grade is cached.';
$string['privacy:metadata:graphitoubb_grade_cache:score'] = 'Aggregate score after policy is applied.';
$string['privacy:metadata:graphitoubb_grade_cache:fraction'] = 'Aggregate grade fraction after policy is applied.';
$string['privacy:metadata:graphitoubb_grade_cache:attempt_count'] = 'Number of submissions counted in the aggregate.';
$string['privacy:metadata:graphitoubb_grade_cache:policy_applied'] = 'The grading policy used (best, last, or average).';
$string['privacy:metadata:graphitoubb_grade_cache:timemodified'] = 'When the cached grade was last updated.';

// Cron task.
$string['task_cleanup_orphans'] = 'GraphitoUBB cleanup orphans';

// Behat helpers — iter1.
$string['behat_invalid_cell_value'] = 'Invalid cell value "{$a}". Accepted values are "V", "F", or "" (empty).';
$string['behat_reset_confirm'] = 'Reset all attempts for this student?';

// Preset catalogue (banco de ejercicios pre-instalados).
$string['preset_catalog_title'] = 'Exercise catalogue (ready-made templates)';
$string['preset_catalog_help'] = 'Pick a curated exercise to preload the form below. You can edit it before saving.';
$string['preset_group_afd'] = 'AFD — finite automata';
$string['preset_group_truth_table'] = 'Truth tables (logic)';
$string['preset_load'] = 'Load';
$string['preset_loaded'] = 'Template loaded: "{$a}". Review the fields below and click Save problem.';
$string['preset_difficulty_easy'] = 'Easy';
$string['preset_difficulty_medium'] = 'Medium';
$string['preset_difficulty_hard'] = 'Hard';

// grafo / arbol (graph & tree tools).
$string['preset_group_grafo'] = 'Grafo (graph theory)';
$string['preset_group_arbol'] = 'Árbol (trees & BST)';
$string['graph_consigna_title'] = 'Task';
$string['graph_consigna_grading_info'] = 'When you submit, your answer is graded automatically. You need {$a}% to pass.';
$string['graph_type_construct'] = 'Construction';
$string['graph_type_decision'] = 'Decision';
$string['graph_type_traversal'] = 'Traversal';
$string['graph_type_bst_build'] = 'BST construction';
$string['graph_type_traversal_answer'] = 'Tree traversal';
$string['graph_type_reconstruct'] = 'Tree reconstruction';
$string['graph_result_score'] = 'Score: {$a->correct} / {$a->total} checks ({$a->pct}%)';
$string['graph_result_passed'] = 'Passed';
$string['graph_result_invalid'] = 'Your answer could not be graded (empty or incomplete). Score 0.';
$string['graph_decision_yes'] = 'Yes';
$string['graph_decision_no'] = 'No';
$string['graph_help_title'] = 'How to use the graph editor';
$string['graph_help_addnode'] = 'Add vertex: click "Add vertex", then click an empty spot on the canvas.';
$string['graph_help_addedge'] = 'Add edge: click "Add edge", then click the two vertices to connect.';
$string['graph_help_setroot'] = 'Set root: click "Set root", then click the node that is the root.';
$string['graph_help_rename'] = 'Rename: double-click a vertex to change its label.';
$string['graph_help_delete'] = 'Delete: click "Delete", then click the vertex or edge to remove.';
$string['graph_toolbar_label'] = 'Graph editor tools';
$string['graph_btn_addnode'] = 'Add vertex';
$string['graph_btn_addedge'] = 'Add edge';
$string['graph_btn_setroot'] = 'Set root';
$string['graph_btn_delete'] = 'Delete';
$string['graph_btn_tidy'] = 'Tidy';
$string['graph_btn_clear'] = 'Clear';
$string['graph_hint_idle'] = 'Pick a tool above, then click on the canvas.';
$string['graph_hint_addnode'] = 'Click an empty spot to add a vertex.';
$string['graph_hint_addedge'] = 'Click the source vertex, then the target vertex.';
$string['graph_hint_setroot'] = 'Click the vertex that should be the root.';
$string['graph_hint_delete'] = 'Click a vertex or edge to delete it.';
$string['graph_rename_title'] = 'Rename vertex';
$string['graph_rename_label'] = 'Vertex label';
$string['graph_pick_side'] = 'Which child side?';
$string['graph_clear_title'] = 'Clear canvas';
$string['graph_clear_body'] = 'Remove all vertices and edges? This cannot be undone.';
$string['graph_answer_decision_legend'] = 'Your answer';
$string['graph_answer_traversal_legend'] = 'Trace your walk';
$string['graph_answer_traversal_help'] = 'Click the vertices on the canvas in the order you would visit them. Each step uses an edge between the two vertices. Use Undo to step back.';
$string['graph_answer_walk_label'] = 'Your walk:';
$string['graph_answer_undo'] = 'Undo last';
$string['graph_answer_clear'] = 'Clear walk';
$string['graph_walk_hint_next'] = 'Now click the next vertex connected to the last one.';
$string['graph_walk_hint_notedge'] = 'There is no unused edge between those two vertices — pick an adjacent vertex.';
$string['graph_answer_sequence_legend'] = 'Your sequence';
$string['graph_answer_sequence_placeholder'] = 'e.g. 1, 3, 6, 8, 10';
$string['graph_finish_reload'] = 'Answer submitted and graded.';
$string['err_graph_max_nodes'] = 'You have reached the maximum of {$a} vertices.';
$string['err_graph_max_edges'] = 'You have reached the maximum of {$a} edges.';
$string['err_tree_two_children'] = 'A node can have at most two children (left and right).';
