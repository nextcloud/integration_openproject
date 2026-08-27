<!--
  - SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Testing Setup Scripts

Use the [dev compose setup](../../dev/) to run Nextcloud, OpenProject and other required servers for the testing.

## Cleanup Before Running Script

Reset Nextcloud and OpenProject configurations before running the script.

- Nextcloud:
  - Reset integration administration settings: `Administration settings > OpenProject`.
  - Delete `OpenProject` user, group and team folder.
- OpenProject:
  - Delete `nextcloud` file storage: `Administration > Files`.

> Run the following cleanup script:
>
> ```bash
> bash dev/cleanup_setup.sh
> ```

## Two-Way OAuth Setup Script

1. [Reset settings](#before-running-the-setup-script) before running the script.
2. Run the following command to set up integration without project folder:

   ```bash
   SETUP_PROJECT_FOLDER=false \
   NEXTCLOUD_HOST=<nextcloud_instance_url> \
   OPENPROJECT_HOST=<openproject_instance_url> \
   OP_ADMIN_USERNAME=admin \
   OP_ADMIN_PASSWORD=admin \
   NC_ADMIN_USERNAME=admin \
   NC_ADMIN_PASSWORD=admin \
   OPENPROJECT_STORAGE_NAME=Nextcloud  \
   bash integration_setup.sh
   ```

3. Check the setup is successful:
   - In OpenProject, Nextcloud file storage should be created.
   - In Nextcloud, integration settings are complete.
4. Re-run the script, check no errors occur.
5. To setup with project folder, repeat steps 1-3 including `SETUP_PROJECT_FOLDER=true` env in the script command.

## Single-Sign-On Setup Script

> **Note**: OpenProject must be an Enterprise edition to use SSO setup.

### Nextcloud Hub IDP

> Enable the following extra apps in Nextcloud:
>
> - `oidc` - OIDC Identity Provider
> - `user_oidc` - OpenID Connect user backend

1. [Reset settings](#before-running-the-setup-script) before running the script.
2. Run the following command to set up integration without project folder:

   ```bash
   NC_INTEGRATION_PROVIDER_TYPE=nextcloud_hub \
   NC_ADMIN_USERNAME=admin \
   NC_ADMIN_PASSWORD=admin \
   NC_INTEGRATION_ENABLE_NAVIGATION=false \
   NC_INTEGRATION_ENABLE_SEARCH=false \
   NC_HOST=<nextcloud_instance_url> \
   OP_ADMIN_USERNAME=admin \
   OP_ADMIN_PASSWORD=admin \
   OP_STORAGE_NAME=nextcloud \
   OP_HOST=<openproject_instance_url> \
   OP_USE_LOGIN_TOKEN=true \
   bash integration_oidc_setup.sh
   ```

3. Check the setup is successful:
   - In OpenProject, Nextcloud file storage should be created.
   - In Nextcloud, integration settings are complete.
4. Re-run the script, check no errors occur.
5. To setup with project folder, repeat steps 1-3 with `SETUP_PROJECT_FOLDER=true` env in the script command.

### External IDP

> Enable the following extra apps in Nextcloud:
>
> - `user_oidc` - OpenID Connect user backend

1. [Reset settings](#before-running-the-setup-script) before running the script.
2. Run the following command to set up integration without project folder:

   ```bash
   NC_HOST=<nextcloud_instance_url> \
   NC_ADMIN_USERNAME=admin \
   NC_ADMIN_PASSWORD=admin \
   NC_INTEGRATION_PROVIDER_TYPE=external \
   NC_INTEGRATION_PROVIDER_NAME=keycloak \
   NC_INTEGRATION_OP_CLIENT_ID=openproject \
   NC_INTEGRATION_TOKEN_EXCHANGE=false \
   NC_INTEGRATION_ENABLE_NAVIGATION=false \
   NC_INTEGRATION_ENABLE_SEARCH=false \
   OP_HOST=<openproject_instance_url> \
   OP_ADMIN_USERNAME=admin \
   OP_ADMIN_PASSWORD=admin \
   OP_STORAGE_NAME=nextcloud \
   OP_USE_LOGIN_TOKEN=true \
   bash integration_oidc_setup.sh
   ```

3. Check the setup is successful:
   - In OpenProject, Nextcloud file storage should be created.
   - In Nextcloud, integration settings are complete.
4. Re-run the script, check no errors occur.
5. To setup with project folder, repeat steps 1-3 with `SETUP_PROJECT_FOLDER=true` env in the script command.

### External IDP With Token Exchange

1. [Reset settings](#before-running-the-setup-script) before running the script.
2. Run the following command to set up integration without project folder:

   ```bash
   NC_HOST=<nextcloud_instance_url> \
   NC_ADMIN_USERNAME=admin \
   NC_ADMIN_PASSWORD=admin \
   NC_INTEGRATION_PROVIDER_TYPE=external \
   NC_INTEGRATION_PROVIDER_NAME=keycloak \
   NC_INTEGRATION_OP_CLIENT_ID=openproject \
   NC_INTEGRATION_TOKEN_EXCHANGE=true \
   NC_INTEGRATION_ENABLE_NAVIGATION=false \
   NC_INTEGRATION_ENABLE_SEARCH=false \
   OP_HOST=<openproject_instance_url> \
   OP_ADMIN_USERNAME=admin \
   OP_ADMIN_PASSWORD=admin \
   OP_STORAGE_NAME=nextcloud \
   OP_STORAGE_AUDIENCE=nextcloud \
   OP_STORAGE_SCOPE=<scope_for_token_exchange> \
   bash integration_oidc_setup.sh
   ```

3. Check the setup is successful:
   - In OpenProject, Nextcloud file storage should be created.
   - In Nextcloud, integration settings are complete.
4. Re-run the script, check no errors occur.
5. To setup with project folder, repeat steps 1-3 with `SETUP_PROJECT_FOLDER=true` env in the script command.

### Update Existing Setup

1. Set up the integration in Nextcloud and OpenProject with two-way OAuth method.
2. Run the following command:

   ```bash
   NC_INTEGRATION_PROVIDER_TYPE=nextcloud_hub \
   NC_ADMIN_USERNAME=admin \
   NC_ADMIN_PASSWORD=admin \
   NC_INTEGRATION_ENABLE_NAVIGATION=false \
   NC_INTEGRATION_ENABLE_SEARCH=false \
   NC_HOST=<nextcloud_instance_url> \
   OP_ADMIN_USERNAME=admin \
   OP_ADMIN_PASSWORD=admin \
   OP_STORAGE_NAME=nextcloud \
   OP_HOST=<openproject_instance_url> \
   OP_USE_LOGIN_TOKEN=true \
   SETUP_PROJECT_FOLDER=true \
   bash integration_oidc_setup.sh
   ```

3. Check the update is successful:
   - In OpenProject, Nextcloud file storage is configured with SSO.
   - In Nextcloud, integration settings are updated with SSO.
