# RUN docker-php-ext-install pdo_sqlite \

FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite

# Serve from /public, not the repo root, so nothing outside the front
# controller (src/, database/, tests/, .env) is web-accessible.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . .

# storage/ needs to be writable for the SQLite file + logs.
RUN mkdir -p storage/logs \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

EXPOSE 80

# Migrate + seed on every container start, then hand off to Apache.
CMD php database/migrate.php && php database/seed.php && apache2-foreground
