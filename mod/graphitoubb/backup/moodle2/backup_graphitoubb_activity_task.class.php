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
 * Backup task for mod_graphitoubb.
 *
 * No declare(strict_types=1) — backup task classes are legacy-style.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/graphitoubb/backup/moodle2/backup_graphitoubb_stepslib.php');

/**
 * Backup task for the graphitoubb activity module.
 */
class backup_graphitoubb_activity_task extends backup_activity_task {
    /**
     * No module-specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Register the single structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_graphitoubb_activity_structure_step(
            'graphitoubb_structure',
            'graphitoubb.xml'
        ));
    }

    /**
     * No special content-link encoding required.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        return $content;
    }
}
