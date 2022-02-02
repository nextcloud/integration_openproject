Feature: Upload to cloud storage and link to a work package component
  As an OpenProject user with write access to a project
  I want to upload files to cloud storage linked to project work packages of any type
  So that the files can be viewed by others involved in the project

  Background:
    Given user "Alice" has been created
    And user "Alice" has write access to project "Demo Project"
    And project "Demo Project" has a set of work packages including type PHASE, TASK and MILESTONE
    And user "Alice" has logged in to the web UI

  Scenario Outline: Upload first file to cloud storage of a work package
    Given user "Alice" has opened a <work-package-type> in the project "Demo Project"
    When user "Alice" uses the "Upload to cloud storage" option to upload a file
    Then the file should be listed in the "Files in Cloud Storage" list
    Examples:
      | work-package-type |
      | PHASE             |
      | TASK              |
      | MILESTONE         |

  Scenario Outline: More than one file can be uploaded to cloud storage of one work package
    Given user "Alice" has opened a <work-package-type> in the project "Demo Project"
    And user "Alice" has uploaded a file to cloud storage
    When user "Alice" uses the "Upload to cloud storage" option to upload another file
    Then 2 files should be listed in the "Files in Cloud Storage" list
    Examples:
      | work-package-type |
      | PHASE             |
      | TASK              |
      | MILESTONE         |

  Scenario Outline: Update a file already uploaded to cloud storage of a work package
    Given user "Alice" has opened a <work-package-type> in the project "Demo Project"
    And user "Alice" has uploaded a file to cloud storage
    When user "Alice" uses the "Upload to cloud storage" option to upload a file of the same name
    And the user confirms that they want the file to be overwritten
    And then the latest file contents should be in the cloud storage
    # Question: cloud storage can usually do versions. Is there any requirement to provide access to versions directly from OpenProject?
    Examples:
      | work-package-type |
      | PHASE             |
      | TASK              |
      | MILESTONE         |

  Scenario Outline: Upload a folder to cloud storage of a work package
    Given user "Alice" has opened a <work-package-type> in the project "Demo Project"
    When user "Alice" uses the "Upload to cloud storage" option to upload a folder contain files and sub-folders
    Then the folder structure should be listed in the "Files in Cloud Storage" list
    # Question: is the set of files linked to cloud storage for a work package just a flat list, or can it have a folder structure?
    # sample "Then" requirement:
    # And the user can expand the folder structure of the cloud storage files for the work package
    # And the user can open a file, folder or sub-folder in the cloud storage
    # And the user can create a sub-folder
    # And the user can delete a sub-folder
    # And in general the user can "manage the files and folders" (move/rename...)
    Examples:
      | work-package-type |
      | PHASE             |
      | TASK              |
      | MILESTONE         |
