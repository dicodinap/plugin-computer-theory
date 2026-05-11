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

/**
 * Service for managing student attempts on a graphitoubb activity instance.
 *
 * Enforces R-5: one attempt per user per instance (UNIQUE instanceid+userid).
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_service {
    /**
     * Returns the existing attempt or creates a new one (idempotent).
     *
     * @param int $instanceid Graphitoubb instance id.
     * @param int $userid     Moodle user id.
     * @return \stdClass Attempt row.
     */
    public function start_or_resume(int $instanceid, int $userid): \stdClass {
        global $DB;

        $attempt = $DB->get_record('graphitoubb_attempt', [
            'instanceid' => $instanceid,
            'userid'     => $userid,
        ]);

        if ($attempt) {
            return $attempt;
        }

        $now = time();
        $id  = $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $instanceid,
            'userid'      => $userid,
            'status'      => 'inprogress',
            'timestarted' => $now,
        ]);

        return $DB->get_record('graphitoubb_attempt', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Marks the attempt as finished.
     *
     * @param int $attemptid Attempt id.
     * @return void
     */
    public function finish(int $attemptid): void {
        global $DB;

        $DB->update_record('graphitoubb_attempt', (object) [
            'id'           => $attemptid,
            'status'       => 'finished',
            'timefinished' => time(),
        ]);
    }

    /**
     * Returns the attempt row or null if not found.
     *
     * @param int $attemptid Attempt id.
     * @return \stdClass|null Attempt row or null.
     */
    public function get_attempt(int $attemptid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('graphitoubb_attempt', ['id' => $attemptid]);
        return $record ?: null;
    }

    /**
     * Returns true if the attempt belongs to the given user.
     *
     * @param int $attemptid Attempt id.
     * @param int $userid    User id.
     * @return bool
     */
    public function belongs_to(int $attemptid, int $userid): bool {
        global $DB;
        return $DB->record_exists('graphitoubb_attempt', [
            'id'     => $attemptid,
            'userid' => $userid,
        ]);
    }
}
