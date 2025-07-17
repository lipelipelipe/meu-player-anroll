# Usa uma imagem oficial do PHP que já vem com o servidor web Apache.
FROM php:apache

# O Apache no container serve arquivos a partir de /var/www/html/
WORKDIR /var/www/html/

# Instala as extensões PHP necessárias.
RUN apt-get update && apt-get install -y \
  libcurl4-openssl-dev \
  && rm -rf /var/lib/apt/lists/* \
  && docker-php-ext-install curl sockets

# Copia todos os arquivos do seu projeto para dentro do container.
COPY . /var/www/html/

# --- LINHAS ADICIONADAS ---
# Define permissões de forma explícita e robusta.
# 755 para diretórios (ler, escrever, executar para o dono; ler, executar para outros)
# 644 para arquivos (ler, escrever para o dono; ler para outros)
RUN find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

# Garante que o servidor Apache seja o dono dos arquivos.
RUN chown -R www-data:www-data /var/www/html

# A imagem base já sabe como iniciar o Apache.
