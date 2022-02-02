Feature: Open in cloud storage
  As an OpenProject user with at least read access to a project
  I want to open files in cloud storage that are linked to project work packages of any type
  So that I can view, download and/or work with the file

  Background:
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Alice" has write access to project "Demo Project"
    And user "Brian" has at least read access to project "Demo Project"
    And project "Demo Project" has a set of work packages including type PHASE, TASK and MILESTONE
    And each work package has at least one file uploaded to cloud storage
    And user "Brian" has logged in to the web UI

  Scenario Outline: Open in the cloud storage of a work package
    Given user "Brian" has opened a <work-package-type> in the project "Demo Project"
    When user "Brian" uses the "Open in cloud storage" option to open a file
    Then a new browser tab should open displaying the web UI of the cloud storage with the file shown
    And if the cloud storage web UI can display that file type in the browser then it should be opened
    And if the cloud storage web UI cannot display that file type in the browser then the option to download should be given
    Examples:
      | work-package-type |
      | PHASE             |
      | TASK              |
      | MILESTONE         |

  Scenario Outline: List all files in the cloud storage of a work package
    Given user "Brian" has opened a <work-package-type> in the project "Demo Project"
    When user "Brian" uses the "List in cloud storage" option to open a file
    Then a new browser tab should open displaying the web UI of the cloud storage with all the files listed
    Examples:
      | work-package-type |
      | PHASE             |
      | TASK              |
      | MILESTONE         |
