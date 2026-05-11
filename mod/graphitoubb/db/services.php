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
 * External function (web service) registrations for mod_graphitoubb.
 *
 * No declare(strict_types=1) — Moodle hook contract.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'mod_graphitoubb_save_snapshot' => [
        'classname'    => 'mod_graphitoubb\external\save_snapshot',
        'methodname'   => 'execute',
        'description'  => 'Save a tool-state snapshot for an in-progress attempt.',
        'type'         => 'write',
        'capabilities' => 'mod/graphitoubb:attempt',
        'ajax'         => true,
    ],

    'mod_graphitoubb_get_latest_snapshot' => [
        'classname'    => 'mod_graphitoubb\external\get_latest_snapshot',
        'methodname'   => 'execute',
        'description'  => 'Fetch the latest snapshot payload for an attempt (empty if none).',
        'type'         => 'read',
        'capabilities' => 'mod/graphitoubb:attempt',
        'ajax'         => true,
    ],

    'mod_graphitoubb_log_word' => [
        'classname'    => 'mod_graphitoubb\external\log_word',
        'methodname'   => 'execute',
        'description'  => 'Log a word tested against the automaton during an attempt.',
        'type'         => 'write',
        'capabilities' => 'mod/graphitoubb:attempt',
        'ajax'         => true,
    ],

    'mod_graphitoubb_finish_attempt' => [
        'classname'    => 'mod_graphitoubb\external\finish_attempt',
        'methodname'   => 'execute',
        'description'  => 'Mark an attempt as finished.',
        'type'         => 'write',
        'capabilities' => 'mod/graphitoubb:attempt',
        'ajax'         => true,
    ],

];
