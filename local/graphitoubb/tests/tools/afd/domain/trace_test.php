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
 * Unit tests for the AFD trace value object.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

use local_graphitoubb\tools\afd\domain\trace;

/**
 * Tests for trace value object.
 *
 * @covers \local_graphitoubb\tools\afd\domain\trace
 */
final class trace_test extends \basic_testcase {
    /**
     * Build a single execution step array.
     *
     * @param string $current Current state id.
     * @param string $symbol  Symbol consumed.
     * @param string $next    Next state id.
     * @return array
     */
    private function make_step(string $current, string $symbol, string $next): array {
        return ['current' => $current, 'symbol' => $symbol, 'next' => $next];
    }

    public function test_is_accepted_true(): void {
        $t = new trace([], true);
        $this->assertTrue($t->is_accepted());
    }

    public function test_is_accepted_false(): void {
        $t = new trace([], false);
        $this->assertFalse($t->is_accepted());
    }

    public function test_get_steps_returns_steps(): void {
        $steps = [
            $this->make_step('q0', 'a', 'q1'),
            $this->make_step('q1', 'b', 'q1'),
        ];
        $t = new trace($steps, true);
        $this->assertCount(2, $t->get_steps());
        $this->assertSame('q0', $t->get_steps()[0]['current']);
        $this->assertSame('a', $t->get_steps()[0]['symbol']);
        $this->assertSame('q1', $t->get_steps()[0]['next']);
    }

    public function test_empty_trace_has_no_steps(): void {
        $t = new trace([], false);
        $this->assertSame([], $t->get_steps());
    }

    public function test_get_final_state_returns_last_next(): void {
        $steps = [
            $this->make_step('q0', 'a', 'q1'),
            $this->make_step('q1', 'b', 'q2'),
        ];
        $t = new trace($steps, true);
        $this->assertSame('q2', $t->get_final_state());
    }

    public function test_get_final_state_null_when_no_steps(): void {
        $t = new trace([], false);
        $this->assertNull($t->get_final_state());
    }

    public function test_class_is_final(): void {
        $r = new \ReflectionClass(trace::class);
        $this->assertTrue($r->isFinal());
    }
}
