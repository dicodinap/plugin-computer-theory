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
 * Question class for qtype_graphitoubb.
 *
 * Loaded by Moodle's question engine via the filename convention (non-autoloaded).
 * Delegates grading to local_graphitoubb\tools\truth_table\grader\grader.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_graphitoubb\tools\truth_table\grader\grader;

/**
 * A GraphitoUBB truth_table question.
 *
 * Supports exercise types: complete | equivalence | classify.
 * Behaviours tested in iter1: deferredfeedback, immediatefeedback.
 * SHOULD work with: adaptive, adaptivenopenalty.
 * NOT supported in iter1: interactive, manualgraded.
 */
class qtype_graphitoubb_question extends question_graded_automatically {
    /** @var string Tool slug — always 'truth_table' for this qtype. */
    public string $tool = 'truth_table';

    /** @var string Exercise mode: 'complete' | 'equivalence' | 'classify'. */
    public string $exercise_type = 'complete';

    /** @var array Decoded problem JSON array. */
    public array $problem_payload = [];

    /** @var array Decoded scoring config: radio_weight, table_weight, wrong_radio_policy. */
    public array $scoring_config = [];

    /** @var array Decoded UI config: intermediate_subformulas, row_order. */
    public array $ui_config = [];

    /** @var string SHA-256 hash of the canonical problem payload at save time. */
    public string $payload_hash = '';

    /** @var int Schema version for migration support. */
    public int $schema_version = 1;

    /**
     * Declare the expected student response variables.
     *
     * The question engine will persist these in question_attempt_step_data.
     * answer_payload: JSON-encoded submission from the truth_table editor.
     *
     * @return array Variable name => PARAM_* constant.
     */
    public function get_expected_data(): array {
        return ['answer_payload' => PARAM_RAW];
    }

    /**
     * Return a short human-readable summary of the student's response.
     *
     * Used in the quiz review page and attempt overview.
     *
     * @param  array $response The response array with 'answer_payload'.
     * @return string|null Summary string or null when no response.
     */
    public function summarise_response(array $response): ?string {
        $raw = $response['answer_payload'] ?? '';
        if ($raw === '' || $raw === null) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return get_string('err_internal', 'qtype_graphitoubb');
        }

        // grafo/arbol: summarise the answer envelope.
        if ($this->tool === 'grafo' || $this->tool === 'arbol') {
            $kind = $data['answer_kind'] ?? '';
            if ($kind === 'boolean') {
                return ($data['value'] ?? false) ? get_string('yes') : get_string('no');
            }
            if ($kind === 'sequence') {
                $seq = $data['edges'] ?? ($data['values'] ?? []);
                return is_array($seq) ? implode(', ', $seq) : '';
            }
            if ($kind === 'graph') {
                return count($data['graph']['nodes'] ?? []) . ' vertices, '
                    . count($data['graph']['edges'] ?? []) . ' edges';
            }
            if ($kind === 'tree') {
                return count($data['tree']['nodes'] ?? []) . ' nodes';
            }
            return $kind;
        }

        $rows = $data['table']['rows'] ?? [];
        $row_count = count($rows);

        $rows_suffix = $row_count > 0
            ? get_string('summary_rows', 'qtype_graphitoubb', $row_count)
            : '';

        switch ($this->exercise_type) {
            case 'equivalence':
                $radio = $data['radio_answer'] ?? null;
                $label = ($radio === true || $radio === 'true')
                    ? get_string('yes')
                    : get_string('no');
                return get_string('summary_equivalence', 'qtype_graphitoubb', $label) . $rows_suffix;

            case 'classify':
                $radio = $data['radio_answer'] ?? null;
                $radio_str = is_string($radio)
                    ? $radio
                    : get_string('summary_no_answer', 'qtype_graphitoubb');
                return get_string('summary_classify', 'qtype_graphitoubb', $radio_str) . $rows_suffix;

            default: // complete
                return get_string('summary_complete', 'qtype_graphitoubb', $row_count);
        }
    }

    /**
     * Determine whether the student's response is complete (non-empty JSON payload).
     *
     * @param  array $response
     * @return bool
     */
    public function is_complete_response(array $response): bool {
        $raw = $response['answer_payload'] ?? '';
        if ($raw === '' || $raw === null) {
            return false;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return false;
        }
        // grafo/arbol: a complete response is a non-empty answer envelope.
        if ($this->tool === 'grafo' || $this->tool === 'arbol') {
            return !empty($data['answer_kind']);
        }
        return true;
    }

    /**
     * Determine whether the response can be graded.
     *
     * For truth_table, gradable == complete (no partial-entry state).
     *
     * @param  array $response
     * @return bool
     */
    public function is_gradable_response(array $response): bool {
        return $this->is_complete_response($response);
    }

    /**
     * Determine whether two responses are equivalent without regrading.
     *
     * Compares the SHA-256 hash of the answer_payload if present in both;
     * falls back to a direct string comparison.
     *
     * @param  array $prevresponse
     * @param  array $newresponse
     * @return bool
     */
    public function is_same_response(array $prevresponse, array $newresponse): bool {
        $prev_raw = $prevresponse['answer_payload'] ?? '';
        $new_raw  = $newresponse['answer_payload'] ?? '';

        if ($prev_raw === $new_raw) {
            return true;
        }

        // If both are valid JSON, compare SHA-256 hashes of canonical encoding.
        $prev_data = json_decode($prev_raw, true);
        $new_data  = json_decode($new_raw, true);

        if (is_array($prev_data) && is_array($new_data)) {
            return hash('sha256', json_encode($prev_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                === hash('sha256', json_encode($new_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return false;
    }

    /**
     * Grade the student's response by delegating to local_graphitoubb grader.
     *
     * Decodes the answer_payload JSON, builds the submission array, calls
     * grader::instance()->grade(), and returns the [fraction, state] pair.
     *
     * Any Throwable (parse error, invalid payload, grader failure) is caught
     * and returns [0.0, question_state::$gaveup].
     *
     * @param  array $response The response array with 'answer_payload'.
     * @return array [float $fraction, question_state $state]
     */
    public function grade_response(array $response): array {
        try {
            $raw = $response['answer_payload'] ?? '';
            if ($raw === '' || $raw === null) {
                return [0.0, question_state::$gaveup];
            }

            $submission = json_decode($raw, true);
            if (!is_array($submission)) {
                return [0.0, question_state::$gaveup];
            }

            if (empty($this->problem_payload)) {
                return [0.0, question_state::$gaveup];
            }

            // Tool-aware routing (D8/C2): grafo/arbol grade through the shared
            // grader_dispatch; truth_table keeps its existing path (I3 — unchanged).
            if ($this->tool === 'grafo' || $this->tool === 'arbol') {
                $dispatch = \local_graphitoubb\grader_dispatch::for($this->tool);
                if ($dispatch === null) {
                    return [0.0, question_state::$gaveup];
                }
                $arr = $dispatch->grade($this->problem_payload, $raw);
                if (!empty($arr['invalid'])) {
                    return [0.0, question_state::$gaveup];
                }
                $fraction = (float) ($arr['fraction'] ?? 0.0);
                return [$fraction, question_state::graded_state_for_fraction($fraction)];
            }

            $result = grader::instance()->grade(
                $this->problem_payload,
                $submission
            );

            return [
                $result->fraction,
                question_state::graded_state_for_fraction($result->fraction),
            ];
        } catch (\Throwable $e) {
            return [0.0, question_state::$gaveup];
        }
    }

    /**
     * Return the correct response for review display.
     *
     * The correct response is the computed truth table, which is not trivially
     * serializable into a single response array. Return null; the renderer's
     * correct_response() handles display via the grader.
     *
     * @return array|null
     */
    public function get_correct_response(): ?array {
        return null;
    }

    /**
     * Return a validation error message for an incomplete response.
     *
     * Shown when the student tries to submit an incomplete answer.
     *
     * @param  array $response
     * @return string Error message in Spanish.
     */
    public function get_validation_error(array $response): string {
        return 'Respuesta inválida.';
    }
}
