#!/bin/bash
# SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="${SCRIPT_DIR}/log"
mkdir -p "${LOG_DIR}"

# OpenProject configurations
OP_HOST=${OP_HOST:-https://openproject.local}
OP_STORAGE_URL="${OP_HOST}/api/v3/storages"
OP_ADMIN_USERNAME=${OP_ADMIN_USERNAME:-admin}
OP_ADMIN_PASSWORD=${OP_ADMIN_PASSWORD:-admin}
OP_ADMIN_AUTH="${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD}"
# Nextcloud configurations
NC_ADMIN_USERNAME=${NC_ADMIN_USERNAME:-admin}
NC_ADMIN_PASSWORD=${NC_ADMIN_PASSWORD:-admin}
NC_INTEGRATION_APP_ID="integration_openproject"
NC_OPENPROJECT_GROUPS=("OpenProject" "OpenProjectNoAutomaticProjectFolders")
NC_HOST=${NC_HOST:-https://nextcloud.local}
NC_INTEGRATION_URL="${NC_HOST}/index.php/apps/${NC_INTEGRATION_APP_ID}"
NC_APPS_URL="${NC_HOST}/ocs/v2.php/cloud/apps"
NC_USERS_URL="${NC_HOST}/ocs/v2.php/cloud/users"
NC_GROUPS_URL="${NC_HOST}/ocs/v2.php/cloud/groups"
NC_TEAM_FOLDERS_URL="${NC_HOST}/index.php/apps/groupfolders/folders"
NC_ADMIN_AUTH="${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD}"

function log_info() {
  echo "[INFO] $1"
}

function log_error() {
  echo "[ERROR] $1"
  echo "[ERROR] Response: $(cat ${LOG_DIR}/response.txt)"

  rm -rf "${LOG_DIR}"
  exit 1
}

function disable_app() {
  local nc_disable_app_status
  local app_id=$1

  nc_disable_app_status=$(curl -sS -X DELETE "${NC_APPS_URL}/${app_id}" \
    -H "OCS-APIRequest: true" \
    -o "${LOG_DIR}/response.txt" -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
  if [[ "${nc_disable_app_status}" == "200" ]]; then
    log_info "App disabled: ${app_id}"
  else
    log_error "Failed to disable app: ${app_id}. Status code: ${nc_disable_app_status}"
  fi
}

function enable_app() {
  local nc_enable_app_status
  local app_id=$1

  nc_enable_app_status=$(curl -sS -X POST "${NC_APPS_URL}/${app_id}" \
    -H "OCS-APIRequest: true" \
    -o "${LOG_DIR}/response.txt" -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
  if [[ "${nc_enable_app_status}" == "200" ]]; then
    log_info "App enabled: ${app_id}"
  else
    log_error "Failed to enable app: ${app_id}. Status code: ${nc_enable_app_status}"
  fi
}

function reset_integration_settings() {
  local nc_reset_status
  local retry=$1

  nc_reset_status=$(curl -sS -X DELETE "${NC_INTEGRATION_URL}/setup" \
  -H "OCS-APIRequest: true" \
  -o "${LOG_DIR}/response.txt" -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
  if [[ "${nc_reset_status}" == "200" ]]; then
    log_info "Nextcloud integration settings reset successfully."
  elif [[ "${retry}" == "retry" ]]; then
    log_error "Failed to reset Nextcloud integration settings. Status code: ${nc_reset_status}"
  elif [[ "${nc_reset_status}" == "404" ]]; then
    enable_app "${NC_INTEGRATION_APP_ID}"
    reset_integration_settings "retry"
  else
    log_error "Failed to reset Nextcloud integration settings. Status code: ${nc_reset_status}"
  fi
}

# get OpenProject storages
op_storages_status=$(curl -sS "${OP_STORAGE_URL}" \
  -o "${LOG_DIR}/response.txt" -w "%{http_code}" -u"${OP_ADMIN_AUTH}")
if [[ "${op_storages_status}" == "200" ]]; then
  op_storages=$(cat "${LOG_DIR}/response.txt")
  op_nc_storage_id=$(echo "${op_storages}" | jq -r '._embedded.elements[] | select(._links.type.title=="Nextcloud") | .id')
  if [[ -z "${op_nc_storage_id}" ]]; then
    log_info "No Nextcloud storage found in OpenProject."
  else
    # delete Nextcloud storage
    op_storage_delete_status=$(curl -sS -X DELETE "${OP_STORAGE_URL}/${op_nc_storage_id}" \
      -o "${LOG_DIR}/response.txt" -w "%{http_code}" -u"${OP_ADMIN_AUTH}")
    if [[ "${op_storage_delete_status}" == "204" ]]; then
      log_info "Nextcloud storage deleted successfully."
    else
      log_error "Failed to delete Nextcloud storage. Status code: ${op_storage_delete_status}"
    fi
  fi
else
  log_error "Failed to get OpenProject storages. Status code: ${op_storages_status}"
fi

# reset Nextcloud integration settings
reset_integration_settings

# disable integration_openproject app
disable_app "${NC_INTEGRATION_APP_ID}"

# delete OpenProject user
nc_delete_user_status=$(curl -sS -X DELETE "${NC_USERS_URL}/OpenProject" \
  -H "OCS-APIRequest: true" \
  -o "${LOG_DIR}/response.txt" -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
if [[ "${nc_delete_user_status}" == "200" ]]; then
  log_info "User deleted: OpenProject"
elif [[ "${nc_delete_user_status}" == "404" ]]; then
  log_info "User does not exist: OpenProject"
else
  log_error "Failed to delete user: OpenProject. Status code: ${nc_delete_user_status}"
fi

# delete OpenProject groups
for group in "${NC_OPENPROJECT_GROUPS[@]}"; do
  nc_delete_group_status=$(curl -sS -X DELETE "${NC_GROUPS_URL}/${group}" \
    -H "OCS-APIRequest: true" \
    -o "${LOG_DIR}/response.txt" -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
  if [[ "${nc_delete_group_status}" == "200" ]]; then
    log_info "Group deleted: ${group}"
  elif [[ "${nc_delete_group_status}" == "400" ]]; then
    log_info "Group does not exist: $group"
  else
    log_error "Failed to delete group: ${group}. Status code: ${nc_delete_group_status}"
  fi
done

# get team folders
nc_team_folders_status=$(curl -sS "${NC_TEAM_FOLDERS_URL}?format=json" \
-H "OCS-APIRequest: true" \
-o "${LOG_DIR}/response.txt" -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
if [[ "${nc_team_folders_status}" == "200" ]]; then
  nc_team_folders=$(cat "${LOG_DIR}/response.txt")
  nc_team_folder_id=$(echo "${nc_team_folders}" | jq -r '.ocs.data | to_entries[] | select(.value.mount_point=="OpenProject") | .value.id')
  if [[ -z "${nc_team_folder_id}" ]]; then
    log_info "Team folder does not exist: OpenProject"
  else
    # delete OpenProject team folder
    nc_delete_team_folder_status=$(curl -sS -X DELETE "${NC_TEAM_FOLDERS_URL}/${nc_team_folder_id}" \
      -H "OCS-APIRequest: true" \
      -o "${LOG_DIR}/response.txt" -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
    if [[ "${nc_delete_team_folder_status}" == "200" ]]; then
      log_info "Team folder deleted: OpenProject"
    else
      log_error "Failed to delete team folder: OpenProject. Status code: ${nc_delete_team_folder_status}"
    fi
  fi
else
  log_error "Failed to list team folders. Status code: ${nc_team_folders_status}"
fi

# enable integration_openproject app
enable_app "${NC_INTEGRATION_APP_ID}"

rm -rf "${LOG_DIR}"