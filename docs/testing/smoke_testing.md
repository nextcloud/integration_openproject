<!--
  - SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Smoke Testing Docs for `integration_openproject`

This file consists of some smoke testing to be done before the release (major and minor) of `integration_application` application.
The need for this smoke testing (manual) is that we do not have e2e test setup to automate that involves both `OpenProject` and `Nextcloud`.

**Table of Content:**

- [Section A: Two-Way OAuth 2.0 Authorization Code Flow](#section-a-two-way-oauth-20-authorization-code-flow)
- [Section B: Single-Sign-On through OpenID Connect Identity Provider](#section-b-single-sign-on-through-openid-connect-identity-provider)
  - [B.1: Nextcloud Hub as IDP](#B1-nextcloud-hub-as-idp)
  - [B.2: External Provider](#B2-External-Provider)

- [Common Smoke Test Steps](#common-smoke-test-steps)
  - [1. Link/Unlink a work package for a file/folder in Nextcloud](#1-linkunlink-a-work-package-for-a-filefolder-in-nextcloud)  
  - [2. Link/Unlink a work package for a file/folder from OpenProject](#2-linkunlink-a-work-package-for-a-filefolder-from-openproject)  
  - [3. Direct upload file/folder from OpenProject to Nextcloud](#3-direct-upload-filefolder-from-openproject-to-nextcloud)  
  - [4. Create a WorkPackage from Nextcloud](#4-create-a-workpackage-from-nextcloud)  
  - [5. Check notification in `OpenProject` widget in Nextcloud](#5-check-notification-in-openproject-widget-in-nextcloud)  
  - [6. Check New folder with automatically managed permissions in OpenProject](#6-check-new-folder-with-automatically-managed-permissions-in-openproject)

- [App Upgrade Testing](#app-upgrade-testing)
  - [Upgrade Steps](#upgrade-steps)
  - [For OAuth 2.0 Setup](#for-oauth-20-setup)
  - [For OIDC Setup](#for-oidc-setup)
    - [Nextcloud Hub as IDP](#nextcloud-hub-as-idp)
    - [External Provider (Keycloak)](#external-provider-keycloak)
      - [Token Exchange Disabled](#token-exchange-disabled)
      - [Token Exchange Enabled](#token-exchange-enabled)  

## Section A: Two-Way OAuth 2.0 Authorization Code Flow

### A1. Oauth configuration
- [ ] In `OpenProject`, navigate to `Administration > Files`.
- [ ] Create a file storage type `Nextcloud` and name it as `Nextcloud` in `OpenProject`.
- [ ] In admin setting of `Nextcloud`, navigate to `Administration Settings > OpenProject`.
- [ ] Copy `OpenProject` Oauth Credential (client_id and client_secret) and save them in `Nextcloud`.
- [ ] Copy `Nextcloud` Oauth Credential (client_id and client_secret) and save them in `OpenProject`.
- [ ] Disable project folder (automatically managed folder).

### A2. Connect Nextcloud with OpenProject

- [ ] Complete step [Test No A1](#a1-oauth-configuration).
- [ ] In `Nextcloud`, navigate to `Personal Settings > Openproject` and click on `Connect to OpenProject` button.
- [ ] `Nextcloud` admin should be connected as an `OpenProject` admin.
- [ ] Also, create a user in both `Nextcloud` as well as `OpenProject`.
- [ ] From the personal section of the created user in `Nextcloud`, connect to `OpenProject`.
- [ ] `Nextcloud` user should be connected as an `OpenProject` user.

### A3. Add File storage (Nextcloud) to an OpenProject project
- [ ] Complete step [Test No A1](#a1-oauth-configuration).
- [ ] Select an `OpenProject` Project (for example, `Demo Project`) in `OpenProject`.
- [ ] Navigate to `Project settings > Files` of `Demo Project`.
- [ ] Add a file storage name `Nextcloud`( choose `No specific Folder` option ) for `Demo Project`.

### A4. Connect OpenProject with Nextcloud
- [ ] Complete step [Test No A1](#a1-oauth-configuration).
- [ ] Complete step [Test No A3](#A3-Add-File-storage-Nextcloud-to-an-OpenProject-project).
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, and login to `Nextcloud`.
- [ ] `OpenProject` admin is connected to `Nextcloud` as a `Nextcloud` admin.
- [ ] Also, create a user in both `Nextcloud` as well as `OpenProject`.
- [ ] Add the created `OpenProject` user as the member of `Demo Project` project (admin can add members to a project).
- [ ] Try to connect the created `OpenProject` user as created `Nextcloud` user.
- [ ] `OpenProject` user should be connected as a `Nextcloud` user.

### A5. Setup and check project folder in Nextcloud (with project folder setup)
- [ ] Complete step [Test No A1](#a1-oauth-configuration).
- [ ] Enable `groupfolders` application in `Nextcloud`.
- [ ] Enable `Automatically managed folders` switch in admin setting and set project folder.
- [ ] Application password should be generated.
- [ ] Verify that `OpenProject` user and group are created with user `OpenProject` as sub-admin of the group.
- [ ] Verify that `OpenProjectNoAutomaticProjectFolders` group is also created with user `OpenProject` as sub-admin.
- [ ] Try deleting `OpenProject` user and group, those should not be deleted.
- [ ] Try deleting `OpenProjectNoAutomaticProjectFolders` group, it should not be possible to delete.
- [ ] Test group management (as user `OpenProject`):
  - Login as `OpenProject` user
  - Add a test user `user1` to the `OpenProject` group
  - Remove `user1` from the `OpenProject` group
  - Verify that `user1` is automatically moved to the `OpenProjectNoAutomaticProjectFolders` group

### A6. Complete the common smoke tests
- [ ] Complete [smoke tests 1-6](#common-smoke-test-steps).

### A7. Check the integration script for oauth set up

> Before Running the script make sure that your `Nextcloud` and `OpenProject` instance is up and running

- [ ] Run the `integration_setup.sh` script to set up integration without project folder with the following command:
```bash
SETUP_PROJECT_FOLDER=true \
NEXTCLOUD_HOST=<nextcloud_instance_url> \
OPENPROJECT_HOST=<openproject_instance_url> \
OP_ADMIN_USERNAME=admin \
OP_ADMIN_PASSWORD=admin \                                                                                                             
NC_ADMIN_USERNAME=admin \                                                                                     
NC_ADMIN_PASSWORD=admin \                                                             
OPENPROJECT_STORAGE_NAME=Nextcloud  \                         
bash integration_setup.sh
```
- [ ] Upon success, try step [Test No A2](#A2-Connect-Nextcloud-with-OpenProject) (Skip first check).
- [ ] Upon success, try step [Test No A4](#a4-connect-openproject-with-nextcloud) (Skip first check).
- [ ] Also, to set up the integration configuration with project folder setup, just set environment `SETUP_PROJECT_FOLDER=true` and run the script.
- [ ] Re-run the script again after it is already setup (Should not give any error).

## Section B: Single-Sign-On through OpenID Connect Identity Provider

### B.1: Nextcloud Hub as IDP

#### B.1.1. Configure Nextcloud
- [ ] In Nextcloud, install and enable `oidc` and `user_oidc` apps.
- [ ] Create a new user( with username, display name, password, and email)
- [ ] Run following command:
  - `php occ config:system:set user_oidc --type boolean --value="true" oidc_provider_bearer_validation`
- [ ]  Go to `Administration > OpenID Connect` and enable `store login tokens` option.
- [ ] Go to `Administation > Security`
- [ ] Add OIDC client ("OpenID Connect clients" section):
  - Add a client name (not an identifier)
  - Add a redirect URL (<openproject_host>/auth/oidc-<idp-displayname-from-OP>/callback)
  - After adding, choose `Access Token Type` as `JWT Access Token (RFC9068)`.
  - Set `Refresh Token Expire Time` to `Never`
  - Save
  - Copy the Client ID and Client secret (you will need these later in OpenProject and integration_openproject)

####  B.1.2. Add Nextcloud IDP in OpenProject
- [ ] In OpenProject, go to `Administration > Authentication > OpenID providers`
- [ ] Add a new custom OpenID provider:
  - Display name: `nextcloud` (use this name as redirect URL in Nextcloud: <idp-displayname-from-OP>)
  - Discovery URL: `<nextcloud-host>/index.php/.well-known/openid-configuration`
  - Client ID: Client ID copied earlier from Nextcloud
  - Client secret: Client secret copied earlier from Nextcloud
- [ ] Go to `Administration > Files`
- [ ] Select the file storage type called Nextcloud (created earlier in previous test)
- [ ] Under `OAuth configuration`, select `Use access token obtained during user log in`

#### B.1.3. Setup integration (Without project folder setup)
- [ ] Complete step [Test No B.1.1](#B11-Configure-Nextcloud).
- [ ] Complete step [Test No B.1.2](#B12-Add-Nextcloud-Idp-in-OpenProject).
- [ ] In nextcloud, go to `Administration > OpenProject`.
- [ ] Under `Authentication Method`, select `Single-Sign-On through OpenID Connect Identity Provider`.
- [ ] In `Authentication settings`, select `provider Type` as `Nextcloud Hub`.
- [ ] Set Openproject `client ID` by Client ID copied earlier in **Test No B1**.

#### B.1.4. Login to OpenProject using Nextcloud user
- [ ] Complete step [Test No B.1.1](#B11-Configure-Nextcloud)
- [ ] Complete step [Test No B.1.2](#B12-Add-Nextcloud-Idp-in-OpenProject).
- [ ] Complete step [Test No B.1.3](#b13-Setup-integration-Without-project-folder-setup).
- [ ] In `nextcloud`, login as nextcloud-created user.
- [ ] In `openproject`, use the SSO button on the login page to sign in as the nextcloud-created user.
- [ ] Login should be successful in `openproject`.
- [ ] Login should be successful in `openproject` with username having created-nextcloud user's display name.
- [ ] The OpenProject username must match the Nextcloud-created user’s name.

#### B.1.5. Verify Connection of Nextcloud user with OpenProject
- [ ] Complete step [Test No B.1.1](#B11-Configure-Nextcloud).
- [ ] Complete step [Test No B.1.2](#B12-Add-Nextcloud-Idp-in-OpenProject).
- [ ] Complete step [Test No B.1.3](#b13-Setup-integration-Without-project-folder-setup).
- [ ] Complete step [Test No B.1.4](#b14-Login-to-OpenProject-using-Nextcloud-user).
- [ ] In nextcloud, login as nextcloud-created user.
- [ ] Navigate to `Settings > Openproject`
- [ ] Should show user is connected as an OpenProject user.

#### B.1.6. Complete the common smoke tests
- [ ] Complete [smoke tests 1-6](#common-smoke-test-steps).

### B.2: External Provider
> Here, keycloak is an External Provider

#### B.2.1. Configure Keycloak
- [ ] Set up Keycloak using this guide: [Keycloak Setup](https://www.openproject-edge.com/docs/system-admin-guide/integrations/nextcloud/oidc-sso/#keycloak)

#### B.2.2. Configure Nextcloud
- [ ] In **nextcloud**, go to `Administration > OpenID Connect`.
- [ ] Enable `store login tokens` options.
- [ ] Register a new providers with following data:
  - Identifier: `keycloak`
  - Client ID: nextcloud client id from keycloak
  - Client Secret: nextcloud client secret from keycloak
  - Discovery endpoint: `<keycloak-hosts>/realms/<realm-name>/.well-known/openid-configuration`
  - Scope: `openid email profile api_v3`
  - submit
- [ ] Login as keycloak-created user in `Nextcloud`.
- [ ] Login Should be successful
- [ ] Logout

#### B.2.3. Add Keycloak IDP in OpenProject
- [ ] In **OpenProject**, go to `Administration > Authentication > OpenID providers`
- [ ] Add a new custom OpenID provider:
  - Display name: `keycloak`
  - Discovery URL: `<keycloak-host>/realms/<realm-name>/.well-known/openid-configuration`
  - Client ID: Client ID of openproject from keycloak
  - Client secret: Client secret of openproject from keycloak
- [ ] Login as keycloak-created user in `Openproject`.
- [ ] Log out, then Login as admin in `Openproject`.
- [ ] As an `OpenProject` admin, add keycloak-created user as a member in one of the project.

#### B.2.4. Setup integration (token exchange disabled)
- [ ] In nextcloud, go to `Administration > OpenProject`.
- [ ] Under `Authentication Method`, select `Single-Sign-On through OpenID Connect Identity Provider`.
- [ ] In `Authentication settings`, select `provider Type` as `Keycloak`.
- [ ] Disable `token exchange`

#### B.2.5. Verify Connection in nextcloud
- [ ] Complete step [Test No B.2.1](#b21-Configure-Keycloak).
- [ ] Complete step [Test No B.2.2](#b22-Configure-Nextcloud).
- [ ] Complete step [Test No B.2.3](#b23-Add-Keycloak-IDP-in-OpenProject).
- [ ] Complete step [Test No B.2.4](#b24-Setup-integration-token-exchange-disabled).
- [ ] In nextcloud, login as keycloak-created user.
- [ ] Navigate to `Settings > Openproject`
- [ ] Should show user is connected as an OpenProject user.

#### B.2.6. Complete the common smoke tests
- [ ] Complete [smoke tests 1-6](#common-smoke-test-steps).

#### B.2.7.Setup integration (token exchange enabled)
- [ ] Complete step [Test No B.2.1](#b21-Configure-Keycloak).
- [ ] Complete step [Test No B.2.2](#b22-Configure-Nextcloud).
- [ ] Complete step [Test No B.2.3](#b23-Add-Keycloak-IDP-in-OpenProject).
- [ ] Go to `Administration > OpenProject` in nextcloud
- [ ] Under `Authentication Method`, select `Single-Sign-On through OpenID Connect Identity Provider`
- [ ] In `Authentication settings`, select `provider Type` as `Keycloak`
- [ ] Enable `token exchange`
- [ ] Set `OpenProject client ID *` as `Openproject`

#### B.2.8. Verify Connection in nextcloud
- [ ] Complete step [Test No B.2.1](#b21-Configure-Keycloak).
- [ ] Complete step [Test No B.2.2](#b22-Configure-Nextcloud).
- [ ] Complete step [Test No B.2.3](#b23-Add-Keycloak-IDP-in-OpenProject).
- [ ] Complete step [Test No B.2.5](#b25-Verify-Connection-in-nextcloud).
- [ ] In nextcloud, login as keycloak-created user.
- [ ] Navigate to `settings > Openproject`
- [ ] Should show user is connected as an OpenProject user.

#### B.2.9. Complete the common smoke tests
- [ ] Complete [smoke tests 1-6](#common-smoke-test-steps).

### Check the integration script for sso setup (Nextcloud Hub)

> Before Running the script make sure that your `Nextcloud` and `OpenProject` instance is up and running
> If you're using Nextcloud as the Identity Provider (OIDC), make sure the following apps are installed and enabled in Nextcloud:
>   - oidc
>   - integration_openproject
>
> If you are using a custom Identity Provider, ensure that:
>   - user_oidc app is installed and enabled in Nextcloud.
>   - The custom IdP is properly configured and accessible.
>
> To add the Nextcloud storage, delete the 'nextcloud' file storage from OpenProject, reset the Nextcloud config, and run the script again.

- [ ] Complete step [Test No B.1.1](#B11-Configure-Nextcloud) (only the first , second and thrid steps are required).
- [ ] Complete step [Test No B.1.2](#B12-Add-Nextcloud-Idp-in-OpenProject) (only the first and second steps are required).
- [ ] Run the `integration_oidc_setup.sh` script to set up integration without project folder with the following command:

```bash
NC_INTEGRATION_PROVIDER_TYPE=nextcloud_hub \
NC_ADMIN_USERNAME=admin \
NC_ADMIN_PASSWORD=admin \
NC_INTEGRATION_ENABLE_NAVIGATION=false \
NC_INTEGRATION_ENABLE_SEARCH=false \
NC_HOST=https://nextcloud.local \
OP_ADMIN_USERNAME=admin \
OP_ADMIN_PASSWORD=admin \
OP_STORAGE_NAME=nextcloud \
OP_HOST=https://openproject.local \
OP_USE_LOGIN_TOKEN=true \
bash integration_oidc_setup.sh
```

- [ ] Upon success, try step [Test No B.1.4](#b14-Login-to-OpenProject-using-Nextcloud-user).
- [ ] Upon success, try step [Test No B.1.5](#b15-Verify-Connection-of-Nextcloud-user-with-OpenProject).
- [ ] Also, to set up the integration configuration with project folder setup, at first delete 'nextcloud' file storage from OpenProject.
- [ ] In nextcloud, delete the `OpenProject` user, group and team folder from the nextcloud (if they exist).
- [ ] Then, reset the Nextcloud config.
- [ ] set environment `SETUP_PROJECT_FOLDER=true` and run the script.
- [ ] Run the script again after it is already setup (Should not give any error).

### Check the integration script for sso setup (External provider  without token exchange)
> Before running the script make sure that you delete the 'nextcloud' file storage from OpenProject and reset the integration settings in Nextcloud.

- [ ] Complete step [Test No B.2.1](#b21-Configure-Keycloak).
- [ ] Complete step [Test No B.2.2](#b22-Configure-Nextcloud).
- [ ] Complete step [Test No B.2.3](#b23-Add-Keycloak-IDP-in-OpenProject).
- [ ] Run the `integration_oidc_setup.sh` script to set up integration without project folder with the following command:

```bash
NC_HOST=https://nextcloud.local \
NC_ADMIN_USERNAME=admin \
NC_ADMIN_PASSWORD=admin \
NC_INTEGRATION_PROVIDER_TYPE=external \
NC_INTEGRATION_PROVIDER_NAME=keycloak \
NC_INTEGRATION_OP_CLIENT_ID=openproject \
NC_INTEGRATION_TOKEN_EXCHANGE=false \
NC_INTEGRATION_ENABLE_NAVIGATION=false \
NC_INTEGRATION_ENABLE_SEARCH=false \
OP_HOST=https://openproject.local \
OP_ADMIN_USERNAME=admin \
OP_ADMIN_PASSWORD=admin \
OP_STORAGE_NAME=nextcloud \
OP_USE_LOGIN_TOKEN=true \
bash integration_oidc_setup.sh

```

- [ ] Upon success, try step [Test No B.2.5](#B25-Verify-Connection-in-nextcloud).
- [ ] Also, to set up the integration configuration with project folder setup, at first delete 'nextcloud' file storage from OpenProject.
- [ ] In nextcloud, delete the `OpenProject` user, group and team folder from the nextcloud (if they exist).
- [ ] Then, reset the Nextcloud config.
- [ ] set environment `SETUP_PROJECT_FOLDER=true` and run the script.
- [ ] Run the script again after it is already setup (Should not give any error).


### Check the integration script for sso setup (External provider  with token exchange)
> Before Running the below script make sure that you delete the 'nextcloud' file storage from OpenProject and reset the Nextcloud config.

- [ ] Complete step [Test No B.2.1](#b21-Configure-Keycloak).
- [ ] Complete step [Test No B.2.2](#b22-Configure-Nextcloud).
- [ ] Complete step [Test No B.2.3](#b23-Add-Keycloak-IDP-in-OpenProject).
- [ ] Run the `integration_oidc_setup.sh` script to set up integration without project folder with the following command:

```bash
NC_HOST=https://nextcloud.local \
NC_ADMIN_USERNAME=admin \
NC_ADMIN_PASSWORD=admin \
NC_INTEGRATION_PROVIDER_TYPE=external \
NC_INTEGRATION_PROVIDER_NAME=keycloak \
NC_INTEGRATION_OP_CLIENT_ID=openproject \
NC_INTEGRATION_TOKEN_EXCHANGE=true \
NC_INTEGRATION_ENABLE_NAVIGATION=false \
NC_INTEGRATION_ENABLE_SEARCH=false \
OP_HOST=https://openproject.local \
OP_ADMIN_USERNAME=admin \
OP_ADMIN_PASSWORD=admin \
OP_STORAGE_NAME=nextcloud \
OP_STORAGE_AUDIENCE=nextcloud \
bash integration_oidc_setup.sh
```

- [ ] Upon success, try step [Test No B.2.8](#B28-Verify-Connection-in-nextcloud).
- [ ] Also, to set up the integration configuration with project folder setup, at first delete 'nextcloud' file storage from OpenProject.
- [ ] In nextcloud, delete the `OpenProject` user, group and team folder from the nextcloud (if they exist).
- [ ] Then, reset the Nextcloud config.
- [ ] set environment `SETUP_PROJECT_FOLDER=true` and run the script.
- [ ] Run the script again after it is already setup (Should not give any error).

## Common Smoke Test Steps

### 1. Link/Unlink a work package for a file/folder in Nextcloud
- [ ] In openproject, Select a file, navigate to sidebar `OpenProject` tab.
- [ ] Search for any of the work packages in the `Demo Project`.
- [ ] Work packages are listed.
- [ ] Link to any one of the work packages appeared in the search lists.
- [ ] Linked work package appears in the `OpenProject` Tab with a successful message.
- [ ] Also, try linking other work packages, reload the browser and all the linked ones should appear in the `OpenProject` Tab.
- [ ] Hover to a work package to be unlinked, unlink button is visible.
- [ ] Unlink a work package and it should be deleted from the `OpenProject` Tab with a successful message.

### 2. Link/Unlink a work package for a file/folder from OpenProject
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, click `link existing files`, select available files (for example, welcome.txt) from Nextcloud and link it to the work package.
- [ ] Selected file is linked to the work package in `OpenProject`
- [ ] Also Navigate to nextcloud and see in the `OpenProject` tab for file (welcome.txt), the work package should be linked.

### 3. Direct upload file/folder from OpenProject to Nextcloud
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, click `Upload files`, select available files from your local system (for example, local.txt) and upload choosing the upload location.
- [ ] Uploaded file is linked to the work package in `OpenProject`
- [ ] Also Navigate to `Nextcloud` and see in the `OpenProject` tab for file (local.txt), the work package should be linked.

### 4. Create a WorkPackage from Nextcloud
- [ ] Open the form to create work package from Nextcloud in the `OpenProject` tab for a file/folder.
- [ ] Select `Demo Project`, fill up the modal form and create.
- [ ] Work package should be created and linked to the selected file.

### 5. Check notification in `OpenProject` widget in Nextcloud
> Make sure your `OpenProject` is running along with `worker` instance
- [ ] Create a separate user in both `Nextcloud` as well as `OpenProject`.
- [ ] Connect `Nextcloud` user to `OpenProject` user and vice-versa (`OpenProject` user to `Nextcloud` user).
- [ ] Now as an `OpenProject` admin, assign any of the `Demo Project` work packages to the created `OpenProject` user.
- [ ] The `Nextcloud` user should receive a notification regarding the assignment.

### 6. Check New folder with automatically managed permissions in OpenProject
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, click `link existing files`.
- [ ] In a modal, `Nextcloud > OpenProject > Demo project(1)` should be visible.
- [ ] Also Navigate to `Nextcloud` and in Files `OpenProject > Demo project(1)` folder is created.
- [ ] Try to delete `OpenProject` or `OpenProject > Demo project(1)`. They should not be deleted.

## App Upgrade Testing

### Upgrade Steps

- [ ] **Check update is available**: `php occ app:update --showonly integration_openproject`
- [ ] **Run upgrade**: `php occ app:update --allow-unstable integration_openproject`
- [ ] **Verify upgrade**: Confirm no errors and version updated

> **Important**: When upgrading from old versions, the upgrade might fail with "Undefined constant" error due to a known cache issue in Nextcloud. To fix this, please run the following commands:
>
> ```bash
> php occ upgrade
> php occ maintenance:mode --off
> ```

### Upgrade Test Cases
#### Existing OAuth 2.0 Setup

- [ ] **Before upgrade**: Perform complete setup with OAuth2 method (Project folder enabled)
- [ ] Perform [Upgrade steps](#upgrade-steps)
- [ ] **After upgrade**: Check that the integration setup and other changes are preserved

#### Existing SSO Setup

##### Nextcloud Hub as IDP

- [ ] **Before upgrade**: Perform complete setup with sso method (Nextcloud Hub as IDP, Project folder enabled)
- [ ] Perform [Upgrade steps](#upgrade-steps)
- [ ] **After upgrade**: Check that the integration setup and other changes are preserved

#### External Provider (Keycloak)

##### Token Exchange Disabled

- [ ] **Before upgrade**: Perform complete setup with sso method (Keycloak as IDP, Token exchange disable, Project folder enabled)
- [ ] Perform [Upgrade steps](#upgrade-steps)
- [ ] **After upgrade**: Check that the integration setup and other changes are preserved

##### Token Exchange Enabled

- [ ] **Before upgrade**: Perform complete setup with sso method (Keycloak as IDP, Token exchange enable, Project folder enabled)
- [ ] Perform [Upgrade steps](#upgrade-steps)
- [ ] **After upgrade**: Check that the integration setup and other changes are preserved
