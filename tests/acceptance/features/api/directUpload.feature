Feature: API endpoint for direct upload

  As an OpenProject user
  I want to upload files to Nextcloud from inside OpenProject
  So that I don't need to leave OpenProject, open Nextcloud and then search for the same work package to create a link.

  As an OpenProject admin
  I want the OpenProject front-end to send the files directly to Nextcloud
  So that the long requests for uploading don't block any resources on the OpenProject back-end.

  Background:
    Given user "Carol" has been created


  Scenario Outline: Send a file to the direct-upload endpoint
    Given user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | "<valid-file-name>" |
      | data      | some data           |
    Then the HTTP status code should be "201"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^<pattern>$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "<file-name>" for user "Carol" should be "some data"
    Examples:
      | valid-file-name     | file-name       | pattern                     |
      | textfile0.txt       | textfile0.txt   | textfile0\\.txt             |
      | असजिलो file         | असजिलो file     | असजिलो file                 |
      | ?&$%?§ file.txt     | ?&$%?§ file.txt | \\?\\&\\$\\%\\?§ file\\.txt |
      | ../textfile.txt     | textfile.txt    | textfile\\.txt              |
      | folder/testfile.txt | testfile.txt    | testfile\\.txt              |
      | text\file.txt       | file.txt        | file\\.txt                  |


  Scenario: Send an invalid filename to the direct-upload endpoint
    Given user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | "         " |
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

  Scenario: Send an empty filename to the direct-upload endpoint or exceed max_post_size
    # we cannot distinguish both cases
    Given user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | ""          |
      | data      | some data   |
    Then the HTTP status code should be "413"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error",
        "upload_limit"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^File was not uploaded\\. post_max_size exceeded\\?$"},
          "upload_limit": {"type": "integer"}
      }
    }
    """


  Scenario: send a token that doesn't exist to the direct-upload endpoint
    When an anonymous user sends a multipart form data POST request to the "direct-upload/4ojy3w2yqcMeqmfYMjJSfrr9n56wqJdPZPBdsSsiRD4A6SooKaQqqoKnpmGcFBiw" endpoint with:
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
    Given user "Carol" has uploaded file with content "original data" to "/file.txt"
    And user "Carol" got a direct-upload token for "/"
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
          "error": {"type": "string", "pattern": "^conflict, file name already exists$"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Carol" should be "original data"


  Scenario: Folder is deleted before upload happens
    Given user "Carol" has created folder "/forOP"
    And user "Carol" got a direct-upload token for "/forOP"
    And user "Carol" has deleted folder "/forOP"
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
          "error": {"type": "string", "pattern": "^folder not found or not enough permissions$"}
      }
    }
    """


  Scenario: Folder is deleted and recreated (new fileid) before upload happens
    Given user "Carol" has created folder "/forOP"
    And user "Carol" got a direct-upload token for "/forOP"
    And user "Carol" has deleted folder "/forOP"
    And user "Carol" has created folder "/forOP"
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
          "error": {"type": "string", "pattern": "^folder not found or not enough permissions$"}
      }
    }
    """


  Scenario Outline: Folder is renamed before upload happens
    Given user "Carol" has created folder "/forOP"
    And user "Carol" has created folder "/secondfolder"
    And user "Carol" got a direct-upload token for "/forOP"
    And user "Carol" has renamed folder "/forOP" to "<rename-destination>"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt  |
      | data      | some data |
    Then the HTTP status code should be "201"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "<rename-destination>/file.txt" for user "Carol" should be "some data"
    Examples:
      | rename-destination  |
      | /renamed            |
      | /secondfolder/forOP |


  Scenario: Upload to a folder that is received by different routes
    Given user "Brian" has been created
    And user "Chandra" has been created
    And user "Dipak" has been created
    And user "Brian" has created folder "/toShare"
    And user "Brian" has shared folder "/toShare" with user "Carol" with "all" permissions
    And user "Brian" has shared folder "/toShare" with user "Chandra" with "all" permissions
    And user "Brian" has shared folder "/toShare" with user "Dipak" with "all" permissions
    And user "Chandra" has shared folder "/toShare" with user "Carol" with "all" permissions
    And user "Dipak" has shared folder "/toShare" with user "Carol" with "all" permissions
    And user "Carol" got a direct-upload token for "/toShare"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt  |
      | data      | some data |
    Then the HTTP status code should be "201"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """

  Scenario: Use the same token after one successful upload
    Given user "Carol" got a direct-upload token for "/"
    And an anonymous user has sent a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | testfile.txt |
      | data      | some data    |
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt  |
      | data      | some data |
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

  Scenario: Use the same token after one unsuccessful upload
    Given user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | ""        |
      | data      | some data |
    Then the HTTP status code should be "413"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt  |
      | data      | some data |
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

  Scenario: use a token created by a user that was disabled after creating the token
    Given user "Carol" got a direct-upload token for "/"
    And user "Carol" has been disabled
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt  |
      | data      | some data |
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


  Scenario: use a token created by a user that was deleted after creating the token
    Given user "Carol" got a direct-upload token for "/"
    And user "Carol" has been deleted
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt  |
      | data      | some data |
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


  Scenario: use a token created by a user that was deleted and recreated after creating the token
    Given user "Carol" got a direct-upload token for "/"
    And user "Carol" has been deleted
    And user "Carol" has been created
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
          "error": {"type": "string", "pattern": "^folder not found or not enough permissions$"}
      }
    }
    """


  Scenario: send file to a share without create permissions
    Given user "Brian" has been created
    And user "Brian" has created folder "/toShare"
    And user "Brian" has shared folder "/toShare" with user "Carol" with "all" permissions
    And user "Carol" got a direct-upload token for "/toShare"
    And user "Brian" has changed the share permissions of last created share to "read+update+delete+share"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt |
      | data      | new data |
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

  Scenario: overwrite a file to a share without create but with update permissions
    Given user "Brian" has been created
    And user "Brian" has created folder "/toShare"
    And user "Brian" has uploaded file with content "original data" to "/toShare/file.txt"
    And user "Brian" has shared folder "/toShare" with user "Carol" with "all" permissions
    And user "Carol" got a direct-upload token for "/toShare"
    And user "Brian" has changed the share permissions of last created share to "read+update+delete+share"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt |
      | data      | new data |
      | overwrite | true     |
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
          "file_name": {"type": "string", "pattern": "^file\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/toShare/file.txt" for user "Carol" should be "new data"
    And the content of file at "/toShare/file.txt" for user "Brian" should be "new data"


  Scenario: set overwrite to false and send file with an existing filename
    Given user "Carol" has uploaded file with content "original data" to "/file.txt"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt |
      | data      | new data |
      | overwrite | false    |
    Then the HTTP status code should be "201"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file \\(2\\)\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Carol" should be "original data"
    And the content of file at "/file (2).txt" for user "Carol" should be "new data"


  Scenario: set overwrite to false and send file with an existing filename, also files with that name and suffixed numbers also exist
    Given user "Carol" has uploaded file with content "data 1" to "/file.txt"
    And user "Carol" has uploaded file with content "data 2" to "/file (2).txt"
    And user "Carol" has uploaded file with content "data 3" to "/file (3).txt"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt |
      | data      | new data |
      | overwrite | false    |
    Then the HTTP status code should be "201"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file \\(4\\)\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Carol" should be "data 1"
    And the content of file at "/file (2).txt" for user "Carol" should be "data 2"
    And the content of file at "/file (3).txt" for user "Carol" should be "data 3"
    And the content of file at "/file (4).txt" for user "Carol" should be "new data"


  Scenario: set overwrite to false and send file with an existing filename (filename has already a number in brackets)
    Given user "Carol" has uploaded file with content "original data" to "/file (2).txt"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file (2).txt |
      | data      | new data     |
      | overwrite | false        |
    Then the HTTP status code should be "201"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file \\(3\\)\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/file (2).txt" for user "Carol" should be "original data"
    And the content of file at "/file (3).txt" for user "Carol" should be "new data"


  Scenario: set overwrite to true and send file with an existing filename
    Given user "Carol" has uploaded file with content "original data" to "/file.txt"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt |
      | data      | new data |
      | overwrite | true     |
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
          "file_name": {"type": "string", "pattern": "^file\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Carol" should be "new data"


  Scenario: set overwrite to true and send file with an existing filename, but no permissions to overwrite
    Given user "Brian" has been created
    And user "Brian" has uploaded file with content "original data" to "/file.txt"
    And user "Brian" has shared file "/file.txt" with user "Carol" with "read" permissions
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt |
      | data      | new data |
      | overwrite | true     |
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
    And the content of file at "/file.txt" for user "Carol" should be "original data"


  Scenario Outline: set overwrite to an invalid value
    Given user "Carol" has uploaded file with content "original data" to "/file.txt"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt    |
      | data      | new data    |
      | overwrite | <overwrite> |
    Then the HTTP status code should be "400"
    And the data of the response should match
    """"
    {
    "type": "object",
    "not": {
      "required": [
          "file_name",
          "file_id"
        ]
      },
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^invalid overwrite value$"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Carol" should be "original data"
    Examples:
      | overwrite |
      | 1         |
      | 0         |
      | null      |
      |           |
      | rubbish   |


  Scenario: CORS preflight request
    Given user "Carol" got a direct-upload token for "/"
    When an anonymous user sends an OPTIONS request to the "direct-upload/%last-created-direct-upload-token%" endpoint with these headers:
      | header                         | value                    |
      | Access-Control-Request-Method  | POST                     |
      | Access-Control-Request-Headers | origin, x-requested-with |
      | Origin                         | https://openproject.org  |
    Then the HTTP status code should be "200"
    And the following headers should be set
      | header                       | value                   |
      | Access-Control-Allow-Origin  | https://openproject.org |
      | Access-Control-Allow-Methods | POST                    |


  Scenario Outline: set overwrite and send a new file
    Given user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt    |
      | data      | new data    |
      | overwrite | <overwrite> |
    Then the HTTP status code should be "201"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Carol" should be "new data"
    Examples:
      | overwrite |
      | true      |
      | false     |


  Scenario: set overwrite to true and send a file with an existing folder name
    Given user "Carol" has created folder "file.txt"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt |
      | data      | new data |
      | overwrite | true     |
    Then the HTTP status code should be "409"
    And the data of the response should match
    """"
    {
    "type": "object",
    "not": {
      "required": [
          "file_name",
          "file_id"
        ]
      },
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^overwrite is not allowed on non-files$"}
      }
    }
    """


  Scenario: set overwrite to false and send a file with an existing folder name
    Given user "Carol" has created folder "file.txt"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt |
      | data      | new data |
      | overwrite | false    |
    Then the HTTP status code should be "201"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file \\(2\\)\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/file (2).txt" for user "Carol" should be "new data"


  Scenario: don't set overwrite and send a file with an existing folder name
    Given user "Carol" has created folder "file.txt"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt |
      | data      | new data |
    Then the HTTP status code should be "409"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^conflict, file name already exists$"}
      }
    }
    """


  Scenario: Upload a file that just fits into the users quota
    Given the quota of user "Carol" has been set to "10 B"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | textfile0.txt |
      | data      | 1234567890    |
    Then the HTTP status code should be "201"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^textfile0\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "textfile0.txt" for user "Carol" should be "1234567890"


  Scenario: Upload a file exceeding the users quota
    Given the quota of user "Carol" has been set to "9 B"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt   |
      | data      | 1234567890 |
    Then the HTTP status code should be "507"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^insufficient quota$"}
      }
    }
    """


  Scenario: Upload a file into a folder, exceeding the users quota
    Given the quota of user "Carol" has been set to "9 B"
    And user "Carol" has created folder "/forOP"
    And user "Carol" got a direct-upload token for "/forOP"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt   |
      | data      | 1234567890 |
    Then the HTTP status code should be "507"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^insufficient quota$"}
      }
    }
    """


  Scenario: Upload a file into a shared folder exceeding the quota of the user sharing the folder
    Given user "Brian" has been created
    And the quota of user "Carol" has been set to "10 B"
    And the quota of user "Brian" has been set to "9 B"
    And user "Brian" has created folder "/toShare"
    And user "Brian" has shared folder "/toShare" with user "Carol" with "all" permissions
    And user "Carol" got a direct-upload token for "/toShare"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt   |
      | data      | 1234567890 |
    Then the HTTP status code should be "507"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^insufficient quota$"}
      }
    }
    """


  Scenario: Upload a file into a shared folder exceeding the quota of sharee but not that of sharer
    Given user "Brian" has been created
    And the quota of user "Carol" has been set to "10 B"
    And the quota of user "Brian" has been set to "20 B"
    And user "Brian" has created folder "/toShare"
    And user "Brian" has shared folder "/toShare" with user "Carol" with "all" permissions
    And user "Carol" got a direct-upload token for "/toShare"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt        |
      | data      | 123456789012345 |
    Then the HTTP status code should be "201"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^file\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/toShare/file.txt" for user "Carol" should be "123456789012345"
    And the content of file at "/toShare/file.txt" for user "Brian" should be "123456789012345"


  Scenario: overwrite an existing file with content that fits the quota. Needed quota is sizeof(old data)+sizeof(new data)
    Given the quota of user "Carol" has been set to "20 B"
    And user "Carol" has uploaded file with content "1234567890" to "/file.txt"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt   |
      | data      | 0987654321 |
      | overwrite | true       |
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
          "file_name": {"type": "string", "pattern": "^file\\.txt$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Carol" should be "0987654321"


  Scenario: try to overwrite an existing file with content that exceeds the quota. Needed quota is sizeof(old data)+sizeof(new data)
    Given the quota of user "Carol" has been set to "19 B"
    And user "Carol" has uploaded file with content "1234567890" to "/file.txt"
    And user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | file.txt   |
      | data      | 0987654321 |
      | overwrite | true       |
    Then the HTTP status code should be "507"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^insufficient quota$"}
      }
    }
    """
    And the content of file at "/file.txt" for user "Carol" should be "1234567890"


  Scenario: Try to upload a file with a blacklisted file name
    Given user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | .htaccess              |
      | data      | <IfModule mod_alias.c> |
    Then the HTTP status code should be "403"
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


  Scenario: upload a hidden file
    Given user "Carol" got a direct-upload token for "/"
    When an anonymous user sends a multipart form data POST request to the "direct-upload/%last-created-direct-upload-token%" endpoint with:
      | file_name | .hidden     |
      | data      | hidden file |
    Then the HTTP status code should be "201"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "file_name",
        "file_id"
      ],
      "properties": {
          "file_name": {"type": "string", "pattern": "^\\.hidden$"},
          "file_id": {"type" : "integer"}
      }
    }
    """
    And the content of file at "/.hidden" for user "Carol" should be "hidden file"


