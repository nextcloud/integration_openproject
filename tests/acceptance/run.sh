#!/bin/bash

# SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

SCRIPT_DIR="$(dirname "$0")"
REPORTS_DIR="${SCRIPT_DIR}/reports"

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

if [ ! -f "$REPORTS_DIR/failures.txt" ] && [ ! -f "$REPORTS_DIR/unexpected-passes.txt" ]; then
  # pass the test execution if the exit code is non-zero
  # but there are expected failures defined.
  if [ $exit_code -eq 1 ] && [ -s "$REPORTS_DIR/expected-failures.txt" ]; then
    exit_code=0
  fi
else
  if [ -s "$REPORTS_DIR/failures.txt" ]; then
    echo ""
    echo "[ERROR] Failed scenarios:"
    cat "$REPORTS_DIR/failures.txt"
    exit_code=1
  fi
  if [ -s "$REPORTS_DIR/unexpected-passes.txt" ]; then
    echo ""
    echo "[ERROR] Unexpected passed scenarios:"
    cat "$REPORTS_DIR/unexpected-passes.txt"
    exit_code=1
  fi
fi

if [ -s "$REPORTS_DIR/expected-failures.txt" ]; then
  echo ""
  echo "[INFO] Expected failed scenarios:"
  cat "$REPORTS_DIR/expected-failures.txt"
fi

echo ""
exit $exit_code