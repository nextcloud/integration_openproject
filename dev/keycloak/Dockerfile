# SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

ARG IMAGE_REPO=quay.io/keycloak/keycloak
ARG IMAGE_TAG=26.2

FROM busybox AS commands

RUN wget -qO /bin/curl https://github.com/moparisthebest/static-curl/releases/latest/download/curl-amd64

FROM ${IMAGE_REPO}:${IMAGE_TAG}

COPY --from=commands --chmod=755 /bin/curl /usr/bin/curl
COPY --from=commands --chmod=755 /bin/which /usr/bin/which
COPY --chmod=755 keycloak.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
