#!/bin/bash
# SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

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

# Validate required environment variables
validate_environment() {
  required_variables="GITHUB_TOKEN ELEMENT_ROOM_ID ELEMENT_CHAT_URL NIGHTLY_CI_USER_TOKEN REPO_NAMES"
  for required_variable in $required_variables; do
    if [[ -z "${!required_variable:-}" ]]; then
      log_error "Missing required environment variables: $required_variable"
      exit 1
    fi
  done
}

is_latest_release_tag() {
  # Set nextcloud as app name for message
  if [[ $REPO_NAME == "server" ]]; then
    APP_NAME="Nextcloud"
  else
    APP_NAME=$REPO_NAME
  fi

  # Set repository owner based on repo name
  if [[ $REPO_NAME = "oidc" ]]; then
    REPO_OWNER=H2CK
  else
    REPO_OWNER=nextcloud
  fi

  # Use yesterday's date for checking releases
  yesterday_date=$(date -d "yesterday" +%F) # e.g. date format 2025-08-07
  log_info "Looking for \"$APP_NAME\" releases created on: $yesterday_date"

  releases_api_status_code=$(curl -s -w "%{http_code}" -H "Authorization: token $GITHUB_TOKEN" \
    "https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/releases" -o /tmp/releases.json)

  releases_json=$(cat /tmp/releases.json)

  if [[ "$releases_api_status_code" -ne 200 ]]; then
    log_error "❌ Failed to get new releases of \"$APP_NAME\". Got status code: $releases_api_status_code"
    log_error "$releases_json"
    exit 1
  fi

  nextcloud_latest_release_tag=$(echo "$releases_json" |
    jq -r --arg date "$yesterday_date" '.[]
    | select(.created_at | startswith($date))
    | .tag_name')

  # Check if the tag is empty or null
  if [[ -z "$nextcloud_latest_release_tag" || "$nextcloud_latest_release_tag" == "null" ]]; then
    log_info "No new release of \"$APP_NAME\""
    return 1 # false
  fi

  # Some repos may have multiple releases in a day
  # Count how many versions are in the release list
  version_count=$(echo "$nextcloud_latest_release_tag" | wc -l)

  if [[ $version_count -gt 1 ]]; then
    log_info "Multiple new releases of \"$APP_NAME\" found: $version_count versions."
    # Join multiple releases into a single line, separated by comma + space
    message='<b>🔔 Alert! Multiple new releases of \"'$APP_NAME'\":<b> '
    mapfile -t tags <<< "$nextcloud_latest_release_tag" # Convert newlines into array elements

    for tag in "${tags[@]}"; do
      message+="<a href='https://github.com/$REPO_OWNER/$REPO_NAME/releases/tag/$tag'>$tag</a>, "
    done

    message=${message%, } # Remove trailing comma and space
  else
    message='<b>🔔 Alert! New release of \"'$APP_NAME'\":<b> <a href='https://github.com/$REPO_OWNER/$REPO_NAME/releases/tag/$nextcloud_latest_release_tag'>'$nextcloud_latest_release_tag'</a>'
  fi

  log_info "Found new release tag(s): $nextcloud_latest_release_tag"
  return 0 # true
}

send_message_to_room() {
  log_info "Sending message to Element room..."

  send_message_to_room_response=$(
    curl -s -o /dev/null -w "%{http_code}" -XPOST \
      "$ELEMENT_CHAT_URL/_matrix/client/r0/rooms/%21$ELEMENT_ROOM_ID/send/m.room.message?access_token=$NIGHTLY_CI_USER_TOKEN" \
      -d '{
      "msgtype": "m.text",
      "body": "",
      "format": "org.matrix.custom.html",
      "formatted_body": "'"$message"'"
    }'
  )

  # Check if the message was sent successfully
  if [[ ${send_message_to_room_response} == 200 ]]; then
    log_success "✅ Message sent successfully to Element room!"
  else
    log_error "❌ Failed to send message to Element room."
    log_error "Response code: $send_message_to_room_response"
    exit 1
  fi
}

# Main execution
main() {
  validate_environment

  log_info "Checking new releases of: \"$REPO_NAMES\""

  for REPO_NAME in $REPO_NAMES; do
    if is_latest_release_tag; then
      send_message_to_room
    fi

    log_info "----------------------------------------------------"
  done
  log_success "Release check completed!"
}

main "$@"
