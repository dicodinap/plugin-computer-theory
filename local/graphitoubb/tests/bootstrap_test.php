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
 * Smoke tests for bootstrap.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_graphitoubb\bootstrap;
use local_graphitoubb\tool_registry;

/**
 * Verifies bootstrap::init() runs without error and leaves the registry
 * in the expected state for S2 (no tools registered yet).
 *
 * @covers \local_graphitoubb\bootstrap
 */
final class bootstrap_test extends \basic_testcase {
    /**
     * Reset the registry before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        tool_registry::reset_instance();
    }

    /**
     * Clean up registry after each test.
     */
    protected function tearDown(): void {
        tool_registry::reset_instance();
        parent::tearDown();
    }

    /**
     * init() completes without throwing and leaves registry empty (S2 — no tools yet).
     */
    public function test_init_runs_without_error(): void {
        bootstrap::init();
        $this->assertSame([], tool_registry::instance()->all());
    }

    /**
     * init() is idempotent — calling it twice does not duplicate registrations.
     */
    public function test_init_is_idempotent(): void {
        bootstrap::init();
        bootstrap::init();
        $this->assertSame([], tool_registry::instance()->all());
    }
}
