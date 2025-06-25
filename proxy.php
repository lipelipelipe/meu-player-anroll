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
    'https://subarashi.free.nf',
    // adicione mais origens confiáveis aqui
];

// Domínios permitidos para vídeo e playlist
$allowed_video_sources = [
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
];

// Também adiciona dinamicamente "c1.vidroll.cloud" até "c30.vidroll.cloud"
for ($i = 1; $i <= 30; $i++) {
    $allowed_video_sources[] = "c{$i}.vidroll.cloud";
}

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
    // URL base para resolver caminhos relativos
    $baseUrl = substr($targetUrl, 0, strrpos($targetUrl, '/') + 1);

    // URL absoluta deste proxy para reescrever links na playlist
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $proxyBase = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/proxy.php';

    // Reescreve linhas que NÃO começam com # para apontar para proxy.php
    $content = preg_replace_callback('/^(?!#)(.*)$/m', function ($matches) use ($baseUrl, $proxyBase) {
        $line = trim($matches[1]);
        if (!preg_match('/^https?:\/\//', $line)) {
            // Link relativo, converte para absoluto
            $line = $baseUrl . $line;
        }
        // Reescreve para usar proxy.php?url=...
        return $proxyBase . '?url=' . urlencode($line);
    }, $content);

    header('Content-Type: application/vnd.apple.mpegurl');
} else {
    // Para segmentos .ts, imagens ou outros arquivos, mantém o Content-Type original
    header('Content-Type: ' . ($contentType ?: 'application/octet-stream'));
}

// --- SAÍDA FINAL ---

echo $content;
exit;
