/**
 * Script do Player HLS
 *
 * Este script lida com a inicialização do player de vídeo usando hls.js.
 * Ele constrói a URL do proxy e anexa o stream de vídeo ao elemento <video>.
 */

// Executa o código apenas depois que todo o conteúdo HTML da página foi carregado.
document.addEventListener('DOMContentLoaded', () => {
    
    // --- CONFIGURAÇÃO ---
    
    const videoElement = document.getElementById('videoPlayer');
    if (!videoElement) {
        console.error("Elemento de vídeo #videoPlayer não encontrado.");
        return;
    }
    
    // A URL original do manifest HLS. Mantida aqui para clareza e fácil manutenção.
    const originalHLSUrl = "https://cdn-zenitsu-2-gamabunta.b-cdn.net/cf/hls/animes/kijin-gentoushou/013.mp4/media-1/stream.m3u8";

    // URL base do seu proxy PHP.
    // ATENÇÃO: Substitua 'https://meu-player-anroll.onrender.com' pela URL real do seu serviço Render.com após o deploy.
    const proxyBaseUrl = "https://meu-player-anroll.onrender.com/proxy.php";
    
    // Constrói a URL final que será passada para o player.
    // `encodeURIComponent` é VITAL. Ele codifica a URL original para que caracteres especiais como '?', '&' e '/'
    // não quebrem a URL do proxy. Isso garante que a URL completa seja passada como um único parâmetro.
    const proxiedHLSUrl = `${proxyBaseUrl}?url=${encodeURIComponent(originalHLSUrl)}`;
    
    console.log("Iniciando player com a URL do proxy:", proxiedHLSUrl);

    // --- LÓGICA DE INICIALIZAÇÃO DO PLAYER ---

    // Verifica se o navegador suporta HLS via hls.js.
    if (Hls.isSupported()) {
        console.log("hls.js é suportado. Inicializando...");
        const hls = new Hls({
            // Configurações opcionais para robustez
            maxMaxBufferLength: 30, // Limita o buffer para economizar memória
            enableWorker: true,     // Usa Web Workers para decodificar, melhorando a performance da UI
        });

        // Carrega a fonte (nosso proxy).
        hls.loadSource(proxiedHLSUrl);
        // Anexa o HLS ao elemento <video>.
        hls.attachMedia(videoElement);

        // Event listener para quando o manifest é carregado e analisado.
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            console.log("Manifest HLS carregado e analisado com sucesso.");
            // O atributo 'muted' no HTML aumenta a chance do autoplay funcionar.
            // Aqui, tentamos tocar o vídeo, e se falhar (devido a políticas do navegador),
            // o usuário ainda terá os controles para iniciar manualmente.
            videoElement.play().catch(error => {
                console.warn("Autoplay bloqueado pelo navegador:", error.message);
            });
        });

        // Event listener para tratamento de erros. Essencial para um player robusto.
        hls.on(Hls.Events.ERROR, (event, data) => {
            console.error(`Erro no HLS.js: Tipo - ${data.type}, Detalhes - ${data.details}`);
            if (data.fatal) {
                switch (data.type) {
                    case Hls.ErrorTypes.NETWORK_ERROR:
                        console.warn("Erro de rede fatal. Tentando reconectar...");
                        hls.startLoad(); // Tenta recarregar a fonte.
                        break;
                    case Hls.ErrorTypes.MEDIA_ERROR:
                        console.warn("Erro de mídia fatal. Tentando recuperar...");
                        hls.recoverMediaError(); // Tenta recuperar de um segmento corrompido.
                        break;
                    default:
                        // Erro irrecuperável. Destrói a instância do HLS.
                        console.error("Erro fatal e irrecuperável. Destruindo a instância do HLS.");
                        hls.destroy();
                        break;
                }
            }
        });

    } else if (videoElement.canPlayType('application/vnd.apple.mpegurl')) {
        // Fallback para navegadores com suporte nativo a HLS (principalmente Safari no macOS e iOS).
        console.log("hls.js não é suportado, mas o navegador tem suporte nativo a HLS.");
        videoElement.src = proxiedHLSUrl;
    } else {
        alert("Seu navegador não suporta a tecnologia HLS necessária para reproduzir este vídeo.");
        console.error("HLS não é suportado neste navegador.");
    }
});
