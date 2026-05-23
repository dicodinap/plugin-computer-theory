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
 * Feedback item value object — one cell's grading detail.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\grader;

/**
 * Immutable record for the grading detail of a single table cell or radio answer.
 *
 * cell_kind values:
 *   'subformula' — an intermediate subformula column cell.
 *   'final'      — the final (root formula) column cell.
 *   'radio'      — the equivalence / classify radio answer (not a table cell).
 */
final class feedback_item {
    /**
     * Build a feedback item.
     *
     * @param int         $row_index    0-based row index in the truth table (use -1 for radio items).
     * @param string      $col_label    Column label (canonical formula string, or 'radio').
     * @param string      $cell_kind    'subformula' | 'final' | 'radio'.
     * @param mixed       $submitted    Value the student submitted: string ('V'|'F'|''), bool, or null.
     * @param mixed       $expected     Correct value: string ('V'|'F') or bool.
     * @param bool        $is_correct   Whether the submitted value equals the expected value.
     * @param bool        $is_root_error True when this incorrect cell's error originates here (not propagated).
     * @param string      $explanation  Human-readable explanation in Spanish.
     */
    public function __construct(
        public readonly int $row_index,
        public readonly string $col_label,
        public readonly string $cell_kind,
        public readonly mixed $submitted,
        public readonly mixed $expected,
        public readonly bool $is_correct,
        public readonly bool $is_root_error,
        public readonly string $explanation
    ) {
    }

    /**
     * Serialise to a flat array suitable for JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'row_index'    => $this->row_index,
            'col_label'    => $this->col_label,
            'cell_kind'    => $this->cell_kind,
            'submitted'    => $this->submitted,
            'expected'     => $this->expected,
            'is_correct'   => $this->is_correct,
            'is_root_error' => $this->is_root_error,
            'explanation'  => $this->explanation,
        ];
    }
}
