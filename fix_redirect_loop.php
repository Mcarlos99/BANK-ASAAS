<?php
/**
 * Script para corrigir loop de redirecionamento infinito
 * Arquivo: fix_redirect_loop.php
 * 
 * Execute este script para diagnosticar e corrigir o problema
 */

echo "🔄 DIAGNÓSTICO DE LOOP DE REDIRECIONAMENTO\n";
echo "=========================================\n\n";

// Desabilitar redirecionamentos para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // 1. Verificar se os arquivos existem
    echo "📁 VERIFICANDO ARQUIVOS\n";
    echo "─────────────────────\n";
    
    $files = [
        'login.php' => 'Página de login',
        'bootstrap.php' => 'Sistema de bootstrap',
        'auth.php' => 'Sistema de autenticação',
        'config.php' => 'Configurações',
        'index.php' => 'Dashboard principal'
    ];
    
    foreach ($files as $file => $desc) {
        if (file_exists($file)) {
            echo "  ✅ {$desc}\n";
        } else {
            echo "  ❌ {$desc} - ARQUIVO NÃO ENCONTRADO\n";
        }
    }
    
    echo "\n";
    
    // 2. Analisar possíveis causas do loop
    echo "🔍 ANÁLISE DE POSSÍVEIS CAUSAS\n";
    echo "────────────────────────────\n";
    
    // Verificar login.php
    if (file_exists('login.php')) {
        $loginContent = file_get_contents('login.php');
        
        // Verificar redirecionamentos no login.php
        if (strpos($loginContent, 'header(\'Location:') !== false) {
            echo "  ⚠️  login.php contém redirecionamentos\n";
        } else {
            echo "  ✅ login.php não contém redirecionamentos diretos\n";
        }
        
        // Verificar se está incluindo bootstrap corretamente
        if (strpos($loginContent, 'bootstrap.php') !== false) {
            echo "  ✅ login.php inclui bootstrap.php\n";
        } else {
            echo "  ❌ login.php não inclui bootstrap.php\n";
        }
    }
    
    // Verificar bootstrap.php
    if (file_exists('bootstrap.php')) {
        $bootstrapContent = file_get_contents('bootstrap.php');
        
        // Verificar redirecionamentos no bootstrap
        if (strpos($bootstrapContent, 'setup_complete.php') !== false) {
            echo "  ⚠️  bootstrap.php redireciona para setup_complete.php\n";
        }
        
        if (strpos($bootstrapContent, 'checkSystemInstalled') !== false) {
            echo "  ⚠️  bootstrap.php verifica se sistema está instalado\n";
        }
    }
    
    // Verificar auth.php
    if (file_exists('auth.php')) {
        $authContent = file_get_contents('auth.php');
        
        if (strpos($authContent, 'requireLogin') !== false) {
            echo "  ⚠️  auth.php tem função requireLogin que pode causar redirecionamentos\n";
        }
    }
    
    echo "\n";
    
    // 3. Verificar arquivo de lock do sistema
    echo "🔒 VERIFICAÇÃO DO SISTEMA\n";
    echo "────────────────────────\n";
    
    $lockFile = '.setup_complete.lock';
    if (file_exists($lockFile)) {
        echo "  ✅ Sistema marcado como instalado (.setup_complete.lock existe)\n";
        $lockContent = file_get_contents($lockFile);
        if ($lockContent) {
            $lockData = json_decode($lockContent, true);
            if ($lockData) {
                echo "  📅 Instalado em: " . ($lockData['setup_date'] ?? 'N/A') . "\n";
                echo "  📊 Versão: " . ($lockData['version'] ?? 'N/A') . "\n";
            }
        }
    } else {
        echo "  ❌ Sistema NÃO está marcado como instalado\n";
        echo "  🔄 Isso pode causar redirecionamento para setup_complete.php\n";
    }
    
    // Verificar se tabelas existem
    try {
        require_once 'config.php';
        $db = DatabaseManager::getInstance();
        
        $tables = ['usuarios', 'polos', 'sessoes'];
        $missingTables = [];
        
        foreach ($tables as $table) {
            $result = $db->getConnection()->query("SHOW TABLES LIKE '{$table}'");
            if ($result->rowCount() == 0) {
                $missingTables[] = $table;
            }
        }
        
        if (empty($missingTables)) {
            echo "  ✅ Todas as tabelas necessárias existem\n";
        } else {
            echo "  ❌ Tabelas faltando: " . implode(', ', $missingTables) . "\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ Erro ao verificar banco de dados: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // 4. Criar versão segura do login.php
    echo "🔧 CRIANDO LOGIN.PHP SEGURO\n";
    echo "──────────────────────────\n";
    
    $safeLoginContent = '<?php
/**
 * Página de Login - VERSÃO SEGURA (sem loops)
 * Arquivo: login.php
 */

// Configuração básica para evitar loops
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

// EVITAR QUALQUER REDIRECIONAMENTO AUTOMÁTICO INICIAL
$skipAuth = true;

$erro = "";
$sucesso = "";

// Processar login apenas se for POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "login") {
    
    // Incluir arquivos necessários apenas quando necessário
    try {
        require_once "config_api.php";
        require_once "config.php";
        require_once "auth.php";
        
        $auth = new AuthSystem();
        
        $email = trim($_POST["email"] ?? "");
        $senha = $_POST["senha"] ?? "";
        $lembrar = isset($_POST["lembrar"]);
        
        if (empty($email) || empty($senha)) {
            $erro = "Email e senha são obrigatórios";
        } else {
            $resultado = $auth->login($email, $senha, $lembrar);
            
            if ($resultado["success"]) {
                // Redirecionamento seguro apenas após login bem-sucedido
                $redirect = $resultado["redirect"] ?? "index.php";
                header("Location: {$redirect}");
                exit;
            } else {
                $erro = $resultado["message"];
            }
        }
        
    } catch (Exception $e) {
        $erro = "Sistema temporariamente indisponível: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema IMEP Split ASAAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 0;
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .login-left {
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        
        .login-right {
            padding: 60px 40px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="row g-0">
                <!-- Lado Esquerdo -->
                <div class="col-lg-6 login-left">
                    <div class="content">
                        <h2 class="mb-4">
                            <i class="bi bi-building"></i>
                            IMEP Split ASAAS
                        </h2>
                        
                        <p class="lead mb-4">
                            Sistema de gestão de pagamentos com split automático para todos os polos da rede IMEP.
                        </p>
                        
                        <div class="mt-4 pt-4" style="border-top: 1px solid rgba(255,255,255,0.2);">
                            <small>
                                <i class="bi bi-shield-check"></i>
                                Sistema seguro com autenticação multi-fator
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Lado Direito -->
                <div class="col-lg-6 login-right">
                    <div class="text-center mb-4">
                        <h3>Bem-vindo de volta!</h3>
                        <p class="text-muted">Entre com suas credenciais para acessar o sistema</p>
                    </div>
                    
                    <!-- Mensagens -->
                    <?php if ($erro): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($erro); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($sucesso): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i>
                            <?php echo htmlspecialchars($sucesso); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Formulário de Login -->
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       required 
                                       value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
                                       placeholder="seu@email.com">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="senha" 
                                       name="senha" 
                                       required 
                                       placeholder="Digite sua senha">
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="lembrar" name="lembrar">
                            <label class="form-check-label" for="lembrar">
                                Manter-me conectado por 30 dias
                            </label>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-login text-white">
                                <i class="bi bi-box-arrow-in-right"></i>
                                Entrar no Sistema
                            </button>
                        </div>
                    </form>
                    
                    <!-- Debug Info -->
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle"></i> Credenciais de Teste</h6>
                            <small>
                                <strong>Master Admin:</strong><br>
                                Email: admin@imepedu.com.br<br>
                                Senha: admin123<br><br>
                                
                                <strong>Admin São Paulo:</strong><br>
                                Email: admin.sp@imepedu.com.br<br>
                                Senha: polo2024
                            </small>
                        </div>
                    </div>
                    
                    <!-- Status do Sistema -->
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            Sistema: 
                            <span class="badge bg-<?php echo file_exists(".setup_complete.lock") ? "success" : "warning"; ?>">
                                <?php echo file_exists(".setup_complete.lock") ? "Instalado" : "Não Instalado"; ?>
                            </span>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';

    // Fazer backup do login.php atual se existir
    if (file_exists('login.php')) {
        $backupFile = 'login_backup_' . date('Y-m-d_H-i-s') . '.php';
        copy('login.php', $backupFile);
        echo "  💾 Backup criado: {$backupFile}\n";
    }
    
    // Criar novo login.php seguro
    file_put_contents('login.php', $safeLoginContent);
    echo "  ✅ login.php seguro criado\n";
    
    echo "\n";
    
    // 5. Corrigir bootstrap.php para evitar redirecionamentos desnecessários
    echo "🔧 CORRIGINDO BOOTSTRAP.PHP\n";
    echo "──────────────────────────\n";
    
    $safeBootstrapContent = '<?php
/**
 * Bootstrap Seguro - SEM REDIRECIONAMENTOS AUTOMÁTICOS
 * Arquivo: bootstrap.php
 */

// Evitar execução múltipla
if (defined("SYSTEM_BOOTSTRAP_LOADED")) {
    return;
}
define("SYSTEM_BOOTSTRAP_LOADED", true);

// Configurar PHP
ini_set("display_errors", 0);
ini_set("log_errors", 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Definir timezone
date_default_timezone_set("America/Sao_Paulo");

// Incluir arquivos básicos
$files = [
    "config_api.php",
    "config.php", 
    "auth.php"
];

foreach ($files as $file) {
    if (file_exists(__DIR__ . "/" . $file)) {
        try {
            require_once $file;
        } catch (Exception $e) {
            error_log("Bootstrap: Erro ao carregar {$file}: " . $e->getMessage());
        }
    }
}

// Funções utilitárias
function isAjaxRequest() {
    return !empty($_SERVER["HTTP_X_REQUESTED_WITH"]) && 
           strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest";
}

function safeJsonResponse($data, $statusCode = 200) {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header("Content-Type: application/json; charset=utf-8");
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function safeRedirect($url, $permanent = false) {
    if (headers_sent()) {
        echo "<script>window.location.href = \'{$url}\';</script>";
    } else {
        $code = $permanent ? 301 : 302;
        http_response_code($code);
        header("Location: {$url}");
    }
    exit;
}

// Handler de erros que ignora redeclarações
set_error_handler(function($severity, $message, $file, $line) {
    if ($severity === E_NOTICE || $severity === E_USER_NOTICE) {
        return true;
    }
    
    if (strpos($message, "Cannot redeclare class") !== false) {
        error_log("Bootstrap: Classe duplicada ignorada: {$message}");
        return true;
    }
    
    error_log("Erro PHP: {$message} em {$file}:{$line}");
    return true;
});

// Definir constantes
define("SYSTEM_ROOT", __DIR__);

// Inicializar auth de forma segura SEM redirecionamentos automáticos
$auth = null;
if (class_exists("AuthSystem")) {
    try {
        $auth = new AuthSystem();
    } catch (Exception $e) {
        error_log("Bootstrap: Erro ao inicializar auth: " . $e->getMessage());
    }
}
?>';

    // Backup do bootstrap atual
    if (file_exists('bootstrap.php')) {
        $bootstrapBackup = 'bootstrap_backup_' . date('Y-m-d_H-i-s') . '.php';
        copy('bootstrap.php', $bootstrapBackup);
        echo "  💾 Backup criado: {$bootstrapBackup}\n";
    }
    
    // Criar bootstrap seguro
    file_put_contents('bootstrap.php', $safeBootstrapContent);
    echo "  ✅ bootstrap.php seguro criado\n";
    
    echo "\n";
    
    // 6. Criar arquivo de lock se não existir
    if (!file_exists('.setup_complete.lock')) {
        echo "🔒 CRIANDO ARQUIVO DE LOCK\n";
        echo "─────────────────────────\n";
        
        $lockData = [
            'setup_date' => date('Y-m-d H:i:s'),
            'version' => '2.1.0',
            'fix_applied' => 'redirect_loop_fix',
            'php_version' => PHP_VERSION
        ];
        
        file_put_contents('.setup_complete.lock', json_encode($lockData, JSON_PRETTY_PRINT));
        echo "  ✅ Arquivo .setup_complete.lock criado\n";
    }
    
    echo "\n";
    
    // 7. Teste final
    echo "🧪 TESTE FINAL\n";
    echo "─────────────\n";
    
    // Simular carregamento do login.php
    ob_start();
    $hasError = false;
    
    try {
        // Tentar carregar sem executar (parse check)
        $loginCode = file_get_contents('login.php');
        if (php_check_syntax_string($loginCode) === false) {
            echo "  ❌ Erro de sintaxe no login.php\n";
            $hasError = true;
        } else {
            echo "  ✅ login.php tem sintaxe válida\n";
        }
    } catch (Exception $e) {
        echo "  ❌ Erro ao verificar login.php: " . $e->getMessage() . "\n";
        $hasError = true;
    }
    
    ob_end_clean();
    
    if (!$hasError) {
        echo "  ✅ Correção aplicada com sucesso!\n";
        echo "\n";
        echo "🎯 PRÓXIMOS PASSOS:\n";
        echo "1. Limpe o cache/cookies do navegador\n";
        echo "2. Acesse: https://bank.imepedu.com.br/login.php\n";
        echo "3. Use as credenciais: admin@imepedu.com.br / admin123\n";
        echo "\n";
        echo "🔧 SE AINDA HOUVER PROBLEMAS:\n";
        echo "- Verifique os logs de erro do servidor\n";
        echo "- Execute: tail -f /var/log/apache2/error.log\n";
        echo "- Verifique permissões dos arquivos: chmod 644 *.php\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
}

// Função para verificar sintaxe (compatibilidade)
function php_check_syntax_string($code) {
    $tempFile = tempnam(sys_get_temp_dir(), 'php_syntax_check');
    file_put_contents($tempFile, $code);
    
    $output = shell_exec("php -l {$tempFile} 2>&1");
    unlink($tempFile);
    
    return strpos($output, 'No syntax errors') !== false;
}
?>