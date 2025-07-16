<?php
// proxy.php - Versão profissional para Render.com

// Lista de domínios permitidos
$allowed_video_sources = [
    'www.anroll.net',
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
    'c5.vidroll.cloud',
    'c6.vidroll.cloud',
    'c7.vidroll.cloud',
    'c8.vidroll.cloud',
    'vidroll.cloud' // Domínio raiz adicionado para maior abrangência
];

// Configurações de segurança
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Verifica se a URL foi fornecida
if (!isset($_GET['url']) || empty($_GET['url'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'URL não fornecida.']));
}

$targetUrl = urldecode($_GET['url']);

// Valida a URL
$urlParts = parse_url($targetUrl);
if (!isset($urlParts['host'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'URL inválida.']));
}

// Verifica se o domínio está na lista de permissões
$domainAllowed = false;
foreach ($allowed_video_sources as $allowed_domain) {
    if (strpos($urlParts['host'], $allowed_domain) !== false) {
        $domainAllowed = true;
        break;
    }
}

if (!$domainAllowed) {
    http_response_code(403);
    header('Content-Type: application/json');
    die(json_encode(['error' => "Domínio '{$urlParts['host']}' não permitido."]));
}

// Configura o cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $targetUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'Referer: https://www.anroll.net/',
        'Accept: */*',
        'Accept-Language: en-US,en;q=0.9',
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// Processa a resposta
if ($httpCode >= 200 && $httpCode < 300) {
    if (strpos($targetUrl, '.m3u8') !== false) {
        // Processa playlists M3U8
        header('Content-Type: application/vnd.apple.mpegurl');
        $baseUrl = substr($targetUrl, 0, strrpos($targetUrl, '/') + 1);
        $currentProxyUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
        
        $lines = explode("\n", $response);
        $processedContent = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                $processedContent .= $line . "\n";
                continue;
            }
            
            if (!preg_match('/^https?:\/\//i', $line)) {
                $line = $baseUrl . $line;
            }
            
            $processedContent .= $currentProxyUrl . '?url=' . urlencode($line) . "\n";
        }
        
        echo $processedContent;
    } else {
        // Outros tipos de conteúdo (TS, etc)
        header('Content-Type: ' . $contentType);
        echo $response;
    }
} else {
    http_response_code(502);
    header('Content-Type: application/json');
    die(json_encode(['error' => "Falha ao buscar o conteúdo. Status: {$httpCode}"]));
}
?>
