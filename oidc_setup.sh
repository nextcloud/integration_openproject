# SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
# SPDX-FileCopyrightText: 2023 Bundesministerium des Innern und für Heimat, PG ZenDiS "Projektgruppe für Aufbau ZenDiS"
# SPDX-FileCopyrightText: 2023 Nextcloud GmbH
# SPDX-License-Identifier: AGPL-3.0-only
#!/usr/bin/env bash

# This bash script is to set up the whole OIDC configuration between OpenProject and Nextcloud.
# To run this script the Nextcloud and OpenProject instances must be up and running

# variables from environment
# NEXTCLOUD_HOST=<nextcloud_host_url>
# OPENPROJECT_HOST=<openproject_host_url>
# OP_ADMIN_USERNAME=<openproject_admin_username>
# OP_ADMIN_PASSWORD=<openproject_admin_password>
# NC_ADMIN_USERNAME=<nextcloud_admin_username>
# NC_ADMIN_PASSWORD=<nextcloud_admin_password>
# OIDC_PROVIDER=<nextcloud|keycloak> This variable is the name of the idp provider which makes the oidc configuration in OpenProject required for Nextcloud integration.
# NC_TOKEN_EXCHANGE=<true|false> If true then the token exchange is done
# OPENPROJECT_STORAGE_NAME=<openproject_filestorage_name> This variable is the name of the storage which keeps the oauth configuration in OpenProject required for Nextcloud integration.
# SETUP_PROJECT_FOLDER=<true|false> If true then the integration is done with a project folder setup in Nextcloud
# INTEGRATION_SETUP_DEBUG=<true|false> If true then the script will output more details (set -x/-v) and keep the payload files
# INTEGRATION_SETUP_TEMP_DIR=<pre-existing_directory> The payloads sent to the APIs are written into temporary files, by default ./temp, you can specify a custom directory that is expected to pre-exist e.g. when bootstrapping in a K8s job with ephemeral storage.

set -e

if [ -z "$NEXTCLOUD_HOST" ]; then
    echo "Missing nextcloud host. Provide space separated list using 'NEXTCLOUD_HOST' env."
    exit 1
fi

if [ -z "$OPENPROJECT_HOST" ]; then
    echo "Missing php versions. Provide space separated list using 'OPENPROJECT_HOST' env."
    exit 1
fi

INTEGRATION_SETUP_DEFAULT_TEMP_DIR=./temp

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

# Support for "Debug mode"
if [[ $INTEGRATION_SETUP_DEBUG == "true"  ]]; then
  log_info "Debug mode is enabled"
  set -x
  set -v
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

# if something goes wrong or an error occurs during the setup of the whole integration
# we can delete the storage created in OpenProject, otherwise it would need to be deleted manually when the script is run the next time
deleteOPStorageAndPrintErrorResponse() {
  log_error "Setup of OpenProject and Nextcloud integration failed when the idp is '$OIDC_PROVIDER'."
  delete_storage_response=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
                      ${OPENPROJECT_BASEURL_FOR_STORAGE}/${storage_id} \
                      -H 'accept: application/hal+json' \
                      -H 'Content-Type: application/json' \
                      -H 'X-Requested-With: XMLHttpRequest')
  if [[ ${delete_storage_response} == 204 ]]; then
      log_info "File storage name \"${OPENPROJECT_STORAGE_NAME}\" has been deleted from OpenProject!"
  else
      log_info "Failed to delete file storage \"${OPENPROJECT_STORAGE_NAME}\" from OpenProject!"
  fi
}

# These URLs are just to check if the Nextcloud and OpenProject instances have been started or not before running the script
NEXTCLOUD_HOST_STATE=$(curl -s -X GET ${NEXTCLOUD_HOST}/status.php)
NEXTCLOUD_HOST_INSTALLED_STATE=$(echo $NEXTCLOUD_HOST_STATE | jq -r ".installed")
NEXTCLOUD_HOST_MAINTENANCE_STATE=$(echo $NEXTCLOUD_HOST_STATE | jq -r ".maintenance")
OPENPROJECT_BASEURL_FOR_STORAGE=${OPENPROJECT_HOST}/api/v3/storages
openproject_host_state_response=$(curl -s -X GET -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} ${OPENPROJECT_HOST}/api/v3/configuration)
# These two data are set to "false" when the integration is done without project folder setup
setup_project_folder=false
setup_app_password=false
# Minimum OpenProject version required in order to run this script.
MINIMUM_OP_VERSION="14.2.0"
# Openproject and Nextcloud Client name
openproject_client_name="openproject"
# These url are of the nextcloud apps
OIDC_BASE_URL=${NEXTCLOUD_HOST}/index.php/apps/oidc
NC_INTEGRATION_BASE_URL=${NEXTCLOUD_HOST}/index.php/apps/integration_openproject

checkNCAppVersion() {
  # checks the version of a specified Nextcloud app.
  # If the app version is below the required version, an error is logged and the script exists.
  app_name=$1
  
  # assign app_miniimum version 
  if [ $app_name = 'user_oidc' ]; then
    app_min_version=$MIN_SUPPORTED_USER_OIDC_APP_VERSION
  elif [ $app_name = 'oidc' ]; then
    app_min_version=$MIN_SUPPORTED_OIDC_APP_VERSION
  elif [ $app_name = 'integration_openproject' ]; then
    app_min_version=$MIN_SUPPORTED_INTEGRATION_OPENRPOJECT_APP_VERSION
  else
    log_error "Minimum required version for the '$app_name' app is not set."
    exit 1
  fi

  NC_apps_information_response=$(curl -s -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} "${NEXTCLOUD_HOST}/ocs/v2.php/cloud/apps/$app_name?format=json" \
    -H 'OCS-APIRequest: true' \
  )

  NC_apps_version=$(echo $NC_apps_information_response | jq -r ".ocs.data.version")

  if [ -z "$NC_apps_version" ]; then
    log_error "Failed to get the version information for the '$app_name' app. This might indicate that the app does not exist or is not enabled"
    exit 1
  elif [[ "$NC_apps_version" < "$app_min_version" ]]; then
   log_error "This script requires $app_name apps Version greater than or equal to '$app_min_version' but found version '$NC_apps_version'"
   exit 1
  fi
}


# This script requires minimum versions of Nextcloud apps: OIDC, User OIDC, and OpenProject integration
MIN_SUPPORTED_USER_OIDC_APP_VERSION="7.1.0"
MIN_SUPPORTED_OIDC_APP_VERSION="1.5.0"
MIN_SUPPORTED_INTEGRATION_OPENRPOJECT_APP_VERSION="2.9.0"
checkNCAppVersion "oidc"
checkNCAppVersion "user_oidc"
checkNCAppVersion "integration_openproject"

isNextcloudAdminConfigOk() {
  admin_config_response=$(curl -s -X GET -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} \
          ${NC_INTEGRATION_BASE_URL}/check-admin-config \
          -H 'accept: application/hal+json' \
          -H 'Content-Type: application/json' \
          -H 'X-Requested-With: XMLHttpRequest')
  config_status_without_project_folder=$(echo $admin_config_response | jq -r ".config_status_without_project_folder")
  project_folder_setup_status=$(echo $admin_config_response | jq -r ".project_folder_setup_status")
  if [[ ${SETUP_PROJECT_FOLDER} == 'true' ]]; then
    if [[ ${project_folder_setup_status} == 'true' && ${config_status_without_project_folder} == 'true' ]]; then
      echo 0
    else
      echo 1
    fi
  elif [[ ${config_status_without_project_folder} == 'true' ]]; then
    echo 0
  else
    echo 1
  fi
}

isOpenProjectFileStorageConfigOk() {
  all_file_storages_available_response=$(curl -s -X GET -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
      ${OPENPROJECT_BASEURL_FOR_STORAGE} \
      -H 'accept: application/hal+json' \
      -H 'Content-Type: application/json' \
      -H 'X-Requested-With: XMLHttpRequest'
    )
    oauth_configured_status=$(echo $all_file_storages_available_response | jq -r --arg name "$OPENPROJECT_STORAGE_NAME" '.["_embedded"].elements[] | select(.name == $name) | .configured')
    has_nc_application_password_status=$(echo $all_file_storages_available_response | jq -r --arg name "$OPENPROJECT_STORAGE_NAME" '.["_embedded"].elements[] | select(.name == $name) | .hasApplicationPassword')
  if [[ ${SETUP_PROJECT_FOLDER} == 'true' ]]; then
    if [[ ${oauth_configured_status} == 'true' && ${has_nc_application_password_status} == 'true' ]]; then
      echo 0
    else
      echo 1
    fi
  elif [[ ${oauth_configured_status} == 'true' ]]; then
    echo 0
  else
    echo 1
  fi
}

checkNextcloudIntegrationConfiguration() {
  status_nc=$(isNextcloudAdminConfigOk)
  if [[ "$status_nc" -ne 0 ]]; then
    log_error "Some admin configuration is incomplete in Nextcloud '${NEXTCLOUD_HOST}' for integration with OpenProject."
    if [[ ${SETUP_PROJECT_FOLDER} == 'true' ]]; then
      log_error "Or project folder setup might be missing in Nextcloud '${NEXTCLOUD_HOST}'."
    fi
    log_info "You could try deleting the file storage '${OPENPROJECT_STORAGE_NAME}' in OpenProject and run the script again."
    exit 1
  fi
  log_success "Admin configuration in Nextcloud for integration with OpenProject is configured."
}

# delete oidc client [oidc apps]
deleteOidcClient() {
    delete_clients_response=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
                      ${OIDC_BASE_URL}/clients/$1 \
                      -H 'Content-Type: application/json' \
                      -H 'OCS-APIRequest: true')
    if [[ ${delete_clients_response} != 200 ]]; then
      log_error "Failed to delete oidc client name \"${openproject_client_name}\" from oidc app!"
    fi
}

logUnhandledError() {
  log_error "Unhandled error while creating the file storage '${OPENPROJECT_STORAGE_NAME}'"
  log_error "OpenProject returned the following error: '${error_message}'"
  log_info "You could try deleting the file storage '${OPENPROJECT_STORAGE_NAME}' in OpenProject and run the script again."
}

logCompleteIntegrationConfiguration() {
  log_success "Setup of OpenProject and Nextcloud is complete."
  log_info "Note: To add the Nextcloud storage, you could try deleting the '${OPENPROJECT_STORAGE_NAME}' file storage from OpenProject, reset the Nextcloud config, and run the script again."
  exit 0
}

checkOpenProjectIntegrationConfiguration() {
  # At this point we know that the file storage already exists, so we only check if it is configured completely in OpenProject
  status_op=$(isOpenProjectFileStorageConfigOk)
  if [[ "$status_op" -ne 0 ]]; then
    log_info "File storage name '$OPENPROJECT_STORAGE_NAME' in OpenProject already exists."
    log_error "File storage '$OPENPROJECT_STORAGE_NAME' configuration is incomplete in OpenProject '${OPENPROJECT_HOST}' for integration with Nextcloud."
    if [[ ${SETUP_PROJECT_FOLDER} == 'true' ]]; then
      log_error "Or the application password has not been set in 'OpenProject' '${OPENPROJECT_HOST}'."
    fi
    log_info "You could try deleting the file storage '${OPENPROJECT_STORAGE_NAME}' in OpenProject and run the script again."
    exit 1
  fi
  log_success "File storage name '$OPENPROJECT_STORAGE_NAME' in OpenProject for integration with Nextcloud is already configured."
}

checkNextcloudIntegrationConfiguration() {
  status_nc=$(isNextcloudAdminConfigOk)
  if [[ "$status_nc" -ne 0 ]]; then
    log_error "Some admin configuration is incomplete in Nextcloud '${NEXTCLOUD_HOST}' for integration with OpenProject."
    if [[ ${SETUP_PROJECT_FOLDER} == 'true' ]]; then
      log_error "Or project folder setup might be missing in Nextcloud '${NEXTCLOUD_HOST}'."
    fi
    log_info "You could try deleting the file storage '${OPENPROJECT_STORAGE_NAME}' in OpenProject and run the script again."
    exit 1
  fi
  log_success "Admin configuration in Nextcloud for integration with OpenProject is already configured."
}

createOidcClient() {
  # make an api request to create the oidc client in oidc App
  cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_1_oidc_create_oidc_client.json <<EOF
{
  "name": "$openproject_client_name",
  "redirectUri": "$OPENPROJECT_HOST/auth/oidc-nextcloud/callback",
  "signingAlg": "RS256",
  "type": "confidential"
}
EOF

  oidc_information_response=$(curl -s -XPOST -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} "${OIDC_BASE_URL}/clients" \
    -H 'Content-Type: application/json' \
    -H 'OCS-APIRequest: true' \
    -d @"${INTEGRATION_SETUP_TEMP_DIR}/request_body_1_oidc_create_oidc_client.json"
  )

  if [[ $INTEGRATION_SETUP_DEBUG != "true"  ]] ; then rm ${INTEGRATION_SETUP_TEMP_DIR}/request_body_1_oidc_create_oidc_client.json; fi

  if ! [[ $(echo "$oidc_information_response" | jq -r '.name') = "$openproject_client_name" ]]; then
    log_info "The response is missing openproject_client_name"
    log_error "[OIDC app]:Failed when creating '$openproject_client_name' oidc client";
    exit 1
  fi

  # required information from the above response
  openproject_client_id=$(echo $oidc_information_response | jq -r '.clientId')
  openproject_id=$(echo $oidc_information_response | jq -r '.id')
}

enableStoreLoginTokens() {
  # make an api request to enable the store login tokens
  # This is needed if you are using other apps that want to use user_oidc's token exchange or simply get the login token
  cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_5_enable_store_login_token.json <<EOF
{
  "values":{
    "store_login_token": true
  }
}
EOF

  user_oidc_token_storage_status=$(curl -s -o /dev/null -w "%{http_code}" -X POST -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} \
                    ${NEXTCLOUD_HOST}/index.php/apps/user_oidc/admin-config \
                    -H 'Content-Type: application/json' \
                    -H 'OCS-APIRequest: true' \
                    -d @"${INTEGRATION_SETUP_TEMP_DIR}/request_body_5_enable_store_login_token.json" )

  if [[ $INTEGRATION_SETUP_DEBUG != "true"  ]] ; then rm ${INTEGRATION_SETUP_TEMP_DIR}/request_body_5_enable_store_login_token.json; fi

  if [[ $user_oidc_token_storage_status != 200 ]]; then
    log_error "[user_oidc apps] Failed to enable store login tokens"
    exit 1
  fi
}

# make an api request to register provider in user_oidc App
registerProviders() {
  cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_2_register_provider.json <<EOF
{
  "identifier": "Keycloak",                                
  "clientId": "nextcloud",    
  "clientSecret": "<nextcloud-client>",                                                                     
  "discoveryEndpoint": "$KEYCLOAK_BASE_URL/realms/$KEYCLOAK_REALM_NAME/.well-known/openid-configuration",
  "endSessionEndpoint": "",                       
  "scope": "openid email profile" 
}
EOF

  user_oidc_information_response=$(curl -s -o /dev/null -w "%{http_code}" -X POST -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} \
                    ${NEXTCLOUD_HOST}/index.php/apps/user_oidc/provider \
                    -H 'Content-Type: application/json' \
                    -H 'OCS-APIRequest: true' \
                    -d @"${INTEGRATION_SETUP_TEMP_DIR}/request_body_2_register_provider.json" )
  if [[ $INTEGRATION_SETUP_DEBUG != "true"  ]] ; then rm ${INTEGRATION_SETUP_TEMP_DIR}/request_body_2_register_provider.json; fi

  if [[ $user_oidc_information_response == 200 ]]; then
    log_success "${OIDC_PROVIDER} Provider was successfully registered"
  elif [[ $user_oidc_information_response == 409 ]]; then
    log_info "[user_oidc apps] Provider with the given identifier already exists"
  else
    log_error "[user_oidc apps] Failed to register provider"
    exit 1
  fi
}

# check if openproject and nextcloud instances are started or not
# openproject instances
if [[ $(echo $openproject_host_state_response | jq -r "._type") != "Configuration" ]]; then
  if [[ $(echo $openproject_host_state_response | jq -r ".errorIdentifier") == "urn:openproject-org:api:v3:errors:Unauthenticated" ]]; then
    log_error "OpenProject authentication failed!"
    exit 1
  else
    log_error "Unhandled error checking for OpenProject's availability, please checkout the request's response:"
    log_error "${$openproject_host_state_response}"
    exit 1
  fi
fi
# nextcloud instances
if [[ ${NEXTCLOUD_HOST_INSTALLED_STATE} != "true" || ${NEXTCLOUD_HOST_MAINTENANCE_STATE} != "false" ]]; then
  log_error "Nextcloud host cannot be reached or is in maintenance mode!"
  exit 1
fi

# This script requires OpenProject Version >= MINIMUM_OP_VERSION
openproject_info_response=$(curl -s -X GET -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} ${OPENPROJECT_HOST}/api/v3)
openproject_version=$(echo $openproject_info_response | jq -r ".coreVersion")
if [[ "$openproject_version" < "$MINIMUM_OP_VERSION" ]]; then
  log_error "This script requires OpenProject Version greater than or equal to '$MINIMUM_OP_VERSION' but found version '$openproject_version'"
  exit 1
fi

# we can set whether we want the integration with project folder or without it using environment variable 'SETUP_PROJECT_FOLDER'
if [[ ${SETUP_PROJECT_FOLDER} == "true" ]]; then
  # make an api request to get the status of the project folder setup
  project_folder_setup_response=$(curl -s -X GET -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} \
                ${NC_INTEGRATION_BASE_URL}/project-folder-status \
                -H 'accept: application/hal+json' \
                -H 'Content-Type: application/json' \
                -H 'X-Requested-With: XMLHttpRequest')
  isProjectFolderAlreadySetup=$(echo $project_folder_setup_response | jq -r ".result")
  if [[ "$isProjectFolderAlreadySetup" == "true" ]]; then
    setup_project_folder=false
    setup_app_password=true
    setup_method=PATCH
  else
    setup_project_folder=true
    setup_app_password=true
    setup_method=POST
  fi
else
  setup_method=POST
fi

# API call to get openproject_client_id and openproject_client_secret
cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_op_create_storage.json <<EOF
{
  "name": "${OPENPROJECT_STORAGE_NAME}",
  "storageAudience": "test",
  "applicationPassword": "",
  "_links": {
    "origin": {
      "href": "${NEXTCLOUD_HOST}"
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
  ${OPENPROJECT_BASEURL_FOR_STORAGE} \
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
    checkOpenProjectIntegrationConfiguration
    checkNextcloudIntegrationConfiguration
    logCompleteIntegrationConfiguration
  elif [[ ${error_id} == "urn:openproject-org:api:v3:errors:PropertyConstraintViolation" ]]; then
    # A PropertyConstraintViolation is always a single error
    error_messages_grep=$(echo $create_storage_response | jq -r '.message')
    if [[ "$error_messages_grep" == "Host has already been taken." ||  "$error_messages_grep" == "Name has already been taken." ]]; then
      checkOpenProjectIntegrationConfiguration
      checkNextcloudIntegrationConfiguration
      logCompleteIntegrationConfiguration
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
storage_id=$(echo $create_storage_response | jq -e '.id')

if [[ ${storage_id} == null ]]; then
  echo "${create_storage_response}" | jq
  log_error "Response does not contain storage_id (id)."
  log_error "Setup of OpenProject and Nextcloud integration failed."
  exit 1
fi

log_success "Creating file storage name \"${OPENPROJECT_STORAGE_NAME}\" in OpenProject was successful."

# Set up OIDC configuration based on the chosen provider.
# If the provider is Nextcloud, create a new OIDC client.
# Otherwise, register the external OIDC provider on user_oidc apps.
if [[ $OIDC_PROVIDER == "keycloak" ]]; then
  openproject_client_id=$OP_CLIENT_ID
  if [[ $REGISTER_PROVIDER == "true" ]]; then
    registerProviders
    enableStoreLoginTokens
  fi
elif [[ $OIDC_PROVIDER == "nextcloud" ]]; then
  createOidcClient
fi

cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_3_nc_oidc_setup.json <<EOF
{
  "values":{
    "openproject_instance_url": "$OPENPROJECT_HOST", 
    "sso_provider_type": "nextcloud_hub", 
    "authorization_method": "oidc", 
    "targeted_audience_client_id" : "$openproject_client_id",
    "default_enable_navigation": false,
    "default_enable_unified_search": false,
    "setup_project_folder": $setup_project_folder,
    "setup_app_password": $setup_app_password
  }
}
EOF

# If the OIDC provider is Keycloak, update the Nextcloud OIDC setup request body.
# Adds Keycloak-specific configuration including token exchange and sets the SSO provider type to "external".
if [[ $OIDC_PROVIDER = "keycloak" ]]; then
  jq --argjson nc_token_exchange "$NC_TOKEN_EXCHANGE" \
     '.values += {"oidc_provider": "Keycloak", "token_exchange": $nc_token_exchange} | .values.sso_provider_type = "external"' \
     ${INTEGRATION_SETUP_TEMP_DIR}/request_body_3_nc_oidc_setup.json > tempEdit.json
  mv tempEdit.json ${INTEGRATION_SETUP_TEMP_DIR}/request_body_3_nc_oidc_setup.json
fi

# API call to set the  openproject_client_id and openproject_client_secret to Nextcloud [integration_openproject]
nextcloud_information_response=$(curl -s -X${setup_method} -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} "${NC_INTEGRATION_BASE_URL}/setup" \
  -H 'Content-Type: application/json' \
  -d @${INTEGRATION_SETUP_TEMP_DIR}/request_body_3_nc_oidc_setup.json
)

if [[ $INTEGRATION_SETUP_DEBUG != "true"  ]] ; then rm ${INTEGRATION_SETUP_TEMP_DIR}/request_body_3_nc_oidc_setup.json; fi

if [[ "$nextcloud_information_response" != *"nextcloud_oauth_client_name"* ]] && \
    [[ "$nextcloud_information_response" != *"openproject_redirect_uri"* ]]; then

    log_info "The response is missing nextcloud_oauth_client_name or openproject_redirect_uri"
    log_error "Failed to setup OIDC from Nextcloud."
    deleteOPStorageAndPrintErrorResponse "$nextcloud_information_response"

    if [[ "$SETUP_PROJECT_FOLDER" == "true" && "$nextcloud_information_response" != *"openproject_user_app_password"* ]]; then
      log_info "If the error response is related to project folder setup name 'OpenProject' (group, user, folder),"
      log_info "Then follow the link https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#troubleshooting to resolve the error."
    elif [[ $OIDC_PROVIDER = "nextcloud" ]]; then deleteOidcClient "$openproject_id"; 
    fi
    exit 1
fi

if [[ ${SETUP_PROJECT_FOLDER} == true ]]; then
  openproject_user_app_password=$(echo $nextcloud_information_response | jq -e ".openproject_user_app_password")
fi

log_success "Setting up OpenProject oidc configuration where idp is $OIDC_PROVIDER in Nextcloud was successful."

# Save the application password to OpenProject
if [[ ${SETUP_PROJECT_FOLDER} == "true" ]]; then
  cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_op_set_project_folder_app_password.json <<EOF
  {
    "applicationPassword": ${openproject_user_app_password}
  }
EOF
  save_app_password_response=$(curl -s -X PATCH -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
                                    ${OPENPROJECT_BASEURL_FOR_STORAGE}/${storage_id} \
                                    -H 'accept: application/hal+json' \
                                    -H 'Content-Type: application/json' \
                                    -H 'X-Requested-With: XMLHttpRequest' \
                                    -d @${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_op_set_project_folder_app_password.json
  )
  if [[ $INTEGRATION_SETUP_DEBUG != "true"  ]] ; then rm ${INTEGRATION_SETUP_TEMP_DIR}/request_body_4_op_set_project_folder_app_password.json; fi

  if [[ "$save_app_password_response" == *"_type"* ]]; then
    app_password_response_type=$(echo $save_app_password_response | jq -r "._type")
    if [[ ${app_password_response_type} == "Error" ]]; then
      deleteOPStorageAndPrintErrorResponse "$save_app_password_response"
      exit 1
    fi
    has_application_password=$(echo $save_app_password_response | jq -r ".hasApplicationPassword")
    if [[ ${has_application_password} == false ]]; then
      deleteOPStorageAndPrintErrorResponse "$save_app_password_response"
      exit 1
    fi
    log_success "Saving 'OpenProject' user application password to OpenProject was successful."
  fi
fi

logCompleteIntegrationConfiguration