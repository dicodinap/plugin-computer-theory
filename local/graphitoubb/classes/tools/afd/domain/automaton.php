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
 * AFD automaton aggregate root.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\afd\domain;

/**
 * Immutable aggregate root representing a complete deterministic finite automaton.
 */
final class automaton {
    /** @var state[] */
    private array $states;

    /** @var string[] */
    private array $alphabet;

    /** @var transition[] */
    private array $transitions;

    /** @var string Start state identifier. */
    private string $start;

    /** @var string[] Accepting state identifiers. */
    private array $finals;

    /**
     * Build an automaton from its five components.
     *
     * @param state[]      $states      All states in the automaton.
     * @param string[]     $alphabet    Alphabet symbols.
     * @param transition[] $transitions All transitions.
     * @param string       $start       Start state id.
     * @param string[]     $finals      Accepting state ids.
     */
    public function __construct(
        array $states,
        array $alphabet,
        array $transitions,
        string $start,
        array $finals
    ) {
        $this->states      = $states;
        $this->alphabet    = $alphabet;
        $this->transitions = $transitions;
        $this->start       = $start;
        $this->finals      = $finals;
    }

    /**
     * Return all states.
     *
     * @return state[]
     */
    public function get_states(): array {
        return $this->states;
    }

    /**
     * Return the alphabet symbols.
     *
     * @return string[]
     */
    public function get_alphabet(): array {
        return $this->alphabet;
    }

    /**
     * Return all transitions.
     *
     * @return transition[]
     */
    public function get_transitions(): array {
        return $this->transitions;
    }

    /**
     * Return the start state identifier.
     *
     * @return string
     */
    public function get_start(): string {
        return $this->start;
    }

    /**
     * Return the accepting state identifiers.
     *
     * @return string[]
     */
    public function get_finals(): array {
        return $this->finals;
    }
}
