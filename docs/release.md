<!--
  - SPDX-FileCopyrightText: 2024-2025 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Release Process

## 1. Release Preparation

### a. Major/Minor Release

1. Create a release branch from the master with the format `release/<major>.<minor>` (e.g. `release/2.1`).

On the release branch:

2. In case any new feature was added, update the feature description in:
   - [README.md](../README.md)
   - [appinfo/info.xml](../appinfo/info.xml)
3. Update the version in:
   - [appinfo/info.xml](../appinfo/info.xml)
   - [package.json](../package.json)
4. Update the minimum and maximum nexcloud version support in:
   - [appinfo/info.xml](../appinfo/info.xml)
   - [shared workflow CI](../.github/workflows/shared_workflow.yml)
   - [nighlty ci release branch](../.github/workflows/nighlty-ci-release-branch.yml)
   - [release CI](../.github/workflows/release.yml)
5. Add the new release branch in `.tx/backport` to allow transifex commits.
6. Update `CHANGELOG.md` with the changes and the version to be released.
7. Update the new release branch in the [nightly CI](../.github/workflows/nighlty-ci-release-branch.yml).
8. Perform confirmatory testing (Changelogs) - by the OpenProject team.
9. Perform [smoke testing](testing/smoke_testing.md) - by the OpenProject team.

### b. Patch Release

On the current release branch:

1. Update the patch version in:
   - [appinfo/info.xml](../appinfo/info.xml)
   - [package.json](../package.json)
2. Update `CHANGELOG.md` with the changes and the version to be released.
3. Update the minimum and maximum nexcloud version support in:
   - [appinfo/info.xml](../appinfo/info.xml)
   - [shared workflow CI](../.github/workflows/shared_workflow.yml)
   - [nighlty ci release branch](../.github/workflows/nighlty-ci-release-branch.yml)
   - [release CI](../.github/workflows/release.yml)
4. Perform confirmatory testing (Changelogs) - by the OpenProject team.
5. Perform [smoke testing](testing/smoke_testing.md) - by the OpenProject team.

## 2. Publish Release

> [!IMPORTANT]
>
> The tag MUST follow the following format:
>
> - For release: `vX.Y.Z` (e.g. `v2.1.1`)
> - For test release: `vX.Y.Z-yyyymmdd-nightly` (e.g. `v2.1.1-20220928-nightly`)

1. Tag a commit from the release branch.

   ```bash
   git tag vX.Y.Z -m "vX.Y.Z"

   # E.g.:
   # git tag v2.1.1-20220928-nightly -m "v2.1.1-20220928-nightly"
   ```

   > **_NOTE:_** Every tag should be created with a unique commit, or the publish will fail.

2. Push the tag to the `auto-release` branch.

   ```bash
   git push origin release/<major>.<minor>:auto-release vX.Y.Z

   # E.g.:
   # git push origin release/2.1:auto-release v2.1.1-20220928-nightly
   ```

3. Approve the deployment in GitHub actions.
4. Check the release on Nextcloud [app store](https://apps.nextcloud.com/apps/integration_openproject/releases).

## 3. After Release

1. Add the release notes to the newly created [GitHub release](https://github.com/nextcloud/integration_openproject/releases).
2. Merge the necessary commits from the release branch into the `master` branch.
3. In the `master` branch, bump the app version to the next version (e.g.: `X.(Y+1).0-alpha.1`).
