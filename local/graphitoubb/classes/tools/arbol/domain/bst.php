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
 * Canonical binary-search-tree construction from an insertion order.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\arbol\domain;

/**
 * Builds the canonical BST for a list of insertions and exposes it as a
 * path→value map (matching {@see tree::position_value_map}) for per-node grading.
 */
final class bst {
    /**
     * Canonical BST position→value map for an insertion sequence. Duplicate
     * insertions are no-ops (Edge Cases: repeated value ignored).
     *
     * @param  int[] $insertions
     * @return array<string,int> path ("" = root, then L/R steps) => value
     */
    public static function position_value_map(array $insertions): array {
        // node: [value, L index, R index]; index into $nodes, -1 = none.
        $nodes = [];
        $rootidx = -1;
        foreach ($insertions as $raw) {
            $v = (int) $raw;
            if ($rootidx === -1) {
                $nodes[] = [$v, -1, -1];
                $rootidx = 0;
                continue;
            }
            $cur = $rootidx;
            while (true) {
                if ($v === $nodes[$cur][0]) {
                    break; // Duplicate — ignore.
                }
                $dir = ($v < $nodes[$cur][0]) ? 1 : 2; // 1 = L index, 2 = R index.
                if ($nodes[$cur][$dir] === -1) {
                    $nodes[] = [$v, -1, -1];
                    $nodes[$cur][$dir] = count($nodes) - 1;
                    break;
                }
                $cur = $nodes[$cur][$dir];
            }
        }

        $map = [];
        if ($rootidx === -1) {
            return $map;
        }
        $walk = function (int $idx, string $path) use (&$walk, &$map, $nodes): void {
            $map[$path] = $nodes[$idx][0];
            if ($nodes[$idx][1] !== -1) {
                $walk($nodes[$idx][1], $path . 'L');
            }
            if ($nodes[$idx][2] !== -1) {
                $walk($nodes[$idx][2], $path . 'R');
            }
        };
        $walk($rootidx, '');
        return $map;
    }

    /**
     * Distinct values in insertion order (used for the grading denominator).
     *
     * @param  int[] $insertions
     * @return int[]
     */
    public static function distinct_values(array $insertions): array {
        $seen = [];
        $out  = [];
        foreach ($insertions as $raw) {
            $v = (int) $raw;
            if (!isset($seen[$v])) {
                $seen[$v] = true;
                $out[]    = $v;
            }
        }
        return $out;
    }
}
