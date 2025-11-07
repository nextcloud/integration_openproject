#!/bin/bash
# SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
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


ELEMENT_ROOM_ID=wNGZBbAPrhCiGXtQYp:openproject.org

is_latest_release_tag() {
  log_info "Checking for new $REPO_NAME release..."
  if [[ $REPO_NAME = "oidc" ]]; then
    REPO_OWNER=H2CK
  else
    REPO_OWNER=nextcloud
  fi
  yesterday_date=$(date -d "yesterday" +%F)

  releases_api_status_code=$(curl -s -w "%{http_code}" -H "Authorization: token $GITHUB_TOKEN" \
  "https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/releases" -o /tmp/releases.json)

  releases_json=$(cat /tmp/releases.json)

  if [[ "$releases_api_status_code" -ne 200 ]]; then
    log_error "❌ Failed to get \"$REPO_NAME\" release info with status code $releases_api_status_code"
    log_error "$releases_json"
    exit 1
  fi

  nextcloud_latest_release_tag=$(echo "$releases_json" \
    | jq -r '.[]
    | select(.created_at | startswith("2025-08-07"))
    | .tag_name')

  # Check if the tag is empty or null
  if [[ -z "$nextcloud_latest_release_tag" || "$nextcloud_latest_release_tag" == "null" ]]; then
      log_info "No new \"$REPO_NAME\" release found for the date $yesterday_date."
      return 1 # false
  fi

  # Count how many versions are in the release list
  version_count=$(echo "$nextcloud_latest_release_tag" | wc -l)

  if [[ $version_count -gt 1 ]]; then
      log_info "Multiple $REPO_NAME releases found: $version_count versions."
      # Join multiple releases into a single line, separated by comma + space
      nextcloud_latest_release_tag=$(paste -sd', ' <<<"$nextcloud_latest_release_tag")
      mulitple="Multiple "
  fi

  log_info "On date $yesterday_date, \nfound new release tag(s): $nextcloud_latest_release_tag"
  return 0 # true
}

send_message_to_room() {
  send_message_to_room_response=$(
  curl -s -o /dev/null -w "%{http_code}" -XPOST \
    "$ELEMENT_CHAT_URL/_matrix/client/r0/rooms/%21$ELEMENT_ROOM_ID/send/m.room.message?access_token=$NIGHTLY_CI_USER_TOKEN" \
    -d '{
      "msgtype": "m.text",
      "body": "",
      "format": "org.matrix.custom.html",
      "formatted_body": "<h3>🔔 '"$mulitple"' '"$REPO_NAME"' Release Alert! : '"$nextcloud_latest_release_tag"'</h3>"
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

for REPO_NAME in $REPO_NAMES; do
  if is_latest_release_tag; then
    send_message_to_room
  fi
  log_info "----------------------------------------------------------"
done