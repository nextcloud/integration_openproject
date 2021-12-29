# OpenProject integration into Nextcloud

OpenProject integration provides a dashboard widget displaying your important notifications,
a search provider for work packages and notifications for changes in active work packages.

## 🔧 Configuration

### User settings

The account configuration happens in the "Connected accounts" user settings section. It requires to create a personal access token (API key) in your OpenProject account settings.

A link to the "Connected accounts" user settings section will be displayed in the widget for users who didn't configure an OpenProject account.

### Admin settings

There also is a "Connected accounts" **admin** settings section if you want to allow your Nextcloud users to use OAuth to authenticate to a specific OpenProject instance.

1. As an OpenProject admin create an OAuth app 
   1. in OpenProject go to Administration -> Authentication -> OAuth applications
   2. use a name of your choice
   3. as `Redirect URI` use `<nextcloud-uri>/index.php/apps/integration_openproject/oauth-redirect`
   4. note down the Client ID and the Client Secret
2. As an NextCloud admin configure the OpenProject integration
   1. in NextCloud go to Settings -> Administration -> Connected accounts
   2. provide the OpenProject address, the Client ID and the Client Secret
3. As an NextCloud user connect to OpenProject
   1. in NextCloud go to Settings -> Personal -> Connected accounts
   2. provide the OpenProject address (it has to be exactly the same as provided by the administrator in step 2)
   3. a new button `Connect to OpenProject` should be visible
   4. click `Connect to OpenProject`
   5. you will be redirected to OpenProject
   6. log-in to OpenProject if you haven't already
   7. Authorize the NextCloud App
   8. you will be redirected back to OpenProject

#### Background jobs

To be able to periodically check activity in OpenProject (when "notifications for activity in my work packages" is enabled), you need to choose the "Cron" background job method and set a system cron task calling cron.php as explained in the [documentation](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/background_jobs_configuration.html#cron).
