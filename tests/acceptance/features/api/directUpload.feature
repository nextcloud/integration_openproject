@skip
Feature: API endpoint for direct upload

  As an OpenProject user
  I want to upload files to Nextcloud from inside OpenProject
  So that I don't need to leave OpenProject, open Nextcloud and then search for the same work package to create a link.

  As an OpenProject admin
  I want the OpenProject front-end to send the files directly to Nextcloud
  So that the long requests for uploading don't block any resources on the OpenProject back-end.

  Background:
    Given user "Alice" has been created


  Scenario Outline: Send a file to the direct-upload endpoint
    Given user "Alice" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | <file-name> |
      | data      | some data   |
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^<file-name>$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at <file-name> for user "Alice" should be "some data"
    Examples:
      | file-name         |
      | "textfile0.txt"   |
      | "असजिलो file"     |
      | "?&$%?§ file.txt" |


  Scenario Outline: Send an invalid filename to the direct-upload endpoint
    Given user "Alice" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | <file-name> |
      | data      | some data   |
    Then the HTTP status code should be "400"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^invalid file name$"}
      }
    }
    """
    Examples:
      | file-name             |
      | ""                    |
      | "  "                  |
      | "../textfile.txt"     |
      | "folder/textfile.txt" |
      | "text\file.txt"       |


  Scenario: Send an invalid token to the direct-upload endpoint
    When an anonymous user sends a multipart form data POST request to the "direct-upload/ABCabc123" endpoint with:
      | file_name | textfile.txt |
      | data      | some data    |
    Then the HTTP status code should be "401"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^unauthorized$"}
      }
    }
    """


  Scenario: Send a file with a filename that already exists (no overwrite parameter)
    Given user "Alice" has uploaded file with content "original data" to "/file.txt"
    And user "Alice" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt     |
      | data      | changed data |
    Then the HTTP status code should be "409"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^conflict$"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Alice" should be "original data"


  Scenario: Folder is deleted before upload happens
    Given user "Alice" has created folder "/forOP"
    And user "Alice" got a direct-upload token for "/forOP"
    And user "Alice" has deleted folder "/forOP"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt  |
      | data      | some data |
    Then the HTTP status code should be "404"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^folder not found$"}
      }
    }
    """


  Scenario: Folder is deleted and recreated (new fileid) before upload happens
    Given user "Alice" has created folder "/forOP"
    And user "Alice" got a direct-upload token for "/forOP"
    And user "Alice" has deleted folder "/forOP"
    And user "Alice" has created folder "/forOP"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt  |
      | data      | some data |
    Then the HTTP status code should be "404"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^folder not found$"}
      }
    }
    """


  Scenario Outline: Folder is renamed before upload happens
    Given user "Alice" has created folder "/forOP"
    And user "Alice" has created folder "/secondfolder"
    And user "Alice" got a direct-upload token for "/forOP"
    And user "Alice" has renamed folder "/forOP" to "<rename-destination>"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt  |
      | data      | some data |
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "<rename-destination>/file.txt" for user "Alice" should be "some data"
    Examples:
      | rename-destination  |
      | /renamed            |
      | /secondfolder/forOP |


  Scenario: Upload to a folder that is received by different routes
    Given user "Brian" has been created
    And user "Chandra" has been created
    And user "Dipak" has been created
    And user "Brian" has created folder "/toShare"
    And user "Brian" has shared folder "/toShare" with user "Alice" with "all" permissions
    And user "Brian" has shared folder "/toShare" with user "Chandra" with "all" permissions
    And user "Brian" has shared folder "/toShare" with user "Dipak" with "all" permissions
    And user "Chandra" has shared folder "/toShare" with user "Alice" with "all" permissions
    And user "Dipak" has shared folder "/toShare" with user "Alice" with "all" permissions
    And user "Alice" got a direct-upload token for "/toShare"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt  |
      | data      | some data |
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """


  Scenario: set overwrite to false and send file with an existing filename
    Given user "Alice" has uploaded file with content "original data" to "/file.txt"
    And user "Alice" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | overwrite | file.txt |
      | data      | false     | new data |
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file \(2\).txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Alice" should be "original data"
    And the content of file at "/file (2).txt" for user "Alice" should be "new data"


  Scenario: set overwrite to false and send file with an existing filename, also files with that name and suffixed numbers also exist
    Given user "Alice" has uploaded file with content "data 1" to "/file.txt"
    And user "Alice" has uploaded file with content "data 2" to "/file (2).txt"
    And user "Alice" has uploaded file with content "data 3" to "/file (3).txt"
    And user "Alice" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | overwrite | file.txt |
      | data      | false     | new data |
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file \(4\).txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Alice" should be "data 1"
    And the content of file at "/file (2).txt" for user "Alice" should be "data 2"
    And the content of file at "/file (3).txt" for user "Alice" should be "data 3"
    And the content of file at "/file (4).txt" for user "Alice" should be "new data"


  Scenario: set overwrite to false and send file with an existing filename (filename has already a number in brackets)
    Given user "Alice" has uploaded file with content "original data" to "/file (2).txt"
    And user "Alice" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | overwrite | file (2).txt |
      | data      | false     | new data     |
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file \(2\)\(2\).txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Alice" should be "original data"
    And the content of file at "/file (2)(2).txt" for user "Alice" should be "new data"


  Scenario: set overwrite to true and send file with an existing filename
    Given user "Alice" has uploaded file with content "original data" to "/file.txt"
    And user "Alice" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | overwrite | file.txt |
      | data      | true      | new data |
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Alice" should be "new data"


  Scenario: set overwrite to true and send file with an existing filename, but no permissions to overwrite
    Given user "Brian" has been created
    And user "Brian" has uploaded file with content "original data" to "/file.txt"
    And user "Brian" has shared file "/file.txt" with user "Alice" with "read" permissions
    And user "Alice" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | overwrite | file.txt |
      | data      | true      | new data |
    Then the HTTP status code should be "403"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^not enough permissions$"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Alice" should be "original data"
