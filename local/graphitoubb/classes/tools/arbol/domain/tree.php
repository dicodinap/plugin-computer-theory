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
 * Immutable binary-tree value object for the arbol tool.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\arbol\domain;

/**
 * A parsed binary tree — nodes with numeric values, parent→child edges carrying
 * an explicit L|R side, and a designated root. Pure/DB-free.
 */
final class tree {
    /** @var array<string,int|null> node id => numeric value */
    private array $values;

    /** @var array<string,array{L:?string,R:?string}> node id => children by side */
    private array $children;

    /** @var string|null */
    private ?string $root;

    /**
     * @param array<string,int|null> $values
     * @param array<string,array{L:?string,R:?string}> $children
     * @param string|null $root
     */
    private function __construct(array $values, array $children, ?string $root) {
        $this->values   = $values;
        $this->children = $children;
        $this->root     = $root;
    }

    /**
     * Build a tree from a decoded {nodes,edges,root} array.
     *
     * @param  mixed $raw
     * @return self|null null when the input is not an array.
     */
    public static function from_array($raw): ?self {
        if (!is_array($raw)) {
            return null;
        }
        $values   = [];
        $children = [];
        foreach ($raw['nodes'] ?? [] as $n) {
            if (!is_array($n) || !isset($n['id'])) {
                continue;
            }
            $id = (string) $n['id'];
            $val = null;
            if (isset($n['value']) && is_numeric($n['value'])) {
                $val = (int) $n['value'];
            } else if (isset($n['label']) && is_numeric($n['label'])) {
                $val = (int) $n['label'];
            }
            $values[$id]   = $val;
            $children[$id] = ['L' => null, 'R' => null];
        }
        foreach ($raw['edges'] ?? [] as $e) {
            if (!is_array($e) || !isset($e['parent'], $e['child'])) {
                continue;
            }
            $p = (string) $e['parent'];
            $c = (string) $e['child'];
            $side = ($e['side'] ?? '') === 'R' ? 'R' : 'L';
            if (!isset($children[$p]) || !isset($values[$c])) {
                continue;
            }
            // First edge for a (parent, side) wins; extras are ignored (invalidity
            // is caught by is_valid()'s edge-count check).
            if ($children[$p][$side] === null) {
                $children[$p][$side] = $c;
            }
        }
        $root = isset($raw['root']) && $raw['root'] !== '' ? (string) $raw['root'] : null;
        return new self($values, $children, $root);
    }

    /**
     * Structural validity: a root exists, ≤2 children per node (one per side),
     * every node reachable exactly once from the root (no cycle, no forest).
     *
     * @return bool
     */
    public function is_valid(): bool {
        if ($this->root === null || !isset($this->values[$this->root])) {
            return empty($this->values); // Empty tree is vacuously valid-but-empty.
        }
        $seen  = [];
        $queue = [$this->root];
        while ($queue) {
            $v = array_shift($queue);
            if (isset($seen[$v])) {
                return false; // Cycle / shared child.
            }
            $seen[$v] = true;
            foreach (['L', 'R'] as $side) {
                $c = $this->children[$v][$side];
                if ($c !== null) {
                    $queue[] = $c;
                }
            }
        }
        // Every node must be reachable from the root (no detached components).
        return count($seen) === count($this->values);
    }

    /**
     * @return string|null
     */
    public function root(): ?string {
        return $this->root;
    }

    /**
     * @return bool
     */
    public function is_empty(): bool {
        return empty($this->values);
    }

    /**
     * @param  string $id
     * @return string|null Left child id.
     */
    public function left(string $id): ?string {
        return $this->children[$id]['L'] ?? null;
    }

    /**
     * @param  string $id
     * @return string|null Right child id.
     */
    public function right(string $id): ?string {
        return $this->children[$id]['R'] ?? null;
    }

    /**
     * @param  string $id
     * @return int|null Numeric value of a node.
     */
    public function value(string $id): ?int {
        return $this->values[$id] ?? null;
    }

    /**
     * Map every node to its structural path from the root ("" = root, then a
     * string of 'L'/'R' steps) → node value. Only nodes reachable from the root
     * are included. Deterministic; used for per-position grading (D12).
     *
     * @return array<string,int|null> path => value
     */
    public function position_value_map(): array {
        $map = [];
        if ($this->root === null || !isset($this->values[$this->root])) {
            return $map;
        }
        $walk = function (string $id, string $path) use (&$walk, &$map): void {
            if (isset($map[$path])) {
                return; // Guard against malformed cyclic input.
            }
            $map[$path] = $this->values[$id];
            $l = $this->children[$id]['L'];
            $r = $this->children[$id]['R'];
            if ($l !== null) {
                $walk($l, $path . 'L');
            }
            if ($r !== null) {
                $walk($r, $path . 'R');
            }
        };
        $walk($this->root, '');
        return $map;
    }
}
