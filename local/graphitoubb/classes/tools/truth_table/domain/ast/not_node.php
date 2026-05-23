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
 * Negation unary node in the formula AST.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain\ast;

/**
 * Represents ¬φ — logical negation of the operand formula.
 */
final class not_node extends formula_ast {
    /**
     * The operand formula being negated.
     *
     * @var formula_ast
     */
    public readonly formula_ast $operand;

    /**
     * Build a negation node.
     *
     * @param formula_ast $operand The formula to negate.
     */
    public function __construct(formula_ast $operand) {
        $this->operand = $operand;
    }

    /**
     * Return the logical negation of the operand's value.
     *
     * @param  array<string, bool> $assignment
     * @return bool
     */
    public function evaluate(array $assignment): bool {
        return !$this->operand->evaluate($assignment);
    }

    /**
     * Return the deduplicated variables of the operand.
     *
     * @return string[]
     */
    public function variables(): array {
        return array_values(array_unique($this->operand->variables()));
    }

    /**
     * Return the canonical negation string.
     *
     * Decision: atoms (var_node, const_node) and another not_node do not need
     * extra parentheses because negation is prefix and right-associative.
     * Binary nodes do need parentheses to disambiguate. This matches common
     * propositional logic notation (¬A vs ¬(A ∧ B)).
     *
     * @return string
     */
    public function canonical(): string {
        $operand = $this->operand;
        if ($operand instanceof var_node || $operand instanceof const_node || $operand instanceof not_node) {
            return '¬' . $operand->canonical();
        }
        return '¬(' . $operand->canonical() . ')';
    }

    /**
     * Return the depth of this node (1 + operand depth).
     *
     * @return int
     */
    public function depth(): int {
        return 1 + $this->operand->depth();
    }
}
