#!/bin/bash

STEP_CERTS_DIR="/step/certs"

if [ -d "$STEP_CERTS_DIR" ]; then
    rm -rf /etc/ssl/certs/Step_Root_CA.pem /usr/local/share/ca-certificates/Step_Root_CA.crt
    echo "[INFO] Linking root CA certificate..."
    cp "$STEP_CERTS_DIR"/root_ca.crt /usr/local/share/ca-certificates/Step_Root_CA.crt
    update-ca-certificates
fi

chown www-data custom_apps
find ./custom_apps -mindepth 1 -path ./custom_apps/integration_openproject -prune -o -exec chown www-data {} \;

/entrypoint.sh apache2-foreground &

# Wait for Nextcloud
while [ $(curl -s http://localhost -w %{http_code} -o /dev/null) != 302 ] && [ $(curl -s http://localhost -w %{http_code} -o /dev/null) != 200 ]; do
    sleep 1
done

chsh -s /bin/bash www-data
# install nextcloud
su www-data -c "php occ maintenance:install -vvv \
    --database pgsql \
    --database-name $POSTGRES_DB \
    --database-host $POSTGRES_HOST \
    --database-user $POSTGRES_USER \
    --database-pass $POSTGRES_PASSWORD \
    --admin-user admin \
    --admin-pass admin \
    --data-dir /var/www/html/data"

su www-data -c "php occ a:e integration_openproject"
su www-data -c "php occ config:system:set allow_local_remote_servers --value 1"
su www-data -c "php occ security:certificates:import /etc/ssl/certs/ca-certificates.crt"
su www-data -c "php occ config:system:set trusted_domains 1 --value=nextcloud.local"

tail -f data/nextcloud.log
