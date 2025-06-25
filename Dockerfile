FROM php:8.1-apache

# Instala o cURL, necessário para o proxy funcionar
RUN apt-get update && apt-get install -y libcurl4-openssl-dev && \
    docker-php-ext-install curl

# Garante a criação da pasta js
RUN mkdir -p /var/www/html/js

# Copia os arquivos para o servidor Apache no Render
COPY index.html /var/www/html/
COPY proxy.php /var/www/html/
COPY js/hls.min.js /var/www/html/js/
