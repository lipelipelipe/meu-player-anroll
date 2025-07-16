<?php
// proxy.php - Proxy robusto para streaming HLS com reescrita e proteção

// Lista de domínios permitidos (ex: vidroll e anroll)
$allowed_video_sources = [
    'www.anroll.net',
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
    'c5.vidroll.cloud',
    'c6.vidroll.cloud',
    'c7.vidroll.cloud',
    'c8.vidroll.cloud',
    'vidroll.cloud'
];

// Cabeçalhos CORS - libera para qualquer origem
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Verifica se a URL foi passada
if (!isset($_GET['url']) || empty($_GET['url'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'URL não fornecida']);
    exit;
}

$targetUrl = urldecode($_GET['url']);

// Validação básica da URL
$urlParts = parse_url($targetUrl);
if (!isset($urlParts['host'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'URL inválida']);
    exit;
}

// Verifica domínio permitido (aceita subdomínios parciais)
$allowed = false;
foreach ($allowed_video_sources as $domain) {
    if (stripos($urlParts['host'], $domain) !== false) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => "Domínio não permitido: {$urlParts['host']}"]);
    exit;
}

// Define headers para "enganação" da proteção
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Referer: https://www.anroll.net/',
    'Accept: */*',
    'Accept-Language: en-US,en;q=0.9'
];

// Função para fazer a requisição CURL com retries
function fetchUrl($url, $headers, $maxRetries = 3, $timeout = 15) {
    $attempt = 0;
    do {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_ENCODING => '' // gzip, deflate etc
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $attempt++;
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['code' => $httpCode, 'content' => $response];
        }
        // Pode adicionar delay entre tentativas se quiser
    } while ($attempt < $maxRetries);

    return ['code' => $httpCode ?? 0, 'content' => null, 'error' => $err];
}

// Faz a requisição ao conteúdo original
$result = fetchUrl($targetUrl, $headers);
if ($result['code'] < 200 || $result['code'] >= 300 || !$result['content']) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => "Falha ao buscar o conteúdo. HTTP code: {$result['code']}", 'curl_error' => $result['error'] ?? '']);
    exit;
}

// Detecta se é um arquivo M3U8 para reescrever URLs
if (stripos($targetUrl, '.m3u8') !== false) {
    header('Content-Type: application/vnd.apple.mpegurl');

    $baseUrl = substr($targetUrl, 0, strrpos($targetUrl, '/') + 1);
    $proxyUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

    $lines = preg_split('/\r\n|\r|\n/', $result['content']);
    $output = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            $output .= $line . "\n";
            continue;
        }
        // Se URL relativa, transforma em absoluta
        if (!preg_match('/^https?:\/\//i', $line)) {
            $line = $baseUrl . $line;
        }
        // Reescreve para passar pelo proxy
        $output .= $proxyUrl . '?url=' . urlencode($line) . "\n";
    }
    echo $output;
    exit;
}

// Para outros arquivos (ts, key, mp4 etc) apenas repassa
header('Content-Type: ' . ($result['content-type'] ?? 'application/octet-stream'));
echo $result['content'];
exit;
