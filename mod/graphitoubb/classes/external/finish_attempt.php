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
use core_external\external_single_structure;
use core_external\external_value;
use mod_graphitoubb\attempt_service;

/**
 * External function: mark an attempt as finished.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class finish_attempt extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
        ]);
    }

    /**
     * Finish an attempt. Idempotent — calling twice on a finished attempt is safe.
     *
     * Teachers with mod/graphitoubb:viewreport bypass the ownership check.
     *
     * @param int $attemptid
     * @return array{status: string}
     */
    public static function execute(int $attemptid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
        ]);

        $attemptservice = new attempt_service();
        $attempt = $attemptservice->get_attempt($params['attemptid']);
        $cm = get_coursemodule_from_instance('graphitoubb', (int) $attempt->instanceid, 0, false, MUST_EXIST);
        $context = \context_module::instance((int) $cm->id);

        $canbypass = has_capability('mod/graphitoubb:viewreport', $context);
        if (!$canbypass) {
            require_capability('mod/graphitoubb:attempt', $context);
        }
        if (!$canbypass && !$attemptservice->belongs_to($params['attemptid'], (int) $USER->id)) {
            throw new \moodle_exception('not_attempt_owner', 'mod_graphitoubb');
        }

        $attemptservice->finish($params['attemptid']);

        $response = ['status' => 'ok'];

        // C1: if this instance is an AFD exercise, grade the student's latest
        // automaton against the authored test words and persist a submission.
        $problem = (new \mod_graphitoubb\problem_repository())->find_by_instance((int) $attempt->instanceid);
        if ($problem && $problem->tool === 'afd') {
            $pdata        = json_decode($problem->payload, true) ?: [];
            $config       = $pdata['config'] ?? [];
            $latest       = (new \mod_graphitoubb\snapshot_service())->get_latest($params['attemptid']);
            $snapshotjson = $latest ? $latest->payload : null;

            $grading = (new \local_graphitoubb\tools\afd\grader\afd_grader())->grade($config, $snapshotjson);

            $automaton = $snapshotjson ? (json_decode($snapshotjson, true) ?: []) : [];
            (new \mod_graphitoubb\submission_repository())->save(
                $params['attemptid'],
                ['tool' => 'afd', 'automaton' => $automaton],
                $grading,
                (string) $problem->payload_hash,
                1
            );

            $response['graded']        = true;
            $response['invalid']       = (bool) $grading['invalid'];
            $response['fraction']      = (float) $grading['fraction'];
            $response['words_correct'] = (int) $grading['words_correct'];
            $response['words_total']   = (int) $grading['words_total'];
        }

        return $response;
    }

    /**
     * Return structure definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status'        => new external_value(PARAM_ALPHANUMEXT, 'ok'),
            'graded'        => new external_value(PARAM_BOOL, 'Whether AFD grading ran', VALUE_OPTIONAL),
            'invalid'       => new external_value(PARAM_BOOL, 'Automaton ungradeable (e.g. no start)', VALUE_OPTIONAL),
            'fraction'      => new external_value(PARAM_FLOAT, 'Score fraction 0–1', VALUE_OPTIONAL),
            'words_correct' => new external_value(PARAM_INT, 'Test words classified correctly', VALUE_OPTIONAL),
            'words_total'   => new external_value(PARAM_INT, 'Total test words', VALUE_OPTIONAL),
        ]);
    }
}
