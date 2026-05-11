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
 * Privacy provider for local_graphitoubb.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_graphitoubb\privacy;

/**
 * Null privacy provider — local_graphitoubb v1 does not store personal data.
 *
 * The plugin is a runtime registry for graphitoubb tools. Persistence of student
 * artifacts is delegated to host plugins (mod_graphitoubb, qtype_graphitoubb).
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Reason this plugin stores no personal data.
     *
     * @return string lang string identifier.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
