#!/usr/bin/env bash

# This bash script is to set up the whole `openproject_integration` app integration
# To run this script the Nextcloud and OpenProject instances must be running

# variables from environment
# NEXTCLOUD_HOST=<nextcloud_host_url>
# OPENPROJECT_HOST=<openproject_host_url>
# OP_ADMIN_USERNAME=<openproject_admin_username>
# OP_ADMIN_PASSWORD=<openproject_admin_password>
# NC_ADMIN_USERNAME=<nextcloud_admin_username>
# NC_ADMIN_PASSWORD=<nextcloud_admin_password>
# OPENPROJECT_STORAGE_NAME=<openproject_filestorage_name> This variable is the name of the storage which keeps the oauth information in OpenProject required for integration.

log_error() {
	echo -e "\e[31m$1\e[0m"
}

log_info() {
	echo -e "\e[37m$1\e[0m"
}

log_success() {
	echo -e "\e[32m$1\e[0m"
}

# we now have option to set up project folders in the integration app
# this function checks if the system is ready for the project folder setup
isSystemReadyForProjectFolderSetup() {
#	log_info "Checking your system for project folder setup ..."
	log_error "Failed !!"
    exit 1
}



# making sure that jq is installed
if ! command -v jq &> /dev/null
then
	log_error "jq is not installed"
	log_info "sudo apt install -y jq (ubuntu) or brew install jq (mac)"
	exit 1
fi

# These URLs are just to check if the Nextcloud and OpenProject instances have been started or not before running the script
NEXTCLOUD_HOST_STATE=$(curl -s -X GET ${NEXTCLOUD_HOST}/status.php)
NEXTCLOUD_HOST_INSTALLED_STATE=$(echo $NEXTCLOUD_HOST_STATE | jq -r ".installed")
NEXTCLOUD_HOST_MAINTENANCE_STATE=$(echo $NEXTCLOUD_HOST_STATE | jq -r ".maintenance")
OPENPROJECT_HOST_STATE=$(curl -s -X GET -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
                          ${OPENPROJECT_HOST}/api/v3/configuration | jq -r "._type")
OPENPROJECT_BASEURL_FOR_STORAGE=${OPENPROJECT_HOST}/api/v3/storages
NC_INTEGRATION_BASE_URL=${NEXTCLOUD_HOST}/index.php/apps/integration_openproject
setup_project_folder=false
setup_app_password-false

# there might me cases where the user has already setup the project
isProjectFolderSetupCorrect() {
	# make an CURL API request and get the status of project folder
	project_folder_setup_status=$(curl -s -XGET -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} "${NC_INTEGRATION_BASE_URL}/project-folder-status")
}


# check if both instances are started or not
if [[ ${OPENPROJECT_HOST_STATE} != "Configuration" ]]
then
	log_error "OpenProject host cannot be reached!!"
	exit 1
fi
if [[ ${NEXTCLOUD_HOST_INSTALLED_STATE} != "true" || ${NEXTCLOUD_HOST_MAINTENANCE_STATE} != "false" ]]
then
	log_error "Nextcloud host cannot be reached or is in maintenance mode!!"
	exit 1
fi

# we can set whether we want the integration with project folder or without it using environment variable 'SET_PROJECT_FOLDER_SETUP'
if [[ ${SET_PROJECT_FOLDER_SETUP} == true ]]
then
	isProjectFolderSetupCorrect
	result="$project_folder_setup_status"
	result_value=$(echo "$result" | jq -e ".result")
	if [[ ${result_value} == false ]]
	then
		setup_project_folder=true
		setup_project_folder=true
		isSystemReadyForProjectFolderSetup
	fi
fi

log_info "Creating file storage in OpenProject ..."
# api call to get openproject_client_id and openproject_client_secret
create_storage_response=$(curl -s -X POST -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
                            ${OPENPROJECT_BASEURL_FOR_STORAGE} \
                            -H 'accept: application/hal+json' \
                            -H 'Content-Type: application/json' \
                            -H 'X-Requested-With: XMLHttpRequest' \
                            -d '{
                            "name": "'${OPENPROJECT_STORAGE_NAME}'",
                            "_links": {
                              "origin": {
                                "href": "'${NEXTCLOUD_HOST}'"
                              },
                              "type": {
                                "href": "urn:openproject-org:api:v3:storages:Nextcloud"
                              }
                            }
                          }')

# check for errors
response_type=$(echo $create_storage_response | jq -r "._type")
if [[ ${response_type} == "Error" ]]; then
	error_message=$(echo $create_storage_response | jq -r ".message")
	if [[ ${error_message} == "Multiple field constraints have been violated." ]]; then
		violated_error_messages=$(echo $create_storage_response | jq -r "._embedded.errors[].message")
		log_error "'${NEXTCLOUD_HOST}' ${violated_error_messages}"
		log_info "Try deleting the file storage in OpenProject and integrate again !!"
		exit 1
	elif [[ ${error_message} == "You did not provide the correct credentials." ]]; then
		log_error "Unauthorized !!! Try running OpenProject with the environment variables below"
		log_info "OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_USER=<global_admin_username>  OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_PASSWORD=<global_admin_password>  foreman start -f Procfile.dev"
		exit 1
	fi
	log_error "OpenProject storage name '${OPENPROJECT_STORAGE_NAME}' ${error_message}"
	log_info "Try deleting the file storage in OpenProject and integrate again !!"
	exit 1
fi

# required information from the above response
storage_id=$(echo $create_storage_response | jq -e '.id')
openproject_client_id=$(echo $create_storage_response | jq -e '._embedded.oauthApplication.clientId')
openproject_client_secret=$(echo $create_storage_response | jq -e '._embedded.oauthApplication.clientSecret')

if [ ${storage_id} == null ] || [ ${openproject_client_id} == null ] || [ ${openproject_client_secret} == null ]; then
	echo "${create_storage_response}" | jq
	log_error "Response does not contain storage_id (id) or openproject_client_id (clientId) or openproject_client_secret (clientSecret)"
	log_error "Setup of the integration failed :( !!"
	exit 1
fi

log_info "success!"

log_info "Setting up OpenProject integration in Nextcloud..."
# api call to set the  openproject_client_id and openproject_client_secret to Nextcloud and also get nextcloud_client_id and nextcloud_client_secret
nextcloud_information_response=$(curl -s -XPOST -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} "${NC_INTEGRATION_BASE_URL}/setup" \
						   -d '{
						   "values":{
								   "openproject_instance_url":"'${OPENPROJECT_HOST}'",
								   "openproject_client_id":'${openproject_client_id}',
								   "openproject_client_secret":'${openproject_client_secret}',
								   "default_enable_navigation":false,
								   "default_enable_unified_search":false,
								   "setup_project_folder":false,
								   "setup_app_password":false
								   }
						   }' \
						   -H 'Content-Type: application/json')

# required information from the above response
nextcloud_client_id=$(echo $nextcloud_information_response | jq -e ".nextcloud_client_id")
nextcloud_client_secret=$(echo $nextcloud_information_response | jq -e ".nextcloud_client_secret")

if [ ${nextcloud_client_id} == null ] || [ ${nextcloud_client_secret} == null ]; then
	echo "${nextcloud_information_response}" | jq
	log_error "Response does not contain nextcloud_client_id (nextcloud_client_id) or nextcloud_client_secret(nextcloud_client_secret)"
	log_error "Setup of the integration failed :( !!"
	exit 1
fi

log_info "success!"

log_info "Setting up Nextcloud integration in OpenProject..."
# api call to set the nextcloud_client_id and nextcloud_client_secret to OpenProject files storage
set_nextcloud_to_storage_response=$(curl -s -X POST -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
                                  ${OPENPROJECT_BASEURL_FOR_STORAGE}/${storage_id}/oauth_client_credentials \
                                  -H 'accept: application/hal+json' \
                                  -H 'Content-Type: application/json' \
                                  -H 'X-Requested-With: XMLHttpRequest' \
                                  -d '{
                                  "clientId": '${nextcloud_client_id}',
                                  "clientSecret": '${nextcloud_client_secret}'
                                  }')


# if there is no error from the last api call then the integration can be declared successful

response_type=$(echo $set_nextcloud_to_storage_response | jq -r "._type")
if [ ${nextcloud_client_id} == "Error" ]; then
	error_message=$(echo $set_nextcloud_to_storage_response | jq -r ".message")
	log_error "${error_message}"
	log_error "Setup of the integration failed :( !!"
	exit 1
fi

log_info "success!"
log_success "Setup of the integration was successful :) !!"
