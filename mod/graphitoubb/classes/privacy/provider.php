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
 * Privacy provider for mod_graphitoubb.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_graphitoubb\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Full privacy provider — mod_graphitoubb owns all student artifact data.
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Declares the personal data stored by this plugin.
     *
     * @param collection $collection Metadata collection to populate.
     * @return collection Updated collection.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('graphitoubb_attempt', [
            'userid'       => 'privacy:metadata:graphitoubb_attempt:userid',
            'status'       => 'privacy:metadata:graphitoubb_attempt:status',
            'timestarted'  => 'privacy:metadata:graphitoubb_attempt:timestarted',
            'timefinished' => 'privacy:metadata:graphitoubb_attempt:timefinished',
        ], 'privacy:metadata:graphitoubb_attempt');

        $collection->add_database_table('graphitoubb_snapshot', [
            'payload'     => 'privacy:metadata:graphitoubb_snapshot:payload',
            'timecreated' => 'privacy:metadata:graphitoubb_snapshot:timecreated',
        ], 'privacy:metadata:graphitoubb_snapshot');

        $collection->add_database_table('graphitoubb_wordbank_log', [
            'word'        => 'privacy:metadata:graphitoubb_wordbank_log:word',
            'accepted'    => 'privacy:metadata:graphitoubb_wordbank_log:accepted',
            'timecreated' => 'privacy:metadata:graphitoubb_wordbank_log:timecreated',
        ], 'privacy:metadata:graphitoubb_wordbank_log');

        return $collection;
    }

    /**
     * Returns all module contexts where the given user has data.
     *
     * @param int $userid User id.
     * @return contextlist Populated contextlist.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm  ON cm.id = ctx.instanceid
                                           AND ctx.contextlevel = :contextlevel
                  JOIN {graphitoubb} g      ON g.id = cm.instance
                  JOIN {graphitoubb_attempt} ga ON ga.instanceid = g.id
                 WHERE ga.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Returns all users with data in the given module context.
     *
     * @param userlist $userlist Userlist for the context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT ga.userid
                  FROM {graphitoubb_attempt} ga
                  JOIN {graphitoubb} g        ON g.id  = ga.instanceid
                  JOIN {course_modules} cm    ON cm.instance = g.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Exports personal data for the given user within the given contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('graphitoubb', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $attempt = $DB->get_record(
                'graphitoubb_attempt',
                ['instanceid' => $cm->instance, 'userid' => $userid]
            );
            if (!$attempt) {
                continue;
            }

            $snapshots   = $DB->get_records('graphitoubb_snapshot', ['attemptid' => $attempt->id]);
            $wordbanklogs = $DB->get_records('graphitoubb_wordbank_log', ['attemptid' => $attempt->id]);

            $data = (object) [
                'status'       => $attempt->status,
                'timestarted'  => transform::datetime($attempt->timestarted),
                'timefinished' => $attempt->timefinished
                    ? transform::datetime($attempt->timefinished)
                    : null,
                'snapshots'    => array_values(array_map(
                    fn($s) => (object) [
                        'payload'        => $s->payload,
                        'schema_version' => (int) $s->schema_version,
                        'timecreated'    => transform::datetime($s->timecreated),
                    ],
                    $snapshots
                )),
                'wordbank_log' => array_values(array_map(
                    fn($w) => (object) [
                        'word'        => $w->word,
                        'accepted'    => (bool) $w->accepted,
                        'timecreated' => transform::datetime($w->timecreated),
                    ],
                    $wordbanklogs
                )),
            ];

            writer::with_context($context)->export_data([], $data);
        }
    }

    /**
     * Deletes all data for all users in the given context.
     *
     * @param \context $context Module context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('graphitoubb', $context->instanceid);
        if (!$cm) {
            return;
        }

        self::delete_attempts_for_instance((int) $cm->instance);
    }

    /**
     * Deletes data for the given user in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('graphitoubb', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $attempt = $DB->get_record(
                'graphitoubb_attempt',
                ['instanceid' => $cm->instance, 'userid' => $userid]
            );
            if (!$attempt) {
                continue;
            }

            self::delete_attempt_data((int) $attempt->id);
            $DB->delete_records('graphitoubb_attempt', ['id' => $attempt->id]);
        }
    }

    /**
     * Deletes data for a list of users in the given context.
     *
     * @param approved_userlist $userlist Approved userlist.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('graphitoubb', $context->instanceid);
        if (!$cm) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $attempt = $DB->get_record(
                'graphitoubb_attempt',
                ['instanceid' => $cm->instance, 'userid' => $userid]
            );
            if (!$attempt) {
                continue;
            }

            self::delete_attempt_data((int) $attempt->id);
            $DB->delete_records('graphitoubb_attempt', ['id' => $attempt->id]);
        }
    }

    /**
     * Deletes snapshots and wordbank log rows for an attempt.
     *
     * @param int $attemptid Attempt id.
     */
    private static function delete_attempt_data(int $attemptid): void {
        global $DB;
        $DB->delete_records('graphitoubb_snapshot', ['attemptid' => $attemptid]);
        $DB->delete_records('graphitoubb_wordbank_log', ['attemptid' => $attemptid]);
    }

    /**
     * Deletes all attempts (and their child rows) for a given instance.
     *
     * @param int $instanceid Graphitoubb instance id.
     */
    private static function delete_attempts_for_instance(int $instanceid): void {
        global $DB;

        $attemptids = $DB->get_fieldset_select('graphitoubb_attempt', 'id', 'instanceid = ?', [$instanceid]);
        if ($attemptids) {
            [$insql, $inparams] = $DB->get_in_or_equal($attemptids);
            $DB->delete_records_select('graphitoubb_snapshot', "attemptid $insql", $inparams);
            $DB->delete_records_select('graphitoubb_wordbank_log', "attemptid $insql", $inparams);
        }

        $DB->delete_records('graphitoubb_attempt', ['instanceid' => $instanceid]);
    }
}
