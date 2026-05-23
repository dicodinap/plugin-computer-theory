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
 * XMLDB upgrade tests for mod_graphitoubb.
 *
 * These tests call xmldb_graphitoubb_upgrade() directly to verify that the
 * upgrade function is idempotent and that it creates the expected schema.
 *
 * @package    mod_graphitoubb
 * @covers     ::xmldb_graphitoubb_upgrade
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upgrade_test extends \advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        // upgrade_mod_savepoint() lives in upgradelib.php — load before the upgrade script.
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../../db/upgrade.php');
    }

    /**
     * Verifies that running upgrade to 2026051800 creates all expected tables
     * and fields, and that running it again does not throw errors (idempotency).
     */
    public function test_upgrade_2026051800_adds_fields_idempotently(): void {
        global $DB;

        $dbman = $DB->get_manager();

        // Roll the recorded plugin version back so upgrade_mod_savepoint() does not
        // see this as a downgrade. The test installs the plugin at 2026051800 already;
        // we simulate a fresh upgrade by rewinding the stored version.
        set_config('version', 2025010101, 'mod_graphitoubb');

        // --- First run: simulate upgrading from before 2026051800. ---
        $result = xmldb_graphitoubb_upgrade(2025010101);
        $this->assertTrue($result);

        // graphitoubb_attempt: verify iter1 draft fields.
        $table = new xmldb_table('graphitoubb_attempt');
        $this->assertTrue($dbman->field_exists($table, new xmldb_field('current_draft')));
        $this->assertTrue($dbman->field_exists($table, new xmldb_field('draft_updated_at')));

        // graphitoubb: verify policy fields.
        $table = new xmldb_table('graphitoubb');
        $this->assertTrue($dbman->field_exists($table, new xmldb_field('attempts_policy')));
        $this->assertTrue($dbman->field_exists($table, new xmldb_field('attempts_max')));
        $this->assertTrue($dbman->field_exists($table, new xmldb_field('close_behavior')));

        // iter1 tables.
        $this->assertTrue($dbman->table_exists(new xmldb_table('graphitoubb_problem')));
        $this->assertTrue($dbman->table_exists(new xmldb_table('graphitoubb_submission')));
        $this->assertTrue($dbman->table_exists(new xmldb_table('graphitoubb_event')));
        $this->assertTrue($dbman->table_exists(new xmldb_table('graphitoubb_grade_cache')));

        // graphitoubb_submission fields.
        $table = new xmldb_table('graphitoubb_submission');
        foreach (
            ['attemptid', 'payload', 'payload_hash', 'problem_snapshot_hash',
                  'score', 'fraction', 'passed', 'grading_result', 'schema_version', 'timecreated'] as $field
        ) {
            $this->assertTrue(
                $dbman->field_exists($table, new xmldb_field($field)),
                "graphitoubb_submission.$field should exist after upgrade"
            );
        }

        // graphitoubb_event fields.
        $table = new xmldb_table('graphitoubb_event');
        foreach (['userid', 'instanceid', 'attemptid', 'name', 'payload', 'timecreated'] as $field) {
            $this->assertTrue(
                $dbman->field_exists($table, new xmldb_field($field)),
                "graphitoubb_event.$field should exist after upgrade"
            );
        }

        // graphitoubb_grade_cache fields.
        $table = new xmldb_table('graphitoubb_grade_cache');
        foreach (['attemptid', 'score', 'fraction', 'attempt_count', 'policy_applied', 'timemodified'] as $field) {
            $this->assertTrue(
                $dbman->field_exists($table, new xmldb_field($field)),
                "graphitoubb_grade_cache.$field should exist after upgrade"
            );
        }

        // --- Second run: idempotency — must not throw, must still return true.
        // Rewind version again because the first run advanced it to 2026051800.
        set_config('version', 2025010101, 'mod_graphitoubb');
        $result2 = xmldb_graphitoubb_upgrade(2025010101);
        $this->assertTrue($result2);

        // Schema still intact after second run.
        $this->assertTrue($dbman->table_exists(new xmldb_table('graphitoubb_submission')));
        $this->assertTrue($dbman->table_exists(new xmldb_table('graphitoubb_event')));
        $this->assertTrue($dbman->table_exists(new xmldb_table('graphitoubb_grade_cache')));
    }
}
