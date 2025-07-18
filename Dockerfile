# Estágio 1: Comece com a imagem oficial e leve do Node.js.
# Usamos a versão 20 (LTS - Long Term Support), que é estável e recomendada.
FROM node:20-slim

# Define o diretório de trabalho dentro do contêiner.
WORKDIR /app

# Copia os arquivos de definição de dependências.
COPY package.json package-lock.json* ./

# Instala SOMENTE as dependências de produção. É mais rápido e seguro.
RUN npm install --omit=dev

# Copia o resto do código da sua aplicação para o diretório de trabalho.
COPY . .

# Expõe a porta que o nosso serviço Express irá escutar.
EXPOSE 3001

# O comando para iniciar a aplicação quando o contêiner for executado.
CMD ["npm", "start"]
