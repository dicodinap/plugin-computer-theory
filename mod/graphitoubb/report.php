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
 * Teacher report entry point for mod_graphitoubb.
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
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'graphitoubb');
    $instance = $DB->get_record('graphitoubb', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $instance = $DB->get_record('graphitoubb', ['id' => $g], '*', MUST_EXIST);
    [$course, $cm] = get_course_and_cm_from_instance($g, 'graphitoubb');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/graphitoubb:viewreport', $context);

$PAGE->set_url('/mod/graphitoubb/report.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$repository  = new \mod_graphitoubb\report_repository();
$attempts    = $repository->list_attempts_for_instance((int) $cm->instance);
$canattempt = has_capability('mod/graphitoubb:attempt', $context);

/** @var \mod_graphitoubb\output\renderer $renderer */
$renderer = $PAGE->get_renderer('mod_graphitoubb');

$backurl = new moodle_url('/mod/graphitoubb/view.php', ['id' => $cm->id]);

echo $OUTPUT->header();
echo html_writer::link($backurl, get_string('back_to_activity', 'mod_graphitoubb'));
echo $renderer->render_attempt_list($attempts, $context);
if (empty($attempts) && $canattempt) {
    echo $OUTPUT->single_button($backurl, get_string('start_your_attempt', 'mod_graphitoubb'), 'get');
}
echo $OUTPUT->footer();
