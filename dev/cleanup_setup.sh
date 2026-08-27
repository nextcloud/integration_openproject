#!/bin/bash
# SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

OP_HOST=${OP_HOST-https://openproject.local}
OP_STORAGE_URL="${OP_HOST}/api/v3/storages"
OP_ADMIN_USERNAME=${OP_ADMIN_USERNAME:-admin}
OP_ADMIN_PASSWORD=${OP_ADMIN_PASSWORD:-admin}
OP_ADMIN_AUTH="${OP_ADMIN_USERNAME}:${OP_ADMIN_PASSWORD}"

op_storages=$(curl -sS "${OP_STORAGE_URL}" -u"${OP_ADMIN_AUTH}")
op_storage_id=$(echo "${op_storages}" | jq -r '._embedded.elements[] | select(.name=="nextcloud") | .id')
# delete Nextcloud storage
op_storage_delete_status=$(curl -sS -X DELETE "${OP_STORAGE_URL}/${op_storage_id}" \
  -o /dev/null -w "%{http_code}" -u"${OP_ADMIN_AUTH}")
if [[ "${op_storage_delete_status}" == "204" ]]; then
  echo "[INFO] Nextcloud storage deleted successfully."
else
  echo "[ERROR] Failed to delete Nextcloud storage. HTTP status code: ${op_storage_delete_status}"
fi

NC_HOST=${NC_HOST-https://nextcloud.local}
INTEGRATION_SETUP_URL="${NC_HOST}/index.php/apps/integration_openproject/setup"
APPS_URL="${NC_HOST}/ocs/v2.php/cloud/apps/integration_openproject"
USER_URL="${NC_HOST}/ocs/v2.php/cloud/users/OpenProject"
GROUP_URL="${NC_HOST}/ocs/v2.php/cloud/groups"
TEAM_FOLDER_URL="${NC_HOST}/index.php/apps/groupfolders/folders"
NC_OPENPROJECT_GROUPS=("OpenProject" "OpenProjectNoAutomaticProjectFolders")
NC_ADMIN_USERNAME=${NC_ADMIN_USERNAME:-admin}
NC_ADMIN_PASSWORD=${NC_ADMIN_PASSWORD:-admin}
NC_ADMIN_AUTH="${NC_ADMIN_USERNAME}:${NC_ADMIN_PASSWORD}"

# reset Nextcloud integration settings
nc_reset_status=$(curl -sS -X DELETE "${INTEGRATION_SETUP_URL}" \
  -H "OCS-APIRequest: true" \
  -o /dev/null -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
if [[ "${nc_reset_status}" == "200" ]]; then
  echo "[INFO] Nextcloud integration settings reset successfully."
else
  echo "[ERROR] Failed to reset Nextcloud integration settings. HTTP status code: ${nc_reset_status}"
fi

# disable integration_openproject app
nc_disable_app_status=$(curl -sS -X DELETE "${APPS_URL}" \
  -H "OCS-APIRequest: true" \
  -o /dev/null -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
if [[ "${nc_disable_app_status}" == "200" ]]; then
  echo "[INFO] App disabled: integration_openproject"
else
  echo "[ERROR] Failed to disable app: integration_openproject. HTTP status code: ${nc_disable_app_status}"
fi

# delete OpenProject user
nc_delete_user_status=$(curl -sS -X DELETE "${USER_URL}" \
  -H "OCS-APIRequest: true" \
  -o /dev/null -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
if [[ "${nc_delete_user_status}" == "200" ]]; then
  echo "[INFO] User deleted: OpenProject"
else
  echo "[ERROR] Failed to delete user: OpenProject. HTTP status code: ${nc_delete_user_status}"
fi

# delete OpenProject groups
for group in "${NC_OPENPROJECT_GROUPS[@]}"; do
  nc_delete_group_status=$(curl -sS -X DELETE "${GROUP_URL}/${group}" \
    -H "OCS-APIRequest: true" \
    -o /dev/null -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
  if [[ "${nc_delete_group_status}" == "200" ]]; then
    echo "[INFO] Group deleted: ${group}"
  else
    echo "[ERROR] Failed to delete group: ${group}. HTTP status code: ${nc_delete_group_status}"
  fi
done

nc_team_folders=$(curl -sS "${TEAM_FOLDER_URL}" -u"${NC_ADMIN_AUTH}")
nc_team_folder_id=$(echo "${nc_team_folders}" | jq -r '.ocs.data | to_entries[] | select(.value.mount_point=="OpenProject") | .value.id')
# delete OpenProject team folder
nc_delete_team_folder_status=$(curl -sS -X DELETE "${TEAM_FOLDER_URL}/${nc_team_folder_id}" \
  -H "OCS-APIRequest: true" \
  -o /dev/null -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
if [[ "${nc_delete_team_folder_status}" == "200" ]]; then
  echo "[INFO] Team folder deleted: OpenProject"
else
  echo "[ERROR] Failed to delete team folder: OpenProject. HTTP status code: ${nc_delete_team_folder_status}"
fi

# enable integration_openproject app
nc_enable_app_status=$(curl -sS -X POST "${APPS_URL}" \
  -H "OCS-APIRequest: true" \
  -o /dev/null -w "%{http_code}" -u"${NC_ADMIN_AUTH}")
if [[ "${nc_enable_app_status}" == "200" ]]; then
  echo "[INFO] App enabled: integration_openproject"
else
  echo "[ERROR] Failed to enable app: integration_openproject. HTTP status code: ${nc_enable_app_status}"
fi
