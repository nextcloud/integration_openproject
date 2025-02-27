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

defaultPhpVersion="8.2"
defaultphpMajorVersion=$(getphpMajorVersionVersion "$defaultPhpVersion")
defaultphpMinorVersion=$(getphpMinorVersionVersion "$defaultPhpVersion")
defaultDb="mysql"

MATRIX=""
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
    if [ -n "$phpVersion" ]; then
        phpVersionMajor=$(getphpMajorVersionVersion "$phpVersion")
        phpVersionMinor=$(getphpMinorVersionVersion "$phpVersion")
        MATRIX="$MATRIX{\"nextcloudVersion\": \"$ncVersion\", \"phpVersion\": \"$phpVersion\", \"phpVersionMajor\": \"$phpVersionMajor\", \"phpVersionMinor\": \"$phpVersionMinor\", \"database\": \"$defaultDb\"},"
        continue
    fi

    # [INFO] Run all combination for the latest NC version
    for phpVersion in $phpVersions; do
        MATRIX="$MATRIX{\"nextcloudVersion\": \"$ncVersion\", \"phpVersion\": \"$phpVersion\", \"phpVersionMajor\": \"$phpVersionMajor\", \"phpVersionMinor\": \"$phpVersionMinor\", \"database\": \"$defaultDb\"},"
    done

done

# add extra db matrix
if [ -n "$extraDb" ]; then
    MATRIX="$MATRIX{\"nextcloudVersion\": \"$latestSupportedNCVersion\", \"phpVersion\": \"$defaultPhpVersion\", \"phpVersionMajor\": \"$defaultphpMajorVersion\", \"phpVersionMinor\": \"$defaultphpMinorVersion\", \"database\": \"$extraDb\"},"
fi

# remove last comma
MATRIX=${MATRIX%?}
echo "$MATRIX"
