Feature: individual connection to OpenProject
  As a NextCloud user
  I want to be able to setup my individual connection to OpenProject
  So that I don't need to bother the NextCloud adminstrator

  As a NextCloud admin
  I want to be able to stop users to setup their individual connection to OpenProject
  So that I can enforce a higher level of security by using OAuth2
  and users have less chance to make mistakes

  Background:
    Given user "Alice" has been created
    And user "Alice" has uploaded file with content "some data" to "/file.txt"
    And user "Alice" has logged in using the webUI


  Scenario: default settings
    When the user displays the "OpenProject notifications" widget using the webUI
    Then the widget should display the message "No OpenProject account connected" on the webUI
    And the widget should display a button to link to the "Connected accounts" page on the "Settings" page on the webUI
    When the user opens the "OpenProject" details tab of the file "file.txt"
    Then the message "No OpenProject account connected" should be displayed in the details panel on the webUI
    And a link to the "Connected accounts" page on the "Settings" page should be displayed in the details panel on the webUI
    When the user navigates to the "Connected accounts" page on the "Settings" page using the webUI
    Then the OpenProject integration settings should be visible on the webUI


  Scenario: Administrator has allowed to use individual connections and user uses same OpenProject URL as the administrator
    Given the administrator has allowed to use individual connections to OpenProject
    And the administrator has setup the OpenProject integration with these settings:
      | OpenProject instance address | http://localhost:3000 |
      | Application ID               | 123                   |
      | Application secret           | 456                   |
    When the user navigates to the "Connected accounts" page on the "Settings" page using the webUI
    And the user enters "http://localhost:3000" as the OpenProject instance address
    Then a "Connect to OpenProject" button should be displayed
    But an "Access token" input field should not be displayed


  Scenario: Administrator has allowed to use individual connections but user uses different OpenProject URL to the one the administrator has setup
    Given the administrator has allowed to use individual connections to OpenProject
    And the administrator has setup the OpenProject integration with these settings:
      | OpenProject instance address | http://openproject.org |
      | Application ID               | 123                    |
      | Application secret           | 456                    |
    When the user navigates to the "Connected accounts" page on the "Settings" page using the webUI
    And the user enters "http://myproject.com" as the OpenProject instance address
    Then the "Access token" input field should be displayed
    But no "Connect to OpenProject" button should be displayed


  Scenario: Administrator has forbidden to use individual connections
    Given the administrator has forbidden to use individual connections to OpenProject
    When the user displays the "OpenProject notifications" widget using the webUI
    Then the widget should display the message "No OpenProject account connected" on the webUI
    But the widget should not display a button to link to the "Connected accounts" page on the "Settings" page on the webUI
    When the user opens the "OpenProject" details tab of the file "file.txt"
    Then the message "No OpenProject account connected" should be displayed in the details panel on the webUI
    But a link should not be displayed in the details panel on the webUI
    When the user navigates to the "Connected accounts" page on the "Settings" page using the webUI
    Then the OpenProject integration settings should not be visible on the webUI


  Scenario: Tokens of the user are invalidated if the administrator forbids to use individual connections after a user has setup a connection
    Given the administrator has allowed to use individual connections to OpenProject
    And user "Alice" has successfully established a connection to OpenProject with these settings:
      | OpenProject instance address | http://openproject |
      | Application ID               | 123                |
      | Application secret           | 456                |
    When the administrator forbids to use individual connections to OpenProject
    And the user displays the "OpenProject notifications" widget using the webUI
    Then the widget should display the message "No OpenProject account connected" on the webUI
    But the widget should not display a button to link to the "Connected accounts" page on the "Settings" page on the webUI
