Feature: Manage files directly in cloud storage
  As an OpenProject user with write access to a project
  I want to manage files directly in cloud storage that are linked to project work packages of any type
  So that I can sort out and update the data for a work package

  Background:
    Given user "Alice" has been created
    And user "Alice" has write access to project "Demo Project"
    And project "Demo Project" has a set of work packages including type PHASE, TASK and MILESTONE
    And each work package has at least one file uploaded to cloud storage

  Scenario Outline: Open the cloud storage of a work package
    When user "Alice" browses to the web UI of the cloud storage and logs in
    Then user "Alice" can see a folder for each OpenProject work package that she has access to
    # Note: Alice might see other files and folders that are her personal data,
    # or shares received from other users on the cloud storage
    # The cloud storage is not necessarily dedicated to storing just OpenProject files
    Examples:
      | work-package-type |
      | PHASE             |
      | TASK              |
      | MILESTONE         |

  Scenario Outline: Manage the cloud storage of a work package with the cloud storage web UI
    When user "Alice" browses to the web UI of the cloud storage and logs in
    And user "Alice" opens the folder for an OpenProject work package
    Then user "Alice" can see the files for that OpenProject work package
    And user "Alice" can manage the files for that OpenProject work package
    # Note: manage includes download, open, rename, move, delete, upload new, upload overwrite...
    #       this is essentially a "share" or "space" that is "linked" to that OpenProject work package
    #       The possible actions are limited by the access level (read, write etc) that the user has to the Work Package element
    And changes that "Alice" makes in the cloud storage are seen in the OpenProject work package UI
    # Note: this means that OpenProject and the cloud storage will need to be able to "keep in sync" in some way
    #       Changes can come from either side.
    #       For example, OpenProject will need to "discover" somehow that a file has been deleted from cloud storage
    #       and not show it in the OpenProject Work Package UI.
    Examples:
      | work-package-type |
      | PHASE             |
      | TASK              |
      | MILESTONE         |

  Scenario Outline: Manage the cloud storage of a work package with the cloud storage sync tools
    Given user "Alice" has set up a cloud storage sync tool
    # for example, desktop sync client (there might also be Android, iOS etc sync clients)
    And user "Alice" has synced her files to her local device
    # for example, her laptop
    Then user "Alice" can manage the files for an OpenProject work package on her local device
    # Note: manage includes open, rename, move, delete, create new, overwrite...
    #       this is essentially a "share" or "space" that is "linked" to that OpenProject work package
    #       The possible actions are limited by the access level (read, write etc) that the user has to the Work Package element
    And changes that "Alice" makes on her local device are seen in the cloud storage
    # (that is the usual operation of the sync client - nothing special about OpenProject)
    And changes that "Alice" makes in the cloud storage are seen in the OpenProject work package UI
    # The changes that sync up to the cloud storage also end up being "synced" with the OpenProject work package
    Examples:
      | work-package-type |
      | PHASE             |
      | TASK              |
      | MILESTONE         |
