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
 * Validator — enforces spec bounds on formulae and problem payloads.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

use local_graphitoubb\validation_result;

/**
 * Validates formula strings and problem payload arrays against spec §3 bounds.
 *
 * All error messages are in Spanish.
 */
final class validator {
    /** Maximum number of distinct variables allowed in a formula. */
    public const MAX_VARIABLES = 5;

    /** Maximum number of characters in the normalised formula string. */
    public const MAX_FORMULA_LENGTH = 128;

    /** Maximum AST depth allowed. */
    public const MAX_DEPTH = 12;

    /** Maximum number of intermediate subformulas shown in the truth table. */
    public const MAX_SUBFORMULAS = 8;

    /** Maximum size in bytes of the serialised problem JSON. */
    public const MAX_PROBLEM_JSON_BYTES = 8192;

    /** Maximum number of truth-table rows (2^MAX_VARIABLES). */
    public const MAX_ROWS = 32;

    /** Maximum number of cells in the truth table (32 rows × 9 columns). */
    public const MAX_CELLS = 288;

    /** @var lexer */
    private lexer $lexer;

    /** @var parser */
    private parser $parser;

    /**
     * Construct the validator with its collaborators.
     */
    public function __construct() {
        $this->lexer  = new lexer();
        $this->parser = new parser();
    }

    /**
     * Validate a raw formula string against all spec bounds.
     *
     * Steps:
     *  1. Normalise (apply synonyms, strip whitespace).
     *  2. Check length ≤ MAX_FORMULA_LENGTH.
     *  3. Try to parse (catches lex and parse errors).
     *  4. Check variable count ≤ MAX_VARIABLES.
     *  5. Check AST depth ≤ MAX_DEPTH.
     *
     * @param  string $raw Raw formula input from the user.
     * @return validation_result
     */
    public function validate_formula(string $raw): validation_result {
        $normalized = $this->lexer->normalize($raw);

        // Length check on the normalised (whitespace-stripped) form.
        $length = mb_strlen($normalized, 'UTF-8');
        if ($length > self::MAX_FORMULA_LENGTH) {
            return validation_result::fail([
                'La fórmula excede el máximo de ' . self::MAX_FORMULA_LENGTH
                . ' caracteres (tiene ' . $length . ').',
            ]);
        }

        // Parse.
        try {
            $ast = $this->parser->parse($raw);
        } catch (lex_exception $e) {
            return validation_result::fail(['Error léxico: ' . $e->getMessage()]);
        } catch (parse_exception $e) {
            return validation_result::fail(['Error sintáctico: ' . $e->getMessage()]);
        }

        // Variable count.
        $vars = array_unique($ast->variables());
        if (count($vars) > self::MAX_VARIABLES) {
            return validation_result::fail([
                'La fórmula tiene ' . count($vars) . ' variables distintas; '
                . 'el máximo permitido es ' . self::MAX_VARIABLES . '.',
            ]);
        }

        // AST depth.
        $depth = $ast->depth();
        if ($depth > self::MAX_DEPTH) {
            return validation_result::fail([
                'La fórmula tiene profundidad ' . $depth . '; '
                . 'el máximo permitido es ' . self::MAX_DEPTH . '.',
            ]);
        }

        return validation_result::pass();
    }

    /**
     * Validate a problem payload array against structural requirements.
     *
     * Checks:
     *  - JSON serialisation ≤ MAX_PROBLEM_JSON_BYTES.
     *  - Required top-level fields present (tool, type, config).
     *  - Type-specific config fields present.
     *  - Scoring weights sum sanity check for equivalence and classify types.
     *
     * @param  array $problem Decoded problem array (from JSON).
     * @return validation_result
     */
    public function validate_problem(array $problem): validation_result {
        $errors = [];

        // JSON size check.
        $json = json_encode($problem);
        if ($json !== false && strlen($json) > self::MAX_PROBLEM_JSON_BYTES) {
            $errors[] = 'El JSON del problema excede el máximo de ' . self::MAX_PROBLEM_JSON_BYTES . ' bytes.';
        }

        // Required top-level fields.
        foreach (['tool', 'type', 'config'] as $field) {
            if (!array_key_exists($field, $problem)) {
                $errors[] = 'Campo requerido ausente: "' . $field . '".';
            }
        }

        if ($errors) {
            return validation_result::fail($errors);
        }

        $type   = $problem['type'] ?? '';
        $config = $problem['config'] ?? [];

        // Type-specific required config fields.
        $required_config = $this->required_config_fields($type);
        foreach ($required_config as $field) {
            if (!array_key_exists($field, $config)) {
                $errors[] = 'Campo de configuración requerido ausente: "' . $field . '" para tipo "' . $type . '".';
            }
        }

        // Scoring weights sanity check for types that have a scoring section.
        if (in_array($type, ['equivalence', 'classify'], true)) {
            $scoring = $problem['scoring'] ?? null;
            if ($scoring !== null) {
                $radio_weight = $scoring['radio_weight'] ?? null;
                $table_weight = $scoring['table_weight'] ?? null;
                if ($radio_weight !== null && $table_weight !== null) {
                    $sum = (int)$radio_weight + (int)$table_weight;
                    if ($sum !== 100) {
                        $errors[] = 'La suma de radio_weight (' . $radio_weight . ') + table_weight ('
                            . $table_weight . ') debe ser 100; encontrado: ' . $sum . '.';
                    }
                }
            }
        }

        return $errors ? validation_result::fail($errors) : validation_result::pass();
    }

    /**
     * Return the list of required config fields for the given problem type.
     *
     * @param  string $type Problem type: 'complete', 'equivalence', 'classify'.
     * @return string[]
     */
    private function required_config_fields(string $type): array {
        switch ($type) {
            case 'complete':
                return ['formula'];
            case 'equivalence':
                return ['formula_1', 'formula_2', 'expected_equivalent', 'require_table_justification'];
            case 'classify':
                return ['formula', 'expected_class', 'require_table_justification'];
            default:
                return [];
        }
    }
}
