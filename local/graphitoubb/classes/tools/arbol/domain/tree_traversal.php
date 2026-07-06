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
 * Binary-tree traversals (pre/in/post/level order) → value sequences.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\arbol\domain;

/**
 * Produces the canonical value sequence for a given traversal order over a tree.
 */
final class tree_traversal {
    /**
     * Return the ordered value sequence for a traversal over a tree.
     *
     * @param  tree   $t
     * @param  string $order 'pre' | 'in' | 'post' | 'level'
     * @return int[]
     */
    public static function order(tree $t, string $order): array {
        $root = $t->root();
        if ($root === null) {
            return [];
        }
        if ($order === 'level') {
            return self::level($t, $root);
        }
        $out = [];
        self::dfs($t, $root, $order, $out);
        return $out;
    }

    /**
     * Depth-first pre/in/post traversal accumulator.
     *
     * @param tree   $t
     * @param string $id
     * @param string $order
     * @param int[]  $out
     */
    private static function dfs(tree $t, string $id, string $order, array &$out): void {
        if ($order === 'pre') {
            $out[] = $t->value($id);
        }
        $l = $t->left($id);
        if ($l !== null) {
            self::dfs($t, $l, $order, $out);
        }
        if ($order === 'in') {
            $out[] = $t->value($id);
        }
        $r = $t->right($id);
        if ($r !== null) {
            self::dfs($t, $r, $order, $out);
        }
        if ($order === 'post') {
            $out[] = $t->value($id);
        }
    }

    /**
     * Breadth-first (level-order) traversal.
     *
     * @param  tree   $t
     * @param  string $root
     * @return int[]
     */
    private static function level(tree $t, string $root): array {
        $out   = [];
        $queue = [$root];
        while ($queue) {
            $id    = array_shift($queue);
            $out[] = $t->value($id);
            $l = $t->left($id);
            $r = $t->right($id);
            if ($l !== null) {
                $queue[] = $l;
            }
            if ($r !== null) {
                $queue[] = $r;
            }
        }
        return $out;
    }
}
