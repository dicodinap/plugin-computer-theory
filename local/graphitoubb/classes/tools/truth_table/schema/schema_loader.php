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
 * Schema loader — loads and validates truth_table JSON Schemas without Composer deps.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\schema;

use local_graphitoubb\validation_result;
use RuntimeException;

/**
 * Loads JSON Schema files from this directory and performs handwritten
 * structural validation against them.
 *
 * Design decision: we implement a light handwritten validator rather than
 * pulling in justinrainbow/json-schema or another Composer package, because
 * Moodle plugins should avoid Composer deps not already bundled in Moodle core.
 *
 * Validation scope:
 *   - Required field presence (top level and known nested objects).
 *   - Type checks for known fields (string, integer, boolean, array, object, null).
 *   - Enum checks where the schema defines them.
 *   - additionalProperties: false enforcement at the top level and at known
 *     nested objects (ui, config, scoring, table, table.rows[]).
 *     The vars map inside each row is intentionally NOT enforced here because
 *     its keys are variable letters (A–Z) whose set is dynamic.
 *
 * Error messages are in Spanish.
 */
final class schema_loader {
    /**
     * Load a JSON Schema file as a decoded PHP array.
     *
     * @param  string $type The problem/submission type: 'complete', 'equivalence', or 'classify'.
     * @param  string $kind 'problem' or 'submission'.
     * @return array The decoded JSON Schema as an associative array.
     * @throws RuntimeException If the file is missing or cannot be decoded.
     */
    public function load(string $type, string $kind): array {
        $path = __DIR__ . '/' . $kind . '-' . $type . '.v1.json';
        if (!file_exists($path)) {
            throw new RuntimeException('Archivo de schema no encontrado: ' . $path);
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('No se pudo leer el archivo de schema: ' . $path);
        }
        $schema = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON inválido en schema: ' . json_last_error_msg());
        }
        return $schema;
    }

    /**
     * Validate a decoded data array against the schema for the given type and kind.
     *
     * Returns a validation_result with all errors found (not just the first).
     *
     * @param  array  $data The data array to validate (already JSON-decoded).
     * @param  string $type 'complete', 'equivalence', or 'classify'.
     * @param  string $kind 'problem' or 'submission'.
     * @return validation_result
     */
    public function validate(array $data, string $type, string $kind): validation_result {
        $errors = [];

        // Validate common fields present in all schemas.
        $errors = array_merge($errors, $this->validate_common_fields($data, $type));

        // Type-specific validation.
        if ($kind === 'problem') {
            $errors = array_merge($errors, $this->validate_problem($data, $type));
        } else {
            $errors = array_merge($errors, $this->validate_submission($data, $type));
        }

        return $errors ? validation_result::fail($errors) : validation_result::pass();
    }

    // =========================================================================
    // Private: common field validation
    // =========================================================================

    /**
     * Validate fields common to all problem and submission schemas.
     *
     * @param  array  $data
     * @param  string $type Expected type value.
     * @return string[]
     */
    private function validate_common_fields(array $data, string $type): array {
        $errors = [];

        // Required fields at top level.
        foreach (['tool', 'schema_version', 'type'] as $field) {
            if (!array_key_exists($field, $data)) {
                $errors[] = 'Campo requerido: ' . $field;
            }
        }

        // Type checks and const checks.
        if (array_key_exists('tool', $data)) {
            if (!is_string($data['tool'])) {
                $errors[] = 'Tipo inválido en tool: esperado string';
            } else if ($data['tool'] !== 'truth_table') {
                $errors[] = 'Valor inválido en tool: ' . $data['tool'];
            }
        }

        if (array_key_exists('schema_version', $data)) {
            if (!is_int($data['schema_version'])) {
                $errors[] = 'Tipo inválido en schema_version: esperado integer';
            } else if ($data['schema_version'] !== 1) {
                $errors[] = 'Valor inválido en schema_version: ' . $data['schema_version'];
            }
        }

        if (array_key_exists('type', $data)) {
            if (!is_string($data['type'])) {
                $errors[] = 'Tipo inválido en type: esperado string';
            } else if ($data['type'] !== $type) {
                $errors[] = 'Valor inválido en type: ' . $data['type'];
            }
        }

        return $errors;
    }

    // =========================================================================
    // Private: problem validation per type
    // =========================================================================

    /**
     * Validate a problem array for a specific type.
     *
     * @param  array  $data
     * @param  string $type
     * @return string[]
     */
    private function validate_problem(array $data, string $type): array {
        $errors = [];

        // Top-level required fields.
        $required = ['tool', 'schema_version', 'type', 'ui', 'config'];
        if (in_array($type, ['equivalence', 'classify'], true)) {
            $required[] = 'scoring';
        }
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                $errors[] = 'Campo requerido: ' . $field;
            }
        }

        // additionalProperties: false at top level.
        $allowed = ['tool', 'schema_version', 'type', 'ui', 'config'];
        if (in_array($type, ['equivalence', 'classify'], true)) {
            $allowed[] = 'scoring';
        }
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = 'Campo no permitido: ' . $key;
            }
        }

        // Validate ui object.
        if (array_key_exists('ui', $data)) {
            $errors = array_merge($errors, $this->validate_ui($data['ui']));
        }

        // Validate scoring object (equivalence and classify only).
        if (in_array($type, ['equivalence', 'classify'], true) && array_key_exists('scoring', $data)) {
            $errors = array_merge($errors, $this->validate_scoring($data['scoring']));
        }

        // Validate config object per type.
        if (array_key_exists('config', $data)) {
            $errors = array_merge($errors, $this->validate_problem_config($data['config'], $type));
        }

        return $errors;
    }

    /**
     * Validate the ui object.
     *
     * @param  mixed $ui
     * @return string[]
     */
    private function validate_ui(mixed $ui): array {
        $errors = [];

        if (!is_array($ui)) {
            return ['Tipo inválido en ui: esperado object'];
        }

        // Required fields.
        foreach (['intermediate_subformulas', 'row_order'] as $field) {
            if (!array_key_exists($field, $ui)) {
                $errors[] = 'Campo requerido: ui.' . $field;
            }
        }

        // additionalProperties: false.
        $allowed_ui = ['intermediate_subformulas', 'manual_subformulas', 'row_order'];
        foreach (array_keys($ui) as $key) {
            if (!in_array($key, $allowed_ui, true)) {
                $errors[] = 'Campo no permitido: ui.' . $key;
            }
        }

        if (array_key_exists('intermediate_subformulas', $ui)) {
            if (!is_string($ui['intermediate_subformulas'])) {
                $errors[] = 'Tipo inválido en ui.intermediate_subformulas: esperado string';
            } else if (!in_array($ui['intermediate_subformulas'], ['auto', 'none', 'manual'], true)) {
                $errors[] = 'Valor inválido en ui.intermediate_subformulas: ' . $ui['intermediate_subformulas'];
            }
        }

        if (array_key_exists('manual_subformulas', $ui)) {
            if (!is_array($ui['manual_subformulas'])) {
                $errors[] = 'Tipo inválido en ui.manual_subformulas: esperado array';
            }
        }

        if (array_key_exists('row_order', $ui)) {
            if (!is_string($ui['row_order'])) {
                $errors[] = 'Tipo inválido en ui.row_order: esperado string';
            } else if ($ui['row_order'] !== 'canonical') {
                $errors[] = 'Valor inválido en ui.row_order: ' . $ui['row_order'];
            }
        }

        return $errors;
    }

    /**
     * Validate the scoring object.
     *
     * @param  mixed $scoring
     * @return string[]
     */
    private function validate_scoring(mixed $scoring): array {
        $errors = [];

        if (!is_array($scoring)) {
            return ['Tipo inválido en scoring: esperado object'];
        }

        foreach (['radio_weight', 'table_weight', 'wrong_radio_policy'] as $field) {
            if (!array_key_exists($field, $scoring)) {
                $errors[] = 'Campo requerido: scoring.' . $field;
            }
        }

        // additionalProperties: false.
        $allowed = ['radio_weight', 'table_weight', 'wrong_radio_policy'];
        foreach (array_keys($scoring) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = 'Campo no permitido: scoring.' . $key;
            }
        }

        if (array_key_exists('radio_weight', $scoring)) {
            if (!is_int($scoring['radio_weight'])) {
                $errors[] = 'Tipo inválido en scoring.radio_weight: esperado integer';
            } else if ($scoring['radio_weight'] < 0 || $scoring['radio_weight'] > 100) {
                $errors[] = 'Valor inválido en scoring.radio_weight: ' . $scoring['radio_weight'];
            }
        }

        if (array_key_exists('table_weight', $scoring)) {
            if (!is_int($scoring['table_weight'])) {
                $errors[] = 'Tipo inválido en scoring.table_weight: esperado integer';
            } else if ($scoring['table_weight'] < 0 || $scoring['table_weight'] > 100) {
                $errors[] = 'Valor inválido en scoring.table_weight: ' . $scoring['table_weight'];
            }
        }

        if (array_key_exists('wrong_radio_policy', $scoring)) {
            if (!is_string($scoring['wrong_radio_policy'])) {
                $errors[] = 'Tipo inválido en scoring.wrong_radio_policy: esperado string';
            } else if (!in_array($scoring['wrong_radio_policy'], ['strict', 'proportional'], true)) {
                $errors[] = 'Valor inválido en scoring.wrong_radio_policy: ' . $scoring['wrong_radio_policy'];
            }
        }

        return $errors;
    }

    /**
     * Validate the config object for each problem type.
     *
     * @param  mixed  $config
     * @param  string $type
     * @return string[]
     */
    private function validate_problem_config(mixed $config, string $type): array {
        $errors = [];

        if (!is_array($config)) {
            return ['Tipo inválido en config: esperado object'];
        }

        if ($type === 'complete') {
            if (!array_key_exists('formula', $config)) {
                $errors[] = 'Campo requerido: config.formula';
            }
            $allowed = ['formula'];
            foreach (array_keys($config) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $errors[] = 'Campo no permitido: config.' . $key;
                }
            }
            if (array_key_exists('formula', $config) && !is_string($config['formula'])) {
                $errors[] = 'Tipo inválido en config.formula: esperado string';
            }
        } else if ($type === 'equivalence') {
            foreach (['formula_1', 'formula_2', 'require_table_justification'] as $field) {
                if (!array_key_exists($field, $config)) {
                    $errors[] = 'Campo requerido: config.' . $field;
                }
            }
            $allowed = ['formula_1', 'formula_2', 'expected_equivalent', 'require_table_justification'];
            foreach (array_keys($config) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $errors[] = 'Campo no permitido: config.' . $key;
                }
            }
            foreach (['formula_1', 'formula_2'] as $field) {
                if (array_key_exists($field, $config) && !is_string($config[$field])) {
                    $errors[] = 'Tipo inválido en config.' . $field . ': esperado string';
                }
            }
            if (array_key_exists('expected_equivalent', $config)) {
                $v = $config['expected_equivalent'];
                if ($v !== null && !is_bool($v)) {
                    $errors[] = 'Tipo inválido en config.expected_equivalent: esperado boolean o null';
                }
            }
            if (
                array_key_exists('require_table_justification', $config) &&
                !is_bool($config['require_table_justification'])
            ) {
                $errors[] = 'Tipo inválido en config.require_table_justification: esperado boolean';
            }
        } else if ($type === 'classify') {
            foreach (['formula', 'require_table_justification'] as $field) {
                if (!array_key_exists($field, $config)) {
                    $errors[] = 'Campo requerido: config.' . $field;
                }
            }
            $allowed = ['formula', 'expected_class', 'require_table_justification'];
            foreach (array_keys($config) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $errors[] = 'Campo no permitido: config.' . $key;
                }
            }
            if (array_key_exists('formula', $config) && !is_string($config['formula'])) {
                $errors[] = 'Tipo inválido en config.formula: esperado string';
            }
            if (array_key_exists('expected_class', $config)) {
                $v = $config['expected_class'];
                if ($v !== null && !is_string($v)) {
                    $errors[] = 'Tipo inválido en config.expected_class: esperado string o null';
                } else if (is_string($v) && !in_array($v, ['tautology', 'contradiction', 'contingency'], true)) {
                    $errors[] = 'Valor inválido en config.expected_class: ' . $v;
                }
            }
            if (
                array_key_exists('require_table_justification', $config) &&
                !is_bool($config['require_table_justification'])
            ) {
                $errors[] = 'Tipo inválido en config.require_table_justification: esperado boolean';
            }
        }

        return $errors;
    }

    // =========================================================================
    // Private: submission validation per type
    // =========================================================================

    /**
     * Validate a submission array for a specific type.
     *
     * @param  array  $data
     * @param  string $type
     * @return string[]
     */
    private function validate_submission(array $data, string $type): array {
        $errors = [];

        // Required fields at top level (table is optional for equiv/classify).
        $required = ['tool', 'schema_version', 'type', 'radio_answer'];
        if ($type === 'complete') {
            $required[] = 'table';
        }
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                $errors[] = 'Campo requerido: ' . $field;
            }
        }

        // additionalProperties: false at top level.
        $allowed = ['tool', 'schema_version', 'type', 'table', 'radio_answer'];
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = 'Campo no permitido: ' . $key;
            }
        }

        // radio_answer validation by type.
        if (array_key_exists('radio_answer', $data)) {
            $errors = array_merge($errors, $this->validate_radio_answer($data['radio_answer'], $type));
        }

        // Validate table if present.
        if (array_key_exists('table', $data) && $data['table'] !== null) {
            $errors = array_merge($errors, $this->validate_submission_table($data['table']));
        }

        return $errors;
    }

    /**
     * Validate the radio_answer field per submission type.
     *
     * @param  mixed  $value
     * @param  string $type
     * @return string[]
     */
    private function validate_radio_answer(mixed $value, string $type): array {
        if ($type === 'complete') {
            if ($value !== null) {
                return ['Valor inválido en radio_answer: ' . var_export($value, true)];
            }
            return [];
        }

        if ($type === 'equivalence') {
            if ($value !== null && !is_bool($value)) {
                return ['Tipo inválido en radio_answer: esperado boolean o null'];
            }
            return [];
        }

        if ($type === 'classify') {
            if ($value === null) {
                return []; // Allowed (unanswered).
            }
            if (!is_string($value)) {
                return ['Tipo inválido en radio_answer: esperado string o null'];
            }
            if (!in_array($value, ['tautology', 'contradiction', 'contingency'], true)) {
                return ['Valor inválido en radio_answer: ' . $value];
            }
            return [];
        }

        return [];
    }

    /**
     * Validate the table object in a submission.
     *
     * @param  mixed $table
     * @return string[]
     */
    private function validate_submission_table(mixed $table): array {
        $errors = [];

        if (!is_array($table)) {
            return ['Tipo inválido en table: esperado object'];
        }

        foreach (['columns', 'rows'] as $field) {
            if (!array_key_exists($field, $table)) {
                $errors[] = 'Campo requerido: table.' . $field;
            }
        }

        // additionalProperties: false.
        $allowed = ['columns', 'rows'];
        foreach (array_keys($table) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = 'Campo no permitido: table.' . $key;
            }
        }

        if (array_key_exists('columns', $table)) {
            if (!is_array($table['columns'])) {
                $errors[] = 'Tipo inválido en table.columns: esperado array';
            }
        }

        if (array_key_exists('rows', $table)) {
            if (!is_array($table['rows'])) {
                $errors[] = 'Tipo inválido en table.rows: esperado array';
            } else {
                foreach ($table['rows'] as $row_idx => $row) {
                    $errors = array_merge(
                        $errors,
                        $this->validate_submission_row($row, $row_idx)
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a single row object in the submission table.
     *
     * @param  mixed $row
     * @param  int   $row_idx 0-based row index (for error messages).
     * @return string[]
     */
    private function validate_submission_row(mixed $row, int $row_idx): array {
        $errors = [];
        $prefix = 'table.rows[' . $row_idx . ']';

        if (!is_array($row)) {
            return ['Tipo inválido en ' . $prefix . ': esperado object'];
        }

        foreach (['vars', 'values'] as $field) {
            if (!array_key_exists($field, $row)) {
                $errors[] = 'Campo requerido: ' . $prefix . '.' . $field;
            }
        }

        // additionalProperties: false at row level.
        $allowed = ['vars', 'values'];
        foreach (array_keys($row) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = 'Campo no permitido: ' . $prefix . '.' . $key;
            }
        }

        // vars: object — do not enforce additionalProperties (keys are dynamic A–Z variable names).
        if (array_key_exists('vars', $row) && !is_array($row['vars'])) {
            $errors[] = 'Tipo inválido en ' . $prefix . '.vars: esperado object';
        }

        if (array_key_exists('values', $row)) {
            if (!is_array($row['values'])) {
                $errors[] = 'Tipo inválido en ' . $prefix . '.values: esperado array';
            } else {
                foreach ($row['values'] as $vi => $val) {
                    if (!in_array($val, ['V', 'F', ''], true)) {
                        $errors[] = 'Valor inválido en ' . $prefix . '.values[' . $vi . ']: ' . var_export($val, true);
                    }
                }
            }
        }

        return $errors;
    }
}
