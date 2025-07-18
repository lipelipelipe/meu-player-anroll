// anime-service.js - v22.0 (Edição Definitiva Ultraleve - Validada)
// Autor: Felipe & IA Assistente
// Foco: Performance, simplicidade e compatibilidade máximas. Sem Playwright.

const express = require('express');
const cors = require('cors');
const axios = require('axios');
const cheerio = require('cheerio');

const app = express();
const port = process.env.PORT || 3001;

// Configuração explícita do CORS para aceitar requisições de qualquer origem (incluindo localhost).
const corsOptions = {
  origin: '*',
  methods: 'GET,HEAD,PUT,PATCH,POST,DELETE',
  preflightContinue: false,
  optionsSuccessStatus: 204
};
app.use(cors(corsOptions));
app.use(express.json({ limit: '10mb' }));


// ROTA DE VERIFICAÇÃO DE SAÚDE (HEALTH CHECK)
app.get('/', (req, res) => {
    res.status(200).json({
        status: 'ok',
        message: 'Serviço de extração ULTRALEVE está operacional e aguardando ordens na rota /extract.'
    });
});

// --- ROTA DE EXECUÇÃO DE ORDENS: /extract ---
app.post('/extract', async (req, res) => {
    const { seriesUrl } = req.body;
    if (!seriesUrl || !seriesUrl.includes('gogoanime.by/')) {
        return res.status(400).json({ error: 'ORDEM CORROMPIDA: URL da série do Gogoanime não fornecida ou inválida.' });
    }

    console.log(`\n======================================================`);
    console.log(`[ALVO DESIGNADO] ${seriesUrl}`);
    console.log(`======================================================`);

    try {
        console.log('[AGENTE] Modo Ultraleve ativado. Obtendo metadados com Axios...');
        const { data: mainPageHtml } = await axios.get(seriesUrl);
        const $ = cheerio.load(mainPageHtml);

        console.log('[RECONHECIMENTO] Analisando estrutura da página de metadados...');
        
        const metadata = {
            title: $('.infox h1.entry-title').text().trim(),
            cover_url: $('.thumb img').attr('src'),
            description: $('.infox .desc, .infox .entry-content p').first().text().trim(),
            status: $('span:contains("Status:")').parent().text().replace('Status:', '').trim(),
            release_year: $('span:contains("Released on:")').parent().text().replace('Released on:', '').split(',').pop().trim(),
            genres: $('.genxed a').map((i, el) => $(el).text().trim()).get()
        };

        if (!metadata.title) throw new Error('Falha no reconhecimento. Título da série não encontrado.');
        console.log(`[RECONHECIMENTO COMPLETO] Título: "${metadata.title}"`);
        
        const episodeLinks = $('#episode_related a, .episodes-container .episode-item a, #load_ep a').map((i, el) => $(el).attr('href')).get();
        if (episodeLinks.length === 0) {
            throw new Error("Nenhum episódio encontrado. O seletor do site pode ter mudado.");
        }
        
        const episodesToProcess = episodeLinks.map(link => new URL(link, 'https://gogoanime.by/').href).reverse();
        
        console.log(`[MAPEAMENTO] ${episodesToProcess.length} episódios encontrados. Iniciando extração dos tokens.`);
        
        const extractedEpisodes = [];
        const REGEX_TOKEN = /loadPlayer\s*\(\s*['"](Blogger|embed)['"],\s*['"]([a-zA-Z0-9\/+=]+)['"]/i;
        const REGEX_TITLE = /<h1 class="entry-title">([^<]+)<\/h1>/;
        const REGEX_SUBTITLE = /data-subtitle=['"]([^'"]+\.vtt)['"]/i;

        for (const [index, episodeUrl] of episodesToProcess.entries()) {
            try {
                await new Promise(resolve => setTimeout(resolve, 50)); // Pausa mínima para não sobrecarregar
                const { data: htmlContent } = await axios.get(episodeUrl, { timeout: 15000 });
                const videoMatch = htmlContent.match(REGEX_TOKEN);
                
                if (videoMatch && videoMatch[2]) {
                    const titleMatch = htmlContent.match(REGEX_TITLE);
                    const chapterName = titleMatch ? titleMatch[1] : `Episode ${index + 1}`;
                    const subtitleMatch = htmlContent.match(REGEX_SUBTITLE);
                    extractedEpisodes.push({
                        chapter_name: chapterName, token: videoMatch[2], subtitle_url: subtitleMatch ? subtitleMatch[1] : null
                    });
                }
            } catch (error) {
                console.error(`  - FALHA no episódio ${index + 1}: ${error.message}`);
            }
        }
        
        const report = { metadata: metadata, episodes: extractedEpisodes };
        console.log(`[MISSÃO CUMPRIDA] Extração de ${extractedEpisodes.length} episódios concluída.`);
        res.status(200).json(report);

    } catch (error) {
        const errorMessage = error.message.split('\n')[0];
        console.error(`[ERRO CATASTRÓFICO] A missão falhou:`, errorMessage);
        res.status(500).json({ error: `O Agente de Extração falhou no servidor: ${errorMessage}` });
    }
});

// OUVE NA PORTA DO RENDER E NO HOST 0.0.0.0
app.listen(port, '0.0.0.0', () => {
    console.log('======================================================');
    console.log(`[SERVIDOR DE EXTRAÇÃO ULTRALEVE ATIVO] Escutando em 0.0.0.0:${port}`);
    console.log('[AGUARDANDO ORDENS DE EXTRAÇÃO]');
    console.log('======================================================');
});
