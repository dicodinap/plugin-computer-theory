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
 * Unique binary-tree reconstruction from a traversal pair (pre_in / post_in).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\arbol\domain;

/**
 * Reconstructs the unique binary tree from a preorder+inorder or
 * postorder+inorder pair of DISTINCT values (D13), as a path→value map.
 */
final class tree_reconstruct {
    /**
     * Reconstruct the unique tree as a path→value map.
     *
     * @param  string $pair 'pre_in' | 'post_in'
     * @param  int[]  $a    preorder (pre_in) or postorder (post_in)
     * @param  int[]  $b    inorder
     * @return array<string,int>|null null when the pair is invalid / inconsistent.
     */
    public static function position_value_map(string $pair, array $a, array $b): ?array {
        $a = array_map('intval', $a);
        $b = array_map('intval', $b);

        // Both must be permutations of the same DISTINCT value set.
        if (count($a) !== count($b)) {
            return null;
        }
        if (count(array_unique($a)) !== count($a) || count(array_unique($b)) !== count($b)) {
            return null;
        }
        if (array_diff($a, $b) || array_diff($b, $a)) {
            return null;
        }
        if ($pair !== 'pre_in' && $pair !== 'post_in') {
            return null;
        }

        $map = [];
        $ok  = self::build($pair, $a, $b, '', $map);
        return $ok ? $map : null;
    }

    /**
     * Recursive reconstruction into $map. Returns false on inconsistency.
     *
     * @param  string $pair
     * @param  int[]  $a  pre/post segment for this subtree
     * @param  int[]  $b  inorder segment for this subtree
     * @param  string $path
     * @param  array<string,int> $map
     * @return bool
     */
    private static function build(string $pair, array $a, array $b, string $path, array &$map): bool {
        if (empty($a)) {
            return true;
        }
        $root = ($pair === 'pre_in') ? $a[0] : $a[count($a) - 1];
        $k = array_search($root, $b, true);
        if ($k === false) {
            return false;
        }
        $map[$path] = $root;

        $leftin  = array_slice($b, 0, $k);
        $rightin = array_slice($b, $k + 1);
        $lsize   = count($leftin);

        if ($pair === 'pre_in') {
            $lefta  = array_slice($a, 1, $lsize);
            $righta = array_slice($a, 1 + $lsize);
        } else { // post_in.
            $lefta  = array_slice($a, 0, $lsize);
            $righta = array_slice($a, $lsize, count($a) - $lsize - 1);
        }

        return self::build($pair, $lefta, $leftin, $path . 'L', $map)
            && self::build($pair, $righta, $rightin, $path . 'R', $map);
    }
}
