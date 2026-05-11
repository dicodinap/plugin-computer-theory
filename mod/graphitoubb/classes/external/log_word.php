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
use mod_graphitoubb\wordbank_service;

/**
 * External function: log a word tested against the automaton.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class log_word extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'word'      => new external_value(PARAM_TEXT, 'Word tested against the automaton'),
            'accepted'  => new external_value(PARAM_BOOL, 'Whether the automaton accepted the word'),
        ]);
    }

    /**
     * Log a word entry for an attempt.
     *
     * Teachers with mod/graphitoubb:viewreport bypass the ownership check.
     *
     * @param int    $attemptid
     * @param string $word
     * @param bool   $accepted
     * @return array{status: string}
     */
    public static function execute(int $attemptid, string $word, bool $accepted): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'word'      => $word,
            'accepted'  => $accepted,
        ]);

        $attemptservice = new attempt_service();
        $attempt = $attemptservice->get_attempt($params['attemptid']);
        $cm = get_coursemodule_from_instance('graphitoubb', (int) $attempt->instanceid, 0, false, MUST_EXIST);
        $context = \context_module::instance((int) $cm->id);

        $canbypass = has_capability('mod/graphitoubb:viewreport', $context);
        if (!$canbypass) {
            require_capability('mod/graphitoubb:attempt', $context);
        }
        if (!$canbypass && !$attemptservice->belongs_to($params['attemptid'], (int) $USER->id)) {
            throw new \moodle_exception('not_attempt_owner', 'mod_graphitoubb');
        }

        $service = new wordbank_service();
        $service->log($params['attemptid'], $params['word'], (bool) $params['accepted']);

        return ['status' => 'ok'];
    }

    /**
     * Return structure definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHANUMEXT, 'ok'),
        ]);
    }
}
