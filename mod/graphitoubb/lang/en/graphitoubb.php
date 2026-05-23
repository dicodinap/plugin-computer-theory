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
