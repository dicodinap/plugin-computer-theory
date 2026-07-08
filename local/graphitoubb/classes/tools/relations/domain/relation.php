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
 * Binary-relation domain — normalisation, property predicates and counterexample
 * extraction. Pure, DB-free, layout-independent (matrix/pairs/digraph all
 * normalise to a pair list before analysis).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\relations\domain;

/**
 * Static helpers for a binary relation R ⊆ S×S over a base set S.
 */
final class relation {
    /** The four supported properties. */
    public const PROPERTIES = ['reflexive', 'symmetric', 'antisymmetric', 'transitive'];

    /**
     * Normalise a raw pair list to a list of [string,string] pairs, deduplicated
     * and (optionally) restricted to elements of the base set.
     *
     * @param  mixed    $pairs   Raw pairs (each [a,b]).
     * @param  string[] $baseset Allowed elements; empty = accept all.
     * @return array<int,array{0:string,1:string}> Sorted distinct pairs.
     */
    public static function normalize_pairs($pairs, array $baseset = []): array {
        $allowed = $baseset ? array_fill_keys(array_map('strval', $baseset), true) : null;
        $seen = [];
        $out  = [];
        if (is_array($pairs)) {
            foreach ($pairs as $p) {
                if (!is_array($p) || count($p) < 2) {
                    continue;
                }
                $a = (string) array_values($p)[0];
                $b = (string) array_values($p)[1];
                if ($allowed !== null && (!isset($allowed[$a]) || !isset($allowed[$b]))) {
                    continue;
                }
                $key = $a . "\x1f" . $b;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key]  = true;
                $out[]       = [$a, $b];
            }
        }
        usort($out, static function (array $x, array $y): int {
            return [$x[0], $x[1]] <=> [$y[0], $y[1]];
        });
        return $out;
    }

    /**
     * Build a fast membership lookup set from a normalised pair list.
     *
     * @param  array<int,array{0:string,1:string}> $pairs
     * @return array<string,true>
     */
    public static function membership(array $pairs): array {
        $set = [];
        foreach ($pairs as $p) {
            $set[$p[0] . "\x1f" . $p[1]] = true;
        }
        return $set;
    }

    /**
     * Compute the truth value of a property on relation R over base set S.
     *
     * @param  string   $property One of self::PROPERTIES.
     * @param  array<int,array{0:string,1:string}> $pairs  Normalised R.
     * @param  string[] $baseset  S.
     * @return bool
     */
    public static function holds(string $property, array $pairs, array $baseset): bool {
        return self::counterexample($property, $pairs, $baseset) === null;
    }

    /**
     * Return the first witnessing counterexample when a property is FALSE, or null
     * when the property holds. The shape depends on the property:
     *   reflexive     → {kind:'reflexive', a}
     *   symmetric     → {kind:'symmetric', a, b}
     *   antisymmetric → {kind:'antisymmetric', a, b}
     *   transitive    → {kind:'transitive', a, b, c}
     *
     * @param  string   $property
     * @param  array<int,array{0:string,1:string}> $pairs Normalised R.
     * @param  string[] $baseset S.
     * @return array|null
     */
    public static function counterexample(string $property, array $pairs, array $baseset): ?array {
        $set = self::membership($pairs);
        $has = static function (string $a, string $b) use ($set): bool {
            return isset($set[$a . "\x1f" . $b]);
        };

        switch ($property) {
            case 'reflexive':
                foreach ($baseset as $a) {
                    $a = (string) $a;
                    if (!$has($a, $a)) {
                        return ['kind' => 'reflexive', 'a' => $a];
                    }
                }
                return null;

            case 'symmetric':
                foreach ($pairs as $p) {
                    if (!$has($p[1], $p[0])) {
                        return ['kind' => 'symmetric', 'a' => $p[0], 'b' => $p[1]];
                    }
                }
                return null;

            case 'antisymmetric':
                foreach ($pairs as $p) {
                    if ($p[0] !== $p[1] && $has($p[1], $p[0])) {
                        return ['kind' => 'antisymmetric', 'a' => $p[0], 'b' => $p[1]];
                    }
                }
                return null;

            case 'transitive':
                foreach ($pairs as $p) {
                    foreach ($pairs as $q) {
                        if ($p[1] === $q[0] && !$has($p[0], $q[1])) {
                            return ['kind' => 'transitive', 'a' => $p[0], 'b' => $p[1], 'c' => $q[1]];
                        }
                    }
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Set difference / intersection helpers over normalised pair lists.
     *
     * @param  array<int,array{0:string,1:string}> $a
     * @param  array<int,array{0:string,1:string}> $b
     * @return array{missing:array,extra:array,intersection:int,union:int}
     */
    public static function compare(array $a, array $b): array {
        // $a = canonical (expected), $b = student.
        $seta = self::membership($a);
        $setb = self::membership($b);
        $missing = []; // In canonical but not student.
        foreach ($a as $p) {
            if (!isset($setb[$p[0] . "\x1f" . $p[1]])) {
                $missing[] = $p;
            }
        }
        $extra = []; // In student but not canonical.
        foreach ($b as $p) {
            if (!isset($seta[$p[0] . "\x1f" . $p[1]])) {
                $extra[] = $p;
            }
        }
        $inter = count($a) - count($missing);
        $union = count($a) + count($extra);
        return ['missing' => $missing, 'extra' => $extra, 'intersection' => $inter, 'union' => $union];
    }
}
