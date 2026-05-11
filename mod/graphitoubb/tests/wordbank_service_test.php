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
use mod_graphitoubb\wordbank_service;

/**
 * Tests for mod_graphitoubb wordbank_service.
 *
 * @package    mod_graphitoubb
 * @covers     \mod_graphitoubb\wordbank_service
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wordbank_service_test extends advanced_testcase {
    /** @var wordbank_service */
    private wordbank_service $wordbank;
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
            'name'         => 'Wordbank test',
            'intro'        => '',
            'introformat'  => FORMAT_HTML,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $attempts       = new attempt_service();
        $attempt        = $attempts->start_or_resume($instanceid, (int) $user->id);
        $this->attemptid = (int) $attempt->id;
        $this->wordbank  = new wordbank_service();
    }

    public function test_log_returns_positive_id(): void {
        $id = $this->wordbank->log($this->attemptid, 'abc', true);
        $this->assertGreaterThan(0, $id);
    }

    public function test_list_for_attempt_returns_all_entries(): void {
        $this->wordbank->log($this->attemptid, 'ab', true);
        $this->wordbank->log($this->attemptid, 'ba', false);

        $list = $this->wordbank->list_for_attempt($this->attemptid);

        $this->assertCount(2, $list);
    }

    public function test_list_for_attempt_preserves_insertion_order(): void {
        $this->wordbank->log($this->attemptid, 'first', true);
        $this->wordbank->log($this->attemptid, 'second', false);

        $list = $this->wordbank->list_for_attempt($this->attemptid);

        $this->assertSame('first', $list[0]->word);
        $this->assertSame('second', $list[1]->word);
    }

    public function test_list_for_attempt_returns_empty_for_no_entries(): void {
        $list = $this->wordbank->list_for_attempt($this->attemptid);
        $this->assertCount(0, $list);
    }

    public function test_accepted_flag_persisted_correctly(): void {
        $this->wordbank->log($this->attemptid, 'valid', true);
        $this->wordbank->log($this->attemptid, 'invalid', false);

        $list = $this->wordbank->list_for_attempt($this->attemptid);

        $this->assertSame(1, (int) $list[0]->accepted);
        $this->assertSame(0, (int) $list[1]->accepted);
    }

    public function test_limit_param_is_respected(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->wordbank->log($this->attemptid, "word$i", true);
        }

        $list = $this->wordbank->list_for_attempt($this->attemptid, 3);
        $this->assertCount(3, $list);
    }
}
