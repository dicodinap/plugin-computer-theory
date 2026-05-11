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
 * AFD state value object.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\afd\domain;

/**
 * Immutable representation of a single AFD state.
 */
final class state {
    /** @var string Unique state identifier. */
    private string $id;

    /** @var string Human-readable label (falls back to id when empty). */
    private string $label;

    /**
     * Build a state with an optional display label.
     *
     * @param string $id    Unique state identifier.
     * @param string $label Display label; defaults to $id when empty.
     */
    public function __construct(string $id, string $label = '') {
        $this->id    = $id;
        $this->label = $label !== '' ? $label : $id;
    }

    /**
     * Return the unique state identifier.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Return the display label.
     *
     * @return string
     */
    public function get_label(): string {
        return $this->label;
    }
}
