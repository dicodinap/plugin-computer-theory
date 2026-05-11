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
 * Service for logging words tested against an automaton during an attempt.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wordbank_service {
    /**
     * Logs a word tested during an attempt.
     *
     * @param int    $attemptid Attempt id.
     * @param string $word      Input word that was simulated.
     * @param bool   $accepted  Whether the automaton accepted the word.
     * @return int New log entry id.
     */
    public function log(int $attemptid, string $word, bool $accepted): int {
        global $DB;

        $id = $DB->insert_record('graphitoubb_wordbank_log', [
            'attemptid'   => $attemptid,
            'word'        => $word,
            'accepted'    => $accepted ? 1 : 0,
            'timecreated' => time(),
        ]);

        return (int) $id;
    }

    /**
     * Returns word log entries for an attempt, ordered chronologically.
     *
     * @param int $attemptid Attempt id.
     * @param int $limit     Maximum entries to return (default 100).
     * @return array Array of stdClass log rows.
     */
    public function list_for_attempt(int $attemptid, int $limit = 100): array {
        global $DB;

        $records = $DB->get_records_sql(
            'SELECT * FROM {graphitoubb_wordbank_log} WHERE attemptid = ? ORDER BY timecreated ASC, id ASC',
            [$attemptid],
            0,
            $limit
        );

        return array_values($records);
    }
}
