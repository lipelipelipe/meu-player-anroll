<?php
// proxy.php — Versão 7.0 FINAL
// Proxy seguro para streaming HLS com reescrita automática de manifests (.m3u8)

// 🔒 Domínios permitidos (parcial com suporte a subdomínios)
$allowed_video_sources = [
    'www.anroll.net',
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
    'vidroll.cloud'
];

// 🌐 Domínios permitidos para CORS
$allowed_origins = [
    'http://localhost',
    'https://subarashi.free.nf'
];

// 🧭 URL do seu próprio proxy
$my_proxy_url = 'https://meu-player-anroll.onrender.com/proxy.php';

// 🔐 CORS: permite apenas domínios específicos (ou todos como fallback)
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    header("Access-Control-Allow-Origin: *");
}

// 📥 Valida se a URL foi fornecida
$targetUrl = isset($_GET['url']) ? $_GET['url'] : '';
if (empty($targetUrl)) {
    http_response_code(400);
    die("URL não fornecida.");
}

// 🛡️ Valida protocolo permitido
$scheme = parse_url($targetUrl, PHP_URL_SCHEME);
if (!in_array($scheme, ['http', 'https'])) {
    http_response_code(400);
    die("Protocolo inválido.");
}

// 🌀 Impede loop no próprio proxy
if (strpos($targetUrl, $_SERVER['HTTP_HOST']) !== false) {
    http_response_code(400);
    die("Loop de proxy detectado.");
}

// 🔍 Valida se o host faz parte de algum domínio permitido (parcial com subdomínio)
$urlParts = parse_url($targetUrl);
$host = $urlParts['host'] ?? '';
$permitido = false;

foreach ($allowed_video_sources as $source) {
    if (strpos($host, $source) !== false) {
        $permitido = true;
        break;
    }
}

if (!$permitido) {
    http_response_code(403);
    error_log("[PROXY BLOQUEADO] Host não permitido: $host — $targetUrl");
    die("Fonte de vídeo não permitida.");
}

// 🧠 Cabeçalhos padrão
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Referer: https://www.anroll.net/'
];

// 🔁 Executa cURL
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

// ✅ Se sucesso
if ($httpcode >= 200 && $httpcode < 300) {

    // 📄 Se for um arquivo .m3u8
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

            // 🔗 Corrige caminho relativo
            if (strpos($line, 'http') !== 0) {
                $line = $base_url . $line;
            }

            // 🔁 Encaminha pelo proxy novamente
            $proxied_line = $my_proxy_url . '?url=' . urlencode($line);
            $new_content .= $proxied_line . "\n";
        }

        // 🔚 Garante finalização
        if (strpos($new_content, '#EXT-X-ENDLIST') === false) {
            $new_content .= "#EXT-X-ENDLIST\n";
        }

        echo $new_content;
    } else {
        // 🎥 Outro tipo de conteúdo (vídeo, .ts, .key, .jpg, etc.)
        header("Content-Type: " . $contentType);
        echo $content;
    }

} else {
    // ❌
