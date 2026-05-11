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
 * Student/teacher entry point for mod_graphitoubb.
 *
 * No declare(strict_types=1) — Moodle entry-point convention.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = optional_param('id', 0, PARAM_INT);
$g  = optional_param('g', 0, PARAM_INT);

if ($id) {
    $cm       = get_coursemodule_from_id('graphitoubb', $id, 0, false, MUST_EXIST);
    $course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $instance = $DB->get_record('graphitoubb', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($g) {
    $instance = $DB->get_record('graphitoubb', ['id' => $g], '*', MUST_EXIST);
    $course   = $DB->get_record('course', ['id' => $instance->course], '*', MUST_EXIST);
    $cm       = get_coursemodule_from_instance('graphitoubb', $instance->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('invalidcoursemodule');
}

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/graphitoubb:view', $context);

$PAGE->set_url('/mod/graphitoubb/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

$canviewreport = has_capability('mod/graphitoubb:viewreport', $context);
$canattempt    = has_capability('mod/graphitoubb:attempt', $context);

if (!$canviewreport && !$canattempt) {
    require_capability('mod/graphitoubb:attempt', $context);
}

$renderer = $PAGE->get_renderer('mod_graphitoubb');

echo $OUTPUT->header();

if ($canattempt) {
    $service = new \mod_graphitoubb\attempt_service();
    $attempt = $service->start_or_resume((int) $instance->id, (int) $USER->id);
    echo $renderer->render_editor((int) $attempt->id, (int) $instance->id, 1);
}

echo $renderer->render_view_links((int) $cm->id, $canviewreport, $canattempt);

echo $OUTPUT->footer();
