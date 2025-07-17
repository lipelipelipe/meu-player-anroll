# Estágio 1: Comece com a imagem oficial do Node.js que já inclui o Playwright e suas dependências.
# Esta é a imagem recomendada pela equipe do Playwright para evitar problemas.
FROM mcr.microsoft.com/playwright/javascript:v1.42.1-focal

# Define o diretório de trabalho dentro do contêiner.
WORKDIR /app

# Copia os arquivos de definição de dependências primeiro.
# Isso aproveita o cache do Docker. Se esses arquivos não mudarem, o Docker
# não irá reinstalar as dependências, acelerando builds futuras.
COPY package.json package-lock.json ./

# Instala as dependências definidas no package.json.
RUN npm install

# Copia o resto do código da sua aplicação para o diretório de trabalho.
COPY . .

# O Playwright já vem instalado nesta imagem, então não precisamos de 'npx playwright install'.
# Opcional: Se quiser ter certeza, você pode descomentar a linha abaixo, mas não é necessário.
# RUN npx playwright install --with-deps

# Expõe a porta que o nosso serviço Express irá escutar.
# A Render irá mapear uma porta externa para esta.
EXPOSE 3001

# O comando para iniciar a aplicação quando o contêiner for executado.
# Ele executa o script "start" definido no nosso package.json.
CMD ["npm", "start"]
