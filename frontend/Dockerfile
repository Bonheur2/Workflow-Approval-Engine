# Frontend: the server-rendered PHP UI that talks to the backend API.
# Only extension needed is curl (for ApiClient.php's HTTP calls to the
# backend) - no Composer, no JS build step.

FROM php:8.2-apache

RUN docker-php-ext-install curl

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . .

EXPOSE 80
CMD ["apache2-foreground"]
