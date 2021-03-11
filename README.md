# OpenProject integration into Nextcloud

OpenProject integration provides a dashboard widget displaying your important notifications,
a search provider for work packages and notifications for changes in active work packages.

## 🔧 Configuration

### User settings

The account configuration happens in the "Connected accounts" user settings section. It requires to create a personal access token (API key) in your OpenProject account settings.

A link to the "Connected accounts" user settings section will be displayed in the widget for users who didn't configure a OpenProject account.

### Admin settings

There also is a "Connected accounts" **admin** settings section if you want to allow your Nextcloud users to use OAuth to authenticate to a specific OpenProject instance. An admin can create an OAuth app (and get a client ID and a client secret) on OpenProject side in Administration -> Authentication -> OAuth applications.