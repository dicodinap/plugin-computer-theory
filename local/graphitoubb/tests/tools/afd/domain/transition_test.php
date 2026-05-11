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
 * Unit tests for the AFD transition value object.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use local_graphitoubb\tools\afd\domain\transition;

/**
 * Tests for transition value object.
 *
 * @covers \local_graphitoubb\tools\afd\domain\transition
 */
final class transition_test extends \basic_testcase {
    public function test_constructor_stores_all_fields(): void {
        $t = new transition('q0', 'a', 'q1');
        $this->assertSame('q0', $t->get_from());
        $this->assertSame('a', $t->get_symbol());
        $this->assertSame('q1', $t->get_to());
    }

    public function test_self_loop_is_valid(): void {
        $t = new transition('q0', 'b', 'q0');
        $this->assertSame('q0', $t->get_from());
        $this->assertSame('q0', $t->get_to());
    }

    public function test_instances_are_independent(): void {
        $a = new transition('q0', 'a', 'q1');
        $b = new transition('q1', 'b', 'q2');
        $this->assertNotSame($a->get_from(), $b->get_from());
        $this->assertNotSame($a->get_symbol(), $b->get_symbol());
    }

    public function test_class_is_final(): void {
        $r = new \ReflectionClass(transition::class);
        $this->assertTrue($r->isFinal());
    }
}
