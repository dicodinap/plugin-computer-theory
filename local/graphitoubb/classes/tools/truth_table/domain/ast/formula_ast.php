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
 * Abstract base for all formula AST nodes.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain\ast;

/**
 * Base class for all nodes in the propositional formula AST.
 *
 * Each concrete subclass represents one kind of syntactic element:
 * a variable, a Boolean constant, or a compound formula (not, and, or, xor, impl, iff).
 */
abstract class formula_ast {
    /**
     * Evaluate the formula under the given variable assignment.
     *
     * The caller (truth_table_builder) guarantees that every variable that
     * appears in the formula is present as a key in $assignment.
     * Decision: no defensive default — strict lookup preserves bug visibility.
     *
     * @param  array<string, bool> $assignment Map of variable name → bool.
     * @return bool
     */
    abstract public function evaluate(array $assignment): bool;

    /**
     * Return a deduplicated list of variable names that appear in this subtree.
     *
     * @return string[]
     */
    abstract public function variables(): array;

    /**
     * Return the canonical string representation of this subtree.
     *
     * Decision: binary nodes wrap their own output in parentheses unconditionally
     * (task instruction is explicit). The canonicalizer exposes this string for
     * use in column headers and teacher preview.
     *
     * @return string
     */
    abstract public function canonical(): string;

    /**
     * Return the depth of this subtree (leaf = 1).
     *
     * @return int
     */
    abstract public function depth(): int;
}
