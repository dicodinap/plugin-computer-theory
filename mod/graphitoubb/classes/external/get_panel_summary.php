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
 * External function: teacher panel — summary tab data.
 *
 * Extends get_problem_stats with problem metadata and parsed top_errors from
 * grading_result JSON. Budget: ≤8 DB queries (inherits ~5 from get_problem_stats
 * + 1 problem load + 1 grading_result fetch).
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_panel_summary extends external_api {
    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Activity instance id'),
        ]);
    }

    /**
     * @param int $instanceid
     * @return array
     */
    public static function execute(int $instanceid): array {
        global $DB;

        $params  = self::validate_parameters(self::execute_parameters(), ['instanceid' => $instanceid]);
        $iid     = $params['instanceid'];
        $cm      = get_coursemodule_from_instance('graphitoubb', $iid, 0, false, MUST_EXIST);
        $context = \context_module::instance((int) $cm->id);
        require_capability('mod/graphitoubb:viewreports', $context);

        // Load problem (1 query).
        $problem = $DB->get_record('graphitoubb_problem', ['instanceid' => $iid], '*', IGNORE_MISSING);

        // Base stats from get_problem_stats (delegates; runs ~5 internal queries).
        $base = get_problem_stats::execute($iid);

        // Top errors: fetch all grading_result blobs and parse in PHP (1 query).
        $grading_results_sql = "SELECT s.grading_result
                                  FROM {graphitoubb_submission} s
                                  JOIN {graphitoubb_attempt} a ON a.id = s.attemptid
                                 WHERE a.instanceid = :iid
                                   AND s.grading_result IS NOT NULL
                                   AND s.grading_result <> ''";
        $raw_results = $DB->get_fieldset_sql($grading_results_sql, ['iid' => $iid]);

        $error_counts    = [];
        $total_cells     = 0;
        $submission_count = 0;

        foreach ($raw_results as $raw) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                continue;
            }
            $submission_count++;
            // Accumulate cells_total for denominator (from the grading result itself).
            if (!empty($decoded['cells_total'])) {
                $total_cells = max($total_cells, (int) $decoded['cells_total']);
            }
            if (empty($decoded['feedback_items']) || !is_array($decoded['feedback_items'])) {
                continue;
            }
            foreach ($decoded['feedback_items'] as $item) {
                if (empty($item['is_correct']) || $item['is_correct'] === false) {
                    $row_index = (int) ($item['row_index'] ?? -1);
                    $col_label = (string) ($item['col_label'] ?? '');
                    if ($row_index < 0 || $col_label === '') {
                        continue;
                    }
                    $key = $row_index . '|' . $col_label;
                    if (!isset($error_counts[$key])) {
                        $error_counts[$key] = ['row_index' => $row_index, 'col_label' => $col_label, 'count' => 0];
                    }
                    $error_counts[$key]['count']++;
                }
            }
        }

        // Sort by count desc, keep top 5.
        usort($error_counts, fn($a, $b) => $b['count'] - $a['count']);
        $top_errors = [];
        $denom      = $submission_count > 0 ? $submission_count : 1;
        foreach (array_slice($error_counts, 0, 5) as $ec) {
            $top_errors[] = [
                'row_index'  => $ec['row_index'],
                'col_label'  => $ec['col_label'],
                'count'      => $ec['count'],
                'percentage' => round(($ec['count'] / $denom) * 100, 2),
            ];
        }

        return [
            'instanceid'          => $iid,
            'problem_id'          => $problem ? (int) $problem->id : 0,
            'problem_payload'     => $problem ? (string) $problem->payload : '',
            'schema_version'      => $problem ? (int) $problem->schema_version : 1,
            'enrolled'            => $base['enrolled'],
            'attempted'           => $base['attempted'],
            'submitted'           => $base['submitted'],
            'with_draft'          => $base['with_draft'],
            'avg_score'           => $base['avg_score'],
            'median_score'        => $base['median_score'],
            'stddev_score'        => $base['stddev_score'],
            'buckets'             => $base['buckets'],
            'time_median_seconds' => $base['time_median_seconds'],
            'top_errors'          => $top_errors,
        ];
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'instanceid'          => new external_value(PARAM_INT, 'Activity instance id'),
            'problem_id'          => new external_value(PARAM_INT, 'Problem record id (0 if none)'),
            'problem_payload'     => new external_value(PARAM_RAW, 'Raw problem JSON payload'),
            'schema_version'      => new external_value(PARAM_INT, 'Problem schema version'),
            'enrolled'            => new external_value(PARAM_INT, 'Enrolled students with attempt capability'),
            'attempted'           => new external_value(PARAM_INT, 'Students who opened at least one attempt'),
            'submitted'           => new external_value(PARAM_INT, 'Students who submitted at least once'),
            'with_draft'          => new external_value(PARAM_INT, 'Attempts with an active draft'),
            'avg_score'           => new external_value(PARAM_FLOAT, 'Average fraction (0-1)'),
            'median_score'        => new external_value(PARAM_FLOAT, 'Median fraction'),
            'stddev_score'        => new external_value(PARAM_FLOAT, 'Standard deviation of fraction'),
            'buckets'             => new external_multiple_structure(
                new external_single_structure([
                    'bucket' => new external_value(PARAM_INT, 'Bucket index 0..10 (fraction*10, capped at 10 for 1.0)'),
                    'count'  => new external_value(PARAM_INT, 'Number of attempts in bucket'),
                ]),
                '11 score buckets (0-9 = 0-90%, 10 = 90-100%)'
            ),
            'time_median_seconds' => new external_value(PARAM_INT, 'Median completion time in seconds'),
            'top_errors'          => new external_multiple_structure(
                new external_single_structure([
                    'row_index'  => new external_value(PARAM_INT, 'Truth-table row index (0-based)'),
                    'col_label'  => new external_value(PARAM_TEXT, 'Column label (formula string)'),
                    'count'      => new external_value(PARAM_INT, 'Absolute error count'),
                    'percentage' => new external_value(PARAM_FLOAT, '% of submitters who errored here'),
                ]),
                'Top 5 cells by error count'
            ),
        ]);
    }
}
