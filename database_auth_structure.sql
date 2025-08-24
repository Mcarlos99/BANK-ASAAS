# SISTEMA DE AUTENTICAÇÃO MULTI-TENANT - ESTRUTURA DO BANCO
# Arquivo: database_auth_structure.sql

# ===================================
# TABELA DE POLOS (TENANTS)
# ===================================

CREATE TABLE IF NOT EXISTS polos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    codigo VARCHAR(50) UNIQUE NOT NULL, -- Ex: POLO_SP_001, POLO_RJ_002
    cidade VARCHAR(100) NOT NULL,
    estado CHAR(2) NOT NULL,
    endereco TEXT,
    telefone VARCHAR(20),
    email VARCHAR(255),
    
    # Configurações específicas do polo
    asaas_environment ENUM('sandbox', 'production') DEFAULT 'sandbox',
    asaas_production_api_key VARCHAR(500) NULL,
    asaas_sandbox_api_key VARCHAR(500) NULL,
    asaas_webhook_token VARCHAR(255) NULL,
    
    # Status e controle
    is_active TINYINT(1) DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    # Configurações adicionais
    configuracoes JSON NULL, -- Para futuras configurações específicas
    
    INDEX idx_codigo (codigo),
    INDEX idx_ativo (is_active),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

# ===================================
# TABELA DE USUÁRIOS
# ===================================

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    polo_id INT NULL, -- NULL para admin master
    
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL, -- Hash da senha
    
    # Tipos de usuário
    tipo ENUM('master', 'admin_polo', 'operador') NOT NULL DEFAULT 'operador',
    # master = Admin geral do sistema
    # admin_polo = Admin específico de um polo
    # operador = Usuário comum do polo
    
    # Permissões específicas (JSON para flexibilidade)
    permissoes JSON NULL,
    
    # Status e controle
    is_active TINYINT(1) DEFAULT 1,
    ultimo_login TIMESTAMP NULL,
    tentativas_login INT DEFAULT 0,
    bloqueado_ate TIMESTAMP NULL,
    
    # Auditoria
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_por INT NULL, -- ID do usuário que criou
    
    # Chaves estrangeiras
    FOREIGN KEY (polo_id) REFERENCES polos(id) ON DELETE CASCADE,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    INDEX idx_email (email),
    INDEX idx_polo (polo_id),
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

# ===================================
# TABELA DE SESSÕES
# ===================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

# ===================================
# TABELA DE LOG DE AUDITORIA
# ===================================

CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    polo_id INT NULL,
    
    acao VARCHAR(100) NOT NULL, -- login, logout, create_wallet, etc.
    tabela VARCHAR(50) NULL, -- Tabela afetada
    registro_id VARCHAR(50) NULL, -- ID do registro afetado
    
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

# ===================================
# MODIFICAR TABELAS EXISTENTES PARA SUPORTAR MULTI-TENANT
# ===================================

# Adicionar polo_id nas tabelas existentes
ALTER TABLE customers ADD COLUMN polo_id INT NULL AFTER id;
ALTER TABLE split_accounts ADD COLUMN polo_id INT NULL AFTER id;
ALTER TABLE payments ADD COLUMN polo_id INT NULL AFTER id;
ALTER TABLE wallet_ids ADD COLUMN polo_id INT NULL AFTER id;

# Criar índices para polo_id
ALTER TABLE customers ADD INDEX idx_polo (polo_id);
ALTER TABLE split_accounts ADD INDEX idx_polo (polo_id);
ALTER TABLE payments ADD INDEX idx_polo (polo_id);
ALTER TABLE wallet_ids ADD INDEX idx_polo (polo_id);

# Adicionar chaves estrangeiras
ALTER TABLE customers ADD FOREIGN KEY (polo_id) REFERENCES polos(id) ON DELETE CASCADE;
ALTER TABLE split_accounts ADD FOREIGN KEY (polo_id) REFERENCES polos(id) ON DELETE CASCADE;
ALTER TABLE payments ADD FOREIGN KEY (polo_id) REFERENCES polos(id) ON DELETE CASCADE;
ALTER TABLE wallet_ids ADD FOREIGN KEY (polo_id) REFERENCES polos(id) ON DELETE CASCADE;

# ===================================
# DADOS INICIAIS
# ===================================

# Polo Master (para dados globais)
INSERT INTO polos (nome, codigo, cidade, estado, is_active) VALUES 
('Administração Central', 'MASTER', 'São Paulo', 'SP', 1);

# Admin Master inicial
INSERT INTO usuarios (polo_id, nome, email, senha, tipo) VALUES 
(NULL, 'Administrador Master', 'admin@imepedu.com.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'master');
# Senha padrão: password (alterar após primeiro login)

# Exemplo de polos
INSERT INTO polos (nome, codigo, cidade, estado, email, asaas_environment) VALUES 
('IMEP - Polo São Paulo', 'POLO_SP_001', 'São Paulo', 'SP', 'saopaulo@imepedu.com.br', 'sandbox'),
('IMEP - Polo Rio de Janeiro', 'POLO_RJ_001', 'Rio de Janeiro', 'RJ', 'rio@imepedu.com.br', 'sandbox'),
('IMEP - Polo Belo Horizonte', 'POLO_MG_001', 'Belo Horizonte', 'MG', 'bh@imepedu.com.br', 'sandbox');

# Admins dos polos (senhas padrão: polo123)
INSERT INTO usuarios (polo_id, nome, email, senha, tipo) VALUES 
(2, 'Admin São Paulo', 'admin.sp@imepedu.com.br', '$2y$10$4rFZN4.GQJNJGqNhtdCuKOa5SAGPMXOYnDTz4X8jnxKkT7nNmVWki', 'admin_polo'),
(3, 'Admin Rio de Janeiro', 'admin.rj@imepedu.com.br', '$2y$10$4rFZN4.GQJNJGqNhtdCuKOa5SAGPMXOYnDTz4X8jnxKkT7nNmVWki', 'admin_polo'),
(4, 'Admin Belo Horizonte', 'admin.mg@imepedu.com.br', '$2y$10$4rFZN4.GQJNJGqNhtdCuKOa5SAGPMXOYnDTz4X8jnxKkT7nNmVWki', 'admin_polo');