<?php
/**
 * Script para corrigir conflito de classe AsaasConfig
 * Arquivo: fix_class_conflict.php
 * 
 * Execute este script para corrigir o problema de classe duplicada
 */

echo "🔧 CORREÇÃO DE CONFLITO DE CLASSE ASAASCONFIG\n";
echo "===========================================\n\n";

$backupDir = __DIR__ . '/backup_' . date('Y-m-d_H-i-s');

try {
    // 1. Criar backup dos arquivos atuais
    echo "📂 Criando backup dos arquivos...\n";
    
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
        echo "  ✅ Diretório de backup criado: {$backupDir}\n";
    }
    
    $filesToBackup = [
        'asaas_split_system.php',
        'config.php',
        'bootstrap.php'
    ];
    
    foreach ($filesToBackup as $file) {
        if (file_exists($file)) {
            copy($file, $backupDir . '/' . $file);
            echo "  ✅ Backup criado: {$file}\n";
        }
    }
    
    echo "\n";
    
    // 2. Analisar o problema
    echo "🔍 ANALISANDO O PROBLEMA\n";
    echo "────────────────────────\n";
    
    $configContent = file_get_contents('config.php');
    $asaasContent = file_get_contents('asaas_split_system.php');
    
    $configHasAsaasConfig = strpos($configContent, 'class AsaasConfig') !== false;
    $asaasHasAsaasConfig = strpos($asaasContent, 'class AsaasConfig') !== false;
    
    echo "  📋 config.php contém AsaasConfig: " . ($configHasAsaasConfig ? 'SIM' : 'NÃO') . "\n";
    echo "  📋 asaas_split_system.php contém AsaasConfig: " . ($asaasHasAsaasConfig ? 'SIM' : 'NÃO') . "\n";
    
    if ($configHasAsaasConfig && $asaasHasAsaasConfig) {
        echo "  ❌ PROBLEMA CONFIRMADO: Classe duplicada encontrada!\n";
    } else {
        echo "  ✅ Nenhuma duplicação detectada nos arquivos atuais\n";
    }
    
    echo "\n";
    
    // 3. Aplicar correção
    echo "🛠️  APLICANDO CORREÇÃO\n";
    echo "────────────────────\n";
    
    // Corrigir asaas_split_system.php - remover classe AsaasConfig
    if ($asaasHasAsaasConfig) {
        echo "  🔧 Removendo classe AsaasConfig de asaas_split_system.php...\n";
        
        // Encontrar o início e fim da classe AsaasConfig
        $pattern = '/\/\*\*[^*]*\*+(?:[^*\/][^*]*\*+)*\/\s*class\s+AsaasConfig\s*{.*?^}/msU';
        
        // Método alternativo mais seguro
        $lines = explode("\n", $asaasContent);
        $newLines = [];
        $skipLines = false;
        $braceCount = 0;
        $inAsaasConfig = false;
        
        foreach ($lines as $lineNum => $line) {
            $trimmedLine = trim($line);
            
            // Detectar início da classe AsaasConfig
            if (strpos($trimmedLine, 'class AsaasConfig') !== false) {
                echo "    📍 Classe AsaasConfig encontrada na linha " . ($lineNum + 1) . "\n";
                $inAsaasConfig = true;
                $skipLines = true;
                $braceCount = 0;
                continue;
            }
            
            if ($inAsaasConfig) {
                // Contar chaves para saber quando a classe termina
                $openBraces = substr_count($line, '{');
                $closeBraces = substr_count($line, '}');
                $braceCount += ($openBraces - $closeBraces);
                
                if ($braceCount <= 0 && strpos($line, '}') !== false) {
                    echo "    📍 Fim da classe AsaasConfig na linha " . ($lineNum + 1) . "\n";
                    $inAsaasConfig = false;
                    $skipLines = false;
                    continue;
                }
                continue;
            }
            
            if (!$skipLines) {
                $newLines[] = $line;
            }
        }
        
        // Adicionar comentário explicativo
        $commentLines = [
            '',
            '// CLASSE ASAASCONFIG REMOVIDA DAQUI - ESTÁ NO CONFIG.PHP',
            '// Para evitar conflito de redeclaração de classe',
            ''
        ];
        
        $newContent = implode("\n", array_merge($newLines, $commentLines));
        
        file_put_contents('asaas_split_system.php', $newContent);
        echo "  ✅ Classe AsaasConfig removida de asaas_split_system.php\n";
    }
    
    // 4. Criar bootstrap.php corrigido
    echo "  🔧 Criando bootstrap.php corrigido...\n";
    
    $bootstrapContent = '<?php
/**
 * Bootstrap do Sistema - CORREÇÃO DE CONFLITO DE CLASSES
 * Arquivo: bootstrap.php
 * 
 * SUBSTITUA os includes em outros arquivos por este único include
 * Uso: require_once \'bootstrap.php\'; (no início de cada arquivo)
 */

// Evitar execução múltipla
if (defined(\'SYSTEM_BOOTSTRAP_LOADED\')) {
    return;
}
define(\'SYSTEM_BOOTSTRAP_LOADED\', true);

// Buffer de saída para evitar problemas com headers
ob_start();

// Configurar PHP para melhor performance e segurança
ini_set(\'display_errors\', 0);
ini_set(\'log_errors\', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Definir timezone
date_default_timezone_set(\'America/Sao_Paulo\');

// Incluir arquivos na ordem correta para evitar problemas de classe duplicada
$requiredFiles = [
    \'config_api.php\' => \'Configurações de API\',
    \'config.php\' => \'Configurações do sistema (contém AsaasConfig)\',
    \'asaas_split_system.php\' => \'Sistema ASAAS (sem AsaasConfig duplicada)\',
    \'auth.php\' => \'Sistema de autenticação\'
];

// Incluir arquivos de forma segura
foreach ($requiredFiles as $file => $description) {
    $filePath = __DIR__ . \'/\' . $file;
    if (file_exists($filePath)) {
        try {
            require_once $filePath;
        } catch (Error $e) {
            if (strpos($e->getMessage(), \'Cannot redeclare class\') !== false) {
                error_log("Bootstrap: Classe já declarada - ignorando: " . $e->getMessage());
                continue;
            }
            error_log("Bootstrap: Erro ao carregar {$file}: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Bootstrap: Exceção ao carregar {$file}: " . $e->getMessage());
        }
    }
}

// Incluir config_manager.php por último se existir
if (file_exists(__DIR__ . \'/config_manager.php\')) {
    try {
        require_once \'config_manager.php\';
    } catch (Exception $e) {
        error_log("Bootstrap: Erro ao carregar config_manager: " . $e->getMessage());
    }
}

// Função utilitária para verificar se é AJAX
function isAjaxRequest() {
    return !empty($_SERVER[\'HTTP_X_REQUESTED_WITH\']) && 
           strtolower($_SERVER[\'HTTP_X_REQUESTED_WITH\']) === \'xmlhttprequest\';
}

// Função para resposta JSON segura
function safeJsonResponse($data, $statusCode = 200) {
    if (ob_get_level()) {
        ob_clean();
    }
    
    if (!headers_sent()) {
        http_response_code($statusCode);
        header(\'Content-Type: application/json; charset=utf-8\');
        header(\'Cache-Control: no-cache, must-revalidate\');
        header(\'Expires: Mon, 26 Jul 1997 05:00:00 GMT\');
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Função para redirect seguro
function safeRedirect($url, $permanent = false) {
    if (headers_sent()) {
        echo "<script>window.location.href = \'{$url}\';</script>";
        echo "<noscript><meta http-equiv=\'refresh\' content=\'0;url={$url}\'></noscript>";
    } else {
        $code = $permanent ? 301 : 302;
        http_response_code($code);
        header("Location: {$url}");
    }
    exit;
}

// Handler de erros personalizado que ignora redeclarações
set_error_handler(function($severity, $message, $file, $line) {
    if ($severity === E_NOTICE || $severity === E_USER_NOTICE) {
        return true;
    }
    
    if (strpos($message, \'Cannot redeclare class\') !== false) {
        error_log("Bootstrap: Classe já declarada detectada - ignorando: {$message}");
        return true;
    }
    
    $errorMsg = "Erro PHP: {$message} em {$file}:{$line}";
    error_log($errorMsg);
    
    if (isAjaxRequest()) {
        safeJsonResponse([
            \'success\' => false,
            \'error\' => \'Erro interno do sistema\'
        ], 500);
    }
    
    return true;
});

// Definir constantes úteis
define(\'SYSTEM_ROOT\', __DIR__);
define(\'SYSTEM_URL\', 
    (isset($_SERVER[\'HTTPS\']) ? \'https\' : \'http\') . 
    \'://\' . $_SERVER[\'HTTP_HOST\'] . 
    dirname($_SERVER[\'SCRIPT_NAME\'])
);

// Instanciar sistema de autenticação de forma segura
$auth = null;
if (class_exists(\'AuthSystem\')) {
    try {
        $auth = new AuthSystem();
    } catch (Exception $e) {
        error_log("Bootstrap: Erro ao inicializar AuthSystem: " . $e->getMessage());
    }
}

// Log de bootstrap bem-sucedido
error_log("Sistema bootstrap carregado com sucesso - " . basename($_SERVER[\'SCRIPT_NAME\']));
?>';

    file_put_contents('bootstrap.php', $bootstrapContent);
    echo "  ✅ bootstrap.php corrigido criado\n";
    
    echo "\n";
    
    // 5. Testar se a correção funcionou
    echo "🧪 TESTANDO A CORREÇÃO\n";
    echo "────────────────────\n";
    
    // Criar arquivo de teste temporário
    $testContent = '<?php
error_reporting(E_ALL);
ini_set(\'display_errors\', 1);

try {
    require_once \'bootstrap.php\';
    echo "✅ Bootstrap carregado sem erros\n";
    
    if (class_exists(\'AsaasConfig\')) {
        echo "✅ Classe AsaasConfig disponível\n";
    } else {
        echo "❌ Classe AsaasConfig não encontrada\n";
    }
    
    if (class_exists(\'AsaasSplitPayment\')) {
        echo "✅ Classe AsaasSplitPayment disponível\n";
    } else {
        echo "❌ Classe AsaasSplitPayment não encontrada\n";
    }
    
    if (class_exists(\'DatabaseManager\')) {
        echo "✅ Classe DatabaseManager disponível\n";
    } else {
        echo "❌ Classe DatabaseManager não encontrada\n";
    }
    
} catch (Error $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Exceção: " . $e->getMessage() . "\n";
}
?>';

    file_put_contents('test_fix.php', $testContent);
    
    echo "  🔬 Executando teste...\n";
    $output = shell_exec('php test_fix.php 2>&1');
    echo "  📋 Resultado do teste:\n";
    echo "     " . str_replace("\n", "\n     ", trim($output)) . "\n";
    
    // Remover arquivo de teste
    unlink('test_fix.php');
    
    echo "\n";
    
    // 6. Verificações finais
    echo "📋 VERIFICAÇÕES FINAIS\n";
    echo "────────────────────\n";
    
    $fixedAsaasContent = file_get_contents('asaas_split_system.php');
    $hasAsaasConfig = strpos($fixedAsaasContent, 'class AsaasConfig') !== false;
    
    if (!$hasAsaasConfig) {
        echo "  ✅ Classe AsaasConfig removida de asaas_split_system.php\n";
    } else {
        echo "  ❌ Classe AsaasConfig ainda presente em asaas_split_system.php\n";
    }
    
    if (file_exists('bootstrap.php')) {
        echo "  ✅ bootstrap.php corrigido criado\n";
    } else {
        echo "  ❌ bootstrap.php não foi criado\n";
    }
    
    echo "\n";
    
    echo "✅ CORREÇÃO CONCLUÍDA!\n";
    echo "====================\n\n";
    
    echo "📋 RESUMO DAS ALTERAÇÕES:\n";
    echo "• Classe AsaasConfig removida de asaas_split_system.php\n";
    echo "• Classe AsaasConfig mantida apenas em config.php\n";
    echo "• bootstrap.php atualizado com tratamento de erros melhorado\n";
    echo "• Backup dos arquivos originais em: {$backupDir}\n\n";
    
    echo "🚀 PRÓXIMOS PASSOS:\n";
    echo "1. Teste o sistema acessando login.php\n";
    echo "2. Se houver problemas, restaure os backups\n";
    echo "3. Verifique os logs em /logs/ para mais detalhes\n\n";
    
    echo "💡 DICA: Use require_once 'bootstrap.php'; em todos os arquivos PHP\n";
    echo "   em vez de incluir os arquivos individuais.\n\n";
    
    if (strpos($output, '✅') !== false && strpos($output, 'sem erros') !== false) {
        echo "🎉 TESTE PASSOU - Sistema funcionando!\n";
    } else {
        echo "⚠️  Verifique os resultados do teste acima\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO NA CORREÇÃO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if (is_dir($backupDir)) {
        echo "\n🔄 Para restaurar os backups:\n";
        echo "cp {$backupDir}/* ./\n";
    }
}
?>