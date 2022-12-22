Feature: API endpoint to prepare direct upload

  As an OpenProject user
  I want to upload files to Nextcloud from inside OpenProject
  So that I don't need to leave OpenProject, open Nextcloud and then search for the same work package to create a link.

  As an OpenProject admin
  I want the OpenProject front-end to send the files directly to Nextcloud
  So that the long requests for uploading don't block any resources on the OpenProject back-end.

  Background:
    Given user "Alice" has been created

  Scenario Outline: Get a direct-upload token for a folder
    Given user "Alice" has created folder <folder>
    When user "Alice" sends a GET request to the direct-upload endpoint with the ID of <folder>
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "token",
        "expires_on"
      ],
      "properties": {
          "token": {"type": "string", "pattern": "^[A-Za-z0-9]{64}$"},
          "expires_on" : {"type" : "integer", "minimum": %now+3500s%, "maximum": %now+3600s%}
      }
    }
    """
    Examples:
      | folder                |
      | "/forOpenProject"     |
      | "/folder with spaces" |
      | "/a/sub/folder"       |
      | "/असजिलो folder"      |
      | "/?&$%?§ folder"      |


  Scenario: Try to get a direct-upload token for the root folder
    When user "Alice" sends a GET request to the direct-upload endpoint with the ID of "/"
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "token",
        "expires_on"
      ],
      "properties": {
          "token": {"type": "string", "pattern": "^[A-Za-z0-9]{64}$"},
          "expires_on" : {"type" : "integer", "minimum": %now+3500s%, "maximum": %now+3600s%}
      }
    }
    """


  Scenario Outline: Get a direct-upload token for a folder received as share
    Given user "Brian" has been created
    And user "Brian" has created folder "/toShare"
    And user "Brian" has shared folder "/toShare" with user "Alice" with "<permissions>" permissions
    When user "Alice" sends a GET request to the direct-upload endpoint with the ID of "/toShare"
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "token",
        "expires_on"
      ],
      "properties": {
          "token": {"type": "string", "pattern": "^[A-Za-z0-9]{64}$"},
          "expires_on" : {"type" : "integer", "minimum": %now+3500s%, "maximum": %now+3600s%}
      }
    }
    """
    Examples:
      | permissions               |
      | all                       |
      | read+update+create+delete |
      | read+update+create+share  |
      | read+create+delete+share  |
      | read+create               |


  Scenario: Try to get a direct-upload token for a file
    Given user "Alice" has uploaded file with content "some data" to "/file.txt"
    When user "Alice" sends a GET request to the direct-upload endpoint with the ID of "/file.txt"
    Then the HTTP status code should be "404"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^folder not found or not enough permissions$"}
      }
    }
    """


  Scenario: Try to get a direct-upload token for a non existing folder-id
    When user "Alice" sends a GET request to the direct-upload endpoint with the ID "999999999"
    Then the HTTP status code should be "404"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^folder not found or not enough permissions$"}
      }
    }
    """


  Scenario: Try to get a direct-upload token for a folder without create permissions
    Given user "Brian" has been created
    And user "Brian" has created folder "/toShare"
    And user "Brian" has shared folder "/toShare" with user "Alice" with "read+update+delete+share" permissions
    When user "Alice" sends a GET request to the direct-upload endpoint with the ID of "/toShare"
    Then the HTTP status code should be "404"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^folder not found or not enough permissions$"}
      }
    }
    """


  Scenario: Try to get token as non-existent user
    When user "test" sends a GET request to the direct-upload endpoint with the ID "123"
    Then the HTTP status code should be "401"


  Scenario: Tokens should be random
    Given user "Brian" has been created
    And user "Alice" has created folder "/folder for OpenProject"
    And user "Brian" has created folder "/folder for OpenProject"
    When user "Alice" gets a direct-upload token for "/folder for OpenProject"
    And user "Brian" gets a direct-upload token for "/folder for OpenProject"
    And user "Alice" gets a direct-upload token for "/"
    And user "Brian" gets a direct-upload token for "/"
    Then all direct-upload tokens should be different
