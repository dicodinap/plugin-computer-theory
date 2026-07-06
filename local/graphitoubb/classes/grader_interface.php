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
 * Common grader contract shared by every GraphitoUBB tool (D14).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb;

/**
 * A grader takes the decoded problem payload plus the student's submission JSON
 * (the latest snapshot / answer envelope) and returns the shared result array:
 *
 *   [
 *     'graded'        => bool,
 *     'invalid'       => bool,
 *     'message'       => ?string,
 *     'score'         => float,
 *     'fraction'      => float,
 *     'passed'        => bool,     // fraction >= 0.6
 *     'items_total'   => int,
 *     'items_correct' => int,
 *     'results'       => array<int,array{check:string,expected:mixed,got:mixed,correct:bool}>,
 *   ]
 *
 * Implementations are pure and DB-free (only the WS wrapper touches the DB).
 */
interface grader_interface {
    /** Shared passing threshold as a fraction. */
    public const PASS_THRESHOLD = 0.6;

    /**
     * Grade a submission against a problem.
     *
     * @param  array       $problem        Decoded problem payload ({tool,type,config,...}).
     * @param  string|null $submissionjson Latest submission/snapshot JSON, or null.
     * @return array Shared result array (see interface docblock).
     */
    public function grade(array $problem, ?string $submissionjson): array;
}
