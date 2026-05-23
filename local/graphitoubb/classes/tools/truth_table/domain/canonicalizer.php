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
 * Canonicalizer — serialises an AST to its canonical string representation.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

use local_graphitoubb\tools\truth_table\domain\ast\formula_ast;
use local_graphitoubb\tools\truth_table\domain\ast\var_node;
use local_graphitoubb\tools\truth_table\domain\ast\const_node;

/**
 * Converts a formula AST to its fully-parenthesised canonical form and
 * extracts an ordered list of intermediate subformulae.
 */
final class canonicalizer {
    /**
     * Return the canonical string of the given AST.
     *
     * Delegates to formula_ast::canonical(). The return value is suitable for
     * column headers in the truth table and for teacher preview.
     *
     * @param  formula_ast $ast
     * @return string
     */
    public function canonical(formula_ast $ast): string {
        return $ast->canonical();
    }

    /**
     * Return a deduplicated ordered list of non-atomic subformula canonical strings.
     *
     * Decision: atoms (var_node, const_node) are excluded — they appear as dedicated
     * variable columns in the truth table. The root formula itself is also excluded
     * because the builder appends it separately as the 'final' column.
     * (This keeps the subformulas() budget within spec MAX_SUBFORMULAS = 8.)
     *
     * Traversal: post-order DFS so that inner subformulas appear before outer ones.
     * Deduplication preserves first occurrence.
     * Cap: at most $max distinct subformula strings are returned.
     *
     * @param  formula_ast $ast Root of the formula.
     * @param  int         $max Maximum number of subformulas to return (default 8).
     * @return string[]         Ordered canonical strings of intermediate subformulas.
     */
    public function subformulas(formula_ast $ast, int $max = 8): array {
        $seen   = [];
        $result = [];
        $this->collect_subformulas($ast, true, $seen, $result, $max);
        return $result;
    }

    /**
     * Recursively collect non-atomic non-root subformulas in post-order.
     *
     * @param  formula_ast $node     Current node.
     * @param  bool        $is_root  True only for the top-level call.
     * @param  array<string, bool> $seen     Dedup map (canonical → true).
     * @param  string[]    $result   Accumulator — modified in place.
     * @param  int         $max      Maximum number of results.
     * @return void
     */
    private function collect_subformulas(
        formula_ast $node,
        bool $is_root,
        array &$seen,
        array &$result,
        int $max
    ): void {
        if (count($result) >= $max) {
            return;
        }

        // Recurse into children first (post-order).
        foreach ($this->children($node) as $child) {
            $this->collect_subformulas($child, false, $seen, $result, $max);
            if (count($result) >= $max) {
                return;
            }
        }

        // Exclude atoms and the root.
        if ($is_root) {
            return;
        }
        if ($node instanceof var_node || $node instanceof const_node) {
            return;
        }

        $key = $node->canonical();
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key]  = true;
        $result[]    = $key;
    }

    /**
     * Return the direct children of a node using reflection on public readonly properties.
     *
     * Decision: use duck-typing on named properties rather than instanceof checks
     * for every binary/unary combination. This avoids a large switch when new node
     * types are added. var_node and const_node have no formula_ast children.
     *
     * @param  formula_ast $node
     * @return formula_ast[]
     */
    private function children(formula_ast $node): array {
        $children = [];
        if (property_exists($node, 'left') && $node->left instanceof formula_ast) {
            $children[] = $node->left;
        }
        if (property_exists($node, 'right') && $node->right instanceof formula_ast) {
            $children[] = $node->right;
        }
        if (property_exists($node, 'operand') && $node->operand instanceof formula_ast) {
            $children[] = $node->operand;
        }
        return $children;
    }
}
