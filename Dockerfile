# Usa uma imagem oficial do PHP que já vem com o servidor web Apache.
# Esta é a maneira mais fácil e robusta de servir um site PHP.
# A tag 'latest' usará a versão mais recente do PHP com Apache.
FROM php:apache

# O Apache no container serve arquivos a partir de /var/www/html/
# Definimos este como nosso diretório de trabalho.
WORKDIR /var/www/html/

# Instala as extensões PHP necessárias para nosso script proxy.php.
# - 'curl' é essencial para fazer as requisições HTTP para a URL remota.
# - 'sockets' é uma boa prática para operações de rede.
# O comando limpa o cache do apt no final para manter a imagem pequena.
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install curl sockets

# Copia todos os arquivos do seu projeto (local) para dentro do container,
# no diretório do servidor web (/var/www/html/).
COPY . /var/www/html/

# Garante que o servidor Apache tenha permissão para ler os arquivos.
# O Apache roda com o usuário 'www-data'.
RUN chown -R www-data:www-data /var/www/html

# A imagem base 'php:apache' já tem um comando para iniciar o Apache.
# Não precisamos definir um CMD ou ENTRYPOINT, ele funcionará automaticamente.
# A porta 80 do container será exposta por padrão. O Render gerenciará a porta externa.