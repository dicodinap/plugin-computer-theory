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
Feature: Student solves an equivalence-type truth table activity
  In order to demonstrate understanding of logical equivalence
  As a student
  I need to select whether two formulas are equivalent and optionally justify with a table

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
      | activity         | type        | formula_1 | formula_2 | expected_equivalent |
      | Equivalence Test | equivalence | A → B     | ¬A ∨ B    | 1                   |
    And the following "activity" exists:
      | activity | graphitoubb      |
      | course   | C1               |
      | name     | Equivalence Test |
      | intro    | Are A → B and ¬A ∨ B equivalent? |

  Scenario: Student selects the correct radio answer and receives full credit
    Given I am on "Course 1" course homepage logged in as "student1"
    When I follow "Equivalence Test"
    And I select "Sí, son equivalentes" radio option
    And I press "Enviar respuesta"
    Then I should see the score "1.00" in the panel summary

  Scenario: Student selects the wrong radio answer with strict policy and receives zero
    Given I am on "Course 1" course homepage logged in as "student1"
    When I follow "Equivalence Test"
    And I select "No, no son equivalentes" radio option
    And I press "Enviar respuesta"
    Then I should see the score "0.00" in the panel summary
