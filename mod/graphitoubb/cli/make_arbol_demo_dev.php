<?php
// DEV-ONLY: provision arbol demo activities from the shipped catalogue presets,
// exactly the way the teacher activity picker does (preset payload -> problem_repository).
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/modlib.php');

use local_graphitoubb\catalog\preset_catalog;
use mod_graphitoubb\problem_repository;

$course   = $DB->get_record('course', ['shortname' => 'AFD-TEST'], '*', MUST_EXIST);
$moduleid = (int) $DB->get_field('modules', 'id', ['name' => 'graphitoubb'], MUST_EXIST);
$catalog  = new preset_catalog();

// Which catalogue exercises to instantiate.
$keys = ['arbol_traversal_inorder', 'arbol_bst_build_basic'];

foreach ($keys as $key) {
    $preset = $catalog->get($key);
    if (!$preset) {
        cli_writeln("!! preset {$key} not found, skipping");
        continue;
    }

    // 1. Create the activity instance (course module + gradebook wiring).
    $moduleinfo = (object) [
        'modulename'      => 'graphitoubb',
        'module'          => $moduleid,
        'course'          => $course->id,
        'section'         => 0,
        'visible'         => 1,
        'name'            => $preset->title,      // localised title from the catalogue.
        'intro'           => '',
        'introformat'     => FORMAT_HTML,
        'attempts_policy' => 'best',
        'close_behavior'  => 'auto_submit',
    ];
    $cm = add_moduleinfo($moduleinfo, $course);
    $instanceid = (int) $cm->instance;

    // 2. Persist the problem payload (the canonical, grade-ready payload).
    $payload = $preset->payload;
    (new problem_repository())->save($instanceid, $preset->tool, (string) $payload['type'], $payload, 1);

    cli_writeln(sprintf('OK  %-26s -> "%s"', $key, $preset->title));
    cli_writeln(sprintf('    view: %s/mod/graphitoubb/view.php?id=%d', $CFG->wwwroot, $cm->coursemodule));
}

cli_writeln('done. Log in as student1 / Test1234#  (course: ' . $course->fullname . ')');
