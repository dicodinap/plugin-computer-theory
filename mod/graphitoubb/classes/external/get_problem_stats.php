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
 * External function: aggregate problem statistics for the teacher panel summary tab.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_problem_stats extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Activity instance id'),
        ]);
    }

    /**
     * Return aggregate statistics for the given instance.
     *
     * @param int $instanceid
     * @return array
     */
    public static function execute(int $instanceid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['instanceid' => $instanceid]);

        $cm      = get_coursemodule_from_instance('graphitoubb', $params['instanceid'], 0, false, MUST_EXIST);
        $context = \context_module::instance((int) $cm->id);
        require_capability('mod/graphitoubb:viewreports', $context);

        $iid = $params['instanceid'];

        // Enrolled students (active enrolments).
        $enrolled = count(get_enrolled_users($context, 'mod/graphitoubb:attempt'));

        // Attempts summary.
        $attempted   = (int) $DB->count_records('graphitoubb_attempt', ['instanceid' => $iid]);
        $with_draft  = (int) $DB->count_records_select(
            'graphitoubb_attempt',
            "instanceid = :iid AND current_draft IS NOT NULL",
            ['iid' => $iid]
        );

        // Submissions.
        $submitted_sql = "SELECT COUNT(DISTINCT a.id)
                            FROM {graphitoubb_attempt} a
                            JOIN {graphitoubb_submission} s ON s.attemptid = a.id
                           WHERE a.instanceid = :iid";
        $submitted = (int) $DB->count_records_sql($submitted_sql, ['iid' => $iid]);

        // Score aggregates from grade_cache.
        $score_sql = "SELECT gc.fraction
                        FROM {graphitoubb_grade_cache} gc
                        JOIN {graphitoubb_attempt} a ON a.id = gc.attemptid
                       WHERE a.instanceid = :iid";
        $fractions = array_map('floatval', $DB->get_fieldset_sql($score_sql, ['iid' => $iid]));

        $avg_score    = 0.0;
        $median_score = 0.0;
        $stddev_score = 0.0;

        if ($fractions) {
            $n = count($fractions);
            $avg_score = array_sum($fractions) / $n;
            sort($fractions);
            $mid = (int) floor($n / 2);
            $median_score = ($n % 2 === 0) ? ($fractions[$mid - 1] + $fractions[$mid]) / 2.0 : $fractions[$mid];
            if ($n > 1) {
                $variance = array_sum(array_map(fn($f) => ($f - $avg_score) ** 2, $fractions)) / $n;
                $stddev_score = sqrt($variance);
            }
        }

        // Time median (seconds from timestarted to timefinished).
        $time_sql = "SELECT (timefinished - timestarted) AS duration
                       FROM {graphitoubb_attempt}
                      WHERE instanceid = :iid AND timefinished IS NOT NULL AND timefinished > 0";
        $durations = $DB->get_fieldset_sql($time_sql, ['iid' => $iid]);
        $time_median = 0;
        if ($durations) {
            sort($durations);
            $n   = count($durations);
            $mid = (int) floor($n / 2);
            $time_median = ($n % 2 === 0) ? (int) (($durations[$mid - 1] + $durations[$mid]) / 2) : (int) $durations[$mid];
        }

        // Score buckets — 11 buckets: indices 0-9 correspond to fractions [0.0,0.1) .. [0.9,1.0);
        // index 10 captures fraction == 1.0 exactly. Uses int keys per panel spec.
        $buckets = [];
        for ($i = 0; $i <= 10; $i++) {
            $buckets[] = ['bucket' => $i, 'count' => 0];
        }
        foreach ($fractions as $f) {
            $idx = min(10, (int) floor($f * 10));
            $buckets[$idx]['count']++;
        }

        // top_errors is computed by get_panel_summary (needs grading_result full parse).
        // get_problem_stats returns an empty placeholder; callers that need top_errors use get_panel_summary.
        $top_errors = [];

        return [
            'enrolled'            => $enrolled,
            'attempted'           => $attempted,
            'submitted'           => $submitted,
            'with_draft'          => $with_draft,
            'avg_score'           => round($avg_score, 4),
            'median_score'        => round($median_score, 4),
            'stddev_score'        => round($stddev_score, 4),
            'time_median_seconds' => $time_median,
            'top_errors'          => $top_errors,
            'buckets'             => $buckets,
        ];
    }

    /**
     * Return structure definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enrolled'            => new external_value(PARAM_INT, 'Number of enrolled students with attempt capability'),
            'attempted'           => new external_value(PARAM_INT, 'Number of attempt records'),
            'submitted'           => new external_value(PARAM_INT, 'Number of attempts with at least one submission'),
            'with_draft'          => new external_value(PARAM_INT, 'Number of attempts with an active draft'),
            'avg_score'           => new external_value(PARAM_FLOAT, 'Average fraction (0-1)'),
            'median_score'        => new external_value(PARAM_FLOAT, 'Median fraction'),
            'stddev_score'        => new external_value(PARAM_FLOAT, 'Standard deviation of fraction'),
            'time_median_seconds' => new external_value(PARAM_INT, 'Median time in seconds to complete'),
            'top_errors'          => new external_multiple_structure(
                new external_single_structure([
                    'coord'      => new external_value(PARAM_TEXT, 'Cell coordinate (row,col)'),
                    'count'      => new external_value(PARAM_INT, 'Number of errors at this cell'),
                    'percentage' => new external_value(PARAM_FLOAT, 'Percentage of submitters who errored here'),
                ]),
                'Top 5 error cells'
            ),
            'buckets'             => new external_multiple_structure(
                new external_single_structure([
                    'bucket' => new external_value(PARAM_INT, 'Bucket index 0..10 (fraction*10, 10 = perfect score)'),
                    'count'  => new external_value(PARAM_INT, 'Number of attempts in this bucket'),
                ]),
                '11 score distribution buckets'
            ),
        ]);
    }
}
