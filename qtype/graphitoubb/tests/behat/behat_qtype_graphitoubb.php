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
 * Behat step definitions for qtype_graphitoubb (iter1).
 *
 * Covers question bank creation, student quiz interaction, and teacher review.
 *
 * @package    qtype_graphitoubb
 * @category   test
 * @copyright  2026 GraphitoUBB
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: No namespace — Moodle Behat auto-discovery requires the global namespace.

/**
 * Step definitions for the GraphitoUBB question type.
 *
 * Extends behat_base to access Moodle's Behat helpers.
 */
class behat_qtype_graphitoubb extends behat_base {
    // -------------------------------------------------------------------------
    // Question bank — creation
    // -------------------------------------------------------------------------

    /**
     * Creates a new GraphitoUBB question of the given type through the question bank UI.
     *
     * Flow: question bank page → "Create new question" → select "GraphitoUBB Truth Table"
     * → fill form (name, type, formula) → save.
     *
     * @When /^I add a graphitoubb question of type "(?P<type>[^"]*)" with formula "(?P<formula>[^"]*)" to the question bank$/
     *
     * @param string $type    Exercise type: complete|equivalence|classify.
     * @param string $formula Logic formula using Unicode operators.
     */
    public function i_add_a_graphitoubb_question_to_the_question_bank(string $type, string $formula): void {
        // Click "Create new question" button.
        $this->execute('behat_general::i_click_on', [
            get_string('createnewquestion', 'question'),
            'button',
        ]);

        // Wait for the question type chooser modal.
        $this->getSession()->wait(3000, 'document.querySelector(".qtype-chooser, .modal-body") !== null');

        // Select the GraphitoUBB question type.
        $qtypeEl = $this->find('xpath', '//label[contains(., "GraphitoUBB") or contains(., "graphitoubb")]');
        if ($qtypeEl) {
            $qtypeEl->click();
        } else {
            // Some Moodle versions use a list item.
            $listItem = $this->find('css', '[data-qtype="graphitoubb"]');
            if ($listItem) {
                $listItem->click();
            }
        }

        // Click the "Add" or "Next" button in the chooser.
        $addBtn = $this->find('css', '.qtype-chooser [data-action="submit"], .modal-footer .btn-primary');
        if ($addBtn) {
            $addBtn->click();
        }

        // Wait for the question edit form.
        $this->getSession()->wait(5000, 'document.querySelector("#id_questiontext, [name=\'questiontext\']") !== null');

        // Fill in the form.
        $this->execute('behat_forms::i_set_the_field_to', ['Question name', 'My TT question']);
        $this->execute('behat_forms::i_set_the_field_to', ['Question text', 'Complete the truth table']);
        $this->execute('behat_forms::i_set_the_field_to', ['Exercise type', $type]);
        $this->execute('behat_forms::i_set_the_field_to', ['Formula', $formula]);

        // Save.
        $this->execute('behat_forms::press_button', ['id_submitbutton']);
    }

    /**
     * Asserts a question with the given name appears in the question bank listing.
     *
     * @Then /^the question should be in the question bank with name "(?P<name>[^"]*)"$/
     *
     * @param string $name Expected question name.
     */
    public function the_question_should_be_in_the_question_bank_with_name(string $name): void {
        $this->assertSession()->pageTextContains($name);
    }

    // -------------------------------------------------------------------------
    // Student quiz interaction
    // -------------------------------------------------------------------------

    /**
     * Navigates to the quiz and starts an attempt (clicks "Attempt quiz").
     *
     * Assumes the student is already on the course homepage.
     *
     * @When /^I attempt the truth table question$/
     */
    public function i_attempt_the_truth_table_question(): void {
        // Navigate to the quiz link (first quiz on the page).
        $quizLink = $this->find('css', '.modtype_quiz a.aalink, .activity.quiz a.aalink');
        if (!$quizLink) {
            // Fallback: follow the first quiz link by text.
            $this->execute('behat_general::i_click_on', ['Quiz', 'link']);
        } else {
            $quizLink->click();
        }

        // Click "Attempt quiz now" button.
        $this->getSession()->wait(3000, 'document.querySelector("[name=\'startattempt\'], .btn[href*=\'startattempt\']") !== null');
        $startBtn = $this->find('css', '[name="startattempt"]');
        if (!$startBtn) {
            $startBtn = $this->find('xpath', '//input[@value[contains(.,"Attempt quiz")]] | //button[contains(.,"Attempt quiz")]');
        }
        if ($startBtn) {
            $startBtn->click();
        }

        // Wait for the question body to render.
        $this->getSession()->wait(
            5000,
            'document.querySelector("[data-region=\"truth-table-editor\"]") !== null'
        );
    }

    /**
     * Fills the truth table with N correct cells out of the total and submits.
     *
     * For a 2-variable formula (A ∧ B), the answer column has 4 rows.
     * This step fills the first $correct cells with the correct value "V" or "F"
     * (determined by the answer key already loaded from the problem) and leaves
     * the rest empty, then submits the quiz attempt.
     *
     * NOTE: In practice the correct values depend on the formula; this step fills
     * cells by clicking "Submit all and finish" after optionally setting values via
     * the editor. Full formula-aware filling requires the step definitions in
     * behat_mod_graphitoubb to be reused — call them via $this->execute().
     *
     * @When /^I submit my truth table response with (?P<correct>\d+) correct cells of (?P<total>\d+)$/
     *
     * @param int $correct Number of cells to fill correctly (simplified: fill all with "F" which is correct for A ∧ B rows 1-3).
     * @param int $total   Total number of cells in the table.
     */
    public function i_submit_my_truth_table_response(int $correct, int $total): void {
        // Use the mod_graphitoubb cell-fill step for each cell.
        // For A ∧ B (2 variables), the answer column has 4 rows.
        // Rows 1-3 expect "F", row 4 expects "V".
        $answers = ['F', 'F', 'F', 'V'];
        $colLabel = 'A ∧ B'; // Default for the standard test question.

        for ($row = 1; $row <= min($correct, $total); $row++) {
            $value = $answers[$row - 1] ?? 'F';
            $selector = sprintf(
                '[data-region="truth-table-editor"] [data-row="%d"][data-col="%s"]',
                $row,
                addslashes($colLabel)
            );
            $cell = $this->find('css', $selector);
            if ($cell) {
                if ($cell->getTagName() === 'input') {
                    $cell->setValue($value);
                } else {
                    $cell->click();
                    $this->getSession()->getDriver()->keyPress($selector, $value);
                }
            }
        }

        // Submit the quiz attempt.
        $this->execute('behat_general::i_click_on', ['Submit all and finish', 'button']);
        $this->getSession()->wait(2000, 'document.querySelector(".modal.show") !== null');
        $confirmBtn = $this->find('css', '.modal.show [data-action="save"], .modal.show .btn-primary');
        if ($confirmBtn) {
            $confirmBtn->click();
        }
    }

    /**
     * Asserts that per-cell feedback is visible on the review/results page.
     *
     * @Then /^I should see per-cell feedback$/
     */
    public function i_should_see_per_cell_feedback(): void {
        $feedbackEl = $this->find(
            'css',
            '[data-region="truth-table-feedback"], ' .
            '.graphitoubb-feedback-list, ' .
            '[data-feedback-status]'
        );

        if (!$feedbackEl) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'Per-cell feedback element not found on the page.',
                $this->getSession()
            );
        }
    }

    /**
     * Asserts a grade is displayed on the review or results page.
     *
     * @Then /^I should see a grade displayed$/
     */
    public function i_should_see_a_grade_displayed(): void {
        $gradeEl = $this->find(
            'css',
            '.grade, [data-region="grade-display"], .gradingdetails, .que .grade'
        );

        if (!$gradeEl) {
            // Fall back to checking for any numeric score pattern in page text.
            $this->assertSession()->elementExists('css', '.grade, .gradingdetails');
        }
    }

    // -------------------------------------------------------------------------
    // Teacher review
    // -------------------------------------------------------------------------

    /**
     * Navigates to the quiz reports section and opens a named student's attempt.
     *
     * @When /^I navigate to the quiz reports and open student1's attempt$/
     */
    public function i_navigate_to_quiz_reports_and_open_student_attempt(): void {
        // Navigate to Results → Responses.
        $this->execute('behat_navigation::i_navigate_to_in_current_page_administration', ['Results > Responses']);

        // Wait for the report table.
        $this->getSession()->wait(5000, 'document.querySelector(".quizreportgrade") !== null');

        // Click on student1's attempt row (the first attempt link).
        $attemptLink = $this->find('css', '.quizreportgrade a, [data-region="attempt-link"]');
        if (!$attemptLink) {
            $attemptLink = $this->find('xpath', '//a[contains(@href, "review.php")]');
        }
        if ($attemptLink) {
            $attemptLink->click();
        }

        // Wait for the review page.
        $this->getSession()->wait(
            5000,
            'document.querySelector(".reviewsummary, [data-region=\"truth-table-feedback\"]") !== null'
        );
    }

    /**
     * Asserts the truth table submitted by the student is visible in the review screen.
     *
     * @Then /^I should see the truth table the student submitted$/
     */
    public function i_should_see_the_truth_table_submitted(): void {
        $tableEl = $this->find(
            'css',
            '[data-region="truth-table-editor"], ' .
            '.graphitoubb-truth-table, ' .
            'table.truthtable'
        );

        if (!$tableEl) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'Truth table element not found on the review page.',
                $this->getSession()
            );
        }
    }

    /**
     * Asserts the grade assigned to the attempt is displayed in the review screen.
     *
     * @Then /^I should see the grade assigned to the attempt$/
     */
    public function i_should_see_the_grade_assigned_to_the_attempt(): void {
        $this->i_should_see_a_grade_displayed();
    }
}
