<!--
  - SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
This file consists of some smoke testing to be done before the release (major and minor) of `integration_application` application.
The need for this smoke testing (manual) is that we do not have e2e test setup to automate that involves both `OpenProject` and `Nextcloud`.

This document covers smoke tests for two authentication methods used in the `integration_openproject` apps:

1. **Two-way OAuth 2.0 authorization code flow** 
2. **Single-Sign-On through OpenID Connect Identity Provider**
    - **Nextcloud Hub** as the IdP  
    - **External Provider** as the IdP
      - With token exchange enable
      - With token exchange disable


## Smoke Test for `integration_openproject` (In Nextcloud and OpenProject)
### 1. Oauth configuration (without project folder setup/Automatically managed folders)
- [ ] In `OpenProject`, navigate to `Administration > Files`.
- [ ] Create a file storage type `Nextcloud` and name it as `Nextcloud` in `OpenProject`.
- [ ] In admin setting of `Nextcloud`, navigate to `Administration Settings > OpenProject`.
- [ ] Copy `OpenProject` Oauth Credential (client_id and client_secret) and save them in `Nextcloud`.
- [ ] Copy `Nextcloud` Oauth Credential (client_id and client_secret) and save them in `OpenProject`.

### 2. Connect Nextcloud with OpenProject (Without project folder setup)
- [ ] Complete Smoke Test No 1.
- [ ] In `Nextcloud`, navigate to `Personal Settings > Openproject` and click on `Connect to OpenProject` button.
- [ ] `Nextcloud` admin should be connected as an `OpenProject` admin.
- [ ] Also, create a user in both `Nextcloud` as well as `OpenProject`.
- [ ] From the personal section of the created user in `Nextcloud`, connect to `OpenProject`.
- [ ] `Nextcloud` user should be connected as an `OpenProject` user.

### 3. Add File storage (Nextcloud) to an OpenProject project
- [ ] Complete Smoke Test No 1.
- [ ] Select an `OpenProject` Project (for example, `Demo Project`) in `OpenProject`.
- [ ] Navigate to `Project settings > Files` of `Demo Project`.
- [ ] Add a file storage name `Nextcloud`( choose `No specific Folder` option ) for `Demo Project`.

### 4. Connect OpenProject with Nextcloud
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 3.
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, and login to `Nextcloud`.
- [ ] `OpenProject` admin is connected to `Nextcloud` as a `Nextcloud` admin.
- [ ] Also, create a user in both `Nextcloud` as well as `OpenProject`.
- [ ] Add the created `OpenProject` user as the member of `Demo Project` project (admin can add members to a project).
- [ ] Try to connect the created `OpenProject` user as created `Nextcloud` user.
- [ ] `OpenProject` user should be connected as a `Nextcloud` user.

### 5. Setup and check project folder in Nextcloud (with project folder setup)
- [ ] Complete Smoke Test No 1.
- [ ] Enable `groupfolders` application in `Nextcloud`.
- [ ] Enable `Automatically managed folders` switch in admin setting and set project folder.
- [ ] Application password should be generated.
- [ ] `OpenProject` user and group are created such that user `OpenProject` is admin of the group.
- [ ] Try deleting `OpenProject` user and group, those should not be deleted.

### 6. Link/Unlink a work package for a file/folder in Nextcloud
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 2.
- [ ] Complete Smoke Test No 3.
- [ ] Select a file, navigate to sidebar `OpenProject` tab.
- [ ] Search for any of the work packages in the `Demo Project`.
- [ ] Work packages are listed.
- [ ] Link to any one of the work packages appeared in the search lists.
- [ ] Linked work package appears in the `OpenProject` Tab with a successful message.
- [ ] Also, try linking other work packages, reload the browser and all the linked ones should appear in the `OpenProject` Tab.
- [ ] Hover to a work package to be unlinked, unlink button is visible.
- [ ] Unlink a work package and it should be deleted from the `OpenProject` Tab with a successful message.

### 7. Link/Unlink a work package for a file/folder from OpenProject
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 3.
- [ ] Complete Smoke Test No 4.
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, click `link existing files`, select available files (for example, welcome.txt) from Nextcloud and link it to the work package.
- [ ] Selected file is linked to the work package in `OpenProject`
- [ ] Also Navigate to nextcloud and see in the `OpenProject` tab for file (welcome.txt), the work package should be linked.

### 8. Direct upload file/folder from OpenProject to Nextcloud
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 3.
- [ ] Complete Smoke Test No 4.
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, click `Upload files`, select available files from your local system (for example, local.txt) and upload choosing the upload location.
- [ ] Uploaded file is linked to the work package in `OpenProject`
- [ ] Also Navigate to `Nextcloud` and see in the `OpenProject` tab for file (local.txt), the work package should be linked.

### 9. Create a WorkPackage from Nextcloud
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 2.
- [ ] Complete Smoke Test No 3.
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

### 11. Check New folder with automatically managed permissions in OpenProject
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 3 (Make sure to choose `New folder with automatically managed permissions` while creating `File storage`).
- [ ] Complete Smoke Test No 4.
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, click `link existing files`.
- [ ] In a modal, `Nextcloud > OpenProject > Demo project(1)` should be visible.
- [ ] Also Navigate to `Nextcloud` and in Files `OpenProject > Demo project(1)` folder is created.
- [ ] Try to delete `OpenProject` or `OpenProject > Demo project(1)`. They should not be deleted.

### 12. Check the integration script

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
- [ ] Upon success, try Smoke Test No 2 (Skip first check).
- [ ] Upon success, try Smoke Test No 4 (Skip first check).
- [ ] Also, to set up the integration configuration with project folder setup, just set environment `SETUP_PROJECT_FOLDER=true` and run the script.
- [ ] Re-run the script again after it is already setup (Should not give any error).


13. SSO Authentication via OpenID Connect (Nextcloud Hub as Identity Provider, without Token Exchange)

**In nextcloud**

- [ ] Install and enable `oidc` app (min version 1.6.0)
- [ ] Go to `Administation > Security`
- [ ] Under "OpenID Connect clients" section:
    - Add a client name (not an identifier)
    - Add a redirect URL (<openproject_host>/auth/oidc-<idp-displayname-from-OP>/callback)
    - Save
    - Note Client ID and Client secret
- [ ] Create a new user: username, displayname, password, and email 
- [ ] Also install and enable `user_oidc` apps
- [ ] Run following command:
    - php occ config:system:set user_oidc --type boolean --value="true" oidc_provider_token_generation
    - php occ config:system:set user_oidc --type boolean --value="true" oidc_provider_bearer_validation

- [ ] Then, Navigate to `Administration > OpenProject`
- [ ] On `Authentication Method` section, choose `Single-Sign-On through OpenID Connect Identity Provider`
- [ ] On `Authentication settings` section, choose `provider Type` as `Nextcloud Hub`
- [ ] Add Openproject client ID: client-id from Nextcloud

**In openproject**

- [ ] Navigate to `Administration > Authentication > OpenID providers`
- [ ] Add a custom OpenID provider:
  - Display name: nextcloud (use this name as redirect URL in Nextcloud: <idp-displayname-from-OP>)
  - Discovery URL: <nextcloud-host>/index.php/.well-known/openid-configuration
  - Client ID: client-id from Nextcloud
  - Client secret: client-secret from Nextcloud

- [ ] Navigate to the `Administration > Files`
- [ ] Go to file storage type named as `Nextcloud` which is created before.
- [ ] On `OAuth configuration` section choose `Use access token obtained during user log in`