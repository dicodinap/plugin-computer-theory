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
 * Teacher panel entry point for mod_graphitoubb.
 *
 * URL parameters:
 *   id  — course-module id (cmid).
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/graphitoubb/lib.php');

$cmid = required_param('id', PARAM_INT);

$cm      = get_coursemodule_from_id('graphitoubb', $cmid, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('graphitoubb', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/graphitoubb:viewreports', $context);

// Load problem (may be null if not yet configured).
$problem = $DB->get_record('graphitoubb_problem', ['instanceid' => $cm->instance], '*', IGNORE_MISSING) ?: null;

// Page setup.
$PAGE->set_url(new moodle_url('/mod/graphitoubb/panel.php', ['id' => $cmid]));
$PAGE->set_title(get_string('panel_title', 'mod_graphitoubb'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Initialize AMD panel dashboard.
$PAGE->requires->js_call_amd(
    'mod_graphitoubb/panel_dashboard',
    'init',
    [['#graphitoubb-panel', (int) $cm->instance, (int) $context->id]]
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('panel_title', 'mod_graphitoubb'));

/** @var \mod_graphitoubb\output\renderer $renderer */
$renderer = $PAGE->get_renderer('mod_graphitoubb');
$renderable = new \mod_graphitoubb\output\teacher_panel_renderable(
    (int) $cm->instance,
    $problem,
    (int) $context->id
);

echo $renderer->render_teacher_panel($renderable);

echo $OUTPUT->footer();
