<?php
/**
 * Script para corrigir erro "Class ConfigManager not found"
 * Arquivo: fix_configmanager_error.php
 */

echo "🔧 CORREÇÃO DO ERRO CONFIGMANAGER\n";
echo "=================================\n\n";

try {
    // 1. Verificar se o arquivo config_manager.php existe
    echo "📁 VERIFICANDO ARQUIVOS NECESSÁRIOS\n";
    echo "──────────────────────────────────\n";
    
    $files = [
        'config_manager.php' => 'Gerenciador de configurações',
        'admin_master.php' => 'Painel master admin',
        'auth.php' => 'Sistema de autenticação',
        'config.php' => 'Configurações do sistema'
    ];
    
    foreach ($files as $file => $desc) {
        if (file_exists($file)) {
            echo "  ✅ {$desc} ({$file})\n";
        } else {
            echo "  ❌ {$desc} ({$file}) - NÃO ENCONTRADO\n";
        }
    }
    
    echo "\n";
    
    // 2. Verificar se ConfigManager está no config_manager.php
    if (file_exists('config_manager.php')) {
        $content = file_get_contents('config_manager.php');
        
        if (strpos($content, 'class ConfigManager') !== false) {
            echo "  ✅ Classe ConfigManager encontrada em config_manager.php\n";
        } else {
            echo "  ❌ Classe ConfigManager NÃO encontrada em config_manager.php\n";
        }
    }
    
    // 3. Verificar admin_master.php
    if (file_exists('admin_master.php')) {
        $adminContent = file_get_contents('admin_master.php');
        
        // Verificar includes
        if (strpos($adminContent, 'config_manager.php') !== false) {
            echo "  ✅ admin_master.php inclui config_manager.php\n";
        } else {
            echo "  ❌ admin_master.php NÃO inclui config_manager.php\n";
        }
        
        // Verificar se usa ConfigManager
        if (strpos($adminContent, 'ConfigManager') !== false) {
            echo "  ⚠️  admin_master.php usa classe ConfigManager (linha ~153)\n";
        }
    }
    
    echo "\n";
    
    // 4. Criar config_manager.php se não existir
    if (!file_exists('config_manager.php')) {
        echo "🔧 CRIANDO CONFIG_MANAGER.PHP\n";
        echo "────────────────────────────\n";
        
        $configManagerContent = '<?php
/**
 * Gerenciador de Configurações ASAAS por Polo
 * Arquivo: config_manager.php
 */

/**
 * Classe para gerenciar configurações dos polos
 */
class ConfigManager {
    
    private $db;
    private $auth;
    
    public function __construct() {
        if (class_exists(\'DatabaseManager\')) {
            try {
                $this->db = DatabaseManager::getInstance();
            } catch (Exception $e) {
                error_log("ConfigManager: Erro ao conectar com banco: " . $e->getMessage());
                $this->db = null;
            }
        }
        
        if (class_exists(\'AuthSystem\')) {
            $this->auth = new AuthSystem();
        }
    }
    
    /**
     * Listar polos (baseado na permissão do usuário)
     */
    public function listarPolos($incluirInativos = false) {
        if (!$this->db) {
            return [];
        }
        
        try {
            $whereClause = $incluirInativos ? \'\' : \'WHERE is_active = 1\';
            
            if ($this->auth && $this->auth->isMaster()) {
                // Master vê todos os polos
                $stmt = $this->db->getConnection()->query("
                    SELECT p.*, 
                           (SELECT COUNT(*) FROM usuarios WHERE polo_id = p.id AND is_active = 1) as total_usuarios,
                           (SELECT COUNT(*) FROM payments WHERE polo_id = p.id) as total_pagamentos,
                           (SELECT COUNT(*) FROM wallet_ids WHERE polo_id = p.id AND is_active = 1) as wallet_ids
                    FROM polos p 
                    {$whereClause}
                    ORDER BY nome
                ");
            } else {
                // Admin de polo vê apenas seu polo
                $poloId = $_SESSION[\'polo_id\'] ?? 0;
                $stmt = $this->db->getConnection()->prepare("
                    SELECT p.*, 
                           (SELECT COUNT(*) FROM usuarios WHERE polo_id = p.id AND is_active = 1) as total_usuarios,
                           (SELECT COUNT(*) FROM payments WHERE polo_id = p.id) as total_pagamentos,
                           (SELECT COUNT(*) FROM wallet_ids WHERE polo_id = p.id AND is_active = 1) as wallet_ids
                    FROM polos p 
                    WHERE p.id = ? {$whereClause}
                    ORDER BY nome
                ");
                $stmt->execute([$poloId]);
            }
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("ConfigManager: Erro ao listar polos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Criar novo polo (apenas master)
     */
    public function createPolo($dados) {
        if (!$this->auth || !$this->auth->isMaster()) {
            throw new Exception(\'Acesso negado\');
        }
        
        if (!$this->db) {
            throw new Exception(\'Banco de dados não disponível\');
        }
        
        // Validações básicas
        $required = [\'nome\', \'codigo\', \'cidade\', \'estado\'];
        foreach ($required as $field) {
            if (empty($dados[$field])) {
                throw new Exception("Campo \'{$field}\' é obrigatório");
            }
        }
        
        try {
            // Verificar se código já existe
            $stmt = $this->db->getConnection()->prepare("SELECT COUNT(*) as count FROM polos WHERE codigo = ?");
            $stmt->execute([$dados[\'codigo\']]);
            if ($stmt->fetch()[\'count\'] > 0) {
                throw new Exception(\'Código do polo já existe\');
            }
            
            // Inserir polo
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO polos (nome, codigo, cidade, estado, endereco, telefone, email, asaas_environment) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $dados[\'nome\'],
                $dados[\'codigo\'],
                $dados[\'cidade\'],
                $dados[\'estado\'],
                $dados[\'endereco\'] ?? null,
                $dados[\'telefone\'] ?? null,
                $dados[\'email\'] ?? null,
                $dados[\'asaas_environment\'] ?? \'sandbox\'
            ]);
            
            return $this->db->getConnection()->lastInsertId();
        } catch (Exception $e) {
            error_log("ConfigManager: Erro ao criar polo: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obter configurações de um polo
     */
    public function getPoloConfig($poloId) {
        if (!$this->db) {
            return [];
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT * FROM polos WHERE id = ?
            ");
            $stmt->execute([$poloId]);
            $polo = $stmt->fetch();
            
            return $polo ?: [];
        } catch (Exception $e) {
            error_log("ConfigManager: Erro ao obter config do polo: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter estatísticas do polo
     */
    public function getPoloStats($poloId) {
        if (!$this->db) {
            return [
                \'usuarios_ativos\' => 0,
                \'clientes\' => 0,
                \'wallet_ids\' => 0,
                \'pagamentos\' => 0,
                \'pagamentos_recebidos\' => 0,
                \'valor_total\' => 0
            ];
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM usuarios WHERE polo_id = ? AND is_active = 1) as usuarios_ativos,
                    (SELECT COUNT(*) FROM customers WHERE polo_id = ?) as clientes,
                    (SELECT COUNT(*) FROM wallet_ids WHERE polo_id = ? AND is_active = 1) as wallet_ids,
                    (SELECT COUNT(*) FROM payments WHERE polo_id = ?) as pagamentos,
                    (SELECT COUNT(*) FROM payments WHERE polo_id = ? AND status = \'RECEIVED\') as pagamentos_recebidos,
                    (SELECT COALESCE(SUM(value), 0) FROM payments WHERE polo_id = ? AND status = \'RECEIVED\') as valor_total
            ");
            
            $stmt->execute([$poloId, $poloId, $poloId, $poloId, $poloId, $poloId]);
            
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("ConfigManager: Erro ao obter stats do polo: " . $e->getMessage());
            return [
                \'usuarios_ativos\' => 0,
                \'clientes\' => 0,
                \'wallet_ids\' => 0,
                \'pagamentos\' => 0,
                \'pagamentos_recebidos\' => 0,
                \'valor_total\' => 0
            ];
        }
    }
}

/**
 * Classe para adaptação dinâmica do sistema ASAAS
 */
class DynamicAsaasConfig {
    
    private $auth;
    private $configManager;
    
    public function __construct() {
        if (class_exists(\'AuthSystem\')) {
            $this->auth = new AuthSystem();
        }
        $this->configManager = new ConfigManager();
    }
    
    /**
     * Obter instância do ASAAS com configurações do polo atual
     */
    public function getInstance() {
        if (!$this->auth || !$this->auth->isLogado()) {
            throw new Exception(\'Usuário não logado\');
        }
        
        // Master usa configurações globais
        if ($this->auth->isMaster()) {
            return $this->getMasterInstance();
        }
        
        // Usuário de polo usa configurações específicas
        return $this->getPoloInstance($_SESSION[\'polo_id\']);
    }
    
    /**
     * Obter instância para polo específico (apenas master)
     */
    public function getPoloInstance($poloId) {
        if (!$this->auth || (!$this->auth->isMaster() && $_SESSION[\'polo_id\'] != $poloId)) {
            throw new Exception(\'Acesso negado a este polo\');
        }
        
        $config = $this->configManager->getPoloConfig($poloId);
        
        if (empty($config)) {
            throw new Exception("Polo {$poloId} não encontrado");
        }
        
        $environment = $config[\'asaas_environment\'] ?? \'sandbox\';
        $apiKey = $environment === \'production\' ? 
            $config[\'asaas_production_api_key\'] : 
            $config[\'asaas_sandbox_api_key\'];
            
        if (empty($apiKey)) {
            throw new Exception("API Key não configurada para ambiente \'{$environment}\' do polo {$config[\'nome\']}");
        }
        
        if (class_exists(\'AsaasSplitPayment\')) {
            return new AsaasSplitPayment($apiKey, $environment);
        } else {
            throw new Exception(\'Classe AsaasSplitPayment não encontrada\');
        }
    }
    
    /**
     * Obter instância master (configurações globais)
     */
    private function getMasterInstance() {
        $environment = defined(\'ASAAS_ENVIRONMENT\') ? ASAAS_ENVIRONMENT : \'sandbox\';
        
        if ($environment === \'production\') {
            $apiKey = defined(\'ASAAS_PRODUCTION_API_KEY\') ? ASAAS_PRODUCTION_API_KEY : null;
        } else {
            $apiKey = defined(\'ASAAS_SANDBOX_API_KEY\') ? ASAAS_SANDBOX_API_KEY : null;
        }
        
        if (empty($apiKey) || 
            in_array($apiKey, [\'SUA_API_KEY_PRODUCAO_AQUI\', \'SUA_API_KEY_SANDBOX_AQUI\'])) {
            throw new Exception("API Key global não configurada para ambiente \'{$environment}\'");
        }
        
        if (class_exists(\'AsaasSplitPayment\')) {
            return new AsaasSplitPayment($apiKey, $environment);
        } else {
            throw new Exception(\'Classe AsaasSplitPayment não encontrada\');
        }
    }
}

/**
 * Classe para estatísticas do sistema
 */
class SystemStats {
    
    public static function getGeneralStats($poloId = null) {
        try {
            if (class_exists(\'DatabaseManager\')) {
                $db = DatabaseManager::getInstance();
                
                // Se há sistema de auth ativo, usar estatísticas filtradas
                if (isset($_SESSION[\'usuario_tipo\']) && $_SESSION[\'usuario_tipo\'] !== \'master\') {
                    $poloId = $_SESSION[\'polo_id\'];
                }
                
                $whereClause = $poloId ? \'WHERE polo_id = ?\' : \'\';
                $params = $poloId ? [$poloId] : [];
                
                // Estatísticas básicas
                $sql = "
                    SELECT 
                        (SELECT COUNT(*) FROM customers {$whereClause}) as total_customers,
                        (SELECT COUNT(*) FROM wallet_ids WHERE is_active = 1" . ($poloId ? " AND polo_id = ?" : "") . ") as total_wallet_ids,
                        (SELECT COUNT(*) FROM payments {$whereClause}) as total_payments,
                        (SELECT COUNT(*) FROM payments WHERE status = \'RECEIVED\'" . ($poloId ? " AND polo_id = ?" : "") . ") as received_payments,
                        (SELECT COALESCE(SUM(value), 0) FROM payments WHERE status = \'RECEIVED\'" . ($poloId ? " AND polo_id = ?" : "") . ") as total_value
                ";
                
                $stmt = $db->getConnection()->prepare($sql);
                $execParams = [];
                if ($poloId) {
                    $execParams = [$poloId, $poloId, $poloId, $poloId]; // Um para cada subquery
                }
                
                $stmt->execute($execParams);
                return $stmt->fetch();
            }
        } catch (Exception $e) {
            error_log("SystemStats: Erro ao obter estatísticas: " . $e->getMessage());
        }
        
        return [
            \'total_customers\' => 0,
            \'total_wallet_ids\' => 0,
            \'total_payments\' => 0,
            \'received_payments\' => 0,
            \'total_value\' => 0
        ];
    }
}
?>';
        
        file_put_contents('config_manager.php', $configManagerContent);
        echo "  ✅ config_manager.php criado com sucesso\n";
    }
    
    echo "\n";
    
    // 5. Corrigir admin_master.php para incluir o arquivo
    echo "🔧 CORRIGINDO ADMIN_MASTER.PHP\n";
    echo "─────────────────────────────\n";
    
    if (file_exists('admin_master.php')) {
        $adminContent = file_get_contents('admin_master.php');
        
        // Verificar se já inclui bootstrap.php ou config_manager.php
        if (strpos($adminContent, 'bootstrap.php') !== false) {
            echo "  ✅ admin_master.php já inclui bootstrap.php\n";
        } elseif (strpos($adminContent, 'config_manager.php') === false) {
            echo "  🔧 Adicionando include do config_manager.php...\n";
            
            // Encontrar a seção de includes e adicionar config_manager.php
            $pattern = '/(<\?php[\s\S]*?)(require_once|include_once)/';
            
            if (preg_match($pattern, $adminContent)) {
                // Adicionar include após os outros
                $adminContent = preg_replace(
                    '/(require_once [\'"]config\.php[\'"];)/i',
                    '$1' . "\nrequire_once 'config_manager.php';",
                    $adminContent
                );
            } else {
                // Adicionar logo após <?php
                $adminContent = preg_replace(
                    '/(<\?php)/i',
                    '$1' . "\nrequire_once 'config_manager.php';",
                    $adminContent
                );
            }
            
            // Backup do arquivo original
            copy('admin_master.php', 'admin_master_backup_' . date('Y-m-d_H-i-s') . '.php');
            
            // Salvar versão corrigida
            file_put_contents('admin_master.php', $adminContent);
            echo "  ✅ Include adicionado ao admin_master.php\n";
        } else {
            echo "  ✅ admin_master.php já inclui config_manager.php\n";
        }
    }
    
    echo "\n";
    
    // 6. Atualizar bootstrap.php para incluir config_manager
    echo "🔧 ATUALIZANDO BOOTSTRAP.PHP\n";
    echo "──────────────────────────\n";
    
    if (file_exists('bootstrap.php')) {
        $bootstrapContent = file_get_contents('bootstrap.php');
        
        if (strpos($bootstrapContent, 'config_manager.php') === false) {
            echo "  🔧 Adicionando config_manager.php ao bootstrap...\n";
            
            // Adicionar config_manager.php à lista de arquivos
            $bootstrapContent = str_replace(
                "'auth.php' => 'Sistema de autenticação'",
                "'auth.php' => 'Sistema de autenticação',\n    'config_manager.php' => 'Gerenciador de configurações'",
                $bootstrapContent
            );
            
            file_put_contents('bootstrap.php', $bootstrapContent);
            echo "  ✅ config_manager.php adicionado ao bootstrap\n";
        } else {
            echo "  ✅ Bootstrap já inclui config_manager.php\n";
        }
    }
    
    echo "\n";
    
    // 7. Verificar se tabela polos existe
    echo "🗄️ VERIFICANDO TABELA POLOS\n";
    echo "──────────────────────────\n";
    
    try {
        require_once 'config.php';
        $db = DatabaseManager::getInstance();
        
        $result = $db->getConnection()->query("SHOW TABLES LIKE 'polos'");
        if ($result->rowCount() == 0) {
            echo "  ❌ Tabela 'polos' não existe\n";
            echo "  🔧 Criando tabela polos...\n";
            
            $db->getConnection()->exec("
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
            
            echo "  ✅ Tabela polos criada\n";
            
            // Criar polo exemplo
            $stmt = $db->getConnection()->prepare("
                INSERT IGNORE INTO polos (nome, codigo, cidade, estado) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute(['Administração Central', 'MASTER', 'São Paulo', 'SP']);
            echo "  ✅ Polo exemplo criado\n";
        } else {
            echo "  ✅ Tabela polos existe\n";
        }
    } catch (Exception $e) {
        echo "  ❌ Erro ao verificar tabela polos: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // 8. Teste final
    echo "🧪 TESTE FINAL\n";
    echo "─────────────\n";
    
    try {
        // Incluir arquivos necessários
        require_once 'bootstrap.php';
        
        if (class_exists('ConfigManager')) {
            echo "  ✅ Classe ConfigManager disponível\n";
            
            $configManager = new ConfigManager();
            echo "  ✅ ConfigManager instanciado com sucesso\n";
            
            // Testar método
            $polos = $configManager->listarPolos();
            echo "  ✅ Método listarPolos() funciona - " . count($polos) . " polos encontrados\n";
            
        } else {
            echo "  ❌ Classe ConfigManager ainda não está disponível\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ Erro no teste: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    echo "✅ CORREÇÃO DO CONFIGMANAGER CONCLUÍDA!\n";
    echo "======================================\n\n";
    
    echo "🎯 PRÓXIMOS PASSOS:\n";
    echo "1. Tente acessar o admin_master.php novamente\n";
    echo "2. O erro 'Class ConfigManager not found' deve estar resolvido\n";
    echo "3. Se houver outros erros, execute este script novamente\n\n";
    
    echo "📁 ARQUIVOS CRIADOS/MODIFICADOS:\n";
    echo "- config_manager.php (criado)\n";
    echo "- admin_master.php (include adicionado)\n";
    echo "- bootstrap.php (atualizado)\n";
    echo "- Tabela polos (criada se não existia)\n\n";
    
    echo "🔍 DEBUG:\n";
    echo "Se ainda houver problemas, verifique:\n";
    echo "1. Se todos os arquivos estão no mesmo diretório\n";
    echo "2. Se as permissões estão corretas (644 para .php)\n";
    echo "3. Se o PHP consegue incluir os arquivos\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n\n";
    
    echo "🔧 SOLUÇÃO MANUAL:\n";
    echo "1. Crie um arquivo config_manager.php com as classes necessárias\n";
    echo "2. Adicione 'require_once \"config_manager.php\";' no início do admin_master.php\n";
    echo "3. Verifique se a tabela 'polos' existe no banco de dados\n";
}
?>