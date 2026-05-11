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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\tests\provider_testcase;
use mod_graphitoubb\privacy\provider;

/**
 * Privacy provider tests for mod_graphitoubb.
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

    // Metadata declaration.

    public function test_get_metadata_declares_all_tables(): void {
        $collection = new collection('mod_graphitoubb');
        $collection = provider::get_metadata($collection);

        $tablenames = [];
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $tablenames[] = $item->get_name();
            }
        }

        $this->assertContains('graphitoubb_attempt', $tablenames);
        $this->assertContains('graphitoubb_snapshot', $tablenames);
        $this->assertContains('graphitoubb_wordbank_log', $tablenames);
    }

    // Context discovery.

    public function test_get_contexts_for_userid_returns_empty_when_no_data(): void {
        $user        = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertCount(0, $contextlist);
    }

    public function test_get_contexts_for_userid_returns_module_context(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $module  = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $course->id]);
        $context = context_module::instance($module->cmid);

        $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $module->id,
            'userid'      => $user->id,
            'status'      => 'inprogress',
            'timestarted' => time(),
        ]);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);

        $this->assertCount(1, $contextlist);
        $contextids = $contextlist->get_contextids();
        $this->assertContains((string) $context->id, $contextids);
    }

    // User discovery within a context.

    public function test_get_users_in_context(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $usera   = $this->getDataGenerator()->create_user();
        $userb   = $this->getDataGenerator()->create_user();
        $module  = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $course->id]);
        $context = context_module::instance($module->cmid);

        foreach ([$usera->id, $userb->id] as $uid) {
            $DB->insert_record('graphitoubb_attempt', [
                'instanceid'  => $module->id,
                'userid'      => $uid,
                'status'      => 'inprogress',
                'timestarted' => time(),
            ]);
        }

        $userlist = new userlist($context, 'mod_graphitoubb');
        provider::get_users_in_context($userlist);

        $this->assertCount(2, $userlist);
    }

    // Delete: per-user.

    public function test_delete_data_for_user_removes_attempt_and_children(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $module  = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $course->id]);
        $context = context_module::instance($module->cmid);

        $attemptid = $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $module->id,
            'userid'      => $user->id,
            'status'      => 'inprogress',
            'timestarted' => time(),
        ]);

        $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $attemptid,
            'payload'        => '{"states":[]}',
            'schema_version' => 1,
            'timecreated'    => time(),
        ]);

        $DB->insert_record('graphitoubb_wordbank_log', [
            'attemptid'   => $attemptid,
            'word'        => 'ab',
            'accepted'    => 1,
            'timecreated' => time(),
        ]);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $approved    = new approved_contextlist($user, 'mod_graphitoubb', $contextlist->get_contextids());

        provider::delete_data_for_user($approved);

        $this->assertFalse($DB->record_exists('graphitoubb_attempt', ['id' => $attemptid]));
        $this->assertFalse($DB->record_exists('graphitoubb_snapshot', ['attemptid' => $attemptid]));
        $this->assertFalse($DB->record_exists('graphitoubb_wordbank_log', ['attemptid' => $attemptid]));
    }

    // Delete: all users in context.

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $usera   = $this->getDataGenerator()->create_user();
        $userb   = $this->getDataGenerator()->create_user();
        $module  = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $course->id]);
        $context = context_module::instance($module->cmid);

        foreach ([$usera->id, $userb->id] as $uid) {
            $attemptid = $DB->insert_record('graphitoubb_attempt', [
                'instanceid'  => $module->id,
                'userid'      => $uid,
                'status'      => 'inprogress',
                'timestarted' => time(),
            ]);
            $DB->insert_record('graphitoubb_snapshot', [
                'attemptid'      => $attemptid,
                'payload'        => '{}',
                'schema_version' => 1,
                'timecreated'    => time(),
            ]);
        }

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(
            0,
            $DB->count_records('graphitoubb_attempt', ['instanceid' => $module->id])
        );
        $this->assertSame(0, $DB->count_records('graphitoubb_snapshot'));
    }
}
