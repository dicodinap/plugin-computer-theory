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
 * arbol tool — implements tool_interface for tree/BST exercises.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\arbol;

use local_graphitoubb\tool_descriptor;
use local_graphitoubb\tool_interface;
use local_graphitoubb\validation_result;

/**
 * Entry point for the arbol tool; registered with tool_registry via bootstrap.
 */
final class arbol_tool implements tool_interface {
    /** Phase-1 bounds (D9). */
    public const MAX_NODES = 31;
    /** Maximum node label length. */
    public const MAX_LABEL = 6;

    /**
     * Return the descriptor for this tool.
     *
     * @return tool_descriptor
     */
    public static function descriptor(): tool_descriptor {
        return new tool_descriptor(
            'arbol',
            'Tree / BST',
            '1.0.0',
            ['edit', 'snapshot']
        );
    }

    /**
     * Validate a canonical arbol payload ({nodes,edges,root}) against bounds.
     *
     * @param  array $payload
     * @return validation_result
     */
    public function validate(array $payload): validation_result {
        $errors = [];
        $nodes  = $payload['nodes'] ?? [];
        if (!is_array($nodes)) {
            return validation_result::fail(['malformed: nodes must be an array']);
        }
        if (count($nodes) > self::MAX_NODES) {
            $errors[] = 'max_nodes: ' . count($nodes) . ' > ' . self::MAX_NODES;
        }
        foreach ($nodes as $n) {
            $label = is_array($n) ? (string) ($n['label'] ?? ($n['id'] ?? '')) : '';
            if (\core_text::strlen($label) > self::MAX_LABEL) {
                $errors[] = 'max_label: node label too long';
                break;
            }
        }
        return $errors ? validation_result::fail($errors) : validation_result::pass();
    }

    /**
     * Normalise a canonical arbol array into the persistence-ready shape.
     *
     * @param  array $tree
     * @return array
     */
    public function serialize(array $tree): array {
        return [
            'schema_version' => 1,
            'nodes'          => $tree['nodes'] ?? [],
            'edges'          => $tree['edges'] ?? [],
            'root'           => $tree['root'] ?? null,
        ];
    }

    /**
     * Provide the Mustache template name and render context for the tree editor.
     *
     * @return array{template: string, context: array}
     */
    public function render_editor(): array {
        return [
            'template' => 'mod_graphitoubb/graph_editor',
            'context'  => [],
        ];
    }
}
