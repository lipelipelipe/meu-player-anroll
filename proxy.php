<?php
// proxy.php - Versão Final Inteligente com reescrita de URL

// Lista de domínios permitidos para buscar o vídeo.
$allowed_video_sources = [
    'www.anroll.net',
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
    'c5.vidroll.cloud', // Adicionando os novos domínios
    'c6.vidroll.cloud'
];

// O endereço do nosso próprio proxy.
$my_proxy_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

header('Access-Control-Allow-Origin: *');

$targetUrl = isset($_GET['url']) ? $_GET['url'] : '';
if (empty($targetUrl)) {
    http_response_code(400); die("URL não fornecida.");
}

$urlParts = parse_url($targetUrl);
if (!isset($urlParts['host']) || !in_array($urlParts['host'], $allowed_video_sources)) {
    http_response_code(403); die("Fonte de vídeo não permitida.");
}

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

$content = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpcode >= 200 && $httpcode < 300) {
    // Verificamos se é um manifesto M3U8 para reescrever as URLs
    if (strpos(strtolower($targetUrl), '.m3u8') !== false) {
        $base_url = substr($targetUrl, 0, strrpos($targetUrl, '/') + 1);
        $lines = explode("\n", $content);
        $new_content = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && $line[0] != '#') {
                // Se a linha não for um link completo, constrói a URL absoluta
                if (substr($line, 0, 4) !== 'http') {
                    $line = $base_url . $line;
                }
                // Reescreve a linha para passar pelo nosso proxy
                $new_content .= $my_proxy_url . '?url=' . urlencode($line) . "\n";
            } else {
                // Mantém as linhas de comentário e diretivas
                $new_content .= $line . "\n";
            }
        }
        $content = $new_content;
    }

    // Envia o cabeçalho correto e o conteúdo (modificado ou não)
    header('Content-Type: ' . $contentType);
    // Para M3U8, é mais seguro forçar o tipo correto
    if (strpos(strtolower($targetUrl), '.m3u8') !== false) {
        header('Content-Type: application/vnd.apple.mpegurl');
    }

    echo $content;

} else {
    http_response_code(502);
    die("Falha ao buscar o conteúdo da fonte. Status: " . $httpcode);
}
?>
