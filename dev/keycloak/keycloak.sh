#!/bin/bash
# SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

set -eo pipefail

CURL=/usr/bin/curl

KC_URL="http://localhost:8080"

NC_CLIENT_DB_ID=d97b00f4-c4eb-41f0-ad9e-0c6674d13348
OP_CLIENT_DB_ID=2a721a37-e840-49d1-8715-ab36cfc56dcf
APIV3_SCOPE_ID=83625306-1925-4069-a77b-d8d9d2ec520b
NC_AUD_SCOPE_ID=7107eebd-c2ef-4a8f-aa3c-f8112a243b9f

###################################
# Start Keycloak                  #
###################################
/opt/keycloak/bin/kc.sh start-dev "$@" &
KC_PID=$!
# wait to ensure the process has started
sleep 1
if [[ ! -d "/proc/$KC_PID" ]]; then
    echo "[ERROR] Failed to start Keycloak"
    exit 1
fi

# wait for Keycloak to start
max_retry=60
retry=1
time_elapsed=0
# pre-wait to allow Keycloak to start
echo "[INFO] Waiting for Keycloak to be ready..."
sleep 10
while [[ $retry -le $max_retry ]]; do
    server_status=$($CURL -s -o /dev/null -w "%{http_code}" "$KC_URL" || echo "000")
    if [[ $server_status -ne 0 && $server_status -lt 400 ]]; then
        break
    fi
    echo "[INFO] Waiting for Keycloak to be ready... (Retry $retry/$max_retry)"
    sleep 5
    time_elapsed=$((time_elapsed + 5))
    ((retry++))
done
if [[ $retry -gt $max_retry ]]; then
    echo "[ERROR] $time_elapsed seconds timeout. Keycloak is not ready"
    exit 1
fi

###################################
# Setup realm, users and clients  #
###################################

COMMON_USER_ATTRIBUTES='
"emailVerified": true,
"enabled": true,
"credentials": [
    {
        "type": "password",
        "value": "1234",
        "temporary": false
    }
]'

COMMON_CLIENT_ATTRIBUTES='
"clientAuthenticatorType" : "client-secret",
"protocol": "openid-connect",
"enabled": true,
"attributes": {
    "standard.token.exchange.enableRefreshRequestedTokenType" : "SAME_SESSION",
    "standard.token.exchange.enabled" : "true"
}'

# get access token for Keycloak admin
TOKEN=$($CURL -s -XPOST "$KC_URL/realms/master/protocol/openid-connect/token" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "client_id=admin-cli" \
    -d "username=$KC_BOOTSTRAP_ADMIN_USERNAME" \
    -d "password=$KC_BOOTSTRAP_ADMIN_PASSWORD" \
    -d "grant_type=password" | sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p')

if [ -z "$TOKEN" ]; then
    echo "[ERROR] Failed to obtain access token"
    exit 1
fi

# create realm with users and clients
realm_status=$(
    $CURL -XPOST "$KC_URL/admin/realms" -s -o /dev/null -w "%{http_code}" \
        -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -d '{ 
            "realm":"'"$KC_REALM_NAME"'",
            "enabled":true,
            "users": [
                {
                    "username": "alice",
                    "firstName" : "Alice",
                    "lastName" : "Hansen",
                    "email" : "alice@example.com",
                    '"$COMMON_USER_ATTRIBUTES"'
                },
                {
                    "username": "brian",
                    "firstName" : "Brian",
                    "lastName" : "Murphy",
                    "email" : "brian@example.com",
                    '"$COMMON_USER_ATTRIBUTES"'
                }
            ],
            "clients": [
                {
                    "id": "'"$NC_CLIENT_DB_ID"'",
                    "clientId": "'"$KC_NEXTCLOUD_CLIENT_ID"'",
                    "secret" : "'"$KC_NEXTCLOUD_CLIENT_SECRET"'",
                    "redirectUris" : [ "https://'"$KC_NEXTCLOUD_CLIENT_HOST"'/*" ],
                    '"$COMMON_CLIENT_ATTRIBUTES"'
                },
                {
                    "id": "'"$OP_CLIENT_DB_ID"'",
                    "clientId": "'"$KC_OPENPROJECT_CLIENT_ID"'",
                    "secret" : "'"$KC_OPENPROJECT_CLIENT_SECRET"'",
                    "redirectUris" : [ "https://'"$KC_OPENPROJECT_CLIENT_HOST"'/*" ],
                    '"$COMMON_CLIENT_ATTRIBUTES"'
                }
            ]
        }'
)

if [[ "$realm_status" -ne 201 && "$realm_status" -ne 409 ]]; then
    echo "[ERROR] Failed to create realm '$KC_REALM_NAME', status code: $realm_status"
    exit 1
elif [[ "$realm_status" -ne 409 ]]; then
    echo "[INFO] Realm '$KC_REALM_NAME' exists, skipping creation..."
else
    echo "[INFO] Realm '$KC_REALM_NAME' created successfully"
fi

function add_client_scope() {
    local response_status
    local scope_name="$1"
    local scope_id="$2"
    local client_id="$3"

    response_status=$(
        $CURL -XPOST "$KC_URL/admin/realms/$KC_REALM_NAME/client-scopes" \
            -s -o /dev/null -w "%{http_code}" \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json" \
            -d '{
                "id":"'"$scope_id"'",
                "name":"'"$scope_name"'",
                "protocol":"openid-connect",
                "attributes": {"include.in.token.scope":"true"},
                "protocolMappers": [
                    {
                        "name": "'"$client_id"'_aud_mapper",
                        "protocol": "openid-connect",
                        "protocolMapper": "oidc-audience-mapper",
                        "config": {
                            "included.client.audience": "'"$client_id"'",
                            "access.token.claim": "true"
                        }
                    }
                ]
            }'
    )

    if [[ "$response_status" -ne 201 && "$response_status" -ne 409 ]]; then
        echo "[ERROR] Failed to create client scope '$scope_name', status code: $response_status"
        exit 1
    elif [[ "$response_status" -ne 409 ]]; then
        echo "[INFO] Client scope '$scope_name' exists, skipping creation..."
    else
        echo "[INFO] Client scope '$scope_name' created successfully"
    fi

}

function add_scope_to_client() {
    local response_status
    local client_id="$1" # client database ID (not a client identifier)
    local scope_id="$2"

    response_status=$(
        $CURL -XPUT "$KC_URL/admin/realms/$KC_REALM_NAME/clients/$client_id/optional-client-scopes/$scope_id" \
            -s -o /dev/null -w "%{http_code}" \
            -H "Authorization: Bearer $TOKEN"
    )

    if [ "$response_status" -ne 204 ]; then
        echo "[ERROR] Failed to add scope '$scope_id' to client '$client_id', status code: $response_status"
        exit 1
    else
        echo "[INFO] Scope '$scope_id' added to client '$client_id' successfully"
    fi
}

add_client_scope "api_v3" "$APIV3_SCOPE_ID" "$KC_OPENPROJECT_CLIENT_ID"
add_client_scope "add-nc-aud" "$NC_AUD_SCOPE_ID" "$KC_NEXTCLOUD_CLIENT_ID"
add_scope_to_client "$NC_CLIENT_DB_ID" "$APIV3_SCOPE_ID"
add_scope_to_client "$OP_CLIENT_DB_ID" "$NC_AUD_SCOPE_ID"

# bring Keycloak to foreground
wait $KC_PID
