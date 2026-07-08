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
 * Karnaugh minimality — minimum prime-implicant cover size (≤ 16 cells).
 *
 * Quine–McCluskey generates all prime implicants; an exact set-cover (essential
 * PIs + branch-and-bound on the rest) returns the minimum number of groups a
 * fully-simplified SOP answer needs. Used to reward fewer/larger groups behind
 * the `require_minimal` flag.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\karnaugh\domain;

/**
 * Static minimal-cover computation for a boolean function given by its minterms.
 */
final class minimize {
    /**
     * Minimum number of prime implicants needed to cover every minterm.
     *
     * @param  int[] $minterms Distinct assignment indices where f = 1.
     * @param  int   $nvars    Number of variables (2..4).
     * @return int   Optimal group count (0 when there are no minterms).
     */
    public static function optimal_cover_size(array $minterms, int $nvars): int {
        $minterms = self::normalize($minterms, $nvars);
        if (empty($minterms)) {
            return 0;
        }
        // Tautology: a single all-free group (term = 1) covers everything.
        if (count($minterms) === (1 << $nvars)) {
            return 1;
        }

        $primes = self::prime_implicants($minterms, $nvars);
        return self::min_cover($minterms, $primes);
    }

    /**
     * Normalise minterms to distinct valid indices.
     *
     * @param  int[] $minterms
     * @param  int   $nvars
     * @return int[]
     */
    private static function normalize(array $minterms, int $nvars): array {
        $max = (1 << $nvars) - 1;
        $set = [];
        foreach ($minterms as $m) {
            $i = (int) $m;
            if ($i >= 0 && $i <= $max) {
                $set[$i] = true;
            }
        }
        $keys = array_keys($set);
        sort($keys);
        return $keys;
    }

    /**
     * Compute all prime implicants via Quine–McCluskey.
     *
     * An implicant is encoded as [bits, dash] where `dash` marks the free (don't-
     * fix) bit positions and `bits` holds the fixed values (0 on dash bits).
     *
     * @param  int[] $minterms
     * @param  int   $nvars
     * @return array<int,array{bits:int,dash:int}> Prime implicants (deduped).
     */
    private static function prime_implicants(array $minterms, int $nvars): array {
        // Current layer of implicants keyed by "bits:dash".
        $current = [];
        foreach ($minterms as $m) {
            $current[$m . ':0'] = ['bits' => $m, 'dash' => 0];
        }

        $primes = [];
        while (!empty($current)) {
            $used   = [];
            $next   = [];
            $items  = array_values($current);
            $n      = count($items);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $items[$i];
                    $b = $items[$j];
                    if ($a['dash'] !== $b['dash']) {
                        continue;
                    }
                    $diff = $a['bits'] ^ $b['bits'];
                    // Combine only when they differ in exactly one fixed bit.
                    if ($diff !== 0 && ($diff & ($diff - 1)) === 0) {
                        $mergedbits = $a['bits'] & $b['bits'];
                        $mergeddash = $a['dash'] | $diff;
                        $key = $mergedbits . ':' . $mergeddash;
                        $next[$key] = ['bits' => $mergedbits, 'dash' => $mergeddash];
                        $used[$i] = true;
                        $used[$j] = true;
                    }
                }
            }
            // Any implicant not combined this round is prime.
            for ($i = 0; $i < $n; $i++) {
                if (empty($used[$i])) {
                    $key = $items[$i]['bits'] . ':' . $items[$i]['dash'];
                    $primes[$key] = $items[$i];
                }
            }
            $current = $next;
        }

        return array_values($primes);
    }

    /**
     * Minterms covered by one prime implicant.
     *
     * @param  array{bits:int,dash:int} $pi
     * @return int[] Covered indices.
     */
    private static function covered_minterms(array $pi): array {
        $free = $pi['dash'];
        $base = $pi['bits'];
        $freebits = [];
        for ($b = 0; (1 << $b) <= $free; $b++) {
            if ($free & (1 << $b)) {
                $freebits[] = 1 << $b;
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
        return $covered;
    }

    /**
     * Minimum number of prime implicants covering all minterms (exact).
     *
     * Extracts essential PIs first, then branch-and-bounds the remainder using the
     * "least-covered minterm" branching rule. Cheap for ≤ 16 minterms.
     *
     * @param  int[] $minterms
     * @param  array<int,array{bits:int,dash:int}> $primes
     * @return int
     */
    private static function min_cover(array $minterms, array $primes): int {
        // Build coverage bitsets: for each PI, which minterms (by list index) it covers.
        $mindex = [];
        foreach ($minterms as $k => $m) {
            $mindex[$m] = $k;
        }
        $full = (count($minterms) === 0) ? 0 : ((1 << count($minterms)) - 1);

        $pimasks = [];
        foreach ($primes as $pi) {
            $mask = 0;
            foreach (self::covered_minterms($pi) as $m) {
                if (isset($mindex[$m])) {
                    $mask |= (1 << $mindex[$m]);
                }
            }
            if ($mask !== 0) {
                $pimasks[] = $mask;
            }
        }

        // Greedy upper bound to seed the branch-and-bound.
        $best = self::greedy_cover_size($full, $pimasks);
        self::branch($full, $pimasks, 0, $best);
        return $best;
    }

    /**
     * Greedy set-cover size (upper bound).
     *
     * @param  int   $remaining Bitset of still-uncovered minterms.
     * @param  int[] $pimasks   Coverage bitsets.
     * @return int
     */
    private static function greedy_cover_size(int $remaining, array $pimasks): int {
        $count = 0;
        while ($remaining !== 0) {
            $bestmask = 0;
            $bestgain = -1;
            foreach ($pimasks as $mask) {
                $gain = self::popcount($mask & $remaining);
                if ($gain > $bestgain) {
                    $bestgain = $gain;
                    $bestmask = $mask;
                }
            }
            if ($bestgain <= 0) {
                break; // Unreachable for a well-formed PI set.
            }
            $remaining &= ~$bestmask;
            $count++;
        }
        return $count;
    }

    /**
     * Branch-and-bound exact set cover; updates $best by reference.
     *
     * @param  int   $remaining Uncovered minterm bitset.
     * @param  int[] $pimasks   Coverage bitsets.
     * @param  int   $used      Groups used so far.
     * @param  int   $best      Current best (by reference).
     * @return void
     */
    private static function branch(int $remaining, array $pimasks, int $used, int &$best): void {
        if ($remaining === 0) {
            if ($used < $best) {
                $best = $used;
            }
            return;
        }
        if ($used + 1 >= $best) {
            return; // Cannot beat the incumbent.
        }
        // Branch on the uncovered minterm covered by the fewest PIs.
        $targetbit = -1;
        $fewest    = PHP_INT_MAX;
        $rem = $remaining;
        while ($rem !== 0) {
            $bit = $rem & (-$rem);
            $cnt = 0;
            foreach ($pimasks as $mask) {
                if ($mask & $bit) {
                    $cnt++;
                }
            }
            if ($cnt < $fewest) {
                $fewest    = $cnt;
                $targetbit = $bit;
            }
            $rem &= ~$bit;
        }
        if ($targetbit === -1) {
            return;
        }
        foreach ($pimasks as $mask) {
            if (($mask & $targetbit) === 0) {
                continue;
            }
            self::branch($remaining & ~$mask, $pimasks, $used + 1, $best);
        }
    }

    /**
     * Population count.
     *
     * @param  int $x
     * @return int
     */
    private static function popcount(int $x): int {
        $c = 0;
        while ($x > 0) {
            $c += $x & 1;
            $x >>= 1;
        }
        return $c;
    }
}
