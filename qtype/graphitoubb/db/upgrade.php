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
 * Upgrade script for qtype_graphitoubb.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin database schema.
 *
 * Empty for the initial release; structure is ready for future savepoints.
 *
 * @param  int  $oldversion The version the plugin is upgrading from.
 * @return bool
 */
function xmldb_qtype_graphitoubb_upgrade(int $oldversion): bool {
    // Future migrations: add savepoints here.
    // Example:
    // if ($oldversion < 2026052000) {
    // alter table...
    // upgrade_plugin_savepoint(true, 2026052000, 'qtype', 'graphitoubb');
    // }

    // Seed the curated truth_table presets into the system Question Bank on existing
    // installs. Idempotent (guarded by a config version) and defensive (never throws).
    if ($oldversion < 2026062904) {
        \qtype_graphitoubb\catalog_seeder::seed(2026062904);
        upgrade_plugin_savepoint(true, 2026062904, 'qtype', 'graphitoubb');
    }

    return true;
}
