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
	ELEMENT_CHAT_URL,
	ELEMENT_ROOM_ID,
	NIGHTLY_CI_USER_TOKEN,
	GITHUB_TOKEN,
	REPO_OWNER,
	REPO_NAME,
	RUN_ID,
	BRANCH_NAME,
	WORKFLOW_STATUS,
	JOB_NAME
)

for var in "${required_vars[@]}"; do
  if [[ -z "${!var}" ]]; then
    log_error "❌ Missing required environment variable: $var"
    log_info ""
    log_info "Available environment variables:"
    log_info "- ELEMENT_CHAT_URL       : URL of the Element chat (e.g. https://matrix.element.io)"
    log_info "- ELEMENT_ROOM_ID        : Matrix room ID (e.g. abcdefg:matrix.element.io)"
    log_info "- NIGHTLY_CI_USER_TOKEN  : Access token for sending messages (e.g. "sometoken")"
    log_info "- GITHUB_TOKEN           : GitHub API access token (e.g. "sometoken")"
    log_info "- REPO_OWNER             : Repository owner (e.g. nextcloud)"
    log_info "- REPO_NAME              : Repository name (e.g. server)"
    log_info "- RUN_ID                 : GitHub Actions run ID (e.g. 26520692643)"
    log_info "- BRANCH_NAME            : Branch name (e.g. master)"
    log_info "- WORKFLOW_STATUS        : Workflow status (success/failure/cancelled)"
    log_info "- JOB_NAME               : Name of the job (e.g. builds)"
    log_info ""
    exit 1
  fi
done

log_info "Sending report to the element chat...."

if [[ "$WORKFLOW_STATUS" == "success" ]]; then
  WORKFLOW_STATUS="✅ Success"
elif [[ "$WORKFLOW_STATUS" == "failure" ]]; then
  WORKFLOW_STATUS="❌ Failure"
else
  WORKFLOW_STATUS="⚠️ $WORKFLOW_STATUS"
fi

if [[ "$JOB_NAME" == "builds" ]]; then
  NIGHTLY_NAME="NC-Nightly"
elif [[ "$JOB_NAME" == "upgrade-test" ]]; then
  NIGHTLY_NAME="NC-Upgrade-Test-Nightly"
else
  log_error "Unknown job name: $JOB_NAME. Expected 'builds' or 'upgrade-test'."
  exit 1
fi

send_message_to_room_response=$(curl -s -XPOST "$ELEMENT_CHAT_URL/_matrix/client/r0/rooms/%21$ELEMENT_ROOM_ID/send/m.room.message?access_token=$NIGHTLY_CI_USER_TOKEN" \
                                      -d '
                                          {
                                            "msgtype": "m.text",
                                            "body": "",
                                            "format": "org.matrix.custom.html",
                                            "formatted_body": "<a href=\"https://github.com/'$REPO_OWNER'/'$REPO_NAME'/actions/runs/'$RUN_ID'\">'$NIGHTLY_NAME'-'$BRANCH_NAME'</a> <b><i>'$WORKFLOW_STATUS'</i></b>"
                                          }
                                        '
                                      )

if [[ "$send_message_to_room_response" != *"event_id"* ]]; then
  log_error "Failed to send message to element. Below response did not contain event_id!"
  log_info "$send_message_to_room_response"
  exit 1
fi

log_success "Notification of the nightly build has been sent to Element chat (OpenProject + Nextcloud)"
