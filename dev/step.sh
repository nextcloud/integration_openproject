#!/bin/bash

touch /certs/acme.json
chmod 600 /certs/acme.json

bash /entrypoint.sh

HOME=/home/step

# update the certificate duration to 1 year
step ca provisioner update acme --x509-min-dur=24h --x509-max-dur=8760h --x509-default-dur=8760h

cp "$HOME/certs/root_ca.crt" "$HOME/certs/Step_Root_CA.crt"
ln -s "$HOME/certs/Step_Root_CA.crt" /etc/ssl/certs/Step_Root_CA.pem
update-ca-certificates

step-ca --password-file $PWDPATH $CONFIGPATH
