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
 * Tool descriptor value object for local_graphitoubb.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_graphitoubb;

/**
 * Immutable metadata about a graphitoubb tool.
 *
 * Each tool implementing tool_interface returns a descriptor from descriptor()
 * so the registry can introspect available tools without instantiating them.
 */
final class tool_descriptor {
    /** @var string Stable identifier (e.g. 'afd'). */
    private string $id;

    /** @var string Human-readable name. */
    private string $name;

    /** @var string Tool version (semver-ish). */
    private string $version;

    /** @var string[] Capability identifiers exposed by this tool. */
    private array $capabilities;

    /**
     * Build a tool descriptor.
     *
     * @param string   $id Stable tool identifier.
     * @param string   $name Human-readable name.
     * @param string   $version Tool version string.
     * @param string[] $capabilities Capability identifiers.
     */
    public function __construct(string $id, string $name, string $version, array $capabilities) {
        $this->id = $id;
        $this->name = $name;
        $this->version = $version;
        $this->capabilities = $capabilities;
    }

    /**
     * Return the tool identifier.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Return the tool name.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Return the tool version string.
     *
     * @return string
     */
    public function get_version(): string {
        return $this->version;
    }

    /**
     * Return the list of capability identifiers this tool exposes.
     *
     * @return string[]
     */
    public function get_capabilities(): array {
        return $this->capabilities;
    }
}
