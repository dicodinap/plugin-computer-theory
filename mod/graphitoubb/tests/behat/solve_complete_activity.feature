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
Feature: Student solves a complete-type truth table activity
  In order to demonstrate understanding of propositional logic
  As a student
  I need to fill in all cells of the truth table and submit my answer

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
      | activity        | type     | formula |
      | Conjunction TT  | complete | A ∧ B   |
    And the following "activity" exists:
      | activity | graphitoubb    |
      | course   | C1             |
      | name     | Conjunction TT |
      | intro    | Complete the truth table for A ∧ B |

  Scenario: Student fills all cells correctly and gets full score
    Given I am on "Course 1" course homepage logged in as "student1"
    When I follow "Conjunction TT"
    And I fill the truth table cell 1 column "A ∧ B" with "F"
    And I fill the truth table cell 2 column "A ∧ B" with "F"
    And I fill the truth table cell 3 column "A ∧ B" with "F"
    And I fill the truth table cell 4 column "A ∧ B" with "V"
    And I press "Enviar respuesta"
    Then I should see the score "1.00" in the panel summary
    And the truth table cell 1 column "A ∧ B" should contain "F"
    And the truth table cell 4 column "A ∧ B" should contain "V"

  Scenario: Student fills one cell incorrectly then retries
    Given I am on "Course 1" course homepage logged in as "student1"
    When I follow "Conjunction TT"
    And I fill the truth table cell 1 column "A ∧ B" with "V"
    And I fill the truth table cell 2 column "A ∧ B" with "F"
    And I fill the truth table cell 3 column "A ∧ B" with "F"
    And I fill the truth table cell 4 column "A ∧ B" with "V"
    And I press "Enviar respuesta"
    Then the feedback should mark cell 1 column "A ∧ B" as "incorrect"
    And I should not see the score "1.00" in the panel summary
    When I press "Nuevo intento"
    And I fill the truth table cell 1 column "A ∧ B" with "F"
    And I fill the truth table cell 2 column "A ∧ B" with "F"
    And I fill the truth table cell 3 column "A ∧ B" with "F"
    And I fill the truth table cell 4 column "A ∧ B" with "V"
    And I press "Enviar respuesta"
    Then I should see the score "1.00" in the panel summary
