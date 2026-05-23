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
 * Unit tests for the domain validator.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

/**
 * 6 tests covering bounds enforcement and problem-shape validation.
 *
 * @coversNothing
 */
final class validator_test extends \basic_testcase {
    /** @var validator */
    private validator $validator;

    protected function setUp(): void {
        parent::setUp();
        $this->validator = new validator();
    }

    // -------------------------------------------------------------------------
    // Test 1 — Rejects formula with > MAX_VARIABLES distinct variables.
    // -------------------------------------------------------------------------
    public function test_rejects_too_many_variables(): void {
        // Arrange — 6 variables, limit is 5.
        $formula = 'A ∧ B ∧ C ∧ D ∧ E ∧ F';

        // Act.
        $result = $this->validator->validate_formula($formula);

        // Assert.
        $this->assertFalse($result->ok, 'Must reject formula with 6 variables.');
        $this->assertNotEmpty($result->errors);
    }

    // -------------------------------------------------------------------------
    // Test 2 — Rejects formula with depth > MAX_DEPTH.
    // -------------------------------------------------------------------------
    public function test_rejects_depth_over_max(): void {
        // Arrange — 13 levels of negation: depth = 13, limit = 12.
        $formula = '¬¬¬¬¬¬¬¬¬¬¬¬¬A'; // 13 nots.

        // Act.
        $result = $this->validator->validate_formula($formula);

        // Assert.
        $this->assertFalse($result->ok, 'Must reject formula with depth 14 (13 nots + 1 var).');
        $this->assertNotEmpty($result->errors);
    }

    // -------------------------------------------------------------------------
    // Test 3 — Rejects formula longer than MAX_FORMULA_LENGTH characters.
    // -------------------------------------------------------------------------
    public function test_rejects_formula_over_max_length(): void {
        // Arrange — a formula that normalises to more than 128 chars.
        // Build a deep conjunction tree: A∧A∧A...  (≥ 129 chars normalised).
        $formula = implode(' ∧ ', array_fill(0, 70, 'A')); // 70 'A's joined with ' ∧ '.

        // Act.
        $result = $this->validator->validate_formula($formula);

        // Assert.
        $this->assertFalse($result->ok, 'Must reject formula longer than 128 chars.');
        $this->assertNotEmpty($result->errors);
    }

    // -------------------------------------------------------------------------
    // Test 4 — Accepts a valid problem payload.
    // -------------------------------------------------------------------------
    public function test_accepts_valid_complete_problem(): void {
        // Arrange.
        $problem = [
            'tool'   => 'truth_table',
            'type'   => 'complete',
            'config' => [
                'formula' => 'A ∧ B',
            ],
        ];

        // Act.
        $result = $this->validator->validate_problem($problem);

        // Assert.
        $this->assertTrue($result->ok, 'Valid problem must pass. Errors: ' . implode(', ', $result->errors));
    }

    // -------------------------------------------------------------------------
    // Test 5 — Rejects scoring weights that do not sum to 100.
    // -------------------------------------------------------------------------
    public function test_rejects_scoring_weights_not_summing_to_100(): void {
        // Arrange.
        $problem = [
            'tool'    => 'truth_table',
            'type'    => 'equivalence',
            'config'  => [
                'formula_1'                 => 'A ∧ B',
                'formula_2'                 => 'B ∧ A',
                'expected_equivalent'       => true,
                'require_table_justification' => false,
            ],
            'scoring' => [
                'radio_weight' => 60,
                'table_weight' => 60, // 60 + 60 = 120, not 100.
            ],
        ];

        // Act.
        $result = $this->validator->validate_problem($problem);

        // Assert.
        $this->assertFalse($result->ok, 'Must reject when weights do not sum to 100.');
        $found = false;
        foreach ($result->errors as $err) {
            if (strpos($err, '100') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Error message should mention 100.');
    }

    // -------------------------------------------------------------------------
    // Test 6 — Rejects problem JSON exceeding MAX_PROBLEM_JSON_BYTES.
    // -------------------------------------------------------------------------
    public function test_rejects_json_over_max_bytes(): void {
        // Arrange — pad a valid problem with a large extra field to exceed 8 KB.
        $problem = [
            'tool'    => 'truth_table',
            'type'    => 'complete',
            'config'  => ['formula' => 'A'],
            'padding' => str_repeat('x', 9000), // 9 KB of padding.
        ];

        // Act.
        $result = $this->validator->validate_problem($problem);

        // Assert.
        $this->assertFalse($result->ok, 'Must reject problem exceeding 8 KB JSON size.');
        $found = false;
        foreach ($result->errors as $err) {
            if (strpos($err, 'bytes') !== false || strpos($err, 'JSON') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Error message should mention JSON size.');
    }
}
