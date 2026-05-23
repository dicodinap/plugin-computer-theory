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
 * Variable leaf node in the formula AST.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain\ast;

/**
 * Represents a single propositional variable (A–Z).
 */
final class var_node extends formula_ast {
    /**
     * The variable name (single uppercase letter, A–Z).
     *
     * @var string
     */
    public readonly string $name;

    /**
     * Build a variable node.
     *
     * @param string $name Single uppercase letter A–Z.
     */
    public function __construct(string $name) {
        $this->name = $name;
    }

    /**
     * Return the value bound to this variable in the assignment.
     *
     * @param  array<string, bool> $assignment
     * @return bool
     */
    public function evaluate(array $assignment): bool {
        return $assignment[$this->name];
    }

    /**
     * Return the variable name as the only element.
     *
     * @return string[]
     */
    public function variables(): array {
        return [$this->name];
    }

    /**
     * Return the variable name as-is (atoms are not wrapped in parentheses).
     *
     * @return string
     */
    public function canonical(): string {
        return $this->name;
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
