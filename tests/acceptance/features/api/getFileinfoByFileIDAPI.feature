Feature: retrieve file information using a file ID

  Scenario: get information of a single file
    Given user "Alice" has been created
    And user "Alice" has uploaded file with content "some data" to "/file.txt"
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "id",
        "name",
        "mtime",
        "ctime",
        "name",
        "mimetype",
        "path",
        "owner_id",
        "owner_name",
        "trashed"
      ],
      "properties": {
          "status": {"type": "string", "minLength": 2},
          "statuscode" : {"type" : "number"},
          "id" : {"type" : "integer", "minimum": 1, "maximum": 99999},
          "size" : {"type" : "integer", "minimum": 9, "maximum": 9 },
          "mtime" : {"type" : "integer"},
          "ctime" : {"type" : "integer", "minimum": 0, "maximum": 0},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "mimetype": {"type": "string", "pattern": "^text\/plain$"},
          "path": {"type": "string", "pattern": "^\/file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "trashed": {"type": "boolean"}

      }
    }
   """
