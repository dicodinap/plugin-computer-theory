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
 * Unit tests for tool_descriptor.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_graphitoubb\tool_descriptor;

/**
 * Tests for the tool_descriptor value object.
 *
 * @covers \local_graphitoubb\tool_descriptor
 */
final class tool_descriptor_test extends \basic_testcase {
    /**
     * All four fields are stored and returned verbatim.
     */
    public function test_constructor_stores_all_fields(): void {
        $descriptor = new tool_descriptor('afd', 'AFD Tool', '1.0.0', ['simulate', 'validate']);

        $this->assertSame('afd', $descriptor->get_id());
        $this->assertSame('AFD Tool', $descriptor->get_name());
        $this->assertSame('1.0.0', $descriptor->get_version());
        $this->assertSame(['simulate', 'validate'], $descriptor->get_capabilities());
    }

    /**
     * Empty capabilities array is valid (tool with no declared capabilities).
     */
    public function test_empty_capabilities_is_valid(): void {
        $descriptor = new tool_descriptor('noop', 'Noop', '0.1.0', []);

        $this->assertSame([], $descriptor->get_capabilities());
    }

    /**
     * Two instances constructed with different arguments are independent.
     */
    public function test_instances_are_independent(): void {
        $a = new tool_descriptor('tool-a', 'Tool A', '1.0.0', ['x']);
        $b = new tool_descriptor('tool-b', 'Tool B', '2.0.0', ['y', 'z']);

        $this->assertSame('tool-a', $a->get_id());
        $this->assertSame('tool-b', $b->get_id());
        $this->assertNotSame($a->get_capabilities(), $b->get_capabilities());
    }

    /**
     * Class is declared final — cannot be extended.
     */
    public function test_class_is_final(): void {
        $reflection = new \ReflectionClass(tool_descriptor::class);
        $this->assertTrue($reflection->isFinal());
    }

    /**
     * get_capabilities() returns a copy: mutating the returned array does not
     * affect subsequent calls (PHP array copy-on-write semantics).
     */
    public function test_capabilities_copy_on_write(): void {
        $descriptor = new tool_descriptor('t', 'T', '1.0.0', ['a', 'b']);
        $caps = $descriptor->get_capabilities();
        $caps[] = 'injected';

        $this->assertSame(['a', 'b'], $descriptor->get_capabilities());
    }
}
