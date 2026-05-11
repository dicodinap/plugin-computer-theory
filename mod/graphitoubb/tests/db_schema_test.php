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

/**
 * DB schema integrity tests for mod_graphitoubb.
 *
 * Verifies the UNIQUE(instanceid, userid) constraint on graphitoubb_attempt (R-5).
 *
 * @package    mod_graphitoubb
 * @coversNothing
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class db_schema_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../lib.php');
    }

    public function test_unique_attempt_constraint_rejects_duplicate(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();

        $data              = new stdClass();
        $data->course      = $course->id;
        $data->name        = 'Schema integrity test';
        $data->intro       = '';
        $data->introformat = FORMAT_HTML;

        $instanceid = graphitoubb_add_instance($data);

        $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $instanceid,
            'userid'      => $user->id,
            'status'      => 'inprogress',
            'timestarted' => time(),
        ]);

        $this->expectException(dml_write_exception::class);

        $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $instanceid,
            'userid'      => $user->id,
            'status'      => 'inprogress',
            'timestarted' => time(),
        ]);
    }

    public function test_different_users_can_each_have_one_attempt(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $usera  = $this->getDataGenerator()->create_user();
        $userb  = $this->getDataGenerator()->create_user();

        $data              = new stdClass();
        $data->course      = $course->id;
        $data->name        = 'Multi-user schema test';
        $data->intro       = '';
        $data->introformat = FORMAT_HTML;

        $instanceid = graphitoubb_add_instance($data);

        $ida = $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $instanceid,
            'userid'      => $usera->id,
            'status'      => 'inprogress',
            'timestarted' => time(),
        ]);

        $idb = $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $instanceid,
            'userid'      => $userb->id,
            'status'      => 'inprogress',
            'timestarted' => time(),
        ]);

        $this->assertGreaterThan(0, $ida);
        $this->assertGreaterThan(0, $idb);
        $this->assertNotSame($ida, $idb);
    }

    public function test_same_user_can_attempt_different_instances(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();

        $data              = new stdClass();
        $data->course      = $course->id;
        $data->name        = 'Instance A';
        $data->intro       = '';
        $data->introformat = FORMAT_HTML;

        $instancea = graphitoubb_add_instance($data);
        $data->name = 'Instance B';
        $instanceb = graphitoubb_add_instance($data);

        $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $instancea,
            'userid'      => $user->id,
            'status'      => 'inprogress',
            'timestarted' => time(),
        ]);

        $id = $DB->insert_record('graphitoubb_attempt', [
            'instanceid'  => $instanceb,
            'userid'      => $user->id,
            'status'      => 'inprogress',
            'timestarted' => time(),
        ]);

        $this->assertGreaterThan(0, $id);
    }
}
