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

use advanced_testcase;

/**
 * Tests for mod_graphitoubb report_repository.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_graphitoubb\report_repository
 */
final class report_repository_test extends advanced_testcase {
    /** @var \stdClass Course fixture. */
    private \stdClass $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Returns zero rows when no attempts exist for the instance.
     *
     * @covers \mod_graphitoubb\report_repository::list_attempts_for_instance
     */
    public function test_list_attempts_zero_for_empty_instance(): void {
        $instance = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $this->course->id]);
        $repo = new report_repository();
        $result = $repo->list_attempts_for_instance((int) $instance->id);
        $this->assertCount(0, $result);
    }

    /**
     * Returns one row with correct fields when a single attempt exists.
     *
     * @covers \mod_graphitoubb\report_repository::list_attempts_for_instance
     */
    public function test_list_attempts_returns_one_row(): void {
        $instance = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $this->course->id]);
        $user     = $this->getDataGenerator()->create_user();
        $service  = new attempt_service();
        $service->start_or_resume((int) $instance->id, (int) $user->id);

        $repo   = new report_repository();
        $result = $repo->list_attempts_for_instance((int) $instance->id);

        $this->assertCount(1, $result);
        $this->assertEquals($user->id, $result[0]->userid);
        $this->assertEquals('inprogress', $result[0]->status);
        $this->assertEquals(0, (int) $result[0]->snapshot_count);
        $this->assertNull($result[0]->last_word_tested);
    }

    /**
     * Returns N rows for N distinct users and aggregates snapshot_count correctly.
     *
     * @covers \mod_graphitoubb\report_repository::list_attempts_for_instance
     */
    public function test_list_attempts_aggregates_snapshots_and_last_word(): void {
        global $DB;

        $instance = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $this->course->id]);
        $user1    = $this->getDataGenerator()->create_user();
        $user2    = $this->getDataGenerator()->create_user();
        $service  = new attempt_service();
        $attempt1 = $service->start_or_resume((int) $instance->id, (int) $user1->id);
        $service->start_or_resume((int) $instance->id, (int) $user2->id);

        $now = time();

        // Two snapshots for attempt1.
        $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $attempt1->id,
            'schema_version' => 1,
            'payload'        => '{}',
            'timecreated'    => $now,
        ]);
        $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $attempt1->id,
            'schema_version' => 1,
            'payload'        => '{}',
            'timecreated'    => $now + 1,
        ]);

        // Two wordbank entries for attempt1; 'xyz' is the later one.
        $DB->insert_record('graphitoubb_wordbank_log', [
            'attemptid'   => $attempt1->id,
            'word'        => 'abc',
            'accepted'    => 1,
            'timecreated' => $now,
        ]);
        $DB->insert_record('graphitoubb_wordbank_log', [
            'attemptid'   => $attempt1->id,
            'word'        => 'xyz',
            'accepted'    => 0,
            'timecreated' => $now + 5,
        ]);

        $repo   = new report_repository();
        $result = $repo->list_attempts_for_instance((int) $instance->id);

        $this->assertCount(2, $result);

        // Find rows by userid — ordering by timestarted may be non-deterministic
        // when both attempts start within the same second.
        $row1 = null;
        $row2 = null;
        foreach ($result as $row) {
            if ((int) $row->userid === (int) $user1->id) {
                $row1 = $row;
            } else {
                $row2 = $row;
            }
        }

        $this->assertNotNull($row1, 'Row for user1 must be present.');
        $this->assertEquals(2, (int) $row1->snapshot_count);
        $this->assertEquals('xyz', $row1->last_word_tested);

        $this->assertNotNull($row2, 'Row for user2 must be present.');
        $this->assertEquals(0, (int) $row2->snapshot_count);
        $this->assertNull($row2->last_word_tested);
    }

    /**
     * Returns only attempts for the queried instance, not sibling instances.
     *
     * @covers \mod_graphitoubb\report_repository::list_attempts_for_instance
     */
    public function test_list_attempts_scoped_to_instance(): void {
        $instance1 = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $this->course->id]);
        $instance2 = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $this->course->id]);
        $user      = $this->getDataGenerator()->create_user();
        $service   = new attempt_service();
        $service->start_or_resume((int) $instance1->id, (int) $user->id);

        $repo = new report_repository();
        $this->assertCount(1, $repo->list_attempts_for_instance((int) $instance1->id));
        $this->assertCount(0, $repo->list_attempts_for_instance((int) $instance2->id));
    }
}
