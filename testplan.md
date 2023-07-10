## Installation

### Prerequisites

- fresh NextCloud, without any previous history of having the app installed

### Run tests as

1. main admin user
2. newly created user that is member of the "admin" group

### Tests

| steps                                                  | expected outcome                     | result | comment |
|--------------------------------------------------------|--------------------------------------|--------|---------|
| install app from marketplace                           | app installs without error messages  |        |         |
| install old version of the app, upgrade to new version | upgrade works without error messages |        |         |
| enable the app using `occ` command                     | app is listed as enabled             |        |         |
| disable the app using `occ` command                    | app is listed as disabled            |        |         |
| enable the app using the WebUI                         | app is listed as enabled             |        |         |
| disable the app using the WebUI                        | app is listed as disabled            |        |         |

## Configuration
### Before configuring the app

#### Prerequisites

- fresh NextCloud, without any previous history of having the app installed
- install, but don't configure OpenProject Integration app

### Run tests as

1. main admin user
2. newly created user that is member of the "admin" group

#### Tests

| steps                               | expected outcome                                                            | result | comment |
|-------------------------------------|-----------------------------------------------------------------------------|--------|---------|
| check dashboard                     | good error message shown                                                    |        |         |
| check OpenProject sidebar of a file | good error message shown                                                    |        |         |
| check administrators settings       | OpenProject section shown, only OpenProject host can be entered             |        |         |
| check personal settings             | OpenProject section in 'Connected accounts' shown, good error message shown |        |         |

### Configuration as admin
#### Prerequisites

- NextCloud installed
- OpenProject Integration app installed
- OpenProject Integration app enabled

### Run tests as

1. main admin user
2. newly created user that is member of the "admin" group

#### Tests

| steps                                                                            | expected outcome                                                        | result | comment |
|----------------------------------------------------------------------------------|-------------------------------------------------------------------------|--------|---------|
| enter invalid URL as OpenProject host                                            | good error message shown, setting not saved, next step not enabled      |        |         |
| enter valid URL, that does not have an OpenProject listening as OpenProject host | good error message shown, setting not saved, next step not enabled      |        |         |
| enter valid and correct OpenProject host                                         | no error message shown, setting saved, next step enabled                |        |         |
| enter only client Id                                                             | settings cannot be saved                                                |        |         |
| enter also client secret                                                         | settings can be saved, NextCloud OAuth settings are generated and shown |        |         |

### Connecting as user
#### Prerequisites

- NextCloud installed
- OpenProject Integration app installed
- OpenProject Integration app enabled
- OpenProject Integration app configured as administrator

### Run tests as
1. normal user

#### Tests

| steps                                                                         | expected outcome                                                                                                                                | result | comment |
|-------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------|--------|---------|
| check dashboard                                                               | button to connect to OpenProject shown                                                                                                          |        |         |
| check OpenProject sidebar of a file                                           | button to connect to OpenProject shown                                                                                                          |        |         |
| check personal settings                                                       | button to connect to OpenProject shown                                                                                                          |        |         |
| if connected, disconnect from OP, connect to OP through the file sidebar      | oauth connection process successful, message shown that connection was established, after connection redirected to the sidebar of the same file |        |         |
| if connected, disconnect from OP, connect to OP through the dashboard         | oauth connection process successful, message shown that connection was established, after connection redirected to dashboard                    |        |         |
| if connected, disconnect from OP, connect to OP through the personal settings | oauth connection process successful, message shown that connection was established, after connection redirected to settings page                |        |         |
| connect to OP, check personal settings                                        | button to disconnect from OpenProject shown                                                                                                     |        |         |
| connect to OP, check dashboard                                                | notifications listed, or message that there are no notifications                                                                                |        |         |
| connect to OP, check file sidebar of a file                                   | message saying that no workpackages are linked yet                                                                                              |        |         |

### Edit configuration as admin
#### Prerequisites

- NextCloud installed
- OpenProject Integration app installed
- OpenProject Integration app enabled
- OpenProject Integration app configured as administrator
- multiple NC users connected to OpenProject

### Run tests as

1. main admin user
2. newly created user that is member of the "admin" group

#### Tests

| steps                  | expected outcome                                                                                              | result | comment |
|------------------------|---------------------------------------------------------------------------------------------------------------|--------|---------|
| edit OP host           | OAuth client can still be used from OP side if OP is reachable by the new URL                                 |        |         |
| edit OP OAuth settings | warning shown, on confirmation all NC users disconnected from OP                                              |        |         | 
| reset NC OAuth client  | warning shown, on confirmation, old OAuth client deleted and new NC OAuth client created (check in OAuth app) |        |         | 

## Usage

### Prerequisites

- NextCloud installed
- OpenProject Integration app installed
- OpenProject Integration app enabled
- OpenProject Integration app configured as administrator
- multiple NC users connected to OpenProject

### Run tests as
1. normal user

### Dashboard

#### Tests

| steps                                                                    | expected outcome                                                                                       | result | comment |
|--------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------|--------|---------|
| generate some notifications in OP, display the OP dashboard in NC        | all OP notifications listed (not aggregated), every item links to the corresponding notification in OP |        |         |
| generate more than 6 notifications in OP, display the OP dashboard in NC | 6 OP notifications listed and a link to the OP notification center                                     |        |         |
| mark all notifications in OP as read, display the OP dashboard in NC     | no OP notifications listed but a good message saying that there are no notifications                   |        |         |

variations: check different NC themes

### Files

#### Tests

| steps                                                                            | expected outcome                                 | result | comment |
|----------------------------------------------------------------------------------|--------------------------------------------------|--------|---------|
| search for a WP                                                                  | WP that fit the search and only those are listed |        |         |
| link a WP to a file                                                              | WP displayed in list                             |        |         |
| link multiple WP to a file                                                       | WP listed correctly                              |        |         |
| link multiple WP to a file, go to an other file, come back to the original file  | WP listed correctly                              |        |         |
| link multiple WP to a file, delete some WP                                       | WP listed correctly                              |        |         |
| link multiple WP to a file, search for similar WP like the listed ones           | connected WP are not listed in the search        |        |         |

### Notifications

### Prerequisites

- notifications app installed and enabled https://github.com/nextcloud/notifications
- enable notifications in "OpenProject Integration" section of "Connected accounts"

#### Tests


| steps                                                                                                                             | expected outcome                                                                                                                                                                      | result | comment |
|-----------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------|---------|
| generate some notifications in OP, run `cron.php`                                                                                 | number of OP notifications are shown in one NC notification                                                                                                                           |        |         |
| generate some notifications in OP, run `cron.php`, generate more notifications in OP, run `cron.php`                              | two NC notifications are shown, first showing the amount of OP notifications before first cron run and second showing the amount of newly created notifications between the cron runs |        |         |
| generate some notifications in OP, run `cron.php`, dismiss notifications in NC, generate more notifications in OP, run `cron.php` | onle the number of OP notifications that were generated after the dismissal are shown in one NC notification                                                                          |        |         |
| generate some notifications in OP, run `cron.php`, check links of NC notifications                                                | links should go to <OP-instance>/notifications                                                                                                                                        |        |         |

### Search

### Prerequisites

- enable unified search in "OpenProject Integration" section of "Connected accounts"

| steps                                                      | expected outcome                             | result | comment |
|------------------------------------------------------------|----------------------------------------------|--------|---------|
| search for WP in NC unified search                         | related WP are listed in the search results  |        |         |
| search for WP in NC unified search, click on one of the WP | link takes user to the WP details view in OP |        |         |
