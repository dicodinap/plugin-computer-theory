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
 * Seeds the curated truth_table presets into the system-context Question Bank.
 *
 * @package    qtype_graphitoubb
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace qtype_graphitoubb;

use context_system;
use core_question\local\bank\question_edit_contexts;

/**
 * Installs the shipped preset_questions.xml into a dedicated category in the system
 * Question Bank, so a fresh site already has a usable bank of truth_table questions.
 *
 * Idempotent and defensive: it is a no-op once seeded at the current preset version,
 * and any failure is swallowed (logged via debugging) so it can never break plugin
 * install/upgrade. Teachers can always re-import the same file manually from
 * qtype/graphitoubb/db/preset_questions.xml.
 */
final class catalog_seeder {
    /** @var string Stable idnumber for the seeded category (unique per context). */
    private const CATEGORY_IDNUMBER = 'qtype_graphitoubb_presets';

    /** @var string Config key storing the preset version already seeded. */
    private const SEEDED_KEY = 'presets_seeded_version';

    /**
     * Seed the presets if not already seeded at this version.
     *
     * @param  int $presetversion Monotonic version of the shipped catalogue; bump it
     *                            (in version.php's seeding call) to force a re-seed.
     * @return bool True if a seeding import ran, false if skipped or failed softly.
     */
    public static function seed(int $presetversion): bool {
        global $CFG, $DB;

        try {
            // Idempotency guard.
            $seeded = (int) get_config('qtype_graphitoubb', self::SEEDED_KEY);
            if ($seeded >= $presetversion) {
                return false;
            }

            $xmlfile = $CFG->dirroot . '/question/type/graphitoubb/db/preset_questions.xml';
            if (!is_readable($xmlfile)) {
                debugging('qtype_graphitoubb seeder: preset_questions.xml not found', DEBUG_DEVELOPER);
                return false;
            }

            require_once($CFG->dirroot . '/question/format.php');
            require_once($CFG->dirroot . '/question/format/xml/format.php');
            require_once($CFG->libdir . '/questionlib.php');

            $systemcontext = context_system::instance();
            $category      = self::ensure_category($systemcontext);

            // If the category already holds questions, treat as seeded (covers a lost config).
            if (self::category_has_questions((int) $category->id)) {
                set_config(self::SEEDED_KEY, $presetversion, 'qtype_graphitoubb');
                return false;
            }

            $contexts = new question_edit_contexts($systemcontext);

            $qformat = new \qformat_xml();
            $qformat->setCategory($category);
            $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
            $qformat->setCourse(get_site());
            $qformat->setFilename($xmlfile);
            $qformat->setRealfilename('preset_questions.xml');
            $qformat->setMatchgrades('error');
            $qformat->setCatfromfile(false);     // Import into our category, ignore the file marker.
            $qformat->setContextfromfile(false);
            $qformat->setStoponerror(true);
            $qformat->set_display_progress(false); // Avoid notification output during install.

            // Suppress any stray import echo so install/upgrade output stays clean.
            ob_start();
            $ok = $qformat->importpreprocess()
                && $qformat->importprocess()
                && $qformat->importpostprocess();
            ob_end_clean();

            if ($ok) {
                set_config(self::SEEDED_KEY, $presetversion, 'qtype_graphitoubb');
            } else {
                debugging('qtype_graphitoubb seeder: import did not complete', DEBUG_DEVELOPER);
            }
            return (bool) $ok;
        } catch (\Throwable $e) {
            // Never let seeding break an install/upgrade.
            if (ob_get_level() > 0) {
                @ob_end_clean();
            }
            debugging('qtype_graphitoubb seeder failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Find or create the dedicated preset category under the system top category.
     *
     * @param  context_system $context
     * @return \stdClass The question_categories record.
     */
    private static function ensure_category(context_system $context): \stdClass {
        global $DB;

        $existing = $DB->get_record('question_categories', [
            'contextid' => $context->id,
            'idnumber'  => self::CATEGORY_IDNUMBER,
        ]);
        if ($existing) {
            return $existing;
        }

        $top = question_get_top_category($context->id, true);

        $cat = new \stdClass();
        $cat->name       = get_string('preset_category_name', 'qtype_graphitoubb');
        $cat->info       = get_string('preset_category_info', 'qtype_graphitoubb');
        $cat->infoformat = FORMAT_HTML;
        $cat->contextid  = $context->id;
        $cat->parent     = $top->id;
        $cat->sortorder  = 999;
        $cat->stamp      = make_unique_id_code();
        $cat->idnumber   = self::CATEGORY_IDNUMBER;
        $cat->id         = $DB->insert_record('question_categories', $cat);

        return $cat;
    }

    /**
     * Whether the given category already contains at least one question bank entry.
     *
     * @param  int $categoryid
     * @return bool
     */
    private static function category_has_questions(int $categoryid): bool {
        global $DB;
        return $DB->record_exists('question_bank_entries', [
            'questioncategoryid' => $categoryid,
        ]);
    }
}
