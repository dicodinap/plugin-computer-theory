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

// Detect the configured tool for this instance — drives per-tool dispatch.
$problem = $DB->get_record('graphitoubb_problem', ['instanceid' => $instance->id]);
$canmanage = has_capability('mod/graphitoubb:manage', $context) ||
             has_capability('moodle/course:manageactivities', $context);

echo $OUTPUT->header();

if ($problem && $problem->tool === 'truth_table') {
    if ($canattempt) {
        $service = new \mod_graphitoubb\attempt_service();
        $attempt = $service->start_or_resume((int) $instance->id, (int) $USER->id);

        // Load latest submission + grading for this attempt (if any).
        $submission_row = $DB->get_record('graphitoubb_submission', ['attemptid' => $attempt->id], '*', IGNORE_MULTIPLE);
        $submission_payload = null;
        $grading_payload = null;
        if ($submission_row) {
            $submission_payload = json_decode($submission_row->payload, true) ?: null;
            $grading_payload    = json_decode($submission_row->grading_result, true) ?: null;
        }

        echo $renderer->render_truth_table_editor(
            (int) $attempt->id,
            (int) $instance->id,
            $problem,
            $submission_payload,
            $grading_payload
        );
    }
    if ($canmanage) {
        $editurl = new \moodle_url('/mod/graphitoubb/edit_problem.php', ['id' => $cm->id]);
        echo \html_writer::div(
            \html_writer::link($editurl, '✏ Edit problem (teacher)'),
            'mt-3'
        );
    }
} else if ($problem && $problem->tool === 'afd') {
    // C1: graded AFD exercise — consigna (A1) + editor, graded on finish.
    if ($canattempt) {
        $service = new \mod_graphitoubb\attempt_service();
        $attempt = $service->start_or_resume((int) $instance->id, (int) $USER->id);

        echo $renderer->render_afd_consigna($problem);

        // If already finished+graded, show the result above the editor.
        $sub = (new \mod_graphitoubb\submission_repository())->find_by_attempt((int) $attempt->id);
        if ($sub) {
            $gr = json_decode($sub->grading_result, true) ?: [];
            echo $renderer->render_afd_result($gr);
        }

        echo $renderer->render_editor((int) $attempt->id, (int) $instance->id, 1, (string) $attempt->status);
    }
    if ($canmanage) {
        $editurl = new \moodle_url('/mod/graphitoubb/edit_problem.php', ['id' => $cm->id]);
        echo \html_writer::div(
            \html_writer::link($editurl, '✏ Edit problem (teacher)'),
            'mt-3'
        );
    }
} else if ($problem && ($problem->tool === 'grafo' || $problem->tool === 'arbol')) {
    // grafo/arbol graded exercise — consigna + canvas editor, graded on finish.
    if ($canattempt) {
        $service = new \mod_graphitoubb\attempt_service();
        $attempt = $service->start_or_resume((int) $instance->id, (int) $USER->id);

        echo $renderer->render_graph_consigna($problem);

        $finished = ((string) $attempt->status === 'finished');
        $sub = (new \mod_graphitoubb\submission_repository())->find_by_attempt((int) $attempt->id);
        if ($sub) {
            $gr = json_decode($sub->grading_result, true) ?: [];
            echo $renderer->render_graph_result($gr);
        }

        $latest       = (new \mod_graphitoubb\snapshot_service())->get_latest((int) $attempt->id);
        $snapshotjson = $latest ? $latest->payload : null;

        echo $renderer->render_graph_editor(
            (int) $attempt->id,
            (int) $instance->id,
            $problem,
            true,
            $snapshotjson,
            $finished
        );
    }
    if ($canmanage) {
        $editurl = new \moodle_url('/mod/graphitoubb/edit_problem.php', ['id' => $cm->id]);
        echo \html_writer::div(
            \html_writer::link($editurl, '✏ Edit problem (teacher)'),
            'mt-3'
        );
    }
} else if ($canmanage) {
    // No problem configured yet: prompt the teacher.
    $editurl = new \moodle_url('/mod/graphitoubb/edit_problem.php', ['id' => $cm->id]);
    echo \html_writer::tag('p', 'No problem configured yet for this activity.');
    echo \html_writer::link($editurl, '⚙ Configure problem', ['class' => 'btn btn-primary']);
} else if ($canattempt) {
    // Legacy AFD path (existing POC flow).
    $service = new \mod_graphitoubb\attempt_service();
    $attempt = $service->start_or_resume((int) $instance->id, (int) $USER->id);
    echo $renderer->render_editor((int) $attempt->id, (int) $instance->id, 1, (string) $attempt->status);
}

echo $renderer->render_view_links((int) $cm->id, $canviewreport, $canattempt);

echo $OUTPUT->footer();
