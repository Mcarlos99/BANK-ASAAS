<?php
/**
 * Bootstrap do Sistema - VERSÃO COMPLETA E MELHORADA
 * Arquivo: bootstrap.php
 * 
 * Sistema de carregamento inteligente e seguro para o IMEP Split ASAAS
 * Uso: require_once 'bootstrap.php'; (no início de cada arquivo)
 */

// Evitar execução múltipla
if (defined('SYSTEM_BOOTSTRAP_LOADED')) {
    return;
}
define('SYSTEM_BOOTSTRAP_LOADED', true);

// Buffer de saída para evitar problemas com headers
ob_start();

// Configurar PHP para melhor performance e segurança
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurar sessão segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    session_start();
}

// Incluir arquivos na ordem correta para evitar problemas de classe duplicada
$requiredFiles = [
    'config_api.php' => [
        'desc' => 'Configurações de API',
        'required' => true,
        'provides' => ['constantes ASAAS']
    ],
    'config.php' => [
        'desc' => 'Configurações do sistema',
        'required' => true,
        'provides' => ['DatabaseManager', 'AsaasConfig', 'SystemStats', 'WalletManager']
    ],
    'asaas_split_system.php' => [
        'desc' => 'Sistema ASAAS',
        'required' => true,
        'provides' => ['AsaasSplitPayment', 'ExampleUsage', 'WebhookHandler']
    ],
    'auth.php' => [
        'desc' => 'Sistema de autenticação',
        'required' => true,
        'provides' => ['AuthSystem', 'PoloManager']
    ],
    'config_manager.php' => [
        'desc' => 'Gerenciador de configurações',
        'required' => false,
        'provides' => ['ConfigManager', 'DynamicAsaasConfig']
    ]
];

$loadedFiles = [];
$errors = [];
$warnings = [];

// Handler de erros personalizado que ignora redeclarações
set_error_handler(function($severity, $message, $file, $line) {
    // Não mostrar notices em produção
    if ($severity === E_NOTICE || $severity === E_USER_NOTICE) {
        return true;
    }
    
    // Tratar erros de classe já declarada de forma especial
    if (strpos($message, 'Cannot redeclare class') !== false) {
        error_log("Bootstrap: Classe duplicada detectada e ignorada: {$message}");
        return true;
    }
    
    // Tratar outros erros comuns
    if (strpos($message, 'already defined') !== false) {
        error_log("Bootstrap: Constante/função duplicada ignorada: {$message}");
        return true;
    }
    
    $errorMsg = "Erro PHP: {$message} em {$file}:{$line}";
    error_log($errorMsg);
    
    // Se for AJAX, retornar JSON
    if (isAjaxRequest()) {
        safeJsonResponse([
            'success' => false,
            'error' => 'Erro interno do sistema',
            'debug' => defined('DEBUG') && DEBUG ? $errorMsg : null
        ], 500);
    }
    
    return true;
});

// Handler de exceções não capturadas
set_exception_handler(function($exception) {
    $errorMsg = "Exceção não capturada: " . $exception->getMessage() . " em " . $exception->getFile() . ":" . $exception->getLine();
    error_log($errorMsg);
    
    if (isAjaxRequest()) {
        safeJsonResponse([
            'success' => false,
            'error' => 'Erro interno do sistema',
            'debug' => defined('DEBUG') && DEBUG ? $errorMsg : null
        ], 500);
    } else {
        showError('Ocorreu um erro interno no sistema. Tente novamente em alguns instantes.', 'Erro do Sistema');
    }
});

// Incluir arquivos de forma segura e inteligente
foreach ($requiredFiles as $file => $config) {
    $filePath = __DIR__ . '/' . $file;
    
    if (file_exists($filePath)) {
        try {
            $realPath = realpath($filePath);
            
            // Verificar se já foi incluído para evitar redeclarações
            if (!in_array($realPath, $loadedFiles)) {
                
                // Log de início do carregamento
                error_log("Bootstrap: Carregando {$config['desc']} ({$file})");
                
                require_once $filePath;
                $loadedFiles[] = $realPath;
                
                // Verificar se as classes esperadas foram carregadas
                if (isset($config['provides'])) {
                    foreach ($config['provides'] as $class) {
                        if (!class_exists($class) && !function_exists($class) && !defined($class)) {
                            $warnings[] = "Classe/função/constante '{$class}' não encontrada após carregar {$file}";
                        }
                    }
                }
                
                error_log("Bootstrap: {$config['desc']} carregado com sucesso");
                
            } else {
                error_log("Bootstrap: {$file} já foi carregado anteriormente");
            }
            
        } catch (Error $e) {
            if (strpos($e->getMessage(), 'Cannot redeclare class') !== false) {
                $warnings[] = "Classe duplicada ignorada em {$file}: " . $e->getMessage();
                error_log("Bootstrap: Classe duplicada ignorada em {$file}: " . $e->getMessage());
            } else {
                $errors[] = "Erro ao carregar {$file}: " . $e->getMessage();
                error_log("Bootstrap: Erro ao carregar {$file}: " . $e->getMessage());
            }
        } catch (Exception $e) {
            $errors[] = "Exceção ao carregar {$file}: " . $e->getMessage();
            error_log("Bootstrap: Exceção ao carregar {$file}: " . $e->getMessage());
        }
    } else {
        if ($config['required']) {
            $errors[] = "Arquivo obrigatório não encontrado: {$file}";
            error_log("Bootstrap: Arquivo obrigatório não encontrado: {$file}");
        } else {
            $warnings[] = "Arquivo opcional não encontrado: {$file}";
            error_log("Bootstrap: Arquivo opcional não encontrado: {$file}");
        }
    }
}

// Função utilitária para verificar se é AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Função para resposta JSON segura
function safeJsonResponse($data, $statusCode = 200) {
    // Limpar qualquer output anterior
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Configurar headers se ainda não foram enviados
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Função para redirect seguro
function safeRedirect($url, $permanent = false) {
    if (headers_sent()) {
        // Se headers já foram enviados, usar JavaScript
        echo "<script>window.location.href = '{$url}';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url={$url}'></noscript>";
    } else {
        // Headers ainda não enviados, usar PHP
        $code = $permanent ? 301 : 302;
        http_response_code($code);
        header("Location: {$url}");
    }
    exit;
}

// Função para mostrar erro de forma amigável
function showError($message, $title = "Erro no Sistema") {
    // Limpar buffer se necessário
    if (ob_get_level()) {
        ob_clean();
    }
    
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .error-container { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="error-container p-5 text-center">
                    <i class="bi bi-exclamation-triangle display-1 text-warning mb-3"></i>
                    <h3 class="mb-3">' . htmlspecialchars($title) . '</h3>
                    <p class="text-muted mb-4">' . htmlspecialchars($message) . '</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button onclick="history.back()" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </button>
                        <a href="login.php" class="btn btn-primary">
                            <i class="bi bi-house"></i> Início
                        </a>
                    </div>
                    <hr class="my-4">
                    <small class="text-muted">Sistema IMEP Split ASAAS v2.1 • ' . date('Y-m-d H:i:s') . '</small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
    
    echo $html;
    exit;
}

// Verificar se sistema está instalado
function checkSystemInstalled() {
    $lockFile = __DIR__ . '/.setup_complete.lock';
    return file_exists($lockFile);
}

// Função para verificar se tabelas existem
function checkDatabaseTables() {
    if (!class_exists('DatabaseManager')) {
        return false;
    }
    
    try {
        $db = DatabaseManager::getInstance();
        $tables = ['usuarios', 'polos', 'sessoes'];
        
        foreach ($tables as $table) {
            $result = $db->getConnection()->query("SHOW TABLES LIKE '{$table}'");
            if ($result->rowCount() == 0) {
                return false;
            }
        }
        return true;
    } catch (Exception $e) {
        error_log("Bootstrap: Erro ao verificar tabelas: " . $e->getMessage());
        return false;
    }
}

// Função para verificar saúde do sistema
function systemHealthCheck() {
    $status = [
        'database' => false,
        'auth_system' => false,
        'asaas_config' => false,
        'required_tables' => false,
        'file_permissions' => false
    ];
    
    // Verificar banco de dados
    if (class_exists('DatabaseManager')) {
        try {
            $db = DatabaseManager::getInstance();
            $db->getConnection()->query("SELECT 1");
            $status['database'] = true;
        } catch (Exception $e) {
            error_log("Bootstrap Health: Database error: " . $e->getMessage());
        }
    }
    
    // Verificar sistema de autenticação
    if (class_exists('AuthSystem')) {
        $status['auth_system'] = true;
    }
    
    // Verificar configuração ASAAS
    if (class_exists('AsaasConfig')) {
        $status['asaas_config'] = true;
    }
    
    // Verificar tabelas
    $status['required_tables'] = checkDatabaseTables();
    
    // Verificar permissões de arquivos
    $status['file_permissions'] = is_writable(__DIR__ . '/logs') || is_writable(__DIR__);
    
    return $status;
}

// Auto-redirect para instalação se necessário (REMOVIDO para evitar loops)
// Apenas log se sistema não estiver instalado
if (!checkSystemInstalled() && !checkDatabaseTables()) {
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    $allowedScripts = ['setup_complete.php', 'install.php', 'login.php'];
    
    if (!in_array($currentScript, $allowedScripts)) {
        error_log("Bootstrap: Sistema não instalado - acesso a {$currentScript} pode falhar");
    }
}

// Limpar buffer se necessário
if (ob_get_level() > 1) {
    ob_end_clean();
}

// Definir constantes úteis
define('SYSTEM_ROOT', __DIR__);
define('SYSTEM_URL', 
    (isset($_SERVER['HTTPS']) ? 'https' : 'http') . 
    '://' . $_SERVER['HTTP_HOST'] . 
    dirname($_SERVER['SCRIPT_NAME'])
);

// Constantes de versão
define('SYSTEM_VERSION', '2.1.0');
define('SYSTEM_NAME', 'IMEP Split ASAAS Multi-Tenant');
define('BOOTSTRAP_LOADED_AT', microtime(true));

// Instanciar sistema de autenticação de forma segura
$auth = null;
if (class_exists('AuthSystem')) {
    try {
        $auth = new AuthSystem();
        error_log("Bootstrap: AuthSystem inicializado com sucesso");
    } catch (Exception $e) {
        error_log("Bootstrap: Erro ao inicializar AuthSystem: " . $e->getMessage());
        $errors[] = "Erro ao inicializar sistema de autenticação: " . $e->getMessage();
    }
} else {
    $warnings[] = "Classe AuthSystem não encontrada";
    error_log("Bootstrap: AuthSystem não está disponível");
}

// Instanciar gerenciador de configurações de forma segura
$configManager = null;
if (class_exists('ConfigManager')) {
    try {
        $configManager = new ConfigManager();
        error_log("Bootstrap: ConfigManager inicializado com sucesso");
    } catch (Exception $e) {
        error_log("Bootstrap: Erro ao inicializar ConfigManager: " . $e->getMessage());
        $warnings[] = "Erro ao inicializar gerenciador de configurações: " . $e->getMessage();
    }
}

// Função para obter status do bootstrap
function getBootstrapStatus() {
    global $loadedFiles, $errors, $warnings;
    
    return [
        'version' => SYSTEM_VERSION,
        'loaded_at' => BOOTSTRAP_LOADED_AT,
        'loaded_files' => count($loadedFiles),
        'errors' => count($errors),
        'warnings' => count($warnings),
        'health' => systemHealthCheck(),
        'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
        'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB'
    ];
}

// Log de erros críticos se houver
if (!empty($errors)) {
    error_log("Bootstrap: Erros críticos encontrados: " . implode('; ', $errors));
    
    // Se for requisição AJAX e há erros críticos, retornar erro
    if (isAjaxRequest() && count($errors) > 2) {
        safeJsonResponse([
            'success' => false,
            'error' => 'Sistema com problemas de configuração',
            'details' => $errors
        ], 500);
    }
}

// Log de bootstrap bem-sucedido
$bootstrapTime = round((microtime(true) - BOOTSTRAP_LOADED_AT) * 1000, 2);
error_log("Bootstrap: Sistema carregado com sucesso em {$bootstrapTime}ms - " . 
          basename($_SERVER['SCRIPT_NAME']) . 
          " - Arquivos: " . count($loadedFiles) . 
          " - Erros: " . count($errors) . 
          " - Avisos: " . count($warnings));

// Debug info para desenvolvimento (apenas se DEBUG estiver definido)
if (defined('DEBUG') && DEBUG) {
    $debug = [
        'bootstrap' => getBootstrapStatus(),
        'loaded_files' => array_map('basename', $loadedFiles),
        'errors' => $errors,
        'warnings' => $warnings
    ];
    
    // Salvar debug em arquivo se possível
    if (is_writable(__DIR__)) {
        file_put_contents(__DIR__ . '/bootstrap_debug.json', json_encode($debug, JSON_PRETTY_PRINT));
    }
}
?>