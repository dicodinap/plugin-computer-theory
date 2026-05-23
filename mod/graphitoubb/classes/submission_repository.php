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

use local_graphitoubb\tools\truth_table\domain\serializer;

/**
 * Data-access object for graphitoubb_submission records.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class submission_repository {
    /**
     * Persist a graded submission.
     *
     * @param int    $attemptid            graphitoubb_attempt.id
     * @param array  $payload              Decoded submission array.
     * @param array  $grading_result       Flat grading_result::to_array() output.
     * @param string $problem_snapshot_hash SHA-256 of problem at grading time.
     * @param int    $schemaversion        Schema version.
     * @return int   New submission record id.
     */
    public function save(
        int $attemptid,
        array $payload,
        array $grading_result,
        string $problem_snapshot_hash,
        int $schemaversion
    ): int {
        global $DB;

        $ser         = new serializer();
        $payload_json = $ser->encode($payload);
        $payload_hash = $ser->hash($payload);

        $record = (object) [
            'attemptid'             => $attemptid,
            'payload'               => $payload_json,
            'payload_hash'          => $payload_hash,
            'problem_snapshot_hash' => $problem_snapshot_hash,
            'score'                 => (float) ($grading_result['score'] ?? 0),
            'fraction'              => (float) ($grading_result['fraction'] ?? 0),
            'passed'                => (int) ($grading_result['passed'] ?? false),
            'grading_result'        => json_encode($grading_result, JSON_UNESCAPED_UNICODE),
            'schema_version'        => $schemaversion,
            'timecreated'           => time(),
        ];
        return (int) $DB->insert_record('graphitoubb_submission', $record);
    }

    /**
     * Find the most recent submission for an attempt.
     *
     * @param int $attemptid
     * @return \stdClass|null
     */
    public function find_by_attempt(int $attemptid): ?\stdClass {
        global $DB;
        $records = $DB->get_records(
            'graphitoubb_submission',
            ['attemptid' => $attemptid],
            'timecreated DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * Find the latest submission for a user across all their attempts for an instance.
     *
     * @param int $instanceid
     * @param int $userid
     * @return \stdClass|null
     */
    public function latest_for_user_in_instance(int $instanceid, int $userid): ?\stdClass {
        global $DB;

        $sql = "SELECT s.*
                  FROM {graphitoubb_submission} s
                  JOIN {graphitoubb_attempt} a ON a.id = s.attemptid
                 WHERE a.instanceid = :instanceid AND a.userid = :userid
                 ORDER BY s.timecreated DESC
                 LIMIT 1";

        $record = $DB->get_record_sql($sql, ['instanceid' => $instanceid, 'userid' => $userid]);
        return $record ?: null;
    }

    /**
     * Return all submissions for all attempts belonging to an instance.
     *
     * @param int $instanceid
     * @return \stdClass[]
     */
    public function all_for_instance(int $instanceid): array {
        global $DB;

        $sql = "SELECT s.*
                  FROM {graphitoubb_submission} s
                  JOIN {graphitoubb_attempt} a ON a.id = s.attemptid
                 WHERE a.instanceid = :instanceid
                 ORDER BY s.timecreated ASC";

        return $DB->get_records_sql($sql, ['instanceid' => $instanceid]);
    }
}
