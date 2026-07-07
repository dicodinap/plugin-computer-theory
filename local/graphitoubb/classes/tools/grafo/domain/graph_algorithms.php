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
 * Graph-theory predicates for the grafo tool (pure, DB-free, unit-testable).
 *
 * Implements the precise directed/undirected semantics pinned in PRD D21.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\grafo\domain;

/**
 * Stateless collection of graph algorithms operating on a graph value object.
 */
final class graph_algorithms {
    /**
     * Weak connectivity over ALL vertices (undirected reachability). An empty
     * or single-vertex graph is trivially connected.
     *
     * @param  graph $g
     * @return bool
     */
    public static function is_connected(graph $g): bool {
        $ids = $g->node_ids();
        if (count($ids) <= 1) {
            return true;
        }
        return count(self::undirected_component($g, $ids[0])) === count($ids);
    }

    /**
     * Connectivity restricted to vertices with non-zero degree (Euler ignores
     * isolated vertices). True when all non-isolated vertices form one
     * undirected component (or there are none).
     *
     * @param  graph $g
     * @return bool
     */
    public static function is_connected_ignoring_isolated(graph $g): bool {
        $active = [];
        foreach ($g->node_ids() as $v) {
            if ($g->degree($v) > 0) {
                $active[] = $v;
            }
        }
        if (count($active) <= 1) {
            return true;
        }
        $reach = self::undirected_component($g, $active[0]);
        foreach ($active as $v) {
            if (!isset($reach[$v])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Strong connectivity over vertices with non-zero total degree (directed).
     * Empty / single active vertex is trivially strongly connected.
     *
     * @param  graph $g
     * @return bool
     */
    public static function is_strongly_connected_nonzero(graph $g): bool {
        $active = [];
        foreach ($g->node_ids() as $v) {
            if ($g->out_degree($v) + $g->in_degree($v) > 0) {
                $active[] = $v;
            }
        }
        if (count($active) <= 1) {
            return true;
        }
        // Forward reachability from active[0] must cover all active vertices,
        // and so must reverse reachability (edges flipped).
        $fwd = self::directed_reach($g, $active[0], false);
        $rev = self::directed_reach($g, $active[0], true);
        foreach ($active as $v) {
            if (!isset($fwd[$v]) || !isset($rev[$v])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Undirected connected component containing $start (BFS).
     *
     * @param  graph  $g
     * @param  string $start
     * @return array<string,true> visited-set keyed by node id
     */
    private static function undirected_component(graph $g, string $start): array {
        $seen  = [$start => true];
        $queue = [$start];
        while ($queue) {
            $v = array_shift($queue);
            foreach ($g->undirected_neighbours($v) as $w) {
                if (!isset($seen[$w])) {
                    $seen[$w] = true;
                    $queue[]  = $w;
                }
            }
        }
        return $seen;
    }

    /**
     * Directed reachability from $start; when $reverse is true, edges are
     * traversed backwards (to→from).
     *
     * @param  graph  $g
     * @param  string $start
     * @param  bool   $reverse
     * @return array<string,true>
     */
    private static function directed_reach(graph $g, string $start, bool $reverse): array {
        $seen  = [$start => true];
        $queue = [$start];
        while ($queue) {
            $v = array_shift($queue);
            foreach ($g->edges() as $e) {
                $a = $reverse ? $e['to'] : $e['from'];
                $b = $reverse ? $e['from'] : $e['to'];
                if ($a === $v && !isset($seen[$b])) {
                    $seen[$b] = true;
                    $queue[]  = $b;
                }
            }
        }
        return $seen;
    }

    /**
     * Bipartite (2-colourable) — undirected interpretation. A self-loop makes
     * the graph non-bipartite. Empty graph is bipartite.
     *
     * @param  graph $g
     * @return bool
     */
    public static function is_bipartite(graph $g): bool {
        foreach ($g->edges() as $e) {
            if ($e['from'] === $e['to']) {
                return false;
            }
        }
        $colour = [];
        foreach ($g->node_ids() as $start) {
            if (isset($colour[$start])) {
                continue;
            }
            $colour[$start] = 0;
            $queue = [$start];
            while ($queue) {
                $v = array_shift($queue);
                foreach ($g->undirected_neighbours($v) as $w) {
                    if (!isset($colour[$w])) {
                        $colour[$w] = 1 - $colour[$v];
                        $queue[]    = $w;
                    } else if ($colour[$w] === $colour[$v]) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Acyclic test. Undirected: no cycle (counting parallel edges and self-loops
     * as cycles). Directed: no directed cycle (DFS with recursion stack).
     *
     * @param  graph $g
     * @return bool
     */
    public static function is_acyclic(graph $g): bool {
        if ($g->is_directed()) {
            return self::directed_acyclic($g);
        }
        return self::undirected_acyclic($g);
    }

    /**
     * Undirected acyclicity via union-find; any edge joining two already-connected
     * vertices (or a self-loop / parallel edge) closes a cycle.
     *
     * @param  graph $g
     * @return bool
     */
    private static function undirected_acyclic(graph $g): bool {
        $parent = [];
        foreach ($g->node_ids() as $v) {
            $parent[$v] = $v;
        }
        $find = function (string $x) use (&$parent, &$find): string {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }
            return $x;
        };
        foreach ($g->edges() as $e) {
            if ($e['from'] === $e['to']) {
                return false; // Self-loop is a cycle.
            }
            $ra = $find($e['from']);
            $rb = $find($e['to']);
            if ($ra === $rb) {
                return false;
            }
            $parent[$ra] = $rb;
        }
        return true;
    }

    /**
     * Directed acyclicity via DFS colouring (white/grey/black).
     *
     * @param  graph $g
     * @return bool
     */
    private static function directed_acyclic(graph $g): bool {
        $state = []; // 0=unseen, 1=on-stack, 2=done.
        $hascycle = false;
        $visit = function (string $v) use (&$visit, &$state, $g, &$hascycle): void {
            $state[$v] = 1;
            foreach ($g->successors($v) as $w) {
                if (($state[$w] ?? 0) === 1) {
                    $hascycle = true;
                } else if (($state[$w] ?? 0) === 0) {
                    $visit($w);
                }
            }
            $state[$v] = 2;
        };
        foreach ($g->node_ids() as $v) {
            if (($state[$v] ?? 0) === 0) {
                $visit($v);
                if ($hascycle) {
                    return false;
                }
            }
        }
        return !$hascycle;
    }

    /**
     * Is the graph a tree: connected, acyclic, |E| = |V| - 1, |V| >= 1.
     *
     * @param  graph $g
     * @return bool
     */
    public static function is_tree(graph $g): bool {
        $n = $g->vertex_count();
        if ($n < 1) {
            return false;
        }
        return self::is_connected($g)
            && self::is_acyclic($g)
            && $g->edge_count() === $n - 1;
    }

    /**
     * Euler circuit existence.
     *   undirected: connected (ignoring isolated) ∧ every vertex even degree.
     *   directed:   in=out for every vertex ∧ strongly connected over active.
     *
     * @param  graph $g
     * @return bool
     */
    public static function has_euler_circuit(graph $g): bool {
        if ($g->edge_count() === 0) {
            return false;
        }
        if ($g->is_directed()) {
            foreach ($g->node_ids() as $v) {
                if ($g->in_degree($v) !== $g->out_degree($v)) {
                    return false;
                }
            }
            return self::is_strongly_connected_nonzero($g);
        }
        foreach ($g->node_ids() as $v) {
            if ($g->degree($v) % 2 !== 0) {
                return false;
            }
        }
        return self::is_connected_ignoring_isolated($g);
    }

    /**
     * Euler path (open trail) existence — includes the circuit case.
     *   undirected: connected (ignoring isolated) ∧ exactly 0 or 2 odd vertices.
     *   directed:   weakly connected over active ∧ ≤1 vertex out−in=+1,
     *               ≤1 vertex in−out=+1, all others in=out.
     *
     * @param  graph $g
     * @return bool
     */
    public static function has_euler_path(graph $g): bool {
        if ($g->edge_count() === 0) {
            return false;
        }
        if ($g->is_directed()) {
            $startexcess = 0; // out-in = +1.
            $endexcess   = 0; // in-out = +1.
            foreach ($g->node_ids() as $v) {
                $diff = $g->out_degree($v) - $g->in_degree($v);
                if ($diff === 1) {
                    $startexcess++;
                } else if ($diff === -1) {
                    $endexcess++;
                } else if ($diff !== 0) {
                    return false;
                }
            }
            if ($startexcess > 1 || $endexcess > 1) {
                return false;
            }
            return self::is_connected_ignoring_isolated($g);
        }
        $odd = 0;
        foreach ($g->node_ids() as $v) {
            if ($g->degree($v) % 2 !== 0) {
                $odd++;
            }
        }
        if ($odd !== 0 && $odd !== 2) {
            return false;
        }
        return self::is_connected_ignoring_isolated($g);
    }

    /**
     * Bounded Hamiltonian path search (undirected interpretation, backtracking).
     * Only call for graphs within MAX_VERTICES_HAMILTONIAN (exponential).
     *
     * @param  graph $g
     * @return bool
     */
    public static function has_hamiltonian_path(graph $g): bool {
        $ids = $g->node_ids();
        $n   = count($ids);
        if ($n === 0) {
            return false;
        }
        if ($n === 1) {
            return true;
        }
        $adj = self::adjacency_set($g);
        foreach ($ids as $start) {
            if (self::ham_dfs($start, [$start => true], 1, $n, $adj, null)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Bounded Hamiltonian circuit search (undirected). Visits every vertex once
     * and closes back to the start.
     *
     * @param  graph $g
     * @return bool
     */
    public static function has_hamiltonian_circuit(graph $g): bool {
        $ids = $g->node_ids();
        $n   = count($ids);
        if ($n < 3) {
            return false;
        }
        $adj = self::adjacency_set($g);
        $start = $ids[0];
        return self::ham_dfs($start, [$start => true], 1, $n, $adj, $start);
    }

    /**
     * Backtracking helper for Hamiltonian search.
     *
     * @param  string $v current vertex
     * @param  array<string,true> $visited
     * @param  int $count visited count
     * @param  int $n total vertices
     * @param  array<string,array<string,true>> $adj adjacency set
     * @param  string|null $closeto if set, a Hamiltonian circuit must close here
     * @return bool
     */
    private static function ham_dfs(string $v, array $visited, int $count, int $n, array $adj, ?string $closeto): bool {
        if ($count === $n) {
            if ($closeto === null) {
                return true;
            }
            return isset($adj[$v][$closeto]);
        }
        foreach (array_keys($adj[$v] ?? []) as $w) {
            if (!isset($visited[$w])) {
                $visited[$w] = true;
                if (self::ham_dfs($w, $visited, $count + 1, $n, $adj, $closeto)) {
                    return true;
                }
                unset($visited[$w]);
            }
        }
        return false;
    }

    /**
     * Undirected adjacency set (dedup neighbours; ignores self-loops for
     * Hamiltonian reachability).
     *
     * @param  graph $g
     * @return array<string,array<string,true>>
     */
    private static function adjacency_set(graph $g): array {
        $adj = [];
        foreach ($g->node_ids() as $v) {
            $adj[$v] = [];
        }
        foreach ($g->edges() as $e) {
            if ($e['from'] === $e['to']) {
                continue;
            }
            $adj[$e['from']][$e['to']] = true;
            $adj[$e['to']][$e['from']] = true;
        }
        return $adj;
    }

    /**
     * Compare two degree sequences (both sorted ascending) for exact match.
     *
     * @param  int[] $a
     * @param  int[] $b
     * @return bool
     */
    public static function degree_sequences_match(array $a, array $b): bool {
        sort($a);
        sort($b);
        return $a === $b;
    }

    /**
     * Validate a walk supplied as an ordered edge-id list against a walk kind.
     *
     * Rules (PRD Core Concepts / D21):
     *   euler_circuit      — uses every edge id exactly once; returns to start.
     *   euler_path         — uses every edge id exactly once; endpoints may differ.
     *   hamiltonian_path   — visits every vertex exactly once (edge sequence is
     *                        a valid path); endpoints may differ.
     *   hamiltonian_circuit— visits every vertex exactly once and closes to start.
     *
     * For each consecutive edge the endpoints must chain (undirected: either
     * orientation; directed: from→to only). Returns true iff the walk is valid.
     *
     * @param  graph    $g
     * @param  string[] $edgeids Ordered edge-id list forming the walk.
     * @param  string   $walkkind
     * @param  string|null $startvertex Required first vertex, or null.
     * @return bool
     */
    public static function validate_walk(graph $g, array $edgeids, string $walkkind, ?string $startvertex): bool {
        // Resolve edge ids to edges, preserving order; reject unknown/duplicate ids.
        $seen  = [];
        $chain = [];
        foreach ($edgeids as $id) {
            $id = (string) $id;
            if (isset($seen[$id])) {
                // A repeated edge id can only be legal for non-Euler walks, but an
                // edge is a physical thing — reusing the same edge is never allowed.
                return false;
            }
            $seen[$id] = true;
            $e = $g->edge_by_id($id);
            if ($e === null) {
                return false;
            }
            $chain[] = $e;
        }

        $iseuler = ($walkkind === 'euler_circuit' || $walkkind === 'euler_path');
        if ($iseuler) {
            // Must use every edge exactly once.
            if (count($chain) !== $g->edge_count()) {
                return false;
            }
        }
        if (empty($chain)) {
            return false;
        }

        // Walk the chain, tracking the current vertex. Determine the first vertex:
        // the endpoint of edge0 not shared as the entry to edge1 (or honour
        // startvertex / directed orientation).
        $directed = $g->is_directed();
        $first = self::resolve_first_vertex($chain, $directed, $startvertex);
        if ($first === null) {
            return false;
        }
        if ($startvertex !== null && $first !== $startvertex) {
            return false;
        }

        $current  = $first;
        $vertexseq = [$first];
        foreach ($chain as $e) {
            if ($directed) {
                if ($e['from'] !== $current) {
                    return false;
                }
                $current = $e['to'];
            } else {
                if ($e['from'] === $current) {
                    $current = $e['to'];
                } else if ($e['to'] === $current) {
                    $current = $e['from'];
                } else {
                    return false;
                }
            }
            $vertexseq[] = $current;
        }
        $last = $current;

        switch ($walkkind) {
            case 'euler_circuit':
                return $first === $last;
            case 'euler_path':
                return true; // Already checked all edges used once.
            case 'hamiltonian_path':
                return self::visits_all_vertices_once($g, $vertexseq, false);
            case 'hamiltonian_circuit':
                if ($first !== $last) {
                    return false;
                }
                return self::visits_all_vertices_once($g, $vertexseq, true);
            default:
                return false;
        }
    }

    /**
     * Determine the starting vertex of an edge chain.
     *
     * @param  array<int,array{from:string,to:string}> $chain
     * @param  bool $directed
     * @param  string|null $startvertex preferred start, if given
     * @return string|null
     */
    private static function resolve_first_vertex(array $chain, bool $directed, ?string $startvertex): ?string {
        $e0 = $chain[0];
        if ($directed) {
            return $e0['from'];
        }
        if (count($chain) === 1) {
            if ($startvertex !== null && ($e0['from'] === $startvertex || $e0['to'] === $startvertex)) {
                return $startvertex;
            }
            return $e0['from'];
        }
        // For undirected, the shared vertex with edge1 is the SECOND vertex, so the
        // first is the other endpoint of edge0.
        $e1 = $chain[1];
        $shared = null;
        foreach ([$e0['from'], $e0['to']] as $v) {
            if ($v === $e1['from'] || $v === $e1['to']) {
                $shared = $v;
                break;
            }
        }
        if ($shared === null) {
            return null; // edge0 and edge1 do not connect.
        }
        $first = ($e0['from'] === $shared) ? $e0['to'] : $e0['from'];
        // If a start vertex is requested and it is the other endpoint, honour it.
        if ($startvertex !== null && $first !== $startvertex
                && ($e0['from'] === $startvertex || $e0['to'] === $startvertex)) {
            // Only valid if edge0 is a self-relation to shared; otherwise keep first.
            return $first;
        }
        return $first;
    }

    /**
     * Check a vertex sequence visits every vertex of the graph exactly once.
     * For a circuit the final vertex equals the first and is not double-counted.
     *
     * @param  graph    $g
     * @param  string[] $vertexseq
     * @param  bool     $circuit
     * @return bool
     */
    private static function visits_all_vertices_once(graph $g, array $vertexseq, bool $circuit): bool {
        $seq = $vertexseq;
        if ($circuit && count($seq) > 1) {
            array_pop($seq); // Drop the closing repeat.
        }
        $counts = array_count_values($seq);
        foreach ($counts as $c) {
            if ($c !== 1) {
                return false;
            }
        }
        return count($seq) === $g->vertex_count();
    }
}
