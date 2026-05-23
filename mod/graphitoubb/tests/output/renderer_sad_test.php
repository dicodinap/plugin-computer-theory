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

declare(strict_types=1);

namespace mod_graphitoubb\output;

use advanced_testcase;

/**
 * Sad-path tests for the truth_table editor renderer: malformed payloads
 * must degrade to an empty skeleton without throwing.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_graphitoubb\output\renderer
 */
final class renderer_sad_test extends advanced_testcase {
    /** @var renderer */
    private renderer $renderer;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        global $PAGE;
        $PAGE->set_url('/');
        $PAGE->set_context(\context_system::instance());
        $this->renderer = $PAGE->get_renderer('mod_graphitoubb');
    }

    /**
     * Build a fake problem row with the given payload encoded as JSON.
     *
     * @param array|string $payload
     */
    private function fake_problem(array|string $payload): \stdClass {
        return (object) [
            'id'      => 1,
            'payload' => is_string($payload) ? $payload : json_encode($payload),
        ];
    }

    public function test_complete_unparseable_formula_renders_empty_skeleton(): void {
        $payload = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'complete',
            'ui'   => ['intermediate_subformulas' => 'auto'],
            'config' => ['formula' => '(A'], // unclosed paren.
        ];
        $html = $this->renderer->render_truth_table_editor(1, 1, $this->fake_problem($payload));
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        // Raw formula should appear (canonical fallback to user-typed string).
        $this->assertStringContainsString('(A', $html);
    }

    public function test_complete_lex_error_in_formula_renders_without_throwing(): void {
        $payload = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'complete',
            'ui'   => ['intermediate_subformulas' => 'auto'],
            'config' => ['formula' => 'lowercase'],
        ];
        $html = $this->renderer->render_truth_table_editor(1, 1, $this->fake_problem($payload));
        $this->assertIsString($html);
    }

    public function test_equivalence_one_formula_invalid_renders_fallback(): void {
        $payload = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'equivalence',
            'ui'   => ['intermediate_subformulas' => 'auto'],
            'config' => [
                'formula_1' => 'A ∨ B',
                'formula_2' => '(B', // unclosed paren.
                'expected_equivalent' => null,
                'require_table_justification' => true,
            ],
            'scoring' => ['radio_weight' => 50, 'table_weight' => 50, 'wrong_radio_policy' => 'strict'],
        ];
        $html = $this->renderer->render_truth_table_editor(1, 1, $this->fake_problem($payload));
        $this->assertIsString($html);
        $this->assertStringContainsString('A ∨ B / (B', $html); // fallback canonical = "f1 / f2".
    }

    public function test_completely_empty_payload_renders_without_throwing(): void {
        $html = $this->renderer->render_truth_table_editor(1, 1, $this->fake_problem(''));
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    public function test_payload_missing_config_renders_without_throwing(): void {
        $payload = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'complete',
            'ui'   => ['intermediate_subformulas' => 'auto'],
            // no config.
        ];
        $html = $this->renderer->render_truth_table_editor(1, 1, $this->fake_problem($payload));
        $this->assertIsString($html);
    }

    public function test_unknown_type_in_payload_renders_without_throwing(): void {
        $payload = [
            'tool' => 'truth_table', 'schema_version' => 1, 'type' => 'frobnicate',
            'ui'   => ['intermediate_subformulas' => 'auto'],
            'config' => ['formula' => 'A'],
        ];
        $html = $this->renderer->render_truth_table_editor(1, 1, $this->fake_problem($payload));
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }
}
