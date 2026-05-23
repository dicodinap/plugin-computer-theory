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
use mod_graphitoubb\event_service;

/**
 * External function: log a client-side telemetry event.
 *
 * Only events in the allowlist are persisted; others return status='ignored'.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class log_event extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid'  => new external_value(PARAM_INT, 'Attempt id; 0 for pre-attempt events', VALUE_DEFAULT, 0),
            'instanceid' => new external_value(PARAM_INT, 'Activity instance id'),
            'name'       => new external_value(PARAM_ALPHANUMEXT, 'Event name from the allowlist'),
            'payload'    => new external_value(PARAM_RAW, 'Optional JSON payload', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Log the event if the name is on the allowlist.
     *
     * @param int    $attemptid
     * @param int    $instanceid
     * @param string $name
     * @param string $payload  JSON string or empty.
     * @return array{status: string}
     */
    public static function execute(int $attemptid, int $instanceid, string $name, string $payload): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid'  => $attemptid,
            'instanceid' => $instanceid,
            'name'       => $name,
            'payload'    => $payload,
        ]);

        // Capability check on the module context.
        $cm      = get_coursemodule_from_instance('graphitoubb', $params['instanceid'], 0, false, MUST_EXIST);
        $context = \context_module::instance((int) $cm->id);
        require_capability('mod/graphitoubb:attempt', $context);

        $event_svc = new event_service();

        if (!$event_svc->is_allowed($params['name'])) {
            return ['status' => 'ignored'];
        }

        $payload_arr = null;
        if ($params['payload'] !== '') {
            $decoded = json_decode($params['payload'], true);
            if (is_array($decoded)) {
                $payload_arr = $decoded;
            }
        }

        $attempt_id = $params['attemptid'] > 0 ? $params['attemptid'] : null;

        $event_svc->log(
            (int) $USER->id,
            $params['instanceid'],
            $attempt_id,
            $params['name'],
            $payload_arr
        );

        return ['status' => 'logged'];
    }

    /**
     * Return structure definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHANUMEXT, 'logged | ignored'),
        ]);
    }
}
