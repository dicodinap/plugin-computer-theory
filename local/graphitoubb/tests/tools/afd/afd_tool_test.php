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
 * Unit tests for afd_tool.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use local_graphitoubb\tool_descriptor;
use local_graphitoubb\tool_interface;
use local_graphitoubb\tools\afd\afd_tool;
use local_graphitoubb\tools\afd\domain\validator;
use local_graphitoubb\validation_result;

/**
 * Tests for afd_tool.
 *
 * @covers \local_graphitoubb\tools\afd\afd_tool
 */
final class afd_tool_test extends \basic_testcase {
    public function test_implements_tool_interface(): void {
        $r = new \ReflectionClass(afd_tool::class);
        $this->assertTrue($r->implementsInterface(tool_interface::class));
    }

    public function test_descriptor_returns_tool_descriptor(): void {
        $d = afd_tool::descriptor();
        $this->assertInstanceOf(tool_descriptor::class, $d);
    }

    public function test_descriptor_id_is_afd(): void {
        $this->assertSame('afd', afd_tool::descriptor()->get_id());
    }

    public function test_descriptor_has_expected_capabilities(): void {
        $caps = afd_tool::descriptor()->get_capabilities();
        foreach (['edit', 'simulate', 'snapshot', 'wordbank'] as $cap) {
            $this->assertContains($cap, $caps, "Missing capability: $cap");
        }
    }

    public function test_descriptor_version_is_non_empty(): void {
        $this->assertNotEmpty(afd_tool::descriptor()->get_version());
    }

    public function test_descriptor_name_is_non_empty(): void {
        $this->assertNotEmpty(afd_tool::descriptor()->get_name());
    }

    public function test_class_is_final(): void {
        $r = new \ReflectionClass(afd_tool::class);
        $this->assertTrue($r->isFinal());
    }

    public function test_validate_returns_pass_for_minimal_valid_payload(): void {
        $tool = new afd_tool();
        $result = $tool->validate(['states' => [], 'alphabet' => [], 'transitions' => []]);
        $this->assertInstanceOf(validation_result::class, $result);
        $this->assertTrue($result->ok);
    }

    public function test_validate_returns_fail_when_states_exceed_max(): void {
        $tool = new afd_tool();
        $states = array_fill(0, validator::MAX_STATES + 1, ['id' => 'q0', 'label' => 'q0']);
        $result = $tool->validate(['states' => $states, 'alphabet' => [], 'transitions' => []]);
        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->errors);
    }

    public function test_validate_returns_fail_when_required_field_missing(): void {
        $tool = new afd_tool();
        $result = $tool->validate([]);
        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->errors);
    }

    public function test_validate_returns_fail_when_alphabet_exceeds_max(): void {
        $tool = new afd_tool();
        $alphabet = array_map('strval', range(0, validator::MAX_ALPHABET));
        $result = $tool->validate(['states' => [], 'alphabet' => $alphabet, 'transitions' => []]);
        $this->assertFalse($result->ok);
    }

    public function test_serialize_returns_array_with_required_keys(): void {
        $tool = new afd_tool();
        $result = $tool->serialize([
            'states' => [], 'alphabet' => [], 'transitions' => [], 'start' => null, 'finals' => [],
        ]);
        foreach (['schema_version', 'states', 'alphabet', 'transitions', 'start', 'finals'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }

    public function test_serialize_preserves_payload_fields(): void {
        $tool = new afd_tool();
        $states = [['id' => 'q0', 'label' => 'q0']];
        $result = $tool->serialize([
            'states' => $states, 'alphabet' => ['a'], 'transitions' => [], 'start' => 'q0', 'finals' => ['q0'],
        ]);
        $this->assertSame($states, $result['states']);
        $this->assertSame(['a'], $result['alphabet']);
        $this->assertSame('q0', $result['start']);
    }

    public function test_render_editor_returns_template_and_context_keys(): void {
        $tool = new afd_tool();
        $result = $tool->render_editor();
        $this->assertArrayHasKey('template', $result);
        $this->assertArrayHasKey('context', $result);
        $this->assertIsString($result['template']);
        $this->assertIsArray($result['context']);
    }
}
