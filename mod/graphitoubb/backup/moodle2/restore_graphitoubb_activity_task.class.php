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
 * Restore task for mod_graphitoubb.
 *
 * No declare(strict_types=1) — restore task classes are legacy-style.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/graphitoubb/backup/moodle2/restore_graphitoubb_stepslib.php');

/**
 * Restore task for the graphitoubb activity module.
 */
class restore_graphitoubb_activity_task extends restore_activity_task {
    /**
     * No module-specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Register the single structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_graphitoubb_activity_structure_step(
            'graphitoubb_structure',
            'graphitoubb.xml'
        ));
    }

    /**
     * Define how to decode encoded content links.
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('graphitoubb', ['intro'], 'graphitoubb');
        return $contents;
    }

    /**
     * Define decode rules (none needed for graphitoubb).
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [];
    }

    /**
     * Define restore log rules (none needed for graphitoubb).
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Define course-level restore log rules (none needed).
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
