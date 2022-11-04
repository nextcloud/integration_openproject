Feature:
  As a user
  I want to
  So that


  Scenario: user logs in
    Given nextcloud administrator has logged in using the webUI
    And the administrator has navigated to the openproject tab in administrator settings
    And openproject administrator has logged in openproject using the webUI
    When openproject administrator adds file storage with following settings
      | name      | host                              |
      | nextcloud | http://localhost/nextcloud/master |
    And nextcloud administrator adds following openproject host
      | host                  |
      | http://localhost:3000 |
    And openproject administrator copies the openproject oauth credintials
    And nextcloud administrator pastes the openproject oauth credintials
    And nextcloud administrator copies the nextcloud oauth credintials
    And openproject administrator pastes the nextcloud oauth credintials

