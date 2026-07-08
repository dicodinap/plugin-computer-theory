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
 * Unit tests for the RF_04 submission gate (AC5 + I6).
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_graphitoubb;

/**
 * @covers \mod_graphitoubb\submission_gate
 */
final class submission_gate_test extends \advanced_testcase {

    /**
     * Insert a graphitoubb instance row with the given gate settings.
     *
     * @param  array $overrides
     * @return \stdClass
     */
    private function make_instance(array $overrides = []): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $rec = (object) array_merge([
            'course' => $course->id, 'name' => 'Gate test', 'intro' => '', 'introformat' => 1,
            'timemodified' => time(), 'timecreated' => time(),
            'attempts_policy' => 'best', 'attempts_max' => 1, 'close_behavior' => 'auto_submit',
            'timeopen' => 0, 'timeclose' => 0,
        ], $overrides);
        $rec->id = $DB->insert_record('graphitoubb', $rec);
        return $rec;
    }

    /**
     * Record N submissions for $userid on $instance (via one attempt).
     *
     * @param  int $instanceid
     * @param  int $userid
     * @param  int $n
     * @return void
     */
    private function record_submissions(int $instanceid, int $userid, int $n): void {
        global $DB;
        $attemptid = $DB->insert_record('graphitoubb_attempt', (object) [
            'instanceid' => $instanceid, 'userid' => $userid, 'status' => 'finished',
            'timestarted' => time(), 'timefinished' => time(),
        ]);
        for ($i = 0; $i < $n; $i++) {
            $DB->insert_record('graphitoubb_submission', (object) [
                'attemptid' => $attemptid, 'payload' => '{}', 'payload_hash' => 'x',
                'problem_snapshot_hash' => 'y', 'score' => 0, 'fraction' => 0, 'passed' => 0,
                'grading_result' => '{}', 'schema_version' => 1, 'timecreated' => time(),
            ]);
        }
    }

    /** AC5: closed activity (timeclose in the past) ⇒ blocked with reason 'closed'. */
    public function test_closed(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $inst = $this->make_instance(['timeclose' => time() - 100, 'attempts_max' => null]);
        $g = submission_gate::check($inst, (int) $user->id);
        $this->assertFalse($g['allowed']);
        $this->assertSame('closed', $g['reason']);
    }

    /** AC5: not-yet-open (timeopen in the future) ⇒ blocked with reason 'not_open'. */
    public function test_not_open(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $inst = $this->make_instance(['timeopen' => time() + 1000, 'attempts_max' => null]);
        $g = submission_gate::check($inst, (int) $user->id);
        $this->assertFalse($g['allowed']);
        $this->assertSame('not_open', $g['reason']);
    }

    /** AC5: at attempts_max with a submission already recorded ⇒ 'no_attempts'. */
    public function test_no_attempts(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $inst = $this->make_instance(['attempts_max' => 1]);
        $this->record_submissions((int) $inst->id, (int) $user->id, 1);
        $g = submission_gate::check($inst, (int) $user->id);
        $this->assertFalse($g['allowed']);
        $this->assertSame('no_attempts', $g['reason']);
    }

    /** AC5 positive: open window, an attempt available ⇒ allowed. */
    public function test_allowed_when_available(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $inst = $this->make_instance(['attempts_max' => 1]);
        $g = submission_gate::check($inst, (int) $user->id);
        $this->assertTrue($g['allowed']);
        $this->assertNull($g['reason']);
    }

    /** I6: attempts_max = NULL (unlimited), no dates ⇒ always allowed, even after submits. */
    public function test_i6_unlimited_no_lock(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $inst = $this->make_instance(['attempts_max' => null]);
        $this->record_submissions((int) $inst->id, (int) $user->id, 5);
        $g = submission_gate::check($inst, (int) $user->id);
        $this->assertTrue($g['allowed']);
    }
}
