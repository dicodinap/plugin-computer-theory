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
 * Upgrade script for mod_graphitoubb.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade mod_graphitoubb to the given version.
 *
 * @param int $oldversion Previous plugin version.
 * @return bool True on success.
 */
function xmldb_graphitoubb_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026051800) {
        // -----------------------------------------------------------------------
        // 1. Add current_draft + draft_updated_at to graphitoubb_attempt.
        // -----------------------------------------------------------------------
        $table = new xmldb_table('graphitoubb_attempt');

        $field = new xmldb_field('current_draft', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timefinished');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('draft_updated_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'current_draft');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Drop old unique index (single attempt per user) to allow multi-attempt.
        $index = new xmldb_index('uq_instance_user', XMLDB_INDEX_UNIQUE, ['instanceid', 'userid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Add non-unique replacement index.
        $index = new xmldb_index('idx_instance_user', XMLDB_INDEX_NOTUNIQUE, ['instanceid', 'userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // -----------------------------------------------------------------------
        // 2. Add attempts_policy, attempts_max, close_behavior to graphitoubb.
        // -----------------------------------------------------------------------
        $table = new xmldb_table('graphitoubb');

        $field = new xmldb_field('attempts_policy', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'best', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('attempts_max', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'attempts_policy');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('close_behavior', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'auto_submit', 'attempts_max');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // -----------------------------------------------------------------------
        // 3. Create graphitoubb_problem table.
        // -----------------------------------------------------------------------
        $table = new xmldb_table('graphitoubb_problem');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('tool', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, '');
            $table->add_field('type', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, '');
            $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('payload_hash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('schema_version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_instanceid', XMLDB_KEY_FOREIGN, ['instanceid'], 'graphitoubb', ['id']);
            $table->add_index('idx_tool_type', XMLDB_INDEX_NOTUNIQUE, ['tool']);
            $dbman->create_table($table);
        }

        // -----------------------------------------------------------------------
        // 4. Create graphitoubb_submission table.
        // -----------------------------------------------------------------------
        $table = new xmldb_table('graphitoubb_submission');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('payload_hash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('problem_snapshot_hash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('score', XMLDB_TYPE_NUMBER, '10', null, XMLDB_NOTNULL, null, '0', null, 4);
            $table->add_field('fraction', XMLDB_TYPE_NUMBER, '10', null, XMLDB_NOTNULL, null, '0', null, 4);
            $table->add_field('passed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('grading_result', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('schema_version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_attemptid', XMLDB_KEY_FOREIGN, ['attemptid'], 'graphitoubb_attempt', ['id']);
            $dbman->create_table($table);
        }

        // -----------------------------------------------------------------------
        // 5. Create graphitoubb_event table.
        // -----------------------------------------------------------------------
        $table = new xmldb_table('graphitoubb_event');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('name', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('ix_inst_name', XMLDB_INDEX_NOTUNIQUE, ['instanceid', 'name']);
            $dbman->create_table($table);
        }

        // -----------------------------------------------------------------------
        // 6. Create graphitoubb_grade_cache table.
        // -----------------------------------------------------------------------
        $table = new xmldb_table('graphitoubb_grade_cache');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('score', XMLDB_TYPE_NUMBER, '10', null, XMLDB_NOTNULL, null, '0', null, 4);
            $table->add_field('fraction', XMLDB_TYPE_NUMBER, '10', null, XMLDB_NOTNULL, null, '0', null, 4);
            $table->add_field('attempt_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('policy_applied', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'best');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_attemptid', XMLDB_KEY_FOREIGN, ['attemptid'], 'graphitoubb_attempt', ['id']);
            $table->add_index('uq_attemptid', XMLDB_INDEX_UNIQUE, ['attemptid']);
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026051800, 'graphitoubb');
    }

    if ($oldversion < 2026070600) {
        // RF_04 submission gate (D13): add timeopen/timeclose to graphitoubb.
        // Existing rows default to 0 (no window) ⇒ no retroactive lock (I6).
        $table = new xmldb_table('graphitoubb');

        $field = new xmldb_field('timeopen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'close_behavior');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timeclose', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeopen');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026070600, 'graphitoubb');
    }

    return true;
}
