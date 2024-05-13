This file consists of some smoke testing to be done before application release (major and minor).
The importance of this smoke testing (manual) is that we do not have e2e test setup to automate that involves both `OpenProject` and `Nextcloud`.

## Smoke Test for `integration_openproject`
### 1. Oauth flow for OpenProject Nextcloud Integration (without project folder setup)

- [ ] Create a file storage in `OpenProject`
- [ ] Copy `OpenProject` Oauth Credential to save in `Nextcloud`
- [ ] Copy `Nextcloud` Oauth Credential to save in `OpenProject`
- [ ] Complete the admin config set up without project folder setup in `Nextcloud`
- [ ] Complete the admin config without application password setup in `OpenProject`
- [ ] From the personal section of admin in `Nextcloud`, connect to `OpenProject`
- [ ] Create a user in both `Nextcloud` as well as `OpenProject`
- [ ] From the personal section of the created user in `Nextcloud`, connect as created user to `OpenProject`.

### 2. Project Folder Setup

- [ ] Enable `groupfolders` app in `Nextcloud`
- [ ] Set up project folder in `Nextcloud`
- [ ] check for the application password generated
- [ ] Copy the application password and save it to `OpenProject` file storage.
- [ ] Check for the user and group named `OpenProject` created such that the user `OpenProject` is admin of the group `OpenProject`.
- [ ] Try to delete the user and group `OpenProject`. (should not be deleted)
- [ ] Also replace and check for the application password generation


### 3. Link work package for a file/folder

- [ ] On `OpenProject` select a project and add the created file storage for the project
- [ ] Search workpackages from the selected openproject project in the `Nextcloud` openproject tab.
- [ ] Link the searched workpackage.
- [ ] Try linking other workpackages and also try to unlink.
- [ ] Navigate to the workpackage on `OpenProject` from the linked workpackage from `Nextcloud`.
- [ ] From the batch action in `Nexcloud`, select multiple files and try to link those selected files to a workpackage. 

### 4. Create WorkPackage from the Nextcloud

- [ ] Open the form to create workpackage from the nextcloud (shoudl be sidebar panel openproject tab) for a file/folder
- [ ] Fill up the form and create a workpackage.
- [ ] Work package should be created and linked to the selected file.
- [ ] Play around to see if the form to create workpackage in openproject works.


