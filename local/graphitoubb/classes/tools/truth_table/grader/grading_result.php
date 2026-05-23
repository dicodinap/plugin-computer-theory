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
 * Grading result value object — the complete output of a grading run.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\grader;

/**
 * Immutable aggregate that captures every aspect of a grading run.
 *
 * score is rounded to 2 decimal places and lies in [0, max_grade].
 * fraction = score / max_grade, in [0, 1].
 * passed   = fraction >= pass_threshold.
 */
final class grading_result {
    /**
     * Build a grading result.
     *
     * @param float          $score                 Numeric score (rounded to 2 dec), in [0, max_grade].
     * @param float          $fraction              Score / max_grade, in [0, 1].
     * @param bool           $passed                Whether fraction >= pass_threshold.
     * @param int            $cells_total           Total gradeable cells (excludes variable cols and radio items).
     * @param int            $cells_correct         Number of cells that matched the expected value.
     * @param feedback_item[] $feedback_items        Per-cell feedback; may include radio items.
     * @param bool           $error                 True when an internal error aborted grading.
     * @param string|null    $error_message         Human-readable reason if $error is true.
     * @param string         $problem_snapshot_hash SHA-256 of the problem payload at grading time.
     */
    public function __construct(
        public readonly float $score,
        public readonly float $fraction,
        public readonly bool $passed,
        public readonly int $cells_total,
        public readonly int $cells_correct,
        public readonly array $feedback_items,
        public readonly bool $error,
        public readonly ?string $error_message,
        public readonly string $problem_snapshot_hash
    ) {
    }

    /**
     * Return a flat associative array suitable for JSON encoding.
     *
     * feedback_items are serialised via their own to_array().
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'score'                 => $this->score,
            'fraction'              => $this->fraction,
            'passed'                => $this->passed,
            'cells_total'           => $this->cells_total,
            'cells_correct'         => $this->cells_correct,
            'feedback_items'        => array_map(
                static fn(feedback_item $item): array => $item->to_array(),
                $this->feedback_items
            ),
            'error'                 => $this->error,
            'error_message'         => $this->error_message,
            'problem_snapshot_hash' => $this->problem_snapshot_hash,
        ];
    }

    /**
     * Factory for an error grading result — used when grading cannot proceed.
     *
     * @param  string $message              Human-readable error reason.
     * @param  string $problem_snapshot_hash SHA-256 hash of the problem (may be empty string on catastrophic failure).
     * @return self
     */
    public static function error(string $message, string $problem_snapshot_hash): self {
        return new self(
            score: 0.0,
            fraction: 0.0,
            passed: false,
            cells_total: 0,
            cells_correct: 0,
            feedback_items: [],
            error: true,
            error_message: $message,
            problem_snapshot_hash: $problem_snapshot_hash
        );
    }
}
