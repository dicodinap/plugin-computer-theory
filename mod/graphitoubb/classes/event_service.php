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

use mod_graphitoubb\event\attempt_started;
use mod_graphitoubb\event\problem_updated;
use mod_graphitoubb\event\submission_submitted;

/**
 * Telemetry event service.
 *
 * Persists to graphitoubb_event AND fires Moodle event API events for
 * attempt_started, submission_submitted, and problem_updated.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_service {
    /** @var string[] Allowlist of accepted telemetry event names from the client. */
    private const ALLOWLIST = [
        'feedback_viewed',
        'retry_started',
        'problem_updated',
        'draft_saved',
    ];

    /**
     * Persist a telemetry event to graphitoubb_event.
     *
     * @param int        $userid
     * @param int        $instanceid
     * @param int|null   $attemptid
     * @param string     $name
     * @param array|null $payload
     * @return int  New event record id.
     */
    public function log(int $userid, int $instanceid, ?int $attemptid, string $name, ?array $payload): int {
        global $DB;

        $record = (object) [
            'attemptid'   => $attemptid,
            'userid'      => $userid,
            'instanceid'  => $instanceid,
            'name'        => $name,
            'payload'     => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'timecreated' => time(),
        ];
        return (int) $DB->insert_record('graphitoubb_event', $record);
    }

    /**
     * Check whether a client-supplied event name is in the allowlist.
     *
     * @param string $name
     * @return bool
     */
    public function is_allowed(string $name): bool {
        return in_array($name, self::ALLOWLIST, true);
    }

    /**
     * Fire and persist an attempt_started event.
     *
     * @param \stdClass $attempt   graphitoubb_attempt record.
     * @param int       $contextid Module context id.
     */
    public function dispatch_started(\stdClass $attempt, int $contextid): void {
        $this->log(
            (int) $attempt->userid,
            (int) $attempt->instanceid,
            (int) $attempt->id,
            'attempt_started',
            ['timestamp' => time()]
        );

        $event = attempt_started::create([
            'objectid'  => (int) $attempt->id,
            'context'   => \context::instance_by_id($contextid),
            'userid'    => (int) $attempt->userid,
            'other'     => ['instanceid' => (int) $attempt->instanceid],
        ]);
        $event->trigger();
    }

    /**
     * Fire and persist a submission_submitted event.
     *
     * @param \stdClass $attempt        graphitoubb_attempt record.
     * @param array     $grading_result Flat grading_result::to_array().
     * @param int       $submissionid   New submission record id.
     * @param int       $contextid      Module context id.
     */
    public function dispatch_submitted(\stdClass $attempt, array $grading_result, int $submissionid, int $contextid): void {
        $this->log(
            (int) $attempt->userid,
            (int) $attempt->instanceid,
            (int) $attempt->id,
            'submission_sent',
            [
                'timestamp'    => time(),
                'payload_hash' => $grading_result['problem_snapshot_hash'] ?? '',
                'score'        => $grading_result['score'] ?? 0,
            ]
        );

        $event = submission_submitted::create([
            'objectid'  => $submissionid,
            'context'   => \context::instance_by_id($contextid),
            'userid'    => (int) $attempt->userid,
            'other'     => [
                'attemptid' => (int) $attempt->id,
                'score'     => $grading_result['score'] ?? 0,
            ],
        ]);
        $event->trigger();
    }

    /**
     * Fire and persist a problem_updated event.
     *
     * @param \stdClass $problem   graphitoubb_problem record (after update).
     * @param string    $old_hash  SHA-256 of the previous payload.
     * @param string    $new_hash  SHA-256 of the new payload.
     * @param int       $contextid Module context id.
     */
    public function dispatch_problem_updated(\stdClass $problem, string $old_hash, string $new_hash, int $contextid): void {
        global $USER;

        $this->log(
            (int) $USER->id,
            (int) $problem->instanceid,
            null,
            'problem_updated',
            ['old_hash' => $old_hash, 'new_hash' => $new_hash]
        );

        $event = problem_updated::create([
            'objectid'  => (int) $problem->id,
            'context'   => \context::instance_by_id($contextid),
            'other'     => ['old_hash' => $old_hash, 'new_hash' => $new_hash],
        ]);
        $event->trigger();
    }
}
