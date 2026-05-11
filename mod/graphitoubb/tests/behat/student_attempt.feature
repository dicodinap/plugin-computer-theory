@mod @mod_graphitoubb
Feature: A student creates and saves an AFD attempt
  In order to learn formal language theory
  As a student
  I need to be able to create an AFD and save my work

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email         |
      | student1 | Stu       | Dent     | s@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activity" exists:
      | activity | graphitoubb       |
      | course   | C1                |
      | name     | Practice DFA      |
      | intro    | Build a DFA for L |

  Scenario: Student opens activity and sees the editor
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "Practice DFA"
    Then I should see "Loading editor..."
