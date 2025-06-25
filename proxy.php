<?php
// proxy.php - Proxy HLS com suporte a Referer e domínios específicos

// Lista de domínios permitidos a acessar este proxy
$allowed_origins = [
    'http://localhost',
    'http://127.0.0.1',
    'http://subarashi.free.nf',
    'https://subarashi.free.nf',
];

// Libera CORS apenas se o origin for confiável
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
}
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");

// Domínios permitidos para os vídeos .m3u8
$allowed_video_sources = ['cdn-zenitsu-2-gamabunta.b-cdn.net'];
for ($i = 1; $i <= 30; $i++) {
    $allowed_video_sources[] = "c{$i}.vidroll.cloud";
}

$targetUrl = $_GET['url'] ?? '';
if (empty($targetUrl)) {
    http_response_code(400);
    exit("Erro: Parâmetro 'url' não fornecido.");
}

$urlParts = parse_url($targetUrl);
$host = $urlParts['host'] ?? '';
if (!in_array($host, $allowed_video_sources)) {
    http_response_code(403);
    exit("Erro: Acesso ao host '{$host}' não é permitido.");
}

// Cabeçalhos simulando navegador com Referer do Anroll
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'Referer: https://www.anroll.net/',
];

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 20,
]);

$content = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpcode >= 200 && $httpcode < 300) {
    // Reescreve URLs da playlist m3u8
    if (stripos($contentType, 'mpegurl') !== false || stripos($targetUrl, '.m3u8') !== false) {
        $baseUrl = substr($targetUrl, 0, strrpos($targetUrl, '/') + 1);
        $myProxy = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/proxy.php';

        $content = preg_replace_callback('/^(?!#)(.*)$/m', function ($matches) use ($baseUrl, $myProxy) {
            $line = trim($matches[1]);
            if (!preg_match('/^https?:\/\//', $line)) {
                $line = $baseUrl . $line;
            }
            return $myProxy . '?url=' . urlencode($line);
        }, $content);

        header('Content-Type: application/vnd.apple.mpegurl');
    } else {
        header('Content-Type: ' . $contentType);
    }

    echo $content;
} else {
    http_response_code(502);
    exit("Erro ao buscar conteúdo remoto ({$httpcode})");
}
