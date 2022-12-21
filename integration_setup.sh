#!/usr/bin/env bash

# This bash script is to set up the whole `openproject_integration` app integration
# To run this script `nextcloud` and `openproject` instance must be running

# variables from environment
# NEXTCLOUD_HOST=<nextcloud_host_url>
# OPENPROJECT_HOST=<openproject_host_url>
# PATH_TO_NEXTCLOUD=<path to nextcloud in your local machine>

# This urls is are just to check if the nextcloud and openproject has been started or not before running the script
NEXTCLOUD_HOST_STATE=$(curl -s -X GET ${NEXTCLOUD_HOST}/cron.php)
OPENPROJECT_HOST_STATE=$(curl -s -X GET ${OPENPROJECT_HOST}/api/v3/configuration)
OPENPROJECT_BASEURL_FOR_STORAGE=${OPENPROJECT_HOST}/api/v3/storages
INTEGRATION_URL_FOR_SETUP=${NEXTCLOUD_HOST}/index.php/apps/integration_openproject/setup

# check if both instance are started or not
if [[ -z "${OPENPROJECT_HOST_STATE}" ]]
then
	echo "Open Project has not been started !!"
	exit 1
elif [[ "$NEXTCLOUD_HOST_STATE" != *"success"* ]]
 then
	 echo "Next Cloud has not been started !!"
	 exit 1
fi

# making sure that jq is installed
if ! command -v jq &> /dev/null
then
    echo "Please install jq"
    echo "sudo apt install -y jq"
    exit
fi

# api call to get openproject_client_id and openproject_client_secret
CREATE_STORAGE_RESPONSE=$(curl -s -X POST -uapiadmin:apiadmin \
                            ${OPENPROJECT_BASEURL_FOR_STORAGE} \
                            -H 'accept: application/hal+json' \
                            -H 'Content-Type: application/json' \
                            -H 'X-Requested-With: XMLHttpRequest' \
                            -d '{
                            "name": "Nextcloud",
                            "_links": {
                              "origin": {
                                "href": "'${NEXTCLOUD_HOST}'"
                              },
                              "type": {
                                "href": "urn:openproject-org:api:v3:storages:Nextcloud"
                              }
                            }
                          }')

response_type=$(echo $CREATE_STORAGE_RESPONSE | jq -r "._type")
if [[ ${response_type} == "Error" ]]; then
	error_message=$(echo $CREATE_STORAGE_RESPONSE | jq -r ".message")
	if [[ ${error_message} == "Multiple field constraints have been violated." ]]; then
		violated_error_messages=$(echo $CREATE_STORAGE_RESPONSE | jq -r "._embedded.errors[].message")
		echo ${violated_error_messages}
		echo "Try deleting the file storage and integrate"
   		exit
    elif [[ ${error_message} == "You did not provide the correct credentials." ]]; then
    	echo "Unauthorized !!! Try running open project with command below."
    	echo "OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_USER=apiadmin OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_PASSWORD=apiadmin foreman start -f Procfile.dev"
       	exit
    fi
   	echo ${error_message}
	echo "Try deleting the file storage and integrate"
   exit
fi

# required information from the above response
storage_id=$(echo $CREATE_STORAGE_RESPONSE | jq -r ".id")
openproject_client_id=$(echo $CREATE_STORAGE_RESPONSE | jq -r "._embedded.oauthApplication.clientId")
openproject_client_secret=$(echo $CREATE_STORAGE_RESPONSE | jq -r "._embedded.oauthApplication.clientSecret")

# api call to set the  openproject_client_id and openproject_client_secret to nextcloud and also get nextcloud_client_id and nextcloud_client_secret
NEXTCLOUD_INFORMATION_RESPONSE=$(curl -s -XPOST -uadmin:admin ${INTEGRATION_URL_FOR_SETUP} \
						   -d '{
						   "values":{
								   "openproject_instance_url":"'${OPENPROJECT_HOST}'",
								   "openproject_client_id":"'${openproject_client_id}'",
								   "openproject_client_secret":"'${openproject_client_secret}'",
								   "default_enable_navigation":false,
								   "default_enable_unified_search":false
								   }
						   }' \
						   -H 'Content-Type: application/json')


# # required information from the above response
nextcloud_client_id=$(echo $NEXTCLOUD_INFORMATION_RESPONSE | jq -r ".nextcloud_client_id")
nextcloud_client_secret=$(echo $NEXTCLOUD_INFORMATION_RESPONSE | jq -r ".nextcloud_client_secret")

# api call to set the nextcloud_client_id and nextcloud_client_secret to openproject files storage
POST_NC_INFOMRATION_RESPONSE=$(curl -s -X POST -uapiadmin:apiadmin \
                                  ${OPENPROJECT_BASEURL_FOR_STORAGE}/${storage_id}/oauth_client_credentials \
                                  -H 'accept: application/hal+json' \
                                  -H 'Content-Type: application/json' \
                                  -H 'X-Requested-With: XMLHttpRequest' \
                                  -d '{
                                  "clientId": "'${nextcloud_client_id}'",
                                  "clientSecret": "'${nextcloud_client_secret}'"
                                  }')
