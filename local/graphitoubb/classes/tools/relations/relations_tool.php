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
 * relations tool — implements tool_interface for binary-relations exercises.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\relations;

use local_graphitoubb\tool_descriptor;
use local_graphitoubb\tool_interface;
use local_graphitoubb\tools\relations\domain\relation;
use local_graphitoubb\validation_result;

/**
 * Entry point for the relations tool; registered with tool_registry via bootstrap.
 */
final class relations_tool implements tool_interface {
    /** Maximum base-set cardinality (D7). */
    public const MAX_SET = 6;

    /**
     * Return the descriptor for this tool.
     *
     * @return tool_descriptor
     */
    public static function descriptor(): tool_descriptor {
        return new tool_descriptor(
            'relations',
            'Relations',
            '1.0.0',
            ['edit', 'snapshot']
        );
    }

    /**
     * Validate a canonical relations problem config.
     *
     * @param  array $payload
     * @return validation_result
     */
    public function validate(array $payload): validation_result {
        $errors  = [];
        $config  = $payload['config'] ?? $payload;
        $baseset = $config['base_set'] ?? [];
        if (!is_array($baseset) || count($baseset) === 0) {
            $errors[] = 'base_set: must be a non-empty set';
        } else if (count($baseset) > self::MAX_SET) {
            $errors[] = 'base_set: at most ' . self::MAX_SET . ' elements';
        } else if (count(array_unique(array_map('strval', $baseset))) !== count($baseset)) {
            $errors[] = 'base_set: elements must be distinct';
        }
        $rep = $config['required_representation'] ?? 'any';
        if (!in_array($rep, ['matrix', 'pairs', 'digraph', 'any'], true)) {
            $errors[] = 'required_representation: invalid';
        }
        $scoring = $config['scoring'] ?? ['representation_weight' => 40, 'properties_weight' => 60];
        $sum = (int) ($scoring['representation_weight'] ?? 0) + (int) ($scoring['properties_weight'] ?? 0);
        if ($sum !== 100) {
            $errors[] = 'scoring: representation_weight + properties_weight must equal 100';
        }

        return $errors ? validation_result::fail($errors) : validation_result::pass();
    }

    /**
     * Normalise a canonical relations config into the persistence-ready shape.
     *
     * @param  array $config
     * @return array
     */
    public function serialize(array $config): array {
        $baseset = array_values(array_map('strval', $config['base_set'] ?? []));
        return [
            'schema_version'          => 1,
            'base_set'                => $baseset,
            'relation'                => relation::normalize_pairs($config['relation'] ?? [], $baseset),
            'required_representation' => $config['required_representation'] ?? 'any',
            'ask_properties'          => $config['ask_properties'] ?? relation::PROPERTIES,
            'scoring'                 => $config['scoring'] ?? ['representation_weight' => 40, 'properties_weight' => 60],
        ];
    }

    /**
     * The relations editor is rendered by mod_graphitoubb\output\renderer directly.
     *
     * @return array{template: string, context: array}
     */
    public function render_editor(): array {
        return [
            'template' => 'mod_graphitoubb/relations_editor',
            'context'  => [],
        ];
    }
}
