# Usar a imagem oficial do PHP com o servidor Apache
FROM php:8.1-apache

# Instalar a extensão cURL, que é essencial para o proxy.php
RUN docker-php-ext-install curl

# Copiar os arquivos do nosso projeto para a pasta do servidor web no container
COPY player.html /var/www/html/player.html
COPY proxy.php /var/www/html/proxy.php