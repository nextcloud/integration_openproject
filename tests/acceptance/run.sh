#!/bin/bash

SCRIPT_DIR="$(dirname "$0")"
BEHAT_CONFIG="${SCRIPT_DIR}/config/behat.yml"
REPORTS_DIR="${SCRIPT_DIR}/reports"

if [ -z "$BEHAT_FILTER_TAGS" ]; then
  BEHAT_FILTER_TAGS=""
fi

function run_test() {
  composer run test:api -- -c $BEHAT_CONFIG -f pretty --tags $BEHAT_FILTER_TAGS $BEHAT_FEATURE_PATH
}

# cleanup reports directory
rm -rf $REPORTS_DIR

# run the tests
run_test

exit_code=$?

if [ $exit_code -ne 0 ]; then
  echo "### [ TEST REPORTS ] ###"
fi

if [ ! -f "$REPORTS_DIR/failures.txt" ] && [ ! -f "$REPORTS_DIR/unexpected-passed.txt" ]; then
  # pass the test execution if the exit code is non-zero
  # but there are expected failures defined.
  if [ $exit_code -ne 0 ] && [ -s "$REPORTS_DIR/expected-failures.txt" ]; then
    exit_code=0
  fi
else
  if [ -s "$REPORTS_DIR/failures.txt" ]; then
    echo "[ERROR] Failed scenarios:"
    cat "$REPORTS_DIR/failures.txt"
    echo ""
  fi
  if [ -s "$REPORTS_DIR/unexpected-passed.txt" ]; then
    echo "[ERROR] Unexpected passed scenarios:"
    cat "$REPORTS_DIR/unexpected-passed.txt"
    echo ""
  fi
fi

if [ -s "$REPORTS_DIR/expected-failures.txt" ]; then
  echo "[INFO] Expected failed scenarios:"
  cat "$REPORTS_DIR/expected-failures.txt"
fi

exit $exit_code