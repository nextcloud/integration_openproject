<!--
  - SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

## 🧪 Running API tests

> **_NOTE:_**
> Before running the API tests, the nextcloud instance needs to be ready, and also integration app needs to be enabled

To run the whole of the acceptance tests locally run the command below:

```shell
NEXTCLOUD_BASE_URL=http://<nextcloud_host> \
make api-test
```

In order to run only a specific scenario:

```shell
NEXTCLOUD_BASE_URL=http://<nextcloud_host> \
BEHAT_FEATURE_PATH=tests/acceptance/features/api/directUpload.feature:15 \
make api-test
```

## Mark Scenario as Expected Failure

We can mark a scenario as expected to fail for all Nextcloud versions or for specific versions using tags.

- `@expect-fail`: Marks a scenario as expected failure for all Nextcloud versions
- `@expect-fail-on-nc<major-version>`: Marks a scenario as expected failure for a specific Nextcloud version. (E.g.: `@expect-fail-on-nc33`)

> **_NOTE:_**
> We MUST provide `NEXTCLOUD_VERSION` environment variable while running the tests that are tagged `@expect-fail-on-nc<major-version>`.
