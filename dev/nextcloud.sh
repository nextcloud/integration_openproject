#!/bin/bash
# SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

set -e

NEXTCLOUD_ORG="https://github.com/nextcloud"
TMP_SERVER_DIR="/tmp/server"
SERVER_DIR="/var/www/html"

rm -rf "${TMP_SERVER_DIR}" || true
# clone nextcloud server
git clone -b "${SERVER_BRANCH}" --depth 1 "${NEXTCLOUD_ORG}/server.git" "${TMP_SERVER_DIR}"

# get activity app
set +e
git clone -b "${SERVER_BRANCH}" --depth 1 "${NEXTCLOUD_ORG}/activity.git" "${TMP_SERVER_DIR}"/apps/activity
exit_code=$?
set -e
if [ $exit_code -ne 0 ]; then
  git clone -b master --depth 1 "${NEXTCLOUD_ORG}/activity.git" "${TMP_SERVER_DIR}"/apps/activity
fi

(cd "${TMP_SERVER_DIR}" && git submodule update --init)
rsync -a --chmod=755 --chown=www-data:www-data "${TMP_SERVER_DIR}"/ "${SERVER_DIR}"
chown www-data: -R "${SERVER_DIR}"/data
chown www-data: "${SERVER_DIR}"/.htaccess

# run the nextcloud setup
/usr/local/bin/bootstrap.sh apache2-foreground
