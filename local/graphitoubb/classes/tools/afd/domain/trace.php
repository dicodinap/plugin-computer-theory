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
 * AFD execution trace value object.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\afd\domain;

/**
 * Immutable record of a single AFD execution run, used for step-by-step animation.
 *
 * Each step is an associative array:
 *   ['current' => string, 'symbol' => string, 'next' => string]
 */
final class trace {
    /** @var array[] Execution steps. */
    private array $steps;

    /** @var bool Whether the input was accepted. */
    private bool $accepted;

    /**
     * Build a trace from an ordered list of steps and the acceptance result.
     *
     * @param array[] $steps    Ordered list of execution steps.
     * @param bool    $accepted True if the automaton accepted the input.
     */
    public function __construct(array $steps, bool $accepted) {
        $this->steps    = $steps;
        $this->accepted = $accepted;
    }

    /**
     * Return the ordered list of execution steps.
     *
     * Each step has keys: 'current' (state id before consuming), 'symbol', 'next' (state id after).
     *
     * @return array[]
     */
    public function get_steps(): array {
        return $this->steps;
    }

    /**
     * Return whether the input string was accepted.
     *
     * @return bool
     */
    public function is_accepted(): bool {
        return $this->accepted;
    }

    /**
     * Return the state id reached after the last step, or null for an empty trace.
     *
     * @return string|null
     */
    public function get_final_state(): ?string {
        if (empty($this->steps)) {
            return null;
        }
        return end($this->steps)['next'];
    }
}
