#!/bin/bash
# SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

set -e

occ a:e integration_openproject
occ security:certificates:import /etc/ssl/certs/ca-certificates.crt
# setup user_oidc app
occ config:app:set --value=1 user_oidc store_login_token
occ config:system:set user_oidc --type boolean --value="true" oidc_provider_bearer_validation
occ user_oidc:provider:delete Keycloak
occ user_oidc:provider Keycloak \
    -c 'nextcloud' \
    -s 'nextcloud-secret' \
    -d 'https://keycloak.local/realms/opnc/.well-known/openid-configuration' \
    -o 'openid profile email api_v3'
occ user_oidc:provider Keycloak --check-bearer 1
