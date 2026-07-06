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
 * Question type class for qtype_graphitoubb.
 *
 * Loaded by Moodle's question bank via the filename convention (non-autoloaded).
 * Delegates grading to local_graphitoubb\tools\truth_table\grader\grader.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_graphitoubb\tools\truth_table\domain\serializer;
use local_graphitoubb\tools\truth_table\schema\schema_loader;

/**
 * The graphitoubb question type — truth_table problems inside the Moodle question engine.
 *
 * Extends question_type (not question_graded_automatically — that is the question class).
 */
class qtype_graphitoubb extends question_type {
    /**
     * The question type name used by the question engine.
     *
     * @return string
     */
    public function name(): string {
        return 'graphitoubb';
    }

    /**
     * Declares extra DB columns stored in qtype_graphitoubb_options.
     *
     * First element is the table name; subsequent elements are column names.
     * Moodle's question_type::save_question_options and get_question_options
     * will use these to read/write the record automatically — but we override
     * both methods to add schema validation and hash computation.
     *
     * @return array
     */
    public function extra_question_fields(): array {
        // Only scalar columns are listed here: the question engine copies each listed
        // column straight onto the question instance, which would assign the raw JSON
        // strings to the array-typed $problem_payload/$scoring_config/$ui_config
        // properties and throw a TypeError. Those JSON columns are still loaded into
        // $questiondata->options (full row) and decoded in initialise_question_instance().
        return [
            'qtype_graphitoubb_options',
            'tool',
            'exercise_type',
        ];
    }

    /**
     * Column name that links the options table to question.id.
     *
     * @return string
     */
    public function questionid_column_name(): string {
        return 'questionid';
    }

    /**
     * Save question-type specific options to qtype_graphitoubb_options.
     *
     * Builds the problem array from form data, validates schema, computes hash,
     * then upserts the DB record.
     *
     * @param  object $question Stdclass with form fields merged by question engine.
     * @return void
     * @throws \moodle_exception On schema validation failure or encoding error.
     */
    public function save_question_options($question): void {
        global $DB;

        $tool = $question->tool ?? 'truth_table';

        // grafo/arbol: the canonical payload arrives ready-made (XML import / seeding),
        // not from truth_table form fields. Store it as-is (no truth_table schema).
        if ($tool === 'grafo' || $tool === 'arbol') {
            $this->save_canvas_question_options($question, $tool);
            return;
        }

        $exercise_type = $question->exercise_type ?? 'complete';

        // Build the problem_payload array from submitted form fields.
        $problem = $this->build_problem_array($question, $exercise_type);

        // Validate against the JSON Schema before persisting.
        $loader = new schema_loader();
        $result = $loader->validate($problem, $exercise_type, 'problem');
        if (!$result->ok) {
            $errors = implode('; ', $result->errors);
            throw new \moodle_exception(
                'err_schema_validation',
                'qtype_graphitoubb',
                '',
                $errors
            );
        }

        // Serialize and hash the canonical problem.
        $ser     = new serializer();
        $payload_json = $ser->encode($problem);
        $hash         = $ser->hash($problem);

        // Build scoring and ui config JSONs.
        $scoring = $this->build_scoring_array($question, $exercise_type);
        $ui      = $this->build_ui_array($question);

        $record = (object) [
            'questionid'      => $question->id,
            'tool'            => $tool,
            'exercise_type'   => $exercise_type,
            'problem_payload' => $payload_json,
            'scoring_config'  => json_encode($scoring, JSON_UNESCAPED_UNICODE),
            'ui_config'       => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'payload_hash'    => $hash,
            'schema_version'  => 1,
        ];

        $existing = $DB->get_record('qtype_graphitoubb_options', ['questionid' => $question->id]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('qtype_graphitoubb_options', $record);
        } else {
            $DB->insert_record('qtype_graphitoubb_options', $record);
        }
    }

    /**
     * Persist a grafo/arbol (canvas) question's options. The canonical payload is
     * taken verbatim from $question->problem_payload (import/seeding) — grafo/arbol
     * have no truth_table-style form, and no truth_table JSON schema applies.
     *
     * @param  object $question
     * @param  string $tool 'grafo' | 'arbol'
     * @return void
     * @throws \moodle_exception When the payload is not valid JSON.
     */
    private function save_canvas_question_options($question, string $tool): void {
        global $DB;

        $payload_json = is_string($question->problem_payload ?? null)
            ? $question->problem_payload
            : json_encode($question->problem_payload ?? [], JSON_UNESCAPED_UNICODE);
        $decoded = json_decode((string) $payload_json, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('err_schema_validation', 'qtype_graphitoubb', '', 'invalid payload JSON');
        }
        $exercise_type = (string) ($decoded['type'] ?? ($question->exercise_type ?? ''));

        $record = (object) [
            'questionid'      => $question->id,
            'tool'            => $tool,
            'exercise_type'   => $exercise_type,
            'problem_payload' => $payload_json,
            'scoring_config'  => '{}',
            'ui_config'       => '{}',
            'payload_hash'    => hash('sha256', json_encode($decoded, JSON_UNESCAPED_UNICODE)),
            'schema_version'  => (int) ($decoded['schema_version'] ?? 1),
        ];

        $existing = $DB->get_record('qtype_graphitoubb_options', ['questionid' => $question->id]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('qtype_graphitoubb_options', $record);
        } else {
            $DB->insert_record('qtype_graphitoubb_options', $record);
        }
    }

    /**
     * Load question-type options from DB into $question->options.
     *
     * The parent only selects the columns named in extra_question_fields() — which we
     * deliberately keep to the scalar tool/exercise_type to avoid the engine assigning
     * the raw JSON strings to the array-typed question properties. So here we explicitly
     * load the JSON columns the parent skips, ready for initialise_question_instance()
     * to decode.
     *
     * @param  object $question The question object, modified in-place.
     * @return bool
     */
    public function get_question_options($question): bool {
        global $DB;

        $ok = parent::get_question_options($question);

        $row = $DB->get_record('qtype_graphitoubb_options', ['questionid' => $question->id]);
        if ($row) {
            if (!isset($question->options)) {
                $question->options = new \stdClass();
            }
            $question->options->problem_payload = $row->problem_payload;
            $question->options->scoring_config  = $row->scoring_config;
            $question->options->ui_config       = $row->ui_config;
            $question->options->payload_hash    = $row->payload_hash;
            $question->options->schema_version  = $row->schema_version;
        }

        return $ok;
    }

    /**
     * Hydrate a qtype_graphitoubb_question instance from question_data.
     *
     * Called by the question engine after loading from DB.
     *
     * @param  qtype_graphitoubb_question $question    The question object to hydrate.
     * @param  object                     $questiondata Raw DB record with ->options.
     * @return void
     */
    public function initialise_question_instance($question, $questiondata): void {
        parent::initialise_question_instance($question, $questiondata);

        $opts = $questiondata->options;

        $question->tool          = $opts->tool ?? 'truth_table';
        $question->exercise_type = $opts->exercise_type ?? 'complete';
        $question->schema_version = (int) ($opts->schema_version ?? 1);

        // Decode JSON fields — fall back to empty arrays on decode failure.
        $question->problem_payload = $this->json_decode_safe($opts->problem_payload ?? '{}');
        $question->scoring_config  = $this->json_decode_safe($opts->scoring_config ?? '{}');
        $question->ui_config       = $this->json_decode_safe($opts->ui_config ?? '{}');
        $question->payload_hash    = $opts->payload_hash ?? '';
    }

    /**
     * Delete question-type specific data when a question is deleted.
     *
     * @param  int $questionid The question being deleted.
     * @param  int $contextid  The context containing the question.
     * @return void
     */
    public function delete_question($questionid, $contextid): void {
        global $DB;
        $DB->delete_records('qtype_graphitoubb_options', ['questionid' => $questionid]);
        parent::delete_question($questionid, $contextid);
    }

    /**
     * Move files associated with this question to a new context.
     *
     * @param  int $questionid
     * @param  int $oldcontextid
     * @param  int $newcontextid
     * @return void
     */
    public function move_files($questionid, $oldcontextid, $newcontextid): void {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
    }

    /**
     * Export question data to XML format.
     *
     * Serialises problem_payload, scoring_config, and ui_config as CDATA
     * sections so that Moodle's question XML format can round-trip the data.
     *
     * @param  object      $question The question object.
     * @param  qformat_xml $format   The XML format object.
     * @param  mixed       $extra    Unused extra data.
     * @return string XML fragment.
     */
    public function export_to_xml($question, qformat_xml $format, $extra = null): string {
        global $DB;
        // Defensive load: callers like \question_bank::load_question hydrate the
        // question definition but do not always populate the raw options row.
        if (empty($question->options) || empty($question->options->problem_payload)) {
            $loaded = $DB->get_record('qtype_graphitoubb_options', ['questionid' => $question->id]);
            if ($loaded) {
                $question->options = $loaded;
            }
        }
        $opts = $question->options ?? new \stdClass();
        $out  = '';

        $out .= '    <tool>' . $format->xml_escape($opts->tool ?? 'truth_table') . "</tool>\n";
        $out .= '    <exercise_type>' . $format->xml_escape($opts->exercise_type ?? 'complete') . "</exercise_type>\n";
        $out .= '    <schema_version>' . (int) ($opts->schema_version ?? 1) . "</schema_version>\n";
        $out .= '    <problem_payload><![CDATA[' . ($opts->problem_payload ?? '{}') . "]]></problem_payload>\n";
        $out .= '    <scoring_config><![CDATA[' . ($opts->scoring_config ?? '{}') . "]]></scoring_config>\n";
        $out .= '    <ui_config><![CDATA[' . ($opts->ui_config ?? '{}') . "]]></ui_config>\n";
        $out .= '    <payload_hash>' . $format->xml_escape($opts->payload_hash ?? '') . "</payload_hash>\n";

        return $out;
    }

    /**
     * Import a question from XML format.
     *
     * Parses fields written by export_to_xml, validates the schema,
     * and returns a hydrated question object or false on failure.
     *
     * @param  array       $data     Parsed XML node data.
     * @param  object|null $question Partially-built question object.
     * @param  qformat_xml $format   The XML format object.
     * @param  mixed       $extra    Unused extra data.
     * @return object|false
     */
    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        if (!isset($data['#']['problem_payload'])) {
            return false;
        }

        $qo = $format->import_headers($data);
        $qo->qtype = 'graphitoubb';

        $qo->tool           = $format->getpath($data, ['#', 'tool', 0, '#'], 'truth_table');
        $qo->exercise_type  = $format->getpath($data, ['#', 'exercise_type', 0, '#'], 'complete');
        $qo->schema_version = (int) $format->getpath($data, ['#', 'schema_version', 0, '#'], 1);
        $qo->problem_payload = $format->getpath($data, ['#', 'problem_payload', 0, '#'], '{}');
        $qo->scoring_config  = $format->getpath($data, ['#', 'scoring_config', 0, '#'], '{}');
        $qo->ui_config       = $format->getpath($data, ['#', 'ui_config', 0, '#'], '{}');

        // grafo/arbol: no truth_table schema; keep the payload verbatim, hash the
        // decoded canonical form, and let save_canvas_question_options() store it.
        if ($qo->tool === 'grafo' || $qo->tool === 'arbol') {
            $decoded = $this->json_decode_safe($qo->problem_payload);
            if (empty($decoded)) {
                return false;
            }
            $qo->payload_hash = hash('sha256', json_encode($decoded, JSON_UNESCAPED_UNICODE));
            return $qo;
        }

        // Validate the imported problem payload.
        $problem = $this->json_decode_safe($qo->problem_payload);
        $loader  = new schema_loader();
        $result  = $loader->validate($problem, $qo->exercise_type, 'problem');
        if (!$result->ok) {
            return false;
        }

        // Recompute the payload hash from the canonical decoded form.
        $ser             = new serializer();
        $qo->payload_hash = $ser->hash($problem);

        // Hydrate the individual form-style fields from the decoded payload. The engine
        // saves an imported question through save_question_options(), which rebuilds the
        // payload from these fields via build_problem_array(); without this the imported
        // formulas/scoring would be lost and an empty payload stored.
        $this->hydrate_fields_from_problem($qo, $problem);

        return $qo;
    }

    /**
     * Populate the form-style fields (formula, formula_1, scoring, …) on an imported
     * question object from its decoded problem payload, so save_question_options()
     * reconstructs the identical payload.
     *
     * @param  object $qo      The imported question object (modified in place).
     * @param  array  $problem Decoded canonical problem payload.
     * @return void
     */
    private function hydrate_fields_from_problem(object $qo, array $problem): void {
        $config  = $problem['config'] ?? [];
        $scoring = $problem['scoring'] ?? [];
        $type    = $qo->exercise_type ?? 'complete';

        if ($type === 'complete') {
            $qo->formula = $config['formula'] ?? '';
        } else if ($type === 'equivalence') {
            $qo->formula_1                   = $config['formula_1'] ?? '';
            $qo->formula_2                   = $config['formula_2'] ?? '';
            $qo->expected_equivalent         = !empty($config['expected_equivalent']);
            $qo->require_table_justification = !empty($config['require_table_justification']);
        } else if ($type === 'classify') {
            $qo->formula                     = $config['formula'] ?? '';
            $qo->expected_class              = $config['expected_class'] ?? 'tautology';
            $qo->require_table_justification = !empty($config['require_table_justification']);
        }

        if ($type === 'equivalence' || $type === 'classify') {
            $qo->radio_weight       = (int) ($scoring['radio_weight'] ?? 50);
            $qo->table_weight       = (int) ($scoring['table_weight'] ?? 50);
            $qo->wrong_radio_policy = $scoring['wrong_radio_policy'] ?? 'strict';
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers.
    // -------------------------------------------------------------------------

    /**
     * Build the problem array from form submitted data.
     *
     * @param  object $question     The form data.
     * @param  string $exercise_type 'complete' | 'equivalence' | 'classify'.
     * @return array
     */
    private function build_problem_array(object $question, string $exercise_type): array {
        $problem = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => $exercise_type,
            'ui'             => [
                'intermediate_subformulas' => 'auto',
                'manual_subformulas'       => [],
                'row_order'                => 'canonical',
            ],
        ];

        switch ($exercise_type) {
            case 'complete':
                $problem['config'] = [
                    'formula' => trim($question->formula ?? ''),
                ];
                break;

            case 'equivalence':
                $problem['config'] = [
                    'formula_1'                  => trim($question->formula_1 ?? ''),
                    'formula_2'                  => trim($question->formula_2 ?? ''),
                    'expected_equivalent'        => (bool) ($question->expected_equivalent ?? false),
                    'require_table_justification' => (bool) ($question->require_table_justification ?? false),
                ];
                $problem['scoring'] = [
                    'radio_weight'        => (int) ($question->radio_weight ?? 50),
                    'table_weight'        => (int) ($question->table_weight ?? 50),
                    'wrong_radio_policy'  => $question->wrong_radio_policy ?? 'strict',
                ];
                break;

            case 'classify':
                $problem['config'] = [
                    'formula'                    => trim($question->formula ?? ''),
                    'expected_class'             => $question->expected_class ?? 'tautology',
                    'require_table_justification' => (bool) ($question->require_table_justification ?? false),
                ];
                $problem['scoring'] = [
                    'radio_weight'        => (int) ($question->radio_weight ?? 50),
                    'table_weight'        => (int) ($question->table_weight ?? 50),
                    'wrong_radio_policy'  => $question->wrong_radio_policy ?? 'strict',
                ];
                break;
        }

        return $problem;
    }

    /**
     * Build the scoring config array from form data.
     *
     * @param  object $question
     * @param  string $exercise_type
     * @return array
     */
    private function build_scoring_array(object $question, string $exercise_type): array {
        if ($exercise_type === 'complete') {
            return [];
        }
        return [
            'radio_weight'       => (int) ($question->radio_weight ?? 50),
            'table_weight'       => (int) ($question->table_weight ?? 50),
            'wrong_radio_policy' => $question->wrong_radio_policy ?? 'strict',
        ];
    }

    /**
     * Build the UI config array from form data.
     *
     * @param  object $question
     * @return array
     */
    private function build_ui_array(object $question): array {
        return [
            'intermediate_subformulas' => 'auto',
            'row_order'                => 'canonical',
        ];
    }

    /**
     * Safely JSON-decode a string, returning an empty array on failure.
     *
     * @param  string $json
     * @return array
     */
    private function json_decode_safe(string $json): array {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
