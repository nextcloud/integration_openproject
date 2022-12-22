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
