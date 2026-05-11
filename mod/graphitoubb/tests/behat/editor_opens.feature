@mod @mod_graphitoubb @mod_graphitoubb_editor
Feature: Student opens the AFD editor and sees the toolbar
  In order to author an AFD automaton
  As a student
  I need to see the editor toolbar when I open a graphitoubb activity

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
      | name     | AFD Editor Test   |
      | intro    | Build a DFA for L |

  Scenario: Student opens the editor and sees the toolbar
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "AFD Editor Test"
    Then I should see "Add state"
