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
 * Sad-path tests for schema_loader and the lighter validator::validate_problem:
 * corrupt payloads, unknown problem types, invalid weights.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\schema;

use local_graphitoubb\tools\truth_table\domain\validator as domain_validator;

/**
 * Sad-path coverage focused on payload corruption surfaces.
 *
 * @coversNothing
 */
final class schema_loader_sad_test extends \basic_testcase {
    /** @var schema_loader */
    private schema_loader $loader;

    protected function setUp(): void {
        parent::setUp();
        $this->loader = new schema_loader();
    }

    // -------------------------------------------------------------------------
    // Helpers — minimal valid problem fixtures (one per type).
    // -------------------------------------------------------------------------

    private function base_complete(): array {
        return [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'complete',
            'ui'             => [
                'intermediate_subformulas' => 'auto',
                'row_order'                => 'canonical',
            ],
            'config' => ['formula' => 'A ∧ B'],
        ];
    }

    private function base_equivalence(): array {
        return [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'ui'             => [
                'intermediate_subformulas' => 'auto',
                'row_order'                => 'canonical',
            ],
            'scoring' => [
                'radio_weight'       => 50,
                'table_weight'       => 50,
                'wrong_radio_policy' => 'strict',
            ],
            'config' => [
                'formula_1'                   => 'A',
                'formula_2'                   => 'A',
                'expected_equivalent'         => true,
                'require_table_justification' => false,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Type / tool / schema_version corruption
    // -------------------------------------------------------------------------

    public function test_schema_loader_rejects_unknown_type_via_const_check(): void {
        // When the caller asks for 'complete' but the payload says 'frobnicate',
        // the const check on `type` fires regardless of whether 'frobnicate' is known.
        $problem = $this->base_complete();
        $problem['type'] = 'frobnicate';
        $r = $this->loader->validate($problem, 'complete', 'problem');
        $this->assertFalse($r->ok);
        $this->assertContains('Valor inválido en type: frobnicate', $r->errors);
    }

    public function test_schema_loader_rejects_wrong_tool(): void {
        $problem = $this->base_complete();
        $problem['tool'] = 'graph_editor';
        $r = $this->loader->validate($problem, 'complete', 'problem');
        $this->assertFalse($r->ok);
        $this->assertContains('Valor inválido en tool: graph_editor', $r->errors);
    }

    public function test_schema_loader_rejects_wrong_schema_version(): void {
        $problem = $this->base_complete();
        $problem['schema_version'] = 2;
        $r = $this->loader->validate($problem, 'complete', 'problem');
        $this->assertFalse($r->ok);
        $this->assertContains('Valor inválido en schema_version: 2', $r->errors);
    }

    public function test_schema_loader_rejects_non_int_schema_version(): void {
        $problem = $this->base_complete();
        $problem['schema_version'] = '1';
        $r = $this->loader->validate($problem, 'complete', 'problem');
        $this->assertFalse($r->ok);
        $this->assertContains('Tipo inválido en schema_version: esperado integer', $r->errors);
    }

    public function test_schema_loader_rejects_extra_top_level_key(): void {
        $problem = $this->base_complete();
        $problem['rogue'] = 'value';
        $r = $this->loader->validate($problem, 'complete', 'problem');
        $this->assertFalse($r->ok);
        $this->assertContains('Campo no permitido: rogue', $r->errors);
    }

    // -------------------------------------------------------------------------
    // Weights corruption
    // -------------------------------------------------------------------------

    public function test_schema_loader_rejects_radio_weight_above_100(): void {
        $problem = $this->base_equivalence();
        $problem['scoring']['radio_weight'] = 150;
        $problem['scoring']['table_weight'] = -50; // make weights out-of-range on both sides.
        $r = $this->loader->validate($problem, 'equivalence', 'problem');
        $this->assertFalse($r->ok);
        $errors = $r->errors;
        $this->assertContains('Valor inválido en scoring.radio_weight: 150', $errors);
        $this->assertContains('Valor inválido en scoring.table_weight: -50', $errors);
    }

    public function test_schema_loader_rejects_non_int_weight(): void {
        $problem = $this->base_equivalence();
        $problem['scoring']['radio_weight'] = '50';
        $r = $this->loader->validate($problem, 'equivalence', 'problem');
        $this->assertFalse($r->ok);
        $this->assertContains('Tipo inválido en scoring.radio_weight: esperado integer', $r->errors);
    }

    public function test_schema_loader_rejects_missing_scoring_field(): void {
        $problem = $this->base_equivalence();
        unset($problem['scoring']['wrong_radio_policy']);
        $r = $this->loader->validate($problem, 'equivalence', 'problem');
        $this->assertFalse($r->ok);
        $this->assertContains('Campo requerido: scoring.wrong_radio_policy', $r->errors);
    }

    public function test_schema_loader_rejects_unknown_wrong_radio_policy(): void {
        $problem = $this->base_equivalence();
        $problem['scoring']['wrong_radio_policy'] = 'lenient';
        $r = $this->loader->validate($problem, 'equivalence', 'problem');
        $this->assertFalse($r->ok);
        $this->assertContains('Valor inválido en scoring.wrong_radio_policy: lenient', $r->errors);
    }

    public function test_schema_loader_rejects_extra_scoring_key(): void {
        $problem = $this->base_equivalence();
        $problem['scoring']['bonus'] = 10;
        $r = $this->loader->validate($problem, 'equivalence', 'problem');
        $this->assertFalse($r->ok);
        $this->assertContains('Campo no permitido: scoring.bonus', $r->errors);
    }

    // -------------------------------------------------------------------------
    // Domain validator (validator.php) — weight sum, unknown type
    // -------------------------------------------------------------------------

    public function test_domain_validator_rejects_weights_sum_not_100(): void {
        $v = new domain_validator();
        $problem = $this->base_equivalence();
        $problem['scoring']['radio_weight'] = 50;
        $problem['scoring']['table_weight'] = 30; // sum = 80
        $r = $v->validate_problem($problem);
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('debe ser 100', $r->errors[0]);
    }

    public function test_domain_validator_rejects_missing_required_top_level(): void {
        $v = new domain_validator();
        $r = $v->validate_problem(['tool' => 'truth_table']); // missing type, config
        $this->assertFalse($r->ok);
        $errors = $r->errors;
        $this->assertContains('Campo requerido ausente: "type".', $errors);
        $this->assertContains('Campo requerido ausente: "config".', $errors);
    }

    public function test_domain_validator_unknown_type_passes_when_config_empty(): void {
        // The domain validator does not enforce a known `type` value: it only checks
        // top-level required fields, per-type required config fields, and (for the
        // formula-bearing types) parseability of those formulas. Unknown types map
        // to an empty required-config list and no formula fields, so an empty payload
        // is accepted. The schema_loader is the strict type gate for persistence.
        $v = new domain_validator();
        $problem = [
            'tool'   => 'truth_table',
            'type'   => 'frobnicate',
            'config' => [],
        ];
        $r = $v->validate_problem($problem);
        $this->assertTrue($r->ok);
    }

    public function test_domain_validator_rejects_unparseable_formula_complete(): void {
        // Bug regression: edit_problem.php used to accept '(B &&' because
        // validate_problem never invoked the parser. The parser is now called for every
        // formula field per type.
        $v = new domain_validator();
        $problem = [
            'tool'   => 'truth_table',
            'type'   => 'complete',
            'config' => ['formula' => '(B &&'],
        ];
        $r = $v->validate_problem($problem);
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('config.formula', $r->errors[0]);
        $this->assertStringContainsString('Error sintáctico', implode(';', $r->errors));
    }

    public function test_domain_validator_rejects_unparseable_formula_equivalence(): void {
        $v = new domain_validator();
        $problem = [
            'tool'    => 'truth_table',
            'type'    => 'equivalence',
            'config'  => [
                'formula_1' => 'A',
                'formula_2' => '(B &&',
                'expected_equivalent'         => true,
                'require_table_justification' => false,
            ],
            'scoring' => ['radio_weight' => 50, 'table_weight' => 50],
        ];
        $r = $v->validate_problem($problem);
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('config.formula_2', implode(';', $r->errors));
    }

    public function test_domain_validator_rejects_lex_invalid_formula_classify(): void {
        $v = new domain_validator();
        $problem = [
            'tool'   => 'truth_table',
            'type'   => 'classify',
            'config' => [
                'formula'                     => 'lowercase',
                'expected_class'              => 'tautology',
                'require_table_justification' => false,
            ],
        ];
        $r = $v->validate_problem($problem);
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Error léxico', implode(';', $r->errors));
    }

    // -------------------------------------------------------------------------
    // Submission corruption — values out of {V,F,'',null}, radio null variants
    // -------------------------------------------------------------------------

    public function test_submission_rejects_value_outside_allowed_set(): void {
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'complete',
            'radio_answer'   => null,
            'table'          => [
                'columns' => ['A', 'B', 'A∧B'],
                'rows'    => [
                    ['vars' => ['A' => true, 'B' => true], 'values' => ['V', 'F', 'maybe']],
                    ['vars' => ['A' => true, 'B' => false], 'values' => ['V', 'F', 'X']],
                    ['vars' => ['A' => false, 'B' => true], 'values' => ['V', 'F', null]],
                ],
            ],
        ];
        $r = $this->loader->validate($submission, 'complete', 'submission');
        $this->assertFalse($r->ok);
        $errors = $r->errors;
        $this->assertContains("Valor inválido en table.rows[0].values[2]: 'maybe'", $errors);
        $this->assertContains("Valor inválido en table.rows[1].values[2]: 'X'", $errors);
        // null is also outside the allowed {V,F,''} set per the schema.
        $this->assertContains('Valor inválido en table.rows[2].values[2]: NULL', $errors);
    }

    public function test_submission_rejects_null_radio_for_complete(): void {
        // For complete type, radio_answer must be exactly null. A non-null value is rejected.
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'complete',
            'radio_answer'   => 'tautology',
            'table'          => ['columns' => [], 'rows' => []],
        ];
        $r = $this->loader->validate($submission, 'complete', 'submission');
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('Valor inválido en radio_answer', implode(';', $r->errors));
    }

    public function test_submission_rejects_string_radio_for_equivalence(): void {
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'radio_answer'   => 'true',
        ];
        $r = $this->loader->validate($submission, 'equivalence', 'submission');
        $this->assertFalse($r->ok);
        $this->assertContains('Tipo inválido en radio_answer: esperado boolean o null', $r->errors);
    }

    public function test_submission_rejects_unknown_class_for_classify(): void {
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'classify',
            'radio_answer'   => 'satisfiable', // not in {tautology, contradiction, contingency}
        ];
        $r = $this->loader->validate($submission, 'classify', 'submission');
        $this->assertFalse($r->ok);
        $this->assertContains('Valor inválido en radio_answer: satisfiable', $r->errors);
    }

    public function test_submission_rejects_extra_row_field(): void {
        $submission = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'complete',
            'radio_answer'   => null,
            'table'          => [
                'columns' => ['A', 'A∧B'],
                'rows'    => [
                    ['vars' => ['A' => true], 'values' => ['V'], 'extra' => 'oops'],
                ],
            ],
        ];
        $r = $this->loader->validate($submission, 'complete', 'submission');
        $this->assertFalse($r->ok);
        $this->assertContains('Campo no permitido: table.rows[0].extra', $r->errors);
    }
}
