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
 * Contract-shape tests for tool_interface.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_graphitoubb\tool_descriptor;
use local_graphitoubb\tool_interface;

/**
 * Verifies tool_interface declares the correct contract via ReflectionClass.
 *
 * @covers \local_graphitoubb\tool_interface
 */
final class tool_interface_test extends \basic_testcase {
    /**
     * The symbol is an interface, not a class.
     */
    public function test_is_interface(): void {
        $this->assertTrue(interface_exists(tool_interface::class));
        $reflection = new \ReflectionClass(tool_interface::class);
        $this->assertTrue($reflection->isInterface());
    }

    /**
     * descriptor() is declared on the interface.
     */
    public function test_descriptor_method_exists(): void {
        $reflection = new \ReflectionClass(tool_interface::class);
        $this->assertTrue($reflection->hasMethod('descriptor'));
    }

    /**
     * descriptor() must be public and static.
     */
    public function test_descriptor_is_public_static(): void {
        $method = (new \ReflectionClass(tool_interface::class))->getMethod('descriptor');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * descriptor() return type is tool_descriptor.
     */
    public function test_descriptor_return_type(): void {
        $method = (new \ReflectionClass(tool_interface::class))->getMethod('descriptor');
        $returntype = $method->getReturnType();
        $this->assertNotNull($returntype);
        $this->assertSame(tool_descriptor::class, $returntype->getName());
    }

    /**
     * validate() is declared on the interface.
     */
    public function test_validate_method_exists(): void {
        $reflection = new \ReflectionClass(tool_interface::class);
        $this->assertTrue($reflection->hasMethod('validate'));
    }

    /**
     * validate() is public and not static.
     */
    public function test_validate_is_public_instance(): void {
        $method = (new \ReflectionClass(tool_interface::class))->getMethod('validate');
        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isStatic());
    }

    /**
     * serialize() is declared on the interface.
     */
    public function test_serialize_method_exists(): void {
        $reflection = new \ReflectionClass(tool_interface::class);
        $this->assertTrue($reflection->hasMethod('serialize'));
    }

    /**
     * render_editor() is declared on the interface.
     */
    public function test_render_editor_method_exists(): void {
        $reflection = new \ReflectionClass(tool_interface::class);
        $this->assertTrue($reflection->hasMethod('render_editor'));
    }
}
