@skip
Feature: API endpoint for direct upload

  As an OpenProject user
  I want to upload files to Nextcloud from inside OpenProject
  So that I get my stuff done faster

  As an OpenProject admin
  I want the OpenProject front-end to send the files directly to Nextcloud
  So that the long requests for uploading don't block any resources on the OpenProject back-end.

  Background:
    Given user "Alice" has been created

  Scenario Outline: Send a file to the direct-upload endpoint
    Given user "Alice" got a direct-upload token for "/"
    When user "Alice" sends a PUT request to the "direct-upload" endpoint with:
      | file_name   | direct-upload token | data      |
      | <file-name> | %last_created%      | some data |
    Then the HTTP status code should be "200"
    And the content of file "/<file-name>" for user "Alice" should be "some data"
    Examples:
      | file-name         |
      | "textfile0.txt"   |
      | "असजिलो file"     |
      | "?&$%?§ file.txt" |

  Scenario Outline: Send an invalid filename to the direct-upload endpoint
    Given user "Alice" got a direct-upload token for "/"
    When user "Alice" sends a PUT request to the "direct-upload" endpoint with:
      | file_name   | direct-upload token | data      |
      | <file-name> | %last_created%      | some data |
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
    When user "Alice" sends a PUT request to the "direct-upload" endpoint with:
      | file_name    | direct-upload token | data      |
      | textfile.txt | ABCabc123           | some data |
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



