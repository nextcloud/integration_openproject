#!/bin/bash
# SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

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

required_vars=(
  ELEMENT_CHAT_URL
  ELEMENT_ROOM_ID
  NIGHTLY_CI_USER_TOKEN
  GITHUB_REPOSITORY
  GITHUB_RUN_ID
  BRANCH_NAME
  NEEDS_JSON
)

for var in "${required_vars[@]}"; do
  if [[ -z "${!var}" ]]; then
    log_error "❌ Missing required environment variable: $var"
    log_info ""
    log_info "Required environment variables:"
    log_info "- ELEMENT_CHAT_URL       : URL of the Element chat (e.g. https://matrix.element.io)"
    log_info "- ELEMENT_ROOM_ID        : Matrix room ID (e.g. abcdefg:matrix.element.io)"
    log_info "- NIGHTLY_CI_USER_TOKEN  : Access token for sending messages (e.g. "sometoken")"
    log_info "- GITHUB_REPOSITORY      : GitHub repository (e.g. user/repo) set by GitHub Actions environment variable"
    log_info "- GITHUB_RUN_ID          : GitHub run ID (e.g. 123456789) set by GitHub Actions environment variable"
    log_info "- BRANCH_NAME            : Branch name (e.g. master)"
    log_info "- NEEDS_JSON             : JSON string containing job results"
    log_info ""
    exit 1
  fi
done

jobs=$(echo "$NEEDS_JSON" | jq -r 'keys[]' 2>/dev/null)
if [[ -z "$jobs" ]]; then
  log_error "❌ No jobs found in below JSON:"
  log_info "$NEEDS_JSON"
  exit 1
fi

results=$(echo "$NEEDS_JSON" | jq -r '.[].result' 2>/dev/null)

workflow_status="✅ Success"
if [[ "${results[*]}" == *"failure"* ]]; then
  workflow_status="❌ Failure"
elif [[ "${results[*]}" == *"cancelled"* ]]; then
  workflow_status="⚠️ Cancelled"
elif [[ "${results[*]}" == *"skipped"* ]]; then
  workflow_status="⚠️ Skipped"
fi

log_info "Sending report to the element chat...."

payload=$(cat <<EOF
{
  "msgtype": "m.text",
  "body": "",
  "format": "org.matrix.custom.html",
  "formatted_body": "<a href=\"https://github.com/${GITHUB_REPOSITORY}/actions/runs/${GITHUB_RUN_ID}\">NC-Nightly-${BRANCH_NAME}</a><br></br><b>Status: ${workflow_status}</b>"
}
EOF
)

send_message_to_room_response=$(curl -s -XPOST "$ELEMENT_CHAT_URL/_matrix/client/r0/rooms/%21$ELEMENT_ROOM_ID/send/m.room.message?access_token=$NIGHTLY_CI_USER_TOKEN" \
                                      -d "$payload"
                                      )

if [[ "$send_message_to_room_response" != *"event_id"* ]]; then
  log_error "Failed to send message to element. Below response did not contain event_id!"
  log_info "$send_message_to_room_response"
  exit 1
fi

log_success "Notification of the nightly build has been sent to Element chat (OpenProject + Nextcloud)"