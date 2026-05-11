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

namespace mod_graphitoubb\output;

/**
 * Renderer for mod_graphitoubb.
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the student editor shell (Cytoscape canvas placeholder until S11).
     *
     * @param int $attemptid
     * @param int $instanceid
     * @param int $schemaversion
     * @return string HTML.
     */
    public function render_editor(int $attemptid, int $instanceid, int $schemaversion): string {
        $context = [
            'attemptid'     => $attemptid,
            'instanceid'    => $instanceid,
            'schemaversion' => $schemaversion,
        ];
        return $this->render_from_template('mod_graphitoubb/editor', $context);
    }

    /**
     * Render a read-only teacher summary of an attempt.
     *
     * @param \stdClass $attempt Attempt row.
     * @return string HTML.
     */
    public function render_attempt_summary(\stdClass $attempt): string {
        return $this->render_from_template('mod_graphitoubb/attempt_summary', (array) $attempt);
    }

    /**
     * Render the teacher-facing attempt list table for an instance.
     *
     * @param \stdClass[] $attempts Rows from report_repository::list_attempts_for_instance.
     * @param \context    $context  Module context (used for fullname capability check).
     * @return string HTML.
     */
    public function render_attempt_list(array $attempts, \context $context): string {
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $context);
        $rows = [];
        foreach ($attempts as $attempt) {
            $row                     = (array) $attempt;
            $row['fullname']         = fullname($attempt, $canviewfullnames);
            $row['timestarted_fmt']  = userdate((int) $attempt->timestarted);
            $row['timefinished_fmt'] = $attempt->timefinished ? userdate((int) $attempt->timefinished) : '';
            $rows[]                  = $row;
        }
        return $this->render_from_template('mod_graphitoubb/attempt_list', [
            'attempts'     => $rows,
            'has_attempts' => !empty($rows),
        ]);
    }

    /**
     * Render capability-aware navigation links for view.php.
     *
     * @param int  $cmid          Course-module id.
     * @param bool $canviewreport User holds mod/graphitoubb:viewreport.
     * @param bool $canattempt    User holds mod/graphitoubb:attempt.
     * @return string HTML — empty when neither capability applies.
     */
    public function render_view_links(int $cmid, bool $canviewreport, bool $canattempt): string {
        if (!$canviewreport) {
            return '';
        }
        $url = new \moodle_url('/mod/graphitoubb/report.php', ['id' => $cmid]);
        return $this->single_button($url, get_string('view_report', 'mod_graphitoubb'), 'get');
    }
}
