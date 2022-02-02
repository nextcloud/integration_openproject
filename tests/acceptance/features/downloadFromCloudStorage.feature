Feature: Download from cloud storage
  As an OpenProject user with at least read access to a project
  I want to download files from cloud storage that are linked to project work packages of any type
  So that I can view the file in a local application on my computer/device

  Background:
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Alice" has write access to project "Demo Project"
    And user "Brian" has at least read access to project "Demo Project"
    And project "Demo Project" has a set of work packages including type PHASE, TASK and MILESTONE
    And each work package has at least one file uploaded to cloud storage
    And user "Brian" has logged in to the web UI

  Scenario Outline: Download from the cloud storage of a work package
    Given user "Brian" has opened a <work-package-type> in the project "Demo Project"
    When user "Brian" uses the "Download from cloud storage" option to download a file
    Then the file should be available in the local downloads folder of user "Brian"
    Examples:
      | work-package-type |
      | PHASE             |
      | TASK              |
      | MILESTONE         |
