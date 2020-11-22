#!/bin/bash

# Load configuration settings.
. ./config.sh

# Download SimpleSAMLphp and strip version tag for folder.
wget -c https://simplesamlphp.org/download?latest -O - | tar -xz

# Strip the version number from the folder.
ssdist=$(ls -d simplesamlphp-*)
echo $ssdist
mv $ssdist simplesamlphp

# Install our custom modules.
cp /kayako.php /simplesamlphp/www/
cp /parse_metadata.php /simplesamlphp/www/

# Configure SimpleSAMLphp. See config.sh
sed -i "s#'baseurlpath' => 'simplesaml/'#'baseurlpath' => '/'#" /simplesamlphp/config/config.php
sed -i "s#'auth.adminpassword' => '123'#'auth.adminpassword' => '$SS_AdminPassword'#" /simplesamlphp/config/config.php
sed -i "s#'secretsalt' => 'defaultsecretsalt'#'secretsalt' => '$SS_SecretSalt'#" /simplesamlphp/config/config.php

# Generate self-signed SSL certificate.
# default.conf looks in these file locations. It also points to /simplesamlphp/www
#  to serve the app.
mkdir /etc/nginx/ssl
openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
  -keyout /etc/nginx/ssl/server.key -out /etc/nginx/ssl/server.crt -subj "/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"

mv -f /nginx_files/default.conf /etc/nginx/conf.d/

# Convert metadata. This is kind of rocky and not too robust, especially if you want to try
#  this with a different kind of service provider. This will likely only work with Azure AD
#  SAML 2.0 with IDP-initiated login.
# Feed metadata to our custom script and pipe that out to the config file.
cat /metadata.xml | php /simplesamlphp/www/parse_metadata.php >> /simplesamlphp/metadata/saml20-idp-remote.php

# Extract the IDP URL from the metadata. Entity ID needs to be set manually in the config.
idp=$(sed -E 's/.*entityID="([^"]*)".*/\1/' /metadata.xml)
mv -f /authsources-template.php /simplesamlphp/config/authsources.php
sed -i "s#{idp}#$idp#" /simplesamlphp/config/authsources.php
sed -i "s#{entityid}#$SSO_EntityID#" /simplesamlphp/config/authsources.php
