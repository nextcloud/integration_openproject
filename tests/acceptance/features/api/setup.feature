Feature: setup the integration through an API

  Scenario: valid setup without group folder
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
      "values" : {
      "openproject_instance_url": "http://some-host.de",
        "openproject_client_id": "the-client-id",
        "openproject_client_secret": "the-client-secret",
        "default_enable_navigation": false,
        "default_enable_unified_search": false,
        "setup_project_folder": false,
        "setup_app_password": false
        }
      }
      """
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "nextcloud_oauth_client_name",
        "openproject_redirect_uri",
        "nextcloud_client_id",
        "nextcloud_client_secret"
      ],
      "properties": {
          "nextcloud_oauth_client_name": {"type": "string", "pattern": "^OpenProject client$"},
          "openproject_redirect_uri": {"type": "string", "pattern": "^http:\/\/some-host.de\/oauth_clients\/[A-Za-z0-9]+\/callback$"},
          "nextcloud_client_id": {"type": "string", "pattern": "[A-Za-z0-9]+"},
          "nextcloud_client_secret": {"type": "string", "pattern": "[A-Za-z0-9]+"}
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """

  Scenario Outline: setup with invalid data
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "openproject_instance_url": <instance_url>,
          "openproject_client_id": <openproject_client_id>,
          "openproject_client_secret": <openproject_client_secret>,
          "default_enable_navigation": <enable_navigation>,
          "default_enable_unified_search": <enable_unified_search>,
          "setup_project_folder": <setup_project_folder>,
          "setup_app_password": <setup_app_password>
        }
      }
      """
    Then the HTTP status code should be "400"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^invalid data$"}
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """
    Examples:
      | instance_url          | openproject_client_id | openproject_client_secret | enable_navigation | enable_unified_search | setup_project_folder | setup_app_password |
      | null                  | null                  | null                      | null              | null                  | null               | null               |
      | null                  | "id"                  | "secret"                  | false             | false                 | false              | false              |
      | "http://some-host.de" | null                  | "secret"                  | false             | false                 | false              | false              |
      | "http://some-host.de" | "id"                  | null                      | false             | false                 | false              | false              |
      | "http://some-host.de" | "id"                  | "secret"                  | null              | false                 | false              | false              |
      | "http://some-host.de" | "id"                  | "secret"                  | true              | null                  | ""                 | ""                 |
      | ""                    | ""                    | ""                        | ""                | ""                    | false              | false              |
      | ""                    | "id"                  | "secret"                  | false             | false                 | false              | false              |
      | "http://some-host.de" | ""                    | "secret"                  | false             | false                 | false              | false              |
      | "http://some-host.de" | "id"                  | ""                        | false             | false                 | false              | false              |
      | "http://some-host.de" | "id"                  | "secret"                  | ""                | false                 | false              | false              |
      | "http://some-host.de" | "id"                  | "secret"                  | true              | ""                    | false              | false              |
      | "ftp://somehost.de"   | "the-id"              | "secret"                  | true              | false                 | "a string"         | "a string"         |
      | "http://somehost.de"  | false                 | "secret"                  | true              | false                 | false              | false              |
      | "http://somehost.de"  | "id"                  | false                     | true              | false                 | false              | false              |
      | "http://somehost.de"  | "the-id"              | "secret"                  | "a string"        | false                 | false              | false              |
      | "http://somehost.de"  | "the-id"              | "secret"                  | false             | "a string"            | false              | false              |


  Scenario: setup with invalid keys
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "instance_url": "http://openproject.de",
          "client_id": "the-client"
        }
      }
      """
    Then the HTTP status code should be "400"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^invalid key"}
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """


  Scenario Outline: setup with missing keys
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : <values>
      }
      """
    Then the HTTP status code should be "400"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^invalid key"}
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """
    Examples:
      | values                                                                                                                                                                                                           |
      | {"openproject_client_id": "the-client-id", "openproject_client_secret": "the-client-secret", "default_enable_navigation": false, "default_enable_unified_search": false}                                                                       |
      | {"openproject_instance_url": "http://some-host.de","openproject_client_secret": "the-client-secret", "default_enable_navigation": false, "default_enable_unified_search": false, "setup_project_folder": false, "setup_app_password": false }    |
      | {"openproject_instance_url": "http://some-host.de", "openproject_client_id": "the-client-id", "default_enable_navigation": false, "default_enable_unified_search": false, "setup_project_folder": false, "setup_app_password": false}            |
      | {"openproject_instance_url": "http://some-host.de", "openproject_client_id": "the-client-id", "openproject_client_secret": "the-client-secret", "default_enable_navigation": false, "setup_project_folder": false , "setup_app_password": false} |


  Scenario Outline: setup with data that is not even valid JSON
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      <data>
      """
    Then the HTTP status code should be "500"
    Examples:
      | data                                                                                                                                                                                          |
      | "{}"                                                                                                                                                                                          |
      | {"values": {"openproject_instance_url": "http://some-host.de","openproject_client_secret": "the-client-secret", "default_enable_navigation": false, "default_enable_unified_search": false,}} |
      | {"values": {"openproject_instance_url": "http://some-host.de","openproject_client_secret": "the-client-secret", "default_enable_navigation": false, "default_enable_unified_search": false}   |
      | {"values":                                                                                                                                                                                    |
      | "values"                                                                                                                                                                                      |
      | ""                                                                                                                                                                                            |

  Scenario: non-admin user tries to create the setup without project folder
    Given user "Carol" has been created
    When the user "Carol" sends a POST request to the "setup" endpoint with this data:
      """
      {
      "values" : {
      "openproject_instance_url": "http://some-host.de",
        "openproject_client_id": "the-client-id",
        "openproject_client_secret": "the-client-secret",
        "default_enable_navigation": false,
        "default_enable_unified_search": false,
        "setup_project_folder": false,
        "setup_app_password": false
        }
      }
      """
    Then the HTTP status code should be "403"
    And the data of the response should match
    """"
    {
    "type": "object",
    "not": {
      "required": [
          "nextcloud_oauth_client_name",
          "openproject_redirect_uri",
          "nextcloud_client_id",
          "nextcloud_client_secret"
        ]
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """

  Scenario Outline: valid update
    When the administrator sends a PATCH request to the "setup" endpoint with this data:
      """
      {
        "values": {
          "<key>": <value>
        }
      }
      """
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "nextcloud_oauth_client_name",
        "openproject_redirect_uri",
        "nextcloud_client_id"
      ],
      "properties": {
          "nextcloud_oauth_client_name": {"type": "string", "pattern": "^OpenProject client$"},
          "openproject_redirect_uri": {"type": "string", "pattern": "^http:\/\/.*\/oauth_clients\/[A-Za-z0-9]+\/callback$"},
          "nextcloud_client_id": {"type": "string", "pattern": "[A-Za-z0-9]+"}
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """
    Examples:
      | key                           | value                 |
      | openproject_instance_url      | "http://some-host.de" |
      | openproject_client_id         | "client-value"        |
      | openproject_client_secret     | "secret-value"        |
      | default_enable_navigation     | false                 |
      | default_enable_unified_search | true                  |


  Scenario Outline: valid update of multiple values at once
    When the administrator sends a PATCH request to the "setup" endpoint with this data:
      """
      {
        "values": {
          "<key1>": <value1>,
          "<key2>": <value2>
        }
      }
      """
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "nextcloud_oauth_client_name",
        "openproject_redirect_uri",
        "nextcloud_client_id"
      ],
      "properties": {
          "nextcloud_oauth_client_name": {"type": "string", "pattern": "^OpenProject client$"},
          "openproject_redirect_uri": {"type": "string", "pattern": "^http:\/\/.*\/oauth_clients\/[A-Za-z0-9]+\/callback$"},
          "nextcloud_client_id": {"type": "string", "pattern": "[A-Za-z0-9]+"}
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """
    Examples:
      | key1                      | value1                | key2                          | value2         |
      | openproject_instance_url  | "http://some-host.de" | openproject_client_id         | "client-value" |
      | openproject_client_secret | "secret-value"        | openproject_client_id         | "client-value" |
      | openproject_client_secret | "secret-value"        | default_enable_navigation     | false          |
      | default_enable_navigation | false                 | default_enable_unified_search | false          |


  Scenario Outline: update one value with invalid data
    When the administrator sends a PATCH request to the "setup" endpoint with this data:
      """
      {
        "values": {
          "<key>": <value>
        }
      }
      """
    Then the HTTP status code should be "400"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^<error-message>$"}
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """
    Examples:
      | key                           | value          | error-message |
      | openproject_instance_url      | null           | invalid data  |
      | openproject_instance_url      | ""             | invalid data  |
      | openproject_instance_url      | false          | invalid data  |
      | openproject_client_id         | null           | invalid data  |
      | openproject_client_id         | ""             | invalid data  |
      | openproject_client_id         | false          | invalid data  |
      | openproject_client_secret     | null           | invalid data  |
      | openproject_client_secret     | ""             | invalid data  |
      | openproject_client_secret     | false          | invalid data  |
      | default_enable_navigation     | null           | invalid data  |
      | default_enable_navigation     | ""             | invalid data  |
      | default_enable_navigation     | "string"       | invalid data  |
      | default_enable_unified_search | null           | invalid data  |
      | default_enable_unified_search | ""             | invalid data  |
      | default_enable_unified_search | "string"       | invalid data  |
      | instance_url                  | "http://op.de" | invalid key   |


  Scenario Outline: update of multiple values where at least one has invalid data
    When the administrator sends a PATCH request to the "setup" endpoint with this data:
      """
      {
        "values": {
          "<key1>": <value1>,
          "<key2>": <value2>
        }
      }
      """
    Then the HTTP status code should be "400"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^invalid data$"}
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """
    Examples:
      | key1                      | value1                | key2                          | value2         |
      | openproject_instance_url  | "http://some-host.de" | openproject_client_id         | null           |
      | openproject_instance_url  | "ftp://some-host.de"  | openproject_client_id         | "some id"      |
      | openproject_client_secret | ""                    | openproject_client_id         | "client-value" |
      | openproject_client_secret | "secret"              | openproject_client_id         | false          |
      | openproject_client_secret | "secret-value"        | default_enable_navigation     | "string"       |
      | default_enable_navigation | null                  | default_enable_unified_search | false          |


  Scenario Outline: with data that is not even valid JSON
    When the administrator sends a PATCH request to the "setup" endpoint with this data:
      """
      <data>
      """
    Then the HTTP status code should be "500"
    Examples:
      | data                                                                |
      | { "values": { "openproject_instance_url": "http://some-host.de"} }} |
      | { "values": { "openproject_instance_url": "http://some-host.de"}    |
      | "values": { "openproject_instance_url": "http://some-host.de"} }    |
      | { "values":                                                         |
      | "{}"                                                                |
      | ""                                                                  |


  Scenario: non-admin tries to update the setup
    Given user "Carol" has been created
    When the user "Carol" sends a PATCH request to the "setup" endpoint with this data:
      """
      {
        "values": {
          "openproject_instance_url": "http://some-host.de"
        }
      }
      """
    Then the HTTP status code should be "403"
    And the data of the response should match
    """"
    {
    "type": "object",
    "not": {
      "required": [
          "nextcloud_oauth_client_name",
          "openproject_redirect_uri",
          "nextcloud_client_id"
        ]
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """


  Scenario: Reset the integration
    When the administrator sends a DELETE request to the "setup" endpoint
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "status"
      ],
      "properties": {
          "status": {"type": "boolean", "enum": [ true ]}
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """


  Scenario: Trying to reset the integration as non-admin
    Given user "Carol" has been created
    When the user "Carol" sends a DELETE request to the "setup" endpoint
    Then the HTTP status code should be "403"

  Scenario Outline: Trying to setup whole integration, without project folder/user app password and vice-versa
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
      "values" : {
      "openproject_instance_url": "http://some-host.de",
        "openproject_client_id": "the-client-id",
        "openproject_client_secret": "the-client-secret",
        "default_enable_navigation": false,
        "default_enable_unified_search": false,
        "setup_project_folder": <setup_project_folder>,
        "setup_app_password": <setup_app_password>
        }
      }
      """
    Then the HTTP status code should be "400"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^invalid data$"}
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
   """
    Examples:
      | setup_project_folder | setup_app_password |
      | true               | false              |
      | false              | true               |


  # this test wil not pass locally if your system already has a `OpenProject` user/group setup
  Scenario: Set up whole integration with project folder and user app password
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
      "values" : {
      "openproject_instance_url": "http://some-host.de",
        "openproject_client_id": "the-client-id",
        "openproject_client_secret": "the-client-secret",
        "default_enable_navigation": false,
        "default_enable_unified_search": false,
        "setup_project_folder": true,
        "setup_app_password": true
        }
      }
      """
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "nextcloud_oauth_client_name",
        "openproject_redirect_uri",
        "nextcloud_client_id",
        "nextcloud_client_secret",
        "openproject_user_app_password"
      ],
      "properties": {
          "nextcloud_oauth_client_name": {"type": "string", "pattern": "^OpenProject client$"},
          "openproject_redirect_uri": {"type": "string", "pattern": "^http:\/\/some-host.de\/oauth_clients\/[A-Za-z0-9]+\/callback$"},
          "nextcloud_client_id": {"type": "string", "pattern": "[A-Za-z0-9]+"},
          "nextcloud_client_secret": {"type": "string", "pattern": "[A-Za-z0-9]+"},
          "openproject_user_app_password": {"type": "string", "pattern": "[A-Za-z0-9]+"}
      },
      "not": {
      "required": [
          "openproject_revocation_status"
        ]
      }
    }
    """
    And user "OpenProject" should be present in the server
    And group "OpenProject" should be present in the server
    And user "OpenProject" should be the subadmin of the group "OpenProject"
    And groupfolder "OpenProject" should be present in the server
    And groupfolder "OpenProject" should be assigned to the group "OpenProject" with all permissions
    And groupfolder "OpenProject" should have advance permissions enabled
    And groupfolder "OpenProject" should be managed by the user "OpenProject"
    # the next step is only for the tests, because that user has a random password
    Given the administrator has changed the password of "OpenProject" to the default testing password
    And user "OpenProject" should have a folder called "OpenProject"
    # folders inside the OpenProject folder can only be deleted/renamed by the OpenProject user
    And user "Carol" has been created
    And user "Carol" has been added to the group "OpenProject"
    And user "OpenProject" has created folder "/OpenProject/project-abc"
    Then user "Carol" should have a folder called "OpenProject/project-abc"
    When user "Carol" deletes folder "/OpenProject/project-abc"
    Then the HTTP status code should be 500
    When user "Carol" renames folder "/OpenProject/project-abc" to "/OpenProject/project-123"
    Then the HTTP status code should be 500
    When user "OpenProject" renames folder "/OpenProject/project-abc" to "/OpenProject/project-123"
    Then the HTTP status code should be 201
    When user "OpenProject" deletes folder "/OpenProject/project-123"
    Then the HTTP status code should be 204

    # folders 2 levels down inside the OpenProject folder can be deleted by any user even if the parent is also called "OpenProject"
    Given user "OpenProject" has created folder "/OpenProject/OpenProject/project-abc"
    When user "Carol" renames folder "/OpenProject/OpenProject/project-abc" to "/OpenProject/OpenProject/project-123"
    Then the HTTP status code should be 201
    When user "Carol" deletes folder "/OpenProject/OpenProject/project-123"
    Then the HTTP status code should be 204

    # a user, who is not in the OpenProject group can delete/rename items inside a folder that is called OpenProject
    Given user "Brian" has been created
    And user "Brian" has created folder "/OpenProject/project-abc"
    When user "Brian" renames folder "/OpenProject/project-abc" to "/OpenProject/project-123"
    Then the HTTP status code should be 201
    When user "Brian" deletes folder "/OpenProject/project-123"
    Then the HTTP status code should be 204

    # check deleting / disabling the OpenProject user/group
    When the administrator deletes the user "OpenProject"
    Then the HTTP status code should be 400
    And user "OpenProject" should be present in the server
    When the administrator deletes the group "OpenProject"
    Then the HTTP status code should be 400
    And group "OpenProject" should be present in the server
    When the administrator disables the user "OpenProject"
    Then the HTTP status code should be 400

    # resending setup request will fail
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
      "values" : {
        "openproject_instance_url": "http://some-host.de",
        "openproject_client_id": "the-client-id",
        "openproject_client_secret": "the-client-secret",
        "default_enable_navigation": false,
        "default_enable_unified_search": false,
        "setup_project_folder": true,
        "setup_app_password": true
        }
      }
      """
    Then the HTTP status code should be "409"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^The user \"OpenProject\" already exists$"}
      }
    }
    """

    # sending a PATCH request with setup_project_folder=true will also fail
    When the administrator sends a PATCH request to the "setup" endpoint with this data:
      """
      {
      "values" : {
        "setup_project_folder": true
        }
      }
      """
    Then the HTTP status code should be "409"
    And the data of the response should match
    """"
    {
    "type": "object",
    "required": [
        "error"
      ],
      "properties": {
          "error": {"type": "string", "pattern": "^The user \"OpenProject\" already exists$"}
      }
    }
    """

    # we can make api request using the created app password for user "OpenProject"
    When user "OpenProject" sends a "PROPFIND" request to "/remote.php/webdav" using current app password
    Then the HTTP status code should be "207"

    # this is to provide test coverage for issues like this
    # https://community.openproject.org/projects/nextcloud-integration/work_packages/49621
    When a new browser session for "Openproject" starts
    # but other values can be updated by sending a PATCH request
    # also we can replace old app password by sending PATCH request to get new user app password
    And the administrator sends a PATCH request to the "setup" endpoint with this data:
      """
      {
      "values" : {
        "default_enable_navigation": true,
        "setup_app_password": true
        }
      }
      """
    Then the HTTP status code should be "200"
    And the data of the response should match
    """"
    {
      "type": "object",
      "required": [
          "nextcloud_oauth_client_name",
          "openproject_redirect_uri",
          "nextcloud_client_id"
       ],
      "properties": {
          "nextcloud_oauth_client_name": {"type": "string", "pattern": "^OpenProject client$"},
          "openproject_redirect_uri": {"type": "string", "pattern": "^http:\/\/some-host.de\/oauth_clients\/[A-Za-z0-9]+\/callback$"},
          "nextcloud_client_id": {"type": "string", "pattern": "[A-Za-z0-9]+"},
          "openproject_user_app_password": {"type": "string", "pattern": "[A-Za-z0-9]+"}
      }
    }
    """
    And the newly generated app password should be different from the previous one

    # user "OpenProject" can make api request using the newly created app password
    When user "OpenProject" sends a "PROPFIND" request to "/remote.php/webdav" using new app password
    Then the HTTP status code should be "207"

    # user "OpenProject" cannot make api request using the old app password
    When user "OpenProject" sends a "PROPFIND" request to "/remote.php/webdav" using old app password
    Then the HTTP status code should be "401"
