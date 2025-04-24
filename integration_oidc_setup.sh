# SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-only
#!/usr/bin/env bash

# This bash script is to set up the whole OIDC configuration between OpenProject and Nextcloud.
# To run this script the Nextcloud and OpenProject instances must be up and running
# If you're using Nextcloud as the Identity Provider (OIDC), make sure the following apps are installed and enabled in Nextcloud:
#   - oidc
#   - integration_openproject
#
# If you are using a custom Identity Provider, ensure that:
#   - user_oidc app is installed and enabled in Nextcloud.
#   - The custom IdP is properly configured and accessible.

set -e

INTEGRATION_SETUP_DEFAULT_TEMP_DIR=./temp

# helper functions
help() {
  echo -e "Available environment variables:"
  echo -e "Nextcloud:"
  echo -e "\t NC_HOST \t\t\t Nextcloud host URL"
  echo -e "\t NC_ADMIN_USERNAME \t\t\t Nextcloud admin username"
  echo -e "\t NC_ADMIN_PASSWORD \t\t\t Nextcloud admin password"
  echo -e "\t NC_INTEGRATION_PROVIDER_TYPE \t\t Single Sign-On provider type ('nextcloud_hub' or 'external')"
  echo -e "\t NC_INTEGRATION_PROVIDER_NAME \t\t SSO Provider name (Not required when using 'nextcloud_hub' type)"
  echo -e "\t NC_INTEGRATION_OP_CLIENT_ID \t\t OpenProject client ID (Not required when using 'nextcloud_hub' type)"
  echo -e "\t NC_INTEGRATION_TOKEN_EXCHANGE \t\t Enable token exchange (true/false) (Not required when using 'nextcloud_hub' type)"
  echo -e "\t NC_INTEGRATION_ENABLE_NAVIGATION \t Enable navigate to OpenProject header (true/false)"
  echo -e "\t NC_INTEGRATION_ENABLE_SEARCH \t\t Enable unified search (true/false)"
  echo -e "\t SETUP_PROJECT_FOLDER \t Enable project folder setup (true/false). Default: false"
  echo -e ""
  echo -e "OpenProject:"
  echo -e "\t OP_HOST \t OpenProject host URL"
  echo -e "\t OP_ADMIN_USERNAME \t OpenProject admin username"
  echo -e "\t OP_ADMIN_PASSWORD \t OpenProject admin password"
  echo -e "\t OP_STORAGE_NAME \t OpenProject file storage name (eg: nextcloud)"
  echo -e "\t OP_USE_LOGIN_TOKEN \t Use first access token obtained by IDP (true/false). Default: false"
  echo -e "\t OP_STORAGE_AUDIENCE \t Name of the storage audience (Not required when using 'OP_USE_LOGIN_TOKEN=true')"
}

log_error() {
  echo -e "\e[31m$1\e[0m"
}

log_info() {
  echo -e "\e[37m$1\e[0m"
}

log_success() {
  echo -e "\e[32m$1\e[0m"
}

if [[ -z "$NC_HOST" || -z "$OP_HOST" ]]; then
  log_error "Nextcloud and OpenProject host URLs are required."
  help
  exit 1
fi

if [[ -z "$NC_ADMIN_USERNAME" || -z "$NC_ADMIN_PASSWORD" ]]; then
  log_error "Nextcloud admin username and password are required."
  help
  exit 1
fi

if [[ -z "$OP_ADMIN_USERNAME" || -z "$OP_ADMIN_PASSWORD" ]]; then
  log_error "Openproject admin username and password are required."
  help
  exit 1
fi

if [[ $INTEGRATION_SETUP_TEMP_DIR == ""  ]]; then
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

# Validate required configs for integration setup
if [[ -z $NC_INTEGRATION_PROVIDER_TYPE ]] ||
  [[ -z $NC_INTEGRATION_ENABLE_NAVIGATION ]] ||
  [[ -z $NC_INTEGRATION_ENABLE_SEARCH ]] ; then
  log_error "Following configs are required for integration setup:"
  log_error "\tNC_INTEGRATION_ENABLE_NAVIGATION"
  log_error "\tNC_INTEGRATION_ENABLE_SEARCH"
  log_error "\tNC_INTEGRATION_PROVIDER_TYPE"
  help
  exit 1
fi

# Validate required configs for OpenProject setup
if [[ -z $OP_STORAGE_NAME ]]; then
  log_error "Following configs are required for OpenProject setup:"
  log_error "\tOP_STORAGE_NAME"
  help
  exit 1
fi

if [[ -z $OP_USE_LOGIN_TOKEN ]] || [[ "$OP_USE_LOGIN_TOKEN" == "false" ]]; then
  if [[ -z $OP_STORAGE_AUDIENCE ]]; then
    log_error "'OP_STORAGE_AUDIENCE' is required for OpenProject setup."
  fi
elif [[ "$OP_USE_LOGIN_TOKEN" == "true" ]]; then
  OP_STORAGE_AUDIENCE="__op-idp__"
  log_info "Setting OpenProject storage to use first access token"
fi

# Support for "Debug mode"
if [[ $INTEGRATION_SETUP_DEBUG == "true"  ]]; then
  log_info "Debug mode is enabled"
  set -x
  set -v
fi

if [[ -z "$SETUP_PROJECT_FOLDER" || "$SETUP_PROJECT_FOLDER" == "false" ]]; then
  # Integration Defaults settings
  SETUP_PROJECT_FOLDER=false
  SETUP_APP_PASSWORD=false
  log_info "Setting up integration without project folders"
elif [[ "$SETUP_PROJECT_FOLDER" == "true" ]]; then
  SETUP_APP_PASSWORD=true
fi

# Nextcloud Variables
# These URLs are just to check if the Nextcloud instances have been started or not before running the script
NC_HOST_STATUS=$(curl -s -X GET "${NC_HOST}/status.php")
NC_HOST_INSTALLED=$(echo $NC_HOST_STATUS | jq -r ".installed")
NC_HOST_MAINTENANCE=$(echo $NC_HOST_STATUS | jq -r ".maintenance")
# Nextcloud app endpoints
NC_OIDC_BASE_URL=${NC_HOST}/index.php/apps/oidc
NC_INTEGRATION_BASE_URL=${NC_HOST}/index.php/apps/integration_openproject
# OIDC client name used in Nextcloud for OpenProject
NC_OIDC_OP_CLIENT_NAME="openproject"

# OpenProject Variables
# These URLs are just to check if the Openproject instances have been started or not before running the script
OP_STORAGE_ENDPOINT="${OP_HOST}/api/v3/storages"
OP_HOST_CONFIG=$(curl -s -X GET -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} ${OP_HOST}/api/v3/configuration)
# Minimum OpenProject version required to run this script
OP_MINIMUM_VERSION="15.5.0"

# Helper function for OpenProject
opCheckIntegrationConfiguration() {
  op_status=
  op_storages_response=$(curl -s -X GET -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
      ${OP_STORAGE_ENDPOINT} \
      -H 'accept: application/hal+json' \
      -H 'Content-Type: application/json' \
      -H 'X-Requested-With: XMLHttpRequest'
    )
    op_storage_status=$(echo $op_storages_response | jq -r --arg name "$OP_STORAGE_NAME" '.["_embedded"].elements[] | select(.name == $name) | .configured')
    has_nc_app_password=$(echo $op_storages_response | jq -r --arg name "$OP_STORAGE_NAME" '.["_embedded"].elements[] | select(.name == $name) | .hasApplicationPassword')
  if [[ ${SETUP_PROJECT_FOLDER} == 'true' ]]; then
    if [[ ${op_storage_status} == 'true' && ${has_nc_app_password} == 'true' ]]; then
      op_status=0
    else
      op_status=1
    fi
  elif [[ ${op_storage_status} == 'true' ]]; then
    op_status=0
  else
    op_status=1
  fi
  if [[ "$op_status" -ne 0 ]]; then
    log_info "File storage name '$OP_STORAGE_NAME' in OpenProject already exists."
    log_error "File storage '$OP_STORAGE_NAME' configuration is incomplete in OpenProject '${OP_HOST}' for integration with Nextcloud."
    if [[ ${SETUP_PROJECT_FOLDER} == 'true' ]]; then
      log_error "Or the application password has not been set in 'OpenProject' '${OP_HOST}'."
    fi
    log_info "You could try deleting the file storage '${OP_STORAGE_NAME}' in OpenProject and run the script again."
    exit 1
  fi
  log_success "File storage name '$OP_STORAGE_NAME' in OpenProject for integration with Nextcloud is already configured."
}

# if something goes wrong or an error occurs during the setup of the whole integration
# we can delete the storage created in OpenProject, otherwise it would need to be deleted manually when the script is run the next time
opDeleteStorage() {
  log_error "Setup of OpenProject and Nextcloud integration failed when the OIDC Provider Type is '$NC_INTEGRATION_PROVIDER_TYPE'."
  delete_storage_response=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
                      ${OP_STORAGE_ENDPOINT}/${OP_STORAGE_ID} \
                      -H 'accept: application/hal+json' \
                      -H 'Content-Type: application/json' \
                      -H 'X-Requested-With: XMLHttpRequest')
  if [[ ${delete_storage_response} == 204 ]]; then
      log_info "File storage name \"${OP_STORAGE_NAME}\" has been deleted from OpenProject!"
  else
      log_info "Failed to delete file storage \"${OP_STORAGE_NAME}\" from OpenProject!"
  fi
}

# Helper function for nextcloud
ncCheckIntegrationConfiguration() {
  nc_integration_config_ok=
  nc_integration_config_response=$(curl -s -X GET -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} \
          ${NC_INTEGRATION_BASE_URL}/check-admin-config \
          -H 'accept: application/hal+json' \
          -H 'Content-Type: application/json' \
          -H 'X-Requested-With: XMLHttpRequest')
  nc_setup_without_project_folder=$(echo $nc_integration_config_response | jq -r ".config_status_without_project_folder")
  nc_project_folder_status=$(echo $nc_integration_config_response | jq -r ".project_folder_setup_status")
  if [[ ${SETUP_PROJECT_FOLDER} == 'true' ]]; then
    if [[ ${nc_project_folder_status} == 'true' && ${nc_setup_without_project_folder} == 'true' ]]; then
      nc_integration_config_ok=0
    else
      nc_integration_config_ok=1
    fi
  elif [[ ${nc_setup_without_project_folder} == 'true' ]]; then
    nc_integration_config_ok=0
  else
    nc_integration_config_ok=1
  fi
  if [[ "$nc_integration_config_ok" -ne 0 ]]; then
    log_error "Some admin configuration is incomplete in Nextcloud '${NC_HOST}' for integration with OpenProject."
    if [[ ${SETUP_PROJECT_FOLDER} == 'true' ]]; then
      log_error "Or project folder setup might be missing in Nextcloud '${NC_HOST}'."
    fi
    log_info "You could try deleting the file storage '${OP_STORAGE_NAME}' in OpenProject and run the script again."
    exit 1
  fi
  log_success "Admin configuration in Nextcloud for integration with OpenProject is configured."
}

ncCheckAppVersion() {
  # checks the version of a specified Nextcloud app.
  app_name=$1
  
  # assign app_miniimum version 
  if [ $app_name = 'user_oidc' ]; then
    app_min_version=$MIN_SUPPORTED_USER_OIDC_APP_VERSION
  elif [ $app_name = 'oidc' ]; then
    app_min_version=$MIN_SUPPORTED_OIDC_APP_VERSION
  elif [ $app_name = 'integration_openproject' ]; then
    app_min_version=$MIN_SUPPORTED_INTEGRATION_APP_VERSION
  else
    log_error "Minimum required version for the '$app_name' app is not set."
    exit 1
  fi

  nc_apps_version_response=$(curl -s -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} "${NC_HOST}/ocs/v2.php/cloud/apps/$app_name?format=json" \
    -H 'OCS-APIRequest: true' \
  )

  nc_app_version=$(echo $nc_apps_version_response | jq -r ".ocs.data.version")

  if [ -z "$nc_app_version" ]; then
    log_error "Failed to get the version information for the '$app_name' app. This might indicate that the app does not exist or is not enabled"
    exit 1
  elif [[ "$nc_app_version" < "$app_min_version" ]]; then
   log_error "This script requires $app_name apps Version greater than or equal to '$app_min_version' but found version '$nc_app_version'"
   exit 1
  fi
}

# delete oidc client [oidc apps]
deleteOidcClient() {
    nc_delete_client_response=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
                      ${NC_OIDC_BASE_URL}/clients/$1 \
                      -H 'Content-Type: application/json' \
                      -H 'OCS-APIRequest: true')
    if [[ ${nc_delete_client_response} != 200 ]]; then
      log_error "Failed to delete oidc client name \"${NC_OIDC_OP_CLIENT_NAME}\" from oidc app!"
    fi
}

createOidcClient() {
  # make an api request to create the oidc client in oidc App
  cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_1_oidc_create_oidc_client.json <<EOF
{
  "name": "$NC_OIDC_OP_CLIENT_NAME",
  "redirectUri": "$OP_HOST/auth/oidc-nextcloud/callback",
  "signingAlg": "RS256",
  "type": "confidential"
}
EOF

  nc_oidc_client_response=$(curl -s -XPOST -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} "${NC_OIDC_BASE_URL}/clients" \
    -H 'Content-Type: application/json' \
    -H 'OCS-APIRequest: true' \
    -d @"${INTEGRATION_SETUP_TEMP_DIR}/request_body_1_oidc_create_oidc_client.json"
  )

  if [[ $INTEGRATION_SETUP_DEBUG != "true"  ]] ; then rm ${INTEGRATION_SETUP_TEMP_DIR}/request_body_1_oidc_create_oidc_client.json; fi

  if ! [[ $(echo "$nc_oidc_client_response" | jq -r '.name') = "$NC_OIDC_OP_CLIENT_NAME" ]]; then
    log_info "The response is missing openproject_client_name"
    log_error "[OIDC app]:Failed when creating '$NC_OIDC_OP_CLIENT_NAME' oidc client";
    exit 1
  fi

  # required information from the above response
  NC_INTEGRATION_OP_CLIENT_ID=$(echo $nc_oidc_client_response | jq -r '.clientId')
  NC_OIDC_OP_CLIENT_ID=$(echo $nc_oidc_client_response | jq -r '.id')
}

logUnhandledError() {
  log_error "Unhandled error while creating the file storage '${OP_STORAGE_NAME}'"
  log_error "OpenProject returned the following error: '${error_message}'"
  log_info "You could try deleting the file storage '${OP_STORAGE_NAME}' in OpenProject and run the script again."
}

logCompleteIntegrationConfiguration() {
  log_success "Setup of OpenProject and Nextcloud is complete."
  exit 0
}

logAlreadyCompletedIntegrationConfiguration() {
  log_success "Setup of OpenProject and Nextcloud is already completed."
  log_info "Note: To add the Nextcloud storage, you could try deleting the '${OP_STORAGE_NAME}' file storage from OpenProject, reset the Nextcloud config, and run the script again."
  exit 0
}

# This script requires minimum versions of Nextcloud apps: OIDC, User OIDC, and OpenProject integration
MIN_SUPPORTED_USER_OIDC_APP_VERSION="7.1.0"
MIN_SUPPORTED_OIDC_APP_VERSION="1.5.0"
MIN_SUPPORTED_INTEGRATION_APP_VERSION="2.9.0"
ncCheckAppVersion "oidc"
ncCheckAppVersion "user_oidc"
ncCheckAppVersion "integration_openproject"

# check if OpenProject and nextcloud instances are started or not
# OpenProject instances
if [[ $(echo $OP_HOST_CONFIG | jq -r "._type") != "Configuration" ]]; then
  if [[ $(echo $OP_HOST_CONFIG | jq -r ".errorIdentifier") == "urn:openproject-org:api:v3:errors:Unauthenticated" ]]; then
    log_error "OpenProject authentication failed!"
    exit 1
  else
    log_error "Unhandled error checking for OpenProject's availability, please checkout the request's response:"
    log_error "$OP_HOST_CONFIG"
    exit 1
  fi
fi
# nextcloud instances
if [[ ${NC_HOST_INSTALLED} != "true" || ${NC_HOST_MAINTENANCE} != "false" ]]; then
  log_error "Nextcloud host cannot be reached or is in maintenance mode!"
  exit 1
fi

# This script requires OpenProject Version >= OP_MINIMUM_VERSION
openproject_info_response=$(curl -s -X GET -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} ${OP_HOST}/api/v3)
openproject_version=$(echo $openproject_info_response | jq -r ".coreVersion")
if [[ "$openproject_version" < "$OP_MINIMUM_VERSION" ]]; then
  log_error "This script requires OpenProject Version greater than or equal to '$OP_MINIMUM_VERSION' but found version '$openproject_version'"
  exit 1
fi

# API call to add storage
cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_op_create_storage.json <<EOF
{
  "name": "${OP_STORAGE_NAME}",
  "storageAudience": "${OP_STORAGE_AUDIENCE}",
  "applicationPassword": "",
  "_links": {
    "origin": {
      "href": "${NC_HOST}"
    },
    "type": {
      "href": "urn:openproject-org:api:v3:storages:Nextcloud"
    },
    "authenticationMethod": {
      "href": "urn:openproject-org:api:v3:storages:authenticationMethod:OAuth2SSO"
    }
  }
}
EOF

create_storage_response=$(curl -s -X POST -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
  ${OP_STORAGE_ENDPOINT} \
  -H 'accept: application/hal+json' \
  -H 'Content-Type: application/json' \
  -H 'X-Requested-With: XMLHttpRequest' \
  -d @${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_op_create_storage.json
)

if [[ $INTEGRATION_SETUP_DEBUG != "true"  ]]; then rm ${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_op_create_storage.json; fi
# check for errors
response_type=$(echo $create_storage_response | jq -r "._type")
if [[ ${response_type} == "Error" ]]; then
  error_message=$(echo $create_storage_response | jq -r ".message")
  error_id=$(echo $create_storage_response | jq -r ".errorIdentifier")
  if [[ ${error_id} == "urn:openproject-org:api:v3:errors:MultipleErrors" ]]; then
    # If the files storage is already created with the provided Nextcloud host and storage name.
    # We assume that the integration setup is already done in both applications.
    # To check that, we parse the error messages.
    # If there are only two error messages and those are about the Nextcloud host and name being taken.
    # We assume the setup was already completed.
    error_messages_grep=$(echo $create_storage_response | jq -r '.["_embedded"]["errors"] | .[].message')
    readarray -t error_messages <<<"$error_messages_grep"
    error_count=0
    host_already_taken=false
    name_already_taken=false
    for element in "${error_messages[@]}"; do
      if [[ "$element" == "Host has already been taken." ]]; then
        host_already_taken=true
      elif [[ "$element" == "Name has already been taken." ]]; then
        name_already_taken=true
      fi
      (( error_count +=1 ))
    done
    if [[ $host_already_taken != true || $name_already_taken != true || "$error_count" -ne 2  ]]; then
      logUnhandledError
      exit 1
    fi
    opCheckIntegrationConfiguration
    ncCheckIntegrationConfiguration
    logAlreadyCompletedIntegrationConfiguration
  elif [[ ${error_id} == "urn:openproject-org:api:v3:errors:PropertyConstraintViolation" ]]; then
    # A PropertyConstraintViolation is always a single error
    error_messages_grep=$(echo $create_storage_response | jq -r '.message')
    if [[ "$error_messages_grep" == "Host has already been taken." ||  "$error_messages_grep" == "Name has already been taken." ]]; then
      opCheckIntegrationConfiguration
      ncCheckIntegrationConfiguration
      logAlreadyCompletedIntegrationConfiguration
    else
      logUnhandledError
      exit 1
    fi
  elif [[ ${error_id} == "urn:openproject-org:api:v3:errors:Unauthenticated" ]]; then
    log_error "Authorization failed. Ensure you have created a valid BASIC AUTH API account, e.g. utilising the following env variables:"
    log_info "OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_USER=<basic_auth_api_username>"
    log_info "OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_PASSWORD=<basic_auth_api_password>"
  else
    logUnhandledError
  fi
  exit 1
fi

# Required information from the above response
OP_STORAGE_ID=$(echo $create_storage_response | jq -e '.id')

if [[ ${OP_STORAGE_ID} == null ]]; then
  echo "${create_storage_response}" | jq
  log_error "Response does not contain $OP_STORAGE_ID (id)."
  log_error "Setup of OpenProject and Nextcloud integration failed."
  exit 1
fi

log_success "Creating file storage name \"${OP_STORAGE_NAME}\" in OpenProject was successful."

# If the OIDC provider is nextcloud_hub, create a new OIDC client.
# Otherwise, use the OpenProject client ID provided via environment variable.
if [[ $NC_INTEGRATION_PROVIDER_TYPE == "nextcloud_hub" ]]; then
  createOidcClient
elif [[ "$NC_INTEGRATION_PROVIDER_TYPE" == "external" ]] && 
    [[ -z $NC_INTEGRATION_PROVIDER_NAME || -z $NC_INTEGRATION_TOKEN_EXCHANGE ]]; then
    log_error "Following configs are required to setup integration with external provider:"
    log_error "\tNC_INTEGRATION_PROVIDER_NAME"
    log_error "\tNC_INTEGRATION_TOKEN_EXCHANGE"
    help
    exit 1
elif [[ "$NC_INTEGRATION_TOKEN_EXCHANGE" == "true" ]] && [[ -z $NC_INTEGRATION_OP_CLIENT_ID ]]; then
    log_error "'NC_INTEGRATION_OP_CLIENT_ID' is required with token exchange enabled."
    help
    exit 1
fi

cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_nc_integration_setup.json <<EOF
{
  "values":{
    "openproject_instance_url": "$OP_HOST",
    "sso_provider_type": "$NC_INTEGRATION_PROVIDER_TYPE", 
    "authorization_method": "oidc",
    "targeted_audience_client_id" : "$NC_INTEGRATION_OP_CLIENT_ID",
    "default_enable_navigation": $NC_INTEGRATION_ENABLE_SEARCH,
    "default_enable_unified_search": $NC_INTEGRATION_ENABLE_SEARCH,
    "setup_project_folder": $SETUP_PROJECT_FOLDER,
    "setup_app_password": $SETUP_APP_PASSWORD
  }
}
EOF

# If the OIDC provider is Keycloak, update the Nextcloud OIDC setup request body.
# Adds Keycloak-specific configuration including token exchange and sets the SSO provider type to "external".
# If OIDC provider is keycloak, add provider-specific values to the JSON
if [[ $NC_INTEGRATION_PROVIDER_TYPE != "nextcloud_hub" ]]; then
  jq --arg nc_integration_provider_name "$NC_INTEGRATION_PROVIDER_NAME" \
     --argjson nc_integration_token_exchange "$NC_INTEGRATION_TOKEN_EXCHANGE" \
     '.values += {
        "oidc_provider": $nc_integration_provider_name,
        "token_exchange": $nc_integration_token_exchange
      }' \
     "${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_nc_integration_setup.json" > tempEdit.json

  mv tempEdit.json "${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_nc_integration_setup.json"
fi

# API call to set the  openproject_client_id and openproject_client_secret to Nextcloud [integration_openproject]
nc_integration_setup_response=$(curl -s -XPOST -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} "${NC_INTEGRATION_BASE_URL}/setup" \
  -H 'Content-Type: application/json' \
  -d @${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_nc_integration_setup.json
)

if [[ $INTEGRATION_SETUP_DEBUG != "true"  ]] ; then rm ${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_nc_integration_setup.json; fi

if [[ "$nc_integration_setup_response" != *"nextcloud_oauth_client_name"* ]] && \
    [[ "$nc_integration_setup_response" != *"openproject_redirect_uri"* ]]; then

    log_info "The response is missing nextcloud_oauth_client_name or openproject_redirect_uri"
    log_error "Failed to setup OIDC from Nextcloud."
    opDeleteStorage "$nc_integration_setup_response"

    if [[ "$SETUP_PROJECT_FOLDER" == "true" && "$nc_integration_setup_response" != *"openproject_user_app_password"* ]]; then
      log_info "If the error response is related to project folder setup name 'OpenProject' (group, user, folder),"
      log_info "Then follow the link https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#troubleshooting to resolve the error."
    elif [[ $NC_INTEGRATION_PROVIDER_TYPE = "nextcloud_hub" ]]; then
      deleteOidcClient "$NC_OIDC_OP_CLIENT_ID"
    fi
    exit 1
fi

if [[ ${SETUP_PROJECT_FOLDER} == true ]]; then
  NC_APP_PASSWORD=$(echo $nc_integration_setup_response | jq -e ".openproject_user_app_password")
  log_info "Setting up with project folder was successful"
fi

log_success "Setting up OpenProject oidc configuration where OIDC Provider Type is $NC_INTEGRATION_PROVIDER_TYPE in Nextcloud was successful."

# Save the application password to OpenProject
if [[ ${SETUP_PROJECT_FOLDER} == "true" ]]; then
  cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_op_set_project_folder_app_password.json <<EOF
  {
    "applicationPassword": ${NC_APP_PASSWORD}
  }
EOF
  op_save_nc_app_password_response=$(curl -s -XPATCH -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
                                    ${OP_STORAGE_ENDPOINT}/${OP_STORAGE_ID} \
                                    -H 'accept: application/hal+json' \
                                    -H 'Content-Type: application/json' \
                                    -H 'X-Requested-With: XMLHttpRequest' \
                                    -d @${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_op_set_project_folder_app_password.json
  )
  if [[ $INTEGRATION_SETUP_DEBUG != "true"  ]] ; then rm ${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_op_set_project_folder_app_password.json; fi

  if [[ "$op_save_nc_app_password_response" == *"_type"* ]]; then
    app_password_response_type=$(echo $op_save_nc_app_password_response | jq -r "._type")
    if [[ ${app_password_response_type} == "Error" ]]; then
      opDeleteStorage "$op_save_nc_app_password_response"
      exit 1
    fi
    has_application_password=$(echo $op_save_nc_app_password_response | jq -r ".hasApplicationPassword")
    if [[ ${has_application_password} == false ]]; then
      opDeleteStorage "$op_save_nc_app_password_response"
      exit 1
    fi
    log_success "Saving 'OpenProject' user application password to OpenProject was successful."
  fi
fi

logCompleteIntegrationConfiguration