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

namespace mod_graphitoubb\task;

/**
 * Adhoc task that backfills gradebook grades for a mod_graphitoubb instance.
 *
 * Grade pushes cannot run during the upgrade itself (grade_item::is_locked() calls
 * get_fast_modinfo(), which is forbidden while an upgrade is running). The upgrade
 * therefore creates the grade items and defers the actual grade backfill to this
 * task — mirroring core mod_scorm's \mod_scorm\task\update_grades.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_grades extends \core\task\adhoc_task {
    /**
     * Push every user's aggregated grade for the instance in the task's custom data.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/graphitoubb/lib.php');

        $data     = $this->get_custom_data();
        $instance = $DB->get_record('graphitoubb', ['id' => $data->instanceid]);
        if (!$instance) {
            return;
        }

        graphitoubb_update_grades($instance, 0, false);
    }
}
