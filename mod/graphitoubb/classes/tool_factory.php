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

namespace mod_graphitoubb;

use local_graphitoubb\bootstrap;
use local_graphitoubb\tool_interface;
use local_graphitoubb\tool_registry;

/**
 * Wraps tool_registry lookup for mod_graphitoubb consumers.
 *
 * Defensively bootstraps the registry if empty (R-7: plugin load order).
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_factory {
    /**
     * Returns the registered AfdTool instance.
     *
     * Bootstraps default tools if the registry is empty so mod_graphitoubb
     * survives even when local_graphitoubb init() ran before the registry was
     * populated (e.g. during unit test bootstrap order).
     *
     * @return tool_interface
     * @throws \coding_exception When the afd tool is not registered after bootstrap.
     */
    public function get_afd_tool(): tool_interface {
        $registry = tool_registry::instance();

        if (empty($registry->all())) {
            bootstrap::register_default_tools();
        }

        $tool = $registry->get('afd');

        if ($tool === null) {
            throw new \coding_exception('AfdTool not found in registry after bootstrap.');
        }

        return $tool;
    }
}
