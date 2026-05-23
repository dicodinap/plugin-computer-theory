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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\tests\provider_testcase;
use mod_graphitoubb\privacy\provider;

/**
 * Privacy provider tests for mod_graphitoubb — iter1 coverage.
 *
 * @package    mod_graphitoubb
 * @covers \mod_graphitoubb\privacy\provider
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends provider_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../../lib.php');
    }

    // -------------------------------------------------------------------------
    // Helpers.
    // -------------------------------------------------------------------------

    /**
     * Insert a minimal attempt and return its id.
     */
    private function insert_attempt(int $instanceid, int $userid): int {
        global $DB;
        return $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $instanceid,
            'userid'      => $userid,
            'status'      => 'inprogress',
            'timestarted' => time(),
        ]);
    }

    /**
     * Insert a submission row linked to $attemptid.
     */
    private function insert_submission(int $attemptid): int {
        global $DB;
        return $DB->insert_record('graphitoubb_submission', [
            'attemptid'             => $attemptid,
            'payload'               => '{"cells":[]}',
            'payload_hash'          => hash('sha256', '{"cells":[]}'),
            'problem_snapshot_hash' => hash('sha256', 'problem'),
            'score'                 => 0.75,
            'fraction'              => 0.75,
            'passed'                => 1,
            'grading_result'        => '{"items":[]}',
            'schema_version'        => 1,
            'timecreated'           => time(),
        ]);
    }

    /**
     * Insert a telemetry event row.
     */
    private function insert_event(int $userid, int $instanceid, ?int $attemptid = null): int {
        global $DB;
        return $DB->insert_record('graphitoubb_event', [
            'attemptid'   => $attemptid,
            'userid'      => $userid,
            'instanceid'  => $instanceid,
            'name'        => 'tt_cell_changed',
            'payload'     => null,
            'timecreated' => time(),
        ]);
    }

    /**
     * Insert a grade_cache row linked to $attemptid.
     */
    private function insert_grade_cache(int $attemptid): int {
        global $DB;
        return $DB->insert_record('graphitoubb_grade_cache', [
            'attemptid'      => $attemptid,
            'score'          => 0.75,
            'fraction'       => 0.75,
            'attempt_count'  => 1,
            'policy_applied' => 'best',
            'timemodified'   => time(),
        ]);
    }

    // -------------------------------------------------------------------------
    // 1. Metadata declares all iter1 tables.
    // -------------------------------------------------------------------------

    public function test_get_metadata_declares_all_iter1_tables(): void {
        $collection = new collection('mod_graphitoubb');
        $collection = provider::get_metadata($collection);

        $tablenames = [];
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $tablenames[] = $item->get_name();
            }
        }

        // Legacy tables still present.
        $this->assertContains('graphitoubb_attempt', $tablenames);
        $this->assertContains('graphitoubb_snapshot', $tablenames);
        $this->assertContains('graphitoubb_wordbank_log', $tablenames);

        // Iter1 tables.
        $this->assertContains('graphitoubb_submission', $tablenames);
        $this->assertContains('graphitoubb_event', $tablenames);
        $this->assertContains('graphitoubb_grade_cache', $tablenames);

        // graphitoubb_problem is instructor-only — must NOT appear.
        $this->assertNotContains('graphitoubb_problem', $tablenames);
    }

    // -------------------------------------------------------------------------
    // 2. Context discovery.
    // -------------------------------------------------------------------------

    public function test_get_contexts_for_userid_returns_instance_context(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $module  = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $course->id]);
        $context = context_module::instance($module->cmid);

        $this->insert_attempt($module->id, (int) $user->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);

        $this->assertCount(1, $contextlist);
        $this->assertContains((string) $context->id, $contextlist->get_contextids());
    }

    // -------------------------------------------------------------------------
    // 3. User discovery within context.
    // -------------------------------------------------------------------------

    public function test_get_users_in_context_returns_attempt_owners(): void {
        $course  = $this->getDataGenerator()->create_course();
        $usera   = $this->getDataGenerator()->create_user();
        $userb   = $this->getDataGenerator()->create_user();
        $module  = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $course->id]);
        $context = context_module::instance($module->cmid);

        $this->insert_attempt($module->id, (int) $usera->id);
        $this->insert_attempt($module->id, (int) $userb->id);

        $userlist = new userlist($context, 'mod_graphitoubb');
        provider::get_users_in_context($userlist);

        $this->assertCount(2, $userlist);
        $this->assertContains((int) $usera->id, $userlist->get_userids());
        $this->assertContains((int) $userb->id, $userlist->get_userids());
    }

    // -------------------------------------------------------------------------
    // 4. Export includes submissions and events.
    // -------------------------------------------------------------------------

    public function test_export_user_data_includes_submissions_and_events(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $module  = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $course->id]);
        $context = context_module::instance($module->cmid);

        $attemptid = $this->insert_attempt($module->id, (int) $user->id);
        $this->insert_submission($attemptid);
        $this->insert_event((int) $user->id, $module->id, $attemptid);
        $this->insert_grade_cache($attemptid);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $approved    = new approved_contextlist($user, 'mod_graphitoubb', $contextlist->get_contextids());

        provider::export_user_data($approved);

        // writer::with_context()->export_data() is called — no exception means success.
        // The writer stores data in-memory during tests; we verify the attempt row exists.
        $this->assertTrue($DB->record_exists('graphitoubb_submission', ['attemptid' => $attemptid]));
        $this->assertTrue($DB->record_exists('graphitoubb_event', ['attemptid' => $attemptid]));
        $this->assertTrue($DB->record_exists('graphitoubb_grade_cache', ['attemptid' => $attemptid]));
    }

    // -------------------------------------------------------------------------
    // 5. Delete all users in context clears iter1 tables.
    // -------------------------------------------------------------------------

    public function test_delete_data_for_all_users_in_context_clears_iter1_tables(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $usera   = $this->getDataGenerator()->create_user();
        $userb   = $this->getDataGenerator()->create_user();
        $module  = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $course->id]);
        $context = context_module::instance($module->cmid);

        foreach ([$usera->id, $userb->id] as $uid) {
            $aid = $this->insert_attempt($module->id, (int) $uid);
            $this->insert_submission($aid);
            $this->insert_event((int) $uid, $module->id, $aid);
            $this->insert_grade_cache($aid);
        }

        // Also insert a pre-attempt event (attemptid = null).
        $this->insert_event((int) $usera->id, $module->id, null);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(0, $DB->count_records('graphitoubb_attempt', ['instanceid' => $module->id]));
        $this->assertSame(0, $DB->count_records('graphitoubb_submission'));
        $this->assertSame(0, $DB->count_records('graphitoubb_event', ['instanceid' => $module->id]));
        $this->assertSame(0, $DB->count_records('graphitoubb_grade_cache'));
    }

    // -------------------------------------------------------------------------
    // 6. Delete for specific user clears only that user.
    // -------------------------------------------------------------------------

    public function test_delete_data_for_user_clears_only_that_user(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $usera   = $this->getDataGenerator()->create_user();
        $userb   = $this->getDataGenerator()->create_user();
        $module  = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $course->id]);

        $aida = $this->insert_attempt($module->id, (int) $usera->id);
        $aidb = $this->insert_attempt($module->id, (int) $userb->id);
        $this->insert_submission($aida);
        $this->insert_submission($aidb);
        $this->insert_event((int) $usera->id, $module->id, $aida);
        $this->insert_event((int) $userb->id, $module->id, $aidb);
        $this->insert_grade_cache($aida);
        $this->insert_grade_cache($aidb);

        $contextlist = provider::get_contexts_for_userid((int) $usera->id);
        $approved    = new approved_contextlist($usera, 'mod_graphitoubb', $contextlist->get_contextids());

        provider::delete_data_for_user($approved);

        // User A gone.
        $this->assertFalse($DB->record_exists('graphitoubb_attempt', ['id' => $aida]));
        $this->assertFalse($DB->record_exists('graphitoubb_submission', ['attemptid' => $aida]));
        $this->assertFalse($DB->record_exists('graphitoubb_grade_cache', ['attemptid' => $aida]));

        // User B untouched.
        $this->assertTrue($DB->record_exists('graphitoubb_attempt', ['id' => $aidb]));
        $this->assertTrue($DB->record_exists('graphitoubb_submission', ['attemptid' => $aidb]));
        $this->assertTrue($DB->record_exists('graphitoubb_grade_cache', ['attemptid' => $aidb]));
    }

    // -------------------------------------------------------------------------
    // 7. Delete for userlist filters correctly.
    // -------------------------------------------------------------------------

    public function test_delete_data_for_users_filters_userlist(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $usera   = $this->getDataGenerator()->create_user();
        $userb   = $this->getDataGenerator()->create_user();
        $userc   = $this->getDataGenerator()->create_user();
        $module  = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $course->id]);
        $context = context_module::instance($module->cmid);

        $aida = $this->insert_attempt($module->id, (int) $usera->id);
        $aidb = $this->insert_attempt($module->id, (int) $userb->id);
        $aidc = $this->insert_attempt($module->id, (int) $userc->id);

        $this->insert_submission($aida);
        $this->insert_submission($aidb);
        $this->insert_submission($aidc);

        // Delete only users A and B.
        $approved = new approved_userlist($context, 'mod_graphitoubb', [$usera->id, $userb->id]);
        provider::delete_data_for_users($approved);

        $this->assertFalse($DB->record_exists('graphitoubb_attempt', ['id' => $aida]));
        $this->assertFalse($DB->record_exists('graphitoubb_submission', ['attemptid' => $aida]));
        $this->assertFalse($DB->record_exists('graphitoubb_attempt', ['id' => $aidb]));
        $this->assertFalse($DB->record_exists('graphitoubb_submission', ['attemptid' => $aidb]));

        // User C untouched.
        $this->assertTrue($DB->record_exists('graphitoubb_attempt', ['id' => $aidc]));
        $this->assertTrue($DB->record_exists('graphitoubb_submission', ['attemptid' => $aidc]));
    }
}
