FROM php:7.4-apache

RUN a2enmod headers mime rewrite

COPY . /var/www/html/

# Secrets are never baked into the image — see render.yaml / DEPLOY.md.
RUN rm -f /var/www/html/config/firebase-service-account.json \
    /var/www/html/data/.firestore-token-cache.json \
    /var/www/html/cjfirejson.txt

RUN chown -R www-data:www-data /var/www/html/data \
    && chmod -R 775 /var/www/html/data

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
