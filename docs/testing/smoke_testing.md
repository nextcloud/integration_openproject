<!--
  - SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Smoke Testing

**Table of Content:**

- [Installation](#installation)
- [Section A: Two-Way OAuth 2.0 Authorization Code Flow](#section-a-two-way-oauth-20-authorization-code-flow)
- [Section B: Single-Sign-On through OpenID Connect Identity Provider](#section-b-single-sign-on-through-openid-connect-identity-provider)
  - [B.1: Nextcloud Hub as IDP](#B1-nextcloud-hub-as-idp)
  - [B.2: External Provider](#B2-External-Provider)
- [Test Cases](#test-cases)
  - [1. Link/Unlink Work Packages in Nextcloud](#1-linkunlink-work-packages-in-nextcloud)
  - [2. Link/Unlink Work Packages in OpenProject](#2-linkunlink-work-packages-in-openproject)
  - [3. Create a Work Package From Nextcloud](#3-create-a-work-package-from-nextcloud)
  - [4. Check Notifications in Nextcloud Dashboard Widget](#4-check-notifications-in-nextcloud-dashboard-widget)

## Installation

- Download and enable/disable app from [marketplace](https://apps.nextcloud.com/apps/integration_openproject/releases)
  - [ ] Check the version is correct.
- Enable/Disable from CLI:`php occ a:e integration_openproject`, `php occ a:d integration_openproject`
  - [ ] Check the version is correct.
- Enable/Disable from Nextcloud webUI.
  - [ ] Check the version is correct.

## Section A: Two-Way OAuth 2.0 Authorization Code Flow

### Pre-Requisites

- Create a test user in Nextcloud and OpenProject.

### 1. Integration Setup

- Open Nextcloud and OpenProject.
- In OpenProject (as admin), navigate to `Administration > Files` and add a new `Nextcloud` storage:
  - [ ] Add name and Nextcloud host URL.
  - [ ] Select `Two-way OAuth 2.0 authorization code flow` as the authentication method.
  - Note the generated OpenProject OAuth client ID and secret. **[We will need this in the next step]**
- In Nextcloud (as admin), navigate to `Administration Settings > OpenProject`:
  - [ ] Add OpenProject host URL.
  - [ ] Select `Two-way OAuth 2.0 authorization code flow` authentication method.
  - [ ] Enter the OpenProject client ID and secret copied from the step above.
  - [ ] Note the generated Nextcloud OAuth client ID and secret. **[We will need this in the next step]**
  - [ ] Enable `Automatically managed folders`
  - [ ] Note the generated application password. **[We will need this in the next step]**
- Back in OpenProject:
  - [ ] Enter the Nextcloud client ID and secret copied from the step above.
  - [ ] Enable `Automatically managed folders` and enter the application password copied from the step above.
  - [ ] Save the storage.

### 2. Establish Connection

In OpenProject:

- As admin:
  - [ ] Select a Project (e.g., `Demo Project`).
  - [ ] Add created test user as a project member: `Members` sidebar menu.
  - [ ] Add `Nextcloud` storage to it: `Project settings > Files` sidebar menu.
  - [ ] Connect to Nextcloud admin user using `Nextcloud log in` button if prompted.
  - [ ] Log out
- Login as test user:
  - [ ] Connect to Nextcloud test user using `nextcloud` sidebar menu.

In Nextcloud:

- As admin:
  - [ ] Navigate to `Personal Settings > OpenProject`.
  - [ ] Connect to OpenProject admin user using `Connect to OpenProject` button.
  - [ ] Log out
- Login as test user:
  - [ ] Navigate to `Settings > OpenProject`.
  - [ ] Connect to OpenProject test user using `Connect to OpenProject` button.

### 3. Complete the common smoke tests

- [ ] Validate [test cases 1-4](#test-cases).

## Section B: Single-Sign-On through OpenID Connect Identity Provider

> **Note**: OpenProject must be an Enterprise edition to use SSO setup.

### B.1: Nextcloud Hub as IDP

<!-- move setup steps to separate docs file -->

#### Pre-Requisites

Setup Nextcloud:

- Enable the following apps:
  - OIDC Identity Provider (`oidc`)
  - OpenID Connect user backend (`user_oidc`)
- Create an OIDC client: `Administration settings > OpenID Connect Provider`
  - Name: `openproject`
  - Redirect URL: `<openproject_host_url>/auth/oidc-nextcloud/callback`
  - Add the client.
- Open the created client:
  - Change access token type to JWT Access Token.
  - Note the client ID and secret.
- Enable `store login tokens` option in `Administration settings > OpenID Connect`.
- Create a test user with username, display name, password, and email.

Setup OpenProject:

- Add a custom OpenID provider: `Administration > Authentication > OpenID providers`
  - Display name: `nextcloud`
  - Discovery URL: `<nextcloud_host_url>/index.php/.well-known/openid-configuration`
  - Client ID: `Client ID copied earlier from Nextcloud`
  - Client secret: `Client secret copied earlier from Nextcloud`
  - Complete the setup.

#### 1. Integration Setup

- Open Nextcloud and OpenProject.
- In OpenProject (as admin), navigate to `Administration > Files` and add a new `Nextcloud` storage:
  - [ ] Add name and Nextcloud host URL.
  - [ ] Select `Single-Sign-On through OpenID Connect Identity Provider` as the authentication method.
  - [ ] Choose `Use access token obtained during user log in` option.
- In Nextcloud (as admin), navigate to `Administration Settings > OpenProject`:
  - [ ] Add OpenProject host URL.
  - [ ] Select `Single-Sign-On through OpenID Connect Identity Provider` authentication method.
  - [ ] Choose `Nextcloud Hub` as provider type.
  - [ ] Enter the client ID copied from the setup step above.
  - [ ] Enable `Automatically managed folders`
  - [ ] Note the generated application password. **[We will need this in the next step]**
- Back in OpenProject:
  - [ ] Enable `Automatically managed folders` and enter the application password copied from the step above.
  - [ ] Save the storage.

#### 2. Establish Connection

In OpenProject:

- Use Nextcloud SSO button to login as Nextcloud test user.
- Login as admin:
  - [ ] Select a Project (e.g., `Demo Project`).
  - [ ] Add Nextcloud test user as a project member: `Members` sidebar menu.
  - [ ] Add `Nextcloud` storage to it: `Project settings > Files` sidebar menu.
  - [ ] Log out
- As Nextcloud test user:
  - [ ] Select a Project (e.g., `Demo Project`).
  - [ ] Open a work package.
  - [ ] From `FILES` tab, check that Nextcloud files is connected (Should see Nextcloud files upload section).

In Nextcloud:

- As Nextcloud test user:
  - [ ] Navigate to `Settings > OpenProject`.
  - [ ] Check that the user is connected.

#### 3. Complete the common smoke tests

- [ ] Validate [test cases 1-4](#test-cases).

### B.2: External Provider

Use Keycloak as an External Provider.

#### Pre-Requisites

Setup Keycloak (Skip this if you are using [dev compose setup](../../dev/)):

- Set up Keycloak using [Keycloak Setup](https://www.openproject-edge.com/docs/system-admin-guide/integrations/nextcloud/oidc-sso/#keycloak) guide.
- In the current realm, create test users.

Setup Nextcloud (Skip this if you are using [dev compose setup](../../dev/)):

- Enable `store login tokens` option.
- Register a new OIDC provider: `Administration settings > OpenID Connect`.
  - Identifier: `keycloak`
  - Client ID: `Nextcloud client ID from Keycloak`
  - Client Secret: `Nextcloud client secret from Keycloak`
  - Discovery endpoint: `<keycloak_host_url>/realms/<realm-name>/.well-known/openid-configuration`
  - Scope: `openid profile email api_v3`

Setup OpenProject:

- Add a custom OpenID provider: `Administration > Authentication > OpenID providers`
  - Display name: `keycloak`
  - Discovery URL: `<keycloak_host_url>/realms/<realm-name>/.well-known/openid-configuration`
  - Client ID: `OpenProject client ID from Keycloak`
  - Client secret: `OpenProject client secret from Keycloak`
  - Complete the setup.

#### 1. Integration Setup

- Open Nextcloud and OpenProject.
- In OpenProject (as admin), navigate to `Administration > Files` and add a new `Nextcloud` storage:
  - [ ] Add name and Nextcloud host URL.
  - [ ] Select `Single-Sign-On through OpenID Connect Identity Provider` as the authentication method.
  - [ ] Choose `Use access token obtained during user log in` option.
- In Nextcloud (as admin), navigate to `Administration Settings > OpenProject`:
  - [ ] Add OpenProject host URL.
  - [ ] Select `Single-Sign-On through OpenID Connect Identity Provider` authentication method.
  - [ ] Choose `External Provider` as provider type.
  - [ ] Select `keycloak` provider from the list and save.
  - [ ] Enable `Automatically managed folders`
  - [ ] Note the generated application password. **[We will need this in the next step]**
- Back in OpenProject:
  - [ ] Enable `Automatically managed folders` and enter the application password copied from the step above.
  - [ ] Save the storage.

#### 2. Establish Connection

> Keycloak users if you are using [dev compose setup](../../dev/):
>
> - `alice`:`1234`
> - `brian`:`1234`

In OpenProject:

- Use Keycloak SSO button to login as Keycloak test user.
- Login as admin:
  - [ ] Select a Project (e.g., `Demo Project`).
  - [ ] Add Keycloak test user as a project member: `Members` sidebar menu.
  - [ ] Add `Nextcloud` storage to it: `Project settings > Files` sidebar menu.
  - [ ] Log out
- As Keycloak test user:
  - [ ] Select a Project (e.g., `Demo Project`).
  - [ ] Open a work package.
  - [ ] From `FILES` tab, check that Nextcloud files is connected (Should see Nextcloud files upload section).

In Nextcloud:

- Login as Keycloak test user:
  - [ ] Navigate to `Settings > OpenProject`.
  - [ ] Check that the user is connected.

#### 3. Complete the common smoke tests

- [ ] Validate [test cases 1-4](#test-cases).

## Test Cases

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
