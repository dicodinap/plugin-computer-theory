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

@qtype @qtype_graphitoubb @javascript @iter1
Feature: Student answers a truth table question in a quiz
  As a student
  I want to fill in a truth table in a quiz
  So that I can demonstrate my understanding of propositional logic

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                  |
      | teacher1 | Teacher   | One      | teacher1@example.com   |
      | student1 | Student   | One      | student1@example.com   |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And a quiz with a graphitoubb "complete" question "A ∧ B" exists in "C1"

  Scenario: Student submits a fully correct truth table answer and sees grade 1.0
    Given I am on the "Course 1" "Course" page logged in as "student1"
    When I attempt the truth table question
    And I submit my truth table response with 4 correct cells of 4
    Then I should see a grade displayed
    And I should see per-cell feedback
