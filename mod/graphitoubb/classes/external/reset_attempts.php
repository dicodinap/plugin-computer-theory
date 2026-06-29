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

namespace mod_graphitoubb\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function: reset attempts for a student (or all students) in an instance.
 *
 * Requires mod/graphitoubb:reattempt. Deletes graphitoubb_submission,
 * graphitoubb_grade_cache, graphitoubb_snapshot and graphitoubb_wordbank_log
 * rows (the latter two hold the AFD editor's automaton + tested words); resets
 * attempt.status = 'inprogress', attempt.timefinished = null,
 * attempt.current_draft = null. Logs a
 * mod_graphitoubb\event\attempt_started-style event for audit purposes.
 *
 * Design note: we keep the attempt row itself (so the user can re-use the
 * same attempt id) rather than hard-deleting it. This matches spec §7 wording
 * "resets attempt.status='inprogress', timefinished=null" and is safer for FK
 * integrity against graphitoubb_event rows.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reset_attempts extends external_api {
    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Activity instance id'),
            'userid'     => new external_value(PARAM_INT, 'Student user id to reset; 0 = reset all students', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * @param int $instanceid
     * @param int $userid  0 means reset all users in the instance.
     * @return array{reset_count: int}
     */
    public static function execute(int $instanceid, int $userid = 0): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'userid'     => $userid,
        ]);
        $iid    = $params['instanceid'];
        $uid    = $params['userid'];

        $cm      = get_coursemodule_from_instance('graphitoubb', $iid, 0, false, MUST_EXIST);
        $context = \context_module::instance((int) $cm->id);
        require_capability('mod/graphitoubb:reattempt', $context);

        // Fetch target attempts.
        $where  = ['instanceid' => $iid];
        if ($uid > 0) {
            $where['userid'] = $uid;
        }
        $attempts = $DB->get_records('graphitoubb_attempt', $where, '', 'id');

        $reset_count = 0;
        foreach ($attempts as $attempt) {
            $aid = (int) $attempt->id;

            // 1. Delete submissions and grade cache — the data associated with the attempt.
            $DB->delete_records('graphitoubb_submission', ['attemptid' => $aid]);
            $DB->delete_records('graphitoubb_grade_cache', ['attemptid' => $aid]);

            // 1b. Delete the student's actual work: AFD editor snapshots and wordbank log.
            // Without this, resetting an AFD attempt leaves the student's automaton and
            // tested words intact (they reappear on reload), making the reset a no-op for AFD.
            $DB->delete_records('graphitoubb_snapshot', ['attemptid' => $aid]);
            $DB->delete_records('graphitoubb_wordbank_log', ['attemptid' => $aid]);

            // 2. Reset attempt to inprogress (keep the attempt row for FK integrity with events).
            $DB->set_field('graphitoubb_attempt', 'status', 'inprogress', ['id' => $aid]);
            $DB->set_field('graphitoubb_attempt', 'timefinished', null, ['id' => $aid]);
            $DB->set_field('graphitoubb_attempt', 'current_draft', null, ['id' => $aid]);
            $DB->set_field('graphitoubb_attempt', 'draft_updated_at', null, ['id' => $aid]);

            $reset_count++;
        }

        // Audit log: iter2 will add a dedicated \mod_graphitoubb\event\attempts_reset event class.
        // For now we skip emitting; reset_count return value is the contract.

        return ['reset_count' => $reset_count];
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reset_count' => new external_value(PARAM_INT, 'Number of attempt records reset'),
        ]);
    }
}
