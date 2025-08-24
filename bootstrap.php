<?php
/**
 * Bootstrap do Sistema - CORREÇÃO DE HEADERS
 * Arquivo: bootstrap.php
 * 
 * SUBSTITUA os includes em outros arquivos por este único include
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

// Incluir arquivos na ordem correta para evitar problemas
$requiredFiles = [
    'config_api.php',
    'config.php', 
    'auth.php',
    'asaas_split_system.php'
];

foreach ($requiredFiles as $file) {
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        require_once $filePath;
    } else {
        error_log("Bootstrap: Arquivo não encontrado: {$file}");
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
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-triangle display-1 text-warning"></i>
                        <h4 class="card-title mt-3">' . htmlspecialchars($title) . '</h4>
                        <p class="card-text">' . htmlspecialchars($message) . '</p>
                        <div class="mt-4">
                            <button onclick="history.back()" class="btn btn-secondary me-2">
                                <i class="bi bi-arrow-left"></i> Voltar
                            </button>
                            <a href="login.php" class="btn btn-primary">
                                <i class="bi bi-house"></i> Início
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
    
    echo $html;
    exit;
}

// Handler de erros personalizado
set_error_handler(function($severity, $message, $file, $line) {
    // Não mostrar notices em produção
    if ($severity === E_NOTICE || $severity === E_USER_NOTICE) {
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
        showError('Ocorreu um erro interno no sistema. Tente novamente em alguns instantes.');
    }
});

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

// Auto-redirect para instalação se necessário
if (!checkSystemInstalled() && !checkDatabaseTables()) {
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    $allowedScripts = ['setup_complete.php', 'install.php'];
    
    if (!in_array($currentScript, $allowedScripts)) {
        if (headers_sent()) {
            echo '<script>window.location.href = "setup_complete.php";</script>';
        } else {
            header('Location: setup_complete.php');
        }
        exit;
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

// Log de bootstrap bem-sucedido
error_log("Sistema bootstrap carregado com sucesso - " . basename($_SERVER['SCRIPT_NAME']));
?>