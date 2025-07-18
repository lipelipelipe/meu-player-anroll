// anime-service.js - v23.0 (Edição Definitiva Ultraleve com Lógica de Links Corrigida)
// Autor: Felipe & IA Assistente
// Foco: Versão final que combina a abordagem ultraleve com a lógica de extração de links correta.

const express = require('express');
const cors = require('cors');
const axios = require('axios');
const cheerio = require('cheerio');

const app = express();
const port = process.env.PORT || 3001;

const corsOptions = { origin: '*', methods: 'GET,POST', optionsSuccessStatus: 204 };
app.use(cors(corsOptions));
app.use(express.json({ limit: '10mb' }));

app.get('/', (req, res) => {
    res.status(200).json({ status: 'ok', message: 'Serviço ULTRALEVE está operacional (v23.0).' });
});

app.post('/extract', async (req, res) => {
    const { seriesUrl } = req.body;
    if (!seriesUrl || !seriesUrl.includes('gogoanime.by/')) {
        return res.status(400).json({ error: 'URL inválida.' });
    }
    console.log(`\n[MISSÃO] Alvo: ${seriesUrl}`);
    try {
        console.log('[AGENTE] Modo Ultraleve. Obtendo metadados...');
        const { data: mainPageHtml } = await axios.get(seriesUrl);
        const $ = cheerio.load(mainPageHtml);
        
        const metadata = {
            title: $('.infox h1.entry-title').text().trim(),
            cover_url: $('.thumb img').attr('src'),
            description: $('.infox .desc, .infox .entry-content p').first().text().trim(),
            status: $('span:contains("Status:")').parent().text().replace('Status:', '').trim(),
            release_year: $('span:contains("Released on:")').parent().text().replace('Released on:', '').split(',').pop().trim(),
            genres: $('.genxed a').map((i, el) => $(el).text().trim()).get()
        };

        if (!metadata.title) throw new Error('Título não encontrado.');
        
        // =========================================================================================
        // >> A CORREÇÃO FINAL E DEFINITIVA <<
        // Combina a busca em múltiplos seletores com a garantia de que a URL será sempre completa.
        // =========================================================================================
        const baseUrl = 'https://gogoanime.by/';
        const rawLinks = $('#episode_related a, .episodes-container .episode-item a, #load_ep a')
            .map((i, el) => $(el).attr('href'))
            .get();

        const episodesToProcess = rawLinks
            .map(link => {
                // Se o link já for uma URL completa, use-o.
                // Se for um caminho relativo (ex: /episode-1), complete-o com a base.
                try {
                    return new URL(link, baseUrl).href;
                } catch (e) {
                    return null; // Retorna nulo se o link for inválido
                }
            })
            .filter(href => href && href.includes('-episode-')) // Filtra nulos e garante que é um link de episódio
            .reverse();
        // =========================================================================================

        if (episodesToProcess.length === 0) {
            // Este erro agora é mais confiável. Se ele aparecer, o site realmente mudou.
            throw new Error('Nenhum episódio encontrado. O seletor do HTML do site pode ter sido alterado.');
        }

        console.log(`[MAPEAMENTO] ${episodesToProcess.length} episódios encontrados. Iniciando extração dos tokens.`);
        
        const extractedEpisodes = [];
        const REGEX_TOKEN = /loadPlayer\s*\(\s*['"](Blogger|embed)['"],\s*['"]([a-zA-Z0-9\/+=]+)['"]/i;
        const REGEX_TITLE = /<h1 class="entry-title">([^<]+)<\/h1>/;
        const REGEX_SUBTITLE = /data-subtitle=['"]([^'"]+\.vtt)['"]/i;

        for (const [index, episodeUrl] of episodesToProcess.entries()) {
            try {
                await new Promise(resolve => setTimeout(resolve, 50));
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
        console.log(`[MISSÃO CUMPRIDA] Extração de ${extractedEpisodes.length} tokens concluída.`);
        res.status(200).json(report);

    } catch (error) {
        console.error(`[ERRO CATASTRÓFICO]`, error);
        res.status(500).json({ error: `O Agente falhou: ${error.message}` });
    }
});

app.listen(port, '0.0.0.0', () => {
    console.log(`[SERVIDOR ATIVO] Escutando em 0.0.0.0:${port}`);
});
