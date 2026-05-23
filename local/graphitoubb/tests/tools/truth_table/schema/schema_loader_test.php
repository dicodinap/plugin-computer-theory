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
 * Unit tests for schema_loader.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\schema;

/**
 * Tests for the schema_loader: loading, valid cases, and rejection cases.
 *
 * @coversNothing
 */
final class schema_loader_test extends \basic_testcase {
    /** @var schema_loader */
    private schema_loader $loader;

    protected function setUp(): void {
        parent::setUp();
        $this->loader = new schema_loader();
    }

    // -------------------------------------------------------------------------
    // Minimal valid fixtures
    // -------------------------------------------------------------------------

    private function valid_problem_complete(): array {
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

    private function valid_problem_equivalence(): array {
        return [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'equivalence',
            'ui'             => [
                'intermediate_subformulas' => 'none',
                'row_order'                => 'canonical',
            ],
            'scoring' => [
                'radio_weight'       => 50,
                'table_weight'       => 50,
                'wrong_radio_policy' => 'strict',
            ],
            'config' => [
                'formula_1'                  => 'A ∨ B',
                'formula_2'                  => 'B ∨ A',
                'require_table_justification' => false,
            ],
        ];
    }

    private function valid_problem_classify(): array {
        return [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'classify',
            'ui'             => [
                'intermediate_subformulas' => 'auto',
                'row_order'                => 'canonical',
            ],
            'scoring' => [
                'radio_weight'       => 100,
                'table_weight'       => 0,
                'wrong_radio_policy' => 'proportional',
            ],
            'config' => [
                'formula'                    => 'A',
                'require_table_justification' => false,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Test 1 — valid complete problem passes.
    // -------------------------------------------------------------------------
    public function test_valid_problem_complete_passes(): void {
        // Arrange.
        $data = $this->valid_problem_complete();

        // Act.
        $result = $this->loader->validate($data, 'complete', 'problem');

        // Assert.
        $this->assertTrue($result->ok, implode('; ', $result->errors));
        $this->assertEmpty($result->errors);
    }

    // -------------------------------------------------------------------------
    // Test 2 — valid equivalence problem passes.
    // -------------------------------------------------------------------------
    public function test_valid_problem_equivalence_passes(): void {
        // Arrange.
        $data = $this->valid_problem_equivalence();

        // Act.
        $result = $this->loader->validate($data, 'equivalence', 'problem');

        // Assert.
        $this->assertTrue($result->ok, implode('; ', $result->errors));
    }

    // -------------------------------------------------------------------------
    // Test 3 — valid classify problem passes.
    // -------------------------------------------------------------------------
    public function test_valid_problem_classify_passes(): void {
        // Arrange.
        $data = $this->valid_problem_classify();

        // Act.
        $result = $this->loader->validate($data, 'classify', 'problem');

        // Assert.
        $this->assertTrue($result->ok, implode('; ', $result->errors));
    }

    // -------------------------------------------------------------------------
    // Test 4 — missing required field → validation fails.
    // -------------------------------------------------------------------------
    public function test_missing_required_field_fails(): void {
        // Arrange: remove 'config' from a valid complete problem.
        $data = $this->valid_problem_complete();
        unset($data['config']);

        // Act.
        $result = $this->loader->validate($data, 'complete', 'problem');

        // Assert.
        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->errors);

        // Error message should mention 'config'.
        $found = array_filter(
            $result->errors,
            static fn(string $e): bool => str_contains($e, 'config')
        );
        $this->assertNotEmpty($found, 'Expected an error mentioning "config". Got: ' . implode('; ', $result->errors));
    }

    // -------------------------------------------------------------------------
    // Test 5 — additional property at top level → validation fails.
    // -------------------------------------------------------------------------
    public function test_additional_property_fails(): void {
        // Arrange: add an unknown top-level key.
        $data = $this->valid_problem_complete();
        $data['unknown_field'] = 'should not be here';

        // Act.
        $result = $this->loader->validate($data, 'complete', 'problem');

        // Assert.
        $this->assertFalse($result->ok);
        $found = array_filter(
            $result->errors,
            static fn(string $e): bool => str_contains($e, 'unknown_field')
        );
        $this->assertNotEmpty($found, 'Expected error for "unknown_field". Got: ' . implode('; ', $result->errors));
    }

    // -------------------------------------------------------------------------
    // Test 6 — invalid enum value → validation fails.
    // -------------------------------------------------------------------------
    public function test_invalid_enum_value_fails(): void {
        // Arrange: set intermediate_subformulas to an invalid value.
        $data = $this->valid_problem_complete();
        $data['ui']['intermediate_subformulas'] = 'invalid_mode';

        // Act.
        $result = $this->loader->validate($data, 'complete', 'problem');

        // Assert.
        $this->assertFalse($result->ok);
        $found = array_filter(
            $result->errors,
            static fn(string $e): bool => str_contains($e, 'intermediate_subformulas')
        );
        $this->assertNotEmpty($found, 'Expected error for intermediate_subformulas. Got: ' . implode('; ', $result->errors));
    }

    // -------------------------------------------------------------------------
    // Test 7 — valid submission validates.
    // -------------------------------------------------------------------------
    public function test_submission_validates(): void {
        // Arrange: valid complete submission.
        $data = [
            'tool'           => 'truth_table',
            'schema_version' => 1,
            'type'           => 'complete',
            'radio_answer'   => null,
            'table'          => [
                'columns' => ['A', 'B', 'final'],
                'rows'    => [
                    ['vars' => ['A' => 'F', 'B' => 'F'], 'values' => ['F', 'F', 'F']],
                    ['vars' => ['A' => 'F', 'B' => 'V'], 'values' => ['F', 'V', 'F']],
                    ['vars' => ['A' => 'V', 'B' => 'F'], 'values' => ['V', 'F', 'F']],
                    ['vars' => ['A' => 'V', 'B' => 'V'], 'values' => ['V', 'V', 'V']],
                ],
            ],
        ];

        // Act.
        $result = $this->loader->validate($data, 'complete', 'submission');

        // Assert.
        $this->assertTrue($result->ok, implode('; ', $result->errors));
    }
}
