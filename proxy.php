<?php
/**
 * proxy.php - Proxy HLS seguro com suporte a Referer, CORS e reescrita de playlist .m3u8
 *
 * Recebe via GET o parâmetro url (URL do .m3u8 ou segmento .ts)
 * Faz a requisição remota simulando navegador (User-Agent + Referer)
 * Reescreve URLs da playlist para apontar para este proxy
 * Define CORS somente para origens confiáveis
 */

// --- CONFIGURAÇÕES ---

// Origens confiáveis para CORS (origem das páginas que usarão o proxy)
$allowed_origins = [
    'http://localhost',
    'http://127.0.0.1',
    'http://subarashi.free.nf',
    'https://subarashi.free.nf', // Sua origem principal
    // Adicione mais origens confiáveis aqui, se necessário
];

// Domínios permitidos para vídeo e playlist
$allowed_video_sources = [
    // Domínios de CDN e streaming identificados e de confiança:
    'cdn-zenitsu-2-gamabunta.b-cdn.net', // Domínio principal descoberto
    
    // Domínios `.cloud` que você identificou e que são necessários:
    'c1.vidroll.cloud',
    'c2.vidroll.cloud',
    'c3.vidroll.cloud',
    'c4.vidroll.cloud',
    'c5.vidroll.cloud',
    'c6.vidroll.cloud',
    'c7.vidroll.cloud',
    'c8.vidroll.cloud',
    'c9.vidroll.cloud',
    'c10.vidroll.cloud',
    'c11.vidroll.cloud',
    'c12.vidroll.cloud',
    'c13.vidroll.cloud',
    'c14.vidroll.cloud',
    'c15.vidroll.cloud',
    'c16.vidroll.cloud',
    'c17.vidroll.cloud',
    'c18.vidroll.cloud',
    'c19.vidroll.cloud',
    'c20.vidroll.cloud',
    'c21.vidroll.cloud',
    'c22.vidroll.cloud',
    'c23.vidroll.cloud',
    'c24.vidroll.cloud',
    'c25.vidroll.cloud',
    'c26.vidroll.cloud',
    'c27.vidroll.cloud',
    'c28.vidroll.cloud',
    'c29.vidroll.cloud',
    'c30.vidroll.cloud',
    
    // Se você identificou outros domínios `.cloud` ou de outros TLDs
    // que não estejam listados acima e são necessários, adicione-os aqui:
    // 'meu-outro-cdn.cloud',
    // 'cdn-legal.net',
];

// --- FUNÇÃO AUXILIAR PARA PHP <8 (endsWith) ---
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

// --- CONTROLE CORS ---

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
}
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");

// Responde OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- VALIDAÇÃO DA URL ---

$targetUrl = $_GET['url'] ?? '';
if (empty($targetUrl)) {
    http_response_code(400);
    exit("Erro: Parâmetro 'url' não fornecido.");
}

$urlParts = parse_url($targetUrl);
$host = $urlParts['host'] ?? '';

if (!$host || !in_array($host, $allowed_video_sources)) {
    http_response_code(403);
    exit("Erro: Acesso ao host '{$host}' não é permitido.");
}

// --- CONFIGURAÇÃO DA REQUISIÇÃO CURL ---

$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'Referer: https://www.anroll.net/',  // se precisar mudar, ajuste aqui
];

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,  // cuidado: false ignora verificação SSL
    CURLOPT_TIMEOUT => 20,
]);

$content = curl_exec($ch);

$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

curl_close($ch);

if ($httpcode < 200 || $httpcode >= 300 || $content === false) {
    http_response_code(502);
    exit("Erro ao buscar conteúdo remoto ({$httpcode})");
}

// --- TRATAMENTO DO CONTEÚDO ---

if (str_ends_with($targetUrl, '.m3u8')) {
    $baseUrl = substr($targetUrl, 0, strrpos($targetUrl, '/') + 1);

    // Este trecho já está configurado para usar HTTPS se o servidor estiver em HTTPS
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $proxyBase = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/proxy.php';

    $content = preg_replace_callback('/^(?!#)(.*)$/m', function ($matches) use ($baseUrl, $proxyBase) {
        $line = trim($matches[1]);
        if (!preg_match('/^https?:\/\//', $line)) {
            $line = $baseUrl . $line;
        }
        return $proxyBase . '?url=' . urlencode($line);
    }, $content);

    header('Content-Type: application/vnd.apple.mpegurl');
} else {
    header('Content-Type: ' . ($contentType ?: 'application/octet-stream'));
}

// --- SAÍDA FINAL ---

echo $content;
exit;
