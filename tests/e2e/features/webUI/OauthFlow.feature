Feature:
  As an admin
  I want to setup oauth between nextcloud and openproject
  So that the apps can communicate with each other


  Scenario: user logs in
    Given openproject administrator has logged in openproject using the webUI
    And nextcloud administrator has logged in using the webUI
    And the administrator has navigated to the openproject tab in administrator settings
    When openproject administrator adds the nextcloud host with name "nextcloud" in file storage
    And nextcloud administrator adds the openproject host
    And openproject administrator copies the openproject oauth credentials
    And nextcloud administrator pastes the openproject oauth credentials
    And nextcloud administrator copies the nextcloud oauth credentials
    And openproject administrator pastes the nextcloud oauth credentials
    Then file storage "nextcloud" should be listed on the webUI of openproject
    And the oauth setting from should be completed on the webUI of nextcloud
#    When administator connects to the openproject through the personal settings
#    And the user authorizes in open project with username "admin2" and password "admin2"
#    Then the user should be connected to the openproject
