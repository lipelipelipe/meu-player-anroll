<?php
// proxy.php — Versão 6.0 FINAL, Blindada e Compatível com Players Modernos

// Domínios permitidos (fontes de vídeo confiáveis)
$allowed_video_sources = [
    'www.anroll.net',
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
    'c5.vidroll.cloud',
    'c6.vidroll.cloud',
    'c7.vidroll.cloud',
    'c8.vidroll.cloud'
];

// Domínios permitidos para requisições CORS
$allowed_origins = [
    'http://localhost',
    'https://subarashi.free.nf'
];

// URL do próprio proxy (ajuste conforme seu domínio)
$my_proxy_url = 'https://meu-player-anroll.onrender.com/proxy.php';

// Valida CORS dinamicamente
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    header("Access-Control-Allow-Origin: *"); // fallback aberto (ajuste conforme necessário)
}

// Impede acesso direto sem parâmetro
$targetUrl = isset($_GET['url']) ? $_GET['url'] : '';
if (empty($targetUrl)) {
    http_response_code(400);
    die("URL não fornecida.");
}

// Valida protocolo (só http/https)
$scheme = parse_url($targetUrl, PHP_URL_SCHEME);
if (!in_array($scheme, ['http', 'https'])) {
    http_response_code(400);
    die("Protocolo inválido.");
}

// Impede loop no próprio proxy
if (strpos($targetUrl, $_SERVER['HTTP_HOST']) !== false) {
    http_response_code(400);
    die("Loop de proxy detectado.");
}

// Valida host permitido
$urlParts = parse_url($targetUrl);
$host = $urlParts['host'] ?? '';
if (!in_array($host, $allowed_video_sources)) {
    http_response_code(403);
    die("Fonte de vídeo não permitida.");
}

// Define cabeçalhos padrão
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Referer: https://www.anroll.net/'
];

// Inicia requisição cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $targetUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HEADER => false
]);

$content = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// Se sucesso
if ($httpcode >= 200 && $httpcode < 300) {
    // Se for um manifesto HLS (.m3u8)
    if (preg_match('/\.m3u8(\?.*)?$/i', $targetUrl)) {
        header('Content-Type: application/vnd.apple.mpegurl');

        $base_url = substr($targetUrl, 0, strrpos($targetUrl, '/') + 1);
        $lines = explode("\n", $content);
        $new_content = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                $new_content .= $line . "\n";
                continue;
            }

            // Corrige caminhos relativos
            if (strpos($line, 'http') !== 0) {
                $line = $base_url . $line;
            }

            // Redireciona pelo proxy
            $new_content .= $my_proxy_url . '?url=' . urlencode($line) . "\n";
        }

        // Garante fim do manifesto
        if (strpos($new_content, '#EXT-X-ENDLIST') === false) {
            $new_content .= "#EXT-X-ENDLIST\n";
        }

        echo $new_content;
    } else {
        // Outro tipo de conteúdo (vídeo, TS, imagem etc.)
        header("Content-Type: " . $contentType);
        echo $content;
    }
} else {
    // Falha ao buscar conteúdo remoto
    http_response_code($httpcode > 0 ? $httpcode : 502);
    die("Falha ao buscar conteúdo remoto. Código: " . $httpcode);
}
?>
