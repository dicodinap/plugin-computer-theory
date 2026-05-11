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
 * Test data generator for mod_graphitoubb.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generates instances of mod_graphitoubb for unit and behat tests.
 */
class mod_graphitoubb_generator extends testing_module_generator {
    /**
     * Create a graphitoubb activity instance.
     *
     * @param array|stdClass|null $record Instance record overrides.
     * @param array|null $options Module generator options.
     * @return stdClass The created instance.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) ($record ?? []);

        if (!isset($record->intro)) {
            $record->intro = '';
        }
        if (!isset($record->introformat)) {
            $record->introformat = FORMAT_HTML;
        }

        return parent::create_instance($record, (array) $options);
    }
}
