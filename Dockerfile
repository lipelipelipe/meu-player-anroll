# Usar a imagem oficial do PHP com o servidor Apache
FROM php:8.1-apache

# Instala as "ferramentas de construção" (dependências) para a extensão cURL.
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev

# Instala a extensão do PHP.
RUN docker-php-ext-install curl

# Copia os arquivos do nosso projeto para a pasta do servidor web no container.
COPY player.html /var/www/html/player.html
COPY proxy.php /var/www/html/proxy.php
COPY hls.min.js /var/www/html/hls.min.js  # <--- ESTA É A LINHA QUE FALTAVA
