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
Feature: Multi-attempt policy applies the best score
  In order to reward student improvement across attempts
  As a teacher
  I need the grade_cache to reflect the best score when policy is set to "best"

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
      | Multi Attempt   | complete | A ∧ B   |
    And the following "activity" exists:
      | activity        | graphitoubb   |
      | course          | C1            |
      | name            | Multi Attempt |
      | intro           | Multi-attempt policy test |
      | attempts_max    | 3             |
      | attempts_policy | best          |

  Scenario: Student submits three attempts; grade cache reflects best score
    Given I am on "Course 1" course homepage logged in as "student1"
    # Attempt 1 — score ~0.5 (2 of 4 cells correct)
    When I follow "Multi Attempt"
    And I fill the truth table cell 1 column "A ∧ B" with "F"
    And I fill the truth table cell 2 column "A ∧ B" with "F"
    And I fill the truth table cell 3 column "A ∧ B" with "V"
    And I fill the truth table cell 4 column "A ∧ B" with "F"
    And I press "Enviar respuesta"
    And I press "Nuevo intento"
    # Attempt 2 — score ~0.8 (almost complete)
    And I fill the truth table cell 1 column "A ∧ B" with "F"
    And I fill the truth table cell 2 column "A ∧ B" with "F"
    And I fill the truth table cell 3 column "A ∧ B" with "F"
    And I fill the truth table cell 4 column "A ∧ B" with "F"
    And I press "Enviar respuesta"
    And I press "Nuevo intento"
    # Attempt 3 — score ~0.6
    And I fill the truth table cell 1 column "A ∧ B" with "F"
    And I fill the truth table cell 2 column "A ∧ B" with "F"
    And I fill the truth table cell 3 column "A ∧ B" with "V"
    And I fill the truth table cell 4 column "A ∧ B" with "V"
    And I press "Enviar respuesta"
    Then I am on "Course 1" course homepage logged in as "teacher1"
    And I follow "Multi Attempt"
    And I follow "Panel docente"
    And I click on "Por alumno" "link"
    Then I should see "Student" in the ".graphitoubb-panel-per-student" "css_element"
