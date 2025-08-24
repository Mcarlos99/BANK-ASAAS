<?php
/**
 * Gerenciador de Configurações ASAAS por Polo
 * Arquivo: config_manager.php
 */

require_once 'config_api.php';
require_once 'config.php';
require_once 'auth.php';

// Verificar se está logado
$auth->requireLogin();

/**
 * Classe para gerenciar configurações dos polos
 */
class ConfigManager {
    
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->auth = new AuthSystem();
    }
    
    /**
     * Obter configurações de um polo
     */
    public function getPoloConfig($poloId) {
        // Verificar permissão
        if (!$this->auth->isMaster() && $_SESSION['polo_id'] != $poloId) {
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
        if (!$this->auth->isMaster() && $_SESSION['polo_id'] != $poloId) {
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
        $configAuditoria = $config;
        if (!empty($configAuditoria['production_key'])) {
            $configAuditoria['production_key'] = $this->maskApiKey($config['production_key']);
        }
        if (!empty($configAuditoria['sandbox_key'])) {
            $configAuditoria['sandbox_key'] = $this->maskApiKey($config['sandbox_key']);
        }
        
        $this->logAuditoria(
            $_SESSION['usuario_id'],
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
        $this->logAuditoria(
            $_SESSION['usuario_id'],
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
        
        return $result;
    }
    
    /**
     * Listar polos (baseado na permissão do usuário)
     */
    public function listPolos() {
        if ($this->auth->isMaster()) {
            // Master vê todos os polos
            $stmt = $this->db->getConnection()->query("
                SELECT p.*, 
                       (SELECT COUNT(*) FROM usuarios WHERE polo_id = p.id AND is_active = 1) as total_usuarios,
                       (SELECT COUNT(*) FROM payments WHERE polo_id = p.id) as total_pagamentos
                FROM polos p 
                ORDER BY nome
            ");
        } else {
            // Admin de polo vê apenas seu polo
            $stmt = $this->db->getConnection()->prepare("
                SELECT p.*, 
                       (SELECT COUNT(*) FROM usuarios WHERE polo_id = p.id AND is_active = 1) as total_usuarios,
                       (SELECT COUNT(*) FROM payments WHERE polo_id = p.id) as total_pagamentos
                FROM polos p 
                WHERE p.id = ?
                ORDER BY nome
            ");
            $stmt->execute([$_SESSION['polo_id']]);
        }
        
        return $stmt->fetchAll();
    }
    
    /**
     * Criar novo polo (apenas master)
     */
    public function createPolo($dados) {
        if (!$this->auth->isMaster()) {
            throw new Exception('Acesso negado');
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
        $this->logAuditoria(
            $_SESSION['usuario_id'],
            $poloId,
            'criar_polo',
            'polos',
            $poloId,
            $dados
        );
        
        return $poloId;
    }
    
    /**
     * Obter estatísticas do polo
     */
    public function getPoloStats($poloId) {
        if (!$this->auth->isMaster() && $_SESSION['polo_id'] != $poloId) {
            throw new Exception('Acesso negado a este polo');
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                (SELECT COUNT(*) FROM usuarios WHERE polo_id = ? AND is_active = 1) as usuarios_ativos,
                (SELECT COUNT(*) FROM customers WHERE polo_id = ?) as clientes,
                (SELECT COUNT(*) FROM wallet_ids WHERE polo_id = ? AND is_active = 1) as wallet_ids,
                (SELECT COUNT(*) FROM payments WHERE polo_id = ?) as pagamentos,
                (SELECT COUNT(*) FROM payments WHERE polo_id = ? AND status = 'RECEIVED') as pagamentos_recebidos,
                (SELECT COALESCE(SUM(value), 0) FROM payments WHERE polo_id = ? AND status = 'RECEIVED') as valor_total
        ");
        
        $stmt->execute([$poloId, $poloId, $poloId, $poloId, $poloId, $poloId]);
        
        return $stmt->fetch();
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
        $this->auth = new AuthSystem();
        $this->configManager = new ConfigManager();
    }
    
    /**
     * Obter instância do ASAAS com configurações do polo atual
     */
    public function getInstance() {
        if (!$this->auth->isLogado()) {
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
        if (!$this->auth->isMaster() && $_SESSION['polo_id'] != $poloId) {
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

// Instâncias globais
$configManager = new ConfigManager();
$dynamicAsaas = new DynamicAsaasConfig();