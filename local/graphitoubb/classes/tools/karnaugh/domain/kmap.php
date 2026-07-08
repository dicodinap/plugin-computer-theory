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
 * Karnaugh-map domain helpers — pure, layout-independent bit-pattern logic.
 *
 * A K-map cell is identified by its assignment index i in [0, 2^n). Bit j
 * (MSB→LSB) corresponds to var_names[j]. A "group" is a set of assignment
 * indices; its legality, product term and covered-cell set are all derived from
 * the bit patterns (AND/OR masks), which makes Gray adjacency and edge-wrap
 * automatic — no physical grid geometry is needed.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\karnaugh\domain;

/**
 * Static bit-pattern utilities for Karnaugh grouping and equivalence.
 */
final class kmap {
    /**
     * Population count (number of set bits) of a non-negative integer.
     *
     * @param  int $x
     * @return int
     */
    public static function popcount(int $x): int {
        $c = 0;
        while ($x > 0) {
            $c += $x & 1;
            $x >>= 1;
        }
        return $c;
    }

    /**
     * Normalise a list of cell indices to distinct non-negative ints within range.
     *
     * @param  array $cells
     * @param  int   $nvars
     * @return int[] Distinct valid indices (may be empty).
     */
    public static function normalize_cells(array $cells, int $nvars): array {
        $max = (1 << $nvars) - 1;
        $out = [];
        foreach ($cells as $c) {
            if (!is_int($c) && !(is_string($c) && ctype_digit($c))) {
                continue;
            }
            $i = (int) $c;
            if ($i >= 0 && $i <= $max) {
                $out[$i] = true;
            }
        }
        $keys = array_keys($out);
        sort($keys);
        return $keys;
    }

    /**
     * Smallest enclosing sub-cube of a set of cells, as a {and, or, free} mask
     * triple. `and` = bits set in every cell, `or` = bits set in any cell, `free`
     * = bits that vary (= or ^ and). The enclosing sub-cube is every index that
     * agrees with `and` on the fixed (non-free) bits.
     *
     * @param  int[] $cells Non-empty distinct indices.
     * @return array{and:int,or:int,free:int}
     */
    public static function enclosing_cube(array $cells): array {
        $and = -1; // All bits set (two's complement); masked by first &.
        $or  = 0;
        foreach ($cells as $c) {
            $and &= $c;
            $or  |= $c;
        }
        if ($and === -1) {
            $and = 0;
        }
        return ['and' => $and, 'or' => $or, 'free' => $or ^ $and];
    }

    /**
     * All assignment indices covered by the smallest sub-cube enclosing $cells —
     * this is the set on which the group's product term evaluates to 1.
     *
     * @param  int[] $cells Non-empty distinct indices.
     * @return int[] Covered indices (sorted).
     */
    public static function term_cover(array $cells): array {
        if (empty($cells)) {
            return [];
        }
        $cube = self::enclosing_cube($cells);
        $base = $cube['and'];
        $free = $cube['free'];
        // Enumerate every subset of the free bits.
        $freebits = [];
        for ($b = 0; (1 << $b) <= $free || $b < 1; $b++) {
            if ($free & (1 << $b)) {
                $freebits[] = 1 << $b;
            }
            if ((1 << $b) > $free) {
                break;
            }
        }
        $covered = [$base];
        foreach ($freebits as $fb) {
            $next = [];
            foreach ($covered as $c) {
                $next[] = $c;
                $next[] = $c | $fb;
            }
            $covered = $next;
        }
        sort($covered);
        return $covered;
    }

    /**
     * Is this set of cells a legal group — a power-of-2 axis-aligned sub-cube
     * (Gray-adjacent with edge-wrap)? Legality here is purely structural; whether
     * every covered cell is a 1 of f is checked separately by the grader.
     *
     * @param  int[] $cells Distinct indices.
     * @return bool
     */
    public static function is_subcube(array $cells): bool {
        $size = count($cells);
        if ($size === 0) {
            return false;
        }
        // Size must be a power of two.
        if (($size & ($size - 1)) !== 0) {
            return false;
        }
        $cube = self::enclosing_cube($cells);
        // The enclosing sub-cube has exactly 2^popcount(free) members; the group is
        // a sub-cube iff it fills that cube exactly.
        return $size === (1 << self::popcount($cube['free']));
    }

    /**
     * Derive the product term (list of literals) of a legal group.
     *
     * @param  int[]    $cells    Non-empty distinct indices (assumed a sub-cube).
     * @param  string[] $varnames Display labels, MSB→LSB (index 0 = MSB).
     * @param  int      $nvars
     * @return array{literals:array<int,array{var:string,negated:bool}>, text:string}
     */
    public static function term(array $cells, array $varnames, int $nvars): array {
        $cube = self::enclosing_cube($cells);
        $and  = $cube['and'];
        $free = $cube['free'];
        $literals = [];
        // MSB = bit (nvars-1) = varnames[0].
        for ($pos = 0; $pos < $nvars; $pos++) {
            $bit = $nvars - 1 - $pos; // Bit position for varnames[$pos].
            if ($free & (1 << $bit)) {
                continue; // Free variable — omitted from the term.
            }
            $negated = (($and >> $bit) & 1) === 0;
            $literals[] = ['var' => $varnames[$pos] ?? chr(65 + $pos), 'negated' => $negated];
        }
        if (empty($literals)) {
            // Full-map group (all free) → constant 1.
            return ['literals' => [], 'text' => '1'];
        }
        $parts = [];
        foreach ($literals as $lit) {
            $parts[] = ($lit['negated'] ? '¬' : '') . $lit['var'];
        }
        return ['literals' => $literals, 'text' => implode('', $parts)];
    }

    /**
     * Union of the term-covers of every submitted group (the set on which the
     * OR-of-groups SOP expression evaluates to 1).
     *
     * @param  array $groups Each element = {cells:int[]}.
     * @param  int   $nvars
     * @return int[] Covered indices (sorted, distinct).
     */
    public static function union_cover(array $groups, int $nvars): array {
        $set = [];
        foreach ($groups as $g) {
            $cells = self::normalize_cells($g['cells'] ?? [], $nvars);
            if (empty($cells)) {
                continue;
            }
            foreach (self::term_cover($cells) as $i) {
                $set[$i] = true;
            }
        }
        $keys = array_keys($set);
        sort($keys);
        return $keys;
    }
}
