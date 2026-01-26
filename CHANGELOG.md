<!--
  - SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

### Added

### Changed

### Removed

## 2.11.0 - 2026-01-22

### Changed

- Remove the latest stable OpenProject version from warning message [#891](https://github.com/nextcloud/integration_openproject/pull/891)
- Replace internal method with public API for updating file metadata [#908](https://github.com/nextcloud/integration_openproject/pull/908)
- Bump minimum supported version of oidc app to 1.14.1 [#939](https://github.com/nextcloud/integration_openproject/pull/939)

### Fixed

- Show meaningful error message when deleting group folders [#884](https://github.com/nextcloud/integration_openproject/pull/884)
- Fix: Work package creation with a non-existent project [#923](https://github.com/nextcloud/integration_openproject/pull/923)
- Fix: Project Value in the field could not be removed [#923](https://github.com/nextcloud/integration_openproject/pull/923)
- Fix: Misleading message in select field of work package creation modal [#924](https://github.com/nextcloud/integration_openproject/pull/924)
- Fix: Subject field resizing on hover in work package creation modal [#929](https://github.com/nextcloud/integration_openproject/pull/929)

### Removed

- Drop support for Nextcloud 28 and 29 [#925](https://github.com/nextcloud/integration_openproject/pull/925)

## 2.10.1 - 2025-11-05

### Added

- Support Nextcloud 32 [#901](https://github.com/nextcloud/integration_openproject/pull/901)

### Fixed

- Fix: UI colors too Light in Nextcloud 32 [#911](https://github.com/nextcloud/integration_openproject/pull/911)

## 2.10.0 - 2025-09-26

### ⚠️ Upgrade Notice

When upgrading from old versions, the upgrade might fail with `Undefined constant` error due to the known cache issue in Nextcloud. To fix this, please run the following commands:

```bash
php occ upgrade
php occ maintenance:mode --off
```

### Added

- Show form errors if required apps are not enabled or not supported [#868](https://github.com/nextcloud/integration_openproject/pull/868)
- Add a second OpenProject group in Nextcloud to avoid Nextcloud errors when user is removed from their last Group [#869](https://github.com/nextcloud/integration_openproject/pull/869)

### Changed

- Combine error messages for app not enabled and not supported [#865](https://github.com/nextcloud/integration_openproject/pull/865)
- Use app name instead of app ID in error messages [#876](https://github.com/nextcloud/integration_openproject/pull/876)
- Rename Nextcloud group name "OpenProjectSuspended" to "OpenProjectNoAutomaticProjectFolders" [#889](https://github.com/nextcloud/integration_openproject/pull/889)

### Fixed

- Fix: No modifier for the first file upload [#877](https://github.com/nextcloud/integration_openproject/pull/877)
- Fix: Each request to OpenProject gets new token when setup with external SSO with token-exchange [#867](https://github.com/nextcloud/integration_openproject/pull/867)
- Improve translation support and fix grammar in the authentication method switch confirmation message [#875](https://github.com/nextcloud/integration_openproject/pull/875)
- Do not show app error if the form is in disabled state [#880](https://github.com/nextcloud/integration_openproject/pull/880)
- Fix: Empty app name if the app is not available [#886](https://github.com/nextcloud/integration_openproject/pull/886)
- Fix: Show latest stable OpenProject version [#887](https://github.com/nextcloud/integration_openproject/pull/887)

## 2.9.2 - 2025-08-25

### Fixed

- Fix initial set-up with external IDP without token exchange [#848](https://github.com/nextcloud/integration_openproject/pull/848)
- Consume groupfolder properties as an array [#851](https://github.com/nextcloud/integration_openproject/pull/851)
- Prevent unnecessary API calls when the account is setup via OAuth2 but not connected[#856](https://github.com/nextcloud/integration_openproject/pull/856)
- Fix next form not being loaded after saving OpenProject client settings [#857](https://github.com/nextcloud/integration_openproject/pull/857)
- Avoid checking user_oidc app when set up with OAuth2 [#855](https://github.com/nextcloud/integration_openproject/pull/855)
- Fix missing "Connect to OpenProject" button after upgrading from 2.8 [#858](https://github.com/nextcloud/integration_openproject/pull/858)

## 2.9.1 - 2025-06-13

### Fixed

- Fix authentication-method not being set after upgrading to 2.9.0 [#833](https://github.com/nextcloud/integration_openproject/pull/833)
- Fix OpenProject icon for proper rendering [#835](https://github.com/nextcloud/integration_openproject/pull/834)
- Persist authentication settings form state after save [#827](https://github.com/nextcloud/integration_openproject/pull/827)
- Fix: authentication settings doesn't show saved values after reload [#837](https://github.com/nextcloud/integration_openproject/pull/837)

### Changed

- Rename "Group folder" to "Team folder" [#815](https://github.com/nextcloud/integration_openproject/pull/815)
- Replace deprecated method "OC_Helper::uploadLimit()" with "OCP\Util::uploadLimit()" [#825](https://github.com/nextcloud/integration_openproject/pull/825)

## 2.9.0 - 2025-05-21

### Added

- Support OIDC authentication method between Nextcloud and OpenProject
  - Required apps and versions:
    - [user_oidc](https://github.com/nextcloud/user_oidc): `>=7.2.0`
    - [oidc](https://github.com/h2CK/oidc): `>=1.6.0`
  - Show error if connection cannot be made to OpenProject [#756](https://github.com/nextcloud/integration_openproject/pull/756)
  - Show proper error message in the dashboard based on auth method [#770](https://github.com/nextcloud/integration_openproject/pull/770)
  - Show error if user_oidc app is not available [#753](https://github.com/nextcloud/integration_openproject/pull/753)
  - Show error if user_oidc app not supported [#768](https://github.com/nextcloud/integration_openproject/pull/768)
  - Support setup with Nextcloud Hub [#778](https://github.com/nextcloud/integration_openproject/pull/778)
  - Add option to enable/disable token exchange with external OIDC provider [#797](https://github.com/nextcloud/integration_openproject/pull/797)
  - Request token with api_v3 scope [#809](https://github.com/nextcloud/integration_openproject/pull/809)
- Add hint for required OpenProject version and plan [#810](https://github.com/nextcloud/integration_openproject/pull/810)

### Changed

- Rename admin settings labels: `Authorization` -> `Authentication` [#758](https://github.com/nextcloud/integration_openproject/pull/758)
- Drop support for Nextcloud 27 [#779](https://github.com/nextcloud/integration_openproject/pull/779)

### Fixed

- Fix authentication method documentation link [#820](https://github.com/nextcloud/integration_openproject/pull/820)

## 2.8.1 - 2025-02-05

### Fixed

- choose correct base URL for OCS requests [#780](https://github.com/nextcloud/integration_openproject/pull/780)

## 2.8.0 - 2025-02-24

### Added

- Support Nextcloud 31
- Correct encoding of the avatar url [#767](https://github.com/nextcloud/integration_openproject/pull/767)
- Expose OpenProject API endpoints as OCS endpoints [#769](https://github.com/nextcloud/integration_openproject/pull/769)

## 2.7.2 - 2024-12-16

### Fixed

- Fixed fatal error related to groupfolders [#736](https://github.com/nextcloud/integration_openproject/pull/736)
- UI/UX improvement: consistent element sizes in Create Wrokpackage Modal [#743](https://github.com/nextcloud/integration_openproject/pull/743)
- UI/UX improvement: accomodate long subject of a workpackage [#744](https://github.com/nextcloud/integration_openproject/pull/744)

## 2.7.1 - 2024-10-31

### Changed

- Make error handling better in `integration_setup.sh` file for integration configuration setup.
- Improve UI by using Nextcloud's `NoteCard` in project folder setup error.
- Resolve the issue with retrieving the Nextcloud server version for version compare
- Add warning UI when encryption is not explicitly enabled for `groupfolders`.
- Fix hash or encrypt secret for different nextcloud versions

## 2.7.0 - 2024-09-10

### Changed

- This release expects OpenProject version 13.2 or newer
- Add application's support for Nextcloud 30
- Log admin configuration to audit log (`/audit.log`)
- Improve button text visibility when selecting different background images in Nextcloud's UI
- Bump packages version
- Fix random deactivation of automatically managed project folder
- Fix avatar not found in openproject
- Enhance project search when creating workpackages from Nextcloud
- Drop application's support for Nextcloud 26
- Fix issue preventing direct uploading of resources in Nextcloud that are managed by app `Files Access Control`
- Hash or encrypt `client_secret` for different Nextcloud versions

## 2.6.4 - 2024-08-15

### Changed

- This release expects OpenProject version 13.2 or newer
- Add application's support for Nextcloud 30
- Remove Nextcloud's `thecodingmachine` dependency from integration app

## 2.6.3 - 2024-04-17

### Changed

- This release expects OpenProject version 13.2 or newer
- Drop application's support for Nextcloud 25
- Add application's support for Nextcloud 29
- Add support for PHP version 8.2 and 8.3

## 2.6.2 - 2024-04-04

### Changed

- This release expects OpenProject version 13.2 or newer
- Improves form validation for creating workpackages from nextcloud.
- Fixes wrong option text while searching workpackage.
- Add quick link for `group folder` app when not downloaded and enabled (project folder setup).
- Adjust dashboard panel of `integration app` consistent to that dashboard panel of other nextcloud apps.
- Adjust padding for assignee avatar in `workpackage` template.
- Added setup and user guide documentation link for integration app.
- Added description for settings in admin and personal panel.

## 2.6.1 - 2024-02-19

### Changed

- This release expects OpenProject version 13.2 or newer
- Fixes: Signing terms and services for special user "OpenProject" when `terms_of_service` app is enabled
- Fixes: Error when fetching non-existent work package from `talk` app chat

## 2.6.0 - 2024-01-17

### Changed

- This release expects OpenProject version 13.2 or newer
- [What's Changed](https://github.com/nextcloud/integration_openproject/releases/tag/v2.6.0)

## 1.0.5 – 2021-06-28

### Changed

- stop polling widget content when document is hidden
- bump js libs
- get rid of all deprecated stuff
- bump min NC version to 22
- cleanup backend code

## 1.0.4 – 2021-04-27

### Fixed

- Avatar API URL

## 1.0.3 – 2021-04-27

### Changed

- improve activity notifications
- bump js libs

## 1.0.2 – 2021-04-26

### Changed

- cleaner avatar image response
  [#1](https://github.com/eneiluj/integration_openproject/issues/1) @birthe

## 1.0.1 – 2021-04-21

### Changed

- Support NC 20
- bump js libs

## 1.0.0 – 2021-03-19

### Added

- the app
