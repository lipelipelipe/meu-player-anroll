// anime-service-optimized.js - v15.1.0 (Edição Final Robusta)
// Autor: Felipe & IA Assistente
// Estratégia: Metadados via Playwright + Episódios via Axios
// Proteções extras: timeouts, try/catch refinado, fallback de porta, shutdown seguro

const express = require('express');
const cors = require('cors');
const { chromium } = require('playwright');
const axios = require('axios');

const app = express();
const PORT = process.env.PORT || 3001;

app.use(cors());
app.use(express.json({ limit: '10mb' }));

// Pausa humana aleatória entre requisições (simula comportamento natural)
const pausaHumana = (min, max) => {
    const tempo = Math.floor(Math.random() * (max - min + 1)) + min;
    console.log(`[SINCRONIA] Pausando por ${tempo}ms...`);
    return new Promise(resolve => setTimeout(resolve, tempo));
};

// Rota de extração principal
app.post('/extract', async (req, res) => {
    const { seriesUrl } = req.body;

    if (!seriesUrl || typeof seriesUrl !== 'string' || !seriesUrl.includes('gogoanime.by/')) {
        return res.status(400).json({ error: 'ORDEM CORROMPIDA: URL inválida ou ausente.' });
    }

    console.log('\n======================================================');
    console.log('[CENTRAL DE COMANDO] Nova missão recebida.');
    console.log(`[ALVO DESIGNADO] ${seriesUrl}`);
    console.log('======================================================');

    let browser = null;

    try {
        // METADADOS via Playwright
        console.log('[AGENTE] Iniciando navegador para reconhecimento...');
        browser = await chromium.launch({ headless: true });
        const page = await browser.newPage();
        await page.goto(seriesUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });

        const infoBoxLocator = page.locator('.infox');
        const title = await infoBoxLocator.locator('h1.entry-title').innerText();

        if (!title) throw new Error('Título não encontrado.');

        const getTextFromLocator = async (locator) => {
            try {
                return await locator.innerText({ timeout: 5000 });
            } catch {
                return null;
            }
        };

        const descriptionLocator = infoBoxLocator.locator('div.desc, .ninfo > p, .entry-content p').first();

        const metadata = {
            title: title,
            cover_url: await page.locator('.thumb img').getAttribute('src'),
            description: await getTextFromLocator(descriptionLocator),
            status: (await getTextFromLocator(infoBoxLocator.locator('span:has-text("Status:")')))
                ?.replace('Status:', '').trim(),
            release_year: (await getTextFromLocator(infoBoxLocator.locator('span:has-text("Released on:")')))
                ?.split(',').pop().trim(),
            genres: await infoBoxLocator.locator('.genxed a').allInnerTexts()
        };

        console.log(`[RECONHECIMENTO] Série: ${metadata.title}`);

        const allLinks = await page.locator('.episodes-container .episode-item a, #episode_related a, #load_ep a')
            .evaluateAll(links => links.map(a => a.href));
        const episodesToProcess = allLinks.filter(href => href.includes('-episode-')).reverse();

        await browser.close();
        browser = null;
        console.log('[AGENTE] Navegador desligado. Modo rápido ativado.');

        // EXTRAÇÃO via Axios
        console.log(`[EXTRAÇÃO] ${episodesToProcess.length} episódios detectados.`);

        const extractedEpisodes = [];
        const REGEX_TOKEN = /loadPlayer\s*\(\s*['"](Blogger|embed)['"],\s*['"]([a-zA-Z0-9\/+=]+)['"]/i;
        const REGEX_TITLE = /<h1 class="entry-title">([^<]+)<\/h1>/;
        const REGEX_SUBTITLE = /data-subtitle=['"]([^'"]+\.vtt)['"]/i;

        for (const [index, episodeUrl] of episodesToProcess.entries()) {
            try {
                await pausaHumana(40, 90);
                console.log(` -> [${index + 1}/${episodesToProcess.length}] ${episodeUrl.split('/').pop()}`);

                const { data: htmlContent } = await axios.get(episodeUrl, { timeout: 15000 });

                const videoMatch = htmlContent.match(REGEX_TOKEN);
                if (!videoMatch || !videoMatch[2]) {
                    console.warn(` ⚠️ Token não encontrado em ${episodeUrl}`);
                    continue;
                }

                const chapterName = (htmlContent.match(REGEX_TITLE)?.[1] || `Episode ${index + 1}`).trim();
                const subtitleMatch = htmlContent.match(REGEX_SUBTITLE);

                extractedEpisodes.push({
                    chapter_name: chapterName,
                    token: videoMatch[2],
                    subtitle_url: subtitleMatch ? subtitleMatch[1] : null
                });

            } catch (err) {
                console.error(` ❌ Episódio ${index + 1}: ${err.message}`);
            }
        }

        const report = { metadata, episodes: extractedEpisodes };
        console.log(`[MISSÃO CONCLUÍDA] Episódios extraídos: ${extractedEpisodes.length}`);
        res.status(200).json(report);

    } catch (err) {
        console.error(`[FALHA GERAL] ${err.message}`);
        res.status(500).json({ error: `Falha na extração: ${err.message}` });

    } finally {
        if (browser) {
            await browser.close();
            console.log('[FECHAMENTO FORÇADO] Navegador encerrado por segurança.');
        }
    }
});

// Inicialização do servidor
app.listen(PORT, '0.0.0.0', () => {
    console.log('======================================================');
    console.log(`[SERVIDOR ATIVO] http://localhost:${PORT}`);
    console.log('[PRONTO PARA RECEBER ORDENS DE EXTRAÇÃO]');
    console.log('======================================================');
});
