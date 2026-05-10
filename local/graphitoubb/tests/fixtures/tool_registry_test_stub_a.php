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
 * Test fixture: stub tool A (id stub-a).
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_graphitoubb\tool_descriptor;
use local_graphitoubb\tool_interface;
use local_graphitoubb\validation_result;

/**
 * Stub tool A — id 'stub-a', single capability 'cap-x'.
 */
class tool_registry_test_stub_a implements tool_interface {
    /**
     * Return the descriptor for stub tool A.
     *
     * @return tool_descriptor
     */
    public static function descriptor(): tool_descriptor {
        return new tool_descriptor('stub-a', 'Stub A', '1.0.0', ['cap-x']);
    }

    /**
     * Stub: always passes validation.
     *
     * @param array $payload
     * @return validation_result
     */
    public function validate(array $payload): validation_result {
        return validation_result::pass();
    }

    /**
     * Stub: returns payload unchanged.
     *
     * @param array $automaton
     * @return array
     */
    public function serialize(array $automaton): array {
        return $automaton;
    }

    /**
     * Stub: returns empty editor context.
     *
     * @return array{template: string, context: array}
     */
    public function render_editor(): array {
        return ['template' => '', 'context' => []];
    }
}
