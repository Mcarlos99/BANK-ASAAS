<?php
/**
 * Script de teste para verificar o sistema
 * Arquivo: test_system.php
 */

echo "🧪 TESTE DO SISTEMA IMEP SPLIT ASAAS\n";
echo "=====================================\n\n";

// Incluir bootstrap
try {
    require_once 'bootstrap.php';
    echo "✅ Bootstrap carregado com sucesso\n";
} catch (Exception $e) {
    echo "❌ Erro no bootstrap: " . $e->getMessage() . "\n";
    exit(1);
}

// Testar configurações
echo "\n📋 VERIFICANDO CONFIGURAÇÕES:\n";
echo "------------------------------\n";

$configs = [
    'ASAAS_ENVIRONMENT' => defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'NÃO DEFINIDO',
    'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'NÃO DEFINIDO',
    'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'NÃO DEFINIDO'
];

foreach ($configs as $key => $value) {
    echo "{$key}: {$value}\n";
}

// Testar banco de dados
echo "\n🗄️ TESTANDO BANCO DE DADOS:\n";
echo "----------------------------\n";

try {
    $db = DatabaseManager::getInstance();
    $db->getConnection()->query("SELECT 1");
    echo "✅ Conexão com banco funcionando\n";
    
    // Verificar tabelas principais
    $tables = ['polos', 'usuarios', 'sessoes', 'auditoria'];
    
    foreach ($tables as $table) {
        $result = $db->getConnection()->query("SHOW TABLES LIKE '{$table}'");
        if ($result->rowCount() > 0) {
            echo "✅ Tabela {$table} existe\n";
        } else {
            echo "❌ Tabela {$table} não encontrada\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro no banco: " . $e->getMessage() . "\n";
}

// Testar classes principais
echo "\n🔧 TESTANDO CLASSES:\n";
echo "--------------------\n";

$classes = [
    'ConfigManager',
    'AuthSystem', 
    'DatabaseManager',
    'AsaasSplitPayment'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ Classe {$class} disponível\n";
    } else {
        echo "❌ Classe {$class} não encontrada\n";
    }
}

// Testar ConfigManager
echo "\n⚙️ TESTANDO CONFIGMANAGER:\n";
echo "---------------------------\n";

try {
    $configManager = new ConfigManager();
    echo "✅ ConfigManager instanciado\n";
    
    // Testar método listarPolos
    $polos = $configManager->listarPolos(true);
    echo "✅ Método listarPolos() funcionando\n";
    echo "   Polos encontrados: " . count($polos) . "\n";
    
    // Mostrar alguns polos se existirem
    if (count($polos) > 0) {
        echo "   Exemplos:\n";
        foreach (array_slice($polos, 0, 3) as $polo) {
            echo "   - {$polo['nome']} ({$polo['codigo']})\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro no ConfigManager: " . $e->getMessage() . "\n";
}

// Testar AuthSystem
echo "\n🔐 TESTANDO AUTHSYSTEM:\n";
echo "-----------------------\n";

try {
    $auth = new AuthSystem();
    echo "✅ AuthSystem instanciado\n";
    
    if ($auth->isLogado()) {
        $usuario = $auth->getUsuarioAtual();
        echo "✅ Usuário logado: " . $usuario['nome'] . "\n";
        echo "   Tipo: " . $usuario['tipo'] . "\n";
        echo "   Polo: " . ($usuario['polo_nome'] ?? 'Master') . "\n";
    } else {
        echo "ℹ️ Nenhum usuário logado\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro no AuthSystem: " . $e->getMessage() . "\n";
}

// Verificar permissões de arquivos
echo "\n📁 VERIFICANDO PERMISSÕES:\n";
echo "---------------------------\n";

$paths = [
    __DIR__ . '/logs' => 'Diretório de logs',
    __DIR__ . '/config_api.php' => 'Arquivo de configuração',
    __DIR__ . '/admin_master.php' => 'Página master admin'
];

foreach ($paths as $path => $desc) {
    if (file_exists($path)) {
        $readable = is_readable($path) ? '✅' : '❌';
        $writable = is_dir($path) ? (is_writable($path) ? '✅' : '❌') : 'N/A';
        echo "{$readable} {$desc} - Leitura: OK" . (is_dir($path) ? " | Escrita: {$writable}" : "") . "\n";
    } else {
        echo "❌ {$desc} não encontrado\n";
    }
}

// Sumário final
echo "\n📊 SUMÁRIO FINAL:\n";
echo "-----------------\n";

$issues = [];

// Verificar problemas críticos
if (!class_exists('ConfigManager')) {
    $issues[] = "Classe ConfigManager não encontrada";
}

if (!defined('DB_HOST') || !defined('DB_NAME')) {
    $issues[] = "Configurações de banco não definidas";
}

try {
    $db = DatabaseManager::getInstance();
    $result = $db->getConnection()->query("SHOW TABLES LIKE 'polos'");
    if ($result->rowCount() == 0) {
        $issues[] = "Tabela 'polos' não encontrada - execute as migrações";
    }
} catch (Exception $e) {
    $issues[] = "Problema na conexão com banco: " . $e->getMessage();
}

if (empty($issues)) {
    echo "🎉 SISTEMA OK! Todos os componentes principais estão funcionando.\n";
    echo "\n🚀 Você pode acessar:\n";
    echo "   - https://bank.imepedu.com.br/login.php (Login)\n";
    echo "   - https://bank.imepedu.com.br/admin_master.php (Master Admin)\n";
    echo "   - https://bank.imepedu.com.br/index.php (Dashboard Principal)\n";
} else {
    echo "⚠️ PROBLEMAS ENCONTRADOS:\n";
    foreach ($issues as $issue) {
        echo "   - {$issue}\n";
    }
    echo "\n🔧 SOLUÇÕES RECOMENDADAS:\n";
    echo "   1. Substitua o arquivo config_manager.php pela versão corrigida\n";
    echo "   2. Verifique se todas as tabelas foram criadas no banco\n";
    echo "   3. Execute o script de migração se necessário\n";
}

echo "\n=== FIM DO TESTE ===\n";
?>