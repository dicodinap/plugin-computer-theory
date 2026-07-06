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
 * DEV-ONLY: import preset_questions.xml into a fresh system-context category and
 * print the created grafo/arbol question ids (for Playwright preview). Idempotent
 * per run: it always creates a new timestamped-ish category name to avoid clashes.
 *
 * Usage (inside container): php question/type/graphitoubb/cli/import_presets_dev.php
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->libdir . '/questionlib.php');

$xmlfile = $CFG->dirroot . '/question/type/graphitoubb/db/preset_questions.xml';
$systemcontext = context_system::instance();

$top = question_get_top_category($systemcontext->id, true);
$cat = (object) [
    'name'       => 'GraphitoUBB dev presets ' . random_string(6),
    'info'       => 'dev import',
    'infoformat' => FORMAT_HTML,
    'contextid'  => $systemcontext->id,
    'parent'     => $top->id,
    'sortorder'  => 999,
    'stamp'      => make_unique_id_code(),
];
$cat->id = $DB->insert_record('question_categories', $cat);

$contexts = new \core_question\local\bank\question_edit_contexts($systemcontext);
$qformat = new qformat_xml();
$qformat->setCategory($cat);
$qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
$qformat->setCourse(get_site());
$qformat->setFilename($xmlfile);
$qformat->setRealfilename('preset_questions.xml');
$qformat->setMatchgrades('error');
$qformat->setCatfromfile(false);
$qformat->setContextfromfile(false);
$qformat->setStoponerror(true);
$qformat->set_display_progress(false);

$ok = $qformat->importpreprocess() && $qformat->importprocess() && $qformat->importpostprocess();
cli_writeln('import ok=' . ($ok ? '1' : '0') . ' category=' . $cat->id);

// Report grafo/arbol question ids for preview.
$sql = "SELECT q.id, q.name, o.tool
          FROM {question} q
          JOIN {qtype_graphitoubb_options} o ON o.questionid = q.id
          JOIN {question_versions} qv ON qv.questionid = q.id
          JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
         WHERE qbe.questioncategoryid = :cat AND o.tool IN ('grafo','arbol')
      ORDER BY o.tool, q.id";
foreach ($DB->get_records_sql($sql, ['cat' => $cat->id]) as $r) {
    cli_writeln("  [{$r->tool}] qid={$r->id}  {$r->name}  preview=/question/bank/previewquestion/preview.php?id={$r->id}");
}
