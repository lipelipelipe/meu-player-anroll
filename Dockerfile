FROM php:8.1-apache

# Instala dependências necessárias para o cURL funcionar
RUN apt-get update && apt-get install -y libcurl4-openssl-dev && \
    docker-php-ext-install curl

# Copia os arquivos do player para o diretório público do Apache
COPY index.html /var/www/html/
COPY proxy.php /var/www/html/
COPY js/hls.min.js /var/www/html/js/
