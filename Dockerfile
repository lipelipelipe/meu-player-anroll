# Estágio 1: Comece com a imagem oficial do Node.js que já inclui o Playwright e suas dependências.
# Esta é a imagem recomendada pela equipe do Playwright para evitar problemas.
# A versão foi atualizada para v1.45.1-jammy, que é uma tag válida e recente.
FROM mcr.microsoft.com/playwright/javascript:v1.45.1-jammy

# Define o diretório de trabalho dentro do contêiner. Todas as ações a seguir
# acontecerão dentro desta pasta.
WORKDIR /app

# Copia os arquivos de definição de dependências primeiro.
# Isso é uma otimização crucial para o cache do Docker. Se esses arquivos não mudarem
# entre as builds, o Docker reutilizará a camada onde as dependências foram instaladas,
# economizando muito tempo.
COPY package.json package-lock.json* ./

# Instala as dependências do projeto definidas no package.json.
# O argumento '--ci' (npm ci) é recomendado para ambientes de CI/CD como o Render,
# pois ele usa o package-lock.json para garantir uma instalação limpa e reprodutível.
# Se o 'npm ci' falhar, ele automaticamente volta para 'npm install'.
RUN npm ci || npm install

# Copia o resto do código da sua aplicação para o diretório de trabalho.
# Isso é feito por último porque o código da aplicação muda com mais frequência
# do que as dependências.
COPY . .

# Expõe a porta que o nosso serviço Express irá escutar dentro do contêiner.
# A Render irá mapear automaticamente uma porta externa para esta porta interna.
EXPOSE 3001

# O comando para iniciar a aplicação quando o contêiner for executado.
# Ele executa o script "start" definido no nosso package.json ('node anime-service-optimized.js').
CMD ["npm", "start"]
