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
 * Dev/admin CLI: wipe and re-seed the preset Question Bank from preset_questions.xml.
 *
 * Deletes the seeded category and its questions, clears the seeded-version flag, then
 * runs the seeder again. Useful after regenerating the catalogue or fixing the import.
 *
 * Usage:  php question/type/graphitoubb/cli/reseed_presets.php
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/questionlib.php');

$idnumber = 'qtype_graphitoubb_presets';
$context  = context_system::instance();

$category = $DB->get_record('question_categories', ['contextid' => $context->id, 'idnumber' => $idnumber]);
if ($category) {
    // Delete every question in the category, then the category itself.
    $entries = $DB->get_records('question_bank_entries', ['questioncategoryid' => $category->id]);
    foreach ($entries as $entry) {
        $versions = $DB->get_records('question_versions', ['questionbankentryid' => $entry->id]);
        foreach ($versions as $v) {
            question_delete_question($v->questionid);
        }
    }
    // Remove any now-empty bank entries and the category.
    $DB->delete_records('question_bank_entries', ['questioncategoryid' => $category->id]);
    $DB->delete_records('question_categories', ['id' => $category->id]);
    cli_writeln("Deleted seeded category {$category->id} and its questions.");
} else {
    cli_writeln('No seeded category found; nothing to delete.');
}

unset_config('presets_seeded_version', 'qtype_graphitoubb');
cli_writeln('Cleared presets_seeded_version flag.');

$ran = \qtype_graphitoubb\catalog_seeder::seed(2026062904);
cli_writeln($ran ? 'Re-seed completed.' : 'Re-seed did NOT run (check debugging output).');
