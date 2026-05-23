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

@mod @mod_graphitoubb @javascript @iter1 @a11y
Feature: Teacher views the activity panel with four tabs
  In order to monitor student performance
  As a teacher
  I need to navigate the teacher panel and see summary, per-student, heatmap, and export tabs

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
      | activity    | type     | formula |
      | Panel Demo  | complete | A ∧ B   |
    And the following "activity" exists:
      | activity | graphitoubb |
      | course   | C1          |
      | name     | Panel Demo  |
      | intro    | Panel test activity |

  Scenario: Teacher navigates all four panel tabs and sees data sections
    Given I am on "Course 1" course homepage logged in as "teacher1"
    When I follow "Panel Demo"
    And I follow "Panel docente"
    Then I should see "Resumen"
    And the panel tab "Resumen" should be selected
    And I should see "Inscritos"
    And I should see "Intentaron"
    And I should see "Enviaron"
    And I should see "Con borrador"
    When I click on "Por alumno" "link"
    Then the panel tab "Por alumno" should be selected
    And I should see "Alumno"
    And I should see "Nota"
    When I click on "Mapa de calor" "link"
    Then the panel tab "Mapa de calor" should be selected
    And the heatmap should have at least 1 rows and 1 columns
    When I click on "Exportar" "link"
    Then the panel tab "Exportar" should be selected
    And I should see "Formato de exportación"
    And I should see "CSV"
    And the page should pass accessibility tests with no critical violations
