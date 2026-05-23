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

namespace mod_graphitoubb;

/**
 * Grade cache service — computes and stores aggregate grades per attempt.
 *
 * Supports policies: best (highest fraction), last (most recent), average.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class grade_cache_service {
    /**
     * Recompute the cached grade for an attempt by applying the given policy.
     *
     * @param int    $attemptid
     * @param string $policy  'best', 'last', or 'average'.
     */
    public function recompute_for_attempt(int $attemptid, string $policy): void {
        global $DB;

        $submissions = $DB->get_records(
            'graphitoubb_submission',
            ['attemptid' => $attemptid],
            'timecreated ASC'
        );

        if (empty($submissions)) {
            return;
        }

        $count   = count($submissions);
        $fracs   = array_column((array) $submissions, 'fraction');
        $scores  = array_column((array) $submissions, 'score');

        switch ($policy) {
            case 'last':
                $latest   = end($submissions);
                $fraction = (float) $latest->fraction;
                $score    = (float) $latest->score;
                break;

            case 'average':
                $fraction = array_sum($fracs) / $count;
                $score    = array_sum($scores) / $count;
                break;

            case 'best':
            default:
                $best_idx = array_search(max($fracs), $fracs, true);
                $fraction = (float) $fracs[$best_idx];
                $score    = (float) $scores[$best_idx];
                break;
        }

        $existing = $DB->get_record('graphitoubb_grade_cache', ['attemptid' => $attemptid]);
        $now      = time();

        if ($existing) {
            $existing->score          = round($score, 4);
            $existing->fraction       = round($fraction, 4);
            $existing->attempt_count  = $count;
            $existing->policy_applied = $policy;
            $existing->timemodified   = $now;
            $DB->update_record('graphitoubb_grade_cache', $existing);
        } else {
            $DB->insert_record('graphitoubb_grade_cache', (object) [
                'attemptid'      => $attemptid,
                'score'          => round($score, 4),
                'fraction'       => round($fraction, 4),
                'attempt_count'  => $count,
                'policy_applied' => $policy,
                'timemodified'   => $now,
            ]);
        }
    }

    /**
     * Fetch the cached grade for an attempt.
     *
     * @param int $attemptid
     * @return \stdClass|null
     */
    public function get_for_attempt(int $attemptid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('graphitoubb_grade_cache', ['attemptid' => $attemptid]);
        return $record ?: null;
    }
}
