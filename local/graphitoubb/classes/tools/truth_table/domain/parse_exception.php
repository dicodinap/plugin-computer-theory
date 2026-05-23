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
 * Parse exception — thrown when the parser encounters a syntactic error.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

/**
 * Exception raised by the recursive-descent parser on malformed input.
 */
final class parse_exception extends \RuntimeException {
    /** @var int 1-indexed character position where the error occurred. */
    private int $position;

    /**
     * Build a parse exception.
     *
     * @param string $message  Human-readable description of the error (Spanish).
     * @param int    $position 1-indexed character position.
     */
    public function __construct(string $message, int $position) {
        parent::__construct($message);
        $this->position = $position;
    }

    /**
     * Return the 1-indexed position of the offending token.
     *
     * @return int
     */
    public function get_position(): int {
        return $this->position;
    }
}
