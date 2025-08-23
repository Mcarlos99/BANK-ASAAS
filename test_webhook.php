<?php
/**
 * Script para testar configuração do webhook
 * Arquivo: test_webhook.php
 */

echo "🔍 TESTE DE CONFIGURAÇÃO DO WEBHOOK\n";
echo "===================================\n\n";

// Verificar se os arquivos existem
$files = [
    'config_api.php',
    'config.php', 
    'asaas_split_system.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ {$file} existe\n";
    } else {
        echo "❌ {$file} não encontrado\n";
    }
}

echo "\n";

// Incluir arquivos
try {
    require_once 'config_api.php';
    require_once 'config.php';
    require_once 'asaas_split_system.php';
    echo "✅ Arquivos carregados com sucesso\n\n";
} catch (Exception $e) {
    echo "❌ Erro ao carregar arquivos: " . $e->getMessage() . "\n";
    exit(1);
}

// Verificar constantes
echo "📋 VERIFICAÇÃO DAS CONFIGURAÇÕES:\n";
echo "---------------------------------\n";

$checks = [
    'ASAAS_ENVIRONMENT' => defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'NÃO DEFINIDO',
    'ASAAS_PRODUCTION_API_KEY' => defined('ASAAS_PRODUCTION_API_KEY') ? 
        (ASAAS_PRODUCTION_API_KEY ? 'CONFIGURADA (' . substr(ASAAS_PRODUCTION_API_KEY, 0, 20) . '...)' : 'VAZIA') : 'NÃO DEFINIDA',
    'ASAAS_SANDBOX_API_KEY' => defined('ASAAS_SANDBOX_API_KEY') ? 
        (ASAAS_SANDBOX_API_KEY ? 'CONFIGURADA (' . substr(ASAAS_SANDBOX_API_KEY, 0, 20) . '...)' : 'VAZIA') : 'NÃO DEFINIDA',
    'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'NÃO DEFINIDO',
    'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'NÃO DEFINIDO'
];

foreach ($checks as $key => $value) {
    echo "{$key}: {$value}\n";
}

echo "\n";

// Testar inicialização do ASAAS
echo "🔌 TESTE DE CONEXÃO COM ASAAS:\n";
echo "-------------------------------\n";

try {
    $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
    echo "✅ Instância do ASAAS criada com sucesso\n";
    
    // Testar chamada para API
    $accounts = $asaas->listAccounts(1, 0);
    echo "✅ Conexão com API funcionando\n";
    echo "   Total de contas: " . $accounts['totalCount'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro na conexão: " . $e->getMessage() . "\n";
}

echo "\n";

// Testar banco de dados
echo "🗄️ TESTE DE BANCO DE DADOS:\n";
echo "----------------------------\n";

try {
    $db = DatabaseManager::getInstance();
    $db->getConnection()->query("SELECT 1");
    echo "✅ Conexão com banco funcionando\n";
    
    // Verificar se tabelas existem
    $tables = ['customers', 'split_accounts', 'payments', 'payment_splits', 'webhook_logs', 'wallet_ids'];
    
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

echo "\n";

// Simular webhook
echo "🎣 SIMULAÇÃO DE WEBHOOK:\n";
echo "------------------------\n";

try {
    $webhookData = [
        'event' => 'PAYMENT_RECEIVED',
        'payment' => [
            'id' => 'pay_test_123',
            'status' => 'RECEIVED',
            'value' => 10.00,
            'paymentDate' => date('Y-m-d')
        ]
    ];
    
    $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
    $result = $asaas->processWebhook($webhookData);
    
    echo "✅ Processamento de webhook simulado com sucesso\n";
    echo "   Resultado: " . json_encode($result) . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro na simulação: " . $e->getMessage() . "\n";
}

echo "\n";

// Verificar logs
echo "📋 VERIFICAÇÃO DE LOGS:\n";
echo "-----------------------\n";

$logDir = __DIR__ . '/logs';
if (is_dir($logDir)) {
    echo "✅ Diretório de logs existe\n";
    
    if (is_writable($logDir)) {
        echo "✅ Diretório de logs tem permissão de escrita\n";
    } else {
        echo "❌ Diretório de logs sem permissão de escrita\n";
    }
    
    $logFiles = glob($logDir . '/*.log');
    echo "   Arquivos de log encontrados: " . count($logFiles) . "\n";
    
} else {
    echo "❌ Diretório de logs não existe\n";
    echo "   Criando diretório...\n";
    
    if (mkdir($logDir, 0755, true)) {
        echo "✅ Diretório criado com sucesso\n";
    } else {
        echo "❌ Erro ao criar diretório\n";
    }
}

echo "\n";

// Recommendations
echo "🔧 RECOMENDAÇÕES:\n";
echo "-----------------\n";

$recommendations = [];

if (!defined('ASAAS_PRODUCTION_API_KEY') || !ASAAS_PRODUCTION_API_KEY) {
    $recommendations[] = "Configure a API Key de produção no config_api.php";
}

if (ASAAS_PRODUCTION_API_KEY === '$aact_MTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1') {
    $recommendations[] = "Substitua a API Key de produção pela sua chave real";
}

if (ASAAS_ENVIRONMENT !== 'production') {
    $recommendations[] = "Altere ASAAS_ENVIRONMENT para 'production' quando for usar em produção";
}

if (empty($recommendations)) {
    echo "✅ Todas as configurações parecem corretas!\n";
    echo "\n🌐 URL do seu webhook: https://bank.imepedu.com.br/webhook.php\n";
    echo "📝 Configure esta URL no painel do ASAAS\n";
} else {
    foreach ($recommendations as $rec) {
        echo "⚠️  {$rec}\n";
    }
}

echo "\n=== FIM DO TESTE ===\n";

// Se executado via web, mostrar em HTML
if (!php_sapi_name() === 'cli') {
    echo "<pre>Execute este script via linha de comando para melhor visualização</pre>";
}
?>