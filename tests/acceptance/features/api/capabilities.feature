Feature: get capabilities of the app

  Scenario: Get capabilities when group folder app is enabled
    When the administrator requests the nextcloud capabilities
    Then the HTTP status code should be "200"
    And the ocs data of the response should match
    """"
    {
      "type": "object",
      "required": [
        "capabilities"
      ],
      "properties": {
        "capabilities": {
          "type": "object",
          "required": [
            "integration_openproject"
          ],
          "properties": {
            "integration_openproject": {
              "type": "object",
              "required": [
                "app_version",
                "groupfolder_version",
                "groupfolders_enabled"
              ],
              "properties": {
                "app_version": {
                  "type": "string",
                  "pattern": "^\\d+\\.\\d+\\.\\d+$"
                },
                "groupfolder_version": {
                  "type": "string",
                  "pattern": "^\\d+\\.\\d+\\.\\d+(\\-\\w+)?$"
                },
                "groupfolders_enabled": {
                  "type": "boolean",
                  "enum": [
                    true
                  ]
                }
              }
            }
          }
        }
      }
    }
    """
