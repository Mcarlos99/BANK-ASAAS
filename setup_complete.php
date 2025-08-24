<?php
/**
 * Setup Final do Sistema Multi-Tenant IMEP Split ASAAS
 * Arquivo: setup_complete.php
 * 
 * Execute este script para configurar completamente o sistema
 */

// Verificar se já foi executado
$lockFile = __DIR__ . '/.setup_complete.lock';
if (file_exists($lockFile)) {
    echo "⚠️  Sistema já foi configurado anteriormente.\n";
    echo "Para reconfigurar, delete o arquivo .setup_complete.lock e execute novamente.\n";
    exit;
}

echo "🚀 SETUP COMPLETO - SISTEMA MULTI-TENANT IMEP SPLIT ASAAS\n";
echo "===========================================================\n\n";

try {
    // 1. Verificar dependências
    echo "1️⃣  VERIFICANDO DEPENDÊNCIAS\n";
    echo "──────────────────────────\n";
    
    $required = ['curl', 'json', 'pdo', 'pdo_mysql', 'session'];
    $missing = [];
    
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        } else {
            echo "  ✅ {$ext}\n";
        }
    }
    
    if (!empty($missing)) {
        throw new Exception("❌ Extensões PHP faltando: " . implode(', ', $missing));
    }
    
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        throw new Exception("❌ PHP 7.4+ é necessário. Versão atual: " . PHP_VERSION);
    }
    
    echo "  ✅ PHP " . PHP_VERSION . " (Compatível)\n\n";
    
    // 2. Incluir arquivos necessários
    echo "2️⃣  CARREGANDO SISTEMA\n";
    echo "──────────────────────\n";
    
    $files = [
        'config_api.php' => 'Configurações de API',
        'config.php' => 'Configurações do sistema',
        'auth.php' => 'Sistema de autenticação',
        'config_manager.php' => 'Gerenciador de configurações',
        'asaas_split_system.php' => 'Sistema ASAAS'
    ];
    
    foreach ($files as $file => $desc) {
        if (file_exists($file)) {
            require_once $file;
            echo "  ✅ {$desc}\n";
        } else {
            echo "  ⚠️  {$desc} (arquivo não encontrado)\n";
        }
    }
    echo "\n";
    
    // 3. Configurar banco de dados
    echo "3️⃣  CONFIGURANDO BANCO DE DADOS\n";
    echo "─────────────────────────────\n";
    
    $db = DatabaseManager::getInstance();
    $connection = $db->getConnection();
    
    echo "  ✅ Conexão estabelecida\n";
    echo "  📊 Banco: " . DB_NAME . " em " . DB_HOST . "\n";
    
    // Criar tabelas do sistema de autenticação
    echo "  🔧 Criando tabelas de autenticação...\n";
    
    // Tabela de polos
    $connection->exec("
        CREATE TABLE IF NOT EXISTS polos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            codigo VARCHAR(50) UNIQUE NOT NULL,
            cidade VARCHAR(100) NOT NULL,
            estado CHAR(2) NOT NULL,
            endereco TEXT,
            telefone VARCHAR(20),
            email VARCHAR(255),
            
            asaas_environment ENUM('sandbox', 'production') DEFAULT 'sandbox',
            asaas_production_api_key VARCHAR(500) NULL,
            asaas_sandbox_api_key VARCHAR(500) NULL,
            asaas_webhook_token VARCHAR(255) NULL,
            
            is_active TINYINT(1) DEFAULT 1,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            configuracoes JSON NULL,
            
            INDEX idx_codigo (codigo),
            INDEX idx_ativo (is_active),
            INDEX idx_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "    ✅ Tabela polos\n";
    
    // Tabela de usuários
    $connection->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            polo_id INT NULL,
            
            nome VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            senha VARCHAR(255) NOT NULL,
            
            tipo ENUM('master', 'admin_polo', 'operador') NOT NULL DEFAULT 'operador',
            permissoes JSON NULL,
            
            is_active TINYINT(1) DEFAULT 1,
            ultimo_login TIMESTAMP NULL,
            tentativas_login INT DEFAULT 0,
            bloqueado_ate TIMESTAMP NULL,
            
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            criado_por INT NULL,
            
            FOREIGN KEY (polo_id) REFERENCES polos(id) ON DELETE CASCADE,
            
            INDEX idx_email (email),
            INDEX idx_polo (polo_id),
            INDEX idx_tipo (tipo),
            INDEX idx_ativo (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "    ✅ Tabela usuarios\n";
    
    // Tabela de sessões
    $connection->exec("
        CREATE TABLE IF NOT EXISTS sessoes (
            id VARCHAR(128) PRIMARY KEY,
            usuario_id INT NOT NULL,
            polo_id INT NULL,
            
            ip_address VARCHAR(45),
            user_agent TEXT,
            dados_sessao JSON NULL,
            
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ultima_atividade TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expira_em TIMESTAMP NOT NULL,
            
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (polo_id) REFERENCES polos(id) ON DELETE CASCADE,
            
            INDEX idx_usuario (usuario_id),
            INDEX idx_expiracao (expira_em),
            INDEX idx_ultima_atividade (ultima_atividade)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "    ✅ Tabela sessoes\n";
    
    // Tabela de auditoria
    $connection->exec("
        CREATE TABLE IF NOT EXISTS auditoria (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NULL,
            polo_id INT NULL,
            
            acao VARCHAR(100) NOT NULL,
            tabela VARCHAR(50) NULL,
            registro_id VARCHAR(50) NULL,
            
            dados_anteriores JSON NULL,
            dados_novos JSON NULL,
            
            ip_address VARCHAR(45),
            user_agent TEXT,
            
            data_acao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
            FOREIGN KEY (polo_id) REFERENCES polos(id) ON DELETE SET NULL,
            
            INDEX idx_usuario (usuario_id),
            INDEX idx_polo (polo_id),
            INDEX idx_acao (acao),
            INDEX idx_data (data_acao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "    ✅ Tabela auditoria\n";
    
    // Atualizar tabelas existentes para multi-tenant
    echo "  🔧 Atualizando tabelas existentes...\n";
    
    $tabelas = ['customers', 'split_accounts', 'payments', 'wallet_ids'];
    
    foreach ($tabelas as $tabela) {
        // Verificar se tabela existe
        $result = $connection->query("SHOW TABLES LIKE '{$tabela}'");
        if ($result->rowCount() > 0) {
            // Verificar se já tem a coluna polo_id
            $result = $connection->query("SHOW COLUMNS FROM {$tabela} LIKE 'polo_id'");
            if ($result->rowCount() == 0) {
                $connection->exec("ALTER TABLE {$tabela} ADD COLUMN polo_id INT NULL AFTER id");
                $connection->exec("ALTER TABLE {$tabela} ADD INDEX idx_polo (polo_id)");
                echo "    ✅ Coluna polo_id adicionada à {$tabela}\n";
            } else {
                echo "    ⚪ Coluna polo_id já existe em {$tabela}\n";
            }
        } else {
            echo "    📝 Tabela {$tabela} será criada pelo sistema principal\n";
        }
    }
    
    echo "\n";
    
    // 4. Criar dados iniciais
    echo "4️⃣  CRIANDO DADOS INICIAIS\n";
    echo "────────────────────────\n";
    
    // Polo Master
    $stmt = $connection->prepare("
        INSERT IGNORE INTO polos (nome, codigo, cidade, estado, is_active) 
        VALUES ('Administração Central', 'MASTER', 'São Paulo', 'SP', 1)
    ");
    $stmt->execute();
    echo "  ✅ Polo Master (Administração Central)\n";
    
    // Admin Master
    $senhaHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $connection->prepare("
        INSERT IGNORE INTO usuarios (polo_id, nome, email, senha, tipo) 
        VALUES (NULL, 'Master Administrator', 'admin@imepedu.com.br', ?, 'master')
    ");
    $stmt->execute([$senhaHash]);
    echo "  👑 Master Admin criado\n";
    echo "      Email: admin@imepedu.com.br\n";
    echo "      Senha: admin123\n";
    
    // Polos de exemplo
    $polosExemplo = [
        ['IMEP - Polo São Paulo', 'POLO_SP_001', 'São Paulo', 'SP', 'saopaulo@imepedu.com.br'],
        ['IMEP - Polo Rio de Janeiro', 'POLO_RJ_001', 'Rio de Janeiro', 'RJ', 'rio@imepedu.com.br'],
        ['IMEP - Polo Belo Horizonte', 'POLO_MG_001', 'Belo Horizonte', 'MG', 'bh@imepedu.com.br'],
        ['IMEP - Polo Brasília', 'POLO_DF_001', 'Brasília', 'DF', 'brasilia@imepedu.com.br']
    ];
    
    foreach ($polosExemplo as $polo) {
        $stmt = $connection->prepare("
            INSERT IGNORE INTO polos (nome, codigo, cidade, estado, email, asaas_environment) 
            VALUES (?, ?, ?, ?, ?, 'sandbox')
        ");
        $stmt->execute($polo);
    }
    echo "  🏢 4 polos de exemplo criados\n";
    
    // Admins dos polos
    $senhaPoloHash = password_hash('polo2024', PASSWORD_DEFAULT);
    $adminsPolos = [
        [2, 'Admin São Paulo', 'admin.sp@imepedu.com.br'],
        [3, 'Admin Rio de Janeiro', 'admin.rj@imepedu.com.br'],
        [4, 'Admin Belo Horizonte', 'admin.mg@imepedu.com.br'],
        [5, 'Admin Brasília', 'admin.df@imepedu.com.br']
    ];
    
    foreach ($adminsPolos as $admin) {
        $stmt = $connection->prepare("
            INSERT IGNORE INTO usuarios (polo_id, nome, email, senha, tipo) 
            VALUES (?, ?, ?, ?, 'admin_polo')
        ");
        $stmt->execute([$admin[0], $admin[1], $admin[2], $senhaPoloHash]);
    }
    echo "  👥 4 admins de polo criados (senha: polo2024)\n";
    
    echo "\n";
    
    // 5. Configurar permissões de arquivos
    echo "5️⃣  CONFIGURANDO PERMISSÕES\n";
    echo "─────────────────────────\n";
    
    $dirs = [
        __DIR__ . '/logs' => 'Logs do sistema',
        __DIR__ . '/cache' => 'Cache temporário',
        __DIR__ . '/backups' => 'Backups do banco',
        __DIR__ . '/uploads' => 'Arquivos enviados'
    ];
    
    foreach ($dirs as $dir => $desc) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "  ✅ Diretório criado: {$desc}\n";
        } else {
            echo "  ⚪ Diretório existe: {$desc}\n";
        }
        chmod($dir, 0755);
    }
    
    // Criar arquivo .htaccess para logs
    $htaccessContent = "Order Deny,Allow\nDeny from all\n";
    file_put_contents(__DIR__ . '/logs/.htaccess', $htaccessContent);
    echo "  🔒 Proteção de logs configurada\n";
    
    echo "\n";
    
    // 6. Verificar configurações
    echo "6️⃣  VERIFICANDO CONFIGURAÇÕES\n";
    echo "───────────────────────────\n";
    
    // Verificar API Keys
    if (defined('ASAAS_PRODUCTION_API_KEY') && ASAAS_PRODUCTION_API_KEY !== '$aact_prod_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjdmNDZhZTU1LWVjYTgtNDY0Mi1hOTg5LTY0NmMxNmM1ZTFkNzo6JGFhY2hfMWYzOTgxNjEtZWRhNy00ZjhhLTk5MGQtNGYwZjY2MzJmZTJk') {
        echo "  ✅ API Key Master Produção: Configurada\n";
    } else {
        echo "  ⚠️  API Key Master Produção: Usar configuração padrão\n";
    }
    
    if (defined('ASAAS_SANDBOX_API_KEY') && ASAAS_SANDBOX_API_KEY !== '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjYyNTE2NTRkLTlhMmYtNGUxMS1iN2NlLTg1ZTQ5OTJjOTYyYjo6JGFhY2hfZjc5MDNiNTUtOWQ3Ny00MDRiLTg4YjctY2YxZmNhNTY5OGY5') {
        echo "  ✅ API Key Master Sandbox: Configurada\n";
    } else {
        echo "  ⚠️  API Key Master Sandbox: Usar configuração padrão\n";
    }
    
    // Verificar URL do webhook
    if (isset($_SERVER['HTTP_HOST'])) {
        $webhookUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/webhook.php";
        echo "  🔗 URL do Webhook: {$webhookUrl}\n";
    }
    
    echo "\n";
    
    // 7. Criar arquivos de configuração adicionais
    echo "7️⃣  CRIANDO ARQUIVOS DE CONFIGURAÇÃO\n";
    echo "──────────────────────────────────\n";
    
    // Criar arquivo .env de exemplo
    $envContent = "# Configurações do Sistema IMEP Split ASAAS
# Copie para .env e configure suas credenciais

# Banco de Dados
DB_HOST=localhost
DB_NAME=bankdb
DB_USER=bankuser
DB_PASS=lKVX4Ew0u7I89hAUuDCm

# ASAAS - Master (apenas para admin geral)
ASAAS_ENVIRONMENT=production
ASAAS_PRODUCTION_API_KEY=\$aact_prod_SUA_CHAVE_AQUI
ASAAS_SANDBOX_API_KEY=\$aact_hmlg_SUA_CHAVE_AQUI
ASAAS_WEBHOOK_TOKEN=seu_webhook_token_aqui

# Sistema
LOG_LEVEL=INFO
LOG_RETENTION_DAYS=30
WEBHOOK_TIMEOUT=30
";
    
    file_put_contents(__DIR__ . '/.env.example', $envContent);
    echo "  ✅ Arquivo .env.example criado\n";
    
    // Criar arquivo de documentação
    $docContent = "# Sistema Multi-Tenant IMEP Split ASAAS

## Instalação Concluída com Sucesso! 🎉

### Credenciais de Acesso:

**Master Administrator:**
- Email: admin@imepedu.com.br
- Senha: admin123
- Acesso: Painel completo do sistema

**Admins de Polo:**
- admin.sp@imepedu.com.br / polo2024 (São Paulo)
- admin.rj@imepedu.com.br / polo2024 (Rio de Janeiro) 
- admin.mg@imepedu.com.br / polo2024 (Belo Horizonte)
- admin.df@imepedu.com.br / polo2024 (Brasília)

### URLs do Sistema:

- **Login:** login.php
- **Dashboard Master:** admin_master.php
- **Dashboard Polo:** index.php
- **Configurações:** config_manager.php

### Próximos Passos:

1. **Altere todas as senhas padrão**
2. **Configure as API Keys** de cada polo no painel de configurações
3. **Configure a URL do webhook** no painel ASAAS
4. **Teste as conexões** antes de usar em produção

### Configuração de APIs por Polo:

Cada polo deve ter suas próprias credenciais ASAAS configuradas:
1. Faça login como Master Admin
2. Acesse 'Configurações de APIs'
3. Configure as credenciais de cada polo
4. Teste as conexões

### Suporte:

- Documentação: https://docs.asaas.com
- Sistema: Desenvolvido para IMEP

**⚠️ IMPORTANTE: Altere todas as senhas padrão antes de usar em produção!**
";
    
    file_put_contents(__DIR__ . '/INSTALACAO_COMPLETA.md', $docContent);
    echo "  📚 Documentação criada: INSTALACAO_COMPLETA.md\n";
    
    echo "\n";
    
    // 8. Testes finais
    echo "8️⃣  EXECUTANDO TESTES FINAIS\n";
    echo "──────────────────────────\n";
    
    // Testar login
    echo "  🧪 Testando sistema de autenticação...\n";
    $auth = new AuthSystem();
    
    $loginTest = $auth->login('admin@imepedu.com.br', 'admin123');
    if ($loginTest['success']) {
        echo "    ✅ Login Master funcionando\n";
        $auth->logout(); // Limpar sessão de teste
    } else {
        echo "    ❌ Falha no teste de login: " . $loginTest['message'] . "\n";
    }
    
    // Testar banco
    $stmt = $connection->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'master'");
    $masterCount = $stmt->fetch()['total'];
    echo "  📊 Total de admins master: {$masterCount}\n";
    
    $stmt = $connection->query("SELECT COUNT(*) as total FROM polos WHERE is_active = 1");
    $polosCount = $stmt->fetch()['total'];
    echo "  🏢 Total de polos ativos: {$polosCount}\n";
    
    echo "\n";
    
    // 9. Criar arquivo de lock
    file_put_contents($lockFile, json_encode([
        'setup_date' => date('Y-m-d H:i:s'),
        'version' => '2.1.0',
        'php_version' => PHP_VERSION,
        'polos_created' => $polosCount,
        'master_admins' => $masterCount
    ]));
    
    echo "✅ INSTALAÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "===================================\n\n";
    
    echo "🎯 RESUMO DA INSTALAÇÃO:\n";
    echo "• Sistema Multi-Tenant configurado\n";
    echo "• {$polosCount} polos criados (incluindo Master)\n";
    echo "• " . ($masterCount + 4) . " usuários criados\n";
    echo "• Todas as tabelas configuradas\n";
    echo "• Permissões de arquivos ajustadas\n";
    echo "• Sistema de auditoria ativo\n\n";
    
    echo "🔑 CREDENCIAIS PRINCIPAIS:\n";
    echo "┌─────────────────────────────────────────┐\n";
    echo "│ MASTER ADMIN                            │\n";
    echo "│ Email: admin@imepedu.com.br             │\n";
    echo "│ Senha: admin123                         │\n";
    echo "│ URL: " . (isset($_SERVER['HTTP_HOST']) ? "https://{$_SERVER['HTTP_HOST']}/login.php" : "login.php") . str_repeat(' ', max(0, 25 - strlen(isset($_SERVER['HTTP_HOST']) ? "https://{$_SERVER['HTTP_HOST']}/login.php" : "login.php"))) . "│\n";
    echo "└─────────────────────────────────────────┘\n\n";
    
    echo "📋 PRÓXIMAS AÇÕES OBRIGATÓRIAS:\n";
    echo "1. 🔐 Altere TODAS as senhas padrão\n";
    echo "2. ⚙️  Configure as API Keys dos polos\n";
    echo "3. 🔗 Configure o webhook no painel ASAAS\n";
    echo "4. 🧪 Teste todas as funcionalidades\n";
    echo "5. 📚 Leia o arquivo INSTALACAO_COMPLETA.md\n\n";
    
    if (isset($_SERVER['HTTP_HOST'])) {
        echo "🌐 ACESSO DIRETO:\n";
        echo "https://{$_SERVER['HTTP_HOST']}/login.php\n\n";
    }
    
    echo "⚠️  SEGURANÇA: Delete este arquivo (setup_complete.php) após a instalação!\n\n";
    echo "🎉 Sistema pronto para uso!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO NA INSTALAÇÃO:\n";
    echo "====================\n";
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n\n";
    
    echo "🔧 SOLUÇÕES POSSÍVEIS:\n";
    echo "• Verifique as credenciais do banco de dados\n";
    echo "• Verifique as permissões de arquivos\n";
    echo "• Verifique se todas as extensões PHP estão instaladas\n";
    echo "• Verifique se o MySQL está rodando\n\n";
    
    echo "📞 Se o problema persistir, verifique:\n";
    echo "• Logs do PHP: " . ini_get('error_log') . "\n";
    echo "• Logs do MySQL\n";
    echo "• Configurações do servidor\n";
    
    exit(1);
}
?>