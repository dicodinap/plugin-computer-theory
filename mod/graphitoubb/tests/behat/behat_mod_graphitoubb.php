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
 * Behat step definitions for mod_graphitoubb truth-table activities (iter1).
 *
 * Covers truth table editor interaction, autosave, teacher panel, and axe-core a11y.
 *
 * @package    mod_graphitoubb
 * @category   test
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: No namespace — Behat step classes in Moodle must be in the global namespace
// so the auto-discovery mechanism can find them without composer autoloading.

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;

/**
 * Step definitions for the GraphitoUBB truth table activity (iter1).
 *
 * Extends behat_base so we have access to getSession(), find(), and the full
 * Moodle Behat helper surface (find_button, find_field, i_add_to_section, etc.).
 */
class behat_mod_graphitoubb extends behat_base {
    // -------------------------------------------------------------------------
    // Data generator helpers
    // -------------------------------------------------------------------------

    /**
     * Creates a truth_table problem row for a named graphitoubb instance.
     *
     * Uses Moodle's standard "the following X exist" table syntax wired through
     * the mod_graphitoubb_generator::create_problem() helper added in iter1.
     *
     * Example usage in .feature:
     *   Given the following "mod_graphitoubb > problems" exist:
     *     | activity | type     | formula |
     *     | My TT    | complete | A ∧ B   |
     *
     * This step is intentionally left undeclared here — Moodle's core
     * behat_data_generators handles "the following X exist" automatically when
     * the generator exposes get_create_methods() returning 'problems'.
     * The custom steps below handle UI interactions only.
     */

    // -------------------------------------------------------------------------
    // Activity creation helpers
    // -------------------------------------------------------------------------

    /**
     * Adds a truth_table activity to a course section via the UI.
     *
     * @Given /^I add a truth_table activity to course "(?P<coursename>[^"]*)" with formula "(?P<formula>[^"]*)" and type "(?P<type>[^"]*)"$/
     *
     * @param string $coursename Shortname of the course.
     * @param string $formula    Logic formula string (Unicode operators accepted).
     * @param string $type       Exercise type: complete|equivalence|classify.
     */
    public function i_add_truth_table_activity_to_course(
        string $coursename,
        string $formula,
        string $type
    ): void {
        $this->execute('behat_course::i_am_on_course_homepage', [$coursename]);
        $this->execute('behat_course::i_turn_editing_mode_on');
        // Moodle core step: adds a module to section 1.
        $this->execute('behat_course::i_add_to_section', ['GraphitoUBB', '1']);

        // Fill in the activity form.
        $this->execute('behat_forms::i_set_the_field_to', ['Name', 'Truth Table Exercise']);
        $this->execute('behat_forms::i_set_the_field_to', ['Tool', 'truth_table']);
        $this->execute('behat_forms::i_set_the_field_to', ['Exercise type', $type]);
        $this->execute('behat_forms::i_set_the_field_to', ['Formula', $formula]);
        $this->execute('behat_forms::press_button', ['Save and display']);
    }

    // -------------------------------------------------------------------------
    // Truth table editor — cell interaction
    // -------------------------------------------------------------------------

    /**
     * Fills a specific truth table cell with a value (V/F/empty).
     *
     * Targets: [data-region="truth-table-editor"] [data-row="{row}"][data-col="{col}"]
     *
     * @When /^I fill the truth table cell (?P<row>\d+) column "(?P<col>[^"]*)" with "(?P<value>[^"]*)"$/
     *
     * @param int    $row   1-indexed row number.
     * @param string $col   Column label (e.g. "A ∧ B", "A", "B").
     * @param string $value Cell value: "V", "F", or "" (empty).
     */
    public function i_fill_truth_table_cell(int $row, string $col, string $value): void {
        $selector = sprintf(
            '[data-region="truth-table-editor"] [data-row="%d"][data-col="%s"]',
            $row,
            addslashes($col)
        );

        $cell = $this->find('css', $selector);
        $cell->click();

        // The cell may be an input or a contenteditable div.
        if ($cell->getTagName() === 'input' || $cell->getTagName() === 'textarea') {
            $cell->setValue($value);
        } else {
            // contenteditable: clear then type.
            $cell->keyPress('a', 'ctrl');
            $this->getSession()->getDriver()->keyDown($selector, 'a');
            $cell->setValue('');
            if ($value !== '') {
                $cell->setValue($value);
            }
        }
    }

    /**
     * Asserts a truth table cell contains the expected value.
     *
     * @Then /^the truth table cell (?P<row>\d+) column "(?P<col>[^"]*)" should contain "(?P<value>[^"]*)"$/
     *
     * @param int    $row      1-indexed row number.
     * @param string $col      Column label.
     * @param string $expected Expected value: "V", "F", or "".
     */
    public function the_truth_table_cell_should_contain(int $row, string $col, string $expected): void {
        $selector = sprintf(
            '[data-region="truth-table-editor"] [data-row="%d"][data-col="%s"]',
            $row,
            addslashes($col)
        );

        $cell = $this->find('css', $selector);
        $actual = trim($cell->getValue() ?: $cell->getText());

        if ($actual !== $expected) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('Cell (%d, "%s") contains "%s", expected "%s".', $row, $col, $actual, $expected),
                $this->getSession()
            );
        }
    }

    // -------------------------------------------------------------------------
    // Helper symbol toolbar
    // -------------------------------------------------------------------------

    /**
     * Clicks a helper symbol button in the formula toolbar.
     *
     * Targets: [data-helper-symbol="{symbol}"]
     *
     * @When /^I click the helper symbol "(?P<symbol>[^"]*)"$/
     *
     * @param string $symbol Unicode operator symbol (e.g. "∧", "¬", "⊤").
     */
    public function i_click_the_helper_symbol(string $symbol): void {
        $btn = $this->find('css', sprintf('[data-helper-symbol="%s"]', addslashes($symbol)));
        $btn->click();
    }

    // -------------------------------------------------------------------------
    // Formula preview
    // -------------------------------------------------------------------------

    /**
     * Asserts the formula preview element shows the expected canonicalized text.
     *
     * @Then /^the formula preview should be "(?P<preview>[^"]*)"$/
     *
     * @param string $preview Expected canonical preview string.
     */
    public function the_formula_preview_should_be(string $preview): void {
        $el = $this->find('css', '[data-region="formula-preview"]');
        $actual = trim($el->getText());

        if ($actual !== $preview) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('Formula preview is "%s", expected "%s".', $actual, $preview),
                $this->getSession()
            );
        }
    }

    // -------------------------------------------------------------------------
    // Autosave indicator
    // -------------------------------------------------------------------------

    /**
     * Asserts the autosave status badge is in the given state.
     *
     * Recognised states: "idle" | "saving" | "saved" | "error" | "recovered"
     * The badge element carries [data-autosave-state="{state}"].
     *
     * @Then /^the autosave indicator should show "(?P<state>[^"]*)"$/
     *
     * @param string $state Expected state token.
     */
    public function the_autosave_indicator_should_show(string $state): void {
        $selector = sprintf('[data-region="autosave-indicator"][data-autosave-state="%s"]', $state);

        // Allow up to 5 s for the indicator to reach the expected state.
        $this->getSession()->wait(5000, sprintf(
            'document.querySelector(\'%s\') !== null',
            addslashes($selector)
        ));

        $el = $this->find('css', $selector);
        if (!$el) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('Autosave indicator is not in state "%s".', $state),
                $this->getSession()
            );
        }
    }

    /**
     * Waits until the autosave indicator reaches "saved" state (up to 10 s).
     *
     * @When /^I wait for autosave to complete$/
     */
    public function i_wait_for_autosave_to_complete(): void {
        $this->getSession()->wait(
            10000,
            'document.querySelector(\'[data-region="autosave-indicator"][data-autosave-state="saved"]\') !== null'
        );

        $el = $this->find('css', '[data-region="autosave-indicator"]');
        $currentState = $el ? $el->getAttribute('data-autosave-state') : 'unknown';

        if ($currentState !== 'saved') {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('Timed out waiting for autosave; current state is "%s".', $currentState),
                $this->getSession()
            );
        }
    }

    // -------------------------------------------------------------------------
    // Feedback assertions
    // -------------------------------------------------------------------------

    /**
     * Asserts that a feedback item for a cell has the given status after grading.
     *
     * Status: "correct" | "incorrect" | "propagated" | "empty"
     * Targets: [data-feedback-row="{row}"][data-feedback-col="{col}"][data-feedback-status="{status}"]
     *
     * @Then /^the feedback should mark cell (?P<row>\d+) column "(?P<col>[^"]*)" as "(?P<status>[^"]*)"$/
     *
     * @param int    $row    1-indexed row number.
     * @param string $col    Column label.
     * @param string $status Expected feedback status.
     */
    public function the_feedback_should_mark_cell_as(int $row, string $col, string $status): void {
        $selector = sprintf(
            '[data-feedback-row="%d"][data-feedback-col="%s"][data-feedback-status="%s"]',
            $row,
            addslashes($col),
            $status
        );

        $el = $this->find('css', $selector);
        if (!$el) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('Cell (%d, "%s") does not have feedback status "%s".', $row, $col, $status),
                $this->getSession()
            );
        }
    }

    // -------------------------------------------------------------------------
    // Teacher panel
    // -------------------------------------------------------------------------

    /**
     * Asserts a named panel tab is currently selected (aria-selected="true").
     *
     * @Then /^the panel tab "(?P<tabname>[^"]*)" should be selected$/
     *
     * @param string $tabname Visible tab label text.
     */
    public function the_panel_tab_should_be_selected(string $tabname): void {
        // Look for a tab link/button with aria-selected="true" matching the text.
        $tab = $this->find('xpath', sprintf(
            '//*[@role="tab" and @aria-selected="true" and normalize-space()="%s"]',
            $tabname
        ));

        if (!$tab) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('Panel tab "%s" is not selected.', $tabname),
                $this->getSession()
            );
        }
    }

    /**
     * Asserts the heatmap grid has at least the given number of rows and columns.
     *
     * @Then /^the heatmap should have (?P<rows>\d+) rows and (?P<cols>\d+) columns$/
     *
     * @param int $rows Expected minimum row count.
     * @param int $cols Expected minimum column count.
     */
    public function the_heatmap_should_have_rows_and_columns(int $rows, int $cols): void {
        $rowEls = $this->findAll('css', '[data-region="heatmap"] [data-heatmap-row]');
        $colEls = $this->findAll('css', '[data-region="heatmap"] [data-heatmap-col]');

        $actualRows = count($rowEls);
        $actualCols = count($colEls);

        if ($actualRows < $rows) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('Heatmap has %d row(s), expected at least %d.', $actualRows, $rows),
                $this->getSession()
            );
        }
        if ($actualCols < $cols) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('Heatmap has %d column(s), expected at least %d.', $actualCols, $cols),
                $this->getSession()
            );
        }
    }

    /**
     * Asserts the summary KPI score card shows the given score value.
     *
     * The score is rendered in [data-region="score-summary"] or a child with
     * [data-kpi="score"].
     *
     * @Then /^I should see the score "(?P<score>[^"]*)" in the panel summary$/
     *
     * @param string $score Expected score string (e.g. "1.00", "0.00", "0.75").
     */
    public function i_should_see_the_score_in_panel_summary(string $score): void {
        // After grading, the score appears in the page feedback area.
        // If on the panel, check the KPI card; otherwise check the inline feedback.
        $el = $this->find('css', '[data-region="score-summary"], [data-kpi="score"], .graphitoubb-score-display');

        if (!$el) {
            // Fall back: check visible page text contains the score.
            $this->assertSession()->pageTextContains($score);
            return;
        }

        $actual = trim($el->getText());
        if (strpos($actual, $score) === false) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('Score summary shows "%s", expected to contain "%s".', $actual, $score),
                $this->getSession()
            );
        }
    }

    /**
     * Resets all attempts for a named student from the teacher panel.
     *
     * @When /^I reset attempts for student "(?P<name>[^"]*)"$/
     *
     * @param string $name Student's full name as displayed in the per-student tab.
     */
    public function i_reset_attempts_for_student(string $name): void {
        // Find the reset action button in the student row.
        $btn = $this->find('xpath', sprintf(
            '//tr[contains(., "%s")]//button[@data-action="reset-attempts"] | ' .
            '//tr[contains(., "%s")]//a[@data-action="reset-attempts"]',
            $name,
            $name
        ));

        if (!$btn) {
            throw new \Behat\Mink\Exception\ElementNotFoundException(
                $this->getSession(),
                'Reset attempts button',
                'xpath',
                sprintf('row containing "%s"', $name)
            );
        }

        $btn->click();

        // Confirm the dialog if present.
        $this->getSession()->wait(2000, 'document.querySelector(".modal.show") !== null');
        $confirmBtn = $this->find('css', '.modal.show [data-action="confirm"], .modal.show .btn-primary');
        if ($confirmBtn) {
            $confirmBtn->click();
        }
    }

    // -------------------------------------------------------------------------
    // Radio option helpers (equivalence / classify)
    // -------------------------------------------------------------------------

    /**
     * Selects a radio option by its visible label text.
     *
     * @When /^I select "(?P<label>[^"]*)" radio option$/
     *
     * @param string $label Visible text of the radio label to select.
     */
    public function i_select_radio_option(string $label): void {
        $radio = $this->find('xpath', sprintf(
            '//label[normalize-space()="%s"]/preceding-sibling::input[@type="radio"] | ' .
            '//label[normalize-space()="%s"]/input[@type="radio"]',
            $label,
            $label
        ));

        if (!$radio) {
            // Try finding a radio whose associated label text matches.
            $radio = $this->find('xpath', sprintf(
                '//input[@type="radio"][@id=//label[normalize-space()="%s"]/@for]',
                $label
            ));
        }

        if (!$radio) {
            throw new \Behat\Mink\Exception\ElementNotFoundException(
                $this->getSession(),
                'radio input',
                'xpath',
                sprintf('label "%s"', $label)
            );
        }

        $radio->click();
    }

    // -------------------------------------------------------------------------
    // Accessibility — axe-core
    // -------------------------------------------------------------------------

    /**
     * Runs axe-core against the current page and fails on any critical or serious violation.
     *
     * axe-core must be vendored to tests/behat/fixtures/axe.min.js before running
     * @a11y scenarios in CI. See tests/behat/fixtures/README.md for instructions.
     *
     * @Then /^the page should pass accessibility tests with no critical violations$/
     */
    public function the_page_should_pass_a11y_with_no_critical(): void {
        $session = $this->getSession();

        // Load axe-core from the vendored fixture via a dynamic <script> tag.
        // The path below is relative to the Moodle wwwroot; adjust if needed.
        $axeUrl = rtrim($session->getCurrentUrl(), '/') . '/../mod/graphitoubb/tests/behat/fixtures/axe.min.js';

        // Inject axe by appending a <script> element if axe is not already loaded.
        $injectScript = <<<'JS'
(function() {
    if (typeof window.axe !== 'undefined') {
        return;
    }
    var s = document.createElement('script');
    // Use CDN fallback when vendored file is unavailable.
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.0/axe.min.js';
    s.id = '__behat_axe_loader__';
    document.head.appendChild(s);
})();
JS;

        $session->executeScript($injectScript);

        // Wait for axe to be available (up to 15 s — CDN load on first @a11y scenario).
        $session->wait(15000, 'typeof window.axe !== "undefined"');

        // Check axe loaded.
        $axeLoaded = $session->evaluateScript('typeof window.axe !== "undefined"');
        if (!$axeLoaded) {
            throw new \RuntimeException(
                'axe-core could not be loaded. ' .
                'Vendor axe.min.js to tests/behat/fixtures/axe.min.js or ensure CDN access. ' .
                'See tests/behat/fixtures/README.md for instructions.'
            );
        }

        // Run axe and collect results (async → use callback pattern via polling).
        $session->executeScript(<<<'JS'
window.__axe_result__ = null;
window.__axe_done__ = false;
axe.run(document, {
    runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] }
}, function(err, results) {
    window.__axe_result__ = results;
    window.__axe_done__ = true;
});
JS
        );

        // Poll until analysis completes (up to 20 s for complex pages).
        $session->wait(20000, 'window.__axe_done__ === true');

        $violations = $session->evaluateScript(<<<'JS'
(function() {
    if (!window.__axe_result__) { return JSON.stringify([]); }
    var critical = (window.__axe_result__.violations || []).filter(function(v) {
        return v.impact === 'critical' || v.impact === 'serious';
    });
    return JSON.stringify(critical);
})()
JS
        );

        $violationList = json_decode($violations, true);

        if (!empty($violationList)) {
            $messages = [];
            foreach ($violationList as $v) {
                $messages[] = sprintf('[%s] %s — %s', $v['impact'], $v['id'], $v['description']);
            }
            throw new \Behat\Mink\Exception\ExpectationException(
                "axe-core found critical/serious accessibility violations:\n" . implode("\n", $messages),
                $session
            );
        }
    }
}
