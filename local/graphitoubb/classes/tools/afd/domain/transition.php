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
 * AFD transition value object.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\afd\domain;

/**
 * Immutable representation of a single AFD transition (from, symbol) to a target state.
 */
final class transition {
    /** @var string Source state identifier. */
    private string $from;

    /** @var string Alphabet symbol consumed by this transition. */
    private string $symbol;

    /** @var string Target state identifier. */
    private string $to;

    /**
     * Build a transition from source state, symbol, and target state.
     *
     * @param string $from   Source state id.
     * @param string $symbol Alphabet symbol.
     * @param string $to     Target state id.
     */
    public function __construct(string $from, string $symbol, string $to) {
        $this->from   = $from;
        $this->symbol = $symbol;
        $this->to     = $to;
    }

    /**
     * Return the source state identifier.
     *
     * @return string
     */
    public function get_from(): string {
        return $this->from;
    }

    /**
     * Return the alphabet symbol consumed by this transition.
     *
     * @return string
     */
    public function get_symbol(): string {
        return $this->symbol;
    }

    /**
     * Return the target state identifier.
     *
     * @return string
     */
    public function get_to(): string {
        return $this->to;
    }
}
