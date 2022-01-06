# OpenProject integration into Nextcloud

OpenProject integration provides a dashboard widget displaying your important notifications,
a search provider for work packages and notifications for changes in active work packages.

## 🔧 Configuration

### :lock: Authentication

The authentication to OpenProject can happen ether through a personal access token or the OAuth workflow.
Using a personal access token requires more manual steps for every user but no settings need to be set by the NextCloud or OpenProject admin, that enables every NextCloud user to connect to an OpenProject instance of their choice.

Using the OAuth authentication is much easier for every user, but requires the NextCloud admin and the OpenProject admin to configure both apps.

The account configuration happens in the "Connected accounts" user settings section. A link to the "Connected accounts" user settings section will be displayed in the widget for users who didn't configure an OpenProject account.

#### personal access token

1. As an OpenProject user get an access token (API key)
   1. in OpenProject click on your user image in the top right corner
   2. go to "My account" -> "Access token"
   3. click on "Generate" button in the "API" row. If there is no "Generate" button you have already created an API token for this user and in case you don't know it any-more you can always create a new one by clicking "Reset", but this will invalidate any old token.
   4. note down the API token that is displayed
2. As an NextCloud user connect to OpenProject
   1. in NextCloud go to "Settings" -> "Personal" -> "Connected accounts"
   2. provide the OpenProject address
   3. enter or copy the OpenProject API token into the "Access token" field
   4. after a short time the app will try to establish the connection to OpenProject and if all worked correctly it will display the status: "Connected as <fullname of user in OpenProject>"

#### OAuth

1. As an OpenProject admin create an OAuth app 
   1. in OpenProject go to "Administration" -> "Authentication" -> "OAuth applications"
   2. use a name of your choice
   3. as `Redirect URI` use `<nextcloud-uri>/index.php/apps/integration_openproject/oauth-redirect`
   4. note down the Client ID and the Client Secret
2. As an NextCloud admin configure the OpenProject integration
   1. in NextCloud go to "Settings" -> "Personal" -> "Connected accounts"
   2. provide the OpenProject address, the Client ID and the Client Secret
3. As an NextCloud user connect to OpenProject
   1. in NextCloud go to "Settings" -> "Personal" -> "Connected accounts"
   2. provide the OpenProject address (it has to be exactly the same as provided by the administrator in step 2)
   3. a new button `Connect to OpenProject` should be visible
   4. click `Connect to OpenProject`
   5. you will be redirected to OpenProject
   6. log-in to OpenProject if you haven't already
   7. Authorize the NextCloud App
   8. you will be redirected back to OpenProject

#### Background jobs

To be able to periodically check activity in OpenProject (when "notifications for activity in my work packages" is enabled), you need to choose the "Cron" background job method and set a system cron task calling cron.php as explained in the [documentation](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/background_jobs_configuration.html#cron).
