## Setting up the integration with shell script

`integration_setup.sh` sets up the whole integration with just one command.

Prerequisites needed for using the shell script.
1. "OpenProject" is set up and running
2. "Nextcloud" is set up and running
3. The "OpenProject Integration" app is installed and enabled in Nextcloud.
4. The credentials of the OpenProject global user are known
5. In "Nextcloud" we already have set up an admin with credentials.

Once all the above pre-conditions are met we can run the shell script to integrate with the following command.

> Note:
> - We can set the whole integration with or without `project folders` using this script with an environment variable `SETUP_PROJECT_FOLDER`
> - `SETUP_PROJECT_FOLDER=true` will set the integration with `project folders` and vice-versa

Also, the following bash command has an environment variable `OPENPROJECT_STORAGE_NAME` which will be the storage name to store the oauth information in Open Project required for integration.

Below is an example of a command to run the script to set up the integration with `project folders`
```bash
SETUP_PROJECT_FOLDER=true \
NEXTCLOUD_HOST=<nextcloud_host_url> \                      
OPENPROJECT_HOST=<openproject_host_url> \
OP_ADMIN_USERNAME=<openproject_global_admin_uername> OP_ADMIN_PASSWORD=<openproject_global_admin_password> \
NC_ADMIN_USERNAME=<nextcloud_admin_username> NC_ADMIN_PASSWORD=<nextcloud_admin_password> \                                                              
OPENPROJECT_STORAGE_NAME=<files_storage_name> \                          
bash integration_setup.sh
```

> Note: these credentials are only used by the script to do the setup. They are not stored/remembered.
