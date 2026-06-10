# SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: setup the integration through an API


  Scenario: check version of uploaded file inside a team folder
    Given user "Carol" has been created
    And user "Carol" has been added to the group "OpenProject"
    And user "Carol" has created folder "/OpenProject/OpenProject/project-demo"
    And user "Carol" got a direct-upload token for "/OpenProject/OpenProject/project-demo"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt   |
      | data      | 0987654321 |
    Then the version folder of file "/OpenProject/OpenProject/project-demo/file.txt" for user "Carol" should contain "1" element
    When user "Carol" deletes folder "/OpenProject/OpenProject/project-demo"
    Then the HTTP status code should be 204


  Scenario: check version of uploaded file after an update inside a team folder
    Given user "Carol" has been created
    And user "Carol" has been added to the group "OpenProject"
    And user "OpenProject" has created folder "/OpenProject/OpenProject/project-test"
    And user "Carol" has uploaded file with content "0123456789" to "/OpenProject/OpenProject/project-test/file.txt"
    And user "Carol" got a direct-upload token for "/OpenProject/OpenProject/project-test"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt   |
      | data      | 1234567890 |
      | overwrite | true       |
    Then the HTTP status code should be "200"
    And the version folder of file "/OpenProject/OpenProject/project-test/file.txt" for user "Carol" should contain "2" elements
    When user "Carol" deletes folder "/OpenProject/OpenProject/project-test"
    Then the HTTP status code should be 204


  Scenario: check OpenProjectNoAutomaticProjectFolders group after user is removed from OpenProject group (removed by group admin)
    Given user "Carol" has been created
    And user "Carol" has been added to the group "OpenProject"
    When the group admin "OpenProject" removes the user "Carol" from group "OpenProject"
    Then the HTTP status code should be 200
    And user "Carol" should belong to group "OpenProjectNoAutomaticProjectFolders"
    And user "Carol" should not belong to group "OpenProject"


  Scenario: user not in OpenProject group is removed from another group (removed by group admin)
    Given  group "grp1" has been created
    And user "Carol" has been created
    And the following users have been added to the following groups
      | username    | groupname |
      | Carol       | grp1      |
      | OpenProject | grp1      |
    And user "OpenProject" has been assigned the role group admin of group "grp1"
    # group admin cannot remove a user from their last remaining group
      When the group admin "OpenProject" removes the user "Carol" from group "grp1"
    Then the HTTP status code should be 400
    And user "Carol" should belong to group "grp1"
    And user "Carol" should not belong to group "OpenProjectNoAutomaticProjectFolders"


  Scenario: user not in OpenProject group but has multiple group memberships is removed from one group (removed by group admin)
    Given group "grp1" has been created
    And group "grp2" has been created
    And user "Carol" has been created
    And the following users have been added to the following groups
      | username    | groupname |
      | Carol       | grp1      |
      | Carol       | grp2      |
      | OpenProject | grp1      |
    And user "OpenProject" has been assigned the role group admin of group "grp1"
    And user "OpenProject" has been assigned the role group admin of group "grp2"
      When the group admin "OpenProject" removes the user "Carol" from group "grp1"
    Then the HTTP status code should be 200
    And the following users should not belong to the following groups
      | username | groupname                            |
      | Carol    | grp1                                 |
      | Carol    | OpenProjectNoAutomaticProjectFolders |
    And user "Carol" should belong to group "grp2"


  Scenario: user in OpenProject and other groups (removed by group admin)
    Given  group "grp1" has been created
    And user "Carol" has been created
    And the following users have been added to the following groups
      | username    | groupname   |
      | Carol       | grp1        |
      | Carol       | OpenProject |
      | OpenProject | grp1        |
    And user "OpenProject" has been assigned the role group admin of group "grp1"
    When the group admin "OpenProject" removes the user "Carol" from group "grp1"
    Then the HTTP status code should be 200
    And the following users should not belong to the following groups
      | username | groupname                            |
      | Carol    | grp1                                 |
      | Carol    | OpenProjectNoAutomaticProjectFolders |
    # A user cannot be removed from their last group
    Given user "Carol" has been added to the group "grp1"
    When the group admin "OpenProject" removes the user "Carol" from group "OpenProject"
    Then the HTTP status code should be 200
    And user "Carol" should belong to group "grp1"
    And user "Carol" should belong to group "OpenProjectNoAutomaticProjectFolders"
    And user "Carol" should not belong to group "OpenProject"


  Scenario: multiple user in OpenProject groups and only one gets removed (removed by group admin)
    Given user "Alex" has been created
    And user "Brian" has been created
    And user "Carol" has been created
    And the following users have been added to the following groups
      | username | groupname   |
      | Alex     | OpenProject |
      | Brian    | OpenProject |
      | Carol    | OpenProject |
      When the group admin "OpenProject" removes the user "Carol" from group "OpenProject"
    Then the HTTP status code should be 200
    And user "Carol" should belong to group "OpenProjectNoAutomaticProjectFolders"
    And the following users should not belong to the following groups
      | username | groupname                            |
      | Alex     | OpenProjectNoAutomaticProjectFolders |
      | Brian    | OpenProjectNoAutomaticProjectFolders |
      | Carol    | OpenProject                          |


  Scenario: user is in multiple groups including OpenProject and is removed from another group (removed by group admin)
    Given group "grp1" has been created
    And user "Carol" has been created
    And the following users have been added to the following groups
      | username    | groupname   |
      | Carol       | OpenProject |
      | Carol       | grp1        |
      | OpenProject | grp1        |
    And user "OpenProject" has been assigned the role group admin of group "grp1"
    When the group admin "OpenProject" removes the user "Carol" from group "grp1"
    Then the HTTP status code should be 200
    And the following users should not belong to the following groups
      | username | groupname                            |
      | Carol    | grp1                                 |
      | Carol    | OpenProjectNoAutomaticProjectFolders |
    And user "Carol" should belong to group "OpenProject"


  Scenario: user not in OpenProject group is removed from another group (removed by admin)
    Given  group "grp1" has been created
    And user "Carol" has been created
    And the following users have been added to the following groups
      | username    | groupname |
      | Carol       | grp1      |
      | OpenProject | grp1      |
    And user "OpenProject" has been assigned the role group admin of group "grp1"
    When the administrator removes the user "Carol" from group "grp1"
    Then the HTTP status code should be 200
    And the following users should not belong to the following groups
      | username | groupname                            |
      | Carol    | grp1                                 |
      | Carol    | OpenProjectNoAutomaticProjectFolders |


  Scenario: user not in OpenProject group but has multiple group memberships is removed from one group (removed by admin)
    Given group "grp1" has been created
    And group "grp2" has been created
    And user "Carol" has been created
    And the following users have been added to the following groups
      | username    | groupname |
      | Carol       | grp1      |
      | Carol       | grp2      |
      | OpenProject | grp1      |
    And user "OpenProject" has been assigned the role group admin of group "grp1"
    And user "OpenProject" has been assigned the role group admin of group "grp2"
    When the administrator removes the user "Carol" from group "grp1"
    Then the HTTP status code should be 200
    And the following users should not belong to the following groups
      | username | groupname                            |
      | Carol    | grp1                                 |
      | Carol    | OpenProjectNoAutomaticProjectFolders |
    And user "Carol" should belong to group "grp2"


  Scenario: user in OpenProject and other groups (removed by admin)
    Given  group "grp1" has been created
    And user "Carol" has been created
    And the following users have been added to the following groups
      | username    | groupname   |
      | Carol       | grp1        |
      | Carol       | OpenProject |
      | OpenProject | grp1        |
    And user "OpenProject" has been assigned the role group admin of group "grp1"
    When the administrator removes the user "Carol" from group "grp1"
    Then the HTTP status code should be 200
    And the following users should not belong to the following groups
      | username | groupname                            |
      | Carol    | grp1                                 |
      | Carol    | OpenProjectNoAutomaticProjectFolders |
    When the administrator removes the user "Carol" from group "OpenProject"
    Then the HTTP status code should be 200
    And user "Carol" should belong to group "OpenProjectNoAutomaticProjectFolders"
    And user "Carol" should not belong to group "OpenProject"
