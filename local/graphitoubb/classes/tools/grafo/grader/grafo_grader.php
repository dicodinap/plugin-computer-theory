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
 * grafo grader — scores construct/decision/traversal answers (structural,
 * invariant-based; pure and DB-free). Implements the shared grader contract.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\grafo\grader;

use local_graphitoubb\grader_interface;
use local_graphitoubb\tools\grafo\domain\graph;
use local_graphitoubb\tools\grafo\domain\graph_algorithms as alg;

/**
 * Type-dispatched grader for the grafo tool.
 */
final class grafo_grader implements grader_interface {
    /**
     * Grade a grafo submission.
     *
     * @param  array       $problem        Decoded problem payload.
     * @param  string|null $submissionjson Answer-envelope JSON, or null.
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
            case 'construct':
                return $this->grade_construct($config, $envelope);
            case 'decision':
                return $this->grade_decision($config, $envelope);
            case 'traversal':
                return $this->grade_traversal($config, $envelope);
            default:
                return self::invalid_result('unknown_type');
        }
    }

    /**
     * construct: validity gate (empty/unparseable → invalid), then fraction of
     * satisfied constraints over ALL configured constraints (D18).
     *
     * @param  array $config
     * @param  array|null $envelope
     * @return array
     */
    private function grade_construct(array $config, ?array $envelope): array {
        $directed = (bool) ($config['directed'] ?? false);
        $constraints = $config['constraints'] ?? [];

        $graphraw = $envelope['graph'] ?? null;
        $g = graph::from_array($graphraw, $directed);

        // Validity gate: empty (0 nodes) or unparseable canvas.
        if ($g === null || $g->vertex_count() === 0) {
            return self::invalid_result('empty');
        }

        $results = [];
        foreach ($constraints as $key => $expected) {
            $results[] = $this->check_constraint($g, (string) $key, $expected);
        }

        $total   = count($results);
        $correct = 0;
        foreach ($results as $r) {
            if ($r['correct']) {
                $correct++;
            }
        }
        // No constraints configured: a non-empty graph trivially satisfies (1.0).
        $fraction = $total > 0 ? $correct / $total : 1.0;
        return self::scored_result($fraction, $total, $correct, $results);
    }

    /**
     * Evaluate one construct constraint against the submitted graph.
     *
     * @param  graph  $g
     * @param  string $key
     * @param  mixed  $expected
     * @return array{check:string,expected:mixed,got:mixed,correct:bool}
     */
    private function check_constraint(graph $g, string $key, $expected): array {
        switch ($key) {
            case 'n_vertices':
                $got = $g->vertex_count();
                return self::check($key, (int) $expected, $got, $got === (int) $expected);
            case 'n_edges':
                $got = $g->edge_count();
                return self::check($key, (int) $expected, $got, $got === (int) $expected);
            case 'degree_sequence':
                $exp = array_map('intval', is_array($expected) ? $expected : []);
                $got = $g->degree_sequence();
                return self::check($key, $exp, $got, alg::degree_sequences_match($exp, $got));
            case 'connected':
                $got = alg::is_connected($g);
                return self::check($key, (bool) $expected, $got, $got === (bool) $expected);
            case 'bipartite':
                $got = alg::is_bipartite($g);
                return self::check($key, (bool) $expected, $got, $got === (bool) $expected);
            case 'acyclic':
                $got = alg::is_acyclic($g);
                return self::check($key, (bool) $expected, $got, $got === (bool) $expected);
            case 'is_tree':
                $got = alg::is_tree($g);
                return self::check($key, (bool) $expected, $got, $got === (bool) $expected);
            case 'eulerian':
                $got = alg::has_euler_circuit($g);
                return self::check($key, (bool) $expected, $got, $got === (bool) $expected);
            default:
                // Unknown constraint key: never satisfiable (defensive).
                return self::check($key, $expected, null, false);
        }
    }

    /**
     * decision: recompute the true value of the question from the given graph and
     * compare to the student's boolean (all-or-nothing).
     *
     * @param  array $config
     * @param  array|null $envelope
     * @return array
     */
    private function grade_decision(array $config, ?array $envelope): array {
        if ($envelope === null || ($envelope['answer_kind'] ?? '') !== 'boolean'
                || !array_key_exists('value', $envelope)) {
            return self::invalid_result('no_answer');
        }
        $directed = (bool) ($config['given_graph']['directed'] ?? false);
        $g = graph::from_array($config['given_graph'] ?? null, $directed);
        if ($g === null) {
            return self::invalid_result('bad_problem');
        }
        $question = (string) ($config['question'] ?? '');
        $truth    = $this->compute_question($g, $question);
        if ($truth === null) {
            return self::invalid_result('bad_problem');
        }
        $student = (bool) $envelope['value'];
        $correct = ($student === $truth);
        $results = [self::check($question, $truth, $student, $correct)];
        return self::scored_result($correct ? 1.0 : 0.0, 1, $correct ? 1 : 0, $results);
    }

    /**
     * Compute the boolean truth value of a decision question on a graph.
     *
     * @param  graph  $g
     * @param  string $question
     * @return bool|null null when the question label is unknown.
     */
    private function compute_question(graph $g, string $question): ?bool {
        switch ($question) {
            case 'has_euler_circuit':
                return alg::has_euler_circuit($g);
            case 'has_euler_path':
                return alg::has_euler_path($g);
            case 'has_hamiltonian_path':
                return alg::has_hamiltonian_path($g);
            case 'is_connected':
                return alg::is_connected($g);
            case 'is_bipartite':
                return alg::is_bipartite($g);
            default:
                return null;
        }
    }

    /**
     * traversal: 1 if the submitted edge-id walk is a valid walk of walk_kind on
     * the given graph, else 0.
     *
     * @param  array $config
     * @param  array|null $envelope
     * @return array
     */
    private function grade_traversal(array $config, ?array $envelope): array {
        if ($envelope === null || ($envelope['answer_kind'] ?? '') !== 'sequence'
                || empty($envelope['edges']) || !is_array($envelope['edges'])) {
            return self::invalid_result('empty');
        }
        $directed = (bool) ($config['given_graph']['directed'] ?? false);
        $g = graph::from_array($config['given_graph'] ?? null, $directed);
        if ($g === null) {
            return self::invalid_result('bad_problem');
        }
        $walkkind    = (string) ($config['walk_kind'] ?? '');
        $startvertex = isset($config['start_vertex']) && $config['start_vertex'] !== ''
            ? (string) $config['start_vertex'] : null;
        $edgeids = array_map('strval', $envelope['edges']);

        $valid   = alg::validate_walk($g, $edgeids, $walkkind, $startvertex);
        $results = [self::check($walkkind, true, $valid, $valid)];
        return self::scored_result($valid ? 1.0 : 0.0, 1, $valid ? 1 : 0, $results);
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
