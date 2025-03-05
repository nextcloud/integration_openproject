#!/bin/bash
# SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

set -e

OCC() {
    # shellcheck disable=SC2068
    sudo -E -u www-data php "$WEBROOT/occ" $@
}

OCC a:e integration_openproject
OCC security:certificates:import /etc/ssl/certs/ca-certificates.crt
