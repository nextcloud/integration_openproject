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

# check if both instance are started or not
check_instance_running() {
    if [[ -z "${OPENPROJECT_HOST_STATE}" ]]
    then
        echo "Open Project has not been started !!"
        exit 1
    elif [[ "$NEXTCLOUD_HOST_STATE" != *"success"* ]]
     then
         echo "Next Cloud has not been started !!"
         exit 1
    fi
}

check_instance_running


# start making api call to get Open project client id and secret
CREATE_STORAGE_RESPONSE=$(curl -s -X POST -uapiadmin:apiadmin \
                            ${OPENPROJECT_HOST}/api/v3/storages \
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

files_storage_id=$(echo $CREATE_STORAGE_RESPONSE | jq -r ".id")
openproject_client_id=$(echo $CREATE_STORAGE_RESPONSE | jq -r "._embedded.oauthApplication.clientId")
openproject_client_secret=$(echo $CREATE_STORAGE_RESPONSE | jq -r "._embedded.oauthApplication.clientSecret")


RESPONSE_FROM_NEXTCLOUD=$(curl -s -XPOST -uadmin:admin  http://localhost/sampleNC/index.php/apps/integration_openproject/setup \
           -d '{
           "values":{
                   "openproject_instance_url":"'${OPENPROJECT_HOST}'",
                   "openproject_client_id":"'${openproject_client_id}'",
                   "openproject_client_secret":"'${openproject_client_secret}'",
                   "default_enable_navigation":false,
                   "default_enable_unified_search":false
                   }
           }' \
           -H 'Content-Type: application/json'
           )


nextcloud_client_id=$(echo $RESPONSE_FROM_NEXTCLOUD | jq -r ".nextcloud_client_id")
nextcloud_client_secret=$(echo $RESPONSE_FROM_NEXTCLOUD | jq -r ".nextcloud_client_secret")

echo ${files_storage_id}
echo ${nextcloud_client_id}
echo ${nextcloud_client_secret}

# send this client id and secrect to save to the current files storage in the openproject
POST_NC_INFOMRATION_RESPONSE=$(curl -X POST -uapiadmin:apiadmin \
                                              ${OPENPROJECT_HOST}/api/v3/storages/${files_storage_id}/oauth_client_credentials \
                                              -H 'accept: application/hal+json' \
                                              -H 'Content-Type: application/json' \
                                              -H 'X-Requested-With: XMLHttpRequest' \
                                              -d '{
                                              "clientId": "'${nextcloud_client_id}'",
                                              "clientSecret": "'${nextcloud_client_secret}'"
                                            }'
                                            )
