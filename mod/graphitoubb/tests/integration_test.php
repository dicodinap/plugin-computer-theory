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
 * Full happy-path integration test for mod_graphitoubb.
 *
 * Exercises the complete pipeline: course -> module -> attempt -> snapshot -> word -> finish -> report.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

declare(strict_types=1);

use mod_graphitoubb\attempt_service;
use mod_graphitoubb\report_repository;
use mod_graphitoubb\snapshot_service;
use mod_graphitoubb\wordbank_service;

/**
 * Full integration test: create -> attempt -> snapshot -> word -> finish -> report.
 *
 * @coversNothing
 */
final class integration_test extends advanced_testcase {
    /**
     * Return a minimal valid AFD JSON payload (at_least_one_a, graphitoubb canonical format).
     *
     * @return string
     */
    private function valid_afd_json(): string {
        return json_encode([
            'schema_version' => 1,
            'states'         => [['id' => 'q0'], ['id' => 'q1']],
            'alphabet'       => ['a', 'b'],
            'transitions'    => [
                ['from' => 'q0', 'symbol' => 'a', 'to' => 'q1'],
                ['from' => 'q0', 'symbol' => 'b', 'to' => 'q0'],
                ['from' => 'q1', 'symbol' => 'a', 'to' => 'q1'],
                ['from' => 'q1', 'symbol' => 'b', 'to' => 'q0'],
            ],
            'start'  => 'q0',
            'finals' => ['q1'],
        ]);
    }

    /**
     * Happy-path lifecycle: start attempt -> save snapshot -> log word -> finish -> verify report.
     */
    public function test_happy_path_attempt_lifecycle(): void {
        $this->resetAfterTest();

        $course   = $this->getDataGenerator()->create_course();
        $user     = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('graphitoubb', ['course' => $course->id]);

        $instanceid = (int) $instance->id;
        $userid     = (int) $user->id;

        // 1. Start attempt.
        $attempts = new attempt_service();
        $attempt  = $attempts->start_or_resume($instanceid, $userid);
        $this->assertGreaterThan(0, (int) $attempt->id);

        // 2. Save a valid snapshot using injected validator + serializer.
        $snapshots  = new snapshot_service(
            new \local_graphitoubb\tools\afd\domain\validator(),
            new \local_graphitoubb\tools\afd\domain\serializer()
        );
        $snapshotid = $snapshots->save((int) $attempt->id, $this->valid_afd_json(), 1);
        $this->assertGreaterThan(0, $snapshotid);

        // 3. Log a word via wordbank_service.
        $wordbank = new wordbank_service();
        $wordid   = $wordbank->log((int) $attempt->id, 'aba', true);
        $this->assertGreaterThan(0, $wordid);

        // 4. Finish the attempt.
        $attempts->finish((int) $attempt->id);

        // 5. Verify aggregates via report_repository.
        $repo   = new report_repository();
        $result = $repo->list_attempts_for_instance($instanceid);

        $this->assertCount(1, $result);
        $row = $result[0];
        $this->assertEquals($userid, (int) $row->userid);
        $this->assertSame('finished', $row->status);
        $this->assertEquals(1, (int) $row->snapshot_count);
        $this->assertNotNull($row->last_word_tested);
    }
}
