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
 * External function: teacher panel — heatmap tab data.
 *
 * Reads the problem payload to determine column labels and rows_count (2^|vars|),
 * then aggregates feedback_items from all submission.grading_result blobs in PHP.
 * Sparse: cells with zero submissions are omitted. Budget: ≤12 queries (2 used).
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_panel_heatmap extends external_api {
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

        // 1. Load problem to get canonical columns and variable count (1 query).
        $problem = $DB->get_record('graphitoubb_problem', ['instanceid' => $iid], 'payload, schema_version', IGNORE_MISSING);

        $columns    = [];
        $rows_count = 0;

        if ($problem) {
            $p = json_decode($problem->payload, true);
            if (is_array($p)) {
                // Extract column labels. The problem payload stores columns under 'columns'
                // (pre-computed by truth_table_builder) or we derive from variables.
                if (!empty($p['columns']) && is_array($p['columns'])) {
                    $columns = array_values(array_map('strval', $p['columns']));
                } else if (!empty($p['variables']) && is_array($p['variables'])) {
                    $columns = array_values(array_map('strval', $p['variables']));
                }
                // rows_count = 2^|variables|.
                $var_count  = !empty($p['variables']) ? count($p['variables']) : 0;
                $rows_count = $var_count > 0 ? (1 << $var_count) : 0;
            }
        }

        // 2. Fetch all grading_result blobs for submissions in this instance (1 query).
        $gr_sql = "SELECT s.grading_result
                     FROM {graphitoubb_submission} s
                     JOIN {graphitoubb_attempt}    a ON a.id = s.attemptid
                    WHERE a.instanceid = :iid
                      AND s.grading_result IS NOT NULL
                      AND s.grading_result <> ''";
        $blobs = $DB->get_fieldset_sql($gr_sql, ['iid' => $iid]);

        // Aggregate per (row, col_index): correct_count + total_count.
        // Sparse: only emit cells with at least 1 submission.
        $cell_correct = []; // [row][col_index] => correct sum
        $cell_total   = []; // [row][col_index] => total count

        // Build col_index lookup: col_label -> index.
        $col_index_map = array_flip($columns);

        foreach ($blobs as $raw) {
            $gr = json_decode($raw, true);
            if (!is_array($gr) || empty($gr['feedback_items'])) {
                continue;
            }
            foreach ($gr['feedback_items'] as $item) {
                if (!isset($item['row_index'], $item['col_label'])) {
                    continue;
                }
                $ri  = (int) $item['row_index'];
                $cl  = (string) $item['col_label'];
                $ci  = $col_index_map[$cl] ?? null;
                if ($ci === null) {
                    // Column label not in known columns: add it dynamically.
                    $ci            = count($columns);
                    $columns[]     = $cl;
                    $col_index_map[$cl] = $ci;
                }
                if (!isset($cell_total[$ri][$ci])) {
                    $cell_correct[$ri][$ci] = 0;
                    $cell_total[$ri][$ci]   = 0;
                }
                $cell_total[$ri][$ci]++;
                if (!empty($item['is_correct'])) {
                    $cell_correct[$ri][$ci]++;
                }
            }
        }

        // Build sparse cells list.
        $cells = [];
        foreach ($cell_total as $ri => $cols) {
            foreach ($cols as $ci => $total) {
                $correct     = $cell_correct[$ri][$ci] ?? 0;
                $pct_correct = $total > 0 ? round(($correct / $total) * 100, 2) : 0.0;
                $cells[]     = [
                    'row'               => $ri,
                    'col_index'         => $ci,
                    'pct_correct'       => $pct_correct,
                    'count_submissions' => $total,
                ];
            }
        }

        return [
            'columns'    => $columns,
            'rows_count' => $rows_count,
            'cells'      => $cells,
        ];
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'columns'    => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Column label'),
                'Canonical column labels from problem'
            ),
            'rows_count' => new external_value(PARAM_INT, '2^|variables| — number of truth-table rows'),
            'cells'      => new external_multiple_structure(
                new external_single_structure([
                    'row'               => new external_value(PARAM_INT, 'Row index (0-based)'),
                    'col_index'         => new external_value(PARAM_INT, 'Column index into columns array'),
                    'pct_correct'       => new external_value(PARAM_FLOAT, 'Percentage of submissions correct (0-100)'),
                    'count_submissions' => new external_value(PARAM_INT, 'Number of submissions covering this cell'),
                ]),
                'Sparse cell list — omits cells with zero submissions'
            ),
        ]);
    }
}
