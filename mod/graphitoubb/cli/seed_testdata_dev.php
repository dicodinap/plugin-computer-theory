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
 * DEV-ONLY: seed a course, teacher/student/manager users and enrolments
 * for manual/Playwright testing of the AFD flow. Idempotent.
 *
 * Usage (inside container): php mod/graphitoubb/cli/seed_testdata_dev.php
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');

if (!debugging('', DEBUG_DEVELOPER) && empty($CFG->debugdeveloper)) {
    // Allow anyway in dev docker; just a soft note.
    cli_writeln('[note] developer debugging is off, continuing anyway (dev container).');
}

/**
 * Ensure a user exists with the given username; return its record.
 *
 * @param string $username
 * @param string $firstname
 * @return stdClass
 */
function seed_user(string $username, string $firstname): stdClass {
    global $DB, $CFG;
    $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if ($existing) {
        return $existing;
    }
    $user = create_user_record($username, 'Test1234#', 'manual');
    $user->firstname = $firstname;
    $user->lastname = 'AFD';
    $user->email = $username . '@example.com';
    $user->confirmed = 1;
    $user->mnethostid = $CFG->mnet_localhost_id;
    $DB->update_record('user', $user);
    cli_writeln("created user: {$username} (id {$user->id})");
    return $DB->get_record('user', ['id' => $user->id]);
}

// 1. Course.
$shortname = 'AFD-TEST';
$course = $DB->get_record('course', ['shortname' => $shortname]);
if (!$course) {
    $category = $DB->get_record('course_categories', ['id' => 1]) ?: $DB->get_records('course_categories', [], 'id', '*', 0, 1);
    $catid = is_object($category) ? $category->id : reset($category)->id;
    $course = create_course((object) [
        'fullname' => 'AFD Test Course',
        'shortname' => $shortname,
        'category' => $catid,
        'format' => 'topics',
        'numsections' => 3,
        'visible' => 1,
    ]);
    cli_writeln("created course: {$shortname} (id {$course->id})");
} else {
    cli_writeln("course exists: {$shortname} (id {$course->id})");
}

// 2. Users.
$teacher = seed_user('teacher1', 'Teacher');
$student = seed_user('student1', 'Student');
$student2 = seed_user('student2', 'Student2');
$manager = seed_user('manager1', 'Manager');

// 3. Enrolments.
$context = context_course::instance($course->id);
$roles = $DB->get_records_menu('role', null, '', 'shortname,id');

/**
 * Enrol a user with a role into the course (idempotent).
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param int $roleid
 * @return void
 */
function seed_enrol(stdClass $course, stdClass $user, int $roleid): void {
    global $DB;
    $enrol = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
    if (!$instance) {
        $enrolid = $enrol->add_instance($course);
        $instance = $DB->get_record('enrol', ['id' => $enrolid]);
    }
    $enrol->enrol_user($instance, $user->id, $roleid);
}

seed_enrol($course, $teacher, $roles['editingteacher']);
seed_enrol($course, $student, $roles['student']);
seed_enrol($course, $student2, $roles['student']);
seed_enrol($course, $manager, $roles['student']); // manager also enrolled as student baseline.

// System-level manager role assignment.
$syscontext = context_system::instance();
role_assign($roles['manager'], $manager->id, $syscontext->id);

cli_writeln('enrolments done: teacher1=editingteacher, student1/student2=student, manager1=manager(system)+student');
cli_writeln('seed complete. Course id=' . $course->id);
