Feature: retrieve file information of a single file, using the file ID

  Scenario: get information of an existing file
    Given user "Alice" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
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
    }
   """

  Scenario: get information of an existing file in a subfolder
    Given user "Alice" has been created
    And user "Alice" has created folder "/subfolder"
    And user "Alice" has uploaded file with content "some data" to "/subfolder/file.txt"
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
          "statuscode" : {"type" : "number",  "enum": [200] },
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
    }
   """

  Scenario: get information of a trashed file
    Given user "Alice" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has deleted file "file.txt"
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
          "name": {"type": "string", "pattern": "^file.txt.d\\d{10}$"},
          "mimetype": {"type": "string", "pattern": "^text\/plain$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": null,
          "modifier_name": null,
          "trashed": {"type": "boolean", "enum": [true]}
      }
    }
   """

  Scenario: get information of a file that is inside of a trashed folder
    Given user "Alice" has been created
    And user "Alice" has created folder "/subfolder"
    And user "Alice" has uploaded file with content "some data" to "/subfolder/file.txt"
    And user "Alice" has deleted folder "subfolder"
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
          "trashed": {"type": "boolean", "enum": [true]}
      }
    }
   """

  Scenario: get information of a file owned by an different user
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    When user "Brian" gets the information of last created file
    Then the HTTP status code should be "403"
    And the data of the response should match
    """"
    {
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
    }
   """

  Scenario: get information of a non-existing file
    Given user "Alice" has been created
    When user "Brian" gets the information of the file with the id "9999999999999"
    Then the HTTP status code should be "404"
    And the data of the response should match
    """"
    {
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
   """

  Scenario: get information of a file received as a share
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Alice" has uploaded file with content "some data" to "/file.txt"
    And user "Alice" has shared file "/file.txt" with user "Brian"
    When user "Brian" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "name",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name",
        "trashed"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": null,
          "modifier_name": null,
          "trashed": {"type": "boolean", "enum": [false]}
      }
    }
   """

  Scenario: get information of a file that is in a folder received as a share
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Alice" has created folder "/to-share"
    And user "Alice" has uploaded file with content "some data" to "/to-share/file.txt"
    And user "Alice" has shared folder "/to-share" with user "Brian"
    When user "Brian" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "name",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name",
        "trashed"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": null,
          "modifier_name": null,
          "trashed": {"type": "boolean", "enum": [false]}
      }
    }
   """

  Scenario: get information of a file that is received through a folder and a file share
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Alice" has created folder "/to-share"
    And user "Alice" has uploaded file with content "some data" to "/to-share/file.txt"
    And user "Alice" has shared folder "/to-share" with user "Brian"
    And user "Alice" has shared file "/to-share/file.txt" with user "Brian"
    When user "Brian" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "name",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name",
        "trashed"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": null,
          "modifier_name": null,
          "trashed": {"type": "boolean", "enum": [false]}
      }
    }
   """

  Scenario: get information of a file received as a share and renamed
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Alice" has uploaded file with content "some data" to "/file.txt"
    And user "Alice" has shared file "/file.txt" with user "Brian"
    And user "Brian" has renamed file "/file.txt" to "/renamed.txt"
    When user "Brian" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "name",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name",
        "trashed"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": null,
          "modifier_name": null,
          "trashed": {"type": "boolean", "enum": [false]}
      }
    }
   """

  Scenario: get information of a file received in a folder share and renamed
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Alice" has created folder "/to-share"
    And user "Alice" has uploaded file with content "some data" to "/to-share/file.txt"
    And user "Alice" has shared folder "/to-share" with user "Brian"
    And user "Brian" has renamed file "/to-share/file.txt" to "/to-share/renamed.txt"
    When user "Brian" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "name",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name",
        "trashed"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^renamed.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": null,
          "modifier_name": null,
          "trashed": {"type": "boolean", "enum": [false]}
      }
    }
   """

  Scenario: get information of a file received in a folder share and moved out of that share
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Alice" has created folder "/to-share"
    And user "Alice" has uploaded file with content "some data" to "/to-share/file.txt"
    And user "Alice" has shared folder "/to-share" with user "Brian"
    And user "Brian" has renamed file "/to-share/file.txt" to "/moved-out.txt"
    When user "Brian" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "name",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^moved-out.txt$"},
          "owner_id": {"type": "string", "pattern": "^Brian$"},
          "owner_name": {"type": "string", "pattern": "^Brian$"},
          "modifier_id": null,
          "modifier_name": null
      }
    }
   """
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "403"
    And the data of the response should match
    """"
    {
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
    }
   """

  Scenario: get modifier when same user uploads and overwrites a file
    Given user "Alice" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has uploaded file with content "changed data" to "file.txt"
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "mtime",
        "ctime",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [12]},
          "mtime" : {"type" : "integer"},
          "ctime" : {"type" : "integer", "enum": [0]},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": {"type": "string", "pattern": "^Alice$"},
          "modifier_name": {"type": "string", "pattern": "^Alice$"}
      }
    }
   """


  Scenario Outline: get modifier in a chain of shares
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Chandra" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has shared file "file.txt" with user "Brian"
    And user "Brian" has shared file "file.txt" with user "Chandra"
    And user "<modifier>" has uploaded file with content "changed data" to "file.txt"
    When user "<retriever>" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "mtime",
        "ctime",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [12]},
          "mtime" : {"type" : "integer"},
          "ctime" : {"type" : "integer", "enum": [0]},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": {"type": "string", "pattern": "^<modifier>"},
          "modifier_name": {"type": "string", "pattern": "^<modifier>"}
      }
    }
   """
    Examples:
      | modifier | retriever |
      | Alice    | Alice     |
      | Brian    | Alice     |
      | Chandra  | Alice     |
      | Alice    | Brian     |
      | Brian    | Brian     |
      | Chandra  | Brian     |
      | Alice    | Chandra   |
      | Brian    | Chandra   |
      | Chandra  | Chandra   |


  Scenario: get modifier in a chain of shares when there are multiple modifiers
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Chandra" has been created
    And user "Dipak" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has shared file "file.txt" with user "Brian"
    And user "Brian" has shared file "file.txt" with user "Chandra"
    And user "Alice" has shared file "file.txt" with user "Dipak"
    And user "Brian" has uploaded file with content "from B 0" to "file.txt"
    And user "Chandra" has uploaded file with content "from C 00" to "file.txt"
    And user "Dipak" has uploaded file with content "from D 000" to "file.txt"
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "mtime",
        "ctime",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [10]},
          "mtime" : {"type" : "integer"},
          "ctime" : {"type" : "integer", "enum": [0]},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": {"type": "string", "pattern": "^Dipak"},
          "modifier_name": {"type": "string", "pattern": "^Dipak"}
      }
    }
   """


  Scenario: get modifier in a chain of shares when there are multiple modifiers (sharing and modification mixed)
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Chandra" has been created
    And user "Dipak" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has shared file "file.txt" with user "Brian"
    And user "Brian" has uploaded file with content "from B 0" to "file.txt"
    And user "Brian" has shared file "file.txt" with user "Chandra"
    And user "Chandra" has uploaded file with content "from C 00" to "file.txt"
    And user "Alice" has shared file "file.txt" with user "Dipak"
    And user "Dipak" has uploaded file with content "from D 000" to "file.txt"
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "mtime",
        "ctime",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [10]},
          "mtime" : {"type" : "integer"},
          "ctime" : {"type" : "integer", "enum": [0]},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": {"type": "string", "pattern": "^Dipak"},
          "modifier_name": {"type": "string", "pattern": "^Dipak"}
      }
    }
   """


  Scenario: get modifier after various renaming
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Chandra" has been created
    And user "Dipak" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has shared file "file.txt" with user "Brian"
    And user "Brian" has shared file "file.txt" with user "Chandra"
    And user "Alice" has shared file "file.txt" with user "Dipak"
    And user "Dipak" has uploaded file with content "changed data" to "file.txt"
    And user "Alice" has renamed file "file.txt" to "Alices-file.txt"
    And user "Brian" has renamed file "file.txt" to "Brians-file.txt"
    And user "Chandra" has renamed file "file.txt" to "Chandras-file.txt"
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "modifier_id",
        "modifier_name"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [12]},
          "name": {"type": "string", "pattern": "^Alices-file.txt$"},
          "modifier_id": {"type": "string", "pattern": "^Dipak"},
          "modifier_name": {"type": "string", "pattern": "^Dipak"}
      }
    }
   """


  Scenario: get modifier after various moving
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Chandra" has been created
    And user "Dipak" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has created folder "/Alice-folder"
    And user "Brian" has created folder "/Brian-folder"
    And user "Chandra" has created folder "/Chandra-folder"
    And user "Alice" has shared file "file.txt" with user "Brian"
    And user "Brian" has shared file "file.txt" with user "Chandra"
    And user "Alice" has shared file "file.txt" with user "Dipak"
    And user "Dipak" has uploaded file with content "changed data" to "file.txt"
    And user "Alice" has moved file "file.txt" to "/Alice-folder/file.txt"
    And user "Brian" has moved file "file.txt" to "/Brian-folder/file.txt"
    And user "Chandra" has moved file "file.txt" to "/Chandra-folder/file.txt"
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "modifier_id",
        "modifier_name"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [12]},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "modifier_id": {"type": "string", "pattern": "^Dipak"},
          "modifier_name": {"type": "string", "pattern": "^Dipak"}
      }
    }
   """


  Scenario: get modifier after the modifier was deleted
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has shared file "file.txt" with user "Brian"
    And user "Brian" has uploaded file with content "changed data" to "file.txt"
    And user "Brian" has been deleted
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "modifier_id",
        "modifier_name"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [12]},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "modifier_id": {"type": "string", "pattern": "^Brian"},
          "modifier_name": {"type": "string", "pattern": "^Brian"}
      }
    }
   """
