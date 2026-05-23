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
 * Exclusive disjunction (XOR) binary node in the formula AST.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain\ast;

/**
 * Represents φ ⊕ ψ — exclusive or.
 */
final class xor_node extends formula_ast {
    /** Unicode symbol for XOR. */
    public const SYMBOL = '⊕';

    /**
     * Left operand.
     *
     * @var formula_ast
     */
    public readonly formula_ast $left;

    /**
     * Right operand.
     *
     * @var formula_ast
     */
    public readonly formula_ast $right;

    /**
     * Build an XOR node.
     *
     * @param formula_ast $left  Left operand.
     * @param formula_ast $right Right operand.
     */
    public function __construct(formula_ast $left, formula_ast $right) {
        $this->left  = $left;
        $this->right = $right;
    }

    /**
     * Return true iff exactly one operand is true.
     *
     * Decision: use strict inequality (l !== r) which equals XOR for booleans.
     *
     * @param  array<string, bool> $assignment
     * @return bool
     */
    public function evaluate(array $assignment): bool {
        return $this->left->evaluate($assignment) !== $this->right->evaluate($assignment);
    }

    /**
     * Return the deduplicated union of both operands' variables.
     *
     * @return string[]
     */
    public function variables(): array {
        return array_values(array_unique(array_merge($this->left->variables(), $this->right->variables())));
    }

    /**
     * Return the fully-parenthesised canonical string.
     *
     * @return string
     */
    public function canonical(): string {
        return '(' . $this->left->canonical() . ' ' . self::SYMBOL . ' ' . $this->right->canonical() . ')';
    }

    /**
     * Return 1 + the maximum depth of the two operands.
     *
     * @return int
     */
    public function depth(): int {
        return 1 + max($this->left->depth(), $this->right->depth());
    }
}
