<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Player de Vídeo HLS</title>
  <script src="hls.min.js"></script>
  <style>
    html, body { margin: 0; padding: 0; background: #000; height: 100%; overflow: hidden; }
    video { width: 100%; height: 100%; border: none; }
  </style>
</head>
<body>
  <video id="video" controls autoplay playsinline></video>
  <script>
    const params = new URLSearchParams(window.location.search);
    const token = params.get('Blogger');

    if (token) {
      const proxyUrl = `proxy.php?url=${encodeURIComponent(token)}`;
      const video = document.getElementById('video');

      if (Hls.isSupported()) {
        
        // ==============================================================================
        // NOVA CONFIGURAÇÃO: Desabilitamos o carregamento de thumbnails.
        // Isso vai impedir que o player tente buscar as imagens /i2/image/
        // e, consequentemente, vai eliminar os erros 403.
        // ==============================================================================
        const hlsConfig = {
          pdtLoadRetry: 4,    // Retentativas padrão
          levelLoadRetry: 4,  // Retentativas padrão
          fragLoadRetry: 4,   // Retentativas padrão
          enableWebVTT: false,  // Desabilita legendas WebVTT se não forem usadas
          enableCEA708Captions: false, // Desabilita captions se não forem usadas
          // A LINHA MAIS IMPORTANTE:
          vttjs: '', // Forçar a desativação de qualquer tentativa de buscar thumbnails
        };
        
        const hls = new Hls(hlsConfig);
        // ==============================================================================

        hls.loadSource(proxyUrl);
        hls.attachMedia(video);
        
      } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = proxyUrl;
      }
    } else {
      document.body.innerHTML = "<p style='color:white; text-align:center; font-family: sans-serif;'>ERRO: URL do vídeo não foi fornecida.</p>";
    }
  </script>
</body>
</html>
