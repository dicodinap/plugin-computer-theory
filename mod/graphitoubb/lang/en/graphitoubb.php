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
$string['toolbar_add_state'] = 'Add state';
$string['toolbar_add_transition'] = 'Add transition';
$string['toolbar_delete'] = 'Delete';
$string['toolbar_label'] = 'Editor toolbar';
$string['toolbar_set_start'] = 'Set start state';
$string['toolbar_toggle_final'] = 'Toggle final state';
$string['wordbank_empty'] = 'No words tested yet.';
$string['alphabet_label'] = 'Alphabet:';
$string['alphabet_add'] = 'Add';
$string['alphabet_input_aria'] = 'New symbol (single alphanumeric character)';
$string['err_duplicate_transition'] = 'Transition {$a} already exists (deterministic automaton).';
$string['err_empty_alphabet'] = 'Alphabet is empty. Add symbols before running the simulator.';
$string['err_input_too_long'] = 'Input exceeds maximum length ({$a}).';
$string['err_max_alphabet'] = 'Maximum alphabet size ({$a}) reached.';
$string['err_max_states'] = 'Maximum states ({$a}) reached.';
$string['err_max_transitions'] = 'Maximum transitions ({$a}) reached.';
$string['err_no_initial_state'] = 'No initial state set. Please set a start state first.';
$string['err_simulator_reject'] = 'Rejected: {$a}';
$string['err_symbol_in_use'] = 'Cannot remove symbol: it is used by existing transitions.';
$string['warn_logword_failed'] = 'Word log failed. Check your connection.';
$string['err_duplicate_transition'] = 'Transition {$a} already exists (deterministic automaton).';
$string['err_empty_alphabet'] = 'Alphabet is empty. Add symbols before running the simulator.';
$string['err_input_too_long'] = 'Input exceeds maximum length ({$a}).';
$string['err_max_alphabet'] = 'Maximum alphabet size ({$a}) reached.';
$string['err_max_states'] = 'Maximum states ({$a}) reached.';
$string['err_max_transitions'] = 'Maximum transitions ({$a}) reached.';
$string['err_no_initial_state'] = 'No initial state set. Please set a start state first.';
$string['err_simulator_reject'] = 'Rejected: {$a}';
$string['err_symbol_in_use'] = 'Cannot remove symbol: it is used by existing transitions.';
$string['warn_logword_failed'] = 'Word log failed. Check your connection.';
