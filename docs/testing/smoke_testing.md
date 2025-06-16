<!--
  - SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Smoke Testing Docs for `integration_openproject`

This file consists of some smoke testing to be done before the release (major and minor) of `integration_application` application.
The need for this smoke testing (manual) is that we do not have e2e test setup to automate that involves both `OpenProject` and `Nextcloud`.

This document covers smoke tests for two authentication methods used in the `integration_openproject` apps:

**Section A: OAuth 2.0 (Two-Way Authorization Code Flow)**

**Section B: Single Sign-On (SSO) via OpenID Connect**
  - **Nextcloud Hub** as the IdP  
  - **External Provider** as the IdP (with token exchange enable/disable)

# Smoke Testing Coverage (In Nextcloud and OpenProject)

## Common Smoke Test Steps (Applies to Both Authentication Methods)

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

## Section A: OAuth 2.0 Authentication Tests

### A1. Oauth configuration (without project folder setup/Automatically managed folders)
- [ ] In `OpenProject`, navigate to `Administration > Files`.
- [ ] Create a file storage type `Nextcloud` and name it as `Nextcloud` in `OpenProject`.
- [ ] In admin setting of `Nextcloud`, navigate to `Administration Settings > OpenProject`.
- [ ] Copy `OpenProject` Oauth Credential (client_id and client_secret) and save them in `Nextcloud`.
- [ ] Copy `Nextcloud` Oauth Credential (client_id and client_secret) and save them in `OpenProject`.

### A2. Connect Nextcloud with OpenProject (Without project folder setup)

- [ ] Complete Smoke **Test No A1**.
- [ ] In `Nextcloud`, navigate to `Personal Settings > Openproject` and click on `Connect to OpenProject` button.
- [ ] `Nextcloud` admin should be connected as an `OpenProject` admin.
- [ ] Also, create a user in both `Nextcloud` as well as `OpenProject`.
- [ ] From the personal section of the created user in `Nextcloud`, connect to `OpenProject`.
- [ ] `Nextcloud` user should be connected as an `OpenProject` user.

### A3. Add File storage (Nextcloud) to an OpenProject project
- [ ] Complete Smoke **Test No A1**.
- [ ] Select an `OpenProject` Project (for example, `Demo Project`) in `OpenProject`.
- [ ] Navigate to `Project settings > Files` of `Demo Project`.
- [ ] Add a file storage name `Nextcloud`( choose `No specific Folder` option ) for `Demo Project`.

### A4. Connect OpenProject with Nextcloud
- [ ] Complete Smoke **Test No A1**.
- [ ] Complete Smoke **Test No A3**.
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, and login to `Nextcloud`.
- [ ] `OpenProject` admin is connected to `Nextcloud` as a `Nextcloud` admin.
- [ ] Also, create a user in both `Nextcloud` as well as `OpenProject`.
- [ ] Add the created `OpenProject` user as the member of `Demo Project` project (admin can add members to a project).
- [ ] Try to connect the created `OpenProject` user as created `Nextcloud` user.
- [ ] `OpenProject` user should be connected as a `Nextcloud` user.

### A5. Setup and check project folder in Nextcloud (with project folder setup)
- [ ] Complete Smoke **Test No A1**.
- [ ] Enable `groupfolders` application in `Nextcloud`.
- [ ] Enable `Automatically managed folders` switch in admin setting and set project folder.
- [ ] Application password should be generated.
- [ ] `OpenProject` user and group are created such that user `OpenProject` is admin of the group.
- [ ] Try deleting `OpenProject` user and group, those should not be deleted.

### A6. Link/Unlink a work package for a file/folder in Nextcloud
- [ ] Complete Smoke **Test No A1**.
- [ ] Complete Smoke **Test No A2**.
- [ ] Complete Smoke **Test No A3**.
- [ ] Perform common Smoke **Test No 1**

### A7. Link/Unlink a work package for a file/folder from OpenProject
- [ ] Complete Smoke **Test No A1**.
- [ ] Complete Smoke **Test No A3**.
- [ ] Complete Smoke **Test No A4**.
- [ ] Perform common Smoke **Test No 2**. 

### A8. Direct upload file/folder from OpenProject to Nextcloud
- [ ] Complete Smoke **Test No A1**.
- [ ] Complete Smoke **Test No A3**.
- [ ] Complete Smoke **Test No A4**.
- [ ] Perform common Smoke **Test No 3**.

### A9. Create a WorkPackage from Nextcloud
- [ ] Complete Smoke **Test No A1**.
- [ ] Complete Smoke **Test No A2**.
- [ ] Complete Smoke **Test No A3**.
- [ ] Perform common Smoke **Test No 4**.

### A10. Check notification in `OpenProject` widget in Nextcloud
> Make sure your `OpenProject` is running along with `worker` instance
- [ ] Complete Smoke **Test No A1**.
- [ ] Complete Smoke **Test No A2**.
- [ ] Complete Smoke **Test No A3**.
- [ ] Complete Smoke **Test No A4**.
- [ ] Perform common Smoke **Test No 5**.

### A11. Check New folder with automatically managed permissions in OpenProject
- [ ] Complete Smoke **Test No A1**.
- [ ] Complete Smoke **Test No A3** (Make sure to choose `New folder with automatically managed permissions` while creating `File storage`).
- [ ] Complete Smoke **Test No A4**.
- [ ] Perform common Smoke **Test No 6**.

### A12. Check the integration script

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
- [ ] Upon success, try Smoke **Test No A2** (Skip first check).
- [ ] Upon success, try Smoke **Test No A4** (Skip first check).
- [ ] Also, to set up the integration configuration with project folder setup, just set environment `SETUP_PROJECT_FOLDER=true` and run the script.
- [ ] Re-run the script again after it is already setup (Should not give any error).

## Section B: SSO via OpenID Connect (Nextcloud as Identity Provider)

### B1. Configure Nextcloud (IdP Setup)
- [ ] In Nextcloud, enable `oidc` apps.
- [ ] Go to `Administation > Security`
- [ ] Under "OpenID Connect clients" section:
  - Add a client name (not an identifier)
  - Add a redirect URL (<openproject_host>/auth/oidc-<idp-displayname-from-OP>/callback)
  - After adding, choose `Access Token Type` as `JWT Access Token (RFC9068)`.
  - Set `Refresh Token Expire Time` to `Never`
  - Save
  - Copy the Client ID and Client secret (you will need these later in OpenProject and integration_openproject)
- [ ] Create a new user( with username, display name, password, and email)
- [ ] Install and enable `user_oidc` apps
- [ ] Run following command:
  - php occ config:system:set user_oidc --type boolean --value="true" oidc_provider_bearer_validation

###  B2. Configure OpenProject (Client)
- [ ] In OpenProject, go to `Administration > Authentication > OpenID providers`
- [ ] Add a new custom OpenID provider:
  - Display name: `nextcloud` (use this name as redirect URL in Nextcloud: <idp-displayname-from-OP>)
  - Discovery URL: `<nextcloud-host>/index.php/.well-known/openid-configuration`
  - Client ID: Client ID copied earlier from Nextcloud
  - Client secret: Client secret copied earlier from Nextcloud
- [ ] Go to `Administration > Files`
- [ ] Select the file storage type called Nextcloud (created earlier in previous test)
- [ ] Under `OAuth configuration`, select `Use access token obtained during user log in`

### B3. Connect Nextcloud with OpenProject (Without project folder setup)
- [ ] Complete Smoke **Test No B1 and B2**.
- [ ] In nextcloud, go to `Administration > OpenProject`.
- [ ] Under `Authentication Method`, select `Single-Sign-On through OpenID Connect Identity Provider`.
- [ ] In `Authentication settings`, select `provider Type` as `Nextcloud Hub`.
- [ ] Set Openproject `client ID` by Client ID copied earlier in **Test No B1**.
- [ ] Then click on `complete the set-up`.

### B4. Use SSO in OpenProject for login
- [ ] Complete Smoke **Test No B3**.
- [ ] In `nextcloud`, login as nextcloud-created user.
- [ ] In `openproject`, use the SSO button on the login page to sign in as the nextcloud-created user.
- [ ] Login should be successful in `openproject`.
- [ ] Login should be successful in `openproject` with username having created-nextcloud user's display name.
- [ ] The OpenProject username must match the Nextcloud-created user’s name.

### B5. Verify Connection of Nextcloud user with OpenProject
- [ ] Complete Smoke **Test No B4**.
- [ ] In nextcloud, login as nextcloud-created user.
- [ ] Navigate to `settings > Openproject`
- [ ] Should show user is connected as an OpenProject user.

### B6. Link/Unlink a work package for a file/folder in Nextcloud
- [ ] Complete Smoke **Test No B5**.
- [ ] In `nextcloud`, select a file and navigate to sidebar `OpenProject` tab.
- [ ] Search for any of the work packages in the `Demo Project`.
- [ ] Work packages are listed.
- [ ] Link to any one of the work packages appeared in the search lists.
- [ ] Linked work package appears in the `OpenProject` Tab with a successful message.
- [ ] Also, try linking other work packages, reload the browser and all the linked ones should appear in the `OpenProject` Tab.
- [ ] Hover to a work package to be unlinked, unlink button is visible.
- [ ] Unlink a work package and it should be deleted from the `OpenProject` Tab with a successful message.

### B7. Link/Unlink a work package for a file/folder from OpenProject
- [ ] Complete Smoke **Test No B6**.
- [ ] In `nextcloud`, go to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, click `link existing files`, select available files (for example, welcome.txt) from Nextcloud and link it to the work package.
- [ ] Selected file is linked to the work package in `OpenProject`
- [ ] Also Navigate to nextcloud and see in the `OpenProject` tab for file (welcome.txt), the work package should be linked.

### B8. Direct upload file/folder from OpenProject to Nextcloud
- [ ] Complete Smoke **Test No B6**.
- [ ] In `nextcloud`, go to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, click `Upload files`, select available files from your local system (for example, local.txt) and upload choosing the upload location.
- [ ] Uploaded file is linked to the work package in `OpenProject`
- [ ] Also Navigate to `Nextcloud` and see in the `OpenProject` tab for file (local.txt), the work package should be linked.

### B9. Create a WorkPackage from Nextcloud
- [ ] Complete Smoke **Test No B6**.
- [ ] Open the form to create work package from Nextcloud in the `OpenProject` tab for a file/folder.
- [ ] Select `Demo Project`, fill up the modal form and create.
- [ ] Work package should be created and linked to the selected file.

### 10. Check notification in `OpenProject` widget in Nextcloud
> Make sure your `OpenProject` is running along with `worker` instance
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 2.
- [ ] Complete Smoke Test No 3.
- [ ] Complete Smoke Test No 4.
- [ ] Create a separate user in both `Nextcloud` as well as `OpenProject`.
- [ ] Connect `Nextcloud` user to `OpenProject` user and vice-versa (`OpenProject` user to `Nextcloud` user).
- [ ] Now as an `OpenProject` admin, assign any of the `Demo Project` work packages to the created `OpenProject` user.
- [ ] The `Nextcloud` user should receive a notification regarding the assignment.













13. SSO Configuration (Nextcloud Hub as IdP)

**In nextcloud**

- [ ] Install and enable `oidc` app (min version 1.6.0)
- [ ] Go to `Administation > Security`
- [ ] Under "OpenID Connect clients" section:
    - Add a client name (not an identifier)
    - Add a redirect URL (<openproject_host>/auth/oidc-<idp-displayname-from-OP>/callback)
    - Set `Refresh Token Expire Time` to `Never`
    - Save
    - Copy the Client ID and Client secret (you will need these later in OpenProject and integration_openproject)
- [ ] Create a new user( with username, display name, password, and email) 
- [ ] Install and enable `user_oidc` apps
- [ ] Run following command:
    - php occ config:system:set user_oidc --type boolean --value="true" oidc_provider_bearer_validation

**In openproject**

- [ ] Go to `Administration > Authentication > OpenID providers`
- [ ] Add a new custom OpenID provider:
  - Display name: `nextcloud` (use this name as redirect URL in Nextcloud: <idp-displayname-from-OP>)
  - Discovery URL: `<nextcloud-host>/index.php/.well-known/openid-configuration`
  - Client ID: Client ID copied earlier from Nextcloud
  - Client secret: Client secret copied earlier from Nextcloud
- [ ] Go to `Administration > Files`
- [ ] Select the file storage type called Nextcloud (created earlier in previous test)
- [ ] Under `OAuth configuration`, select `Use access token obtained during user log in`

14. Configure SSO Settings in Nextcloud(IdP as nextcloud )
- [ ] Complete Smoke Test No 13.
- [ ] Navigate to `Administration > OpenProject` in nextcloud
- [ ] Under `Authentication Method`, select `Single-Sign-On through OpenID Connect Identity Provider`
- [ ] In `Authentication settings`, select `provider Type` as `Nextcloud Hub`
- [ ] Set Openproject client ID by Client ID copied earlier in nextcloud.
- [ ] Then click on complete the set up

15. Login to Nextcloud as created User(IdP as nextcloud )
- [ ] Complete Smoke Test No 14.
- [ ] Login as nextcloud-created user in `nextcloud`. 
- [ ] In `openproject`, use the SSO button on the login page to sign in as the nextcloud-created user.
- [ ] Login should be successful with the nextcloud-created user in `openproject`.

16. Verify OpenProject Connection in Nextcloud( IdP as nextcloud )
- [ ] Complete Smoke Test No 15.
- [ ] Navigate to `settings > Openproject` in `Nextcloud` of nextcloud -created user
- [ ] Created user should be connected as a `Nextcloud` user.

14. SSO Authentication via OpenID Connect (IdP as keycloak without token exchange)

We can follow up the following link to setup the keycloak
https://www.openproject-edge.com/docs/system-admin-guide/integrations/nextcloud/oidc-sso/#keycloak

**In nextcloud**

- [ ] Go to `Administration > OpenID Connect`
- [ ] Enable `store login tokens` options.
- [ ] Register a new providers with following data:
    - Identifier: `keycloak`
    - Client ID: nextcloud client id from keycloak
    - Client Secret: nextcloud client secret from keycloak
    - Discovery endpoint: `<keycloak-hosts>/realms/<realm-name>/.well-known/openid-configuration`
    - Scope: openid email profile api_v3
    - submit
- [ ] Login as keycloak-created user in `Nextcloud` (Login to initialize the Keycloak user in Nextcloud)
- [ ] Logout

**In openproject**

- [ ] Navigate to `Administration > Authentication > OpenID providers`
- [ ] Add a new custom OpenID provider:
  - Display name: `keycloak`
  - Discovery URL: `<keycloak-host>/realms/<realm-name>/.well-known/openid-configuration`
  - Client ID: Client ID of openproject from keycloak
  - Client secret: Client secret of openproject from keycloak
- [ ] Login as keycloak-created user in `Openproject`
- [ ] Log out, then Login as admin in `Openproject`
- [ ] Add keycloak-created user as a member in one of the project.

**Testing**
- [ ] Go to `Administration > OpenProject` in nextcloud
- [ ] Under `Authentication Method`, select `Single-Sign-On through OpenID Connect Identity Provider`
- [ ] In `Authentication settings`, select `provider Type` as `Keycloak`
- [ ] Disable `token exchange`


15. SSO Authentication via OpenID Connect (IdP as keycloak with token exchange)

We can follow up the following link to setup the link
https://www.openproject-edge.com/docs/system-admin-guide/integrations/nextcloud/oidc-sso/#keycloak

**Testing**
- [ ] Go to `Administration > OpenProject` in nextcloud
- [ ] Under `Authentication Method`, select `Single-Sign-On through OpenID Connect Identity Provider`
- [ ] In `Authentication settings`, select `provider Type` as `Keycloak`
- [ ] Enable `token exchange`
- [ ] Set `OpenProject client ID *` as `Openproject`
- [ ] Click on `keep current setup`