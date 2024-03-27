# 🔗 OpenProject Integration

This application enables integration between Nextcloud and open-source project management software OpenProject. 

![](https://github.com/nextcloud/integration_openproject/raw/master/img/screenshot1.png)
![](https://github.com/nextcloud/integration_openproject/raw/master/img/screenshot2.png)

On the Nextcloud end, it allows users to:

* Link files and folders with work packages in OpenProject
* Find all work packages linked to a file or a folder
* Create work packages directly in Nextcloud
* View OpenProject notifications via the dashboard
* Search for work packages using Nextcloud's search bar
* Link work packages in rich text fields via Smart Picker
* Preview links to work packages in text fields
* Link multiple files and folders to a work package at once

On the OpenProject end, users are able to:

* View all Nextcloud files and folders linked to a work package
* Download linked files or open them in Nextcloud to edit them
* Open linked files in Nextcloud to edit them
* Let OpenProject create shared folders per project

Please report issues and bugs here: https://community.openproject.org/projects/nextcloud-integration/work_packages

## 📚 Documentation for users and administrators guide
- For documentation on how to set up the integration as an administrator, refer to [Nextcloud integration setup](https://openproject.org/docs/system-admin-guide/integrations/nextcloud/).
- For documentation on how to use the integration once it is set up, refer to [Using the Nextcloud integration](https://openproject.org/docs/user-guide/nextcloud-integration/).

## 🔨 Development Setup Guide
- [Set up through docker](docs/setUpViaDocker.md)
- [Setting up as admin with API](docs/settingUpAsAdmin.md)
- [Setting up with SS](docs/settingUpWithSS.md)
- [API for Direct Upload](docs/directUpload.md)
- [API for get file information](docs/getFileInformation.md)
- [Release Preparation](docs/release.md)
- [Running API tests](docs/runningAPItest.md)
