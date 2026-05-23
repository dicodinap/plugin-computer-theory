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
 * Plugin bootstrap — registers all GraphitoUBB tools into the registry.
 *
 * @package    local_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_graphitoubb;

use local_graphitoubb\tools\afd\afd_tool;
use local_graphitoubb\tools\truth_table\truth_table_tool;

/**
 * Entry point for tool registration. Called once at plugin init.
 */
class bootstrap {
    /**
     * Register all available tools into the shared registry.
     *
     * @return void
     */
    public static function init(): void {
        // AfdTool registration added in S3 (classes/tools/afd/afd_tool.php).
        // Defensive registration is handled by mod_graphitoubb\tool_factory via register_default_tools().
    }

    /**
     * Register the default tool set (AfdTool).
     *
     * Extracted from init() so consumers (e.g. mod_graphitoubb\tool_factory)
     * can call it defensively when the registry is empty due to load order.
     *
     * @return void
     */
    public static function register_default_tools(): void {
        $registry = tool_registry::instance();
        $registry->register(new afd_tool());
        $registry->register(new truth_table_tool());
    }
}
