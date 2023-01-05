# OpenProject Integration

This application enables integration between Nextcloud and open-source project management software OpenProject. 

On the Nextcloud end, it allows users to:

* Link files and folders with work packages in OpenProject
* Find all work packages linked to a file or a folder
* View OpenProject notifications via the dashboard
* Search for work packages using Nextcloud's search bar

On the OpenProject end, users are able to:

* View all Nextcloud files and folders linked to a work package
* Download linked files or open them in Nextcloud to edit them

Please report issues and bugs here: https://community.openproject.org/projects/nextcloud-integration/work_packages

## Documentation for users and administrators

- For documentation on how to use the integration once it is set up, refer to [Using the Nextcloud integration](https://openproject.org/docs/user-guide/nextcloud-integration/).
- For documentation on how to set up the integration as an administrator, refer to [Nextcloud integration setup](https://openproject.org/docs/system-admin-guide/integrations/nextcloud/).

## Setting up the integration as an administrator with API
We have a single API endpoint (/setup) available to set up, update and reset the integration.

To set up or update the integration following data needs to be provided:
- openproject_instance_url
- openproject_client_id
- openproject_client_id
- default_enable_navigation
- default_enable_unified_search


1. **Set up the whole integration with a [POST] request**

	We must provide all of the above data for this request.

	Example curl request to set up whole integration
	```bash
	curl -XPOST -uadmin:admin  http://<nextcloud_host>/index.php/apps/integration_openproject/setup \
	-d '{"values":{"openproject_instance_url":<openproject_instance_url>,"openproject_client_id":<openproject_client_id>,"openproject_client_secret":<openproject_client_secret>,"default_enable_navigation":false,"default_enable_unified_search":false}}' \
	-H 'Content-Type: application/json' -v
	```

	The response from the above curl request
	```json
	{
		"nextcloud_oauth_client_name": "<openproject-client>",
		"openproject_redirect_uri": "http://<openproject_instance_url>/oauth_clients/<nextcloud_client_id>/callback",
		"nextcloud_client_id": "<nextcloud_client_id>",
		"nextcloud_client_secret": "<nextcloud_client_secret>",
		"openproject_revocation_status": <openproject_revocation_status>
	}
	```

2. **Update the integration with a [PATCH] request**

    One or more of the above data needs to be sent to this endpoint

	Example curl request to update only `openproject_client_id` and `openproject_client_secret`
	```bash
	curl -XPATCH -uadmin:admin  http://<nextcloud_host>/index.php/apps/integration_openproject/setup \
	-d '{"values":{"openproject_client_id":<openproject_client_id>,"openproject_client_secret":<openproject_client_secret>}}' \
	-H 'Content-Type: application/json' -v
	```

	The response from the above curl request
	```json
	{
		"nextcloud_oauth_client_name": "<openproject-client>",
		"openproject_redirect_uri": "http://<openproject_instance_url>/oauth_clients/<nextcloud_client_id>/callback",
		"nextcloud_client_id": "<nextcloud_client_id>",
		"nextcloud_client_secret": "<nextcloud_client_secret>",
		"openproject_revocation_status": <openproject_revocation_status>
	}
	```

2. **Resetting the whole integration with a [DELETE] request**

	Example curl request to reset whole integration
	```bash
	curl -XDELETE -uadmin:admin http://<nextcloud_host>/index.php/apps/integration_openproject/setup
	```

	The response from the above curl request
	```json
	{
		"status": true,
		"openproject_revocation_status": <openproject_revocation_status>
	}
	```
***Note: In the response `openproject_revocation_status` is included only after successfull connection***

## Setting up the integration with shell script

`integration_setup.sh` sets up the whole integration with just one command.

Prerequisites needed for using the shell script.
1. "OpenProject" is set up and running
2. "Nextcloud" is set up and running
3. The "OpenProject Integration" app is installed and enabled in Nextcloud.
4. The credentials of the OpenProject global user are known
5. In "Nextcloud" we already have set up an admin with credentials.

Once all the above pre-conditions are met we can run the shell script to integrate with the following command.

The following bash command has an environment variable `OPENPROJECT_STORAGE_NAME` which will be the storage name to store the oauth information in open project required for integration.

```bash
NEXTCLOUD_HOST=<nextcloud_host_url> \                      
OPENPROJECT_HOST=<openproject_host_url> \
OP_ADMIN_USERNAME=<openproject_global_admin_uername> OP_ADMIN_PASSWORD=<openproject_global_admin_password> \
NC_ADMIN_USERNAME=<nextcloud_admin_username> NC_ADMIN_PASSWORD=<nextcloud_admin_password> \                                                              
OPENPROJECT_STORAGE_NAME=<files_storage_name> \                          
bash integration_setup.sh
```

***Note: these credentials are only used by the script to do the setup. They are not stored/remembered.***

## Development

Develop using docker compose

Requirements:

- Node.js (>=v14.0.0)
- NPM (>=v7.0.0)
- Docker (>=v19.03.0)
- Docker Compose
    - for v1, minimum version required is v1.29.0, make sure to use `docker-compose` instead of `docker compose`
    - this guide is written for v2
- OpenProject server instance

  It must be reachable by the hostname `host.docker.internal` in the host machine. You can do this through this environment
  variable: `OPENPROJECT_DEV_EXTRA_HOSTS=host.docker.internal`.
  
  It must also use hostname `host.docker.internal:3000` instead of `localhost:3000` in the settings. Change it in
  Administration > System settings > General > Host name, or through the environment variable
  `OPENPROJECT_HOST__NAME=host.docker.internal:3000`.
  
  `host.docker.internal` will resolve to `127.0.0.1` on the docker host and to something like `172.17.0.1` inside of the docker
  services, so OpenProject needs to listen to those addresses. For that use the `-b` option if you are running
  OpenProject manually e.g. `bin/rails server -b 0.0.0.0` or when using foreman set `HOST=0.0.0.0` as env. variable.

  The whole OpenProject start command might look something like:
    - manual start: `OPENPROJECT_DEV_EXTRA_HOSTS=host.docker.internal OPENPROJECT_HOST__NAME=host.docker.internal:3000 RAILS_ENV=development bin/rails server -b 0.0.0.0`
    - using foreman: `HOST=0.0.0.0 OPENPROJECT_DEV_EXTRA_HOSTS=host.docker.internal OPENPROJECT_HOST__NAME=host.docker.internal:3000 foreman start -f Procfile.dev`

  For more information
  see: [OpenProject documentation](https://www.openproject.org/docs/development/development-environment-ubuntu/)

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

It is highly recommended to regularly update the included containers.

```shell
docker compose pull
```

Now, run the containers. In order to expose a port of the `nextcloud` containers, make sure to provide
a `docker-compose.override.yml` specifying the exposed ports. In this example, we assume you simply use the content of
the provided `docker-compose.override.example.yml`, which exposes port `8080`.

```shell
cp docker-compose.override.example.yml docker-compose.override.yml
docker compose up
```

**Note:** If you've cloned the integration app anywhere other that the default `./../../custom_apps`, provide its path
in the `APP_DIR` environment variable

```shell
APP_DIR=<path-to-integration-app> docker compose up
```

After this, you should be able to access the Nextcloud server at [http://localhost:8080](http://localhost:8080).

### Setup NC server

> **Note:** These steps will only be necessary for the first setup.

#### Database

With our compose setup, there are two options for the database:

- **SQLite:** No specific configuration is necessary
- **PostgreSQL:**
  - Database: `nextcloud`
  - User: `nextcloud`
  - Password: `nextcloud`

#### Installation

The NC server installation can be done in two ways:

1. With `occ` command (CLI):
    - Using `PostgreSQL` as database type:
      ```bash
      docker compose exec --user www-data nextcloud php occ maintenance:install -vvv \
      --database pgsql \
      --database-name nextcloud \
      --database-host db \
      --database-user nextcloud \
      --database-pass nextcloud \
      --admin-user admin \
      --admin-pass admin \
      --data-dir /var/www/html/data
      ```
    - Using `SQLite` as database type:
      ```shell
      docker compose exec --user www-data nextcloud php occ maintenance:install -vvv \
      --admin-user admin \
      --admin-pass admin \
      --data-dir /var/www/html/data
      ```

2. With the `browser` (WebUI):
    1. Browse to [http://localhost:8080](http://localhost:8080)
    2. Fill `Create an admin account` form
    3. Choose the database type
    4. Click the `Install` button

#### Enable the integration app:

You can browse as admin to the apps center and enable it using the webUI, or you can just use the terminal as:

```shell
docker compose exec --user www-data nextcloud php occ a:e integration_openproject
```

#### Allow local remote servers:

```shell
docker compose exec --user www-data nextcloud php occ config:system:set allow_local_remote_servers --value 1
```

#### Configure the integration app:

- as NextCloud admin browse to Settings->Administration->OpenProject
- configure the connection to OpenProject using `http://host.docker.internal:3000` as the OpenProject URL
- in OpenProject use `http://localhost:8080` as the NextCloud URL

#### Change the setup for testing purpose
##### Strip off Authorization Bearer tokens of HTTP requests
If the bearer tokens are not forwarded to Nextcloud the authorization cannot work and that needs to be detected by OpenProject.
Easiest way to do that is to disable `mod_rewrite` in Apache:
```shell
docker compose exec nextcloud a2dismod rewrite
docker compose exec nextcloud service apache2 restart
```
To enable it again, run:
```shell
docker compose exec nextcloud a2enmod rewrite
docker compose exec nextcloud service apache2 restart
```

##### Change version of Nextcloud
To test another version of Nextcloud change the nextcloud images in the `docker-compose.override.yml`
e.g:
```yaml
services:
  nextcloud:
    image: nextcloud:23-apache
    ports:
      - "8080:80"

  cron:
    image: nextcloud:23-apache
```

Please note:
1. Only [apache based versions](https://hub.docker.com/_/nextcloud/?tab=description) will work
2. Nextcloud does not support downgrading. If you want to go back to an older version, you need to delete all the volumes with `docker compose down -v` and start the Nextcloud installation again

##### Disable pretty URLs
Nextcloud can work with or without `index.php` in the URL. The connection to OpenProject has to work regardless of how it is configured.

By default, the docker setup in this repo starts Nextcloud without `index.php` in the URL. To change that, we have to edit the `.htaccess` file. The  code that is responsible to rewrite the URLs and make them prettier is attached at the bottom of the `.htaccess` file in the Nextcloud folder.

It looks like that:
```
#### DO NOT CHANGE ANYTHING ABOVE THIS LINE ####

ErrorDocument 403 /
ErrorDocument 404 /
<IfModule mod_rewrite.c>
  Options -MultiViews
  RewriteRule ^core/js/oc.js$ index.php [PT,E=PATH_INFO:$1]
  RewriteRule ^core/preview.png$ index.php [PT,E=PATH_INFO:$1]
...
</IfModule>
```

If you haven't changed anything else in the `.htaccess` file, deleting everything below `#### DO NOT CHANGE ANYTHING ABOVE THIS LINE ####` will reset the file to a state where it does not rewrite the URLs.

You can use `sed` to do it in one command:
```shell
docker compose exec --user www-data nextcloud sed -i '/#### DO NOT CHANGE ANYTHING ABOVE THIS LINE ####/,$d' .htaccess
```

To remove `index.php` again from the URL run
```shell
docker compose exec --user www-data nextcloud php occ maintenance:update:htaccess
```

This will put the rewrite rules back into the `.htaccess` file.

##### Serve Nextcloud from a sub folder
The default docker configuration serves Nextcloud from the root of the domain (`http://localhost:8080/`), but it is also possible to serve it from a sub folder e.g. `http://localhost:8080/nextcloud/`. To do that with the existing docker configuration, there are a couple of steps to take.

1. configure apache to serve the parent folder as `DocumentRoot`:
   ```shell
   docker compose exec nextcloud sed -i 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www/' /etc/apache2/sites-enabled/000-default.conf
   ```
2. configure apache to accept settings from `.htaccess` on any level:
   ```shell
   docker compose exec nextcloud sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
   ```
3. restart apache:
   ```shell
   docker compose exec nextcloud service apache2 restart
   ```
4. delete existing Nextcloud configuration for pretty URLs as it's set to the root folder
   ```shell
   docker compose exec --user www-data nextcloud  rm config/apache-pretty-urls.config.php
   ```
5. set the sub folder as the new base for pretty urls:
   ```shell
   docker compose exec --user www-data nextcloud php occ config:system:set htaccess.RewriteBase --value '/html/'
   docker compose exec --user www-data nextcloud php occ config:system:set overwrite.cli.url --value 'http://localhost/html'
   docker compose exec --user www-data nextcloud php occ maintenance:update:htaccess
   ```

Now, you should be able to reach the Nextcloud installation at http://localhost:8080/html/

### Start Developing

Now you can watch for the app code changes using the following command and start developing.

```shell
cd $HOME/development/custom_apps/integration_openproject
npm run watch
```

## Direct upload
There's an end-point `direct-upload` available which can be used for direct-upload. There's two steps to direct upload first we need to get the `token` which is then used for direct upload.

1. **Preparation for direct upload**
   Send the `POST` request to `direct-upload-token` end-point with data `folder_id` of the destination folder.
   ```console
   curl -u USER:PASSWD http://<nextcloud_host>/index.php/apps/integration_openproject/direct-upload-token -d '{"folder_id":<folder_id>}' -H'Content-Type: application/json'
   ```
   The response from the above curl request will be
   ```json
   {
	   "token": "<token>",
	   "expires_on": <some_timestamp>
   }
   ```
   
   2. **Direct upload**
      Send multipart form data POST request to `direct-upload` end-point to upload the file with `token` acquired from preparation endpoint 
   ```console
   curl-X POST 'http://<nextcloud_host>/index.php/apps/integration_openproject/direct-upload/<token>' \
   --form 'file=@"<path-of-file>"' -H'Content-Type: multipart/form-data'
   ```
   The response from the above curl request will be
   ```json
   {
    	"file_name": "<file_name>",
    	"file_id": <file_id>
   }  
   ```      

### Release:

#### 1. Release Preparation

##### a. In case of a major/minor version

   1. Create a release branch from master with the name `release/<version>` e.g. `release/2.1`
   2. Protect that new branch the same way as `master`
   3. On the release branch update the version in `appinfo/info.xml`
   4. Do QA and fixes of bugs in the release branch

##### b. In case of a patch version

   1. On the release branch of the current minor version update the version in `appinfo/info.xml` (not needed for nightly builds)
   2. Merge the release branch into the `master` branch, to get all good changes also into the current development

#### 2. Publish Release
   1. Tag a commit on the `release/<version>` branch. The tag must have the format `v2.1.1` for releases and `v2.1.1-20220928-nightly` for nightly builds.  
      e.g: `git tag v2.0.6-20220928-nightly -m "v2.0.6-20220728-nightly"`
   3. Push the tag to the `auto-release`  branch: `git push origin release/<version>:auto-release --tags -f`
   4. Approve the deployment in GitHub actions
