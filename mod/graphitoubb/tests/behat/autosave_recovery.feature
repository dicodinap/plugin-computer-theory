# This file is part of Moodle - https://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

@mod @mod_graphitoubb @javascript @iter1
Feature: Student draft is recovered on page reload
  In order not to lose work in progress
  As a student
  I need my in-progress table cells to be restored when I reload the activity page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "mod_graphitoubb > problems" exist:
      | activity      | type     | formula |
      | Autosave Test | complete | A ∧ B   |
    And the following "activity" exists:
      | activity | graphitoubb   |
      | course   | C1            |
      | name     | Autosave Test |
      | intro    | Autosave recovery test |

  Scenario: Student fills cells, waits for autosave, reloads and sees draft restored
    Given I am on "Course 1" course homepage logged in as "student1"
    When I follow "Autosave Test"
    And I fill the truth table cell 1 column "A ∧ B" with "F"
    And I fill the truth table cell 2 column "A ∧ B" with "F"
    And I wait for autosave to complete
    And the autosave indicator should show "saved"
    And I reload the page
    Then the autosave indicator should show "recovered"
    And the truth table cell 1 column "A ∧ B" should contain "F"
    And the truth table cell 2 column "A ∧ B" should contain "F"
