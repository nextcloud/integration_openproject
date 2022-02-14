# OpenProject integration into Nextcloud

OpenProject integration provides a dashboard widget displaying your important notifications,
a search provider for work packages and notifications for changes in active work packages.

## 🔧 Configuration

### :lock: Authentication

To access data in OpenProject on behalf of the user this app needs to authenticate to OpenProject as the respective user.

This can happen either through a personal access token or the OAuth workflow. Both ways to authenticate have their own advantages and disadvantages.

Using a personal access token enables every NextCloud user to connect to an OpenProject instance of their choice because no configuration needs to be changed by the NextCloud or OpenProject admin (except for installing and enabling this app).
On the other hand every user has to perform a series of manual steps to connect NextCloud to OpenProject the first time. Also, this is the less secure way of authentication. If the API Key gets leaked the attacker can do any actions on OpenProject as if they had been done by the legitimate user. Even though the key can be reset, it usually stays the same for a long time. If an attacker gets even short-time access to the NextCloud system or access to a message transferred between NextCloud and OpenProject he could misuse that knowledge for as long as the API key stays unchanged.

Using the OAuth authentication is much easier for every user, but requires the NextCloud admin and the OpenProject admin to configure both apps.
OAuth is also the much safer option to connect both apps and therefore the recommended way to use this app.
To give access to OpenProject the first time the user needs to log-in with the OpenProject credentials and actively approve the connection. In the result a user-token will be generated automatically and exchanged between NextCloud and OpenProject. This token will be refreshed on a regular basis, so if an attacker gains access to a message transferred between NextCloud and OpenProject it can be only misused till the next refreshing of the token happens.

The account configuration happens in the "Connected accounts" user settings section. A link to the "Connected accounts" user settings section will be displayed in the widget for users who didn't configure an OpenProject account.

#### OAuth

1. As an OpenProject admin create an OAuth app
	1. in OpenProject go to "Administration" -> "Authentication" -> "OAuth applications"
	2. use a name of your choice
	3. as `Redirect URI` use `<nextcloud-uri>/index.php/apps/integration_openproject/oauth-redirect`
	4. note down the Client ID and the Client Secret
2. As an NextCloud admin configure the OpenProject integration
	1. in NextCloud go to "Settings" -> "Personal" -> "Connected accounts"
	2. provide the OpenProject address, the Client ID and the Client Secret
3. As an NextCloud user connect to OpenProject
	1. in NextCloud go to "Settings" -> "Personal" -> "Connected accounts"
	2. provide the OpenProject address (it has to be exactly the same as provided by the administrator in step 2)
	3. a new button `Connect to OpenProject` should be visible
	4. click `Connect to OpenProject`
	5. you will be redirected to OpenProject
	6. log-in to OpenProject if you haven't already
	7. Authorize the NextCloud App
	8. you will be redirected back to NextCloud

#### Personal access token (NOT recommended)

1. As an OpenProject user get an access token (API key)
   1. in OpenProject click on your user image in the top right corner
   2. go to "My account" -> "Access token"
   3. click on "Generate" button in the "API" row. If there is no "Generate" button you have already created an API token for this user and in case you don't know it any-more you can always create a new one by clicking "Reset", but this will invalidate any old token.
   4. note down the API token that is displayed
2. As an NextCloud user connect to OpenProject
   1. in NextCloud go to "Settings" -> "Personal" -> "Connected accounts"
   2. provide the OpenProject address
   3. enter or copy the OpenProject API token into the "Access token" field
   4. after a short time the app will try to establish the connection to OpenProject and if all worked correctly it will display the status: "Connected as <fullname of user in OpenProject>"


#### Background jobs

To be able to periodically check activity in OpenProject (when "notifications for activity in my work packages" is enabled), you need to choose the "Cron" background job method and set a system cron task calling cron.php as explained in the [documentation](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/background_jobs_configuration.html#cron).

## Development
Develop using docker compose

Requirements:
- Node.js
- Docker, Docker Compose
- OpenProject server instance running in the host machine
- OpenProject Integration app

### Setup
```shell
# the app needs to be cloned inside the "custom_apps" dir anywhere in the host
mkdir /dev/custom_apps
git clone https://github.com/nextcloud/integration_openproject.git
# installation & building
npm ci
npm run build
# provide ownership of "custom_apps" to the user "www-data"
sudo chown www-data custom_apps -R
```

### Environments
- **APP_DIR**
  - description: location where the `integration_openproject` repository is cloned 
  - default: `./../../custom_apps`

### Start compose
**Note:** If your host machine has anything up on port `80`, please kill it before starting. 

```shell
docker-compose up -d
```

After this, you should be able to access nextcloud server at [http://localhost](http://localhost).

### Setup NC server

> **Note:** These steps will only be necessary for the first setup.

#### Create admin
For the database, **PostgreSQL** is used with the following credentials:
- **Database:** `nextcloud`
- **User:** `nextcloud`
- **Password:** `nextcloud`

1. browse to [http://localhost](http://localhost)
2. create admin user
3. get an installed NC server

#### Enable integration app 
You can browse as admin to the apps center and enable it using the webUI. Or, you can just use the terminal as:

```shell
docker exec --user www-data integration_openproject_nc php occ a:e integration_openproject
```

#### Allow local remote servers: 

```shell
docker exec --user www-data integration_openproject_nc php occ config:system:set allow_local_remote_servers --value 1
```

### Start Developing
Now you can watch for the app code changes using the following command and start developing.

```shell
cd /dev/custom_apps/integration_openproject
npm run watch
```
