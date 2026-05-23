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

/**
 * External function: teacher panel — per-student tab data.
 *
 * Returns one row per enrolled student (with attempt capability). Uses a single
 * aggregation query joining attempts + submissions + grade_cache, then fetches
 * user display names. Budget: ≤10 DB queries.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_panel_per_student extends external_api {
    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Activity instance id'),
            'filter'     => new external_value(PARAM_ALPHANUMEXT, 'all | with_errors | not_submitted', VALUE_DEFAULT, 'all'),
        ]);
    }

    /**
     * @param int    $instanceid
     * @param string $filter  'all' | 'with_errors' | 'not_submitted'
     * @return array
     */
    public static function execute(int $instanceid, string $filter = 'all'): array {
        global $DB;

        $params  = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'filter'     => $filter,
        ]);
        $iid     = $params['instanceid'];
        $filter  = $params['filter'];

        if (!in_array($filter, ['all', 'with_errors', 'not_submitted'], true)) {
            $filter = 'all';
        }

        $cm      = get_coursemodule_from_instance('graphitoubb', $iid, 0, false, MUST_EXIST);
        $context = \context_module::instance((int) $cm->id);
        require_capability('mod/graphitoubb:viewreports', $context);

        // 1. Enrolled users with attempt capability (1 query via Moodle enrol API).
        $enrolled_users = get_enrolled_users(
            $context,
            'mod/graphitoubb:attempt',
            0,
            'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.email'
        );

        if (empty($enrolled_users)) {
            return ['students' => []];
        }

        $user_ids = array_keys($enrolled_users);

        // 2. Aggregate per-user attempt data in a single JOIN query (1 query).
        // Pulls best grade_cache fraction, attempt counts, time spent, draft info.
        [$in_sql, $in_params] = $DB->get_in_or_equal($user_ids, SQL_PARAMS_NAMED, 'uid');
        $in_params['iid'] = $iid;

        $agg_sql = "SELECT
                        a.userid,
                        COUNT(DISTINCT a.id)                               AS attempts_count,
                        SUM(CASE WHEN a.timefinished IS NOT NULL AND a.timefinished > 0
                                 THEN (a.timefinished - a.timestarted) ELSE 0 END)
                                                                           AS time_spent_seconds,
                        MAX(CASE WHEN a.current_draft IS NOT NULL THEN 1 ELSE 0 END)
                                                                           AS has_draft,
                        MAX(CASE WHEN a.status = 'finished'       THEN 1 ELSE 0 END)
                                                                           AS has_finished,
                        MAX(gc.fraction)                                   AS best_fraction,
                        MAX(gc.score)                                       AS best_score,
                        COUNT(DISTINCT s.id)                               AS submission_count,
                        MIN(CASE WHEN s.passed = 0 AND s.id IS NOT NULL   THEN 1 ELSE 0 END)
                                                                           AS has_errors
                      FROM {graphitoubb_attempt} a
                      LEFT JOIN {graphitoubb_grade_cache} gc ON gc.attemptid = a.id
                      LEFT JOIN {graphitoubb_submission}  s  ON s.attemptid  = a.id
                     WHERE a.instanceid = :iid
                       AND a.userid $in_sql
                  GROUP BY a.userid";

        $rows_by_user = [];
        foreach ($DB->get_records_sql($agg_sql, $in_params) as $row) {
            $rows_by_user[(int) $row->userid] = $row;
        }

        // 3. Build result array, apply filter, sort by lastname asc.
        $students = [];
        foreach ($enrolled_users as $uid => $user) {
            $agg = $rows_by_user[$uid] ?? null;

            $attempts_count    = $agg ? (int) $agg->attempts_count : 0;
            $submission_count  = $agg ? (int) $agg->submission_count : 0;
            $has_draft         = $agg ? (bool) $agg->has_draft : false;
            $has_finished      = $agg ? (bool) $agg->has_finished : false;
            $best_fraction     = $agg && $agg->best_fraction !== null ? (float) $agg->best_fraction : 0.0;
            $best_score        = $agg && $agg->best_score !== null ? (float) $agg->best_score : 0.0;
            $time_spent        = $agg ? (int) $agg->time_spent_seconds : 0;
            $has_errors        = $agg ? (bool) $agg->has_errors : false;

            // Determine status string.
            if ($attempts_count === 0) {
                $status = 'not_started';
            } else if ($has_finished) {
                $status = 'finished';
            } else {
                $status = 'inprogress';
            }

            // Apply filter.
            if ($filter === 'not_submitted' && $submission_count > 0) {
                continue;
            }
            if ($filter === 'with_errors') {
                // Include only users who have submitted at least once with a non-perfect score.
                if ($submission_count === 0 || $best_fraction >= 1.0) {
                    continue;
                }
            }

            $students[] = [
                'userid'             => $uid,
                'fullname'           => fullname($user),
                'score'              => round($best_score, 4),
                'fraction'           => round($best_fraction, 4),
                'attempts_count'     => $attempts_count,
                'time_spent_seconds' => $time_spent,
                'status'             => $status,
                'has_draft'          => $has_draft,
            ];
        }

        // Sort by lastname asc (enrolled_users already ordered by Moodle; re-sort to be safe).
        usort($students, function ($a, $b) use ($enrolled_users) {
            $ua = $enrolled_users[$a['userid']] ?? null;
            $ub = $enrolled_users[$b['userid']] ?? null;
            $la = $ua ? strtolower($ua->lastname) : '';
            $lb = $ub ? strtolower($ub->lastname) : '';
            return strcmp($la, $lb);
        });

        return ['students' => $students];
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'students' => new external_multiple_structure(
                new external_single_structure([
                    'userid'             => new external_value(PARAM_INT, 'User id'),
                    'fullname'           => new external_value(PARAM_TEXT, 'Student full name'),
                    'score'              => new external_value(PARAM_FLOAT, 'Best score (raw points)'),
                    'fraction'           => new external_value(PARAM_FLOAT, 'Best fraction (0-1)'),
                    'attempts_count'     => new external_value(PARAM_INT, 'Total attempt records'),
                    'time_spent_seconds' => new external_value(PARAM_INT, 'Total time across all finished attempts (seconds)'),
                    'status'             => new external_value(PARAM_ALPHANUMEXT, 'not_started | inprogress | finished'),
                    'has_draft'          => new external_value(PARAM_BOOL, 'Whether any attempt has an active draft'),
                ]),
                'Per-student rows, sorted by lastname asc'
            ),
        ]);
    }
}
