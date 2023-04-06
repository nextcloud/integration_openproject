Feature: retrieve file information of a single file, using the file ID

  Scenario: get information of an existing file
    Given user "Alice" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the ocs data of the response should match
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
        "trashed",
        "dav_permissions",
        "path"
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
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "trashed": {"type": "boolean", "enum": [false]},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files/file.txt$"}
      }
    }
   """

  Scenario: get information of an existing file in a subfolder
    Given user "Alice" has been created
    And user "Alice" has created folder "/subfolder"
    And user "Alice" has uploaded file with content "some data" to "/subfolder/file.txt"
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the ocs data of the response should match
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
        "trashed",
        "dav_permissions",
        "path"
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
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "trashed": {"type": "boolean", "enum": [false]},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW"},
          "path": {"type": "string", "pattern":"^files\/subfolder\/file.txt$"}
      }
    }
   """

  Scenario: get information of a trashed file
    Given user "Alice" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has deleted file "file.txt"
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the ocs data of the response should match
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
        "trashed",
        "dav_permissions",
        "path"
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
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "trashed": {"type": "boolean", "enum": [true]},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW"},
          "path": {"type": "string", "pattern":"^files_trashbin\/files\/file.txt.d\\d{10}$"}
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
    And the ocs data of the response should match
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
        "trashed",
        "dav_permissions",
        "path"
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
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "trashed": {"type": "boolean", "enum": [true]},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files_trashbin\/files\/subfolder.d\\d{10}\/file.txt"}
      }
    }
   """

  Scenario: get information of a file owned by an different user
    Given user "Alice" has been created
    And user "Brian" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    When user "Brian" gets the information of last created file
    Then the HTTP status code should be "403"
    And the ocs data of the response should match
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
          "trashed",
          "dav_permissions",
          "path"
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
    And the ocs data of the response should match
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
          "trashed",
          "dav_permissions",
          "path"
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
    And the ocs data of the response should match
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
        "trashed",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "trashed": {"type": "boolean", "enum": [false]},
          "dav_permissions": {"type": "string", "pattern":"^SRGNVW$"},
          "path": {"type": "string", "pattern":"^files/file.txt$"}
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
    And the ocs data of the response should match
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
        "trashed",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "trashed": {"type": "boolean", "enum": [false]},
          "dav_permissions": {"type": "string", "pattern":"^SRGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/to-share\/file.txt$"}
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
    And the ocs data of the response should match
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
        "trashed",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "trashed": {"type": "boolean", "enum": [false]},
          "dav_permissions": {"type": "string", "pattern":"^SRGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/to-share\/file.txt$"}
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
    And the ocs data of the response should match
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
        "trashed",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "trashed": {"type": "boolean", "enum": [false]},
          "dav_permissions": {"type": "string", "pattern":"^SRGNVW$"},
          "path": {"type": "string", "pattern":"^files\/renamed.txt$"}
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
    And the ocs data of the response should match
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
        "trashed",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^renamed.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice$"},
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "trashed": {"type": "boolean", "enum": [false]},
          "dav_permissions": {"type": "string", "pattern":"^SRGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/to-share\/renamed.txt$"}
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
    And the ocs data of the response should match
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
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number",  "enum": [200] },
          "name": {"type": "string", "pattern": "^moved-out.txt$"},
          "owner_id": {"type": "string", "pattern": "^Brian$"},
          "owner_name": {"type": "string", "pattern": "^Brian$"},
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/moved-out.txt$"}
      }
    }
   """
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "403"
    And the ocs data of the response should match
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
          "trashed",
          "dav_permissions",
          "path"
        ]
      },
      "properties": {
          "status": {"type": "string", "pattern": "^Forbidden$"},
          "statuscode" : {"type" : "number", "enum": [403]}
      }
    }
   """

  Scenario: get modifier when same user uploads and overwrites a file
    Given user "Alice" has been created with display-name "Alice Hansen"
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has uploaded file with content "changed data" to "file.txt"
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the ocs data of the response should match
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
        "modifier_name",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [12]},
          "mtime" : {"type" : "integer"},
          "ctime" : {"type" : "integer", "enum": [0]},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice Hansen"},
          "modifier_id": {"type": "string", "pattern": "^Alice$"},
          "modifier_name": {"type": "string", "pattern": "^Alice Hansen"},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/file.txt$"}
      }
    }
   """


  Scenario Outline: get modifier in a chain of shares
    Given user "Alice" has been created
    And user "Brian" has been created with display-name "Brian Peters"
    And user "Chandra" has been created with display-name "Chandra Thapa"
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has shared file "file.txt" with user "Brian"
    And user "Brian" has shared file "file.txt" with user "Chandra"
    And user "<modifier>" has uploaded file with content "changed data" to "file.txt"
    When user "<retriever>" gets the information of last created file
    Then the HTTP status code should be "200"
    And the ocs data of the response should match
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
        "modifier_name",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [12]},
          "mtime" : {"type" : "integer"},
          "ctime" : {"type" : "integer", "enum": [0]},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice"},
          "modifier_id": {"type": "string", "pattern": "^<modifier>"},
          "modifier_name": {"type": "string", "pattern": "^<modifier-display-name>"},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/file.txt$"}
      }
    }
   """
    Examples:
      | modifier | modifier-display-name | retriever | comment                                              |
      | Alice    | Alice                 | Alice     | display-name should be username if not specially set |
      | Brian    | Brian Peters          | Alice     |                                                      |
      | Chandra  | Chandra Thapa         | Alice     |                                                      |
      | Alice    | Alice                 | Brian     |                                                      |
      | Brian    | Brian Peters          | Brian     |                                                      |
      | Chandra  | Chandra Thapa         | Brian     |                                                      |
      | Alice    | Alice                 | Chandra   |                                                      |
      | Brian    | Brian Peters          | Chandra   |                                                      |
      | Chandra  | Chandra Thapa         | Chandra   |                                                      |


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
    And the ocs data of the response should match
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
        "modifier_name",
        "dav_permissions",
        "path"
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
          "modifier_name": {"type": "string", "pattern": "^Dipak"},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/file.txt$"}
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
    And the ocs data of the response should match
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
        "modifier_name",
        "dav_permissions",
        "path"
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
          "modifier_name": {"type": "string", "pattern": "^Dipak"},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/file.txt$"}
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
    And the ocs data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "modifier_id",
        "modifier_name",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [12]},
          "name": {"type": "string", "pattern": "^Alices-file.txt$"},
          "modifier_id": {"type": "string", "pattern": "^Dipak"},
          "modifier_name": {"type": "string", "pattern": "^Dipak"},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/Alices-file.txt$"}
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
    And the ocs data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "modifier_id",
        "modifier_name",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [12]},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "modifier_id": {"type": "string", "pattern": "^Dipak"},
          "modifier_name": {"type": "string", "pattern": "^Dipak"},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/Alice-folder\/file.txt$"}
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
    And the ocs data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "modifier_id",
        "modifier_name",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [12]},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/file.txt$"}
      }
    }
   """


  Scenario: get modifier of a folder
    Given user "Alice" has been created with display-name "Alice Hansen"
    And user "Brian" has been created
    And user "Alice" has created folder "/folder"
    And user "Alice" has uploaded file with content "some data" to "/folder/file.txt"
    And user "Alice" has shared folder "/folder" with user "Brian"
    And user "Brian" has uploaded file with content "changed data" to "/folder/file.txt"
    And user "Brian" has uploaded file with content "data" to "/folder/new-file.txt"
    When user "Alice" gets the information of the folder "/folder"
    Then the HTTP status code should be "200"
    And the ocs data of the response should match
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
        "trashed",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "id" : {"type" : "integer", "minimum": 1, "maximum": 99999},
          "size" : {"type" : "integer", "enum": [16] },
          "mtime" : {"type" : "integer"},
          "ctime" : {"type" : "integer", "enum": [0]},
          "name": {"type": "string", "pattern": "^folder$"},
          "mimetype": {"type": "string", "pattern": "^application\/x-op-directory$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice Hansen$"},
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "trashed": {"type": "boolean", "enum": [false]},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/folder$"}
      }
    }
   """


  Scenario: get modifier of a file changed through a public link
    Given user "Alice" has been created with display-name "Alice Hansen"
    And user "Alice" has created folder "/folder"
    And user "Alice" has uploaded file with content "some data" to "/folder/file.txt"
    And user "Alice" has shared folder "/folder" with the public
    And the public has uploaded file "file.txt" with content "changed content" to last created public link
    When user "Alice" gets the information of the file "/folder/file.txt"
    Then the HTTP status code should be "200"
    And the ocs data of the response should match
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
        "trashed",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "id" : {"type" : "integer", "minimum": 1, "maximum": 99999},
          "size" : {"type" : "integer", "enum": [15] },
          "mtime" : {"type" : "integer"},
          "ctime" : {"type" : "integer", "enum": [0]},
          "name": {"type": "string", "pattern": "^file.txt$"},
          "mimetype": {"type": "string", "pattern": "^text\/plain$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice Hansen$"},
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "trashed": {"type": "boolean", "enum": [false]},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/folder\/file.txt$"}
      }
    }
   """


  Scenario: get modifier of a file changed through a public link after a real change
    Given user "Alice" has been created with display-name "Alice Hansen"
    And user "Alice" has created folder "/folder"
    And user "Alice" has uploaded file with content "some data" to "/folder/file.txt"
    And user "Alice" has uploaded file with content "change" to "/folder/file.txt"
    And user "Alice" has shared folder "/folder" with the public
    And the public has uploaded file "file.txt" with content "changed content" to last created public link
    When user "Alice" gets the information of the file "/folder/file.txt"
    Then the HTTP status code should be "200"
    And the ocs data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [15] },
          "name": {"type": "string", "pattern": "^file.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice Hansen$"},
          "modifier_id": {"type": "null"},
          "modifier_name": {"type": "null"},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/folder\/file.txt$"}
      }
    }
   """


  Scenario: get modifier of a file with a lot of unrelated change
    Given user "Alice" has been created with display-name "Alice Hansen"
    And user "Brian" has been created
    And user "Chandra" has been created
    And user "Alice" has uploaded file with content "some data" to "file.txt"
    And user "Alice" has shared file "/file.txt" with user "Brian"
    And user "Brian" has uploaded file with content "change" to "file.txt"
    And user "Alice" has shared file "/file.txt" with user "Chandra"
    And user "Brian" has shared file "/file.txt" with user "Chandra"
    And user "Alice" has renamed file "/file.txt" to "/file1.txt"
    And user "Alice" has renamed file "/file1.txt" to "/file2.txt"
    And user "Alice" has renamed file "/file2.txt" to "/file3.txt"
    And user "Alice" has renamed file "/file3.txt" to "/file4.txt"
    And user "Alice" has renamed file "/file4.txt" to "/file5.txt"
    And user "Alice" has renamed file "/file5.txt" to "/file6.txt"
    And user "Alice" has renamed file "/file6.txt" to "/file7.txt"
    And user "Alice" has renamed file "/file7.txt" to "/file8.txt"
    And user "Alice" has renamed file "/file8.txt" to "/file9.txt"
    And user "Alice" has renamed file "/file9.txt" to "/file10.txt"
    And user "Alice" has renamed file "/file10.txt" to "/file11.txt"
    And user "Alice" has renamed file "/file11.txt" to "/file12.txt"
    And user "Alice" has renamed file "/file12.txt" to "/file13.txt"
    And user "Alice" has renamed file "/file13.txt" to "/file14.txt"
    And user "Alice" has renamed file "/file14.txt" to "/file15.txt"
    And user "Alice" has renamed file "/file15.txt" to "/file16.txt"
    And user "Alice" has renamed file "/file16.txt" to "/file17.txt"
    And user "Alice" has renamed file "/file17.txt" to "/file18.txt"
    And user "Alice" has renamed file "/file18.txt" to "/file19.txt"
    And user "Alice" has renamed file "/file19.txt" to "/file20.txt"
    When user "Alice" gets the information of last created file
    Then the HTTP status code should be "200"
    And the ocs data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status",
        "statuscode",
        "size",
        "name",
        "owner_id",
        "owner_name",
        "modifier_id",
        "modifier_name",
        "dav_permissions",
        "path"
      ],
      "properties": {
          "status": {"type": "string", "pattern": "^OK$"},
          "statuscode" : {"type" : "number", "enum": [200]},
          "size" : {"type" : "integer", "enum": [6] },
          "name": {"type": "string", "pattern": "^file20.txt$"},
          "owner_id": {"type": "string", "pattern": "^Alice$"},
          "owner_name": {"type": "string", "pattern": "^Alice Hansen$"},
          "modifier_id": {"type": "string", "pattern": "^Brian"},
          "modifier_name": {"type": "string", "pattern": "^Brian$"},
          "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
          "path": {"type": "string", "pattern":"^files\/file20.txt$"}
      }
    }
   """
