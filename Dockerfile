# Usar a imagem oficial do PHP com o servidor Apache
FROM php:8.1-apache

# -----> INÍCIO DA CORREÇÃO <-----
# 1. Atualiza a lista de pacotes do sistema operacional do container.
# 2. Instala as "ferramentas de construção" (dependências) para a extensão cURL.
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev
# -----> FIM DA CORREÇÃO <-----

# Agora, este comando vai funcionar porque as ferramentas que ele precisa foram instaladas.
RUN docker-php-ext-install curl

# Copiar os arquivos do nosso projeto para a pasta do servidor web no container
COPY player.html /var/www/html/player.html
COPY proxy.php /var/www/html/proxy.php
