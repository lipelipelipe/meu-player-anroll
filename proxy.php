<?php
// proxy.php - Otimizado e seguro para www.anroll.net
define('ALLOWED_DOMAIN', 'www.anroll.net');

$targetUrl = isset($_GET['url']) ? $_GET['url'] : '';
if (empty($targetUrl) || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    die("URL de manifesto (.m3u8) inválida ou não fornecida.");
}

$urlParts = parse_url($targetUrl);
if (!isset($urlParts['host']) || $urlParts['host'] !== ALLOWED_DOMAIN) {
    http_response_code(403);
    die("Acesso negado. Este proxy só funciona para o domínio permitido.");
}

$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Referer: https://' . ALLOWED_DOMAIN . '/'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$content = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode >= 200 && $httpcode < 300) {
    header('Access-Control-Allow-Origin: *'); 
    header('Content-Type: application/vnd.apple.mpegurl');
    echo $content;
} else {
    http_response_code(502);
    die("Falha ao buscar o conteúdo do anroll.net. Status: " . $httpcode);
}
?>