#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.

# variables from environment
# NEXTCLOUD_HOST=<nextcloud_host_url>
# OPENPROJECT_HOST=<openproject_host_url>
# OP_ADMIN_USERNAME=<openproject_admin_username>
# OP_ADMIN_PASSWORD=<openproject_admin_password>
# NC_ADMIN_USERNAME=<nextcloud_admin_username>
# NC_ADMIN_PASSWORD=<nextcloud_admin_password>
# OPENPROJECT_STORAGE_NAME=<openproject_filestorage_name> This variable is the name of the storage which keeps the oauth configuration in OpenProject required for Nextcloud integration.
# SETUP_PROJECT_FOLDER=<true|false> If true then the integration is done with a project folder setup in Nextcloud
# INTEGRATION_SETUP_DEBUG=<true|false> If true then the script will output more details (set -x/-v) and keep the payload files
# INTEGRATION_SETUP_TEMP_DIR=<pre-existing_directory> The payloads sent to the APIs are written into temporary files, by default ./temp, you can specify a custom directory that is expected to pre-exist e.g. when bootstrapping in a K8s job with ephemeral storage.

set -e

INTEGRATION_SETUP_DEFAULT_TEMP_DIR=./temp
CURL="curl -s -w %{http_code}"

help() {
  echo -e "Available environment variables:"
  echo -e "Nextcloud:"
  echo -e "\t NEXTCLOUD_HOST \t\t\t Nextcloud host URL"
  echo -e "\t NC_ADMIN_USERNAME \t\t\t Nextcloud admin username"
  echo -e "\t NC_ADMIN_PASSWORD \t\t\t Nextcloud admin password"
  echo -e "\t NC_INTEGRATION_PROVIDER_TYPE \t\t Single Sign-On provider type ('nextcloud_hub' or 'external')"
  echo -e "\t NC_INTEGRATION_PROVIDER_NAME \t\t SSO Provider name (Not required when using 'nextcloud_hub' type)"
  echo -e "\t NC_INTEGRATION_OP_CLIENT_ID \t\t OpenProject client ID (Not required when using 'nextcloud_hub' type)"
  echo -e "\t NC_INTEGRATION_TOKEN_EXCHANGE \t\t Enable token exchange (true/false) (Not required when using 'nextcloud_hub' type)"
  echo -e "\t NC_INTEGRATION_ENABLE_NAVIGATION \t Enable navigate to OpenProject header (true/false)"
  echo -e "\t NC_INTEGRATION_ENABLE_SEARCH \t\t Enable unified search (true/false)"
  echo -e "\t NC_INTEGRATION_ENABLE_PROJECT_FOLDER \t Enable project folder (true/false)"
  echo -e "\t NC_USER_OIDC_PROVIDER_NAME \t External OIDC provider name"
  echo -e "\t NC_USER_OIDC_CLIENT_ID \t Nextcloud client ID for OIDC provider"
  echo -e "\t NC_USER_OIDC_CLIENT_SECRET \t Nextcloud client secret for OIDC provider"
  echo -e "\t NC_USER_OIDC_DISCOVERY_URL \t External OIDC provider discovery URL"
  echo -e ""
  echo -e "OpenProject:"
  echo -e "\t OPENPROJECT_HOST \t OpenProject host URL"
  echo -e "\t OP_ADMIN_USERNAME \t OpenProject admin username"
  echo -e "\t OP_ADMIN_PASSWORD \t OpenProject admin password"
}

# helper functions
log_error() {
  echo -e "\e[31m$1\e[0m"
}

log_info() {
  echo -e "\e[37m$1\e[0m"
}

log_success() {
  echo -e "\e[32m$1\e[0m"
}

log_file() {
  time=$(date '+%Y-%m-%d %H:%M')
  echo -e "[$time] $1" >>"${INTEGRATION_SETUP_LOG_FILE}"
}

log_req() {
  local req
  local data
  req="$1"
  data="$2"
  log_file "[REQUEST]"
  log_file "  $req"
  if [[ -n $data ]]; then
    log_file "  $data"
  fi
}

log_res() {
  local res
  local status_code
  local body
  res="$1"
  status_code=$(get_statuscode "$res")
  body=$(get_body "$res")
  log_file "[RESPONSE]"
  log_file "  Code: $status_code"
  log_file "  $body"
}

get_body() {
  local response
  local status_code
  local body
  response="$1"
  status_code="${response: -3}"
  body="${response%"${status_code}"}"
  echo "$body"
}

get_statuscode() {
  local response
  local status_code
  response="$1"
  status_code="${response: -3}"
  echo "$status_code"
}

set -x
# Support for "Debug mode"
if [[ $INTEGRATION_SETUP_DEBUG == "true" ]]; then
  log_info "Debug mode is enabled"
  set -x
  set -v
fi

if [[ $INTEGRATION_SETUP_TEMP_DIR == "" ]]; then
  log_info "Using default temp dir: ${INTEGRATION_SETUP_DEFAULT_TEMP_DIR}"
  INTEGRATION_SETUP_TEMP_DIR=${INTEGRATION_SETUP_DEFAULT_TEMP_DIR}
  mkdir -p ${INTEGRATION_SETUP_TEMP_DIR}
else
  log_info "Using ${INTEGRATION_SETUP_TEMP_DIR} as non-default temporary directory"
  if [ ! -d "${INTEGRATION_SETUP_DEFAULT_TEMP_DIR}" ]; then
    log_error "Temporary directory does not exist"
    exit 1
  fi
fi
INTEGRATION_SETUP_LOG_FILE=${INTEGRATION_SETUP_TEMP_DIR}/sso_integration_setup.log
rm -f "${INTEGRATION_SETUP_LOG_FILE}"

if [[ -z "$NEXTCLOUD_HOST" || -z "$OPENPROJECT_HOST" ]]; then
  log_error "Nextcloud and OpenProject host URLs are required."
  help
  exit 1
fi

log_file "############################"
log_file "# SETUP NEXTCLOUD          #"
log_file "############################"
if [[ -z "$NC_ADMIN_USERNAME" || -z "$NC_ADMIN_PASSWORD" ]]; then
  log_error "Nextcloud admin username and password are required."
  help
  exit 1
fi

# Save required integration setup configs
NC_INTEGRATION_SETUP_DATA="\"openproject_instance_url\": \"${OPENPROJECT_HOST}\", \"authorization_method\": \"oidc\""
# Validate required configs for integration setup
if [[ -z $NC_INTEGRATION_PROVIDER_TYPE ]] ||
  [[ -z $NC_INTEGRATION_ENABLE_NAVIGATION ]] ||
  [[ -z $NC_INTEGRATION_ENABLE_SEARCH ]] ||
  [[ -z $NC_INTEGRATION_ENABLE_PROJECT_FOLDER ]]; then
  log_error "Following configs are required for integration setup:"
  log_error "\tNC_INTEGRATION_PROVIDER_TYPE"
  log_error "\tNC_INTEGRATION_ENABLE_NAVIGATION"
  log_error "\tNC_INTEGRATION_ENABLE_SEARCH"
  log_error "\tNC_INTEGRATION_ENABLE_PROJECT_FOLDER"
  help
  exit 1
fi

NC_INTEGRATION_SETUP_DATA="${NC_INTEGRATION_SETUP_DATA}, \"default_enable_navigation\": ${NC_INTEGRATION_ENABLE_NAVIGATION},
  \"default_enable_unified_search\": ${NC_INTEGRATION_ENABLE_SEARCH},
  \"setup_project_folder\": ${NC_INTEGRATION_ENABLE_PROJECT_FOLDER},
  \"sso_provider_type\": \"${NC_INTEGRATION_PROVIDER_TYPE}\""

if [[ "$NC_INTEGRATION_ENABLE_PROJECT_FOLDER" == "true" ]]; then
  NC_INTEGRATION_SETUP_DATA="${NC_INTEGRATION_SETUP_DATA}, \"setup_app_password\": true"
else
  NC_INTEGRATION_SETUP_DATA="${NC_INTEGRATION_SETUP_DATA}, \"setup_app_password\": false"
fi

# ignore provided NC_INTEGRATION_OP_CLIENT_ID, NC_INTEGRATION_PROVIDER_NAME if using nextcloud_hub
if [[ "$NC_INTEGRATION_PROVIDER_TYPE" == "nextcloud_hub" ]]; then
  NC_INTEGRATION_PROVIDER_NAME=""
  NC_INTEGRATION_OP_CLIENT_ID=""
  NC_INTEGRATION_SETUP_DATA="${NC_INTEGRATION_SETUP_DATA}, \"oidc_provider\": \"Nextcloud Hub\""
elif [[ "$NC_INTEGRATION_PROVIDER_TYPE" == "external" ]]; then
  if [[ -z $NC_INTEGRATION_PROVIDER_NAME || -z $NC_INTEGRATION_TOKEN_EXCHANGE ]]; then
    log_error "Following configs are required to setup integration with external provider:"
    log_error "\tNC_INTEGRATION_PROVIDER_NAME"
    log_error "\NC_INTEGRATION_TOKEN_EXCHANGE"
    help
    exit 1
  fi
  NC_INTEGRATION_SETUP_DATA="${NC_INTEGRATION_SETUP_DATA},
    \"oidc_provider\": \"${NC_INTEGRATION_PROVIDER_NAME}\",
    \"token_exchange\": ${NC_INTEGRATION_TOKEN_EXCHANGE}"
  if [[ "$NC_INTEGRATION_TOKEN_EXCHANGE" == "true" ]]; then
    if [[ -z $NC_INTEGRATION_OP_CLIENT_ID ]]; then
      log_error "'NC_INTEGRATION_OP_CLIENT_ID' is required with token exchange enabled."
      help
      exit 1
    fi
    NC_INTEGRATION_SETUP_DATA="${NC_INTEGRATION_SETUP_DATA}, \"targeted_audience_client_id\": \"${NC_INTEGRATION_OP_CLIENT_ID}\""
  fi
fi

# Validate configs required for external provider setup
if [[ "$NC_INTEGRATION_PROVIDER_TYPE" == "external" ]]; then
  if [[ -z $NC_USER_OIDC_PROVIDER_NAME || -z $NC_USER_OIDC_CLIENT_ID || -z $NC_USER_OIDC_CLIENT_SECRET || -z $NC_USER_OIDC_DISCOVERY_URL ]]; then
    log_error "Following configs are required for external OIDC provider setup:"
    log_error "\tNC_USER_OIDC_PROVIDER_NAME"
    log_error "\tNC_USER_OIDC_CLIENT_ID"
    log_error "\tNC_USER_OIDC_CLIENT_SECRET"
    log_error "\tNC_USER_OIDC_DISCOVERY_URL"
    help
    exit 1
  fi
fi

# Request configurations
NC_BASIC_AUTH="${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD}"
NC_API_HEADER="OCS-APIRequest: true"
NC_CONTENT_TYPE_HEADER="Content-Type: application/json"

nc_check_install_status() {
  log_file "[INFO] Checking Nextcloud installation status..."
  local nc_status
  local request
  local response
  request="GET ${NEXTCLOUD_HOST}/status.php"
  log_req "$request"

  response=$($CURL -X$request)
  nc_status=$(get_body "${response}" | jq -r ".installed")
  log_res "$response"

  if [[ "$nc_status" != "true" ]]; then
    local message
    message="Nextcloud is not installed or not reachable at ${NEXTCLOUD_HOST}"
    log_error "$message"
    log_file "[ERROR] $message"
    exit 1
  fi
  log_file "[INFO] Nextcloud installation status: $nc_status"
}

NC_OIDC_APP_ENDPOINT="${NEXTCLOUD_HOST}/index.php/apps/oidc"
NC_OP_OIDC_CLIENT_ID=
NC_OP_OIDC_CLIENT_SECRET=
NC_OP_OIDC_CLIENT_REDIRECT_URI=
NC_CURRENT_OIDC_CLIENT_DB_ID=
nc_add_op_oidc_client() {
  if [[ "$NC_INTEGRATION_PROVIDER_TYPE" != "nextcloud_hub" ]]; then
    return
  fi

  log_file "[INFO] Creating OpenProject OIDC client in Nextcloud..."
  local request
  local response
  local data
  local body
  local status
  data="{
    \"name\": \"openproject\",
    \"redirectUri\": \"${OPENPROJECT_HOST}/auth/oidc-nextcloud/callback\",
    \"signingAlg\": \"RS256\",
    \"type\": \"confidential\"
  }"
  data="$(echo "$data" | jq -c .)"

  request="POST ${NC_OIDC_APP_ENDPOINT}/clients"
  log_req "$request" "$data"

  response=$($CURL -X$request -u"$NC_BASIC_AUTH" -H"$NC_API_HEADER" -H"$NC_CONTENT_TYPE_HEADER" -d"$data")
  log_res "$response"

  body=$(get_body "${response}")
  status=$(get_statuscode "${response}")

  if [[ "$status" != "200" ]]; then
    local message="Failed to create OpenProject OIDC client in Nextcloud."
    log_error "$message"
    log_error "See logs: ${INTEGRATION_SETUP_LOG_FILE}"
    log_file "[ERROR] $message"
    exit 1
  fi

  NC_OP_OIDC_CLIENT_ID=$(echo "$body" | jq -r '.clientId')
  NC_OP_OIDC_CLIENT_SECRET=$(echo "$body" | jq -r '.clientSecret')
  NC_OP_OIDC_CLIENT_REDIRECT_URI=$(echo "$body" | jq -r '.redirectUris[0].redirect_uri')
  NC_CURRENT_OIDC_CLIENT_DB_ID=$(echo "$body" | jq -r '.id')

  # Add client ID to the integration setup data
  NC_INTEGRATION_SETUP_DATA="${NC_INTEGRATION_SETUP_DATA}, \"targeted_audience_client_id\": \"${NC_OP_OIDC_CLIENT_ID}\""
}

nc_delete_op_oidc_client() {
  log_file "[INFO] Deleting OpenProject OIDC client in Nextcloud..."
  local request
  local response
  local status

  request="DELETE ${NC_OIDC_APP_ENDPOINT}/clients/${NC_CURRENT_OIDC_CLIENT_DB_ID}"
  log_req "$request"

  response=$($CURL -X$request -u"$NC_BASIC_AUTH" -H"$NC_API_HEADER")
  log_res "$response"

  status=$(get_statuscode "${response}")
  if [[ "$status" != "200" ]]; then
    local message="Failed to delete OpenProject OIDC client in Nextcloud."
    log_error "$message"
    log_error "See logs: ${INTEGRATION_SETUP_LOG_FILE}"
    log_file "[ERROR] $message"
    exit 1
  fi
}

NC_USER_OIDC_APP_ENDPOINT="${NEXTCLOUD_HOST}/index.php/apps/user_oidc"
NC_CURRENT_PROVIDER_DB_ID=
nc_add_oidc_provider() {
  if [[ "$NC_INTEGRATION_PROVIDER_TYPE" != "external" ]]; then
    return
  fi

  log_file "[INFO] Adding OIDC provider in Nextcloud..."
  local request
  local response
  local data
  local body
  data="{
    \"identifier\": \"keycloak\",
    \"clientId\": \"nextcloud\",
    \"clientSecret\": \"secret\",
    \"discoveryEndpoint\": \"<discovery_url>\",
    \"scope\": \"openid email profile\"
  }"
  data=$(echo "$data" | jq -c .)

  request="POST ${NC_USER_OIDC_APP_ENDPOINT}/provider"
  log_req "$request" "$data"

  response=$($CURL -X$request -u"$NC_BASIC_AUTH" -H"$NC_API_HEADER" -H"$NC_CONTENT_TYPE_HEADER" -d"$data")
  log_res "$response"

  body=$(get_body "${response}")
  NC_CURRENT_PROVIDER_DB_ID=$(echo "$body" | jq -r '.id')
  if [[ -z $NC_CURRENT_PROVIDER_DB_ID ]]; then
    local message="Failed to add OIDC provider in Nextcloud."
    log_error "$message"
    log_error "See logs: ${INTEGRATION_SETUP_LOG_FILE}"
    log_file "[ERROR] $message"
    exit 1
  fi
}

nc_delete_oidc_provider() {
  log_file "[INFO] Deleting OIDC provider in Nextcloud..."
  local request
  local response
  local status

  request="DELETE ${NC_USER_OIDC_APP_ENDPOINT}/provider/${NC_CURRENT_PROVIDER_DB_ID}"
  log_req "$request"

  response=$($CURL -X$request -u"${NC_BASIC_AUTH}" -H"$NC_API_HEADER")
  log_res "$response"

  status=$(get_statuscode "${response}")
  if [[ "$status" != "200" ]]; then
    local message="Failed to delete OIDC provider in Nextcloud."
    log_error "$message"
    log_error "\tStatus code: ${status}"
    log_file "[ERROR] $message"
    exit 1
  fi
}

NC_INTEGRATION_ENDPOINT="${NEXTCLOUD_HOST}/index.php/apps/integration_openproject"
nc_setup_integration() {
  log_file "[INFO] Setting up integration in Nextcloud..."
  local request
  local response
  local body
  local data

  data=$(echo "{\"values\":{$NC_INTEGRATION_SETUP_DATA}}" | jq -c .)
  request="POST ${NC_INTEGRATION_ENDPOINT}/setup"
  log_req "$request" "$data"

  response=$($CURL -X$request -u"${NC_BASIC_AUTH}" -H"$NC_API_HEADER" -H"$NC_CONTENT_TYPE_HEADER" -d"$data")
  log_res "$response"

  body=$(get_body "${response}")
}

set -e
nc_check_install_status
nc_add_op_oidc_client
nc_add_oidc_provider
nc_setup_integration
