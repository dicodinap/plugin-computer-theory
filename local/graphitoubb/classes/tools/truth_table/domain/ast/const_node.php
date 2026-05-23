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
 * Boolean constant leaf node in the formula AST.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain\ast;

/**
 * Represents the Boolean constants ⊤ (true) and ⊥ (false).
 */
final class const_node extends formula_ast {
    /**
     * The constant Boolean value.
     *
     * @var bool
     */
    public readonly bool $value;

    /**
     * Build a constant node.
     *
     * @param bool $value True for ⊤, false for ⊥.
     */
    public function __construct(bool $value) {
        $this->value = $value;
    }

    /**
     * Return the constant value regardless of the assignment.
     *
     * @param  array<string, bool> $assignment
     * @return bool
     */
    public function evaluate(array $assignment): bool {
        return $this->value;
    }

    /**
     * Constants introduce no variables.
     *
     * @return string[]
     */
    public function variables(): array {
        return [];
    }

    /**
     * Return the Unicode constant symbol.
     *
     * @return string
     */
    public function canonical(): string {
        return $this->value ? '⊤' : '⊥';
    }

    /**
     * A leaf node has depth 1.
     *
     * @return int
     */
    public function depth(): int {
        return 1;
    }
}
