Feature: setup the integration through an API

  Scenario: valid setup
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "openproject_instance_url": "http://some-host.de",
        "openproject_client_id": "the-client-id",
        "openproject_client_secret": "the-client-secret",
        "default_enable_navigation": false,
        "default_enable_unified_search": false
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
          "openproject_redirect_uri": {"type": "string", "pattern": "^http:\/\/some-host\.de\/oauth_clients\/[A-Za-z0-9]+\/callback$"},
          "nextcloud_client_id": {"type": "string", "pattern": "[A-Za-z0-9]+"},
          "nextcloud_client_secret": {"type": "string", "pattern": "[A-Za-z0-9]+"},
      }
    }
   """


  Scenario Outline: setup with invalid data
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "openproject_instance_url": <instance_url>,
        "openproject_client_id": <openproject_client_id>,
        "openproject_client_secret": <openproject_client_secret>,
        "default_enable_navigation": <enable_navigation>,
        "default_enable_unified_search": <enable_unified_search>
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
      }
    }
   """
    Examples:
      | instance_url          | openproject_client_id | openproject_client_secret | enable_navigation | enable_unified_search |
      | null                  | null      | null          | null              | null                  |
      | null                  | "id"      | "secret"      | false             | false                 |
      | "http://some-host.de" | null      | "secret"      | false             | false                 |
      | "http://some-host.de" | "id"      | null          | false             | false                 |
      | "http://some-host.de" | "id"      | "secret"      | null              | false                 |
      | "http://some-host.de" | "id"      | "secret"      | true              | null                  |
      |                       |           |               |                   |                       |
      |                       | "id"      | "secret"      | false             | false                 |
      | "http://some-host.de" |           | "secret"      | false             | false                 |
      | "http://some-host.de" | "id"      |               | false             | false                 |
      | "http://some-host.de" | "id"      | "secret"      |                   | false                 |
      | "http://some-host.de" | "id"      | "secret"      | true              |                       |
      | "ftp://somehost.de"   | "the-id"  | "secret"      | true              | false                 |
      | "http://somehost.de"  | false     | "secret"      | true              | false                 |
      | "http://somehost.de"  | "id"      | false         | true              | false                 |
      | "http://somehost.de"  | "the-id"  | "secret"      | "a string"        | false                 |
      | "http://somehost.de"  | "the-id"  | "secret"      | false             | "a string"            |


  Scenario Outline: valid update
    When the administrator sends a PUT request to the "setup" endpoint with this data:
      """
      {
        "<key>": <value>
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
          "openproject_redirect_uri": {"type": "string", "pattern": "^http:\/\/.*\/oauth_clients\/[A-Za-z0-9]+\/callback$"},
          "nextcloud_client_id": {"type": "string", "pattern": "[A-Za-z0-9]+"},
          "nextcloud_client_secret": {"type": "string", "pattern": "[A-Za-z0-9]+"},
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
    When the administrator sends a PUT request to the "setup" endpoint with this data:
      """
      {
        "<key1>": <value1>,
        "<key2>": <value2>
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
          "openproject_redirect_uri": {"type": "string", "pattern": "^http:\/\/.*\/oauth_clients\/[A-Za-z0-9]+\/callback$"},
          "nextcloud_client_id": {"type": "string", "pattern": "[A-Za-z0-9]+"},
          "nextcloud_client_secret": {"type": "string", "pattern": "[A-Za-z0-9]+"},
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
    When the administrator sends a PUT request to the "setup" endpoint with this data:
      """
      {
        "<key>": <value>
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
      }
    }
   """
    Examples:
      | key                           | value    |
      | openproject_instance_url      | null     |
      | openproject_instance_url      |          |
      | openproject_instance_url      | false    |
      | openproject_client_id         | null     |
      | openproject_client_id         |          |
      | openproject_client_id         | false    |
      | openproject_client_secret     | null     |
      | openproject_client_secret     |          |
      | openproject_client_secret     | false    |
      | default_enable_navigation     | null     |
      | default_enable_navigation     |          |
      | default_enable_navigation     | "string" |
      | default_enable_unified_search | null     |
      | default_enable_unified_search |          |
      | default_enable_unified_search | "string" |


  Scenario Outline: update of multiple values where at least one has invalid data
    When the administrator sends a PUT request to the "setup" endpoint with this data:
      """
      {
        "<key1>": <value1>,
        "<key2>": <value2>
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
      }
    }
   """
    Examples:
      | key1                      | value1                | key2                          | value2         |
      | openproject_instance_url  | "http://some-host.de" | openproject_client_id         | null           |
      | openproject_instance_url  | "ftp://some-host.de"  | openproject_client_id         | "some id"      |
      | openproject_client_secret |                       | openproject_client_id         | "client-value" |
      | openproject_client_secret | "secret"              | openproject_client_id         | false          |
      | openproject_client_secret | "secret-value"        | default_enable_navigation     | "string"       |
      | default_enable_navigation | null                  | default_enable_unified_search | false          |
