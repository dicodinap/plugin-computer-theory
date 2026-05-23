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
 * Export endpoint for the teacher panel — streams CSV, JSON, or PDF.
 *
 * URL parameters:
 *   id      — course-module id (cmid).
 *   format  — csv | json | pdf  (default: csv).
 *   scope   — all | summary | per_student | heatmap  (default: all).
 *
 * @package    mod_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/graphitoubb/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');

use mod_graphitoubb\external\get_panel_summary;
use mod_graphitoubb\external\get_panel_per_student;
use mod_graphitoubb\external\get_panel_heatmap;

$cmid   = required_param('id', PARAM_INT);
$format = optional_param('format', 'csv', PARAM_ALPHA);
$scope  = optional_param('scope', 'all', PARAM_ALPHANUMEXT);

if (!in_array($format, ['csv', 'json', 'pdf'], true)) {
    $format = 'csv';
}
if (!in_array($scope, ['all', 'summary', 'per_student', 'heatmap'], true)) {
    $scope = 'all';
}

$cm      = get_coursemodule_from_id('graphitoubb', $cmid, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/graphitoubb:viewreports', $context);

$iid      = (int) $cm->instance;
$filename = 'graphitoubb_panel_' . $iid . '_' . $scope . '_' . date('Ymd');

// -------------------------------------------------------------------------
// Collect data according to scope.
// -------------------------------------------------------------------------
$data = [];

if (in_array($scope, ['all', 'summary'], true)) {
    $data['summary'] = get_panel_summary::execute($iid);
}
if (in_array($scope, ['all', 'per_student'], true)) {
    $data['per_student'] = get_panel_per_student::execute($iid, 'all');
}
if (in_array($scope, ['all', 'heatmap'], true)) {
    $data['heatmap'] = get_panel_heatmap::execute($iid);
}

// -------------------------------------------------------------------------
// Stream output.
// -------------------------------------------------------------------------

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($format === 'csv') {
    // One CSV per section; use csv_export_writer for per_student (primary useful export).
    $export = new csv_export_writer();
    $export->set_filename($filename);

    if (!empty($data['summary'])) {
        $s = $data['summary'];
        $export->add_data([get_string('panel_tab_summary', 'mod_graphitoubb')]);
        $export->add_data([get_string('kpi_enrolled', 'mod_graphitoubb'), $s['enrolled']]);
        $export->add_data([get_string('kpi_attempted', 'mod_graphitoubb'), $s['attempted']]);
        $export->add_data([get_string('kpi_submitted', 'mod_graphitoubb'), $s['submitted']]);
        $export->add_data([get_string('kpi_with_draft', 'mod_graphitoubb'), $s['with_draft']]);
        $export->add_data([get_string('stat_avg', 'mod_graphitoubb'), round($s['avg_score'], 4)]);
        $export->add_data([get_string('stat_median', 'mod_graphitoubb'), round($s['median_score'], 4)]);
        $export->add_data([get_string('stat_stddev', 'mod_graphitoubb'), round($s['stddev_score'], 4)]);
        $export->add_data([get_string('stat_time_median', 'mod_graphitoubb'), $s['time_median_seconds']]);
        $export->add_data([]);
    }

    if (!empty($data['per_student']['students'])) {
        $export->add_data([get_string('panel_tab_per_student', 'mod_graphitoubb')]);
        $export->add_data([
            get_string('col_student', 'mod_graphitoubb'),
            get_string('col_score', 'mod_graphitoubb'),
            get_string('col_attempts', 'mod_graphitoubb'),
            get_string('col_time', 'mod_graphitoubb'),
            get_string('col_status', 'mod_graphitoubb'),
        ]);
        foreach ($data['per_student']['students'] as $row) {
            $export->add_data([
                $row['fullname'],
                $row['fraction'],
                $row['attempts_count'],
                $row['time_spent_seconds'],
                $row['status'],
            ]);
        }
        $export->add_data([]);
    }

    if (!empty($data['heatmap'])) {
        $hm = $data['heatmap'];
        $export->add_data([get_string('panel_tab_heatmap', 'mod_graphitoubb')]);
        $export->add_data(array_merge(['row'], $hm['columns']));
        // Reindex sparse cells into a full grid for CSV clarity.
        $grid = [];
        foreach ($hm['cells'] as $cell) {
            $grid[$cell['row']][$cell['col_index']] = round($cell['pct_correct'], 1) . '% (' . $cell['count_submissions'] . ')';
        }
        for ($r = 0; $r < $hm['rows_count']; $r++) {
            $row_data = [$r];
            foreach (array_keys($hm['columns']) as $ci) {
                $row_data[] = $grid[$r][$ci] ?? '';
            }
            $export->add_data($row_data);
        }
    }

    $export->download_file();
    exit;
}

if ($format === 'pdf') {
    // Use Moodle-bundled TCPDF. Falls back gracefully if not available.
    $tcpdf_path = $CFG->dirroot . '/lib/tcpdf/tcpdf.php';
    if (!file_exists($tcpdf_path)) {
        // TCPDF not available — fall back to JSON.
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        echo json_encode(['error' => 'PDF not available — TCPDF not installed', 'data' => $data]);
        exit;
    }
    require_once($tcpdf_path);

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GraphitoUBB');
    $pdf->SetAuthor(fullname($USER));
    $pdf->SetTitle(get_string('panel_title', 'mod_graphitoubb'));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, get_string('panel_title', 'mod_graphitoubb'), 0, 1);
    $pdf->SetFont('helvetica', '', 10);

    if (!empty($data['summary'])) {
        $s = $data['summary'];
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, get_string('panel_tab_summary', 'mod_graphitoubb'), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $lines = [
            get_string('kpi_enrolled', 'mod_graphitoubb')    . ': ' . $s['enrolled'],
            get_string('kpi_attempted', 'mod_graphitoubb')   . ': ' . $s['attempted'],
            get_string('kpi_submitted', 'mod_graphitoubb')   . ': ' . $s['submitted'],
            get_string('stat_avg', 'mod_graphitoubb')        . ': ' . round($s['avg_score'], 4),
            get_string('stat_median', 'mod_graphitoubb')     . ': ' . round($s['median_score'], 4),
            get_string('stat_stddev', 'mod_graphitoubb')     . ': ' . round($s['stddev_score'], 4),
        ];
        foreach ($lines as $line) {
            $pdf->Cell(0, 6, $line, 0, 1);
        }
        $pdf->Ln(3);
    }

    if (!empty($data['per_student']['students'])) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, get_string('panel_tab_per_student', 'mod_graphitoubb'), 0, 1);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(70, 6, get_string('col_student', 'mod_graphitoubb'), 1);
        $pdf->Cell(25, 6, get_string('col_score', 'mod_graphitoubb'), 1);
        $pdf->Cell(25, 6, get_string('col_attempts', 'mod_graphitoubb'), 1);
        $pdf->Cell(25, 6, get_string('col_time', 'mod_graphitoubb'), 1);
        $pdf->Cell(30, 6, get_string('col_status', 'mod_graphitoubb'), 1);
        $pdf->Ln();
        $pdf->SetFont('helvetica', '', 9);
        foreach ($data['per_student']['students'] as $row) {
            $pdf->Cell(70, 6, $row['fullname'], 1);
            $pdf->Cell(25, 6, $row['fraction'], 1);
            $pdf->Cell(25, 6, $row['attempts_count'], 1);
            $pdf->Cell(25, 6, $row['time_spent_seconds'], 1);
            $pdf->Cell(30, 6, $row['status'], 1);
            $pdf->Ln();
        }
        $pdf->Ln(3);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    echo $pdf->Output('', 'S');
    exit;
}
