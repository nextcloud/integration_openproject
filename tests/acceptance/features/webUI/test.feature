Feature:
  As a user
  I want to
  So that


  Scenario: user logs in
    Given administrator has logged in using the webUI
    And the user has navigated to the openproject administrator settings
    And openproject administrator created file storage with following settings
      | name      | host                       |
      | nextcloud | http://localhost/nextcloud |
    When administrator connects to the openProject using the webUI with following settings
      | host                  |
      | http://localhost:3000 |

