#!/bin/bash

# SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

SCRIPT_DIR="$(dirname "$0")"
REPORTS_DIR="${SCRIPT_DIR}/reports"
EXPECTED_FAILURES_FILE="$REPORTS_DIR/expected-failures.txt"
FAILURES_FILE="$REPORTS_DIR/failures.txt"
UNEXPECTED_PASSES_FILE="$REPORTS_DIR/unexpected-passes.txt"

# options for behat command
BEHAT_OPTIONS=(-f pretty)

if [ -n "$BEHAT_FILTER_TAGS" ]; then
  BEHAT_OPTIONS+=(--tags "$BEHAT_FILTER_TAGS")
fi

function run_test() {
  local args=("${BEHAT_OPTIONS[@]}")
  if [ -n "$BEHAT_FEATURE_PATH" ]; then
    args+=("$BEHAT_FEATURE_PATH")
  fi
  composer run test:api -- "${args[@]}"
}

# cleanup reports directory
rm -rf "$REPORTS_DIR"

# run the tests
run_test

exit_code=$?

if [ ! -f "$FAILURES_FILE" ] && [ ! -f "$UNEXPECTED_PASSES_FILE" ]; then
  # pass the test execution if the exit code is 1
  # but there are expected failures defined.
  if [ $exit_code -eq 1 ] && [ -s "$EXPECTED_FAILURES_FILE" ]; then
    exit_code=0
  fi
else
  if [ -s "$FAILURES_FILE" ]; then
    echo ""
    echo "[ERROR] Failed scenarios:"
    cat "$FAILURES_FILE"
    exit_code=1
  fi
  if [ -s "$UNEXPECTED_PASSES_FILE" ]; then
    echo ""
    echo "[ERROR] Unexpected passed scenarios:"
    cat "$UNEXPECTED_PASSES_FILE"
    exit_code=1
  fi
fi

if [ -s "$EXPECTED_FAILURES_FILE" ]; then
  echo ""
  echo "[INFO] Expected failed scenarios:"
  cat "$EXPECTED_FAILURES_FILE"
fi

echo ""
exit $exit_code