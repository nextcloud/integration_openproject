# OpenProject integration into Nextcloud

OpenProject integration provides a dashboard widget displaying your important notifications,
a search provider for work packages and notifications for changes in active work packages.

## 🔧 Configuration

### :lock: Authentication

To access data in OpenProject on behalf of the user this app needs to authenticate to OpenProject as the respective user. This happens using the OAuth workflow. (Using a personal access token is deprecated and not possible anymore.)

1. As an OpenProject admin create an OAuth app
	1. in OpenProject go to "Administration" -> "Authentication" -> "OAuth applications"
	2. use a name of your choice
	3. as `Redirect URI` use `<nextcloud-uri>/index.php/apps/integration_openproject/oauth-redirect`
	4. note down the Client ID and the Client Secret
2. As an NextCloud admin configure the OpenProject integration
	1. in NextCloud go to "Settings" -> "Administration" -> "Connected accounts"
	2. provide the OpenProject address, the Client ID and the Client Secret
3. As an NextCloud user connect to OpenProject
    1. click the `Connect to OpenProject` button that you can find on:
       - the OpenProject dashboard widget
       - the OpenProject tab in the details of every file
       - "Settings" -> "Personal" -> "Connected accounts"
    2. you will be redirected to OpenProject
    3. log-in to OpenProject if you haven't already
    4. Authorize the NextCloud App
    5. you will be redirected back to NextCloud

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
# the app needs to be cloned inside the "custom_apps" dir
mkdir $HOME/development/custom_apps -p
cd $HOME/development/custom_apps
git clone https://github.com/nextcloud/integration_openproject.git
# installation & building
npm ci
npm run build
# provide group ownership of "custom_apps" to the user "www-data"
sudo chgrp www-data $HOME/development/custom_apps -R
sudo chmod g+w $HOME/development/custom_apps -R
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
1. browse to [http://localhost](http://localhost)
2. create admin user
3. get an installed NC server

For the database, **PostgreSQL** is used with the following credentials:
- **Database:** `nextcloud`
- **User:** `nextcloud`
- **Password:** `nextcloud`

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
cd $HOME/development/custom_apps/integration_openproject
npm run watch
```
