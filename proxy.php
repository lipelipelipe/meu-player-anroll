<?php
// proxy.php - Versão Final Completa e Corrigida

// Lista de domínios permitidos para buscar o vídeo.
$allowed_video_sources = [
    'www.anroll.net',
    'cdn-zenitsu-2-gamabunta.b-cdn.net' 
    // Adicione outros domínios aqui se precisar no futuro.
];

// Permitir acesso de qualquer origem. Mais simples e eficaz para este caso.
header('Access-Control-Allow-Origin: *'); 

// Pega a URL do parâmetro e valida.
$targetUrl = isset($_GET['url']) ? $_GET['url'] : '';
if (empty($targetUrl) || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    die("URL de manifesto (.m3u8) inválida ou não fornecida.");
}

// VERIFICAÇÃO DE SEGURANÇA: Garante que a URL do vídeo é de uma fonte permitida.
$urlParts = parse_url($targetUrl);
if (!isset($urlParts['host']) || !in_array($urlParts['host'], $allowed_video_sources)) {
    http_response_code(403);
    die("Acesso negado. A fonte do vídeo não está na lista de domínios permitidos.");
}

// Cabeçalhos que vamos enviar para o servidor de vídeo
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Referer: https://www.anroll.net/'
];

// Usa cURL para buscar o conteúdo
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
    // -----> A CORREÇÃO CRUCIAL <-----
    // Forçamos o Content-Type correto para HLS, resolvendo o loop infinito.
    header('Content-Type: application/vnd.apple.mpegurl');
    echo $content;
} else {
    http_response_code(502);
    die("Falha ao buscar o conteúdo da fonte. Status: " . $httpcode);
}
?>
