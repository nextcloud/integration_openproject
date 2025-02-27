#!/bin/bash
set -e

nextcloudVersions=$1
phpVersions=$2
# can be removed once the phpunit and api test jobs are merged
extraDb=$3

if [ -z "$nextcloudVersions" ]; then
    echo "Missing nextcloud versions argument. Must be a first argument"
    exit 1
fi
if [ -z "$phpVersions" ]; then
    echo "Missing php versions argument. Must be a second argument"
    exit 1
fi

# latest stable version
latestSupportedNCVersion=""
for ncVersion in $nextcloudVersions; do
    if [ "$ncVersion" == "master" ]; then
        continue
    fi
    if [ -z "$latestSupportedNCVersion" ]; then
        latestSupportedNCVersion=$ncVersion
        continue
    fi
    if [ "$ncVersion" -gt "$latestSupportedNCVersion" ]; then
        latestSupportedNCVersion=$ncVersion
    fi
done

function getphpMajorVersionVersion() {
    echo "$1" | cut -d'.' -f1
}

function getphpMinorVersionVersion() {
    echo "$1" | cut -d'.' -f2
}

MATRIX=""
function addMatrix() {
    local nextcloudVersion=$1
    local phpVersion=$2
    local phpVersionMajor=$3
    local phpVersionMinor=$4
    local database=$5
    MATRIX="$MATRIX{\"nextcloudVersion\": \"$nextcloudVersion\", \"phpVersion\": \"$phpVersion\", \"phpVersionMajor\": \"$phpVersionMajor\", \"phpVersionMinor\": \"$phpVersionMinor\", \"database\": \"$database\"},"
}

# Defaults
defaultPhpVersion="8.2"
defaultPhpMajorVersion=$(getphpMajorVersionVersion "$defaultPhpVersion")
defaultPhpMinorVersion=$(getphpMinorVersionVersion "$defaultPhpVersion")
defaultDb="mysql"

# [NOTE]
# This generates single job for the older nextcloud version
#   with corresponding php version and mysql database.
# And generates all combination for the latest nextcloud version
#   with all php versions and mysql database
# Example:
#    nextcloudVersions="30 31 master" and phpVersions="8.1 8.2"
#  Generates:
#    - 30 8.2 mysql
#    - 31 8.1 mysql
#    - 31 8.2 mysql
#    - master 8.3 mysql
for ncVersion in $nextcloudVersions; do
    phpVersion=""
    phpVersionMajor=""
    phpVersionMinor=""

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
    elif [ "$ncVersion" != "$latestSupportedNCVersion" ]; then
        phpVersion="$defaultPhpVersion"
    fi

    # add stable prefix
    if [ "$ncVersion" != "master" ]; then
        ncVersion="stable$ncVersion"
    fi
    if [ -n "$phpVersion" ]; then
        phpVersionMajor=$(getphpMajorVersionVersion "$phpVersion")
        phpVersionMinor=$(getphpMinorVersionVersion "$phpVersion")
        addMatrix "$ncVersion" "$phpVersion" "$phpVersionMajor" "$phpVersionMinor" "$defaultDb"
        continue
    fi

    # [INFO] Run all combination for the latest NC version
    for phpVersion in $phpVersions; do
        addMatrix "$ncVersion" "$phpVersion" "$phpVersionMajor" "$phpVersionMinor" "$defaultDb"
    done

done

# add extra db matrix
if [ -n "$extraDb" ]; then
    addMatrix "stable$latestSupportedNCVersion" "$defaultPhpVersion" "$defaultPhpMajorVersion" "$defaultPhpMinorVersion" "$extraDb"
fi

# remove last comma
MATRIX=${MATRIX%?}
echo "$MATRIX"
