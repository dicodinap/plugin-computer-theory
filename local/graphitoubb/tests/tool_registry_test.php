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
 * Unit tests for tool_registry.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/tool_registry_test_stub_a.php');
require_once(__DIR__ . '/fixtures/tool_registry_test_stub_b.php');
require_once(__DIR__ . '/fixtures/tool_registry_test_stub_a_v2.php');

use local_graphitoubb\tool_registry;

/**
 * Tests for tool_registry singleton.
 *
 * @covers \local_graphitoubb\tool_registry
 */
final class tool_registry_test extends \basic_testcase {
    /**
     * Reset singleton before each test to prevent state leakage.
     */
    protected function setUp(): void {
        parent::setUp();
        tool_registry::reset_instance();
    }

    /**
     * Clean up singleton after each test.
     */
    protected function tearDown(): void {
        tool_registry::reset_instance();
        parent::tearDown();
    }

    /**
     * instance() always returns the same object.
     */
    public function test_instance_is_singleton(): void {
        $a = tool_registry::instance();
        $b = tool_registry::instance();
        $this->assertSame($a, $b);
    }

    /**
     * A registered tool can be retrieved by its id.
     */
    public function test_register_and_get(): void {
        $registry = tool_registry::instance();
        $tool = new tool_registry_test_stub_a();
        $registry->register($tool);

        $this->assertSame($tool, $registry->get('stub-a'));
    }

    /**
     * get() returns null for an id that was never registered.
     */
    public function test_get_returns_null_for_unknown_id(): void {
        $this->assertNull(tool_registry::instance()->get('does-not-exist'));
    }

    /**
     * all() returns every registered tool as an indexed array.
     */
    public function test_all_returns_all_tools(): void {
        $registry = tool_registry::instance();
        $a = new tool_registry_test_stub_a();
        $b = new tool_registry_test_stub_b();
        $registry->register($a);
        $registry->register($b);

        $all = $registry->all();
        $this->assertCount(2, $all);
        $this->assertContains($a, $all);
        $this->assertContains($b, $all);
    }

    /**
     * all() returns an empty array when nothing is registered.
     */
    public function test_all_empty_when_nothing_registered(): void {
        $this->assertSame([], tool_registry::instance()->all());
    }

    /**
     * Re-registering the same id replaces the previous entry (last-wins).
     * Rationale: supports hot-reload in development without forcing callers
     * to deregister first.
     */
    public function test_register_same_id_overwrites(): void {
        $registry = tool_registry::instance();
        $v1 = new tool_registry_test_stub_a();
        $v2 = new tool_registry_test_stub_a_v2();

        $registry->register($v1);
        $registry->register($v2);

        $this->assertSame($v2, $registry->get('stub-a'));
        $this->assertCount(1, $registry->all());
    }

    /**
     * reset_instance() destroys the singleton so the next instance() call
     * returns a fresh, empty registry.
     */
    public function test_reset_instance_clears_state(): void {
        $registry = tool_registry::instance();
        $registry->register(new tool_registry_test_stub_a());
        $this->assertCount(1, $registry->all());

        tool_registry::reset_instance();

        $fresh = tool_registry::instance();
        $this->assertCount(0, $fresh->all());
        $this->assertNotSame($registry, $fresh);
    }
}
