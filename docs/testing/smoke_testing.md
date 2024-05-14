This file consists of some smoke testing to be done before integration application release (major and minor).
The need for this smoke testing (manual) is that we do not have e2e test setup to automate that involves both `OpenProject` and `Nextcloud`.

## Smoke Test for `integration_openproject` (In Nextcloud and OpenProject)
### 1. Oauth configuration (without project folder setup/Automatically managed folders)
- [ ] Navigate to `Administration > File storages` in `OpenProject`.
- [ ] Create a file storage type `Nextcloud` and name it as `Nextcloud` in `OpenProject`.
- [ ] Navigate to `Administration Settings > OpenProject` in admin setting of `Nextcloud`.
- [ ] Copy `OpenProject` Oauth Credential (client_id and client_secret) and save them in `Nextcloud`.
- [ ] Copy `Nextcloud` Oauth Credential (client_id and client_secret) and save them in `OpenProject`.

### 2. Connect Nextcloud with OpenProject (Without project folder setup)
- [ ] Complete Smoke Test No 1.
- [ ] In `Administration Settings > OpenProject` personal setting of `Nextcloud` admin, connect to `OpenProject`.
- [ ] `Nextcloud` amdin should be connected as an `OpenProject` admin.
- [ ] Also, create a user in both `Nextcloud` as well as `OpenProject`.
- [ ] From the personal section of the created user in `Nextcloud`, connect to `OpenProject`.
- [ ] `Nextcloud` user should be connected as an `OpenProject` user.

### 3. Add File storage (Nextcloud) to an OpenProject project
- [ ] Complete Smoke Test No 1.
- [ ] Select an `OpenProject` Project (e.g `Demo Project`) in `OpenProject`.
- [ ] Navigate to `Project setings > modules` of `Demo Project`.
- [ ] In modules, check `File storages`, choose `No specific Folder` option and save the setting.
- [ ] Now `Project setings > File storages` option is visible
- [ ] Navigate to `Project setings > File storages` and add a file storage name `Nextcloud` for `Demo Project`.

### 4. Connect OpenProject with Nextcloud
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 3.
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, and login to `Nextcloud`.
- [ ] `OpenProject` admin is connected to `Nextcloud` as a `Nextcloud` admin.
- [ ] Also, create a user in both `Nextcloud` as well as `OpenProject`.
- [ ] Try to connect the created `OpenProject` user as created `Nextcloud` user.
- [ ] `OpenProject` user should be connected as a `Nextcloud` user.

### 5. Setup and check project folder in Nextcloud (with project folder setup)
- [ ] Complete Smoke Test No 1.
- [ ] Enable `groupfolders` application in `Nextcloud`.
- [ ] Enable `Automatically managed folders` switch in admin setting and set project folder.
- [ ] Application password should be generated.
- [ ] `OpenProject` user and group is created such that user `OpenProject` is admin of that group.
- [ ] Try deleting `OpenProject` user and group, those should not be deleted.

### 6. Link/Unlink a work package for a file/folder in Nextcloud
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 2.
- [ ] Complete Smoke Test No 3.
- [ ] Select a file, navigate to sidebar `OpenProject` tab.
- [ ] Search for any of the work packages in the `Demo Project`.
- [ ] Work packages are listed.
- [ ] Link to any one of the work packages appeared in the search lists.
- [ ] Linked work package is appeared in the `OpenProject` Tab with a toast message.
- [ ] Link other work packages, refresh the `Nextcloud` instance and all the linked ones should appear in the `OpenProject` Tab.
- [ ] Hover to a work package to be unlinked, unlink button is visible.
- [ ] Unlink a work package and should be deleted from the `OpenProject` Tab with a toast message.

### 7. Link/Unlink a work package for a file/folder from OpenProject
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 3.
- [ ] Complete Smoke Test No 4.
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, click `link existing files`, select available files (for e.g welcome.txt) from nextcloud and link it to the work package.
- [ ] Selected file is linked to the work package in `OpenProject`
- [ ] Also Navigate to nextcloud and see in the `OpenProject` tab for file (welcome.txt), the work package should be linked.

### 8. Direct upload file/folder from OpenProject to Nextcloud
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 3.
- [ ] Complete Smoke Test No 4.
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, click `Upload files`, select available files from your local system (for e.g local.txt) and upload choosing the upload location.
- [ ] Uploaded file is linked to the work package in `OpenProject`
- [ ] Also Navigate to `Nextcloud` and see in the `OpenProject` tab for file (local.txt), the work package should be linked.

### 9. Create a WorkPackage from the Nextcloud
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 2.
- [ ] Complete Smoke Test No 3.
- [ ] Open the form to create work package from the nextcloud in `OpenProject` tab for a file/folder.
- [ ] Select `Demo Project`, fill up the modal form and create.
- [ ] Work package should be created and linked to the selected file.

### 10. Check OpenProject Notification
> Make sure your `OpenProject` is running along with `worker` instance
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 2.
- [ ] Complete Smoke Test No 3.
- [ ] Complete Smoke Test No 4.
- [ ] Create a separate user in both `Nextcloud` as well as `OpenProject`.
- [ ] Connect `Nextcloud` user to `OpenProject` user and vice-versa (`OpenProject` user to `Nextcloud` user).
- [ ] Now as an `OpenProject` admin, assign any of the `Demo Project` work package to created `OpenProject` user.
- [ ] The `Nextcloud` user should receive a notification regarding the assignment.

### 11. Check New folder with automatically managed permissions in OpenProject
- [ ] Complete Smoke Test No 1.
- [ ] Complete Smoke Test No 3 (Make sure to choose `New folder with automatically managed permissions` while creating `File storage`).
- [ ] Complete Smoke Test No 4.
- [ ] Navigate to `Demo Project > Work Packages` and double click any one of the work packages available.
- [ ] Navigate to `Files` tab, click `link existing files`.
- [ ] In a modal, `Nextcloud > OpenProject > Demo project(1)` should be visible.
- [ ] Also Navigate to `Nextcloud` and in Files `OpenProject > Demo project(1)` folder is created.
- [ ] Try to delete `OpenProject` or `OpenProject > Demo project(1)`, shoudl not be deleted. 

### 12. Check the integration script

> Before Running the script make sure that your `Nextcloud` and `OpenProject` instance is up and running

- [ ] Run the `integration_setup.sh` script with to set up integration without project folder with following command:
```bash
SETUP_PROJECT_FOLDER=true \
NEXTCLOUD_HOST=<nextcloud_instance_url> \
OPENPROJECT_HOST=<openproject_instance_url> \
OP_ADMIN_USERNAME=admin \
OP_ADMIN_PASSWORD=admin \                                                                                                             
NC_ADMIN_USERNAME=admin \                                                                                     
NC_ADMIN_PASSWORD=admin \                                                             
OPENPROJECT_STORAGE_NAME=Nextcloud  \                         
bash integration_setup.sh
```
- [ ] Upon successful, try Smoke Test No 2 (Skip first check).
- [ ] Upon successful, try Smoke Test No 4 (Skip first check).
- [ ] Also, to set up the integration configuration with project folder setup, just set environment `SETUP_PROJECT_FOLDER=true` and run the script.
- [ ] Re-run the script again after it is already setup (Should not give any error).
