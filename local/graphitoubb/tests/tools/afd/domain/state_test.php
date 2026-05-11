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
 * Unit tests for the AFD state value object.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use local_graphitoubb\tools\afd\domain\state;

/**
 * Tests for the state value object.
 *
 * @covers \local_graphitoubb\tools\afd\domain\state
 */
final class state_test extends \basic_testcase {
    public function test_constructor_stores_id(): void {
        $s = new state('q0');
        $this->assertSame('q0', $s->get_id());
    }

    public function test_label_defaults_to_id_when_not_provided(): void {
        $s = new state('q0');
        $this->assertSame('q0', $s->get_label());
    }

    public function test_explicit_label_is_stored(): void {
        $s = new state('q0', 'Start');
        $this->assertSame('Start', $s->get_label());
    }

    public function test_empty_string_label_falls_back_to_id(): void {
        $s = new state('q1', '');
        $this->assertSame('q1', $s->get_label());
    }

    public function test_class_is_final(): void {
        $r = new \ReflectionClass(state::class);
        $this->assertTrue($r->isFinal());
    }

    public function test_two_states_are_independent(): void {
        $a = new state('q0', 'A');
        $b = new state('q1', 'B');
        $this->assertNotSame($a->get_id(), $b->get_id());
        $this->assertNotSame($a->get_label(), $b->get_label());
    }
}
