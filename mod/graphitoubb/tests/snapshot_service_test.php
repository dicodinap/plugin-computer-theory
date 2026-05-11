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

use mod_graphitoubb\attempt_service;
use mod_graphitoubb\snapshot_service;
use mod_graphitoubb\exception\rate_limited_exception;

/**
 * Tests for mod_graphitoubb snapshot_service.
 *
 * @package    mod_graphitoubb
 * @covers     \mod_graphitoubb\snapshot_service
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class snapshot_service_test extends advanced_testcase {
    /** @var snapshot_service */
    private snapshot_service $snapshots;
    /** @var int */
    private int $attemptid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();

        $instanceid = (int) $DB->insert_record('graphitoubb', [
            'course'       => (int) $course->id,
            'name'         => 'Snapshot test',
            'intro'        => '',
            'introformat'  => FORMAT_HTML,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $attempts = new attempt_service();
        $attempt  = $attempts->start_or_resume($instanceid, (int) $user->id);

        $this->attemptid = (int) $attempt->id;
        $this->snapshots = new snapshot_service();
    }

    public function test_save_returns_positive_id(): void {
        $id = $this->snapshots->save($this->attemptid, '{"states":[]}', 1);
        $this->assertGreaterThan(0, $id);
    }

    public function test_get_latest_returns_last_saved(): void {
        global $DB;

        // Insert directly to bypass rate limit and control timecreated.
        $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $this->attemptid,
            'payload'        => '{"states":["q0"]}',
            'schema_version' => 1,
            'timecreated'    => time() - 2,
        ]);
        $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $this->attemptid,
            'payload'        => '{"states":["q0","q1"]}',
            'schema_version' => 1,
            'timecreated'    => time() - 1,
        ]);

        $latest = $this->snapshots->get_latest($this->attemptid);

        $this->assertNotNull($latest);
        $this->assertStringContainsString('q1', $latest->payload);
    }

    public function test_count_for_attempt(): void {
        global $DB;

        $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $this->attemptid,
            'payload'        => '{"a":1}',
            'schema_version' => 1,
            'timecreated'    => time() - 2,
        ]);
        $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $this->attemptid,
            'payload'        => '{"a":2}',
            'schema_version' => 1,
            'timecreated'    => time() - 1,
        ]);

        $this->assertSame(2, $this->snapshots->count_for_attempt($this->attemptid));
    }

    public function test_rapid_save_throws_rate_limited_exception(): void {
        global $DB;

        // Simulate a just-saved snapshot (timecreated = now).
        $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $this->attemptid,
            'payload'        => '{"first":true}',
            'schema_version' => 1,
            'timecreated'    => time(),
        ]);

        $this->expectException(rate_limited_exception::class);
        $this->snapshots->save($this->attemptid, '{"second":true}', 1);
    }

    public function test_save_after_previous_second_succeeds(): void {
        global $DB;

        // Simulate a snapshot saved 1 second ago.
        $DB->insert_record('graphitoubb_snapshot', [
            'attemptid'      => $this->attemptid,
            'payload'        => '{"first":true}',
            'schema_version' => 1,
            'timecreated'    => time() - 1,
        ]);

        $id = $this->snapshots->save($this->attemptid, '{"second":true}', 1);
        $this->assertGreaterThan(0, $id);
    }

    public function test_get_latest_returns_null_for_empty(): void {
        $result = $this->snapshots->get_latest($this->attemptid);
        $this->assertNull($result);
    }

    public function test_count_returns_zero_for_empty(): void {
        $this->assertSame(0, $this->snapshots->count_for_attempt($this->attemptid));
    }

    public function test_save_with_validator_throws_on_malformed_json(): void {
        $svc = new snapshot_service(
            new \local_graphitoubb\tools\afd\domain\validator(),
            new \local_graphitoubb\tools\afd\domain\serializer()
        );
        $this->expectException(\moodle_exception::class);
        $svc->save($this->attemptid, 'not valid json', 1);
    }

    public function test_save_with_validator_throws_on_too_many_states(): void {
        $states = [];
        for ($i = 0; $i < 65; $i++) {
            $states[] = ['id' => 'q' . $i, 'label' => 'q' . $i];
        }
        $payload = json_encode([
            'states'      => $states,
            'alphabet'    => ['a'],
            'transitions' => [],
            'start'       => 'q0',
            'finals'      => ['q0'],
        ]);
        $svc = new snapshot_service(
            new \local_graphitoubb\tools\afd\domain\validator(),
            new \local_graphitoubb\tools\afd\domain\serializer()
        );
        $this->expectException(\moodle_exception::class);
        $svc->save($this->attemptid, $payload, 1);
    }

    public function test_save_without_validator_skips_validation(): void {
        $svc = new snapshot_service();
        $id  = $svc->save($this->attemptid, 'not valid json', 1);
        $this->assertGreaterThan(0, $id);
    }
}
