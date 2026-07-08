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
 * Shared result-array builder for grader_interface implementations (Wave 0).
 *
 * Extracts the `check`/`scored_result`/`invalid_result` helpers that grafo_grader
 * and arbol_grader duplicate verbatim, so new graders (karnaugh, relations) reuse
 * a single source instead of becoming copies 3 and 4. Output is byte-identical to
 * the duplicated helpers (I1).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\grader;

/**
 * Reusable factory methods for the shared 9-key grader result array.
 *
 * Consumers must implement grader_interface (for the PASS_THRESHOLD constant).
 */
trait result_builder {
    /**
     * Build a single result-row.
     *
     * @param  string $check
     * @param  mixed  $expected
     * @param  mixed  $got
     * @param  bool   $correct
     * @return array{check:string,expected:mixed,got:mixed,correct:bool}
     */
    protected static function check(string $check, $expected, $got, bool $correct): array {
        return ['check' => $check, 'expected' => $expected, 'got' => $got, 'correct' => $correct];
    }

    /**
     * Build a graded (valid) result array.
     *
     * @param  float $fraction
     * @param  int   $total
     * @param  int   $correct
     * @param  array $results
     * @param  ?string $message Optional informational message (e.g. minimality hint).
     * @return array
     */
    protected static function scored_result(
        float $fraction,
        int $total,
        int $correct,
        array $results,
        ?string $message = null
    ): array {
        return [
            'graded'        => true,
            'invalid'       => false,
            'message'       => $message,
            'score'         => $fraction,
            'fraction'      => $fraction,
            'passed'        => $fraction >= self::PASS_THRESHOLD,
            'items_total'   => $total,
            'items_correct' => $correct,
            'results'       => $results,
        ];
    }

    /**
     * Build an invalid (ungradeable) result array (fraction 0).
     *
     * @param  string $message
     * @return array
     */
    protected static function invalid_result(string $message): array {
        return [
            'graded'        => true,
            'invalid'       => true,
            'message'       => $message,
            'score'         => 0.0,
            'fraction'      => 0.0,
            'passed'        => false,
            'items_total'   => 0,
            'items_correct' => 0,
            'results'       => [],
        ];
    }
}
