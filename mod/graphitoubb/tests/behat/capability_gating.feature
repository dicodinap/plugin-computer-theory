@mod @mod_graphitoubb
Feature: Capability gating restricts access to the AFD editor
  In order to protect activity access
  As a site administrator
  I need to ensure users without attempt capability cannot access the editor

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

  Scenario: User enrolled as guest cannot access the editor without attempt capability
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | viewer1  | View      | Only     | v@example.com  |
    And the following "course enrolments" exist:
      | user    | course | role  |
      | viewer1 | C1     | guest |
    And I log in as "viewer1"
    And I am on "Course 1" course homepage
    When I follow "Practice DFA"
    Then I should see "you do not currently have permissions"

  Scenario: Student without attempt capability cannot enter the editor
    Given the following "permission overrides" exist:
      | capability              | permission | role    | contextlevel | reference |
      | mod/graphitoubb:attempt | Prevent    | student | Course       | C1        |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "Practice DFA"
    Then I should see "you do not currently have permissions"

  Scenario: User enrolled as guest cannot access the editor
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | viewer1  | View      | Only     | v@example.com  |
    And the following "course enrolments" exist:
      | user    | course | role  |
      | viewer1 | C1     | guest |
    And I log in as "viewer1"
    And I am on "Course 1" course homepage
    When I follow "Practice DFA"
    Then I should see "you do not currently have permissions"
