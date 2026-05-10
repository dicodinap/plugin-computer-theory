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
 * Tool interface — contract for all GraphitoUBB tools.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_graphitoubb;

/**
 * Every tool registered in tool_registry must implement this interface.
 */
interface tool_interface {
    /**
     * Returns the descriptor (metadata) for this tool class.
     *
     * Deviation from spec §1.1: descriptor() added here because the registry
     * requires metadata (id, capabilities) at registration time without
     * instantiating domain objects. Spec gap — decision recorded in apply-progress.
     *
     * @return tool_descriptor
     */
    public static function descriptor(): tool_descriptor;

    /**
     * Validate a raw payload (canonical AFD shape from client) against D-A bounds.
     *
     * @param  array $payload Canonical AFD array: {states, alphabet, transitions, ...}
     * @return validation_result
     */
    public function validate(array $payload): validation_result;

    /**
     * Normalise a canonical AFD array into the persistence-ready shape.
     *
     * @param  array $automaton Canonical AFD array.
     * @return array
     */
    public function serialize(array $automaton): array;

    /**
     * Provide the Mustache template name and render context for the editor surface.
     *
     * @return array{template: string, context: array}
     */
    public function render_editor(): array;
}
