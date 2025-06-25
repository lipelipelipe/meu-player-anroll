<?php
// proxy.php - Versão Final com Geração Dinâmica de Domínios

header('Access-Control-Allow-Origin: *');

// --- Início da Lista de domínios permitidos ---

// Domínios principais e fixos
$allowed_video_sources = [
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
];

// Gera dinamicamente os domínios de c1 até c20 (podemos aumentar se necessário)
for ($i = 1; $i <= 20; $i++) {
    $allowed_video_sources[] = 'c' . $i . '.vidroll.cloud';
}

// --- Fim da Lista ---

$targetUrl = isset($_GET['url']) ? $_GET['url'] : '';
if (empty($targetUrl)) {
    http_response_code(400);
    die("Erro: Parâmetro 'url' não fornecido.");
}

$urlParts = parse_url($targetUrl);
if (!isset($urlParts['host']) || !in_array($urlParts['host'], $allowed_video_sources)) {
    http_response_code(403);
    die("Erro: Acesso à fonte '{$urlParts['host']}' não é permitido pelo proxy.");
}

// Força o Referer a ser o do site anroll.net, que é o que o servidor de destino espera.
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Referer: https://www.anroll.net/'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$content = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpcode >= 200 && $httpcode < 300) {
    if (strpos(strtolower($contentType), 'mpegurl') !== false || strpos(strtolower($targetUrl), '.m3u8') !== false) {
        $base_url = substr($targetUrl, 0, strrpos($targetUrl, '/') + 1);
        $my_proxy_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

        $content = preg_replace_callback(
            '/^(?!#)(.*)$/m',
            function ($matches) use ($base_url, $my_proxy_url) {
                $line = trim($matches[1]);
                if (!preg_match('/^https?:\/\//', $line)) {
                    $line = $base_url . $line;
                }
                return $my_proxy_url . '?url=' . urlencode($line);
            },
            $content
        );
        header('Content-Type: application/vnd.apple.mpegurl');
    } else {
        header('Content-Type: ' . $contentType);
    }
    echo $content;
} else {
    http_response_code(502);
    die("Falha ao buscar conteúdo de: {$targetUrl}. O servidor de origem respondeu com o status: " . $httpcode);
}
?>
