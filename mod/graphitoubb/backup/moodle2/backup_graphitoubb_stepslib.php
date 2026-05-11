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
 * Backup structure step for mod_graphitoubb.
 *
 * No declare(strict_types=1) — backup stepslib classes are legacy-style.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the backup XML structure for a graphitoubb activity instance.
 *
 * User data (attempts, snapshots, wordbank logs) is included only when the
 * userinfo setting is enabled — standard Moodle backup convention.
 */
class backup_graphitoubb_activity_structure_step extends backup_activity_structure_step {
    /**
     * Build and return the backup element tree.
     *
     * @return backup_nested_element Root element wrapped by prepare_activity_structure().
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        // Root element: the graphitoubb instance row.
        $graphitoubb = new backup_nested_element('graphitoubb', ['id'], [
            'name', 'intro', 'introformat', 'timecreated', 'timemodified',
        ]);

        // User-data containers.
        $attempts    = new backup_nested_element('attempts');
        $attempt     = new backup_nested_element('attempt', ['id'], [
            'userid', 'status', 'timestarted', 'timefinished',
        ]);

        $snapshots   = new backup_nested_element('snapshots');
        $snapshot    = new backup_nested_element('snapshot', ['id'], [
            'payload', 'schema_version', 'timecreated',
        ]);

        $wordbanklogs = new backup_nested_element('wordbank_logs');
        $wordbanklog  = new backup_nested_element('wordbank_log', ['id'], [
            'word', 'accepted', 'timecreated',
        ]);

        // Build tree.
        $graphitoubb->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($snapshots);
        $snapshots->add_child($snapshot);
        $attempt->add_child($wordbanklogs);
        $wordbanklogs->add_child($wordbanklog);

        // Sources.
        $graphitoubb->set_source_table('graphitoubb', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $attempt->set_source_table('graphitoubb_attempt', ['instanceid' => backup::VAR_PARENTID]);
            $snapshot->set_source_table('graphitoubb_snapshot', ['attemptid' => backup::VAR_PARENTID]);
            $wordbanklog->set_source_table('graphitoubb_wordbank_log', ['attemptid' => backup::VAR_PARENTID]);

            // Annotate user IDs for later remapping on restore.
            $attempt->annotate_ids('user', 'userid');
        }

        $graphitoubb->annotate_files('mod_graphitoubb', 'intro', null);

        return $this->prepare_activity_structure($graphitoubb);
    }
}
