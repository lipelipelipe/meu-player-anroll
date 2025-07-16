# Usa uma imagem oficial do PHP com o servidor web Apache.
FROM php:8.1-apache

# Atualiza a lista de pacotes e instala as dependências necessárias para a extensão cURL.
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    --no-install-recommends && \
    rm -rf /var/lib/apt/lists/*

# Habilita a extensão cURL do PHP, que é essencial para o proxy.php funcionar.
RUN docker-php-ext-install curl

# Copia TUDO da pasta atual (incluindo a pasta 'js') para a pasta do servidor.
COPY . /var/www/html/
