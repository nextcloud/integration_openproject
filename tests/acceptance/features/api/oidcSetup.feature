# SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later
Feature: setup the integration with OIDC method


  Scenario: setup without team folder (External IDP with token exchange)
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "external",
          "oidc_provider": "Keycloak",
          "token_exchange": true,
          "targeted_audience_client_id": "openproject",
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false
        }
      }
      """
    Then the HTTP status code should be "200"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["status"],
        "properties": {
          "status": {"const": true}
        },
        "not": {
          "required": ["nextcloud_oauth_client_name"]
        }
      }
      """


  Scenario: setup without team folder (External IDP without token exchange)
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "external",
          "oidc_provider": "Keycloak",
          "token_exchange": false,
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false
        }
      }
      """
    Then the HTTP status code should be "200"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["status"],
        "properties": {
          "status": {"const": true}
        },
        "not": {
          "required": ["nextcloud_oauth_client_name"]
        }
      }
      """


  Scenario: setup without team folder (Nextcloud IDP)
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false
        }
      }
      """
    Then the HTTP status code should be "200"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["status"],
        "properties": {
          "status": {"const": true}
        },
        "not": {
          "required": ["nextcloud_oauth_client_name"]
        }
      }
      """


  Scenario Outline: try to setup with invalid data
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": <auth_method>,
          "sso_provider_type": <provider_type>,
          "targeted_audience_client_id": <target_client_id>,
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false
        }
      }
      """
    Then the HTTP status code should be "400"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["error"],
        "properties": {
          "error": {"const": "<error>"}
        },
        "not": {
          "required": ["openproject_revocation_status"]
        }
      }
      """
    Examples:
      | auth_method | provider_type   | target_client_id | error                        |
      | null        | "nextcloud_hub" | "client-id"      | Invalid authorization method |
      | ""          | "nextcloud_hub" | "client-id"      | Invalid authorization method |
      | true        | "nextcloud_hub" | "client-id"      | Invalid authorization method |
      | "unknown"   | "nextcloud_hub" | "client-id"      | Invalid authorization method |
      | "oidc"      | null            | "client-id"      | invalid key                  |
      | "oidc"      | ""              | "client-id"      | invalid key                  |
      | "oidc"      | true            | "client-id"      | invalid key                  |
      | "oidc"      | "unknown"       | "client-id"      | invalid key                  |
      | "oidc"      | "nextcloud_hub" | null             | invalid data                 |
      | "oidc"      | "nextcloud_hub" | ""               | invalid data                 |
      | "oidc"      | "nextcloud_hub" | false            | invalid data                 |
      | "oidc"      | "nextcloud_hub" | []               | invalid data                 |


  Scenario: try to setup with unknown key
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "openproject_instance_url": "http://openproject.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "client-id",
          "setup_project_folder": false,
          "setup_app_password": false,
          "unknown_key": "some-value"
        }
      }
      """
    Then the HTTP status code should be "400"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["error"],
        "properties": {
          "error": {"const": "invalid key"}
        },
        "not": {
          "required": ["openproject_revocation_status"]
        }
      }
      """


  Scenario Outline: try to setup with missing keys
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "openproject_instance_url": "http://openproject.de",
          "authorization_method": "oidc",
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false,
          <settings>
        }
      }
      """
    Then the HTTP status code should be "400"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["error"],
        "properties": {
          "error": {"const": "<error>"}
        },
        "not": {
          "required": ["openproject_revocation_status"]
        }
      }
      """
    Examples:
      | settings                                                                      | error                                                                   |
      | "targeted_audience_client_id":"client-id"                                     | Incomplete settings: 'sso_provider_type' is required with 'oidc' method |
      | "sso_provider_type":"nextcloud_hub"                                           | invalid key                                                             |
      | "sso_provider_type":"external", "token_exchange":false                        | invalid key                                                             |
      | "sso_provider_type":"external", "oidc_provider":"test", "token_exchange":true | invalid key                                                             |


  Scenario: non-admin user tries to setup without team folder
    Given user "Carol" has been created
    When the user "Carol" sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false
        }
      }
      """
    Then the HTTP status code should be "403"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["message"],
        "properties": {
          "message": {"const": "Logged in account must be an admin"}
        },
        "not": {
          "required": [
            "nextcloud_oauth_client_name",
            "openproject_redirect_uri",
            "nextcloud_client_id",
            "nextcloud_client_secret",
            "openproject_revocation_status"
          ]
        }
      }
      """


  Scenario Outline: update settings
    Given the administrator has set up the integration with the following settings:
      """
      {
        "values": {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false
        }
      }
      """
    When the administrator sends a PATCH request to the "setup" endpoint with this data:
      """
      {
        "values": {
          <settings>
        }
      }
      """
    Then the HTTP status code should be "200"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["status"],
        "properties": {
          "status": {"const": true}
        },
        "not": {
          "required": ["nextcloud_oauth_client_name"]
        }
      }
      """
    Examples:
      | settings                                                                                       |
      | "targeted_audience_client_id":"new-id"                                                         |
      | "sso_provider_type":"external","oidc_provider":"keycloak","token_exchange":false               |
      | "sso_provider_type":"external","token_exchange":true,"targeted_audience_client_id":"client-id" |


  Scenario Outline: try to update a setting with invalid data
    Given the administrator has set up the integration with the following settings:
      """
      {
        "values": {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false
        }
      }
      """
    When the administrator sends a PATCH request to the "setup" endpoint with this data:
      """
      {
        "values": {
          <settings>
        }
      }
      """
    Then the HTTP status code should be "400"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["error"],
        "properties": {
          "error": {"const": "<error>"}
        },
        "not": {
          "required": ["openproject_revocation_status"]
        }
      }
      """
    Examples:
      | settings                                                                                         | error        |
      | "sso_provider_type":"unknown"                                                                    | invalid data |
      | "sso_provider_type":"external","oidc_provider":false,"token_exchange":false                      | invalid data |
      | "sso_provider_type":"external","token_exchange":"true","targeted_audience_client_id":"client-id" | invalid data |


  Scenario Outline: try to update settings with invalid json data
    Given the administrator has set up the integration with the following settings:
      """
      {
        "values": {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false
        }
      }
      """
    When the administrator sends a PATCH request to the "setup" endpoint with this data:
      """
      <data>
      """
    Then the HTTP status code should be "400" or "500"
    Examples:
      | data                                                                |
      | { "values": { "openproject_instance_url": "http://some-host.de"} }} |
      | { "values": { "openproject_instance_url": "http://some-host.de"}    |
      | "values": { "openproject_instance_url": "http://some-host.de"} }    |
      | { "values":                                                         |
      | "{}"                                                                |
      | ""                                                                  |


  Scenario: non-admin user tries to update the settings
    Given the administrator has set up the integration with the following settings:
      """
      {
        "values": {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false
        }
      }
      """
    And user "Carol" has been created
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
      """
      {
        "type": "object",
        "required": ["message"],
        "properties": {
          "message": {"const": "Logged in account must be an admin"}
        },
        "not": {
          "required": [
            "nextcloud_oauth_client_name",
            "openproject_redirect_uri",
            "nextcloud_client_id",
            "nextcloud_client_secret",
            "openproject_revocation_status"
          ]
        }
      }
      """


  Scenario: reset the integration setup
    Given the administrator has set up the integration with the following settings:
      """
      {
        "values": {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false
        }
      }
      """
    When the administrator sends a DELETE request to the "setup" endpoint
    Then the HTTP status code should be "200"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["status"],
        "properties": {
          "status": {"const": true}
        },
        "not": {
          "required": ["openproject_revocation_status"]
        }
      }
      """


  Scenario: non-admin user tries to reset the integration setup
    Given the administrator has set up the integration with the following settings:
      """
      {
        "values": {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "setup_project_folder": false,
          "setup_app_password": false,
          "default_enable_navigation": false,
          "default_enable_unified_search": false
        }
      }
      """
    And user "Carol" has been created
    When the user "Carol" sends a DELETE request to the "setup" endpoint
    Then the HTTP status code should be "403"


  Scenario Outline: try to setup with incomplete team folder
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "default_enable_navigation": false,
          "default_enable_unified_search": false,
          "setup_project_folder": <setup_project_folder>,
          "setup_app_password": <setup_app_password>
        }
      }
      """
    Then the HTTP status code should be "400"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["error"],
        "properties": {
          "error": {"const": "invalid data"}
        },
        "not": {
          "required": ["openproject_revocation_status"]
        }
      }
      """
    Examples:
      | setup_project_folder | setup_app_password |
      | true                 | false              |
      | false                | true               |


  Scenario: setup with team folder
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "default_enable_navigation": false,
          "default_enable_unified_search": false,
          "setup_project_folder": true,
          "setup_app_password": true
        }
      }
      """
    Then the HTTP status code should be "200"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": [
          "status",
          "openproject_user_app_password"
        ],
        "properties": {
          "status": {"const": true},
          "openproject_redirect_uri": {"pattern": "^http:\/\/some-host.de\/oauth_clients\/[A-Za-z0-9]+\/callback$"},
          "nextcloud_client_id": {"pattern": "[A-Za-z0-9]+"},
          "nextcloud_client_secret": {"pattern": "[A-Za-z0-9]+"},
          "openproject_user_app_password": {"pattern": "[A-Za-z0-9]+"}
        },
        "not": {
          "required": ["nextcloud_client_id","openproject_redirect_uri"]
        }
      }
      """
    And user "OpenProject" should be the subadmin of the group "OpenProject"
    And user "OpenProject" should be the subadmin of the group "OpenProjectNoAutomaticProjectFolders"
    And groupfolder "OpenProject" should be assigned to the group "OpenProject" with all permissions
    And groupfolder "OpenProject" should have advance permissions enabled
    And groupfolder "OpenProject" should be managed by the user "OpenProject"


  Scenario: try to setup and update twice with team folder
    Given the administrator has set up the integration with the following settings:
      """
      {
        "values": {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "default_enable_navigation": false,
          "default_enable_unified_search": false,
          "setup_project_folder": true,
          "setup_app_password": true
        }
      }
      """
    When the administrator sends a POST request to the "setup" endpoint with this data:
      """
      {
        "values" : {
          "openproject_instance_url": "http://some-host.de",
          "authorization_method": "oidc",
          "sso_provider_type": "nextcloud_hub",
          "targeted_audience_client_id": "openproject",
          "default_enable_navigation": false,
          "default_enable_unified_search": false,
          "setup_project_folder": true,
          "setup_app_password": true
        }
      }
      """
    Then the HTTP status code should be "409"
    And the data of the response should match
      """
      {
        "type": "object",
        "required": ["error"],
        "properties": {
          "error": {"const": "The user \"OpenProject\" already exists"}
        }
      }
      """
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
      """
      {
        "type": "object",
        "required": ["error"],
        "properties": {
          "error": {"const": "The user \"OpenProject\" already exists"}
        }
      }
      """
