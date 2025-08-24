<?php
/**
 * Gerenciador de Configurações ASAAS por Polo - VERSÃO CORRIGIDA
 * Arquivo: config_manager.php
 */

require_once 'config_api.php';
require_once 'config.php';

/**
 * Classe para gerenciar configurações dos polos - COMPLETA
 */
class ConfigManager {
    
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        
        // Criar AuthSystem apenas se não foi passado como parâmetro
        if (class_exists('AuthSystem')) {
            $this->auth = new AuthSystem();
        }
    }
    
    /**
     * Listar polos com informações extras - MÉTODO CORRIGIDO
     */
    public function listarPolos($incluirInativos = false) {
        // Verificar permissão apenas se AuthSystem estiver disponível
        if ($this->auth && !$this->auth->isMaster()) {
            throw new Exception('Acesso negado - apenas Master Admin pode listar todos os polos');
        }
        
        $whereClause = $incluirInativos ? '' : 'WHERE p.is_active = 1';
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT p.*, 
                       (SELECT COUNT(*) FROM usuarios WHERE polo_id = p.id AND is_active = 1) as total_usuarios,
                       (SELECT COUNT(*) FROM payments WHERE polo_id = p.id) as total_pagamentos,
                       (SELECT COUNT(*) FROM customers WHERE polo_id = p.id) as total_clientes,
                       (SELECT COUNT(*) FROM wallet_ids WHERE polo_id = p.id AND is_active = 1) as total_wallet_ids,
                       (SELECT COALESCE(SUM(value), 0) FROM payments WHERE polo_id = p.id AND status = 'RECEIVED') as valor_total_recebido
                FROM polos p 
                {$whereClause}
                ORDER BY p.nome
            ");
            
            $stmt->execute();
            $polos = $stmt->fetchAll();
            
            // Adicionar informações extras para cada polo
            foreach ($polos as &$polo) {
                $polo['total_usuarios'] = (int)$polo['total_usuarios'];
                $polo['total_pagamentos'] = (int)$polo['total_pagamentos'];
                $polo['total_clientes'] = (int)$polo['total_clientes'];
                $polo['total_wallet_ids'] = (int)$polo['total_wallet_ids'];
                $polo['valor_total_recebido'] = (float)$polo['valor_total_recebido'];
                
                // Status da configuração ASAAS
                $polo['asaas_configurado'] = !empty($polo['asaas_production_api_key']) || !empty($polo['asaas_sandbox_api_key']);
                
                // Último pagamento
                $stmtLastPayment = $this->db->getConnection()->prepare("
                    SELECT created_at FROM payments WHERE polo_id = ? ORDER BY created_at DESC LIMIT 1
                ");
                $stmtLastPayment->execute([$polo['id']]);
                $lastPayment = $stmtLastPayment->fetch();
                $polo['ultimo_pagamento'] = $lastPayment ? $lastPayment['created_at'] : null;
            }
            
            return $polos;
            
        } catch (Exception $e) {
            error_log("ConfigManager: Erro ao listar polos: " . $e->getMessage());
            throw new Exception("Erro ao listar polos: " . $e->getMessage());
        }
    }
    
    /**
     * Listar apenas polos básicos (compatibilidade com admin_master.php)
     */
    public function listPolos($incluirInativos = false) {
        return $this->listarPolos($incluirInativos);
    }
    
    /**
     * Obter configurações de um polo
     */
    public function getPoloConfig($poloId) {
        // Verificar permissão
        if ($this->auth && !$this->auth->isMaster() && isset($_SESSION['polo_id']) && $_SESSION['polo_id'] != $poloId) {
            throw new Exception('Acesso negado a este polo');
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM polos WHERE id = ?
        ");
        $stmt->execute([$poloId]);
        $polo = $stmt->fetch();
        
        if (!$polo) {
            throw new Exception('Polo não encontrado');
        }
        
        return $polo;
    }
    
    /**
     * Atualizar configurações ASAAS de um polo
     */
    public function updateAsaasConfig($poloId, $config) {
        // Verificar permissão
        if ($this->auth && !$this->auth->isMaster() && isset($_SESSION['polo_id']) && $_SESSION['polo_id'] != $poloId) {
            throw new Exception('Acesso negado a este polo');
        }
        
        // Validações
        if (!in_array($config['environment'], ['sandbox', 'production'])) {
            throw new Exception('Ambiente inválido');
        }
        
        // Validar formato das API Keys
        if (!empty($config['production_key']) && !$this->isValidApiKey($config['production_key'])) {
            throw new Exception('Formato de API Key de produção inválido');
        }
        
        if (!empty($config['sandbox_key']) && !$this->isValidApiKey($config['sandbox_key'])) {
            throw new Exception('Formato de API Key de sandbox inválido');
        }
        
        // Buscar dados anteriores para auditoria
        $configAnterior = $this->getPoloConfig($poloId);
        
        // Atualizar configurações
        $stmt = $this->db->getConnection()->prepare("
            UPDATE polos 
            SET asaas_environment = ?, 
                asaas_production_api_key = ?, 
                asaas_sandbox_api_key = ?, 
                asaas_webhook_token = ?,
                data_atualizacao = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $config['environment'],
            $config['production_key'],
            $config['sandbox_key'],
            $config['webhook_token'],
            $poloId
        ]);
        
        // Log de auditoria (sem expor as chaves completas)
        if ($this->auth && method_exists($this, 'logAuditoria')) {
            $configAuditoria = $config;
            if (!empty($configAuditoria['production_key'])) {
                $configAuditoria['production_key'] = $this->maskApiKey($config['production_key']);
            }
            if (!empty($configAuditoria['sandbox_key'])) {
                $configAuditoria['sandbox_key'] = $this->maskApiKey($config['sandbox_key']);
            }
            
            $this->logAuditoria(
                $_SESSION['usuario_id'] ?? null,
                $poloId,
                'atualizar_config_asaas',
                'polos',
                $poloId,
                $configAuditoria,
                [
                    'environment_anterior' => $configAnterior['asaas_environment'],
                    'production_key_anterior' => $this->maskApiKey($configAnterior['asaas_production_api_key']),
                    'sandbox_key_anterior' => $this->maskApiKey($configAnterior['asaas_sandbox_api_key'])
                ]
            );
        }
        
        return true;
    }
    
    /**
     * Testar configurações ASAAS
     */
    public function testAsaasConfig($poloId, $environment = null) {
        $config = $this->getPoloConfig($poloId);
        
        $env = $environment ?? $config['asaas_environment'];
        $apiKey = $env === 'production' ? 
            $config['asaas_production_api_key'] : 
            $config['asaas_sandbox_api_key'];
            
        if (empty($apiKey)) {
            throw new Exception('API Key não configurada para ambiente ' . $env);
        }
        
        // Testar conexão
        $result = $this->testApiConnection($apiKey, $env);
        
        // Log do teste
        if ($this->auth && method_exists($this, 'logAuditoria')) {
            $this->logAuditoria(
                $_SESSION['usuario_id'] ?? null,
                $poloId,
                'testar_config_asaas',
                'polos',
                $poloId,
                [
                    'environment' => $env,
                    'result' => $result['success'] ? 'sucesso' : 'falha',
                    'message' => $result['message']
                ]
            );
        }
        
        return $result;
    }
    
    /**
     * Criar novo polo (apenas master)
     */
    public function createPolo($dados) {
        if ($this->auth && !$this->auth->isMaster()) {
            throw new Exception('Acesso negado - apenas Master Admin pode criar polos');
        }
        
        // Validações
        $required = ['nome', 'codigo', 'cidade', 'estado'];
        foreach ($required as $field) {
            if (empty($dados[$field])) {
                throw new Exception("Campo '{$field}' é obrigatório");
            }
        }
        
        // Verificar se código já existe
        $stmt = $this->db->getConnection()->prepare("SELECT COUNT(*) as count FROM polos WHERE codigo = ?");
        $stmt->execute([$dados['codigo']]);
        if ($stmt->fetch()['count'] > 0) {
            throw new Exception('Código do polo já existe');
        }
        
        // Inserir polo
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO polos (nome, codigo, cidade, estado, endereco, telefone, email, asaas_environment) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $dados['nome'],
            $dados['codigo'],
            $dados['cidade'],
            $dados['estado'],
            $dados['endereco'] ?? null,
            $dados['telefone'] ?? null,
            $dados['email'] ?? null,
            $dados['asaas_environment'] ?? 'sandbox'
        ]);
        
        $poloId = $this->db->getConnection()->lastInsertId();
        
        // Log de auditoria
        if ($this->auth && method_exists($this, 'logAuditoria')) {
            $this->logAuditoria(
                $_SESSION['usuario_id'] ?? null,
                $poloId,
                'criar_polo',
                'polos',
                $poloId,
                $dados
            );
        }
        
        return $poloId;
    }
    
    /**
     * Obter estatísticas detalhadas do polo
     */
    public function getPoloStats($poloId) {
        if ($this->auth && !$this->auth->isMaster() && isset($_SESSION['polo_id']) && $_SESSION['polo_id'] != $poloId) {
            throw new Exception('Acesso negado a este polo');
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                (SELECT COUNT(*) FROM usuarios WHERE polo_id = ? AND is_active = 1) as usuarios_ativos,
                (SELECT COUNT(*) FROM customers WHERE polo_id = ?) as clientes,
                (SELECT COUNT(*) FROM wallet_ids WHERE polo_id = ? AND is_active = 1) as wallet_ids,
                (SELECT COUNT(*) FROM payments WHERE polo_id = ?) as pagamentos,
                (SELECT COUNT(*) FROM payments WHERE polo_id = ? AND status = 'RECEIVED') as pagamentos_recebidos,
                (SELECT COALESCE(SUM(value), 0) FROM payments WHERE polo_id = ? AND status = 'RECEIVED') as valor_total,
                (SELECT COUNT(*) FROM split_accounts WHERE polo_id = ?) as contas_split
        ");
        
        $stmt->execute([$poloId, $poloId, $poloId, $poloId, $poloId, $poloId, $poloId]);
        $stats = $stmt->fetch();
        
        // Conversão para tipos corretos
        return [
            'usuarios_ativos' => (int)$stats['usuarios_ativos'],
            'clientes' => (int)$stats['clientes'],
            'wallet_ids' => (int)$stats['wallet_ids'],
            'pagamentos' => (int)$stats['pagamentos'],
            'pagamentos_recebidos' => (int)$stats['pagamentos_recebidos'],
            'valor_total' => (float)$stats['valor_total'],
            'contas_split' => (int)$stats['contas_split'],
            'taxa_conversao' => $stats['pagamentos'] > 0 ? 
                round(($stats['pagamentos_recebidos'] / $stats['pagamentos']) * 100, 2) : 0
        ];
    }
    
    /**
     * Obter polo por ID
     */
    public function getPolo($poloId) {
        return $this->getPoloConfig($poloId);
    }
    
    /**
     * Verificar se polo existe e está ativo
     */
    public function isPoloAtivo($poloId) {
        $stmt = $this->db->getConnection()->prepare("
            SELECT is_active FROM polos WHERE id = ?
        ");
        $stmt->execute([$poloId]);
        $polo = $stmt->fetch();
        
        return $polo && $polo['is_active'];
    }
    
    /**
     * Alternar status do polo (ativar/desativar)
     */
    public function togglePoloStatus($poloId) {
        if ($this->auth && !$this->auth->isMaster()) {
            throw new Exception('Acesso negado');
        }
        
        $stmt = $this->db->getConnection()->prepare("
            UPDATE polos 
            SET is_active = NOT is_active, 
                data_atualizacao = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        return $stmt->execute([$poloId]);
    }
    
    /**
     * Obter lista de usuários do sistema
     */
    public function getUsuarios($poloId = null, $incluirInativos = false) {
        if ($this->auth && !$this->auth->isMaster()) {
            throw new Exception('Acesso negado');
        }
        
        $whereClause = '';
        $params = [];
        
        if ($poloId) {
            $whereClause = 'WHERE u.polo_id = ?';
            $params[] = $poloId;
        }
        
        if (!$incluirInativos) {
            $whereClause .= ($whereClause ? ' AND' : 'WHERE') . ' u.is_active = 1';
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT u.*, p.nome as polo_nome, p.codigo as polo_codigo
            FROM usuarios u
            LEFT JOIN polos p ON u.polo_id = p.id
            {$whereClause}
            ORDER BY u.nome
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Criar novo usuário
     */
    public function createUsuario($dados) {
        if ($this->auth && !$this->auth->isMaster()) {
            throw new Exception('Acesso negado');
        }
        
        // Validações básicas
        $required = ['nome', 'email', 'senha', 'tipo'];
        foreach ($required as $field) {
            if (empty($dados[$field])) {
                throw new Exception("Campo '{$field}' é obrigatório");
            }
        }
        
        // Verificar se email já existe
        $stmt = $this->db->getConnection()->prepare("SELECT COUNT(*) as count FROM usuarios WHERE email = ?");
        $stmt->execute([$dados['email']]);
        if ($stmt->fetch()['count'] > 0) {
            throw new Exception('Email já está em uso');
        }
        
        // Validar tipo de usuário
        $tiposValidos = ['master', 'admin_polo', 'operador'];
        if (!in_array($dados['tipo'], $tiposValidos)) {
            throw new Exception('Tipo de usuário inválido');
        }
        
        // Para admin_polo e operador, polo_id é obrigatório
        if (in_array($dados['tipo'], ['admin_polo', 'operador']) && empty($dados['polo_id'])) {
            throw new Exception('Polo é obrigatório para este tipo de usuário');
        }
        
        // Hash da senha
        $senhaHash = password_hash($dados['senha'], PASSWORD_DEFAULT);
        
        // Inserir usuário
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO usuarios (polo_id, nome, email, senha, tipo, criado_por) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $dados['polo_id'] ?? null,
            $dados['nome'],
            $dados['email'],
            $senhaHash,
            $dados['tipo'],
            $_SESSION['usuario_id'] ?? null
        ]);
        
        $usuarioId = $this->db->getConnection()->lastInsertId();
        
        // Log de auditoria
        if ($this->auth && method_exists($this, 'logAuditoria')) {
            $this->logAuditoria(
                $_SESSION['usuario_id'] ?? null,
                $dados['polo_id'] ?? null,
                'criar_usuario',
                'usuarios',
                $usuarioId,
                array_merge($dados, ['senha' => '[SENHA OCULTA]'])
            );
        }
        
        return $usuarioId;
    }
    
    // =====================================
    // MÉTODOS PRIVADOS
    // =====================================
    
    /**
     * Validar formato da API Key
     */
    private function isValidApiKey($apiKey) {
        return preg_match('/^\$aact_[a-zA-Z0-9_]+$/', $apiKey);
    }
    
    /**
     * Mascarar API Key para logs
     */
    private function maskApiKey($apiKey) {
        if (empty($apiKey)) {
            return null;
        }
        
        return substr($apiKey, 0, 20) . '...' . substr($apiKey, -8);
    }
    
    /**
     * Testar conexão com API do ASAAS
     */
    private function testApiConnection($apiKey, $environment) {
        try {
            $baseUrl = $environment === 'production' ? 
                'https://www.asaas.com/api/v3' : 
                'https://sandbox.asaas.com/api/v3';
                
            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $baseUrl . '/accounts?limit=1&offset=0',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'access_token: ' . $apiKey
                ]
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            
            curl_close($curl);
            
            if ($error) {
                return [
                    'success' => false,
                    'message' => 'Erro de conexão: ' . $error
                ];
            }
            
            if ($httpCode !== 200) {
                $decodedResponse = json_decode($response, true);
                $errorMsg = isset($decodedResponse['errors']) ? 
                    implode(', ', $decodedResponse['errors']) : 
                    'Erro HTTP ' . $httpCode;
                    
                return [
                    'success' => false,
                    'message' => $errorMsg
                ];
            }
            
            $decodedResponse = json_decode($response, true);
            
            return [
                'success' => true,
                'message' => 'Conexão estabelecida com sucesso',
                'data' => [
                    'environment' => $environment,
                    'total_accounts' => $decodedResponse['totalCount'] ?? 0
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Log de auditoria
     */
    private function logAuditoria($usuarioId, $poloId, $acao, $tabela = null, $registroId = null, $dadosNovos = null, $dadosAnteriores = null) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO auditoria (usuario_id, polo_id, acao, tabela, registro_id, dados_anteriores, dados_novos, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $usuarioId,
                $poloId,
                $acao,
                $tabela,
                $registroId,
                $dadosAnteriores ? json_encode($dadosAnteriores) : null,
                $dadosNovos ? json_encode($dadosNovos) : null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Erro ao gravar auditoria: " . $e->getMessage());
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
        if (class_exists('AuthSystem')) {
            $this->auth = new AuthSystem();
        }
        $this->configManager = new ConfigManager();
    }
    
    /**
     * Obter instância do ASAAS com configurações do polo atual
     */
    public function getInstance() {
        if (!$this->auth || !$this->auth->isLogado()) {
            throw new Exception('Usuário não logado');
        }
        
        // Master usa configurações globais
        if ($this->auth->isMaster()) {
            return $this->getMasterInstance();
        }
        
        // Usuário de polo usa configurações específicas
        return $this->getPoloInstance($_SESSION['polo_id']);
    }
    
    /**
     * Obter instância para polo específico (apenas master)
     */
    public function getPoloInstance($poloId) {
        if ($this->auth && !$this->auth->isMaster() && isset($_SESSION['polo_id']) && $_SESSION['polo_id'] != $poloId) {
            throw new Exception('Acesso negado a este polo');
        }
        
        $config = $this->configManager->getPoloConfig($poloId);
        
        $environment = $config['asaas_environment'];
        $apiKey = $environment === 'production' ? 
            $config['asaas_production_api_key'] : 
            $config['asaas_sandbox_api_key'];
            
        if (empty($apiKey)) {
            throw new Exception("API Key não configurada para ambiente '{$environment}' do polo {$config['nome']}");
        }
        
        return new AsaasSplitPayment($apiKey, $environment);
    }
    
    /**
     * Obter instância master (configurações globais)
     */
    private function getMasterInstance() {
        $environment = defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'sandbox';
        
        if ($environment === 'production') {
            $apiKey = defined('ASAAS_PRODUCTION_API_KEY') ? ASAAS_PRODUCTION_API_KEY : null;
        } else {
            $apiKey = defined('ASAAS_SANDBOX_API_KEY') ? ASAAS_SANDBOX_API_KEY : null;
        }
        
        if (empty($apiKey) || 
            in_array($apiKey, ['SUA_API_KEY_PRODUCAO_AQUI', 'SUA_API_KEY_SANDBOX_AQUI'])) {
            throw new Exception("API Key global não configurada para ambiente '{$environment}'");
        }
        
        return new AsaasSplitPayment($apiKey, $environment);
    }
}

/**
 * Classe para relatórios do sistema
 */
class ReportsManager {
    
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    /**
     * Relatório de performance dos Wallet IDs
     */
    public function getWalletPerformanceReport($startDate, $endDate, $poloId = null) {
        $whereClause = "WHERE wi.is_active = 1";
        $params = [$startDate, $endDate];
        
        if ($poloId) {
            $whereClause .= " AND wi.polo_id = ?";
            $params[] = $poloId;
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                wi.*,
                COUNT(ps.id) as split_count,
                COALESCE(SUM(CASE 
                    WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                    ELSE (p.value * ps.percentage_value / 100) 
                END), 0) as total_earned,
                COALESCE(AVG(CASE 
                    WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                    ELSE (p.value * ps.percentage_value / 100) 
                END), 0) as avg_split_value,
                MIN(p.created_at) as first_split,
                MAX(p.created_at) as last_split
            FROM wallet_ids wi
            LEFT JOIN payment_splits ps ON wi.wallet_id = ps.wallet_id
            LEFT JOIN payments p ON ps.payment_id = p.id 
                AND p.status = 'RECEIVED' 
                AND p.created_at BETWEEN ? AND ?
            {$whereClause}
            GROUP BY wi.id
            ORDER BY total_earned DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

// Nota: Classe SystemStats já está definida em config.php
// Não redeclarar aqui para evitar erro "Cannot redeclare class"

// Instâncias globais
if (!isset($configManager)) {
    $configManager = new ConfigManager();
}

if (!isset($dynamicAsaas)) {
    $dynamicAsaas = new DynamicAsaasConfig();
}
?>