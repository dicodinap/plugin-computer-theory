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
Feature: Teacher reviews a student attempt on a truth table question
  As a teacher
  I want to review a student's truth table submission
  So that I can see per-cell feedback and verify the grading

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
    And student1 has submitted an attempt on a graphitoubb quiz in "C1"

  Scenario: Teacher reviews the student attempt and sees feedback per cell
    Given I am on the "Course 1" "Course" page logged in as "teacher1"
    When I navigate to the quiz reports and open student1's attempt
    Then I should see the truth table the student submitted
    And I should see per-cell feedback
    And I should see the grade assigned to the attempt
