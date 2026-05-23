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
 * Draft autosave service.
 *
 * Handles optimistic locking and rate-limit checks for the autosave flow.
 * Rate limit: 30 saves per minute per attempt_id (counted via graphitoubb_event).
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class draft_service {
    /** Maximum autosave requests per minute per attempt. */
    private const RATE_LIMIT = 30;

    /**
     * Persist a draft payload if optimistic lock matches.
     *
     * Returns:
     *   status = 'saved'       → draft written, new draft_updated_at returned.
     *   status = 'conflict'    → client_ts differs from server; server_payload returned.
     *   status = 'ratelimited' → too many saves in the last minute.
     *
     * @param int    $attemptid
     * @param string $payload    Raw JSON string from the client.
     * @param int    $client_ts  The draft_updated_at value the client believes is current.
     * @return array{status: string, draft_updated_at: int, server_payload: string|null}
     */
    public function save_draft(int $attemptid, string $payload, int $client_ts): array {
        global $DB;

        if ($this->is_rate_limited($attemptid)) {
            return ['status' => 'ratelimited', 'draft_updated_at' => 0, 'server_payload' => null];
        }

        $attempt = $DB->get_record('graphitoubb_attempt', ['id' => $attemptid], '*', MUST_EXIST);

        // Optimistic lock: if the server's draft_updated_at does not match what the
        // client sent, return a conflict so the client can prompt the user.
        //
        // Exception: client_ts == 0 means the client has no prior knowledge
        // (fresh page load). In that case we accept the save unconditionally — the
        // genuine conflict case is two open tabs with diverging timestamps.
        $server_ts = (int) ($attempt->draft_updated_at ?? 0);
        if ($server_ts !== 0 && $client_ts !== 0 && $server_ts !== $client_ts) {
            return [
                'status'          => 'conflict',
                'draft_updated_at' => $server_ts,
                'server_payload'  => $attempt->current_draft ?? null,
            ];
        }

        $now = time();
        $DB->set_field('graphitoubb_attempt', 'current_draft', $payload, ['id' => $attemptid]);
        $DB->set_field('graphitoubb_attempt', 'draft_updated_at', $now, ['id' => $attemptid]);

        return ['status' => 'saved', 'draft_updated_at' => $now, 'server_payload' => null];
    }

    /**
     * Retrieve the current draft for an attempt.
     *
     * @param int $attemptid
     * @return array{payload: string, draft_updated_at: int}|null  Null if no draft exists.
     */
    public function get_draft(int $attemptid): ?array {
        global $DB;

        $attempt = $DB->get_record('graphitoubb_attempt', ['id' => $attemptid]);
        if (!$attempt || $attempt->current_draft === null) {
            return null;
        }

        return [
            'payload'          => $attempt->current_draft,
            'draft_updated_at' => (int) $attempt->draft_updated_at,
        ];
    }

    /**
     * Clear the draft for an attempt (called after successful submission).
     *
     * @param int $attemptid
     */
    public function clear_draft(int $attemptid): void {
        global $DB;
        $DB->set_field('graphitoubb_attempt', 'current_draft', null, ['id' => $attemptid]);
        $DB->set_field('graphitoubb_attempt', 'draft_updated_at', null, ['id' => $attemptid]);
    }

    /**
     * Check whether the attempt has exceeded the autosave rate limit.
     *
     * Counts draft_saved events in the last 60 seconds for this attempt.
     *
     * @param int $attemptid
     * @return bool
     */
    public function is_rate_limited(int $attemptid): bool {
        global $DB;

        $since  = time() - 60;
        $count  = $DB->count_records_select(
            'graphitoubb_event',
            "attemptid = :attemptid AND name = 'draft_saved' AND timecreated >= :since",
            ['attemptid' => $attemptid, 'since' => $since]
        );

        return $count >= self::RATE_LIMIT;
    }
}
