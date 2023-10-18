Feature: retrieve information of multiple files using the file IDs

  Scenario: get information of four files, group folders, one own, one received as share, one trashed, one not accessible
    Given user "Carol" has been created
    And group "grp1" has been created
    And user "Carol" has been added to the group "grp1"
    And group folder "groupFolder" has been created
    And  group "grp1" has been added to group folder "groupFolder"
    And user "Brian" has been created with display-name "Brian Adams"
    And user "Carol" has uploaded file with content "some data" to "file.txt"
    And user "Brian" has uploaded file with content "some data" to "fromBrian.txt"
    And user "Carol" has uploaded file with content "more data" to "trashed.txt"
    And user "Brian" has uploaded file with content "some data" to "private.txt"
    And user "Carol" has uploaded file with content "some data" to "fully-deleted.txt"
    And user "Brian" has shared file "/fromBrian.txt" with user "Carol"
    And user "Carol" has deleted file "fully-deleted.txt"
    And user "Carol" has emptied the trash-bin
    And user "Carol" has deleted file "trashed.txt"
    And user "Carol" has renamed file "/fromBrian.txt" to "/renamedByCarol.txt"
    When user "Carol" gets the information of all files and group folder "groupFolder" created in this scenario
    Then the HTTP status code should be "200"
    And the ocs data of the response should match
    """"
    {
      "type": "object",
      "required": [
        "%ids[0]%",
        "%ids[1]%",
        "%ids[2]%",
        "%ids[3]%",
        "%ids[4]%",
        "%ids[5]%"
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
              "owner_id": {"type": "string", "pattern": "^Carol$"},
              "owner_name": {"type": "string", "pattern": "^Carol$"},
              "modifier_id": {"type": "null"},
              "modifier_name": {"type": "null"},
              "trashed": {"type": "boolean", "enum": [false]},
              "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
              "path": {"type": "string", "pattern":"^files\/file.txt$"}
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
              "name": {"type": "string", "pattern": "^fromBrian.txt$"},
              "mimetype": {"type": "string", "pattern": "^text\/plain$"},
              "owner_id": {"type": "string", "pattern": "^Brian$"},
              "owner_name": {"type": "string", "pattern": "^Brian Adams$"},
              "modifier_id": {"type": "null"},
              "modifier_name": {"type": "null"},
              "trashed": {"type": "boolean", "enum": [false]},
              "dav_permissions": {"type": "string", "pattern":"^SRGNVW$"},
              "path": {"type": "string", "pattern":"^files\/renamedByCarol.txt$"}
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
              "name": {"type": "string", "pattern": "^trashed.txt.d\\d{10}$"},
              "mimetype": {"type": "string", "pattern": "^text\/plain$"},
              "owner_id": {"type": "string", "pattern": "^Carol$"},
              "owner_name": {"type": "string", "pattern": "^Carol$"},
              "modifier_id": {"type": "null"},
              "modifier_name": {"type": "null"},
              "trashed": {"type": "boolean", "enum": [true]},
              "dav_permissions": {"type": "string", "pattern":"^RGDNVW$"},
              "path": {"type": "string", "pattern":"^files_trashbin\/files\/trashed.txt.d\\d{10}$"}
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
                "trashed",
                "dav_permissions",
                "path"
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
                "trashed",
                "dav_permissions",
                "path"
              ]
            },
            "properties": {
              "status": {"type": "string", "pattern": "^Not Found$"},
              "statuscode" : {"type" : "number", "enum": [404]}
            }
          },
          "%ids[5]%": {
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
              "size" : {"type" : "integer", "enum": [0] },
              "mtime" : {"type" : "integer"},
              "ctime" : {"type" : "integer", "enum": [0]},
              "name": {"type": "string", "pattern": "^groupFolder$"},
              "mimetype": {"type": "string", "pattern": "^application\/x-op-directory$"},
              "owner_id": {"type": "string", "pattern": "^Carol$"},
              "owner_name": {"type": "string", "pattern": "^Carol$"},
              "modifier_id": {"type": "null"},
              "modifier_name": {"type": "null"},
              "trashed": {"type": "boolean", "enum": [false]},
              "dav_permissions": {"type": "string", "pattern":"^RMGDNVCK"},
              "path": {"type": "string", "pattern":"^files/groupFolder/$"}
            }
          }
      }
    }
   """
