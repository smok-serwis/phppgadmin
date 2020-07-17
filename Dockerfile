FROM php:7.2-apache

RUN apt-get update && \
    apt-get install -y libpq-dev && \
    docker-php-ext-install pgsql pdo pdo_pgsql && \
    apt-get clean

ADD . /var/www/html/
