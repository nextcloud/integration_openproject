<!--
  - SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# App Upgrade Testing

**Table of Content:**

- [Upgrade Steps](#upgrade-steps)
- [For OAuth 2.0 Setup](#for-oauth-20-setup)
- [For OIDC Setup](#for-oidc-setup)
  - [Nextcloud Hub as IDP](#nextcloud-hub-as-idp)
  - [External Provider (Keycloak)](#external-provider-keycloak)
    - [Token Exchange Disabled](#token-exchange-disabled)
    - [Token Exchange Enabled](#token-exchange-enabled)

## Upgrade Steps

- [ ] **Check update is available**: `php occ app:update --showonly integration_openproject`.
- [ ] **Run upgrade**: `php occ app:update --allow-unstable integration_openproject`.
- [ ] **Verify upgrade**: Confirm no errors and version updated.

> **Important**: When upgrading from old versions, the upgrade might fail with "Undefined constant" error due to a known cache issue in Nextcloud. To fix this, please run the following commands:
>
> ```bash
> php occ upgrade
> php occ maintenance:mode --off
> ```

## Upgrade Test Cases

### Existing OAuth 2.0 Setup

- [ ] **Before upgrade**: Perform complete setup with OAuth2 method (Project folder enabled).
- [ ] Perform [Upgrade steps](#upgrade-steps).
- [ ] **After upgrade**: Check that the integration setup and other changes are preserved.

### Existing SSO Setup

#### Nextcloud Hub as IDP

- [ ] **Before upgrade**: Perform complete setup with sso method (Nextcloud Hub as IDP, Project folder enabled).
- [ ] Perform [Upgrade steps](#upgrade-steps).
- [ ] **After upgrade**: Check that the integration setup and other changes are preserved.

### External Provider (Keycloak)

#### Token Exchange Disabled

- [ ] **Before upgrade**: Perform complete setup with sso method (Keycloak as IDP, Token exchange disable, Project folder enabled).
- [ ] Perform [Upgrade steps](#upgrade-steps).
- [ ] **After upgrade**: Check that the integration setup and other changes are preserved.

#### Token Exchange Enabled

- [ ] **Before upgrade**: Perform complete setup with sso method (Keycloak as IDP, Token exchange enable, Project folder enabled).
- [ ] Perform [Upgrade steps](#upgrade-steps).
- [ ] **After upgrade**: Check that the integration setup and other changes are preserved.
