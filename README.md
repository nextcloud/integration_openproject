# OpenProject integration into Nextcloud

OpenProject integration provides the possibility to link OpenProject work packages to a file in NextCloud, a dashboard widget for displaying OpenProject notifications, a search provider to search for work packages through the unified search and can display the count of OpenProject notifications as NextCloud notification.

> **Note:** The configuration documentation to set up the OpenProject to link work packages in the Nextcloud can be found [here](https://www.openproject.org/docs/)

## :computer: Development
Develop using docker compose

Requirements:
- Node.js (>=v14.0.0)
- NPM (>=v7.0.0)
- Docker (>=v19.03.0)
- Docker Compose
  - for v1, minimum version required is v1.29.0 (our guide is for v1)
  - for v2, make sure to use `docker compose` instead of `docker-compose`
- OpenProject server instance running in the host machine

  it must be reachable by the hostname `host.docker.internal`.

  This hostname will resolve to `127.0.0.1` on the docker host and to something like `172.17.0.1` inside of the docker services, so OpenProject needs to listen to those addresses

  For more information see: [OpenProject documentation](https://www.openproject.org/docs/development/development-environment-ubuntu/)

>**Note:**  While starting the OpenProject server make sure to add environment variable `OPENPROJECT_FEATURE__STORAGES__MODULE__ACTIVE=true` or set `feature_storages_module_active: true` in the `configuration.yml`

- OpenProject integration app

### Setup
```shell
# the app needs to be cloned inside the "custom_apps" dir
mkdir $HOME/development/custom_apps -p
cd $HOME/development/custom_apps
git clone https://github.com/nextcloud/integration_openproject.git

# installation & building
cd integration_openproject
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

It is highly recommended to regularly update the included containers.
```shell
docker-compose pull
```

Now, run the containers.
```shell
docker-compose up
```
**Note:** If you've cloned the integration app anywhere other that the default `./../../custom_apps`, provide its path in the `APP_DIR` environment variable
```shell
APP_DIR=<path-to-integration-app> docker-compose up
```

After this, you should be able to access the Nextcloud server at [http://localhost](http://localhost).

### Setup NC server

> **Note:** These steps will only be necessary for the first setup.

#### Create admin
1. Browse to [http://localhost:8080](http://localhost:8080)
2. Create an admin user
3. Get an installed NC server

For the database, **PostgreSQL** is used with the following credentials:
- **Database:** `nextcloud`
- **User:** `nextcloud`
- **Password:** `nextcloud`

#### Enable the integration app:
You can browse as admin to the apps center and enable it using the webUI or you can just use the terminal as:

```shell
docker exec --user www-data integration_openproject_nc php occ a:e integration_openproject
```

#### Allow local remote servers: 

```shell
docker exec --user www-data integration_openproject_nc php occ config:system:set allow_local_remote_servers --value 1
```

#### Configure the integration app:
- as NextCloud admin browse to Settings->Administration->OpenProject
- configure the connection to OpenProject using `http://host.docker.internal:3000` as the OpenProject URL
- in OpenProject use `http://localhost:8080` as the NextCloud URL

### Start Developing
Now you can watch for the app code changes using the following command and start developing.

```shell
cd $HOME/development/custom_apps/integration_openproject
npm run watch
```
