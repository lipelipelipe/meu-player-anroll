<?php
// proxy.php

// Permitir que qualquer origem acesse este proxy (para testes)
// EM PRODUÇÃO, RESTRINJA ISSO AO DOMÍNIO DO SEU PLAYER!
// Ex: header('Access-Control-Allow-Origin: https://meu-player-anroll.onrender.com');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Range, Origin, X-Requested-With, Content-Type, Accept');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Lidar com solicitações OPTIONS (preflight) para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

// --- Configuração ---
$requestTimeout = 45; // Segundos para timeout do cURL
// Lista de domínios de CDN permitidos para buscar conteúdo.
// Adicione mais padrões conforme necessário. O curinga '*' corresponde a qualquer caractere.
$allowed_host_patterns = [
    '*.vidroll.cloud',
    '*.b-cdn.net',
    '*.bunnycdn.ru',
    'c5.vidroll.cloud',
    'c6.vidroll.cloud',
    'c7.vidroll.cloud',
    'c8.vidroll.cloud',
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
];
// --- Fim da Configuração ---

$targetResourceUrl = isset($_GET['url']) ? trim($_GET['url']) : null;
$pageRefererUrl = isset($_GET['page_url']) ? trim($_GET['page_url']) : null;

// Validação básica dos parâmetros
if (!$targetResourceUrl) {
    http_response_code(400);
    die(json_encode(['error' => 'Parameter "url" is missing.']));
}
if (!filter_var($targetResourceUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $targetResourceUrl)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid "url" format. Must be a valid HTTP/S URL.']));
}
if ($pageRefererUrl && (!filter_var($pageRefererUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $pageRefererUrl))) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid "page_url" format. Must be a valid HTTP/S URL if provided.']));
}

// Verificar se o host do recurso alvo é permitido
$url_parts_target = parse_url($targetResourceUrl);
$target_host = isset($url_parts_target['host']) ? strtolower($url_parts_target['host']) : '';

$is_allowed = false;
if (empty($allowed_host_patterns)) { // Se a lista estiver vazia, permitir tudo (NÃO RECOMENDADO PARA PRODUÇÃO)
    $is_allowed = true;
} else {
    foreach ($allowed_host_patterns as $pattern) {
        if (fnmatch(strtolower($pattern), $target_host)) {
            $is_allowed = true;
            break;
        }
    }
}

if (!$is_allowed) {
    http_response_code(403); // Forbidden
    die(json_encode(['error' => 'Access to host "' . htmlspecialchars($target_host) . '" is not allowed through this proxy.']));
}

// Inicializar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetResourceUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Seguir redirecionamentos
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, $requestTimeout);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Importante para HTTPS
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);   // Importante para HTTPS
// curl_setopt($ch, CURLOPT_VERBOSE, true); // Descomente para debugging cURL detalhado

// Preparar cabeçalhos para enviar ao servidor de destino
$headersToForward = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
    'Accept: */*',
    'Accept-Language: en-US,en;q=0.9,pt-BR;q=0.8,pt;q=0.7'
];

// Definir Referer e Origin com base na page_url fornecida
$effectiveReferer = null;
if ($pageRefererUrl) {
    $effectiveReferer = $pageRefererUrl;
    $headersToForward[] = 'Referer: ' . $pageRefererUrl;
    $parsedPageUrl = parse_url($pageRefererUrl);
    if (isset($parsedPageUrl['scheme']) && isset($parsedPageUrl['host'])) {
        $headersToForward[] = 'Origin: ' . $parsedPageUrl['scheme'] . '://' . $parsedPageUrl['host'];
    }
} else {
    // Fallback se page_url não for fornecido (pode não funcionar para CDNs restritos)
    if (isset($url_parts_target['scheme']) && isset($url_parts_target['host'])) {
        $effectiveReferer = $url_parts_target['scheme'] . '://' . $url_parts_target['host'] . '/';
        $headersToForward[] = 'Referer: ' . $effectiveReferer;
        // Não definir Origin no fallback, pois pode ser incorreto.
    }
}

// Passar o cabeçalho Range do cliente para o servidor de destino (essencial para seeking)
if (isset($_SERVER['HTTP_RANGE'])) {
    $headersToForward[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headersToForward);

// Executar a requisição cURL
$responseBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlErrorNum = curl_errno($ch);
$curlErrorMsg = curl_error($ch);
curl_close($ch);

// Log (opcional, útil para depuração no Render.com)
// $logMessage = sprintf(
//     "Proxy request: URL=[%s], PageURL=[%s], RefererSent=[%s], Status=[%d], ContentType=[%s], CurlErrNo=[%d], CurlErrMsg=[%s]",
//     $targetResourceUrl,
//     $pageRefererUrl ?: 'N/A',
//     $effectiveReferer ?: 'N/A',
//     $httpCode,
//     $contentType ?: 'N/A',
//     $curlErrorNum,
//     $curlErrorMsg ?: 'N/A'
// );
// error_log($logMessage);


// Tratar erros do cURL
if ($curlErrorNum) {
    http_response_code(502); // Bad Gateway
    die(json_encode(['error' => 'cURL Error (' . $curlErrorNum . '): ' . htmlspecialchars($curlErrorMsg) . ' when fetching ' . htmlspecialchars($targetResourceUrl)]));
}

// Tratar códigos de status HTTP de erro do servidor de destino
if ($httpCode >= 400) {
    http_response_code($httpCode);
    // Não envie $responseBody diretamente se for um erro HTML do CDN, pois pode conter scripts.
    die(json_encode(['error' => 'Remote server returned status ' . $httpCode . ' for ' . htmlspecialchars($targetResourceUrl)]));
}

// Se for um arquivo M3U8, precisamos reescrever as URLs internas
$isM3U8 = (is_string($contentType) && (strpos(strtolower($contentType), 'mpegurl') !== false || strpos(strtolower($contentType), 'x-mpegurl') !== false)) ||
          (substr(strtolower(parse_url($targetResourceUrl, PHP_URL_PATH)), -5) === '.m3u8');

if ($isM3U8 && $responseBody) {
    $lines = explode("\n", $responseBody);
    $outputLines = [];
    $baseM3U8Url = $targetResourceUrl; // Usado para resolver URLs relativas

    $pageUrlQueryParamForRewrite = '';
    if ($pageRefererUrl) {
        $pageUrlQueryParamForRewrite = '&page_url=' . urlencode($pageRefererUrl);
    }

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (empty($trimmedLine) || strpos($trimmedLine, '#') === 0) {
            $outputLines[] = $line; // Manter comentários e tags M3U8
        } else {
            // É uma URL (segmento .ts ou outro M3U8)
            $segmentOrPlaylistUrl = $trimmedLine;
            $absoluteUrl = '';

            if (strpos($segmentOrPlaylistUrl, '://') !== false) { // Já é absoluta
                $absoluteUrl = $segmentOrPlaylistUrl;
            } else { // URL Relativa, precisa ser resolvida
                $absoluteUrl = resolve_url($baseM3U8Url, $segmentOrPlaylistUrl);
            }
            
            // Reescreve para passar pelo nosso proxy, PROPAGANDO O page_url
            $outputLines[] = 'proxy.php?url=' . urlencode($absoluteUrl) . $pageUrlQueryParamForRewrite;
        }
    }
    $responseBody = implode("\n", $outputLines);
    // Assegura o Content-Type correto para M3U8
    if (!is_string($contentType) || strpos(strtolower($contentType), 'mpegurl') === false) {
         $contentType = 'application/vnd.apple.mpegurl';
    }
}

// Enviar os cabeçalhos corretos e o conteúdo para o cliente
if ($contentType) {
    header('Content-Type: ' . $contentType);
} else {
    // Tenta adivinhar o Content-Type se não foi fornecido (menos comum)
    if ($isM3U8) {
        header('Content-Type: application/vnd.apple.mpegurl');
    } elseif (substr(strtolower(parse_url($targetResourceUrl, PHP_URL_PATH)), -3) === '.ts') {
        header('Content-Type: video/MP2T');
    }
    // Adicione mais tipos se necessário
}

// Outros headers que podem ser úteis para streaming
header('Cache-Control: no-cache, no-store, must-revalidate'); // Evitar cache agressivo no proxy
header('Pragma: no-cache');
header('Expires: 0');
// Content-Length é problemático se modificarmos o conteúdo (M3U8).
// Para segmentos .ts, pode ser útil se o cURL o fornecer e não modificarmos.
// header('Content-Length: ' . strlen($responseBody));

echo $responseBody;
exit;

/**
 * Função básica para resolver uma URL relativa contra uma URL base.
 * @param string $baseUrl A URL base (ex: http://example.com/path/to/file.m3u8)
 * @param string $relativeUrl A URL relativa (ex: ../segment.ts, sub/list.m3u8, /other.m3u8)
 * @return string A URL absoluta resolvida.
 */
function resolve_url($baseUrl, $relativeUrl) {
    if (strpos($relativeUrl, '://') !== false) {
        return $relativeUrl; // Já é absoluta
    }

    $base = parse_url($baseUrl);
    if (!isset($base['path'])) $base['path'] = '/';

    if ($relativeUrl[0] === '/') { // Relativa à raiz do host
        return $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '') . $relativeUrl;
    }

    // Relativa ao diretório atual da URL base
    $path = dirname($base['path']);
    if ($path === '.' || $path === '/') $path = ''; // Evitar /./ ou //

    $fullPath = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
    
    $absPath = $path . '/' . $relativeUrl;

    // Normalizar ".." e "."
    $parts = explode('/', $absPath);
    $absolutes = [];
    foreach ($parts as $part) {
        if ('.' == $part || '' == $part && count($absolutes) > 0) continue; // Ignorar '.' e vazios exceto o primeiro (se for path absoluto)
        if ('..' == $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }
    // Garante que o path comece com / se não for vazio
    $normalizedPath = implode('/', $absolutes);
    if (count($absolutes) > 0 && $absolutes[0] !== '' && $normalizedPath[0] !== '/') {
        $normalizedPath = '/' . $normalizedPath;
    } else if (empty($absolutes)) { // Caso de path/../ se tornar vazio
        $normalizedPath = '/';
    }


    return $fullPath . $normalizedPath;
}

?>
