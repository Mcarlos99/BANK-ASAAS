<?php
/**
 * Script de Configuração do Sistema de Autenticação Multi-Tenant
 * Arquivo: setup_auth.php
 * 
 * Execute este script para configurar o sistema de autenticação
 */

require_once 'config_api.php';
require_once 'config.php';

echo "🚀 CONFIGURAÇÃO DO SISTEMA DE AUTENTICAÇÃO MULTI-TENANT\n";
echo "=======================================================\n\n";

try {
    $db = DatabaseManager::getInstance();
    $connection = $db->getConnection();
    
    echo "✅ Conexão com banco estabelecida\n\n";
    
    // 1. Criar tabela de polos
    echo "📋 Criando tabela de polos...\n";
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
    echo "✅ Tabela polos criada\n";
    
    // 2. Criar tabela de usuários
    echo "👥 Criando tabela de usuários...\n";
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
    echo "✅ Tabela usuarios criada\n";
    
    // 3. Criar tabela de sessões
    echo "🔐 Criando tabela de sessões...\n";
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
    echo "✅ Tabela sessoes criada\n";
    
    // 4. Criar tabela de auditoria
    echo "📊 Criando tabela de auditoria...\n";
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
    echo "✅ Tabela auditoria criada\n";
    
    // 5. Modificar tabelas existentes para suportar multi-tenant
    echo "🔧 Adicionando suporte multi-tenant às tabelas existentes...\n";
    
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
                echo "  ✅ Coluna polo_id adicionada à tabela {$tabela}\n";
            } else {
                echo "  ⚠️  Coluna polo_id já existe na tabela {$tabela}\n";
            }
        } else {
            echo "  ⚠️  Tabela {$tabela} não existe (será criada pelo sistema principal)\n";
        }
    }
    
    // 6. Inserir dados iniciais
    echo "\n📝 Inserindo dados iniciais...\n";
    
    // Polo Master (para admin geral)
    $stmt = $connection->prepare("
        INSERT IGNORE INTO polos (nome, codigo, cidade, estado, is_active) 
        VALUES ('Administração Central', 'MASTER', 'São Paulo', 'SP', 1)
    ");
    $stmt->execute();
    echo "✅ Polo Master criado\n";
    
    // Admin Master
    $senhaHash = password_hash('password', PASSWORD_DEFAULT);
    $stmt = $connection->prepare("
        INSERT IGNORE INTO usuarios (polo_id, nome, email, senha, tipo) 
        VALUES (NULL, 'Administrador Master', 'admin@imepedu.com.br', ?, 'master')
    ");
    $stmt->execute([$senhaHash]);
    echo "✅ Admin Master criado (email: admin@imepedu.com.br, senha: password)\n";
    
    // Polos de exemplo
    $polosExemplo = [
        ['IMEP - Polo São Paulo', 'POLO_SP_001', 'São Paulo', 'SP', 'saopaulo@imepedu.com.br'],
        ['IMEP - Polo Rio de Janeiro', 'POLO_RJ_001', 'Rio de Janeiro', 'RJ', 'rio@imepedu.com.br'],
        ['IMEP - Polo Belo Horizonte', 'POLO_MG_001', 'Belo Horizonte', 'MG', 'bh@imepedu.com.br']
    ];
    
    foreach ($polosExemplo as $polo) {
        $stmt = $connection->prepare("
            INSERT IGNORE INTO polos (nome, codigo, cidade, estado, email, asaas_environment) 
            VALUES (?, ?, ?, ?, ?, 'sandbox')
        ");
        $stmt->execute($polo);
    }
    echo "✅ Polos de exemplo criados\n";
    
    // Admins dos polos
    $senhaPoloHash = password_hash('polo123', PASSWORD_DEFAULT);
    $adminsPolos = [
        [2, 'Admin São Paulo', 'admin.sp@imepedu.com.br'],
        [3, 'Admin Rio de Janeiro', 'admin.rj@imepedu.com.br'],
        [4, 'Admin Belo Horizonte', 'admin.mg@imepedu.com.br']
    ];
    
    foreach ($adminsPolos as $admin) {
        $stmt = $connection->prepare("
            INSERT IGNORE INTO usuarios (polo_id, nome, email, senha, tipo) 
            VALUES (?, ?, ?, ?, 'admin_polo')
        ");
        $stmt->execute([$admin[0], $admin[1], $admin[2], $senhaPoloHash]);
    }
    echo "✅ Admins dos polos criados (senha: polo123)\n";
    
    // 7. Verificações finais
    echo "\n🔍 Verificações finais...\n";
    
    // Contar registros
    $stmt = $connection->query("SELECT COUNT(*) as total FROM polos");
    $totalPolos = $stmt->fetch()['total'];
    echo "  📊 Total de polos: {$totalPolos}\n";
    
    $stmt = $connection->query("SELECT COUNT(*) as total FROM usuarios");
    $totalUsuarios = $stmt->fetch()['total'];
    echo "  👥 Total de usuários: {$totalUsuarios}\n";
    
    // Verificar admin master
    $stmt = $connection->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'master'");
    $totalMasters = $stmt->fetch()['total'];
    echo "  👑 Admins master: {$totalMasters}\n";
    
    echo "\n✅ CONFIGURAÇÃO CONCLUÍDA COM SUCESSO!\n\n";
    
    // Instruções finais
    echo "📋 PRÓXIMOS PASSOS:\n";
    echo "==================\n";
    echo "1. Acesse o sistema através de: login.php\n";
    echo "2. Use as credenciais do Admin Master:\n";
    echo "   Email: admin@imepedu.com.br\n";
    echo "   Senha: password\n";
    echo "3. Altere a senha padrão após o primeiro login\n";
    echo "4. Configure as API Keys dos polos nas configurações\n";
    echo "5. Crie usuários adicionais conforme necessário\n\n";
    
    echo "🔐 CREDENCIAIS DE TESTE:\n";
    echo "========================\n";
    echo "Master Admin: admin@imepedu.com.br / password\n";
    echo "Admin SP: admin.sp@imepedu.com.br / polo123\n";
    echo "Admin RJ: admin.rj@imepedu.com.br / polo123\n";
    echo "Admin MG: admin.mg@imepedu.com.br / polo123\n\n";
    
    echo "⚠️  IMPORTANTE: Altere todas as senhas padrão em produção!\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    exit(1);
}

/**
 * Função auxiliar para verificar e criar arquivo de login
 */
function criarArquivoLogin() {
    $loginFile = __DIR__ . '/login.php';
    
    if (!file_exists($loginFile)) {
        echo "📝 Criando arquivo login.php...\n";
        
        $loginContent = '<?php
/**
 * Página de Login - Sistema IMEP Multi-Tenant
 */
session_start();

// Redirecionar se já está logado
require_once "auth.php";
if ($auth->isLogado()) {
    $usuario = $auth->getUsuarioAtual();
    $redirect = $usuario["tipo"] === "master" ? "admin_master.php" : "index.php";
    header("Location: {$redirect}");
    exit;
}

// Incluir o HTML da página de login aqui
include "login_template.html";
?>';
        
        file_put_contents($loginFile, $loginContent);
        echo "✅ Arquivo login.php criado\n";
    }
}

// Criar arquivos necessários
criarArquivoLogin();

echo "\n🎉 Sistema de autenticação configurado e pronto para uso!\n";
?>