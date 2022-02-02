Feature: See project meta-data directly in cloud storage
  As an OpenProject user with access to a project
  I want to see information about the Project and work package when viewing files directly in the cloud storage
  So that I can know more about the context of the files

  Background:
    Given user "Alice" has been created
    And user "Alice" has write access to project "Demo Project"
    And project "Demo Project" has a set of work packages including type PHASE, TASK and MILESTONE
    And each work package has at least one file uploaded to cloud storage

  Scenario: Navigate project and work package files with the cloud storage web UI
    When user "Alice" browses to the web UI of the cloud storage and logs in
    Then user "Alice" can see a folder for each OpenProject work package that is named in some user-friendly way
    # how to name the root-level cloud storage of each work package so that users can navigate easily?
    # there might be multiple projects with work packages in each, so maybe there should be a top-level folder for each project
    # and a sub-folder for each work package that is visible to the user?

  Scenario: View project meta-data with the cloud storage web UI
    When user "Alice" browses to the web UI of the cloud storage and logs in
    And user "Alice" opens the details panel of a project folder
    Then user "Alice" can see the full name and description of the project
    # And what other project meta-data could be sync/recorded to the cloud storage for display?

  Scenario: View work package meta-data with the cloud storage web UI
    When user "Alice" browses to the web UI of the cloud storage and logs in
    And use "Alice" browses into a project folder
    And user "Alice" opens the details panel of a work package folder
    Then user "Alice" can see the full name and description of the work package
    # And what other work package meta-data could be sync/recorded to the cloud storage for display?
