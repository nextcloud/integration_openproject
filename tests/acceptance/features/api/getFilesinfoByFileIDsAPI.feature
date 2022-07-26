Feature: retrieve information of multiple files using the file IDs

  Scenario: get information of four files, one own, one received as share, one trashed, one not accessible
    Given user "Alice" has been created
    And user "Brian" has been created with display-name "Brian Adams"
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Brian" has uploaded file with content "some data" to "fromBrian.txt"
    And user "Alice" has uploaded file with content "more data" to "trashed.txt"
    And user "Brian" has uploaded file with content "some data" to "private.txt"
    And user "Alice" has uploaded file with content "some data" to "fully-deleted.txt"
    And user "Brian" has shared file "/fromBrian.txt" with user "Alice"
    And user "Alice" has deleted file "fully-deleted.txt"
    And user "Alice" has emptied the trash-bin
    And user "Alice" has deleted file "trashed.txt"
    And user "Alice" has renamed file "/fromBrian.txt" to "/renamedByAlice.txt"
    When user "Alice" gets the information of all files created in this scenario
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
      "type": "object",
      "required": [
        "%ids[0]%",
        "%ids[1]%",
        "%ids[2]%",
        "%ids[3]%",
        "%ids[4]%"
      ],
      "properties": {
          "%ids[0]%": {
            "type": "object",
            "required": [
              "status",
              "statuscode",
              "id",
              "size",
              "name",
              "mtime",
              "ctime",
              "mimetype",
              "owner_id",
              "owner_name",
              "modifier_id",
              "modifier_name",
              "trashed"
            ],
            "properties": {
              "status": {"type": "string", "pattern": "^OK$"},
              "statuscode" : {"type" : "number", "enum": [200]},
              "id" : {"type" : "integer", "minimum": 1, "maximum": 99999},
              "size" : {"type" : "integer", "enum": [9] },
              "mtime" : {"type" : "integer"},
              "ctime" : {"type" : "integer", "enum": [0]},
              "name": {"type": "string", "pattern": "^file.txt$"},
              "mimetype": {"type": "string", "pattern": "^text\/plain$"},
              "owner_id": {"type": "string", "pattern": "^Alice$"},
              "owner_name": {"type": "string", "pattern": "^Alice$"},
              "modifier_id": null,
              "modifier_name": null,
              "trashed": {"type": "boolean", "enum": [false]}
            }
          },
          "%ids[1]%": {
            "type": "object",
            "required": [
              "status",
              "statuscode",
              "id",
              "size",
              "name",
              "mtime",
              "ctime",
              "mimetype",
              "owner_id",
              "owner_name",
              "modifier_id",
              "modifier_name",
              "trashed"
            ],
            "properties": {
              "status": {"type": "string", "pattern": "^OK$"},
              "statuscode" : {"type" : "number", "enum": [200]},
              "id" : {"type" : "integer", "minimum": 1, "maximum": 99999},
              "size" : {"type" : "integer", "enum": [9] },
              "mtime" : {"type" : "integer"},
              "ctime" : {"type" : "integer", "enum": [0]},
              "name": {"type": "string", "pattern": "^fromBrian.txt$"},
              "mimetype": {"type": "string", "pattern": "^text\/plain$"},
              "owner_id": {"type": "string", "pattern": "^Brian$"},
              "owner_name": {"type": "string", "pattern": "^Brian Adams$"},
              "modifier_id": null,
              "modifier_name": null,
              "trashed": {"type": "boolean", "enum": [false]}
            }
          },
          "%ids[2]%": {
            "type": "object",
            "required": [
              "status",
              "statuscode",
              "id",
              "size",
              "name",
              "mtime",
              "ctime",
              "mimetype",
              "owner_id",
              "owner_name",
              "modifier_id",
              "modifier_name",
              "trashed"
            ],
            "properties": {
              "status": {"type": "string", "pattern": "^OK$"},
              "statuscode" : {"type" : "number", "enum": [200]},
              "id" : {"type" : "integer", "minimum": 1, "maximum": 99999},
              "size" : {"type" : "integer", "enum": [9] },
              "mtime" : {"type" : "integer"},
              "ctime" : {"type" : "integer", "enum": [0]},
              "name": {"type": "string", "pattern": "^trashed.txt.d\\d{10}$"},
              "mimetype": {"type": "string", "pattern": "^text\/plain$"},
              "owner_id": {"type": "string", "pattern": "^Alice$"},
              "owner_name": {"type": "string", "pattern": "^Alice$"},
              "modifier_id": null,
              "modifier_name": null,
              "trashed": {"type": "boolean", "enum": [true]}
            }
          },
          "%ids[3]%": {
            "type": "object",
            "required": [
              "status",
              "statuscode"
            ],
            "not": {
             "required": [
                "id",
                "size",
                "name",
                "mtime",
                "ctime",
                "mimetype",
                "owner_id",
                "owner_name",
                "modifier_id",
                "modifier_name",
                "trashed"
              ]
            },
            "properties": {
              "status": {"type": "string", "pattern": "^Forbidden$"},
              "statuscode" : {"type" : "number", "enum": [403]}
            }
          },
          "%ids[4]%": {
            "type": "object",
            "required": [
              "status",
              "statuscode"
            ],
            "not": {
             "required": [
                "id",
                "size",
                "name",
                "mtime",
                "ctime",
                "mimetype",
                "owner_id",
                "owner_name",
                "modifier_id",
                "modifier_name",
                "trashed"
              ]
            },
            "properties": {
              "status": {"type": "string", "pattern": "^Not Found$"},
              "statuscode" : {"type" : "number", "enum": [404]}
            }
          }
        }
    }
   """
