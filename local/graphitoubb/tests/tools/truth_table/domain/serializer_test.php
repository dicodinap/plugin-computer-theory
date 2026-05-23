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
 * Unit tests for the problem serializer.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

namespace local_graphitoubb\tools\truth_table\domain;

/**
 * 3 tests: encode/decode roundtrip, stable hash, hash differentiates content.
 *
 * @coversNothing
 */
final class serializer_test extends \basic_testcase {
    /** @var serializer */
    private serializer $serializer;

    protected function setUp(): void {
        parent::setUp();
        $this->serializer = new serializer();
    }

    // -------------------------------------------------------------------------
    // Test 1 — encode() / decode() roundtrip preserves the original array.
    // -------------------------------------------------------------------------
    public function test_encode_decode_roundtrip(): void {
        // Arrange.
        $problem = [
            'schema_version' => 1,
            'tool'           => 'truth_table',
            'type'           => 'complete',
            'config'         => ['formula' => 'A ∧ B'],
        ];

        // Act.
        $json   = $this->serializer->encode($problem);
        $result = $this->serializer->decode($json);

        // Assert.
        $this->assertSame($problem, $result);
    }

    // -------------------------------------------------------------------------
    // Test 2 — hash() is stable across different key insertion orders.
    // -------------------------------------------------------------------------
    public function test_hash_stable_across_key_order(): void {
        // Arrange — same content, different insertion order.
        $problem_a = [
            'tool'   => 'truth_table',
            'type'   => 'complete',
            'config' => ['formula' => 'A'],
        ];
        $problem_b = [
            'config' => ['formula' => 'A'],
            'type'   => 'complete',
            'tool'   => 'truth_table',
        ];

        // Act.
        $hash_a = $this->serializer->hash($problem_a);
        $hash_b = $this->serializer->hash($problem_b);

        // Assert: same content → same hash regardless of key order.
        $this->assertSame($hash_a, $hash_b, 'Hash must be stable across key insertion order.');
    }

    // -------------------------------------------------------------------------
    // Test 3 — hash() differs when content differs.
    // -------------------------------------------------------------------------
    public function test_hash_differs_when_content_differs(): void {
        // Arrange.
        $problem_a = ['tool' => 'truth_table', 'type' => 'complete', 'config' => ['formula' => 'A']];
        $problem_b = ['tool' => 'truth_table', 'type' => 'complete', 'config' => ['formula' => 'B']];

        // Act.
        $hash_a = $this->serializer->hash($problem_a);
        $hash_b = $this->serializer->hash($problem_b);

        // Assert.
        $this->assertNotSame($hash_a, $hash_b, 'Hash must differ when content differs.');
    }
}
