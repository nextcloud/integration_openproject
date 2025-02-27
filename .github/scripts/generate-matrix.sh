#!/bin/bash
set -e

nextcloud_versions=$1
php_versions=$2

if [ -z "$nextcloud_versions" ]; then
    echo "Missing: nextcloud_versions argument. Must be a first argument"
    exit 1
fi
if [ -z "$php_versions" ]; then
    echo "Missing: php_versions argument. Must be a second argument"
    exit 1
fi

latest_supported_version=""
for ncVersion in $nextcloud_versions; do
    if [ "$ncVersion" == "master" ]; then
        continue
    fi
    if [ -z "$latest_supported_version" ]; then
        latest_supported_version=$ncVersion
        continue
    fi
    if [ "$ncVersion" -gt "$latest_supported_version" ]; then
        latest_supported_version=$ncVersion
    fi
done

MATRIX=""
for ncVersion in $nextcloud_versions; do
    phpVersion=""

    # [INFO] Run only one job for older versions and master branch
    if [ "$ncVersion" == "27" ]; then
        phpVersion="8.0"
    elif [ "$ncVersion" == "28" ]; then
        phpVersion="8.1"
    elif [ "$ncVersion" == "29" ]; then
        phpVersion="8.1"
    elif [ "$ncVersion" == "30" ]; then
        phpVersion="8.2"
    elif [ "$ncVersion" == "master" ]; then
        phpVersion="8.3"
    elif [ "$ncVersion" != "$latest_supported_version" ]; then
        # Defaul: run with PHP 8.2
        phpVersion="8.2"
    fi
    if [ -n "$phpVersion" ]; then
        MATRIX="$MATRIX{\"nextcloudVersion\": \"$ncVersion\", \"phpVersion\": \"$phpVersion\"},"
        continue
    fi

    # [INFO] Run all combination for the latest NC version
    for phpVersion in $php_versions; do
        MATRIX="$MATRIX{\"nextcloudVersion\": \"$ncVersion\", \"phpVersion\": \"$phpVersion\"},"
    done
done

# remove last comma
MATRIX=${MATRIX%?}
echo "$MATRIX"
