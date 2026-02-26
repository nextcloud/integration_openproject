php occ maintenance:mode --on
php occ app:update --allow-unstable integration_openproject
php occ db:add-missing-columns
php occ db:add-missing-indices
php occ db:add-missing-primary-keys
php occ maintenance:mode --off