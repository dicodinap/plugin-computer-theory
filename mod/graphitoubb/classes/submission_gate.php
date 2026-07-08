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
 * RF_04 submission gate (D9/D13) — "enviar sólo si la actividad está activa y
 * tiene intentos disponibles". Shared by finish_attempt.php (canvas tools) and
 * submit.php (truth_table). Additive & opt-in-safe: existing instances with no
 * window and NULL attempts_max behave exactly as before (I6).
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_graphitoubb;

/**
 * Stateless availability + attempts check for a student on an activity instance.
 */
final class submission_gate {
    /**
     * Check whether $userid may submit on $instance right now.
     *
     * @param  \stdClass $instance graphitoubb instance row.
     * @param  int       $userid
     * @return array{allowed:bool, reason:?string} reason ∈ {not_open,closed,no_attempts}.
     */
    public static function check(\stdClass $instance, int $userid): array {
        global $DB;

        $now = time();
        $timeopen  = (int) ($instance->timeopen ?? 0);
        $timeclose = (int) ($instance->timeclose ?? 0);

        if ($timeopen > 0 && $now < $timeopen) {
            return ['allowed' => false, 'reason' => 'not_open'];
        }
        if ($timeclose > 0 && $now > $timeclose) {
            return ['allowed' => false, 'reason' => 'closed'];
        }

        // attempts_max NULL = unlimited.
        $max = $instance->attempts_max ?? null;
        if ($max !== null && $max !== '') {
            $used = self::count_submissions((int) $instance->id, $userid);
            if ($used >= (int) $max) {
                return ['allowed' => false, 'reason' => 'no_attempts'];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Number of graded submissions this user already recorded on the instance.
     *
     * "Attempts used" = COUNT(graphitoubb_submission) across the user's attempts.
     *
     * @param  int $instanceid
     * @param  int $userid
     * @return int
     */
    public static function count_submissions(int $instanceid, int $userid): int {
        global $DB;
        $sql = "SELECT COUNT(s.id)
                  FROM {graphitoubb_submission} s
                  JOIN {graphitoubb_attempt} a ON a.id = s.attemptid
                 WHERE a.instanceid = :instanceid AND a.userid = :userid";
        return (int) $DB->count_records_sql($sql, ['instanceid' => $instanceid, 'userid' => $userid]);
    }

    /**
     * Localised human-readable reason for a blocked gate.
     *
     * @param  string $reason
     * @return string
     */
    public static function reason_text(string $reason): string {
        $map = [
            'not_open'     => 'gate_reason_not_open',
            'closed'       => 'gate_reason_closed',
            'no_attempts'  => 'gate_reason_no_attempts',
        ];
        $key = $map[$reason] ?? 'gate_reason_closed';
        return get_string($key, 'mod_graphitoubb');
    }
}
