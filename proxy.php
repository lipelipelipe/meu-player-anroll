<?php
// proxy.php - Versão 4.0 FINAL - Lógica seletiva de Referer

$allowed_video_sources = [
    'www.anroll.net',
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
    'c5.vidroll.cloud',
    'c6.vidroll.cloud',
    'c7.vidroll.cloud',
    'c8.vidroll.cloud'
];

$my_proxy_url = 'https://meu-player-anroll.onrender.com/proxy.php';

header('Access-Control-Allow-Origin: *');

$targetUrl = isset($_GET['url']) ? $_GET['url'] : '';
if (empty($targetUrl)) {
    http_response_code(400); 
    die("URL não fornecida.");
}

$urlParts = parse_url($targetUrl);
if (!isset($urlParts['host']) || !in_array($urlParts['host'], $allowed_video_sources)) {
    http_response_code(403); 
    die("Fonte de vídeo não permitida.");
}

// ==============================================================================
// NOVA LÓGICA: SÓ ENVIAMOS O REFERER QUANDO NECESSÁRIO
// ==============================================================================
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
];

// As URLs das imagens de preview contêm "/i2/image/".
// Se a URL NÃO for de uma imagem, nós adicionamos o Referer.
if (strpos($targetUrl, '/i2/image/') === false) {
    $headers[] = 'Referer: https://www.anroll.net/';
}
// ==============================================================================

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$content = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpcode >= 200 && $httpcode < 300) {
    if (strpos(strtolower($targetUrl), '.m3u8') !== false) {
        header('Content-Type: application/vnd.apple.mpegurl');
        $base_url = substr($targetUrl, 0, strrpos($targetUrl, '/') + 1);
        $lines = explode("\n", $content);
        $new_content = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && $line[0] != '#') {
                if (substr($line, 0, 4) !== 'http') {
                    $line = $base_url . $line;
                }
                $new_content .= $my_proxy_url . '?url=' . urlencode($line) . "\n";
            } else {
                $new_content .= $line . "\n";
            }
        }

        if (strpos($new_content, '#EXT-X-ENDLIST') === false) {
            $new_content .= "#EXT-X-ENDLIST\n";
        }

        echo $new_content;

    } else {
        header('Content-Type: ' . $contentType);
        echo $content;
    }
} else {
    http_response_code($httpcode > 0 ? $httpcode : 502);
    die("Falha ao buscar o conteúdo da fonte. Status: " . $httpcode);
}
?>
