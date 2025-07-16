<?php
// proxy.php — Versão 8.0 FINAL ESTÁVEL
// Proxy seguro para vídeos HLS com suporte completo a subdomínios vidroll (c1–c10)

// 🛡️ Domínios permitidos
$allowed_video_sources = [
    'www.anroll.net',
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
    'vidroll.cloud'
];

// Subdomínios permitidos de c1.vidroll.cloud até c10.vidroll.cloud
for ($i = 1; $i <= 10; $i++) {
    $allowed_video_sources[] = "c{$i}.vidroll.cloud";
}

// 🌐 Origens permitidas (CORS)
$allowed_origins = [
    'http://localhost',
    'https://subarashi.free.nf'
];

// 🌍 URL do proxy atual
$my_proxy_url = 'https://meu-player-anroll.onrender.com/proxy.php';

// 🔐 CORS
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    header("Access-Control-Allow-Origin: *");
}

// 📥 Verifica se foi passada uma URL
$targetUrl = isset($_GET['url']) ? $_GET['url'] : '';
if (empty($targetUrl)) {
    http_response_code(400);
    die("URL não fornecida.");
}

// 🛑 Verifica protocolo
$scheme = parse_url($targetUrl, PHP_URL_SCHEME);
if (!in_array($scheme, ['http', 'https'])) {
    http_response_code(400);
    die("Protocolo inválido.");
}

// 🔁 Previne loop
$targetHost = parse_url($targetUrl, PHP_URL_HOST);
$serverHost = $_SERVER['HTTP_HOST'];
if ($targetHost === $serverHost) {
    http_response_code(400);
    die("Loop de proxy detectado.");
}

// ✅ Verifica se host é permitido
$permitido = false;
foreach ($allowed_video_sources as $source) {
    if (stripos($targetHost, $source) !== false) {
        $permitido = true;
        break;
    }
}
if (!$permitido) {
    http_response_code(403);
    error_log("[PROXY BLOQUEADO] Host não permitido: $targetHost — $targetUrl");
    die("Fonte de vídeo não permitida.");
}

// 🧠 Cabeçalhos padrão
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Referer: https://www.anroll.net/'
];

// 🚀 Executa cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $targetUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HEADER => false,
]);

$content = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curl_error = curl_error($ch);
curl_close($ch);

// ❌ Falha no cURL
if ($content === false || $httpcode < 200 || $httpcode >= 300) {
    http_response_code($httpcode > 0 ? $httpcode : 502);
    die("Erro ao buscar o conteúdo remoto. Status $httpcode — $curl_error");
}

// 📄 Se for .m3u8, reescreve
if (preg_match('/\.m3u8(\?.*)?$/i', $targetUrl)) {
    header('Content-Type: application/vnd.apple.mpegurl');

    $base_url = substr($targetUrl, 0, strrpos($targetUrl, '/') + 1);
    $lines = explode("\n", $content);
    $new_content = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            $new_content .= $line . "\n";
        } else {
            if (stripos($line, 'http') !== 0) {
                $line = $base_url . $line;
            }
            $proxied = $my_proxy_url . '?url=' . urlencode($line);
            $new_content .= $proxied . "\n";
        }
    }

    if (strpos($new_content, '#EXT-X-ENDLIST') === false) {
        $new_content .= "#EXT-X-ENDLIST\n";
    }

    echo $new_content;

// 🎬 Qualquer outro conteúdo: vídeo, .ts, .key, imagens etc.
} else {
    header("Content-Type: $contentType");
    echo $content;
}
?>
