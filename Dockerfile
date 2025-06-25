FROM php:8.1-apache

# Instala as dependências do sistema necessárias para a extensão cURL do PHP
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev

# Instala e habilita a extensão cURL do PHP, que é essencial para o proxy funcionar
RUN docker-php-ext-install curl

# [AQUI ESTÁ A CORREÇÃO]
# Copia o arquivo 'index.html' (antigo player.html) para o servidor
COPY index.html /var/www/html/index.html

# Copia os outros arquivos do projeto
COPY proxy.php /var/www/html/proxy.php
COPY hls.min.js /var/www/html/hls.min.js
