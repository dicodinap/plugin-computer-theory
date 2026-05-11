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
 * Restore structure step for mod_graphitoubb.
 *
 * No declare(strict_types=1) — restore stepslib classes are legacy-style.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore structure step: reads graphitoubb.xml and rebuilds all tables.
 *
 * Foreign-key remapping order: graphitoubb → attempt → snapshot/wordbank_log.
 * User IDs are remapped via the standard 'user' mapping table.
 */
class restore_graphitoubb_activity_structure_step extends restore_activity_structure_step {
    /**
     * Register XML paths and their processor methods.
     *
     * @return array
     */
    protected function define_structure() {
        $paths   = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('graphitoubb', '/activity/graphitoubb');

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'graphitoubb_attempt',
                '/activity/graphitoubb/attempts/attempt'
            );
            $paths[] = new restore_path_element(
                'graphitoubb_snapshot',
                '/activity/graphitoubb/attempts/attempt/snapshots/snapshot'
            );
            $paths[] = new restore_path_element(
                'graphitoubb_wordbank_log',
                '/activity/graphitoubb/attempts/attempt/wordbank_logs/wordbank_log'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the graphitoubb instance row.
     *
     * @param array $data
     */
    protected function process_graphitoubb($data) {
        global $DB;

        $data        = (object) $data;
        $oldid       = $data->id;
        $data->course = $this->get_courseid();

        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        unset($data->id);
        $newitemid = $DB->insert_record('graphitoubb', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore a student attempt row; remaps userid and instanceid.
     *
     * @param array $data
     */
    protected function process_graphitoubb_attempt($data) {
        global $DB;

        $data   = (object) $data;
        $oldid  = $data->id;

        $data->instanceid   = $this->get_new_parentid('graphitoubb');
        $data->userid       = $this->get_mappingid('user', $data->userid);
        $data->timestarted  = $this->apply_date_offset($data->timestarted);

        if (!empty($data->timefinished)) {
            $data->timefinished = $this->apply_date_offset($data->timefinished);
        }

        unset($data->id);
        $newitemid = $DB->insert_record('graphitoubb_attempt', $data);
        $this->set_mapping('graphitoubb_attempt', $oldid, $newitemid);
    }

    /**
     * Restore a snapshot row; remaps attemptid via parent mapping.
     *
     * @param array $data
     */
    protected function process_graphitoubb_snapshot($data) {
        global $DB;

        $data            = (object) $data;
        $data->attemptid = $this->get_new_parentid('graphitoubb_attempt');
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        unset($data->id);
        $DB->insert_record('graphitoubb_snapshot', $data);
    }

    /**
     * Restore a wordbank log row; remaps attemptid via parent mapping.
     *
     * @param array $data
     */
    protected function process_graphitoubb_wordbank_log($data) {
        global $DB;

        $data            = (object) $data;
        $data->attemptid = $this->get_new_parentid('graphitoubb_attempt');
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        unset($data->id);
        $DB->insert_record('graphitoubb_wordbank_log', $data);
    }

    /**
     * Re-attach intro files after all records are restored.
     */
    protected function after_execute() {
        $this->add_related_files('mod_graphitoubb', 'intro', null);
    }
}
