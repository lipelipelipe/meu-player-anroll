// anime-service.js - v18.0.0 (Edição Final Ultraleve - Sem Playwright)
// Autor: Felipe & IA Assistente
// Foco: Performance e compatibilidade máximas. Usa apenas Axios e Cheerio.
// Roda perfeitamente em qualquer ambiente, incluindo o plano Free do Render.

const express = require('express');
const cors = require('cors');
const axios = require('axios');
const cheerio = require('cheerio'); // <<<<< ADICIONADO para ler HTML

const app = express();
const port = process.env.PORT || 3001;
app.use(cors());
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
    console.log(`[CENTRAL DE COMANDO] Nova missão de extração recebida.`);
    console.log(`[ALVO DESIGNADO] ${seriesUrl}`);
    console.log(`======================================================`);

    try {
        // ETAPA 1: Usar Axios + Cheerio para a página principal (ultrarrápido)
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
        
        const episodeLinks = $('#load_ep a').map((i, el) => $(el).attr('href')).get();
        const episodesToProcess = episodeLinks.map(link => 'https://gogoanime.by' + link).reverse();
        
        console.log(`[MAPEAMENTO] ${episodesToProcess.length} alvos detectados. Iniciando extração dos episódios...`);
        
        const extractedEpisodes = [];
        const REGEX_TOKEN = /loadPlayer\s*\(\s*['"](Blogger|embed)['"],\s*['"]([a-zA-Z0-9\/+=]+)['"]/i;
        const REGEX_TITLE = /<h1 class="entry-title">([^<]+)<\/h1>/;
        const REGEX_SUBTITLE = /data-subtitle=['"]([^'"]+\.vtt)['"]/i;

        // ETAPA 2: Loop com Axios para os episódios (lógica original mantida)
        for (const [index, episodeUrl] of episodesToProcess.entries()) {
            try {
                // Pausa mínima por educação ao servidor
                await new Promise(resolve => setTimeout(resolve, Math.floor(Math.random() * (100 - 50 + 1)) + 50));
                console.log(` -> [EXECUTANDO ${index + 1}/${episodesToProcess.length}] Requisição HTTP para: ${episodeUrl.split('/').pop()}`);
                
                const { data: htmlContent } = await axios.get(episodeUrl, { timeout: 15000 });
                const videoMatch = htmlContent.match(REGEX_TOKEN);
                
                if (videoMatch && videoMatch[2]) {
                    const titleMatch = htmlContent.match(REGEX_TITLE);
                    const chapterName = titleMatch ? titleMatch[1] : `Episode ${index + 1}`;
                    const subtitleMatch = htmlContent.match(REGEX_SUBTITLE);

                    extractedEpisodes.push({
                        chapter_name: chapterName,
                        token: videoMatch[2],
                        subtitle_url: subtitleMatch ? subtitleMatch[1] : null
                    });
                } else {
                    console.warn(`  ⚠️ [ALERTA NO ALVO] Token não encontrado via Axios para episódio ${index + 1}.`);
                }
            } catch (error) {
                console.error(`  ❌ [FALHA NO ALVO] Episódio ${index + 1}: ${error.message}`);
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
