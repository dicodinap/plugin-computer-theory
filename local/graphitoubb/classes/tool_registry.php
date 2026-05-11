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
 * Tool registry — runtime singleton for GraphitoUBB tools.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_graphitoubb;

/**
 * Singleton registry for tools. No DB persistence in v1.
 *
 * Alternatives considered: DB-backed registry (adds latency, overkill for in-process
 * tool lookup), services.php (Moodle DI — not available for local plugins in 4.5),
 * hooks (async, wrong lifecycle). Singleton chosen for simplicity and test isolation
 * via reset_instance().
 */
class tool_registry {
    /** @var self|null */
    private static ?self $instance = null;

    /** @var tool_interface[] Keyed by tool id. */
    private array $tools = [];

    /**
     * Private — use instance().
     */
    private function __construct() {
    }

    /**
     * Returns the shared registry instance.
     *
     * @return self
     */
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a tool. Re-registering the same id overwrites the previous entry
     * (last registration wins — supports hot-reload in development).
     *
     * @param tool_interface $tool
     * @return void
     */
    public function register(tool_interface $tool): void {
        $id = $tool::descriptor()->get_id();
        $this->tools[$id] = $tool;
    }

    /**
     * Retrieve a tool by id.
     *
     * @param  string              $id
     * @return tool_interface|null Null when the id is not registered.
     */
    public function get(string $id): ?tool_interface {
        return $this->tools[$id] ?? null;
    }

    /**
     * Return all registered tools as an indexed array.
     *
     * @return tool_interface[]
     */
    public function all(): array {
        return array_values($this->tools);
    }

    /**
     * Destroy the singleton instance. For unit tests only — do not call in production code.
     *
     * @return void
     */
    public static function reset_instance(): void {
        self::$instance = null;
    }
}
