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

if [ -z "$APP_ID" ] || [ -z "$APP_VERSION" ]; then
  log_error "One or more required environment variables are missing: APP_ID, APP_VERSION"
  exit 1
fi

registerApps() {
  app_name=$1

  register_app=$(curl -s -o /dev/null -w "%{http_code}" -X POST -uadmin:admin \
    http://localhost:8000/api/v1/apps \
    -H "Content-Type: application/json" \
    -d "{
    \"certificate\": \"${CERTIFICATE}\",
    \"signature\": \"${SIGNATURE}\"
    }")
  if [[ ${register_app} == 201 ]]; then
    log_success "\"${app_name}\" has been registered successfully!"
  elif [[ ${register_app} == 204 ]]; then
    log_info "\"${app_name}\" has been updated!"
  elif [[ ${register_app} == 400 ]]; then
    log_error "\"${app_name}\" contains invalid characters, the signature!"
    exit 1
  else
    log_error "Failed to register \"${app_name}\""
    exit 1
  fi
}

publishApps() {
  app_name=$1
  app_version=$2

  register_app=$(curl -s -o /dev/null -w "%{http_code}" -X POST -uadmin:admin \
    http://localhost:8000/api/v1/apps/releases \
    -H "Content-Type: application/json" \
    -d "{
    \"download\":\"${DOWNLOAD_URL}\",
    \"signature\": \"${SIGNATURE}\"
    }")
  if [[ ${register_app} == 200 ]]; then
    log_success "\"${app_name} ${app_version}\" has been updated successfully!"
  elif [[ ${register_app} == 201 ]]; then
    log_success "\"${app_name} ${app_version}\" has been published successfully!"
  else
    log_error "Failed to publish \"${app_name} ${app_version}\""
    exit 1
  fi
}

if [[ $APP_ID == "integration_openproject" ]]; then
  # public certificate of the integration app for testing
  CERTIFICATE="-----BEGIN CERTIFICATE-----\r\nMIIEEjCCAvoCAhF6MA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\r\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\r\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\r\ndXRob3JpdHkwHhcNMjEwMzE4MTMwMTExWhcNMzEwNjI0MTMwMTExWjAiMSAwHgYD\r\nVQQDDBdpbnRlZ3JhdGlvbl9vcGVucHJvamVjdDCCAiIwDQYJKoZIhvcNAQEBBQAD\r\nggIPADCCAgoCggIBALn0ohZShOzR6UJAuN4IErLD5jenUWr83XnKCouC0qeXH6FI\r\nTNGTyOy\/KbDDRIoL1L20xYRl5UKwTbDye10ItUBhNcv72pJ2rDOSJrL84fqMxf00\r\nWdd\/APXJfNNqtgh1QTq9vvim9YCEu7JdeIhZK9ea89RPn47iSj7YijY78mGBfyfm\r\nqpHRYX\/QZAQcwjO2lE9soWUaZlrqu3mxTI218zmaqqcma4x3QakfsZeXZhQSU7D1\r\n6iYG8wy8IaYueJM5OoRRziBXoIfPpwYpEj4RhV1WME9jGhutyrHYg3jAdfvzsFVG\r\ngSVUP2ey1sq3HGZGbzWMBFLDGqfet0lGBIB0HTna1Zvu3ZnuK2uV3MObCmBBbBSs\r\n\/s8hyQTqWEbY2aqVoTBN5lyogwfL6pgZJFvhmtg21oHxBBqqAeQ+TZmWD62WorsX\r\n4F6Ahh1VKkmr5LkVvr2CfME0M1mj9s9gSc7ekXk1oHabH+wwgJV2ZhyezhXgWKgL\r\nUahjSRzkKqp5mbh27sg1kLCx9QNyXxaz8rnAcazGB00JzQlUmXg76cJ0v\/M3qihz\r\nQR5oju\/iMiUYKtqec9LU6wfvmGOOvtl2OFOD3ff69FPS2Km8He4pFWkSqw4DGivE\r\nIJLlgqLGIkWm+uNyocANtYqib52AYwJ\/nFMF6nzOvM1LoxHyJlFmudZRju2jAgMB\r\nAAEwDQYJKoZIhvcNAQELBQADggEBAD8mQtw0p3oh9fyOuyTmalHxoG9rLiV0Q2mz\r\n1T0jonVYN7YqSxS\/yWIQnZQ98x2nU93Be4G9VaLT0NZvRjnem2zemSVvuwp11GeK\r\ne80gJTaJjh8n1Z+gD6GU4C+LjWeiR75sd6Jcqfp3bqL6FGvSzIk3QQOfWuC03aXa\r\nFRleNH6rkMV30sWnXyocatculf7ThHZQMN1c0KuQFrd\/alQh\/+EyjBleLozkeC6G\r\n9IlE9DGRK0NUSvy7W68I7cVhR2ToE8oApdOJ1Cd6TpTYMRtvI2lQ4F7vF++ym0Lw\r\nMIxSI44hNeixh8Yn9rcy\/LqOUgl0niB5hfAiauRwHcOY5wf1hKE=\r\n-----END CERTIFICATE-----"
  DOWNLOAD_URL="https://github.com/nextcloud/$APP_ID/releases/download/v$APP_VERSION/$APP_ID-$APP_VERSION.tar.gz"
elif [[ $APP_ID == "groupfolders" ]]; then
  # public certificate of the groupfolders app for testing
  CERTIFICATE="-----BEGIN CERTIFICATE-----\r\nMIIEBzCCAu8CAhBMMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\r\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\r\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\r\ndXRob3JpdHkwHhcNMTcwNDI2MTc0NDAwWhcNMjcwODAyMTc0NDAwWjAXMRUwEwYD\r\nVQQDEwxncm91cGZvbGRlcnMwggIiMA0GCSqGSIb3DQEBAQUAA4ICDwAwggIKAoIC\r\nAQDa7N4NmUyKgT6Ovo5EXdXV7eBFaCRWKvgnwgpmpYCdSdPpprBaoyKka5FWi2Zo\r\nB2QCDQtmRuZ9RpiAblvLGeIYI1hNd1O6632U5wLtZFa2WsueV8lFGiXIy+r7jK8R\r\nc56tmeclKP4RImk4yydec89bB2\/MmVYaNkNqueckg239C1Nt0GfmmQaTlANvGVAe\r\ndb0+Y+\/YBSHSSE8TDf6n3kFR5RIWspbXv3mwFOaTTxtR+hi01eaPWnGnRz2w1+Wn\r\nXJQVBzLGuQI\/GdYZItbZ5PgVuHMcvIc6elJENiUzqli3y6VbA9posefkPAph75ny\r\nqa+B7RppiE+5d4er8es04azEKjlcmGi6zkosQEikeT01zIiiDjsvsW1gFd5KQ+OX\r\nfUsuL2R8ymX6btj5Ee6lAK7qfCgQfKEIjl5oz48+h46ERe\/NToZsNS\/g5sO5UfG5\r\n+URERPuMteaF6bKMZlHLEVco8RUttocfHGAHuA4PIWgR\/XKLdJ\/c1VsjsvMak90u\r\nfKR6vkGFizGKHQG+2ZXJQuBAih6lQ7Lbd\/v5NWIMiYHZG990EXc6nUmKUcepTl9P\r\n6CfFq4LyX4jEN8KkuLsXk5jMgDf5LjNNvqOXH\/dbynUrjUb+mCMLCq6lvp3SD39m\r\nw1LjNZe6TDsZmPN\/+XoeG88zvidGszy7dVbO2HDcDssnWwIDAQABMA0GCSqGSIb3\r\nDQEBCwUAA4IBAQBqTrXOxSRaqdcBPUfuwWTPs+OzJjJ77DXhQKP3zMMVAadWN1O4\r\naVQ6Q2m6+1YocW4cI1WUiV5JkIXHZk3CZc7GxMmA6E\/STpNfDG+gp1G8ZFkVa7Dr\r\nfYBIvzu1ORvGdLygaiRGDdkc0Rsm49O41T6uKvmuQfBZqosSm4+pMA7MRIyLmi4n\r\nsM5F8ksDKX9dyA3SVufPgb4Qy8Hy85ory4GaPkdDgry3nDK1AU+ZmFyRXo5GfMsG\r\nIGWvIBP52FpCyb\/papXhtLzajVgEY4o0Asv\/E7UFymnOofTrBmZA\/+z3n59\/sZUT\r\nKclsORyDjRlH1yV02PDfgk8Hw2RR5fmaoP3h\r\n-----END CERTIFICATE-----"
  DOWNLOAD_URL="https://github.com/nextcloud-releases/$APP_ID/releases/download/v$APP_VERSION/groupfolders-v$APP_VERSION.tar.gz"
else
  log_error "Invalid app name: $APP_ID"
  log_error "Please set the APP_ID environment variable to either \"integration_openproject\" or \"groupfolders\""
  exit 1
fi

registerApps "$APP_ID"
publishApps "$APP_ID" "$APP_VERSION"