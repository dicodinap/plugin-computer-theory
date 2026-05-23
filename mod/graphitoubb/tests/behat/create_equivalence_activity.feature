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
Feature: Teacher creates an equivalence-type truth table activity
  In order to test whether students can identify logically equivalent formulas
  As a teacher
  I need to be able to add a GraphitoUBB truth table activity of type "equivalence"

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

  Scenario: Teacher creates an equivalence activity with two formulas
    Given I am on the "C1" "Course" page logged in as "teacher1"
    When I turn editing mode on
    And I add a "GraphitoUBB" to section "1" and I fill the form with:
      | Name                  | Equivalence Exercise |
      | Tool                  | truth_table           |
      | Exercise type         | equivalence           |
      | Formula 1             | A → B                |
      | Formula 2             | ¬A ∨ B               |
      | Expected equivalent   | 1                    |
    Then I should see "Equivalence Exercise" in the "region-main" "region"
    And "Equivalence Exercise" activity should be visible
