Feature: list work-packages in the side bar
  As a Nextcloud user
  I want see all work packages on OpenProject that are linked to a file
  So that I can
  - know the status of the file in workflows
  - know which work packages refer to the file and where/how it is used
  - can easily locate and navigate to connected information in OpenProject

  Background:
    Given user "Alice" has been created
    And user "Alice" has uploaded file with content "some data" to "/file.txt"
    And user "Alice" has logged in using the webUI

  Scenario: search for a work package to a file
    Given the user has opened "openProject" section of file "/file.txt" using the webUI
    When the user searches for work package with name "This is a " using the searchbar
    Then all the the work packages with subject "This is a " should be displayed on the webUI
    When the user adds the work package "This is a work package" using the webUI
    Then the work package "This is a work package" should be listed on the webUI

  Scenario: link multiple work packages to a file
    Given the user has opened "openProject" section of file "/file.txt" using the webUI
    When the user searches for work package with name "This is a work package" using the searchbar
    And the work package "This is a work package" is displayed in the drop down menu on the webUI
    And the user adds the work package "This is a work package" using the webUI
    And the user searches for work package with name "This is second work package" using the searchbar
    And the work package "This is second work package" is displayed in the drop down menu on the webUI
    And the user adds the work package "This is second work package" using the webUI
    And the user searches for work package with name "This is third work package" using the searchbar
    And the work package "This is third work package" is displayed in the drop down menu on the webUI
    And the user adds the work package "This is third work package" using the webUI
    Then the following work packages should be listed on the webUI:
      | work-packages               |
      | This is a work package      |
      | This is second work package |
      | This is third work package  |
