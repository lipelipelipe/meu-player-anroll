<?php
// proxy.php — Proxy seguro para streaming HLS com reescrita automática de manifests (.m3u8)

// --- Configurações ---

// Domínios permitidos (parcial com suporte a subdomínios)
$allowed_video_sources = [
    'www.anroll.net',
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
    'vidroll.cloud',
    'c5.vidroll.cloud',
    'c6.vidroll.cloud',
    // adicione outros domínios que desejar
];

// Domínios permitidos para CORS
$allowed_origins = [
    'http://localhost',
    'https://subarashi.free.nf',
    // adicione outros domínios que acessam seu proxy
];

// URL do seu proxy (para reescrita dos manifests)
$my_proxy_url = 'https://meu-player-anroll.onrender.com/proxy.php';

// --- CORS ---
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    header("Access-Control-Allow-Origin: *");
}

// --- Validação URL ---
$targetUrl = isset($_GET['url']) ? trim($_GET['url']) : '';
if (!$targetUrl) {
    http_response_code(400);
    die("URL não fornecida.");
}

$scheme = parse_url($targetUrl, PHP_URL_SCHEME);
if (!in_array($scheme, ['http', 'https'])) {
    http_response_code(400);
    die("Protocolo inválido.");
}

// Evita loop de proxy
if (strpos($targetUrl, $_SERVER['HTTP_HOST']) !== false) {
    http_response_code(400);
    die("Loop de proxy detectado.");
}

$urlParts = parse_url($targetUrl);
$host = $urlParts['host'] ?? '';
$permitido = false;
foreach ($allowed_video_sources as $source) {
    if (stripos($host, $source) !== false) {
        $permitido = true;
        break;
    }
}
if (!$permitido) {
    http_response_code(403);
    error_log("[PROXY BLOQUEADO] Host não permitido: $host — $targetUrl");
    die("Fonte de vídeo não permitida.");
}

// --- cURL com headers reforçados ---
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
    'Referer: https://www.anroll.net/',
    'Connection: keep-alive',
    'Cache-Control: max-age=0',
];

// Inicializa cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $targetUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HEADER => false,
    CURLOPT_COOKIEFILE => '', // ativa uso de cookies
    CURLOPT_COOKIEJAR => '',  // ativa armazenamento de cookies
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
]);

$content = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpcode >= 200 && $httpcode < 300) {
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
            // Reencaminha pelo proxy
            $proxied_line = $my_proxy_url . '?url=' . urlencode($line);
            $new_content .= $proxied_line . "\n";
        }
        if (strpos($new_content, '#EXT-X-ENDLIST') === false) {
            $new_content .= "#EXT-X-ENDLIST\n";
        }
        echo $new_content;
    } else {
        header("Content-Type: " . $contentType);
        echo $content;
    }
} else {
    http_response_code($httpcode);
    die("Erro ao buscar conteúdo remoto. HTTP código: $httpcode");
}
