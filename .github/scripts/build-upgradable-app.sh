#!/bin/bash
# SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

# This script is used to build the integration_openproject app for upgrade testing. It performs the following steps:
# 1. Copy the build files to a separate folder named publish, excluding unnecessary files and directories.
# 2. Get the current version of the app and update it to a new version by incrementing the major version number.
# 3. Sign the app using openssl and occ integrity:sign-app command.
# 4. Archive the app into a tar.gz file.
# 5. Sign the archive using openssl dgst command.
# Note: Before running this script, ensure that the nextcloud instance is running and integration_openproject apps need to be build.

# Required environment variables:
# 1. NEXTCLOUD_PATH (Absolute path to nextcloud where occ command is available, e.g. /var/www/html/build-app-shared)
# 2. INTEGRATION_OPENPROJECT_DIR (Absolute path to the directory containing the integration_openproject repository, e.g. /home/user)

set -e -o pipefail

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

if [[ -z "$NEXTCLOUD_PATH" ]] || [[ -z "$INTEGRATION_OPENPROJECT_DIR" ]]; then
  log_error "Environment variables NEXTCLOUD_PATH or INTEGRATION_OPENPROJECT_DIR are missing."
  exit 1
fi

APP_ID=integration_openproject
cd "$INTEGRATION_OPENPROJECT_DIR"

if [[ ! -d "$INTEGRATION_OPENPROJECT_DIR/$APP_ID" ]]; then
  log_error "Folder does not exist: $INTEGRATION_OPENPROJECT_DIR/$APP_ID"
  exit 1
fi

mkdir -p publish

# copy app files to a separate folder
log_info "Copying necessary app files to publish directory..."
rsync -a \
--exclude=server \
--exclude=dev \
--exclude=.git \
--exclude=appinfo/signature.json \
--exclude='*.swp' \
--exclude=build \
--exclude=.gitignore \
--exclude=.travis.yml \
--exclude=.scrutinizer.yml \
--exclude=CONTRIBUTING.md \
--exclude=composer.phar \
--exclude=js/node_modules \
--exclude=node_modules \
--exclude=src \
--exclude=translationfiles \
--exclude='webpack.*' \
--exclude=stylelint.config.js \
--exclude=.eslintrc.js \
--exclude=.github \
--exclude=.gitlab-ci.yml \
--exclude=crowdin.yml \
--exclude=tools \
--exclude=.tx \
--exclude=.l10nignore \
--exclude=l10n/.tx \
--exclude=l10n/l10n.pl \
--exclude=l10n/templates \
--exclude='l10n/*.sh' \
--exclude='l10n/[a-z][a-z]' \
--exclude='l10n/[a-z][a-z]_[A-Z][A-Z]' \
--exclude=l10n/no-php \
--exclude=makefile \
--exclude=screenshots \
--exclude='phpunit*xml' \
--exclude=tests \
--exclude=ci \
--exclude=vendor/bin \
$APP_ID publish/

cd publish

# get current version of integration_openproject and update to new version
current_version=$(php ${NEXTCLOUD_PATH}/occ app:list --output=json | jq -r ".enabled.$APP_ID") || { log_error "Failed to get current version of $APP_ID app."; exit 1; }
IFS=. read -r a b c <<< "$current_version"
NEXT_APP_VERSION="$((a+1)).$b.$c"

# Save the new tag to a file for later use in the workflow
echo "$NEXT_APP_VERSION" > "${APP_ID}_new_version.txt"

# update version in info.xml
sed -i "s|<version>.*</version>|<version>$NEXT_APP_VERSION</version>|" "integration_openproject/appinfo/info.xml" 

#####################
# Signing the app   #
#####################
# https://nextcloudappstore.readthedocs.io/en/latest/developer.html#obtaining-a-certificate
# Check if openssl exists, otherwise install it
if ! command -v openssl >/dev/null 2>&1; then
    echo "OpenSSL not found. Installing..."
    apt update && apt install -y openssl || {
        echo "Failed to install OpenSSL."
        exit 1
    }
fi
log_info "Generating app.key and app.crt..."
openssl req -x509 -newkey rsa:4096 -sha256 -nodes \
  -keyout app.key \
  -out app.crt \
  -days 3650 \
  -subj "/CN=$APP_ID" \
  -addext "basicConstraints=CA:FALSE" \
  -addext "keyUsage=digitalSignature" \
  -addext "extendedKeyUsage=codeSigning"

if [[ ! -s app.key || ! -s app.crt ]]; then
  log_error "Failed to generate app signing certificate and key. app.key or app.crt not found."
  exit 1
fi

log_info "Adding the generated certificate to nextcloud's root.crt..."
nextcloud_root_crt="${NEXTCLOUD_PATH}/resources/codesigning/root.crt"
if [[ -f ${nextcloud_root_crt} ]]; then
  echo "" >> ${nextcloud_root_crt}
  cat app.crt >> ${nextcloud_root_crt}
else
  log_error "Nextcloud's root.crt not found at ${nextcloud_root_crt}."
  exit 1
fi

# fix permissions for signing
chown www-data app.key
chown www-data app.crt
chown -R www-data $APP_ID

# Sign the app
# need full path for signing
log_info "Signing the app using occ integrity:sign-app command..."
php ${NEXTCLOUD_PATH}/occ integrity:sign-app \
  --privateKey=${INTEGRATION_OPENPROJECT_DIR}/publish/app.key \
  --certificate=${INTEGRATION_OPENPROJECT_DIR}/publish/app.crt \
  --path=${INTEGRATION_OPENPROJECT_DIR}/publish/$APP_ID || { log_error "Failed to sign app."; exit 1; }

# Archive the app
tar -czf $APP_ID-$NEXT_APP_VERSION.tar.gz $APP_ID
if [[ ! -f $APP_ID-$NEXT_APP_VERSION.tar.gz ]]; then
  log_error "Failed to archive the app. Archive file $APP_ID-$NEXT_APP_VERSION.tar.gz not found."
  exit 1
fi
log_success "Archived the app into $APP_ID-$NEXT_APP_VERSION.tar.gz."

#####################
# Sign the archive  #
#####################
log_info "Signing the archive using openssl dgst command..."
openssl dgst -sha512 -sign app.key $APP_ID-$NEXT_APP_VERSION.tar.gz \
  | openssl base64 \
  | tee ${INTEGRATION_OPENPROJECT_DIR}/publish/sign.txt

if [[ ! -s ${INTEGRATION_OPENPROJECT_DIR}/publish/sign.txt ]]; then
  log_error "Failed to sign the archive. Signature file sign.txt is empty or not found."
  exit 1
else
  log_success "Signed the app archive successfully."
fi

log_success "App build and release process has been completed successfully."

# prepare apps.json file
if [[ ! -f ${INTEGRATION_OPENPROJECT_DIR}/publish/${APP_ID}/appinfo/signature.json ]]; then
  echo "Signature file not found at ${INTEGRATION_OPENPROJECT_DIR}/publish/${APP_ID}/appinfo/signature.json."
  exit 1
fi
certificate=$(jq '.certificate' "${INTEGRATION_OPENPROJECT_DIR}/publish/${APP_ID}/appinfo/signature.json")
signature=$(tr -d '\n' < "${INTEGRATION_OPENPROJECT_DIR}/publish/sign.txt")

# Create apps.json with the required structure
cat > apps.json <<EOF
[
  {
    "id": "$APP_ID",
    "releases": [
      {
        "version": "$NEXT_APP_VERSION",
        "minIntSize": 32,
        "download": "http://localhost:8080/${APP_ID}-${NEXT_APP_VERSION}.tar.gz",
        "licenses": [
          "agpl"
        ],
        "isNightly": false,
        "rawPlatformVersionSpec": "\u003E=28 \u003C=32",
        "signature": "$signature",
        "signatureDigest": "sha512"
      }
    ],
    "certificate": $certificate
  }
]
EOF