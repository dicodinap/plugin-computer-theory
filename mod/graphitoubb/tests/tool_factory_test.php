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

use local_graphitoubb\tool_registry;
use mod_graphitoubb\tool_factory;

/**
 * Tests for mod_graphitoubb\tool_factory.
 *
 * @package    mod_graphitoubb
 * @covers     \mod_graphitoubb\tool_factory
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_factory_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        tool_registry::reset_instance();
    }

    protected function tearDown(): void {
        tool_registry::reset_instance();
        parent::tearDown();
    }

    public function test_get_afd_tool_returns_expected_descriptor(): void {
        $factory    = new tool_factory();
        $tool       = $factory->get_afd_tool();
        $descriptor = $tool::descriptor();

        $this->assertSame('afd', $descriptor->get_id());
        $this->assertSame('Deterministic Finite Automaton', $descriptor->get_name());
        $this->assertSame('1.0.0', $descriptor->get_version());
    }

    public function test_get_afd_tool_bootstraps_empty_registry(): void {
        $registry = tool_registry::instance();
        $this->assertEmpty($registry->all());

        $factory = new tool_factory();
        $tool    = $factory->get_afd_tool();

        $this->assertNotNull($tool);
        $this->assertNotEmpty($registry->all());
    }

    public function test_get_afd_tool_uses_already_registered_tool(): void {
        // Pre-populate registry — factory must not double-register.
        $bootstrap = new \local_graphitoubb\bootstrap();
        \local_graphitoubb\bootstrap::register_default_tools();

        $registry = tool_registry::instance();
        // afd, grafo, arbol, truth_table, karnaugh, relations.
        $this->assertCount(6, $registry->all());

        $factory = new tool_factory();
        $factory->get_afd_tool();

        // Still exactly the same tools after factory call (no double-registration).
        $this->assertCount(6, $registry->all());
    }
}
