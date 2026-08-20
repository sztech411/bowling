#!/bin/sh
set -e

# Render injects the service-account JSON as an env var (secret), never as a
# committed file. Write it out at container start so config/firebase.php
# finds it in its normal spot.
if [ -n "$FIREBASE_SERVICE_ACCOUNT_JSON" ]; then
    echo "$FIREBASE_SERVICE_ACCOUNT_JSON" > /var/www/html/config/firebase-service-account.json
fi

mkdir -p /var/www/html/data
touch /var/www/html/data/.firestore-token-cache.json
chown -R www-data:www-data /var/www/html/data /var/www/html/config

exec apache2-foreground
