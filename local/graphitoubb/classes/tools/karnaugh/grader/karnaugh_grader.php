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
 * karnaugh grader — scores the two-stage simplify answer (fill + grouping) with
 * equivalence, group validity and configurable strict minimality. Pure, DB-free.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\karnaugh\grader;

use local_graphitoubb\grader\result_builder;
use local_graphitoubb\grader_interface;
use local_graphitoubb\tools\karnaugh\domain\kmap;
use local_graphitoubb\tools\karnaugh\domain\minimize;

/**
 * Grader for the karnaugh:simplify tool.
 */
final class karnaugh_grader implements grader_interface {
    use result_builder;

    /**
     * Grade a karnaugh submission.
     *
     * @param  array       $problem        Decoded problem payload ({type, config}).
     * @param  string|null $submissionjson Answer-envelope JSON, or null.
     * @return array Shared result array.
     */
    public function grade(array $problem, ?string $submissionjson): array {
        $config = $problem['config'] ?? [];
        $nvars  = (int) ($config['n_vars'] ?? 0);
        if ($nvars < 2 || $nvars > 4) {
            return self::invalid_result('bad_problem');
        }
        $size     = 1 << $nvars;
        $minterms = kmap::normalize_cells($config['minterms'] ?? [], $nvars);
        $mintset  = array_fill_keys($minterms, true);
        $varnames = array_values(array_map('strval', $config['var_names'] ?? []));

        $requireminimal = !array_key_exists('require_minimal', $config) || (bool) $config['require_minimal'];
        $scoring = $config['scoring'] ?? [];
        $fillweight     = (float) ($scoring['fill_weight'] ?? 40);
        $groupingweight = (float) ($scoring['grouping_weight'] ?? 60);

        $envelope = null;
        if ($submissionjson !== null && $submissionjson !== '') {
            $decoded = json_decode($submissionjson, true);
            if (is_array($decoded)) {
                $envelope = $decoded;
            }
        }
        if ($envelope === null || ($envelope['answer_kind'] ?? '') !== 'kmap') {
            return self::invalid_result('empty');
        }

        $groups = $envelope['groups'] ?? [];
        if (!is_array($groups) || count($groups) === 0) {
            // No grouping submitted → the whole answer is ungradeable (validity gate).
            return self::invalid_result('empty');
        }

        $results = [];

        // ---- STAGE 1: fill ------------------------------------------------------
        $cells = [];
        if (isset($envelope['map']['cells']) && is_array($envelope['map']['cells'])) {
            $cells = $envelope['map']['cells'];
        }
        $fillcorrect = 0;
        for ($i = 0; $i < $size; $i++) {
            $expected = isset($mintset[$i]) ? 1 : 0;
            $got = null;
            if (array_key_exists((string) $i, $cells)) {
                $got = (int) $cells[(string) $i];
            } else if (array_key_exists($i, $cells)) {
                $got = (int) $cells[$i];
            }
            $ok = ($got === $expected);
            if ($ok) {
                $fillcorrect++;
            } else {
                $results[] = self::check('cell:' . $i, $expected, $got, false);
            }
        }
        $fillfraction = $size > 0 ? $fillcorrect / $size : 0.0;

        // ---- STAGE 2: grouping --------------------------------------------------
        $totalgroups = count($groups);
        $validgroups = 0;
        foreach ($groups as $idx => $g) {
            $gid   = (string) ($g['id'] ?? ('g' . $idx));
            $gcells = kmap::normalize_cells($g['cells'] ?? [], $nvars);
            if (empty($gcells)) {
                $results[] = self::check('group:' . $gid, 'valid', 'empty', false);
                continue;
            }
            $issub = kmap::is_subcube($gcells);
            // Every listed cell must be a 1 of f.
            $covers0 = false;
            foreach ($gcells as $c) {
                if (!isset($mintset[$c])) {
                    $covers0 = true;
                    break;
                }
            }
            $legal = $issub && !$covers0;
            if ($legal) {
                $validgroups++;
                $results[] = self::check('group:' . $gid, kmap::term($gcells, $varnames, $nvars)['text'], 'ok', true);
            } else {
                $reason = !$issub ? 'not_subcube' : 'covers_zero';
                $results[] = self::check('group:' . $gid, 'valid', $reason, false);
            }
        }
        $validityscore = $totalgroups > 0 ? $validgroups / $totalgroups : 0.0;

        // Equivalence: OR-of-groups ≡ f over all 2ⁿ assignments.
        $union = kmap::union_cover($groups, $nvars);
        $unionset = array_fill_keys($union, true);
        $overcovered = [];
        foreach ($union as $i) {
            if (!isset($mintset[$i])) {
                $overcovered[] = $i;
            }
        }
        $uncovered = [];
        foreach ($minterms as $m) {
            if (!isset($unionset[$m])) {
                $uncovered[] = $m;
            }
        }
        $equivalent = empty($overcovered) && empty($uncovered);
        if (!$equivalent) {
            $results[] = self::check('equivalence', 'f', [
                'over_covered' => $overcovered,
                'uncovered'    => $uncovered,
            ], false);
        }

        // Minimality (only when require_minimal).
        $message  = null;
        $minscore = 1.0;
        $optimal  = null;
        if ($requireminimal) {
            $optimal  = minimize::optimal_cover_size($minterms, $nvars);
            $used     = $validgroups;
            $minscore = min(1.0, $optimal / max($used, 1));
            $results[] = self::check('minimality', $optimal, $used, $used <= max($optimal, 1) && $used > 0);
            $message  = 'optimal:' . $optimal . ';used:' . $used;
        }

        // Grouping fraction.
        if (!$equivalent) {
            $groupingfraction = 0.0;
        } else if ($requireminimal) {
            $groupingfraction = ($validityscore + $minscore) / 2.0;
        } else {
            $groupingfraction = $validityscore;
        }

        $fraction = ($fillweight / 100.0) * $fillfraction
                  + ($groupingweight / 100.0) * $groupingfraction;
        $fraction = max(0.0, min(1.0, $fraction));

        // Generic count pair: cells + groups checked.
        $itemstotal   = $size + $totalgroups;
        $itemscorrect = $fillcorrect + $validgroups;

        return self::scored_result($fraction, $itemstotal, $itemscorrect, $results, $message);
    }
}
