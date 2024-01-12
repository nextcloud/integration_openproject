Feature: retrieve file information of a single file, using the file ID

  Scenario: get information of an existing file
    Given user "Carol" has been created
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    When user "Carol" gets the information of last created file
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
      "dav_permissions",
      "path"
      ],
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "id" : {"type" : "integer", "minimum": 1, "maximum": 99999},
      "size" : {"type" : "integer", "enum": [9] },
      "mtime" : {"type" : "integer"},
      "ctime" : {"type" : "integer", "enum": [0]},
      "name": {"type": "string", "pattern": "^file.txt$"},
      "mimetype": {"type": "string", "pattern": "^text\/plain$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
      "path": {"type": "string", "pattern":"^files/file.txt$"}
      }
      }
      """

  Scenario: get information of an existing file in a subfolder
    Given user "Carol" has been created
    And user "Carol" has created folder "/subfolder"
    And user "Carol" has uploaded file with content "some data" to "/subfolder/file.txt"
    When user "Carol" gets the information of last created file
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
      "dav_permissions",
      "path"
      ],
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number",  "enum": [200] },
      "id" : {"type" : "integer", "minimum": 1, "maximum": 99999},
      "size" : {"type" : "integer", "enum": [9] },
      "mtime" : {"type" : "integer"},
      "ctime" : {"type" : "integer", "enum": [0]},
      "name": {"type": "string", "pattern": "^file.txt$"},
      "mimetype": {"type": "string", "pattern": "^text\/plain$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^RGDNVW"},
      "path": {"type": "string", "pattern":"^files\/subfolder\/file.txt$"}
      }
      }
      """

  Scenario: get information of a trashed file
    Given user "Carol" has been created
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    And user "Carol" has deleted file "file.txt"
    When user "Carol" gets the information of last created file
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
      "dav_permissions",
      "path",
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^Not Found$"},
      "statuscode" : {"type" : "number", "enum": [404]}
      }
      }
      """

  Scenario: get information of a file that is inside of a trashed folder
    Given user "Carol" has been created
    And user "Carol" has created folder "/subfolder"
    And user "Carol" has uploaded file with content "some data" to "/subfolder/file.txt"
    And user "Carol" has deleted folder "subfolder"
    When user "Carol" gets the information of last created file
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
      "dav_permissions",
      "path",
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^Not Found$"},
      "statuscode" : {"type" : "number", "enum": [404]}
      }
      }
      """

  Scenario: get information of a file owned by an different user
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Carol" has uploaded file with content "some data" to "file.txt"
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
      "dav_permissions",
      "path",
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
    Given user "Carol" has been created
    When user "Carol" gets the information of the file with the id "9999999999999"
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
      "dav_permissions",
      "path",
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
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Carol" has uploaded file with content "some data" to "/file.txt"
    And user "Carol" has shared file "/file.txt" with user "Brian"
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number",  "enum": [200] },
      "name": {"type": "string", "pattern": "^file.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^SRGNVW$"},
      "path": {"type": "string", "pattern":"^files/file.txt$"}
      }
      }
      """

  Scenario: get information of a file that is in a folder received as a share
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Carol" has created folder "/to-share"
    And user "Carol" has uploaded file with content "some data" to "/to-share/file.txt"
    And user "Carol" has shared folder "/to-share" with user "Brian"
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number",  "enum": [200] },
      "name": {"type": "string", "pattern": "^file.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^SRGDNVW$"},
      "path": {"type": "string", "pattern":"^files\/to-share\/file.txt$"}
      }
      }
      """

  Scenario: get information of a file that is received through a folder and a file share
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Carol" has created folder "/to-share"
    And user "Carol" has uploaded file with content "some data" to "/to-share/file.txt"
    And user "Carol" has shared folder "/to-share" with user "Brian"
    And user "Carol" has shared file "/to-share/file.txt" with user "Brian"
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number",  "enum": [200] },
      "name": {"type": "string", "pattern": "^file.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^SRGDNVW$"},
      "path": {"type": "string", "pattern":"^files\/to-share\/file.txt$"}
      }
      }
      """

  Scenario: get information of a file received as a share and renamed
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Carol" has uploaded file with content "some data" to "/file.txt"
    And user "Carol" has shared file "/file.txt" with user "Brian"
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
      "dav_permissions",
      "path"
      ],
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number",  "enum": [200] },
      "name": {"type": "string", "pattern": "^file.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^SRGNVW$"},
      "path": {"type": "string", "pattern":"^files\/renamed.txt$"}
      }
      }
      """

  Scenario: get information of a file received in a folder share and renamed
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Carol" has created folder "/to-share"
    And user "Carol" has uploaded file with content "some data" to "/to-share/file.txt"
    And user "Carol" has shared folder "/to-share" with user "Brian"
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
      "dav_permissions",
      "path"
      ],
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number",  "enum": [200] },
      "name": {"type": "string", "pattern": "^renamed.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^SRGDNVW$"},
      "path": {"type": "string", "pattern":"^files\/to-share\/renamed.txt$"}
      }
      }
      """

  Scenario: get information of a file received in a folder share and moved out of that share
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Carol" has created folder "/to-share"
    And user "Carol" has uploaded file with content "some data" to "/to-share/file.txt"
    And user "Carol" has shared folder "/to-share" with user "Brian"
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
      "not": {
      "required": [
      "trashed"
      ]
      },
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
    When user "Carol" gets the information of last created file
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
      "dav_permissions",
      "path",
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
    Given user "Carol" has been created with display-name "Carol Hansen"
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    And user "Carol" has uploaded file with content "changed data" to "file.txt"
    When user "Carol" gets the information of last created file
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [12]},
      "mtime" : {"type" : "integer"},
      "ctime" : {"type" : "integer", "enum": [0]},
      "name": {"type": "string", "pattern": "^file.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol Hansen"},
      "modifier_id": {"type": "string", "pattern": "^Carol$"},
      "modifier_name": {"type": "string", "pattern": "^Carol Hansen"},
      "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
      "path": {"type": "string", "pattern":"^files\/file.txt$"}
      }
      }
      """


  Scenario Outline: get modifier in a chain of shares
    Given user "Carol" has been created
    And user "Brian" has been created with display-name "Brian Peters"
    And user "Chandra" has been created with display-name "Chandra Thapa"
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    And user "Carol" has shared file "file.txt" with user "Brian"
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [12]},
      "mtime" : {"type" : "integer"},
      "ctime" : {"type" : "integer", "enum": [0]},
      "name": {"type": "string", "pattern": "^file.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol"},
      "modifier_id": {"type": "string", "pattern": "^<modifier>"},
      "modifier_name": {"type": "string", "pattern": "^<modifier-display-name>"},
      "dav_permissions": {"type": "string", "pattern":"^<dav_permissions>$"},
      "path": {"type": "string", "pattern":"^files\/file.txt$"}
      }
      }
      """
    Examples:
      | modifier | modifier-display-name | retriever | comment                                              | dav_permissions |
      | Carol    | Carol                 | Carol     | display-name should be username if not specially set | RGDNVW          |
      | Brian    | Brian Peters          | Carol     |                                                      | RGDNVW          |
      | Chandra  | Chandra Thapa         | Carol     |                                                      | RGDNVW          |
      | Carol    | Carol                 | Brian     |                                                      | SRGNVW          |
      | Brian    | Brian Peters          | Brian     |                                                      | SRGNVW          |
      | Chandra  | Chandra Thapa         | Brian     |                                                      | SRGNVW          |
      | Carol    | Carol                 | Chandra   |                                                      | SRGNVW          |
      | Brian    | Brian Peters          | Chandra   |                                                      | SRGNVW          |
      | Chandra  | Chandra Thapa         | Chandra   |                                                      | SRGNVW          |


  Scenario: get modifier in a chain of shares when there are multiple modifiers
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Chandra" has been created
    And user "Dipak" has been created
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    And user "Carol" has shared file "file.txt" with user "Brian"
    And user "Brian" has shared file "file.txt" with user "Chandra"
    And user "Carol" has shared file "file.txt" with user "Dipak"
    And user "Brian" has uploaded file with content "from B 0" to "file.txt"
    And user "Chandra" has uploaded file with content "from C 00" to "file.txt"
    And user "Dipak" has uploaded file with content "from D 000" to "file.txt"
    When user "Carol" gets the information of last created file
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [10]},
      "mtime" : {"type" : "integer"},
      "ctime" : {"type" : "integer", "enum": [0]},
      "name": {"type": "string", "pattern": "^file.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol$"},
      "modifier_id": {"type": "string", "pattern": "^Dipak"},
      "modifier_name": {"type": "string", "pattern": "^Dipak"},
      "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
      "path": {"type": "string", "pattern":"^files\/file.txt$"}
      }
      }
      """


  Scenario: get modifier in a chain of shares when there are multiple modifiers (sharing and modification mixed)
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Chandra" has been created
    And user "Dipak" has been created
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    And user "Carol" has shared file "file.txt" with user "Brian"
    And user "Brian" has uploaded file with content "from B 0" to "file.txt"
    And user "Brian" has shared file "file.txt" with user "Chandra"
    And user "Chandra" has uploaded file with content "from C 00" to "file.txt"
    And user "Carol" has shared file "file.txt" with user "Dipak"
    And user "Dipak" has uploaded file with content "from D 000" to "file.txt"
    When user "Carol" gets the information of last created file
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [10]},
      "mtime" : {"type" : "integer"},
      "ctime" : {"type" : "integer", "enum": [0]},
      "name": {"type": "string", "pattern": "^file.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol$"},
      "modifier_id": {"type": "string", "pattern": "^Dipak"},
      "modifier_name": {"type": "string", "pattern": "^Dipak"},
      "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
      "path": {"type": "string", "pattern":"^files\/file.txt$"}
      }
      }
      """


  Scenario: get modifier after various renaming
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Chandra" has been created
    And user "Dipak" has been created
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    And user "Carol" has shared file "file.txt" with user "Brian"
    And user "Brian" has shared file "file.txt" with user "Chandra"
    And user "Carol" has shared file "file.txt" with user "Dipak"
    And user "Dipak" has uploaded file with content "changed data" to "file.txt"
    And user "Carol" has renamed file "file.txt" to "Carols-file.txt"
    And user "Brian" has renamed file "file.txt" to "Brians-file.txt"
    And user "Chandra" has renamed file "file.txt" to "Chandras-file.txt"
    When user "Carol" gets the information of last created file
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [12]},
      "name": {"type": "string", "pattern": "^Carols-file.txt$"},
      "modifier_id": {"type": "string", "pattern": "^Dipak"},
      "modifier_name": {"type": "string", "pattern": "^Dipak"},
      "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
      "path": {"type": "string", "pattern":"^files\/Carols-file.txt$"}
      }
      }
      """


  Scenario: get modifier after various moving
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Chandra" has been created
    And user "Dipak" has been created
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    And user "Carol" has created folder "/Carol-folder"
    And user "Brian" has created folder "/Brian-folder"
    And user "Chandra" has created folder "/Chandra-folder"
    And user "Carol" has shared file "file.txt" with user "Brian"
    And user "Brian" has shared file "file.txt" with user "Chandra"
    And user "Carol" has shared file "file.txt" with user "Dipak"
    And user "Dipak" has uploaded file with content "changed data" to "file.txt"
    And user "Carol" has moved file "file.txt" to "/Carol-folder/file.txt"
    And user "Brian" has moved file "file.txt" to "/Brian-folder/file.txt"
    And user "Chandra" has moved file "file.txt" to "/Chandra-folder/file.txt"
    When user "Carol" gets the information of last created file
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [12]},
      "name": {"type": "string", "pattern": "^file.txt$"},
      "modifier_id": {"type": "string", "pattern": "^Dipak"},
      "modifier_name": {"type": "string", "pattern": "^Dipak"},
      "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
      "path": {"type": "string", "pattern":"^files\/Carol-folder\/file.txt$"}
      }
      }
      """


  Scenario: get modifier after the modifier was deleted
    Given user "Carol" has been created
    And user "Brian" has been created
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    And user "Carol" has shared file "file.txt" with user "Brian"
    And user "Brian" has uploaded file with content "changed data" to "file.txt"
    And user "Brian" has been deleted
    When user "Carol" gets the information of last created file
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
      "not": {
      "required": [
      "trashed"
      ]
      },
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
    Given user "Carol" has been created with display-name "Carol Hansen"
    And user "Brian" has been created
    And user "Carol" has created folder "/folder"
    And user "Carol" has uploaded file with content "some data" to "/folder/file.txt"
    And user "Carol" has shared folder "/folder" with user "Brian"
    And user "Brian" has uploaded file with content "changed data" to "/folder/file.txt"
    And user "Brian" has uploaded file with content "data" to "/folder/new-file.txt"
    When user "Carol" gets the information of the folder "/folder"
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
      "dav_permissions",
      "path"
      ],
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "id" : {"type" : "integer", "minimum": 1, "maximum": 99999},
      "size" : {"type" : "integer", "enum": [16] },
      "mtime" : {"type" : "integer"},
      "ctime" : {"type" : "integer", "enum": [0]},
      "name": {"type": "string", "pattern": "^folder$"},
      "mimetype": {"type": "string", "pattern": "^application\/x-op-directory$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol Hansen$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^RGDNVCK$"},
      "path": {"type": "string", "pattern":"^files\/folder\/$"}
      }
      }
      """


  Scenario: get modifier of a file changed through a public link
    Given user "Carol" has been created with display-name "Carol Hansen"
    And user "Carol" has created folder "/folder"
    And user "Carol" has uploaded file with content "some data" to "/folder/file.txt"
    And user "Carol" has shared folder "/folder" with the public
    And the public has uploaded file "file.txt" with content "changed content" to last created public link
    When user "Carol" gets the information of the file "/folder/file.txt"
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
      "dav_permissions",
      "path"
      ],
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "id" : {"type" : "integer", "minimum": 1, "maximum": 99999},
      "size" : {"type" : "integer", "enum": [15] },
      "mtime" : {"type" : "integer"},
      "ctime" : {"type" : "integer", "enum": [0]},
      "name": {"type": "string", "pattern": "^file.txt$"},
      "mimetype": {"type": "string", "pattern": "^text\/plain$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol Hansen$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
      "path": {"type": "string", "pattern":"^files\/folder\/file.txt$"}
      }
      }
      """


  Scenario: get modifier of a file changed through a public link after a real change
    Given user "Carol" has been created with display-name "Carol Hansen"
    And user "Carol" has created folder "/folder"
    And user "Carol" has uploaded file with content "some data" to "/folder/file.txt"
    And user "Carol" has uploaded file with content "change" to "/folder/file.txt"
    And user "Carol" has shared folder "/folder" with the public
    And the public has uploaded file "file.txt" with content "changed content" to last created public link
    When user "Carol" gets the information of the file "/folder/file.txt"
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [15] },
      "name": {"type": "string", "pattern": "^file.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol Hansen$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
      "path": {"type": "string", "pattern":"^files\/folder\/file.txt$"}
      }
      }
      """


  Scenario: get modifier of a file with a lot of unrelated change
    Given user "Carol" has been created with display-name "Carol Hansen"
    And user "Brian" has been created
    And user "Chandra" has been created
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    And user "Carol" has shared file "/file.txt" with user "Brian"
    And user "Brian" has uploaded file with content "change" to "file.txt"
    And user "Carol" has shared file "/file.txt" with user "Chandra"
    And user "Brian" has shared file "/file.txt" with user "Chandra"
    And user "Carol" has renamed file "/file.txt" to "/file1.txt"
    And user "Carol" has renamed file "/file1.txt" to "/file2.txt"
    And user "Carol" has renamed file "/file2.txt" to "/file3.txt"
    And user "Carol" has renamed file "/file3.txt" to "/file4.txt"
    And user "Carol" has renamed file "/file4.txt" to "/file5.txt"
    And user "Carol" has renamed file "/file5.txt" to "/file6.txt"
    And user "Carol" has renamed file "/file6.txt" to "/file7.txt"
    And user "Carol" has renamed file "/file7.txt" to "/file8.txt"
    And user "Carol" has renamed file "/file8.txt" to "/file9.txt"
    And user "Carol" has renamed file "/file9.txt" to "/file10.txt"
    And user "Carol" has renamed file "/file10.txt" to "/file11.txt"
    And user "Carol" has renamed file "/file11.txt" to "/file12.txt"
    And user "Carol" has renamed file "/file12.txt" to "/file13.txt"
    And user "Carol" has renamed file "/file13.txt" to "/file14.txt"
    And user "Carol" has renamed file "/file14.txt" to "/file15.txt"
    And user "Carol" has renamed file "/file15.txt" to "/file16.txt"
    And user "Carol" has renamed file "/file16.txt" to "/file17.txt"
    And user "Carol" has renamed file "/file17.txt" to "/file18.txt"
    And user "Carol" has renamed file "/file18.txt" to "/file19.txt"
    And user "Carol" has renamed file "/file19.txt" to "/file20.txt"
    When user "Carol" gets the information of last created file
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [6] },
      "name": {"type": "string", "pattern": "^file20.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol Hansen$"},
      "modifier_id": {"type": "string", "pattern": "^Brian"},
      "modifier_name": {"type": "string", "pattern": "^Brian$"},
      "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
      "path": {"type": "string", "pattern":"^files\/file20.txt$"}
      }
      }
      """

  Scenario Outline: get file info of a file shared with different permissions
    Given user "Carol" has been created with display-name "Carol Hansen"
    And user "Brian" has been created
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    And user "Carol" has shared file "/file.txt" with user "Brian" with "<share-permission>" permissions
    When user "Brian" gets the information of the file "/file.txt"
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [9] },
      "name": {"type": "string", "pattern": "^file.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol Hansen$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^<requester-dav-permissions>$"},
      "path": {"type": "string", "pattern":"^files\/file.txt$"}
      }
      }
      """
    When user "Carol" gets the information of the file "/file.txt"
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [9] },
      "name": {"type": "string", "pattern": "^file.txt$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol Hansen$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^<owner-dav-permissions>$"},
      "path": {"type": "string", "pattern":"^files\/file.txt$"}
      }
      }
      """
    Examples:
      | share-permission   | requester-dav-permissions | owner-dav-permissions |
      | all                | SRGNVW                    | RGDNVW                |
      | read               | SG                        | RGDNVW                |
      | read+share         | SRG                       | RGDNVW                |
      | read+update        | SGNVW                     | RGDNVW                |
      | read+update+share  | SRGNVW                    | RGDNVW                |
      | read+delete        | SG                        | RGDNVW                |
      | read+delete+update | SGNVW                     | RGDNVW                |


  Scenario Outline: get file info of a folder shared with different permissions
    Given user "Carol" has been created with display-name "Carol Hansen"
    And user "Brian" has been created
    And user "Carol" has created folder "/folder"
    And user "Carol" has uploaded file with content "some data" to "/folder/file.txt"
    And user "Carol" has shared folder "/folder" with user "Brian" with "<share-permission>" permissions
    When user "Brian" gets the information of the folder "/folder"
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [9] },
      "name": {"type": "string", "pattern": "^folder$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol Hansen$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^<requester-dav-permissions>$"},
      "path": {"type": "string", "pattern":"^files\/folder\/$"}
      }
      }
      """
    When user "Carol" gets the information of the file "/folder"
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [9] },
      "name": {"type": "string", "pattern": "^folder$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol Hansen$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^<owner-dav-permissions>$"},
      "path": {"type": "string", "pattern":"^files\/folder\/$"}
      }
      }
      """
    Examples:
      | share-permission          | requester-dav-permissions | owner-dav-permissions |
      | all                       | SRGDNVCK                  | RGDNVCK               |
      | read                      | SG                        | RGDNVCK               |
      | read+share                | SRG                       | RGDNVCK               |
      | read+update               | SGNV                      | RGDNVCK               |
      | read+update+share         | SRGNV                     | RGDNVCK               |
      | read+delete               | SGD                       | RGDNVCK               |
      | read+delete+update        | SGDNV                     | RGDNVCK               |
      | read+create               | SGCK                      | RGDNVCK               |
      | read+create+update        | SGNVCK                    | RGDNVCK               |
      | read+create+share         | SRGCK                     | RGDNVCK               |
      | read+create+share+delete  | SRGDCK                    | RGDNVCK               |
      | read+create+update+delete | SGDNVCK                   | RGDNVCK               |
      | read+create+update+share  | SRGNVCK                   | RGDNVCK               |


  Scenario: get information of a group folder
    Given user "Carol" has been created
    And group "grp1" has been created
    And user "Carol" has been added to the group "grp1"
    And group folder "groupFolder" has been created
    And  group "grp1" has been added to group folder "groupFolder"
    When user "Carol" gets the information of the folder "/groupFolder"
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
      "not": {
      "required": [
      "trashed"
      ]
      },
      "properties": {
      "status": {"type": "string", "pattern": "^OK$"},
      "statuscode" : {"type" : "number", "enum": [200]},
      "size" : {"type" : "integer", "enum": [0] },
      "name": {"type": "string", "pattern": "^groupFolder$"},
      "owner_id": {"type": "string", "pattern": "^Carol$"},
      "owner_name": {"type": "string", "pattern": "^Carol$"},
      "modifier_id": {"type": "null"},
      "modifier_name": {"type": "null"},
      "dav_permissions": {"type": "string", "pattern":"^RMGDNVCK$"},
      "path": {"type": "string", "pattern":"^files\/groupFolder\/$"}
      }
      }
      """
