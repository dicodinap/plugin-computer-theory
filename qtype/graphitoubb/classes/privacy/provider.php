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

namespace qtype_graphitoubb\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for qtype_graphitoubb.
 *
 * qtype_graphitoubb_options stores instructor-authored question definitions only.
 * No per-student personal data is stored in the qtype tables.
 *
 * Per-student response data (answer_payload, grading_result) is stored by
 * the Moodle question engine in question_attempt_step_data, which is owned
 * by core_question. This provider declares metadata for that step data and
 * delegates all access/deletion to core_question helpers.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\core_userlist_provider, \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data stored by this plugin.
     *
     * qtype_graphitoubb_options: question definitions authored by instructors.
     * question_attempt_step_data: student response data managed by core_question.
     *
     * @param  collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        // Options table — instructor-owned, no personal data.
        $collection->add_database_table(
            'qtype_graphitoubb_options',
            [
                'questionid'      => 'privacy:metadata:qtype_graphitoubb_options',
                'tool'            => 'privacy:metadata:qtype_graphitoubb_options',
                'exercise_type'   => 'privacy:metadata:qtype_graphitoubb_options',
                'problem_payload' => 'privacy:metadata:qtype_graphitoubb_options',
                'scoring_config'  => 'privacy:metadata:qtype_graphitoubb_options',
                'ui_config'       => 'privacy:metadata:qtype_graphitoubb_options',
            ],
            'privacy:metadata:qtype_graphitoubb_options'
        );

        // Student response data — managed by core_question via step data.
        $collection->add_subsystem_link(
            'core_question',
            [],
            'privacy:metadata'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user data for the specified user.
     *
     * Student response data lives in question_attempt_step_data, owned by
     * core_question. This qtype itself adds no additional contexts.
     *
     * @param  int         $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        // core_question handles question_attempt_step_data contexts.
        // This qtype stores no per-user rows in qtype_graphitoubb_options.
        return new contextlist();
    }

    /**
     * Get the list of users who have data within a specific context.
     *
     * @param  userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        // No per-user data in qtype_graphitoubb_options.
    }

    /**
     * Export all user data for the specified user in the given contexts.
     *
     * core_question handles question_attempt_step_data export.
     * This qtype stores no per-user rows.
     *
     * @param  approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        // No per-user data in qtype_graphitoubb_options to export.
    }

    /**
     * Delete all user data for all users in the given context.
     *
     * @param  \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // No per-user data in qtype_graphitoubb_options to delete.
    }

    /**
     * Delete all user data for the specified user in the given contexts.
     *
     * @param  approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // No per-user data in qtype_graphitoubb_options to delete.
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param  approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        // No per-user data in qtype_graphitoubb_options to delete.
    }
}
