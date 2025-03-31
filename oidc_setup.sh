# SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
# SPDX-FileCopyrightText: 2023 Bundesministerium des Innern und für Heimat, PG ZenDiS "Projektgruppe für Aufbau ZenDiS"
# SPDX-FileCopyrightText: 2023 Nextcloud GmbH
# SPDX-License-Identifier: AGPL-3.0-only
#!/usr/bin/env bash

# This bash script is to set up the whole OAUTH configuration between OpenProject and Nextcloud.
# To run this script the Nextcloud and OpenProject instances must be up and running

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
  echo "$1" | jq
  log_error "Setup of OpenProject and Nextcloud integration failed."
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
NC_INTEGRATION_BASE_URL=${NEXTCLOUD_HOST}/index.php/apps/integration_openproject
openproject_host_state_response=$(curl -s -X GET -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} ${OPENPROJECT_HOST}/api/v3/configuration)
# These two data are set to "false" when the integration is done without project folder setup
setup_project_folder=false
setup_app_password=false
# Minimum OpenProject version required in order to run this script.
MINIMUM_OP_VERSION="14.2.0"
# Openproject and Nextcloud Client name
openproject_client_name="openproject"


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


logCompleteIntegrationConfiguration() {
  log_success "Setup of OpenProject and Nextcloud is complete."
  exit 0
}

# check if openproject and nextcloud instances are started or not
# openproject
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
# nextcloud
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

# API call to get openproject_client_id [oidc App]
cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_1_oidc_create_oidc_client.json <<EOF
{
  "name": "$openproject_client_name",
  "redirectUri": "$OPENPROJECT_HOST/auth/oidc-nextcloud/callback",
  "signingAlg": "RS256",
  "type": "confidential"
}
EOF

# API call to set the openproject_client_id to Nextcloud [oidc App]
oidc_information_response=$(curl -s -X${setup_method} -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} "${NEXTCLOUD_HOST}/index.php/apps/oidc/clients" \
  -H 'Content-Type: application/json' \
  -H 'OCS-APIRequest: true' \
  -d @"${INTEGRATION_SETUP_TEMP_DIR}/request_body_1_oidc_create_oidc_client.json"
)

if ! [[ $(echo "$oidc_information_response" | jq -r '.name') = "$openproject_client_name" ]]; then
  log_error "[OIDC app]:Failed when creating '$openproject_client_name' oidc client";
  exit1 1
fi

log_success "[OIDC app] '$openproject_client_name' oidc client has been created successfully"

if [[ $INTEGRATION_SETUP_DEBUG != "true"  ]] ; then rm ${INTEGRATION_SETUP_TEMP_DIR}/request_body_1_oidc_create_oidc_client.json; fi

# required information from the above response
openproject_client_id=$(echo $oidc_information_response | jq -e '.clientId')
echo $openproject_client_id

cat >${INTEGRATION_SETUP_TEMP_DIR}/request_body_2_nc_oidc_setup.json <<EOF
{
  "values":{
    "openproject_instance_url": "$OPENPROJECT_HOST", 
    "sso_provider_type": "nextcloud_hub", 
    "authorization_method": "oidc", 
    "targeted_audience_client_id" : $openproject_client_id,
    "default_enable_navigation": false,
    "default_enable_unified_search": false,
    "setup_project_folder": $SETUP_PROJECT_FOLDER,
    "setup_app_password": false
  }
}
EOF

# API call to set the  openproject_client_id and openproject_client_secret to Nextcloud [integration_openproject]
echo $setup_method
oidc_information_response=$(curl -s -X${setup_method} -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} "${NC_INTEGRATION_BASE_URL}/setup" \
  -H 'Content-Type: application/json' \
  -d @${INTEGRATION_SETUP_TEMP_DIR}/request_body_2_nc_oidc_setup.json
)

echo $oidc_information_response

log_success "[Integration_openproject app]: oidc where idpis nextcloud has been setup successfully"