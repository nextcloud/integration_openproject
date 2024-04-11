## Setting up the integration as an administrator with API

> Note: Setting up the integration can only be done by admin user but not a normal user

We have a single API endpoint (/setup) available to set up, update and reset the integration.

To set up or update the integration following data needs to be provided:
- openproject_instance_url
- openproject_client_id
- openproject_client_id
- default_enable_navigation
- default_enable_unified_search
- setup_project_folder
- setup_app_password

> Note:
> - We can set up the integration with or without `project folders`
> - To set up the integration without `project folders` we need to set data `setup_project_folder=false` and `setup_app_password=false`
> - To set up the integration with `project folders` we need to set data `setup_project_folder=true` and `setup_app_password=true`, this will create a new user, group, and group folder named OpenProject if the system doesn't already have one or more of these present. Also, an application password will be provided for the user `OpenProject`
> - Once the `project folder` has already been set up, the created `OpenProject` user and group cannot be disabled or removed.
> - If there is any error related to `OpenProject` user, group, group folders when setting up the whole integration or if the admin user wants to remove those entities then this [troubleshooting guide](https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#troubleshooting) can be followed up on how to resolve it. 


1. **Set up the whole integration with a [POST] request**

   We must provide all of the above data for this request.

   Example curl request to set up whole integration with `project folders`
   ```bash
   curl -XPOST -u<nextcloud_admin_username>:<nextcloud_admin_password>  http://<nextcloud_host>/index.php/apps/integration_openproject/setup \
   -d '{"values":{"openproject_instance_url":"<openproject_instance_url>","openproject_client_id":"<openproject_client_id>","openproject_client_secret":"<openproject_client_secret>","default_enable_navigation":false,"default_enable_unified_search":false,"setup_project_folder":true, "setup_app_password":true}}' \
   -H 'Content-Type: application/json' -v
   ```

   The response from the above curl request
   ```json
   {
       "nextcloud_oauth_client_name": "<openproject-client>",
       "openproject_redirect_uri": "http://<openproject_instance_url>/oauth_clients/<nextcloud_client_id>/callback",
       "nextcloud_client_id": "<nextcloud_client_id>",
       "nextcloud_client_secret": "<nextcloud_client_secret>",
       "openproject_user_app_password": "<openproject_user_app_password>",
       "openproject_revocation_status": "<openproject_revocation_status>"
   }
   ```

2. **Update the integration with a [PATCH] request**

    One or more of the above data needs to be sent to this endpoint

   Example curl request to update only `openproject_client_id`
   and `openproject_client_secret`
   ```bash
   curl -XPATCH -u<nextcloud_admin_username>:<nextcloud_admin_password>  http://<nextcloud_host>/index.php/apps/integration_openproject/setup \
   -d '{"values":{"openproject_client_id":"<openproject_client_id>","openproject_client_secret":"<openproject_client_secret>"}}' \
   -H 'Content-Type: application/json' -v
   ```
   
   The response from the above curl request
   ```json
   {
       "nextcloud_oauth_client_name": "<openproject-client>",
       "openproject_redirect_uri": "http://<openproject_instance_url>/oauth_clients/<nextcloud_client_id>/callback",
       "nextcloud_client_id": "<nextcloud_client_id>",
       "nextcloud_client_secret": "<nextcloud_client_secret>",
       "openproject_revocation_status": "<openproject_revocation_status>"
   }
   ```
   > Note: If the integration is done with `project folders` then we can update/change the old `OpenProject` user's application password by setting `set_app_password=true`.
   > Then we get new application password as `"openproject_user_app_password: <openproject_user_app_password>"` in the above JSON response.

3. **Resetting the whole integration with a [DELETE] request**

   Example curl request to reset whole integration
   ```bash
   curl -XDELETE -u<nextcloud_admin_username>:<nextcloud_admin_password> http://<nextcloud_host>/index.php/apps/integration_openproject/setup -v
   ```

   The response from the above curl request
   ```json
   {
       "status": true,
       "openproject_revocation_status": "<openproject_revocation_status>"
   }
   ```
> Note: In the response `openproject_revocation_status` is included only after a successful connection
