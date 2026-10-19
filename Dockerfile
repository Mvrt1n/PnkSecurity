# PNK Security (versión corregida) — imagen PHP+Apache
FROM php:8.3-apache
RUN docker-php-ext-install pdo pdo_mysql mysqli && a2enmod rewrite headers
COPY pnkSecurity/ /var/www/html/
COPY config/ /var/www/config/
COPY herramientas/ /var/www/herramientas/
COPY tests/ /var/www/tests/
RUN mkdir -p /var/www/logs && chown -R www-data:www-data /var/www/logs
ENV APP_ENV=prod
EXPOSE 80
