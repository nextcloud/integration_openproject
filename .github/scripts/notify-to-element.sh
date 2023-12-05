#!/bin/bash

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

log_info "Fetching all workflow jobs....."

response=$(curl -s -H "Authorization: token $GITHUB_TOKEN" \
  "https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/actions/runs/$RUN_ID/jobs")

log_info "Fetching jobs informations succeeded!
"
if [[ "$response" != *"jobs"* ]]; then
  log_error "No jobs found in the below response!"
  log_info "$response"
  exit 1
fi

jobs_informations=$(echo "$response" | jq '.jobs[:-1]')
jobs_conclusions=$(echo "$jobs_informations" | jq -r '.[].conclusion')

workflow_status="Success"
if [[ " ${jobs_conclusions[*]} " == *"failure"* ]]; then
  workflow_status="Failure"
elif [[ " ${jobs_conclusions[*]} " == *"cancelled"* ]]; then
  workflow_status="Cancelled"
elif [[ " ${jobs_conclusions[*]} " == *"skipped"* ]]; then
  workflow_status="Skipped"
fi

log_info "Sending report to the element chat...."

send_message_to_room_response=$(curl -s -XPOST "$ELEMENT_CHAT_URL/_matrix/client/r0/rooms/%21$ELEMENT_ROOM_ID/send/m.room.message?access_token=$NIGHTLY_CI_USER_TOKEN" \
                                      -d '
                                          {
                                            "msgtype": "m.text",
                                            "body": "",
                                            "format": "org.matrix.custom.html",
                                            "formatted_body": "<a href='https://github.com/$REPO_OWNER/$REPO_NAME/actions/runs/$RUN_ID'>NC-Nightly-'$BRANCH_NAME'</a><br></br><b><i>Status: '$workflow_status'</i></b>"
                                          }
                                        '
                                      )

if [[ "$send_message_to_room_response" != *"event_id"* ]]; then
  log_error "Failed to send message to element. Below response did not contain event_id!"
  log_info "$send_message_to_room_response"
  exit 1
fi

log_success "Notification of the nightly build has been sent to Element chat (OpenProject + Nextcloud)"
