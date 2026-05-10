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
 * Unit tests for validation_result.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use local_graphitoubb\validation_result;

/**
 * Tests for validation_result value object.
 *
 * @covers \local_graphitoubb\validation_result
 */
final class validation_result_test extends \basic_testcase {
    public function test_pass_is_ok(): void {
        $r = validation_result::pass();
        $this->assertTrue($r->ok);
    }

    public function test_pass_has_no_errors(): void {
        $r = validation_result::pass();
        $this->assertSame([], $r->errors);
    }

    public function test_fail_is_not_ok(): void {
        $r = validation_result::fail(['e1']);
        $this->assertFalse($r->ok);
    }

    public function test_fail_preserves_errors(): void {
        $errors = ['max_states: 65 > 64', 'max_alphabet: 20 > 16'];
        $r = validation_result::fail($errors);
        $this->assertSame($errors, $r->errors);
    }

    public function test_fail_with_empty_errors(): void {
        $r = validation_result::fail([]);
        $this->assertFalse($r->ok);
        $this->assertSame([], $r->errors);
    }

    public function test_ok_property_is_readonly(): void {
        $ref = new \ReflectionProperty(validation_result::class, 'ok');
        $this->assertTrue($ref->isReadOnly());
    }

    public function test_errors_property_is_readonly(): void {
        $ref = new \ReflectionProperty(validation_result::class, 'errors');
        $this->assertTrue($ref->isReadOnly());
    }

    public function test_class_is_final(): void {
        $ref = new \ReflectionClass(validation_result::class);
        $this->assertTrue($ref->isFinal());
    }
}
