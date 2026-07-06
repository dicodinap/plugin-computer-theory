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
 * Immutable graph value object for the grafo tool.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\grafo\domain;

/**
 * A parsed, in-memory graph — nodes, edges (each with a stable id) and a
 * directed flag. Parallel edges (same from/to) are allowed and distinguished
 * only by edge id (Königsberg multigraph). Pure/DB-free.
 */
final class graph {
    /** @var array<string,string> node id => label. */
    private array $nodes;

    /** @var array<int,array{id:string,from:string,to:string,weight:?float}> */
    private array $edges;

    /** @var bool */
    private bool $directed;

    /**
     * @param array<string,string> $nodes node id => label
     * @param array<int,array{id:string,from:string,to:string,weight:?float}> $edges
     * @param bool $directed
     */
    public function __construct(array $nodes, array $edges, bool $directed) {
        $this->nodes    = $nodes;
        $this->edges    = $edges;
        $this->directed = $directed;
    }

    /**
     * Build a graph from a decoded {nodes,edges,directed} array.
     *
     * Skips malformed nodes/edges rather than throwing; endpoints that reference
     * an unknown node are dropped so downstream algorithms never fault. Returns
     * null when the input is not an array.
     *
     * @param  mixed $raw Decoded graph array.
     * @param  bool|null $directedoverride If set, forces the directed flag
     *                                     (grafo pins directed per problem).
     * @return self|null
     */
    public static function from_array($raw, ?bool $directedoverride = null): ?self {
        if (!is_array($raw)) {
            return null;
        }
        $nodes = [];
        foreach ($raw['nodes'] ?? [] as $n) {
            if (!is_array($n) || !isset($n['id'])) {
                continue;
            }
            $id = (string) $n['id'];
            $nodes[$id] = (string) ($n['label'] ?? $id);
        }
        $edges = [];
        $auto  = 0;
        foreach ($raw['edges'] ?? [] as $e) {
            if (!is_array($e) || !isset($e['from'], $e['to'])) {
                continue;
            }
            $from = (string) $e['from'];
            $to   = (string) $e['to'];
            if (!isset($nodes[$from]) || !isset($nodes[$to])) {
                continue;
            }
            $id = isset($e['id']) ? (string) $e['id'] : ('e' . $auto);
            $auto++;
            $edges[] = [
                'id'     => $id,
                'from'   => $from,
                'to'     => $to,
                'weight' => isset($e['weight']) && is_numeric($e['weight']) ? (float) $e['weight'] : null,
            ];
        }
        $directed = $directedoverride ?? (bool) ($raw['directed'] ?? false);
        return new self($nodes, $edges, $directed);
    }

    /**
     * @return string[] Node ids.
     */
    public function node_ids(): array {
        return array_keys($this->nodes);
    }

    /**
     * @return int Number of vertices.
     */
    public function vertex_count(): int {
        return count($this->nodes);
    }

    /**
     * @return int Number of edges (parallel edges counted).
     */
    public function edge_count(): int {
        return count($this->edges);
    }

    /**
     * @return bool
     */
    public function is_directed(): bool {
        return $this->directed;
    }

    /**
     * @return array<int,array{id:string,from:string,to:string,weight:?float}>
     */
    public function edges(): array {
        return $this->edges;
    }

    /**
     * Look up an edge by its stable id.
     *
     * @param  string $id
     * @return array{id:string,from:string,to:string,weight:?float}|null
     */
    public function edge_by_id(string $id): ?array {
        foreach ($this->edges as $e) {
            if ($e['id'] === $id) {
                return $e;
            }
        }
        return null;
    }

    /**
     * Undirected degree of a vertex: incident edge-ends. A self-loop contributes 2.
     *
     * @param  string $v
     * @return int
     */
    public function degree(string $v): int {
        $d = 0;
        foreach ($this->edges as $e) {
            if ($e['from'] === $v) {
                $d++;
            }
            if ($e['to'] === $v) {
                $d++;
            }
        }
        return $d;
    }

    /**
     * Out-degree (directed). A self-loop contributes 1 to both in and out.
     *
     * @param  string $v
     * @return int
     */
    public function out_degree(string $v): int {
        $d = 0;
        foreach ($this->edges as $e) {
            if ($e['from'] === $v) {
                $d++;
            }
        }
        return $d;
    }

    /**
     * In-degree (directed).
     *
     * @param  string $v
     * @return int
     */
    public function in_degree(string $v): int {
        $d = 0;
        foreach ($this->edges as $e) {
            if ($e['to'] === $v) {
                $d++;
            }
        }
        return $d;
    }

    /**
     * Undirected adjacency: neighbours of $v (with multiplicity), treating every
     * edge as bidirectional regardless of the directed flag.
     *
     * @param  string $v
     * @return string[]
     */
    public function undirected_neighbours(string $v): array {
        $out = [];
        foreach ($this->edges as $e) {
            if ($e['from'] === $v) {
                $out[] = $e['to'];
            }
            if ($e['to'] === $v) {
                $out[] = $e['from'];
            }
        }
        return $out;
    }

    /**
     * Directed successors of $v (edge from $v to w).
     *
     * @param  string $v
     * @return string[]
     */
    public function successors(string $v): array {
        $out = [];
        foreach ($this->edges as $e) {
            if ($e['from'] === $v) {
                $out[] = $e['to'];
            }
        }
        return $out;
    }

    /**
     * Sorted undirected degree sequence (multiset), ascending.
     *
     * @return int[]
     */
    public function degree_sequence(): array {
        $seq = [];
        foreach (array_keys($this->nodes) as $v) {
            $seq[] = $this->degree($v);
        }
        sort($seq);
        return $seq;
    }
}
