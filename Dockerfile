FROM php:8.1-apache

# Instala o cURL, necessário para o proxy funcionar
RUN apt-get update && apt-get install -y libcurl4-openssl-dev && \
    docker-php-ext-install curl

# Garante a criação da pasta js
RUN mkdir -p /var/www/html/js

# Copia os arquivos para o servidor Apache no Render
# Alterado de index.html para player.html
COPY player.html /var/www/html/player.html
COPY proxy.php /var/www/html/proxy.php
COPY js/hls.min.js /var/www/html/js/

# (Opcional) Se você quiser que https://meu-player-anroll.onrender.com/ (sem /player.html)
# também funcione, você pode adicionar uma regra de reescrita do Apache ou
# simplesmente copiar player.html para index.html também.
# Exemplo para que a raiz sirva player.html:
# RUN cp /var/www/html/player.html /var/www/html/index.html
# Ou, melhor, configurar o Apache DirectoryIndex, mas cp é mais simples no Dockerfile.
# Se você sempre vai usar /player.html no src do iframe, a linha acima não é necessária.
