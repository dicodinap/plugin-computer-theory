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
 * Truth table tool — implements tool_interface for propositional logic truth tables.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table;

use local_graphitoubb\tool_descriptor;
use local_graphitoubb\tool_interface;
use local_graphitoubb\tools\truth_table\domain\validator;
use local_graphitoubb\validation_result;

/**
 * Entry point for the truth_table tool; registered with tool_registry via bootstrap.
 *
 * Implements the three activity modes defined in spec §1:
 *  - complete     (student fills table cells)
 *  - equivalence  (radio answer with optional table justification)
 *  - classify     (radio answer with optional table justification)
 */
final class truth_table_tool implements tool_interface {
    /**
     * Return the descriptor for this tool.
     *
     * @return tool_descriptor
     */
    public static function descriptor(): tool_descriptor {
        return new tool_descriptor(
            'truth_table',
            'Truth Table',
            '1.0.0',
            ['edit', 'evaluate', 'snapshot']
        );
    }

    /**
     * Validate a raw problem payload against domain bounds.
     *
     * Delegates structural and formula checks to domain\validator.
     *
     * @param  array $payload Decoded problem array from the client.
     * @return validation_result
     */
    public function validate(array $payload): validation_result {
        $v = new validator();
        return $v->validate_problem($payload);
    }

    /**
     * Normalise a problem array into the persistence-ready shape.
     *
     * Adds schema_version and tool slug. Passes through config, ui, and scoring
     * unchanged so the caller can supply only the keys they know about.
     *
     * @param  array $payload Raw problem array from the client.
     * @return array Persistence-ready problem array.
     */
    public function serialize(array $payload): array {
        return [
            'schema_version' => 1,
            'tool'           => 'truth_table',
            'type'           => $payload['type'] ?? null,
            'config'         => $payload['config'] ?? [],
            'ui'             => $payload['ui'] ?? [],
            'scoring'        => $payload['scoring'] ?? null,
        ];
    }

    /**
     * Return the Mustache template name and render context for the truth table editor.
     *
     * @return array{template: string, context: array}
     */
    public function render_editor(): array {
        return [
            'template' => 'mod_graphitoubb/truth_table_editor',
            'context'  => [],
        ];
    }
}
