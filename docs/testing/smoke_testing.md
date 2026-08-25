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

- [Test Cases](#test-cases)
  - [1. Link/Unlink Work Packages in Nextcloud](#1-linkunlink-work-packages-in-nextcloud)
  - [2. Link/Unlink Work Packages in OpenProject](#2-linkunlink-work-packages-in-openproject)
  - [3. Create a Work Package From Nextcloud](#3-create-a-work-package-from-nextcloud)
  - [4. Check Notifications in Nextcloud Dashboard Widget](#4-check-notifications-in-nextcloud-dashboard-widget)

## Section A: Two-Way OAuth 2.0 Authorization Code Flow

### 1. Integration Setup

- Open Nextcloud and OpenProject.
- In OpenProject, as a user `admin`, navigate to `Administration > Files` and add a new `Nextcloud` storage:
  - [ ] Add name and Nextcloud host URL.
  - [ ] Select `Two-way OAuth 2.0 authorization code flow` as the authentication method.
  - Note the generated OpenProject OAuth client ID and secret. **[We will need this in the next step]**
- In Nextcloud, as a user `admin`, navigate to `Administration Settings > OpenProject`:
  - [ ] Add OpenProject host URL.
  - [ ] Select `Two-way OAuth 2.0 authorization code flow` authentication method.
  - [ ] Enter the OpenProject client ID and secret copied from the above step.
  - [ ] Note the generated Nextcloud OAuth client ID and secret. **[We will need this in the next step]**
  - [ ] Enable `Automatically managed folders`
  - [ ] Note the generated application password. **[We will need this in the next step]**
- Back in OpenProject:
  - [ ] Enter the Nextcloud client ID and secret copied from the above step.
  - [ ] Enable `Automatically managed folders` and enter the application password copied from the above step.

### 2. Establish Connection

Nextcloud:

- [ ] Navigate to `Personal Settings > OpenProject`
- [ ] Connect using `Connect to OpenProject` button.

OpenProject:

- [ ] Select a Project (e.g., `Demo Project`).
- [ ] Add `Nextcloud` storage to it from `Project settings > Files`.
- [ ] Connect using `Nextcloud log in` button.

### 3. Complete the common smoke tests

- [ ] Validate [test cases 1-7](#common-smoke-test-steps).

## Section B: Single-Sign-On through OpenID Connect Identity Provider

> **Note**: `OpenProject` must be an Enterprise edition to use SSO setup.

### B.1: Nextcloud Hub as IDP

#### 1. Server Pre-Requisites

Nextcloud:

- Enable the following apps:
  - OIDC Identity Provider (`oidc`)
  - OpenID Connect user backend (`user_oidc`)
- Create a OIDC client

- [ ] In `Nextcloud`, log in as a user `admin`.
- [ ] Install and enable `OIDC Identity Provider`(`oidc`) and `OpenID Connect user backend`(`user_oidc`) apps.
- [ ] Create a new user ( with username, display name, password, and email).
- [ ] Check whether `oidc_provider_bearer_validation` exists and is set to `true` by running `php occ config:list`.
  > **Note:** This requires the OIDC Identity Provider app >= v1.4.0. Access tokens and JWT tokens can be validated.
  - [ ] If the setting does not exist or is set to `false`, run:
    - `php occ config:system:set user_oidc --type boolean --value="true" oidc_provider_bearer_validation`
- [ ] Go to `Administration > OpenID Connect` and enable `store login tokens` option.
- [ ] Go to `Administration > OpenID Connect Provider`.
  - Click the button `+ Add client`.
  - Add a client name (not an identifier) such as `openproject`.
  - Add a redirect URL: `<openproject_host>/auth/oidc-<idp_displayname_from_openproject>/callback`.
    > **Note:** Use the same value as the custom OpenID provider `Display name` in OpenProject from `B.1.2` (for example, `nextcloud`) for `<idp_displayname_from_openproject>`.
  - Choose Signing Algorithm option as `RS256`.
  - Choose Client Type as `Confidential` and click on `Add` button.
  - After clicking `add` button, click on the recently created client.
  - Choose `Access Token Type` as `JWT Access Token (RFC9068)` and click on `save` button.
  - Go to `settings` section.
  - Set `Refresh Token Expire Time` to `Never`.
  - Save.
  - Copy the Client ID and Client secret (you will need these later in OpenProject and integration_openproject).

#### B.1.2. Add Nextcloud IDP in OpenProject (Without project folder setup)

- [ ] In `OpenProject`, log in as a user `admin`.
- [ ] Go to `Administration > Authentication > OpenID providers`.
- [ ] Add a new custom OpenID provider:
  - Display name: `nextcloud` (use this name as redirect URL in Nextcloud: <idp_displayname_from_openproject>)
  - Discovery URL: `<nextcloud_instance_url>/index.php/.well-known/openid-configuration`
  - Client ID: Client ID copied earlier from Nextcloud
  - Client secret: Client secret copied earlier from Nextcloud
  - Keep all other options as default and click on `save`.
- [ ] Then, go to `Administration > Files`.
- [ ] Create a file storage type `Nextcloud` by clicking the button `+ Storage` and choosing `Nextcloud`.
- [ ] Set the name to `Nextcloud`.
- [ ] Set Host to `<nextcloud-host>`.
- [ ] Set authentication Method to `Single-Sign-On through OpenID Connect Identity Provider`.
- [ ] Then, select the option `Use access token obtained during user log in`.
- [ ] Uncheck project folder (automatically managed folder).
- [ ] Click on button `Finish setup`.

#### B.1.3. Setup integration (Without project folder setup)

- [ ] Complete step [Test No B.1.1](#b11-configure-nextcloud).
- [ ] Complete step [Test No B.1.2](#b12-add-nextcloud-idp-in-openproject-without-project-folder-setup).
- [ ] In `Nextcloud`, as a user `admin`, go to `Administration > OpenProject`.
- [ ] Add openproject host.
- [ ] Under `Authentication Method`, select `Single-Sign-On through OpenID Connect Identity Provider`.
- [ ] In `Authentication settings`, select `provider Type` as `Nextcloud Hub`.
- [ ] Set OpenProject `client ID` by Client ID copied earlier in **Test No B1**.
- [ ] Uncheck `project folder (automatically managed folder)`.

#### B.1.4. Login to OpenProject using Nextcloud user

- [ ] Complete step [Test No B.1.1](#B11-Configure-Nextcloud).
- [ ] Complete step [Test No B.1.2](#b12-add-nextcloud-idp-in-openproject-without-project-folder-setup).
- [ ] Complete step [Test No B.1.3](#b13-setup-integration-without-project-folder-setup).
- [ ] In `nextcloud`, login as nextcloud-created user.
- [ ] In `openproject`, use the SSO button on the login page to sign in as the nextcloud-created user.
- [ ] Login should be successful in `openproject`.
- [ ] The OpenProject username should match the Nextcloud-created user’s name.

#### B.1.5. Verify Connection of Nextcloud user with OpenProject

- [ ] Complete step [Test No B.1.1](#B11-Configure-Nextcloud).
- [ ] Complete step [Test No B.1.2](#b12-add-nextcloud-idp-in-openproject-without-project-folder-setup).
- [ ] Complete step [Test No B.1.3](#b13-setup-integration-without-project-folder-setup).
- [ ] Complete step [Test No B.1.4](#b14-login-to-openProject-using-nextcloud-user).
- [ ] In nextcloud, login as nextcloud-created user.
- [ ] Navigate to `Settings > OpenProject`.
- [ ] Should show user is connected as an OpenProject user.

#### B.1.6. Add File storage (Nextcloud) to an OpenProject project

- [ ] In Openproject, as a user admin, select any OpenProject Project (for example, Demo Project) in OpenProject.
- [ ] Navigate to Project settings > Files of Demo Project.
- [ ] Add a file storage name Nextcloud( choose No specific Folder option ) for Demo Project.
- [ ] Add the nextcloud-created user as the member of Demo Project project.

#### B.1.7. Complete the common smoke tests

- [ ] Complete [smoke tests 1-7](#common-smoke-test-steps).

### B.2: External Provider

> Here, keycloak is an External Provider

#### B.2.1. Configure Keycloak

- [ ] Set up Keycloak using this guide: [Keycloak Setup](https://www.openproject-edge.com/docs/system-admin-guide/integrations/nextcloud/oidc-sso/#keycloak).

#### B.2.2. Configure Nextcloud

- [ ] In **nextcloud**, as an admin go to `Administration > OpenID Connect`.
- [ ] Enable `store login tokens` options.
- [ ] Register a new providers with the following data:
  - Identifier: `keycloak`
  - Client ID: nextcloud client id from keycloak
  - Client Secret: nextcloud client secret from keycloak
  - Discovery endpoint: `<keycloak_instance_url>/realms/<realm-name>/.well-known/openid-configuration` (for example realm name can be `opnc`)
  - Scope: `openid profile email api_v3`
  - submit
- [ ] If you are using [Docker setup](../../dev/), default `Keycloak` users already exist (`alice` and `brian`, password: `1234`), so you can skip the user-creation steps below and go directly to step [B.2.3](#b23-add-keycloak-idp-in-openproject).
- [ ] In Keycloak, go to the user management section. For example, if your realm name is `opnc`, navigate to: `opnc > Users`.
- [ ] Then create a user.
- [ ] Login as keycloak-created user in `Nextcloud`.
- [ ] Login should be successful.
- [ ] Logout.

#### B.2.3. Add Keycloak IDP in OpenProject

- [ ] In `OpenProject`, as a user `admin`, go to `Administration > Authentication > OpenID providers`.
- [ ] Add a new custom OpenID provider:
  - Display name: `keycloak`
  - Discovery URL: `<keycloak_instance_url>/realms/<realm-name>/.well-known/openid-configuration`
  - Client ID: Client ID of openproject provided by keycloak in the <realm-name> realm.
  - Client secret: Client secret of openproject from keycloak
    > Note: To find `Client ID` and `Client secret` in Keycloak, open `Clients` in your `<realm-name>` realm and select `openproject`. Copy `Client ID` from the `Settings` tab and `Client secret` from the `Credentials` tab.
- [ ] Go to Administration > Files.
  - [ ] Create a file storage type `Nextcloud` by clicking the button `+ Storage` and choosing Nextcloud
  - [ ] Add name as `Nextcloud`.
  - [ ] Add Host as `<nextcloud-host>`
  - [ ] Set authentication method to `Single-Sign-On through OpenID Connect Identity Provider`.
  - [ ] Then, select the option `Use access token obtained during user log in`.
  - [ ] Uncheck project folder (automatically managed folder).
  - [ ] Click on button `Finish setup`.
- [ ] Navigate to `Project settings > Files` of a project (for example, `Demo Project`) and add `Nextcloud` as a file storage.
- [ ] In `OpenProject`, login as keycloak-created user.
- [ ] In `OpenProject`, log out, then log in as a user `admin`.
- [ ] As an `OpenProject` admin, add keycloak-created user as a member in one of the project (for example, `Demo Project`).

#### B.2.4. Setup integration (token exchange disabled) in Nextcloud

- [ ] As a user `admin`, go to `Administration > OpenProject`.
- [ ] Add OpenProject host as `<openproject_host>`.
- [ ] Under `Authentication Method`, select `Single-Sign-On through OpenID Connect Identity Provider`.
- [ ] In `Authentication settings`, select `provider Type` as `Keycloak`.
- [ ] Disable `token exchange` as well as `Automatically managed folders`.

#### B.2.5. Verify Connection in nextcloud

- [ ] First, complete steps **B.2.1** to **B.2.4**.
- [ ] In nextcloud, login as keycloak-created user.
- [ ] Navigate to `Settings > OpenProject`.
- [ ] Should show user is connected as an OpenProject user.

#### B.2.6. Complete the common smoke tests

- [ ] Complete [smoke tests 1-7](#common-smoke-test-steps).

#### B.2.7. Setup integration (token exchange enabled) in Nextcloud

- [ ] Complete step [Test No B.2.1](#b21-configure-keycloak).
- [ ] Complete step [Test No B.2.2](#b22-configure-nextcloud).
- [ ] Complete step [Test No B.2.3](#b23-Add-Keycloak-idp-in-openoroject).
- [ ] As a user `admin` go to `Administration > OpenProject` in nextcloud.
- [ ] Under `Authentication Method`, select `Single-Sign-On through OpenID Connect Identity Provider`.
- [ ] In `Authentication settings`, select `provider Type` as `Keycloak`.
- [ ] Enable `token exchange`.
- [ ] Set `OpenProject client ID *` to `openproject`.

#### B.2.8. Verify Connection in nextcloud

- [ ] Complete step [Test No B.2.1](#b21-configure-keycloak).
- [ ] Complete step [Test No B.2.2](#b22-configure-nextcloud).
- [ ] Complete step [Test No B.2.3](#b23-add-keycloak-idp-in-openproject).
- [ ] Complete step [Test No B.2.5](#b25-verify-connection-in-nextcloud).
- [ ] In nextcloud, login as keycloak-created user.
- [ ] Navigate to `Settings > OpenProject`.
- [ ] Should show user is connected as an OpenProject user.

#### B.2.9. Complete the common smoke tests

- [ ] Complete [smoke tests 1-7](#common-smoke-test-steps).

## Test Cases

> **Note**: For SSO setup, run smoke tests 1-5 and the portions of smoke test 6 that involve user interaction should be performed as a connected user.

### 1. Link/Unlink Work Packages in Nextcloud

- Upload a file
- [ ] Open OpenProject sidebar from file context menu.
  - File's 3 dots menu (`...`) -> `OpenProject`.
- [ ] Search for a work package and select to link.
- [ ] Check that the work package is listed under `Existing relations`.
- [ ] Unlink the work package (hover over the linked work package and click unlink icon).

### 2. Link/Unlink Work Packages in OpenProject

- [ ] Open a connected project's work package.
- [ ] From the `FILES` tab, link existing Nextcloud file using `link existing files` option.
- [ ] Again from the `FILES` tab, upload a file to Nextcloud using `Upload files` option.
- In Nextcloud, check that:
  - [ ] Each file has linked work package listed in the OpenProject sidebar.
  - [ ] Uploaded file is present under `OpenProject/<project_name>/`.

### 3. Create a Work Package From Nextcloud

- [ ] From OpenProject sidebar, click `Create and link a new work package` button.
- [ ] Fill in the work package details and create.
- [ ] Check that the work package is listed under `Existing relations`.
- [ ] In OpenProject, check that the work package is created and linked to the file.

### 4. Check Notifications in Nextcloud Dashboard Widget

> Make sure your OpenProject is running along with worker instance

- [ ] In Nextcloud dashboard, click `Customize` and enable `OpenProject` widget.
- [ ] From OpenProject (as admin), assign a work package to a user connected to Nextcloud.
- [ ] The Nextcloud user should see a notification regarding the assignment.
