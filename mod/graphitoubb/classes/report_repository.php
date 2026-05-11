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
 * Read-only queries for the teacher report view.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_repository {
    /**
     * Returns all attempts for an instance with aggregate counters.
     *
     * Joins user name fields, snapshot count (DISTINCT to avoid double-counting
     * when wordbank entries multiply rows), and the last word tested (word at
     * MAX timecreated; MAX(lw.word) resolves ties deterministically).
     *
     * @param int $instanceid Graphitoubb instance id.
     * @return \stdClass[] Rows ordered by timestarted DESC.
     */
    public function list_attempts_for_instance(int $instanceid): array {
        global $DB;

        $sql = "SELECT a.id,
                       a.userid,
                       a.status,
                       a.timestarted,
                       a.timefinished,
                       u.firstname,
                       u.lastname,
                       u.firstnamephonetic,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename,
                       COUNT(DISTINCT s.id)  AS snapshot_count,
                       MAX(lw.word)          AS last_word_tested
                  FROM {graphitoubb_attempt} a
                  JOIN {user} u ON u.id = a.userid
             LEFT JOIN {graphitoubb_snapshot} s ON s.attemptid = a.id
             LEFT JOIN (
                           SELECT wl.attemptid,
                                  wl.word
                             FROM {graphitoubb_wordbank_log} wl
                       INNER JOIN (
                                      SELECT attemptid,
                                             MAX(timecreated) AS maxtc
                                        FROM {graphitoubb_wordbank_log}
                                    GROUP BY attemptid
                                  ) mx ON mx.attemptid = wl.attemptid
                                      AND mx.maxtc     = wl.timecreated
                       ) lw ON lw.attemptid = a.id
                 WHERE a.instanceid = :instanceid
              GROUP BY a.id,
                       a.userid,
                       a.status,
                       a.timestarted,
                       a.timefinished,
                       u.firstname,
                       u.lastname,
                       u.firstnamephonetic,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename
              ORDER BY a.timestarted DESC";

        return array_values($DB->get_records_sql($sql, ['instanceid' => $instanceid]));
    }
}
