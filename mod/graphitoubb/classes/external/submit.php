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

declare(strict_types=1);

namespace mod_graphitoubb\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_graphitoubb\tools\truth_table\domain\serializer;
use local_graphitoubb\tools\truth_table\grader\grader;
use local_graphitoubb\tools\truth_table\schema\schema_loader;
use mod_graphitoubb\attempt_service;
use mod_graphitoubb\draft_service;
use mod_graphitoubb\event_service;
use mod_graphitoubb\grade_cache_service;
use mod_graphitoubb\problem_repository;
use mod_graphitoubb\submission_repository;

/**
 * External function: submit a final truth-table answer and receive grading feedback.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class submit extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'payload'   => new external_value(PARAM_RAW, 'Submission payload JSON'),
        ]);
    }

    /**
     * Grade the submission and persist the result.
     *
     * @param int    $attemptid
     * @param string $payload  Raw JSON submission.
     * @return array  Flattened grading_result plus feedback_items.
     */
    public static function execute(int $attemptid, string $payload): array {
        global $USER, $DB, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'payload'   => $payload,
        ]);

        // Capability and ownership checks.
        $attempt_svc = new attempt_service();
        $attempt     = $attempt_svc->get_attempt($params['attemptid']);
        $cm          = get_coursemodule_from_instance('graphitoubb', (int) $attempt->instanceid, 0, false, MUST_EXIST);
        $context     = \context_module::instance((int) $cm->id);

        require_capability('mod/graphitoubb:submit', $context);
        if (!$attempt_svc->belongs_to($params['attemptid'], (int) $USER->id)) {
            throw new \moodle_exception('not_attempt_owner', 'mod_graphitoubb');
        }

        // RF_04 submission gate (D9/D13): same enforcement as finish_attempt.
        if (!has_capability('mod/graphitoubb:viewreport', $context)) {
            $instance_gate = $DB->get_record('graphitoubb', ['id' => $attempt->instanceid], '*', MUST_EXIST);
            $gate = \mod_graphitoubb\submission_gate::check($instance_gate, (int) $USER->id);
            if (!$gate['allowed']) {
                return array_merge(self::empty_result(), [
                    'error'         => true,
                    'error_message' => \mod_graphitoubb\submission_gate::reason_text((string) $gate['reason']),
                ]);
            }
        }

        // Decode submission JSON.
        $ser        = new serializer();
        $submission = $ser->decode($params['payload']);

        // Load and validate problem.
        $problem_repo = new problem_repository();
        $problem_rec  = $problem_repo->find_by_instance((int) $attempt->instanceid);
        if (!$problem_rec) {
            return array_merge(self::empty_result(), [
                'error'         => true,
                'error_message' => 'No hay un problema configurado para esta actividad.',
            ]);
        }
        $problem = $ser->decode($problem_rec->payload);

        // Schema-validate submission.
        $loader     = new schema_loader();
        $type       = $problem['type'] ?? '';
        $validation = $loader->validate($submission, $type, 'submission');
        if (!$validation->ok) {
            return array_merge(self::empty_result(), [
                'error'         => true,
                'error_message' => implode('; ', $validation->errors),
            ]);
        }

        // Grade.
        $result = grader::instance()->grade($problem, $submission);
        $arr    = $result->to_array();

        // Persist submission.
        $sub_repo     = new submission_repository();
        $submissionid = $sub_repo->save(
            $params['attemptid'],
            $submission,
            $arr,
            $result->problem_snapshot_hash,
            1
        );

        // Update attempt status to 'finished'.
        $DB->set_field('graphitoubb_attempt', 'status', 'finished', ['id' => $params['attemptid']]);
        $DB->set_field('graphitoubb_attempt', 'timefinished', time(), ['id' => $params['attemptid']]);

        // Clear draft after successful submission.
        $draft_svc = new draft_service();
        $draft_svc->clear_draft($params['attemptid']);

        // Recompute grade cache using instance policy.
        $instance = $DB->get_record('graphitoubb', ['id' => $attempt->instanceid], 'attempts_policy');
        $policy   = $instance ? ($instance->attempts_policy ?: 'best') : 'best';
        $gc_svc   = new grade_cache_service();
        $gc_svc->recompute_for_attempt($params['attemptid'], $policy);

        // Push the aggregated grade to the gradebook for this student (itemnumber 0).
        require_once($CFG->dirroot . '/mod/graphitoubb/lib.php');
        graphitoubb_update_grades((object) ['id' => (int) $attempt->instanceid], (int) $attempt->userid);

        // Fire submission event.
        $event_svc = new event_service();
        $event_svc->dispatch_submitted($attempt, $arr, $submissionid, (int) $context->id);

        // Build feedback_items as flat array of arrays for WS transport.
        $feedback_items = array_map(static fn(array $item): array => [
            'row_index'    => (int) ($item['row_index'] ?? 0),
            'col_label'    => (string) ($item['col_label'] ?? ''),
            'cell_kind'    => (string) ($item['cell_kind'] ?? ''),
            'submitted'    => (string) ($item['submitted'] ?? ''),
            'expected'     => (string) ($item['expected'] ?? ''),
            'is_correct'   => (bool) ($item['is_correct'] ?? false),
            'is_root_error' => (bool) ($item['is_root_error'] ?? false),
            'explanation'  => (string) ($item['explanation'] ?? ''),
        ], $arr['feedback_items'] ?? []);

        return [
            'score'                 => (float) $arr['score'],
            'fraction'              => (float) $arr['fraction'],
            'passed'                => (bool)  $arr['passed'],
            'cells_total'           => (int)   $arr['cells_total'],
            'cells_correct'         => (int)   $arr['cells_correct'],
            'feedback_items'        => $feedback_items,
            'error'                 => (bool)  $arr['error'],
            'error_message'         => (string) ($arr['error_message'] ?? ''),
            'problem_snapshot_hash' => (string) $arr['problem_snapshot_hash'],
        ];
    }

    /**
     * Return structure definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'score'                 => new external_value(PARAM_FLOAT, 'Numeric score'),
            'fraction'              => new external_value(PARAM_FLOAT, 'Fraction in [0,1]'),
            'passed'                => new external_value(PARAM_BOOL, 'Whether the submission passed'),
            'cells_total'           => new external_value(PARAM_INT, 'Total gradeable cells'),
            'cells_correct'         => new external_value(PARAM_INT, 'Correct cells'),
            'feedback_items'        => new external_multiple_structure(
                new external_single_structure([
                    'row_index'     => new external_value(PARAM_INT, 'Row index (0-based)'),
                    'col_label'     => new external_value(PARAM_TEXT, 'Column label / formula'),
                    'cell_kind'     => new external_value(PARAM_ALPHANUMEXT, 'subformula | final | radio'),
                    'submitted'     => new external_value(PARAM_TEXT, 'Student-submitted value'),
                    'expected'      => new external_value(PARAM_TEXT, 'Expected value'),
                    'is_correct'    => new external_value(PARAM_BOOL, 'Whether the cell is correct'),
                    'is_root_error' => new external_value(PARAM_BOOL, 'True if the error originated here'),
                    'explanation'   => new external_value(PARAM_TEXT, 'Human-readable explanation'),
                ]),
                'Per-cell feedback'
            ),
            'error'                 => new external_value(PARAM_BOOL, 'True when grading failed'),
            'error_message'         => new external_value(PARAM_TEXT, 'Error reason (empty when no error)'),
            'problem_snapshot_hash' => new external_value(PARAM_ALPHANUMEXT, 'SHA-256 of problem at grading time'),
        ]);
    }

    /**
     * Return a zeroed result skeleton for early-return error paths.
     *
     * @return array
     */
    private static function empty_result(): array {
        return [
            'score'                 => 0.0,
            'fraction'              => 0.0,
            'passed'                => false,
            'cells_total'           => 0,
            'cells_correct'         => 0,
            'feedback_items'        => [],
            'error'                 => false,
            'error_message'         => '',
            'problem_snapshot_hash' => '',
        ];
    }
}
