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
 * relations grader — scores the built representation (Jaccard partial credit) and
 * the four declared properties (with counterexamples from the canonical R). The
 * two axes are independent. Pure, DB-free.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\relations\grader;

use local_graphitoubb\grader\result_builder;
use local_graphitoubb\grader_interface;
use local_graphitoubb\tools\relations\domain\relation;

/**
 * Grader for the relations:analyze tool.
 */
final class relations_grader implements grader_interface {
    use result_builder;

    /**
     * Grade a relations submission.
     *
     * @param  array       $problem        Decoded problem payload ({config}).
     * @param  string|null $submissionjson Answer-envelope JSON, or null.
     * @return array Shared result array.
     */
    public function grade(array $problem, ?string $submissionjson): array {
        $config  = $problem['config'] ?? [];
        $baseset = array_values(array_map('strval', $config['base_set'] ?? []));
        $canonical = relation::normalize_pairs($config['relation'] ?? [], $baseset);
        $askprops = $config['ask_properties'] ?? relation::PROPERTIES;
        $askprops = array_values(array_intersect(relation::PROPERTIES, array_map('strval', $askprops)));
        if (empty($askprops)) {
            $askprops = relation::PROPERTIES;
        }

        $scoring = $config['scoring'] ?? [];
        $repweight  = (float) ($scoring['representation_weight'] ?? 40);
        $propweight = (float) ($scoring['properties_weight'] ?? 60);

        $envelope = null;
        if ($submissionjson !== null && $submissionjson !== '') {
            $decoded = json_decode($submissionjson, true);
            if (is_array($decoded)) {
                $envelope = $decoded;
            }
        }
        if ($envelope === null || ($envelope['answer_kind'] ?? '') !== 'relation') {
            return self::invalid_result('empty');
        }

        $studentpairs = relation::normalize_pairs($envelope['pairs'] ?? [], $baseset);
        $studentprops = is_array($envelope['properties'] ?? null) ? $envelope['properties'] : [];

        $results = [];

        // ---- Representation (Jaccard partial credit) ----------------------------
        $cmp = relation::compare($canonical, $studentpairs);
        $union = $cmp['union'];
        if ($union === 0) {
            // Both empty: an empty relation built correctly is full credit.
            $repfraction = 1.0;
        } else {
            $repfraction = $cmp['intersection'] / $union;
        }
        $repcorrect = ($cmp['missing'] === [] && $cmp['extra'] === []);
        $results[] = self::check('representation', [
            'pairs' => count($canonical),
        ], [
            'missing' => $cmp['missing'],
            'extra'   => $cmp['extra'],
        ], $repcorrect);

        // ---- Properties (with counterexamples) ----------------------------------
        $propcorrect = 0;
        foreach ($askprops as $prop) {
            $truth   = relation::holds($prop, $canonical, $baseset);
            $declared = !empty($studentprops[$prop]);
            $ok = ($declared === $truth);
            if ($ok) {
                $propcorrect++;
                $results[] = self::check('property:' . $prop, $truth, $declared, true);
                continue;
            }
            if ($declared && !$truth) {
                // Declared TRUE but property is FALSE → witnessing counterexample.
                $ce = relation::counterexample($prop, $canonical, $baseset);
                $results[] = self::check('property:' . $prop, $truth, [
                    'declared'       => $declared,
                    'counterexample' => $ce,
                ], false);
            } else {
                // Declared FALSE but property is TRUE → no counterexample.
                $results[] = self::check('property:' . $prop, $truth, [
                    'declared'       => $declared,
                    'counterexample' => null,
                    'holds'          => true,
                ], false);
            }
        }
        $propfraction = count($askprops) > 0 ? $propcorrect / count($askprops) : 0.0;

        $fraction = ($repweight / 100.0) * $repfraction + ($propweight / 100.0) * $propfraction;
        $fraction = max(0.0, min(1.0, $fraction));

        $itemstotal   = 1 + count($askprops);
        $itemscorrect = ($repcorrect ? 1 : 0) + $propcorrect;

        return self::scored_result($fraction, $itemstotal, $itemscorrect, $results);
    }
}
