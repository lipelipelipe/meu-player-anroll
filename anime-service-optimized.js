// anime-service.js - v16.0.0 (Edição Final para Deploy em Produção)
// Autor: Felipe & IA Assistente
// Foco: Estabilidade em ambientes de nuvem como o Render.

const express = require('express');
const cors = require('cors');
const { chromium } = require('playwright');
const axios = require('axios');

const app = express();
// USA A PORTA FORNECIDA PELO RENDER, ou 3001 para desenvolvimento local.
const port = process.env.PORT || 3001;
app.use(cors());
app.use(express.json({ limit: '10mb' }));

// --- PROTOCOLO DE TEMPORIZAÇÃO HUMANA ---
const pausaHumana = (min, max) => {
    const tempo = Math.floor(Math.random() * (max - min + 1)) + min;
    console.log(`[SINCRONIA] Pausando por ${tempo}ms...`);
    return new Promise(resolve => setTimeout(resolve, tempo));
};

// =========================================================================
// ROTA DE VERIFICAÇÃO DE SAÚDE (HEALTH CHECK) - CRÍTICO PARA O RENDER
// Esta rota responde ao "robô de saúde" do Render para provar que o serviço está vivo.
// =========================================================================
app.get('/', (req, res) => {
    res.status(200).json({
        status: 'ok',
        message: 'Serviço de extração está operacional e aguardando ordens na rota /extract.'
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

    let browser = null;

    try {
        console.log('[AGENTE] Iniciando navegador em modo furtivo para metadados...');
        browser = await chromium.launch({ headless: true });
        const page = await browser.newPage();
        await page.goto(seriesUrl, { waitUntil: 'domcontentloaded' });
        
        console.log('[RECONHECIMENTO] Analisando estrutura da página de metadados...');
        const infoBoxLocator = page.locator('.infox');
        const title = await infoBoxLocator.locator('h1.entry-title').innerText();

        const getTextFromLocator = async (locator) => {
            try { return await locator.innerText({ timeout: 5000 }); } catch (e) { return null; }
        };
        const descriptionLocator = infoBoxLocator.locator('div.desc, .ninfo > p, .entry-content p').first();
        const metadata = {
            title: title,
            cover_url: await page.locator('.thumb img').getAttribute('src'),
            description: await getTextFromLocator(descriptionLocator),
            status: (await getTextFromLocator(infoBoxLocator.locator('span:has-text("Status:")')))?.replace('Status:', '').trim(),
            release_year: (await getTextFromLocator(infoBoxLocator.locator('span:has-text("Released on:")')))?.split(',').pop().trim(),
            genres: await infoBoxLocator.locator('.genxed a').allInnerTexts(),
        };

        if (!metadata.title) throw new Error('Falha no reconhecimento. Título da série não encontrado.');
        console.log(`[RECONHECIMENTO COMPLETO] Título: "${metadata.title}"`);
        
        const allLinks = await page.locator('.episodes-container .episode-item a, #episode_related a, #load_ep a').evaluateAll(links => links.map(a => a.href));
        const episodesToProcess = allLinks.filter(href => href.includes('-episode-')).reverse();
        
        await browser.close();
        browser = null;
        console.log('[AGENTE] Navegador desativado. Mudando para modo de extração rápida.');
        
        console.log(`[MAPEAMENTO] ${episodesToProcess.length} alvos detectados. Iniciando extração em modo RÁPIDO...`);
        
        const extractedEpisodes = [];
        const REGEX_TOKEN = /loadPlayer\s*\(\s*['"](Blogger|embed)['"],\s*['"]([a-zA-Z0-9\/+=]+)['"]/i;
        const REGEX_TITLE = /<h1 class="entry-title">([^<]+)<\/h1>/;
        const REGEX_SUBTITLE = /data-subtitle=['"]([^'"]+\.vtt)['"]/i;

        for (const [index, episodeUrl] of episodesToProcess.entries()) {
            try {
                await pausaHumana(50, 100); 
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
        console.log(`[MISSÃO CUMPRIDA] Extração de ${extractedEpisodes.length} episódios concluída em tempo recorde.`);
        res.status(200).json(report);

    } catch (error) {
        const errorMessage = error.message.split('\n')[0];
        console.error(`[ERRO CATASTRÓFICO] A missão falhou:`, errorMessage);
        res.status(500).json({ error: `O Agente de Extração falhou: ${errorMessage}` });
    } finally {
        if (browser) {
            await browser.close();
            console.log(`[DESMOBILIZAÇÃO DE EMERGÊNCIA] Agente desativado.`);
        }
    }
});

// OUVE NA PORTA DO RENDER E NO HOST 0.0.0.0, que é necessário para ambientes Docker.
app.listen(port, '0.0.0.0', () => {
    console.log('======================================================');
    console.log(`[SERVIDOR DE EXTRAÇÃO ATIVO] Escutando em 0.0.0.0:${port}`);
    console.log('[AGUARDANDO ORDENS DE EXTRAÇÃO]');
    console.log('======================================================');
});
