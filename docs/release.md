### Release:

#### 1. Release Preparation

##### a. In case of a major/minor version
1. In case any new feature were added, update the feature description in `README.md` and `appinfo/info.xml`.
2. Create a release branch from master with the name `release/<version>` e.g. `release/2.1`.
3. On the release branch update the version in `appinfo/info.xml`.
4. Allow transifex to write on release branch i.e. update in `.tx/backport`.
5. Do QA for the fixes of bugs in the release branch.
6. Perform the smoke testing described in `docs/testing/smoke_testing.md` in order to detect regression.
7. Update `.github/workflows/nighlty-ci-release-branch.yml` to run nightly on release branch.
8. Merge the release branch into the `master` branch, to get all good changes also into the current development.
9. Add change log in `CHANGELOG.md` with the version to be released.
10. If any unreleased changes in `CHANGELOG.md`, Add them to the newly added change log.

##### b. In case of a patch version

1. On the release branch of the current minor version update the version in `appinfo/info.xml` (not needed for nightly builds).
2. Merge the release branch into the `master` branch, to get all good changes also into the current development.
3. Add change log in `CHANGELOG.md` with the version to be released.
4. If any unreleased changes in `CHANGELOG.md`, Add them to the newly added change log.

#### 2. Publish Release
1. Tag a commit on the `release/<version>` branch. The tag must have the format `v2.1.1` for releases and `v2.1.1-20220928-nightly` for nightly builds.
   >***Note:*** Every tag should be created with a unique commit or else the publish will fail.

   e.g: `git tag v2.0.6-20220928-nightly -m "v2.0.6-20220928-nightly"`.
2. Push the tag to the `auto-release`  branch: `git push origin release/<version>:auto-release --tags -f`.
3. Approve the deployment in GitHub actions.

#### 3. After Release
1. Generate all the change logs for the new version of app on GitHub
   e.g. for newly created tag vX.X.X, follow `https://github.com/nextcloud/integration_openproject/releases/tag/vX.X.X`
