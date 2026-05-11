@mod @mod_graphitoubb
Feature: Teacher views student attempts report
  In order to monitor student progress
  As a teacher
  I need to be able to view the student attempts report

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email         |
      | teacher1 | Tea       | Cher     | t@example.com |
      | student1 | Stu       | Dent     | s@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity | graphitoubb       |
      | course   | C1                |
      | name     | Practice DFA      |
      | intro    | Build a DFA for L |

  Scenario: Teacher sees View report button on activity page
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I follow "Practice DFA"
    Then I should see "View report"

  Scenario: Teacher navigates to report page and sees attempts header
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Practice DFA"
    When I press "View report"
    Then I should see "Student attempts"
