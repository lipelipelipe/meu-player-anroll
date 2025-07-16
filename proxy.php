<?php
/**
 * PHP-HLS Proxy Script (Nível Profissional)
 *
 * Este script atua como um proxy HTTP seguro para streaming HLS (.m3u8) e outros recursos de mídia.
 *
 * Funcionalidades:
 * - Validação rigorosa de URLs para prevenir ataques de SSRF (Server-Side Request Forgery).
 * - Uso da biblioteca cURL para controle total sobre requisições HTTP.
 * - Encaminhamento inteligente de headers essenciais (e.g., Range, User-Agent, Referer).
 * - Análise e reescrita de manifests HLS master e de mídia para que todas as URLs internas
 *   (sub-manifests e segmentos .ts) apontem de volta para este proxy.
 * - Gerenciamento correto de MIME types e status codes HTTP.
 * - Resiliência para downloads de longa duração.
 *
 * @author     Assistente de IA
 * @version    1.1.0
 * @license    MIT
 */

// --- CONFIGURAÇÃO E INICIALIZAÇÃO ---

// Aumenta o tempo de execução do script para 0 (infinito).
// Essencial para o proxy não ser interrompido durante o download de segmentos de vídeo longos.
set_time_limit(0);

// Permite que o script continue rodando mesmo que o cliente desconecte.
// Útil para garantir que o download do segmento seja concluído no servidor, se necessário.
@ignore_user_abort(true);

// --- SEGURANÇA: CONTROLE DE ACESSO E VALIDAÇÃO ---

// Obter a URL remota do parâmetro 'url' na query string.
$remoteUrl = $_GET['url'] ?? null;

// VALIDAÇÃO 1: Verificar se a URL foi fornecida e é sintaticamente válida.
if (!$remoteUrl || !filter_var($remoteUrl, FILTER_VALIDATE_URL)) {
    header("HTTP/1.1 400 Bad Request");
    exit("Erro: Parâmetro 'url' ausente ou inválido.");
}

// VALIDAÇÃO 2: Prevenção de SSRF (Server-Side Request Forgery) - Medida de segurança CRUCIAL.
// Apenas domínios nesta lista de permissões serão acessados.
// Isso impede que seu proxy seja usado para atacar servidores internos ou externos.
$allowedDomains = [
    'cdn-zenitsu-2-gamabunta.b-cdn.net',
    'c5.vidroll.cloud',
    'c6.vidroll.cloud',
    'c7.vidroll.cloud',
    'c8.vidroll.cloud'
];
$remoteHost = parse_url($remoteUrl, PHP_URL_HOST);
if (!in_array(strtolower($remoteHost), $allowedDomains)) {
    header("HTTP/1.1 403 Forbidden");
    exit("Erro: O acesso ao domínio '{$remoteHost}' não é permitido por este proxy.");
}

// --- LÓGICA DO PROXY ---

// Monta a URL base deste próprio script de proxy de forma dinâmica.
// Isso torna o script portável entre diferentes domínios e pastas.
$currentProtocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$yourProxyBaseUrl = "{$currentProtocol}://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}";

// Inicia a sessão cURL, a ferramenta mais robusta para requisições HTTP em PHP.
$ch = curl_init();

// Define os headers que serão encaminhados da requisição do cliente para a requisição remota.
$forwardHeaders = [];
// Coleta os headers da requisição original. `getallheaders` é a preferência.
if (function_exists('getallheaders')) {
    $allHeaders = getallheaders();
} else { // Fallback para ambientes (ex: Nginx com FPM) onde a função não existe.
    $allHeaders = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $allHeaders[$key] = $value;
        }
    }
}

// Filtra e formata os headers a serem encaminhados.
foreach ($allHeaders as $name => $value) {
    $lowerName = strtolower($name);
    // Encaminha headers essenciais para o player e CDN.
    // Ignora headers que devem ser gerenciados pelo cURL (Host) ou que podem causar conflitos (Connection, Accept-Encoding).
    if (in_array($lowerName, ['user-agent', 'referer', 'origin', 'range'])) {
        $forwardHeaders[] = "{$name}: {$value}";
    }
}
// Adiciona um User-Agent padrão se nenhum for fornecido, para evitar ser bloqueado.
if (!isset($allHeaders['User-Agent'])) {
    $forwardHeaders[] = "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36";
}

// Configuração das opções do cURL
curl_setopt($ch, CURLOPT_URL, $remoteUrl);           // A URL de destino.
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);    // Retorna a resposta como string em vez de imprimi-la.
curl_setopt($ch, CURLOPT_HEADER, true);              // Inclui os headers da resposta na saída para podermos analisá-los.
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);    // Segue redirecionamentos HTTP (ex: 301, 302).
curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders); // Define os headers que encaminhamos.
curl_setopt($ch, CURLOPT_TIMEOUT, 60);               // Timeout de 60 segundos para a conexão e resposta.
// Em produção, a verificação SSL deve ser ATIVADA (true). Para testes em alguns ambientes, pode ser necessário desativar.
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

// Executa a requisição cURL.
$response = curl_exec($ch);
if ($response === false) {
    header("HTTP/1.1 502 Bad Gateway");
    exit("Erro ao buscar a URL remota: " . curl_error($ch));
}

// Obtém metadados da resposta cURL.
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

// Separa os headers do corpo da resposta.
$responseHeaders = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

curl_close($ch);

// --- PROCESSAMENTO E ENVIO DA RESPOSTA ---

// Define o status code HTTP da resposta para ser o mesmo que o da requisição remota.
http_response_code($httpCode);

// Define o Content-Type para garantir que o navegador interprete o arquivo corretamente.
header("Content-Type: {$contentType}");

// Lógica de reescrita para arquivos HLS (M3U8).
if (strpos($contentType, 'mpegurl') !== false) {
    // Função auxiliar para resolver URLs relativas para absolutas, baseando-se na URL do manifest.
    function resolve_url($baseUrl, $relativeUrl) {
        if (filter_var($relativeUrl, FILTER_VALIDATE_URL)) {
            return $relativeUrl; // Já é uma URL absoluta.
        }
        $baseParts = parse_url($baseUrl);
        $path = dirname($baseParts['path']) . '/';
        $fullPath = $baseParts['scheme'] . '://' . $baseParts['host'] . $path . $relativeUrl;
        return $fullPath;
    }

    $lines = explode("\n", $body);
    $newBody = [];

    // Processa cada linha do manifest.
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            // Mantém linhas de metadados HLS (começam com #) e linhas vazias.
            $newBody[] = $line;
        } else {
            // Esta é uma linha de URL (segmento .ts ou sub-manifest .m3u8).
            $absoluteInternalUrl = resolve_url($remoteUrl, $line);
            $proxiedInternalUrl = $yourProxyBaseUrl . '?url=' . urlencode($absoluteInternalUrl);
            $newBody[] = $proxiedInternalUrl;
        }
    }
    $finalBody = implode("\n", $newBody);
    
    // Recalcula e define o Content-Length, pois o corpo foi modificado.
    header('Content-Length: ' . strlen($finalBody));
    echo $finalBody;
    
} else {
    // Para todos os outros tipos de arquivo (segmentos .ts, imagens, etc.),
    // simplesmente encaminha o corpo original e o Content-Length.
    header('Content-Length: ' . strlen($body));
    echo $body;
}

exit();
?>