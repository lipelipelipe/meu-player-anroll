FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev

RUN docker-php-ext-install curl

COPY player.html /var/www/html/player.html
COPY proxy.php /var/www/html/proxy.php
COPY hls.min.js /var/www/html/hls.min.js
