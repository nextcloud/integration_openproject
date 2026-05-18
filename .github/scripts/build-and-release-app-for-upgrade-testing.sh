# SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
# SPDX-FileCopyrightText: 2023 Bundesministerium des Innern und für Heimat, PG ZenDiS "Projektgruppe für Aufbau ZenDiS"
# SPDX-FileCopyrightText: 2023 Nextcloud GmbH
# SPDX-License-Identifier: AGPL-3.0-only
#!/usr/bin/env bash

# This bash script is to register and publish the apps in self-hosted appstore.
# To run this script the self-hosted appstore instances must be up and running

set -e

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

# env required
# NEXCLOUD_PATH=/home/nabin/www/stable29  # path to nextcloud
# $TAG=2.11.3 # tag that we want to publish
# WORKING_DIRECTORY=/home/nabin/www/fork-integrationOpenproject # current working directory simply done by pwd command

if [[ -z "$TAG" ]] || [[ -z "$NEXCLOUD_PATH" ]] || [[ -z "$WORKING_DIRECTORY" ]]; then
  log_error "Environment variables TAG, NEXCLOUD_PATH, or WORKING_DIRECTORY are missing."
  exit 1
fi

if [[ ! -d "$WORKING_DIRECTORY/integration_openproject" ]]; then
  log_error "integration_openproject directory does not exist."
  exit 1
fi

# build the apps
cd $WORKING_DIRECTORY
make -C integration_openproject

mkdir -p publish

# remove unnecessary app files
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
integration_openproject publish/

cd publish

# update version in info.xml
sed -i "s|<version>.*</version>|<version>$TAG</version>|" "integration_openproject/appinfo/info.xml" 

# https://nextcloudappstore.readthedocs.io/en/latest/developer.html#obtaining-a-certificate
log_info "Generating app.key and app.crt..."
sudo openssl req -x509 -newkey rsa:4096 -sha256 -nodes \
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
nextcloud_root_crt="${NEXCLOUD_PATH}/resources/codesigning/root.crt"
if [[ -f ${nextcloud_root_crt} ]]; then
  echo "" >> ${nextcloud_root_crt}
  cat app.crt >> ${nextcloud_root_crt}
else
  log_error "Nextcloud's root.crt not found at ${nextcloud_root_crt}."
  exit 1
fi

# fix permisions for signing
sudo chown $USER: app.key
sudo chown -R $USER: $APP_ID

# Sign the app
# need full path for signing
log_info "Signing the app using occ integrity:sign-app command..."
php ${NEXCLOUD_PATH}/occ integrity:sign-app \
  --privateKey=${WORKING_DIRECTORY}/publish/app.key \
  --certificate=${WORKING_DIRECTORY}/publish/app.crt \
  --path=${WORKING_DIRECTORY}/publish/$APP_ID || { log_error "Failed to sign app."; exit 1; }

# php /home/runner/html/nextcloud/occ integrity:sign-app \
#   --privateKey=/home/runner/work/integration_openproject/integration_openproject/publish/app.key \
#   --certificate=/home/runner/work/integration_openproject/integration_openproject/publish/app.crt \
#   --path=/home/runner/work/integration_openproject/integration_openproject/publish/integration_openproject

# Archive the app
tar -czf $APP_ID-$TAG.tar.gz $APP_ID
if [[ ! -f $APP_ID-$TAG.tar.gz ]]; then
  log_error "Failed to archive the app. Archive file $APP_ID-$TAG.tar.gz not found."
  exit 1
fi
log_success "Archived the app into $APP_ID-$TAG.tar.gz."

# Sign the archive
sudo openssl dgst -sha512 -sign app.key $APP_ID-$TAG.tar.gz | openssl base64 | tee ${WORKING_DIRECTORY}/publish/sign.txt || { log_error "Failed to sign the archive."; exit 1; }
if [[ ! -s ${WORKING_DIRECTORY}/publish/sign.txt ]]; then
  log_error "Failed to sign the archive. Signature file sign.txt is empty or not found."
  exit 1
else
  log_success "Signed the app archive successfully."
fi

log_success "App build and release process has been completed successfully."

## copy archieve in nextcloud directory to download
cp $APP_ID-$TAG.tar.gz ${NEXCLOUD_PATH}/$APP_ID-$TAG.tar.gz
if [[ -f ${NEXCLOUD_PATH}/$APP_ID-$TAG.tar.gz ]]; then
  log_success "App archive has been copied successfully."
else
  log_error "Failed to copy app archive to ${NEXCLOUD_PATH}."
  exit 1
fi

## Prepare apps.json file
if [[ ! -f ${WORKING_DIRECTORY}/publish/integration_openproject/appinfo/signature.json ]]; then
  log_error "Signature file not found at ${WORKING_DIRECTORY}/publish/integration_openproject/appinfo/signature.json."
  exit 1
fi

# Convert sign.txt content to one line by removing newlines
signature=$(tr -d '\n' < "${WORKING_DIRECTORY}/publish/sign.txt")
certificate=$(jq '.certificate' ${WORKING_DIRECTORY}/publish/integration_openproject/appinfo/signature.json)

cat > apps.json <<EOF
[
  {
    "id": "$APP_ID",
    "releases": [
      {
        "version": "$TAG",
        "minIntSize": 32,
        "download": "http://localhost/$APP_ID-$TAG.tar.gz",
        "licenses": [
          "agpl"
        ],
        "isNightly": false,
        "rawPlatformVersionSpec": "\u003E=28 \u003C=31",
        "signature": "$signature",
        "signatureDigest": "sha512"
      }
    ],
    "certificate": $certificate
  }
]
EOF

cp apps.json ${NEXCLOUD_PATH}

if [[ -f ${NEXCLOUD_PATH}/apps.json ]]; then
  log_success "apps.json file has been copied successfully."
else
  log_error "Failed to copy apps.json to ${NEXCLOUD_PATH}."
  exit 1
fi