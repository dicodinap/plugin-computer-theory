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
 * karnaugh tool — implements tool_interface for boolean-simplification (K-map).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\karnaugh;

use local_graphitoubb\tool_descriptor;
use local_graphitoubb\tool_interface;
use local_graphitoubb\validation_result;

/**
 * Entry point for the karnaugh tool; registered with tool_registry via bootstrap.
 */
final class karnaugh_tool implements tool_interface {
    /** Maximum number of variables (RF_04: up to 4). */
    public const MAX_VARS = 4;
    /** Minimum number of variables. */
    public const MIN_VARS = 2;

    /**
     * Return the descriptor for this tool.
     *
     * @return tool_descriptor
     */
    public static function descriptor(): tool_descriptor {
        return new tool_descriptor(
            'karnaugh',
            'Karnaugh',
            '1.0.0',
            ['edit', 'snapshot']
        );
    }

    /**
     * Validate a canonical karnaugh problem config ({n_vars, minterms, ...}).
     *
     * @param  array $payload
     * @return validation_result
     */
    public function validate(array $payload): validation_result {
        $errors = [];
        $config = $payload['config'] ?? $payload;
        $nvars  = (int) ($config['n_vars'] ?? 0);
        if ($nvars < self::MIN_VARS || $nvars > self::MAX_VARS) {
            $errors[] = 'n_vars: must be between ' . self::MIN_VARS . ' and ' . self::MAX_VARS;
        }
        $minterms = $config['minterms'] ?? [];
        if (!is_array($minterms)) {
            $errors[] = 'minterms: must be an array';
        } else if (empty($minterms)) {
            // Contradiction (all zeros) is degenerate — nothing to simplify.
            $errors[] = 'minterms: a contradiction (no 1-cells) has nothing to simplify';
        }
        $scoring = $config['scoring'] ?? ['fill_weight' => 40, 'grouping_weight' => 60];
        $sum = (int) ($scoring['fill_weight'] ?? 0) + (int) ($scoring['grouping_weight'] ?? 0);
        if ($sum !== 100) {
            $errors[] = 'scoring: fill_weight + grouping_weight must equal 100';
        }

        return $errors ? validation_result::fail($errors) : validation_result::pass();
    }

    /**
     * Normalise a canonical karnaugh config into the persistence-ready shape.
     *
     * @param  array $config
     * @return array
     */
    public function serialize(array $config): array {
        return [
            'schema_version' => 1,
            'n_vars'         => (int) ($config['n_vars'] ?? 2),
            'var_names'      => array_values(array_map('strval', $config['var_names'] ?? [])),
            'minterms'       => array_values(array_map('intval', $config['minterms'] ?? [])),
            'require_minimal' => !array_key_exists('require_minimal', $config) || (bool) $config['require_minimal'],
            'scoring'        => $config['scoring'] ?? ['fill_weight' => 40, 'grouping_weight' => 60],
        ];
    }

    /**
     * The karnaugh editor is rendered by mod_graphitoubb\output\renderer directly.
     *
     * @return array{template: string, context: array}
     */
    public function render_editor(): array {
        return [
            'template' => 'mod_graphitoubb/karnaugh_editor',
            'context'  => [],
        ];
    }
}
