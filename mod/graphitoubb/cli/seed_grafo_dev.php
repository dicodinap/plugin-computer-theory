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
 * DEV-ONLY: seed grafo/arbol activities (one per preset) into the AFD-TEST course
 * for manual/Playwright testing. Idempotent by activity name. Prints cmids.
 *
 * Usage (inside container): php mod/graphitoubb/cli/seed_grafo_dev.php
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/graphitoubb/lib.php');

$course = $DB->get_record('course', ['shortname' => 'AFD-TEST'], '*', MUST_EXIST);

/**
 * Create (or reuse) a graphitoubb activity by name and set its problem payload.
 *
 * @param stdClass $course
 * @param string   $name
 * @param array    $payload
 * @return int cmid
 */
function seed_grafo_activity(stdClass $course, string $name, array $payload): int {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/modlib.php');

    $existing = $DB->get_record('graphitoubb', ['course' => $course->id, 'name' => $name]);
    if ($existing) {
        $cm = get_coursemodule_from_instance('graphitoubb', $existing->id, $course->id);
        (new \mod_graphitoubb\problem_repository())->save(
            (int) $existing->id, (string) $payload['tool'], (string) $payload['type'], $payload, 1);
        cli_writeln("reused {$name}: cmid={$cm->id} instance={$existing->id}");
        return (int) $cm->id;
    }

    $moduleinfo = (object) [
        'modulename'       => 'graphitoubb',
        'module'           => (int) $DB->get_field('modules', 'id', ['name' => 'graphitoubb'], MUST_EXIST),
        'course'           => $course->id,
        'section'          => 1,
        'name'             => $name,
        'intro'            => '',
        'introformat'      => FORMAT_HTML,
        'visible'          => 1,
        'attempts_policy'  => 'best',
    ];
    $created = add_moduleinfo($moduleinfo, $course);
    (new \mod_graphitoubb\problem_repository())->save(
        (int) $created->instance, (string) $payload['tool'], (string) $payload['type'], $payload, 1);
    cli_writeln("created {$name}: cmid={$created->coursemodule} instance={$created->instance}");
    return (int) $created->coursemodule;
}

$catalog = new \local_graphitoubb\catalog\preset_catalog();
foreach (array_merge($catalog->all('grafo'), $catalog->all('arbol')) as $p) {
    $cmid = seed_grafo_activity($course, 'GT ' . $p->key, $p->payload);
    cli_writeln('  -> view: /mod/graphitoubb/view.php?id=' . $cmid
        . '   edit: /mod/graphitoubb/edit_problem.php?id=' . $cmid);
}
cli_writeln('done.');
