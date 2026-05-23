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

namespace mod_graphitoubb\output;

use renderable;
use renderer_base;
use templatable;

/**
 * Renderable for the teacher panel (4-tab dashboard).
 *
 * Carries the minimal data needed by teacher_panel.mustache:
 * - instanceid and contextid for AMD initialization.
 * - problem metadata for AMD to show before the first WS call resolves.
 * - The WS function names JSON-encoded so the AMD module knows what to call.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_panel_renderable implements renderable, templatable {
    /** @var int Activity instance id. */
    private int $instanceid;

    /** @var \stdClass|null Problem record (null if no problem configured yet). */
    private ?\stdClass $problem;

    /** @var int Context id for the module. */
    private int $contextid;

    /**
     * @param int             $instanceid
     * @param \stdClass|null  $problem    Problem DB record (fields: id, payload, schema_version, tool, type).
     * @param int             $contextid
     */
    public function __construct(int $instanceid, ?\stdClass $problem, int $contextid) {
        $this->instanceid = $instanceid;
        $this->problem    = $problem;
        $this->contextid  = $contextid;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output
     * @return array<string, mixed>
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        $wsfunctions = json_encode([
            'summary'    => 'mod_graphitoubb_get_panel_summary',
            'perStudent' => 'mod_graphitoubb_get_panel_per_student',
            'heatmap'    => 'mod_graphitoubb_get_panel_heatmap',
            'reset'      => 'mod_graphitoubb_reset_attempts',
            'stats'      => 'mod_graphitoubb_get_problem_stats',
        ]);

        return [
            'instanceid'          => $this->instanceid,
            'contextid'           => $this->contextid,
            'problem_id'          => $this->problem ? (int)   $this->problem->id : 0,
            'problem_tool'        => $this->problem ? (string) $this->problem->tool : '',
            'problem_type'        => $this->problem ? (string) $this->problem->type : '',
            'schema_version'      => $this->problem ? (int)   $this->problem->schema_version : 1,
            'wsfunctions'         => $wsfunctions,
            'userlang'            => (string) $USER->lang,
            // Tab active flags — summary is always default.
            'tab_summary_active'  => true,
            'tab_student_active'  => false,
            'tab_heatmap_active'  => false,
            'tab_export_active'   => false,
        ];
    }
}
