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
 * Scheduled task: delete orphaned telemetry and grading rows.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_graphitoubb\task;

/**
 * Removes orphaned rows from iter1 tables on a daily schedule.
 *
 * Orphan definitions:
 * - graphitoubb_event rows older than 180 days.
 * - graphitoubb_submission rows whose attemptid references a non-existent attempt.
 * - graphitoubb_grade_cache rows whose attemptid references a non-existent attempt.
 * - graphitoubb_snapshot rows whose attemptid references a non-existent attempt.
 */
class cleanup_orphans extends \core\task\scheduled_task {
    /** Maximum age in seconds for telemetry events before they are pruned. */
    const EVENT_TTL_SECONDS = 180 * DAYSECS;

    /**
     * Returns the human-readable name of this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_orphans', 'mod_graphitoubb');
    }

    /**
     * Executes the cleanup logic.
     *
     * Each operation is idempotent and safe to run repeatedly.
     */
    public function execute(): void {
        global $DB;

        $cutoff = time() - self::EVENT_TTL_SECONDS;

        // 1. Delete old telemetry events (regardless of orphan status).
        $deleted = $DB->delete_records_select('graphitoubb_event', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        mtrace("cleanup_orphans: deleted $deleted old graphitoubb_event rows (older than 180 days).");

        // 2. Delete graphitoubb_submission rows whose attempt no longer exists.
        $deleted = $DB->delete_records_select(
            'graphitoubb_submission',
            "attemptid NOT IN (SELECT id FROM {graphitoubb_attempt})"
        );
        mtrace("cleanup_orphans: deleted $deleted orphaned graphitoubb_submission rows.");

        // 3. Delete graphitoubb_grade_cache rows whose attempt no longer exists.
        $deleted = $DB->delete_records_select(
            'graphitoubb_grade_cache',
            "attemptid NOT IN (SELECT id FROM {graphitoubb_attempt})"
        );
        mtrace("cleanup_orphans: deleted $deleted orphaned graphitoubb_grade_cache rows.");

        // 4. Delete graphitoubb_snapshot rows whose attempt no longer exists.
        $deleted = $DB->delete_records_select(
            'graphitoubb_snapshot',
            "attemptid NOT IN (SELECT id FROM {graphitoubb_attempt})"
        );
        mtrace("cleanup_orphans: deleted $deleted orphaned graphitoubb_snapshot rows.");
    }
}
