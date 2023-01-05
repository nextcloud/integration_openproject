#!/usr/bin/env bash

# This bash script is to set up the whole `openproject_integration` app integration
# To run this script the `nextcloud` and `openproject` instances must be running

# variables from environment
# NEXTCLOUD_HOST=<nextcloud_host_url>
# OPENPROJECT_HOST=<openproject_host_url>
# OP_ADMIN_USERNAME=<openproject_admin_username>
# OP_ADMIN_PASSWORD=<openproject_admin_password>
# NC_ADMIN_USERNAME=<nextcloud_admin_username>
# NC_ADMIN_PASSWORD=<nextcloud_admin_password>
# OPENPROJECT_STORAGE_NAME=<openproject_filestorage_name> This variable is the name of the storage which keeps the oauth information in open project required for integration.

log_error() {
	echo -e "\e[31m$1\e[0m"
}

log_info() {
	echo -e "\e[37m$1\e[0m"
}

log_success() {
	echo -e "\e[32m$1\e[0m"
}

# making sure that jq is installed
if ! command -v jq &> /dev/null
then
	log_error "jq is not installed"
	log_info "sudo apt install -y jq (ubuntu) or brew install jq (mac)"
	exit 1
fi

# These URLs are just to check if the nextcloud and openproject instances have been started or not before running the script
NEXTCLOUD_HOST_STATE=$(curl -s -X GET ${NEXTCLOUD_HOST}/cron.php)
OPENPROJECT_HOST_STATE=$(curl -s -X GET ${OPENPROJECT_HOST}/api/v3/configuration | jq -r "._type")
OPENPROJECT_BASEURL_FOR_STORAGE=${OPENPROJECT_HOST}/api/v3/storages
INTEGRATION_URL_FOR_SETUP=${NEXTCLOUD_HOST}/index.php/apps/integration_openproject/setup

# check if both instances are started or not
if [[ ${OPENPROJECT_HOST_STATE} != "Configuration" ]]
then
	log_error "Open Project Host has not been started !!"
	exit 1
elif [[ "$NEXTCLOUD_HOST_STATE" != *"success"* ]]
 then
	log_error "Nextcloud Host has not been started !!"
	exit 1
fi

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
		log_info "Try deleting the file storage in openproject and integrate again !!"
		exit 1
	elif [[ ${error_message} == "You did not provide the correct credentials." ]]; then
		log_error "Unauthorized !!! Try running Open Project with the environment variables below"
		log_info "OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_USER=<global_admin_username>  OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_PASSWORD=<global_admin_password>  foreman start -f Procfile.dev"
		exit 1
	fi
	log_error "Open Project storage name '${OPENPROJECT_STORAGE_NAME}' ${error_message}"
	log_info "Try deleting the file storage in openproject and integrate again !!"
	exit 1
fi

# required information from the above response
storage_id=$(echo $create_storage_response | jq -e '.id')
openproject_client_id=$(echo $create_storage_response | jq -e '._embedded.oauthApplication.clientId')
openproject_client_secret=$(echo $create_storage_response | jq -e '._embedded.oauthApplication.clientSecret')

if [ ${storage_id} == null ] || [ ${openproject_client_id} == null ] || [ ${openproject_client_secret} == null ]; then
	echo "${create_storage_response}" | jq
	log_error "Response does not contain storage_id (id) or openproject_client_id (clientId) or openproject_client_secret (clientSecret)"
	log_error "Integration failed :( !!"
	exit 1
fi

log_info "File Storage creation ${OPENPROJECT_STORAGE_NAME} successful ...."

# api call to set the  openproject_client_id and openproject_client_secret to nextcloud and also get nextcloud_client_id and nextcloud_client_secret
nextcloud_information_response=$(curl -s -XPOST -u${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD} ${INTEGRATION_URL_FOR_SETUP} \
						   -d '{
						   "values":{
								   "openproject_instance_url":"'${OPENPROJECT_HOST}'",
								   "openproject_client_id":'${openproject_client_id}',
								   "openproject_client_secret":'${openproject_client_secret}',
								   "default_enable_navigation":false,
								   "default_enable_unified_search":false
								   }
						   }' \
						   -H 'Content-Type: application/json')

# required information from the above response
nextcloud_client_id=$(echo $nextcloud_information_response | jq -e ".nextcloud_client_id")
nextcloud_client_secret=$(echo $nextcloud_information_response | jq -e ".nextcloud_client_secret")

if [ ${nextcloud_client_id} == null ] || [ ${nextcloud_client_secret} == null ]; then
	echo "${nextcloud_information_response}" | jq
	log_error "Response does not contain nextcloud_client_id (nextcloud_client_id) or nextcloud_client_secret(nextcloud_client_secret)"
	log_error "Integration failed :( !!"
	exit 1
fi

log_info "Setting up Open project for Nextcloud successful ..."

# api call to set the nextcloud_client_id and nextcloud_client_secret to openproject files storage
set_nextcloud_to_storage_response=$(curl -s -X POST -u${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD} \
                                  ${OPENPROJECT_BASEURL_FOR_STORAGE}/${storage_id}/oauth_client_credentials \
                                  -H 'accept: application/hal+json' \
                                  -H 'Content-Type: application/json' \
                                  -H 'X-Requested-With: XMLHttpRequest' \
                                  -d '{
                                  "clientId": '${nextcloud_client_id}',
                                  "clientSecret": '${nextcloud_client_secret}'
                                  }')

log_info "Setting up Nextcloud information on OpenProject successful ..."

# if there is no error from the last api call then the integration can be declared successful

response_type=$(echo $set_nextcloud_to_storage_response | jq -r "._type")
if [ ${nextcloud_client_id} == "Error" ]; then
	error_message=$(echo $set_nextcloud_to_storage_response | jq -r ".message")
	log_error "${error_message}"
	log_error "Integration failed :( !!"
	exit 1
else
	log_success "Integration Successful :) !!"
fi
