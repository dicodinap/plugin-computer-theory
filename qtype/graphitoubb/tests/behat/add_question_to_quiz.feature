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
Feature: Add a GraphitoUBB truth table question to a quiz
  As a teacher
  I want to create a truth table question in the question bank
  So that I can add it to a quiz for my students

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I am on the "C1" "Course" page logged in as "teacher1"

  Scenario: Create a complete-type truth table question and verify it appears in the question bank
    Given I navigate to "Question bank" in current page administration
    When I add a graphitoubb question of type "complete" with formula "A ∨ B" to the question bank
    Then the question should be in the question bank with name "My TT question"
    And the question should be of type "GraphitoUBB Truth Table"
