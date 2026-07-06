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
 * arbol grader — scores bst_build/traversal_answer/reconstruct answers (pure,
 * DB-free). Implements the shared grader contract.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\arbol\grader;

use local_graphitoubb\grader_interface;
use local_graphitoubb\tools\arbol\domain\bst;
use local_graphitoubb\tools\arbol\domain\tree;
use local_graphitoubb\tools\arbol\domain\tree_reconstruct;
use local_graphitoubb\tools\arbol\domain\tree_traversal;

/**
 * Type-dispatched grader for the arbol tool.
 */
final class arbol_grader implements grader_interface {
    /**
     * Grade an arbol submission.
     *
     * @param  array       $problem
     * @param  string|null $submissionjson
     * @return array Shared result array.
     */
    public function grade(array $problem, ?string $submissionjson): array {
        $type   = (string) ($problem['type'] ?? '');
        $config = $problem['config'] ?? [];

        $envelope = null;
        if ($submissionjson !== null && $submissionjson !== '') {
            $decoded = json_decode($submissionjson, true);
            if (is_array($decoded)) {
                $envelope = $decoded;
            }
        }

        switch ($type) {
            case 'bst_build':
                return $this->grade_bst_build($config, $envelope);
            case 'traversal_answer':
                return $this->grade_traversal_answer($config, $envelope);
            case 'reconstruct':
                return $this->grade_reconstruct($config, $envelope);
            default:
                return self::invalid_result('unknown_type');
        }
    }

    /**
     * bst_build: per-node partial credit vs the canonical BST (D12).
     *
     * @param  array $config
     * @param  array|null $envelope
     * @return array
     */
    private function grade_bst_build(array $config, ?array $envelope): array {
        $insertions = array_map('intval', $config['insertions'] ?? []);
        $canonical  = bst::position_value_map($insertions);
        $distinct   = count(bst::distinct_values($insertions));

        $t = tree::from_array($envelope['tree'] ?? null);
        if ($t === null || $t->is_empty()) {
            return self::invalid_result('empty');
        }
        if (!$t->is_valid()) {
            return self::invalid_result('invalid_tree');
        }

        $student = $t->position_value_map();
        $matches = 0;
        $results = [];
        foreach ($canonical as $path => $value) {
            $got = $student[$path] ?? null;
            $ok  = ($got === $value);
            if ($ok) {
                $matches++;
            }
            $results[] = self::check('pos:' . ($path === '' ? 'root' : $path), $value, $got, $ok);
        }
        $total    = max($distinct, 1);
        $fraction = $matches / $total;
        return self::scored_result($fraction, $total, $matches, $results);
    }

    /**
     * traversal_answer: longest-common-prefix ratio vs the canonical order (D11).
     *
     * @param  array $config
     * @param  array|null $envelope
     * @return array
     */
    private function grade_traversal_answer(array $config, ?array $envelope): array {
        if ($envelope === null || ($envelope['answer_kind'] ?? '') !== 'sequence'
                || !isset($envelope['values']) || !is_array($envelope['values'])
                || count($envelope['values']) === 0) {
            return self::invalid_result('empty');
        }
        $given = tree::from_array($config['given_tree'] ?? null);
        if ($given === null || !$given->is_valid()) {
            return self::invalid_result('bad_problem');
        }
        $order     = (string) ($config['order'] ?? 'in');
        $canonical = tree_traversal::order($given, $order);
        $student   = array_map('intval', $envelope['values']);

        $lcp = 0;
        $n   = count($canonical);
        for ($i = 0; $i < $n; $i++) {
            if (isset($student[$i]) && $student[$i] === $canonical[$i]) {
                $lcp++;
            } else {
                break;
            }
        }
        $fraction = $n > 0 ? $lcp / $n : 0.0;
        $results  = [self::check('longest_common_prefix', $canonical, $student, $lcp === $n)];
        return self::scored_result($fraction, $n, $lcp, $results);
    }

    /**
     * reconstruct: per-node partial credit vs the unique reconstructed tree (D12).
     *
     * @param  array $config
     * @param  array|null $envelope
     * @return array
     */
    private function grade_reconstruct(array $config, ?array $envelope): array {
        $pair = (string) ($config['pair'] ?? '');
        $a    = $config['a'] ?? [];
        $b    = $config['b'] ?? [];
        $canonical = tree_reconstruct::position_value_map($pair, (array) $a, (array) $b);
        if ($canonical === null) {
            return self::invalid_result('bad_problem');
        }

        $t = tree::from_array($envelope['tree'] ?? null);
        if ($t === null || $t->is_empty()) {
            return self::invalid_result('empty');
        }
        if (!$t->is_valid()) {
            return self::invalid_result('invalid_tree');
        }

        $student = $t->position_value_map();
        $matches = 0;
        $results = [];
        foreach ($canonical as $path => $value) {
            $got = $student[$path] ?? null;
            $ok  = ($got === $value);
            if ($ok) {
                $matches++;
            }
            $results[] = self::check('pos:' . ($path === '' ? 'root' : $path), $value, $got, $ok);
        }
        $total    = max(count($canonical), 1);
        $fraction = $matches / $total;
        return self::scored_result($fraction, $total, $matches, $results);
    }

    /**
     * Build a single result-row.
     *
     * @param  string $check
     * @param  mixed  $expected
     * @param  mixed  $got
     * @param  bool   $correct
     * @return array{check:string,expected:mixed,got:mixed,correct:bool}
     */
    private static function check(string $check, $expected, $got, bool $correct): array {
        return ['check' => $check, 'expected' => $expected, 'got' => $got, 'correct' => $correct];
    }

    /**
     * Build a graded (valid) result array.
     *
     * @param  float $fraction
     * @param  int   $total
     * @param  int   $correct
     * @param  array $results
     * @return array
     */
    private static function scored_result(float $fraction, int $total, int $correct, array $results): array {
        return [
            'graded'        => true,
            'invalid'       => false,
            'message'       => null,
            'score'         => $fraction,
            'fraction'      => $fraction,
            'passed'        => $fraction >= self::PASS_THRESHOLD,
            'items_total'   => $total,
            'items_correct' => $correct,
            'results'       => $results,
        ];
    }

    /**
     * Build an invalid (ungradeable) result array (fraction 0).
     *
     * @param  string $message
     * @return array
     */
    private static function invalid_result(string $message): array {
        return [
            'graded'        => true,
            'invalid'       => true,
            'message'       => $message,
            'score'         => 0.0,
            'fraction'      => 0.0,
            'passed'        => false,
            'items_total'   => 0,
            'items_correct' => 0,
            'results'       => [],
        ];
    }
}
