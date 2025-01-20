# Nextcloud-OpenProject Full Setup

## Table of Contents

1. [Pre-requisites](#pre-requisites)
2. [Run the Setup](#run-the-setup)
3. [Installing Extra Apps](#installing-extra-apps)
4. [Keycloak Realm Configuration](#keycloak-realm-configuration)

### Pre-requisites

- Docker
- Docker Compose
- [certutil](https://command-not-found.com/certutil)

### Run the Setup

1. Add the following line to the `/etc/hosts` file:

   ```bash
   127.0.0.1	nextcloud.local openproject.local keycloak.local traefik.local
   ```

2. Change the directory to the `dev` folder

   ```bash
   cd dev
   ```

3. _(Optional)_ To enable keycloak service, uncomment the following line in the `dev/.env` file:

   ```yaml
   # run keycloak
   KEYCLOAK=:keycloak.yaml
   ```

4. Start the services

   ```bash
   docker compose up
   ```

5. Once the services are up, add the certificates to the system and browser trust store

   ```bash
   bash ./ssl.sh
   ```

   **NOTE:** Restart the browser after adding the certificates to the trust store.

6. Access the services:

   Nextcloud: [nextcloud.local](https://nextcloud.local)

   OpenProject: [openproject.local](https://openproject.local)

   Keycloak: [keycloak.local](https://keycloak.local)

   Traefik: [traefik.local](https://traefik.local)

### Installing Extra Apps

By default, `integration_openproject` app is enabled in Nextcloud. To install other apps, you can clone and build the desired apps in the `apps` directory.

1. Clone and build the app in the `apps` directory

   ```bash
   cd apps
   git clone git@github.com:nextcloud/<app>.git

   cd <app>
   # build the app
   ```

2. Enable the app in Nextcloud

   ```bash
   # integration_openproject/dev
   cd ../../

   docker compose exec -u www-data nextcloud php occ a:e <app>
   ```

If permission issues occur, you can run the following command:

```bash
# <integration_openproject>/dev

docker compose exec nextcloud chown www-data -R custom_apps/<app>
```

### Keycloak Realm Configuration

When the setup is run with Keycloak, new realm will be initialized with necessary clients, permissions, and some demo users. The following are the details:

|         |                                                                    |
| ------- | ------------------------------------------------------------------ |
| Realm   | `opnc`                                                             |
| Clients | `nextcloud` and `openproject` (token-exchange has been configured) |

And the following are the demo users:

| Displayname      | Username | Password |
| ---------------- | -------- | -------- |
| **Alice Hansen** | `alice`  | `1234`   |
| **Brian Murphy** | `brian`  | `1234`   |
