# Estágio 1: Comece com a imagem oficial do Playwright.
# Caminho e tag corretos para garantir que a imagem seja encontrada.
FROM mcr.microsoft.com/playwright:v1.45.1-jammy

# =========================================================================
# CHECKPOINT 1: AMBIENTE E DIRETÓRIO DE TRABALHO
# =========================================================================
# Imprime uma mensagem no log para sabermos que a build começou.
RUN echo "==> [DIAGNÓSTICO] Etapa 1/4: Imagem base carregada. Configurando o diretório de trabalho..."
WORKDIR /app

# =========================================================================
# CHECKPOINT 2: COPIANDO ARQUIVOS DE DEPENDÊNCIA
# =========================================================================
RUN echo "==> [DIAGNÓSTICO] Etapa 2/4: Copiando package.json e package-lock.json..."
COPY package.json package-lock.json* ./

# =========================================================================
# CHECKPOINT 3: INSTALAÇÃO DAS DEPENDÊNCIAS (ETAPA CRÍTICA DE MEMÓRIA)
# =========================================================================
# Imprime o aviso mais importante. Se a build falhar depois desta linha,
# a causa mais provável é falta de memória (RAM) no plano do Render.
RUN echo "==> [DIAGNÓSTICO] Etapa 3/4: INICIANDO ETAPA CRÍTICA - npm install. Se a build falhar aqui, a causa mais provável é FALTA DE MEMÓRIA."

# Instala as dependências. Usamos flags para tentar otimizar um pouco o uso de recursos.
RUN npm ci --no-optional --no-fund || npm install --no-optional --no-fund

# =========================================================================
# CHECKPOINT 4: COPIANDO O CÓDIGO DA APLICAÇÃO
# =========================================================================
RUN echo "==> [DIAGNÓSTICO] Etapa 4/4: Dependências instaladas com sucesso. Copiando o restante da aplicação..."
COPY . .

# Expõe a porta que o nosso serviço Express irá escutar.
EXPOSE 3001

# Comando final para iniciar a aplicação.
CMD ["npm", "start"]
