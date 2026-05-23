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
use mod_graphitoubb\attempt_service;
use mod_graphitoubb\draft_service;
use mod_graphitoubb\event_service;

/**
 * External function: autosave a truth-table draft.
 *
 * Returns status 'saved', 'conflict', or 'ratelimited'.
 * On conflict, server_payload is returned so the client can offer a merge UI.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class save_draft extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid'       => new external_value(PARAM_INT, 'Attempt id'),
            'payload'         => new external_value(PARAM_RAW, 'Draft payload JSON'),
            'payload_hash'    => new external_value(PARAM_ALPHANUMEXT, 'SHA-256 of the payload', VALUE_DEFAULT, ''),
            'draft_updated_at' => new external_value(PARAM_INT, 'Client-side draft_updated_at timestamp', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save draft; absorb rate-limit and conflict into status field.
     *
     * @param int    $attemptid
     * @param string $payload
     * @param string $payload_hash
     * @param int    $draft_updated_at
     * @return array{status: string, draft_updated_at: int, server_payload: string}
     */
    public static function execute(int $attemptid, string $payload, string $payload_hash, int $draft_updated_at): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid'        => $attemptid,
            'payload'          => $payload,
            'payload_hash'     => $payload_hash,
            'draft_updated_at' => $draft_updated_at,
        ]);

        $attempt_svc = new attempt_service();
        $attempt     = $attempt_svc->get_attempt($params['attemptid']);
        $cm          = get_coursemodule_from_instance('graphitoubb', (int) $attempt->instanceid, 0, false, MUST_EXIST);
        $context     = \context_module::instance((int) $cm->id);

        require_capability('mod/graphitoubb:attempt', $context);
        if (!$attempt_svc->belongs_to($params['attemptid'], (int) $USER->id)) {
            throw new \moodle_exception('not_attempt_owner', 'mod_graphitoubb');
        }

        $draft_svc = new draft_service();
        $result    = $draft_svc->save_draft(
            $params['attemptid'],
            $params['payload'],
            $params['draft_updated_at']
        );

        // Log draft_saved event when successfully saved (piggyback on autosave).
        if ($result['status'] === 'saved') {
            $event_svc = new event_service();
            $event_svc->log(
                (int) $USER->id,
                (int) $attempt->instanceid,
                $params['attemptid'],
                'draft_saved',
                ['timestamp' => $result['draft_updated_at'], 'payload_hash' => $params['payload_hash']]
            );
        }

        return [
            'status'           => $result['status'],
            'draft_updated_at' => $result['draft_updated_at'],
            'server_payload'   => $result['server_payload'] ?? '',
        ];
    }

    /**
     * Return structure definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status'           => new external_value(PARAM_ALPHANUMEXT, 'saved | conflict | ratelimited'),
            'draft_updated_at' => new external_value(PARAM_INT, 'New server timestamp (0 on failure)'),
            'server_payload'   => new external_value(PARAM_RAW, 'Server draft JSON on conflict; empty string otherwise'),
        ]);
    }
}
