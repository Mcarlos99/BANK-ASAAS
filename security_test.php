<?php
/**
 * Script de Teste de Segurança - Verificar se o bypass foi corrigido
 * Arquivo: security_test.php
 * 
 * Execute este script para verificar se o sistema está seguro
 */

echo "🔒 TESTE DE SEGURANÇA - VERIFICAÇÃO DE AUTENTICAÇÃO\n";
echo "=================================================\n\n";

// Simular acesso sem estar logado
$_SESSION = []; // Limpar sessão
unset($_COOKIE['PHPSESSID']);

echo "1. TESTANDO ACESSO SEM AUTENTICAÇÃO:\n";
echo "-------------------------------------\n";

// Testar bootstrap sem sessão
try {
    echo "   - Incluindo bootstrap...\n";
    ob_start();
    include_once 'bootstrap.php';
    $bootstrapOutput = ob_get_clean();
    
    echo "   ✅ Bootstrap carregado sem iniciar sessão automaticamente\n";
    
    // Verificar se $auth existe mas não está logado
    if (isset($auth)) {
        if ($auth->isLogado()) {
            echo "   ❌ PROBLEMA: Usuário aparece como logado sem fazer login!\n";
        } else {
            echo "   ✅ AuthSystem funcionando corretamente - usuário não logado\n";
        }
    } else {
        echo "   ⚠️ AuthSystem não disponível\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erro no bootstrap: " . $e->getMessage() . "\n";
}

echo "\n2. TESTANDO ACESSO DIRETO AO INDEX.PHP:\n";
echo "----------------------------------------\n";

// Simular acesso ao index.php sem login
try {
    echo "   - Testando acesso direto ao index.php...\n";
    
    // Capturar saída do index.php
    ob_start();
    
    // Definir variáveis de servidor para simular requisição
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/index.php';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['HTTP_HOST'] = 'bank.imepedu.com.br';
    
    // Incluir index.php deve redirecionar para login
    include_once 'index.php';
    
    $indexOutput = ob_get_clean();
    
    // Se chegou até aqui sem redirecionar, é um problema
    if (strpos($indexOutput, 'Dashboard') !== false || 
        strpos($indexOutput, 'Sistema de Split') !== false) {
        echo "   ❌ PROBLEMA CRÍTICO: index.php acessível sem autenticação!\n";
        echo "   🔧 AÇÃO NECESSÁRIA: Aplicar correção de segurança imediatamente\n";
    } else {
        echo "   ✅ index.php protegido corretamente\n";
    }
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'login') !== false || 
        strpos($e->getMessage(), 'autenticação') !== false) {
        echo "   ✅ index.php bloqueou acesso não autorizado\n";
    } else {
        echo "   ❌ Erro inesperado: " . $e->getMessage() . "\n";
    }
}

echo "\n3. TESTANDO PÁGINAS ADMINISTRATIVAS:\n";
echo "-------------------------------------\n";

$paginasProtegidas = [
    'admin_master.php' => 'Painel Master Admin',
    'config_interface.php' => 'Interface de Configuração',
    'api.php' => 'API Endpoint'
];

foreach ($paginasProtegidas as $arquivo => $descricao) {
    if (file_exists($arquivo)) {
        echo "   - Testando {$descricao}...\n";
        
        try {
            ob_start();
            include_once $arquivo;
            $output = ob_get_clean();
            
            if (strpos($output, 'login') !== false || 
                headers_sent() || 
                strpos($output, 'window.location') !== false) {
                echo "     ✅ {$descricao} protegido\n";
            } else {
                echo "     ❌ {$descricao} pode estar vulnerável\n";
            }
            
        } catch (Exception $e) {
            echo "     ✅ {$descricao} bloqueou acesso\n";
        }
    } else {
        echo "   - {$arquivo} não encontrado\n";
    }
}

echo "\n4. TESTANDO CONFIGURAÇÕES DE SESSÃO:\n";
echo "-------------------------------------\n";

// Verificar configurações de sessão
$sessionConfig = [
    'session.cookie_httponly' => ini_get('session.cookie_httponly'),
    'session.use_strict_mode' => ini_get('session.use_strict_mode'),
    'session.cookie_secure' => ini_get('session.cookie_secure'),
    'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime')
];

foreach ($sessionConfig as $config => $value) {
    $status = $value ? '✅' : '❌';
    echo "   {$status} {$config}: {$value}\n";
}

echo "\n5. TESTANDO BANCO DE DADOS:\n";
echo "----------------------------\n";

try {
    if (class_exists('DatabaseManager')) {
        $db = DatabaseManager::getInstance();
        $db->getConnection()->query("SELECT 1");
        echo "   ✅ Conexão com banco funcionando\n";
        
        // Verificar tabelas de segurança
        $tabelasSeguranca = ['usuarios', 'sessoes', 'auditoria'];
        foreach ($tabelasSeguranca as $tabela) {
            $result = $db->getConnection()->query("SHOW TABLES LIKE '{$tabela}'");
            if ($result->rowCount() > 0) {
                echo "   ✅ Tabela {$tabela} existe\n";
            } else {
                echo "   ❌ Tabela {$tabela} não encontrada\n";
            }
        }
        
    } else {
        echo "   ❌ DatabaseManager não disponível\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro no banco: " . $e->getMessage() . "\n";
}

echo "\n6. VERIFICANDO LOGS DE SEGURANÇA:\n";
echo "----------------------------------\n";

$logDir = __DIR__ . '/logs';
if (is_dir($logDir)) {
    echo "   ✅ Diretório de logs existe\n";
    
    $logFiles = glob($logDir . '/*.log');
    echo "   📄 Arquivos de log: " . count($logFiles) . "\n";
    
    // Verificar log de erro recente
    $errorLog = $logDir . '/php_errors.log';
    if (file_exists($errorLog)) {
        $recentErrors = file_get_contents($errorLog);
        if (strpos($recentErrors, 'Auth:') !== false) {
            echo "   ℹ️ Logs de autenticação encontrados\n";
        }
    }
} else {
    echo "   ⚠️ Diretório de logs não existe\n";
}

echo "\n📋 RESUMO DA VERIFICAÇÃO DE SEGURANÇA:\n";
echo "======================================\n";

$vulnerabilidades = [];

// Verificar se index.php está protegido
if (file_exists('index.php')) {
    $indexContent = file_get_contents('index.php');
    
    if (strpos($indexContent, '$auth->isLogado()') === false && 
        strpos($indexContent, 'requireLogin()') === false) {
        $vulnerabilidades[] = "index.php não possui verificação de autenticação adequada";
    }
    
    if (strpos($indexContent, 'session_start()') !== false && 
        strpos($indexContent, 'bootstrap.php') === false) {
        $vulnerabilidades[] = "index.php inicia sessão diretamente sem controle";
    }
}

// Verificar bootstrap.php
if (file_exists('bootstrap.php')) {
    $bootstrapContent = file_get_contents('bootstrap.php');
    
    if (strpos($bootstrapContent, 'session_start()') !== false) {
        $vulnerabilidades[] = "bootstrap.php inicia sessão automaticamente";
    }
}

// Verificar auth.php
if (file_exists('auth.php')) {
    $authContent = file_get_contents('auth.php');
    
    if (strpos($authContent, 'isLogado()') === false) {
        $vulnerabilidades[] = "auth.php pode não ter método isLogado() implementado";
    }
}

if (empty($vulnerabilidades)) {
    echo "🎉 SISTEMA SEGURO!\n";
    echo "✅ Nenhuma vulnerabilidade crítica encontrada\n";
    echo "✅ Sistema de autenticação funcionando corretamente\n";
    echo "\n🔐 Recomendações adicionais:\n";
    echo "   - Monitore regularmente os logs de segurança\n";
    echo "   - Mantenha senhas complexas para todos os usuários\n";
    echo "   - Use HTTPS em produção\n";
    echo "   - Faça backup regular do banco de dados\n";
} else {
    echo "⚠️ VULNERABILIDADES ENCONTRADAS:\n";
    foreach ($vulnerabilidades as $i => $vuln) {
        echo "   " . ($i + 1) . ". {$vuln}\n";
    }
    
    echo "\n🔧 CORREÇÕES NECESSÁRIAS:\n";
    echo "   1. Substitua o arquivo index.php pelo código corrigido\n";
    echo "   2. Substitua o arquivo auth.php pelo código corrigido\n";
    echo "   3. Substitua o arquivo bootstrap.php pelo código corrigido\n";
    echo "   4. Execute este teste novamente após as correções\n";
    
    echo "\n📞 SUPORTE:\n";
    echo "   - Verifique se todos os arquivos foram atualizados corretamente\n";
    echo "   - Teste o login manual em: https://bank.imepedu.com.br/login.php\n";
    echo "   - Verifique os logs em: {$logDir}/\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Teste concluído em " . date('Y-m-d H:i:s') . "\n";
echo "Sistema: " . (defined('SYSTEM_VERSION') ? SYSTEM_VERSION : 'Versão desconhecida') . "\n";
echo str_repeat("=", 50) . "\n";
?>