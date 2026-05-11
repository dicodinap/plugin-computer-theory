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
 * External web service: fetch latest snapshot for an attempt.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_graphitoubb\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_graphitoubb\attempt_service;
use mod_graphitoubb\snapshot_service;

/**
 * Returns the latest snapshot for an attempt, or an empty payload if none exists.
 *
 * Read-only endpoint. Requires capability mod/graphitoubb:attempt or
 * mod/graphitoubb:viewreport (teachers can inspect any attempt).
 */
final class get_latest_snapshot extends external_api {
    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
        ]);
    }

    /**
     * @param int $attemptid
     * @return array
     */
    public static function execute(int $attemptid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
        ]);
        $attemptid = $params['attemptid'];

        $attemptservice = new attempt_service();
        $attempt = $attemptservice->get_attempt($attemptid);
        if (!$attempt) {
            return ['found' => false, 'payload' => '', 'schemaversion' => 0];
        }

        $cm = get_coursemodule_from_instance('graphitoubb', (int) $attempt->instanceid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        $canbypass = has_capability('mod/graphitoubb:viewreport', $context);
        if (!$canbypass) {
            require_capability('mod/graphitoubb:attempt', $context);
            if (!$attemptservice->belongs_to($attemptid, (int) $USER->id)) {
                throw new \moodle_exception('not_attempt_owner', 'mod_graphitoubb');
            }
        }

        $snapshotservice = new snapshot_service();
        $latest = $snapshotservice->get_latest($attemptid);
        if (!$latest) {
            return ['found' => false, 'payload' => '', 'schemaversion' => 0];
        }

        return [
            'found' => true,
            'payload' => (string) $latest->payload,
            'schemaversion' => (int) $latest->schema_version,
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'found' => new external_value(PARAM_BOOL, 'Whether a snapshot exists'),
            'payload' => new external_value(PARAM_RAW, 'Canonical JSON payload (empty if none)'),
            'schemaversion' => new external_value(PARAM_INT, 'Schema version'),
        ]);
    }
}
