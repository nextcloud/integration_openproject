#!/bin/bash
# SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
# SPDX-License-Identifier: AGPL-3.0-or-later

tmp_cert_dir="$HOME/tmp"

mkdir -p "$tmp_cert_dir"

tmp_cert="$tmp_cert_dir/root_ca.crt"

sudo rm -rf "$tmp_cert" /usr/local/share/ca-certificates/Step_Root_CA.crt /etc/ssl/certs/Step_Root_CA.pem

docker compose cp step:/home/step/certs/root_ca.crt "$tmp_cert"
sudo cp "$tmp_cert" /usr/local/share/ca-certificates/Step_Root_CA.crt
sudo update-ca-certificates

cert_db="$HOME/.pki/nssdb"

# create and initialise the NSS db if doesn't exist
if [ ! -d "$cert_db" ]; then
	mkdir -p "$cert_db"
	certutil -N -d sql:"$cert_db" --empty-password
fi

cert_name="NC-OP Integration Root C"
# delete existing cert if exists
if certutil -L -d sql:"$cert_db" -n "$cert_name" >/dev/null 2>&1; then
	certutil -D -n "$cert_name" -d sql:"$cert_db"
fi

# add root CA to cert db
certutil -A -n "$cert_name" -t TC -d sql:"$cert_db" -i "$tmp_cert"
# update/rebuild cert db
certutil -M -d sql:"$cert_db"
# list certs
certutil -L -d sql:"$cert_db"
