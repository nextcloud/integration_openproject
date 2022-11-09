Feature:
  As a user
  I want to
  So that


  Scenario: user logs in
    Given openproject administrator has logged in openproject using the webUI
    And nextcloud administrator has logged in using the webUI
    And the administrator has navigated to the openproject tab in administrator settings
    When openproject administrator adds file storage with following settings
      | name      | host                              |
      | nextcloud | http://localhost/nextcloud/master |
    And nextcloud administrator adds following openproject host
      | host                  |
      | http://localhost:8080 |
    And openproject administrator copies the openproject oauth credentials
    And nextcloud administrator pastes the openproject oauth credentials
    And nextcloud administrator copies the nextcloud oauth credentials
    And openproject administrator pastes the nextcloud oauth credentials
    Then file storage "nextcloud" should be listed on the webUI of openproject
    And the oauth setting from should be completed on the webUI of nextcloud


