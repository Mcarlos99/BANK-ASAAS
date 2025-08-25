<?php
/**
 * Configurações do Sistema de Split ASAAS - Versão Multi-Tenant
 * Arquivo: config.php
 * Versão com suporte a múltiplos polos
 */

// Configurações MASTER (usadas apenas pelo admin master)
// Mantenha as configurações originais para compatibilidade
define('ASAAS_PRODUCTION_API_KEY', getenv('ASAAS_PRODUCTION_API_KEY') ?: '$aact_prod_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjdmNDZhZTU1LWVjYTgtNDY0Mi1hOTg5LTY0NmMxNmM1ZTFkNzo6JGFhY2hfMWYzOTgxNjEtZWRhNy00ZjhhLTk5MGQtNGYwZjY2MzJmZTJk');
define('ASAAS_SANDBOX_API_KEY', getenv('ASAAS_SANDBOX_API_KEY') ?: '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjYyNTE2NTRkLTlhMmYtNGUxMS1iN2NlLTg1ZTQ5OTJjOTYyYjo6JGFhY2hfZjc5MDNiNTUtOWQ3Ny00MDRiLTg4YjctY2YxZmNhNTY5OGY5');
define('ASAAS_WEBHOOK_TOKEN', getenv('ASAAS_WEBHOOK_TOKEN') ?: 'SEU_WEBHOOK_TOKEN_AQUI');
define('ASAAS_ENVIRONMENT', getenv('ASAAS_ENVIRONMENT') ?: 'production'); // Master sempre em produção

// Configurações do Banco de Dados
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'bankdb');
define('DB_USER', getenv('DB_USER') ?: 'bankuser');
define('DB_PASS', getenv('DB_PASS') ?: 'lKVX4Ew0u7I89hAUuDCm');
define('DB_CHARSET', 'utf8mb4');

// Configurações de Sistema
define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'INFO');
define('LOG_RETENTION_DAYS', 30);
define('WEBHOOK_TIMEOUT', 30);

/**
 * Classe para gerenciar conexão com banco de dados (atualizada)
 */
class DatabaseManager {
    
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new Exception("Erro na conexão com banco: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
/**
     * Salvar Wallet ID com suporte a polo - MÉTODO CORRIGIDO
     */
    public function saveWalletId($walletData) {
        try {
            // Log para debug
            error_log("DatabaseManager::saveWalletId - Dados recebidos: " . json_encode($walletData));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO wallet_ids (id, polo_id, wallet_id, name, description, is_active, created_at) 
                VALUES (:id, :polo_id, :wallet_id, :name, :description, :is_active, NOW())
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name),
                    description = VALUES(description),
                    is_active = VALUES(is_active),
                    updated_at = NOW()
            ");
            
            $params = [
                'id' => $walletData['id'],
                'polo_id' => $walletData['polo_id'], // Pode ser NULL para master
                'wallet_id' => $walletData['wallet_id'],
                'name' => $walletData['name'],
                'description' => $walletData['description'] ?? null,
                'is_active' => $walletData['is_active'] ?? 1
            ];
            
            // Log dos parâmetros para debug
            error_log("DatabaseManager::saveWalletId - Parâmetros SQL: " . json_encode($params));
            
            $result = $stmt->execute($params);
            
            if ($result) {
                error_log("DatabaseManager::saveWalletId - Sucesso! Wallet ID '{$walletData['wallet_id']}' salvo com polo_id: " . ($walletData['polo_id'] ?? 'NULL'));
            } else {
                error_log("DatabaseManager::saveWalletId - Erro na execução SQL");
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("DatabaseManager::saveWalletId - Erro PDO: " . $e->getMessage());
            error_log("DatabaseManager::saveWalletId - Dados que causaram erro: " . json_encode($walletData));
            return false;
        }
    }
    
    /**
     * Buscar Wallet IDs do polo atual
     */
    public function getActiveWalletIds($poloId = null) {
        try {
            if ($poloId === null && isset($_SESSION['polo_id'])) {
                $poloId = $_SESSION['polo_id'];
            }
            
            $whereClause = $poloId ? 'WHERE polo_id = ? AND is_active = 1' : 'WHERE is_active = 1';
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM wallet_ids 
                {$whereClause}
                ORDER BY name
            ");
            
            if ($poloId) {
                $stmt->execute([$poloId]);
            } else {
                $stmt->execute();
            }
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar Wallet IDs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Salvar cliente com polo
     */
    public function saveCustomer($customerData) {
        // Se não especificar polo_id, usar o polo do usuário logado
        if (!isset($customerData['polo_id']) && isset($_SESSION['polo_id'])) {
            $customerData['polo_id'] = $_SESSION['polo_id'];
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO customers (id, polo_id, name, email, cpf_cnpj, mobile_phone, address) 
            VALUES (:id, :polo_id, :name, :email, :cpf_cnpj, :mobile_phone, :address)
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                email = VALUES(email),
                mobile_phone = VALUES(mobile_phone),
                address = VALUES(address),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([
            'id' => $customerData['id'],
            'polo_id' => $customerData['polo_id'],
            'name' => $customerData['name'],
            'email' => $customerData['email'],
            'cpf_cnpj' => $customerData['cpfCnpj'],
            'mobile_phone' => $customerData['mobilePhone'] ?? null,
            'address' => $customerData['address'] ?? null
        ]);
    }
    
    /**
     * Salvar pagamento com polo
     */
    public function savePayment($paymentData) {
        // Se não especificar polo_id, usar o polo do usuário logado
        if (!isset($paymentData['polo_id']) && isset($_SESSION['polo_id'])) {
            $paymentData['polo_id'] = $_SESSION['polo_id'];
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO payments (id, polo_id, customer_id, billing_type, status, value, description, due_date, installment_count) 
            VALUES (:id, :polo_id, :customer_id, :billing_type, :status, :value, :description, :due_date, :installment_count)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([
            'id' => $paymentData['id'],
            'polo_id' => $paymentData['polo_id'],
            'customer_id' => $paymentData['customer'],
            'billing_type' => $paymentData['billingType'],
            'status' => $paymentData['status'],
            'value' => $paymentData['value'],
            'description' => $paymentData['description'],
            'due_date' => $paymentData['dueDate'],
            'installment_count' => $paymentData['installmentCount'] ?? 1
        ]);
    }
    
    /**
     * Buscar dados com filtro por polo
     */
    private function addPoloFilter($query, $params = [], $poloId = null) {
        if ($poloId === null && isset($_SESSION['polo_id'])) {
            $poloId = $_SESSION['polo_id'];
        }
        
        if ($poloId && !isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'master') {
            // Se não é master, filtrar por polo
            if (strpos(strtolower($query), 'where') !== false) {
                $query .= " AND polo_id = ?";
            } else {
                $query .= " WHERE polo_id = ?";
            }
            $params[] = $poloId;
        }
        
        return ['query' => $query, 'params' => $params];
    }
    
    /**
     * Buscar relatório de splits com filtro por polo
     */
    public function getSplitReport($startDate, $endDate, $poloId = null) {
        $baseQuery = "
            SELECT 
                COALESCE(sa.name, wi.name, ps.wallet_id) as account_name,
                ps.wallet_id,
                COUNT(ps.id) as payment_count,
                SUM(CASE 
                    WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                    ELSE (p.value * ps.percentage_value / 100) 
                END) as total_received,
                CASE 
                    WHEN sa.id IS NOT NULL THEN 'Conta Split'
                    WHEN wi.id IS NOT NULL THEN 'Wallet ID'
                    ELSE 'Desconhecido'
                END as source_type
            FROM payment_splits ps
            JOIN payments p ON ps.payment_id = p.id
            LEFT JOIN split_accounts sa ON ps.wallet_id = sa.wallet_id
            LEFT JOIN wallet_ids wi ON ps.wallet_id = wi.wallet_id
            WHERE p.status = 'RECEIVED' 
                AND p.received_date BETWEEN ? AND ?
        ";
        
        $params = [$startDate, $endDate];
        
        // Filtrar por polo se necessário
        $filtered = $this->addPoloFilter($baseQuery, $params, $poloId);
        
        $filtered['query'] .= " GROUP BY ps.wallet_id ORDER BY total_received DESC";
        
        $stmt = $this->pdo->prepare($filtered['query']);
        $stmt->execute($filtered['params']);
        
        return $stmt->fetchAll();
    }
    
    // ... outros métodos existentes mantidos para compatibilidade
    
    /**
     * Obter estatísticas com filtro por polo
     */
    public function getPoloFilteredStats($poloId = null) {
        if ($poloId === null && isset($_SESSION['polo_id'])) {
            $poloId = $_SESSION['polo_id'];
        }
        
        $whereClause = $poloId ? 'WHERE polo_id = ?' : '';
        $params = $poloId ? [$poloId] : [];
        
        try {
            // Estatísticas básicas
            $stmt = $this->pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM customers {$whereClause}) as total_customers,
                    (SELECT COUNT(*) FROM wallet_ids WHERE is_active = 1" . ($poloId ? " AND polo_id = ?" : "") . ") as total_wallet_ids,
                    (SELECT COUNT(*) FROM payments {$whereClause}) as total_payments,
                    (SELECT COUNT(*) FROM payments WHERE status = 'RECEIVED'" . ($poloId ? " AND polo_id = ?" : "") . ") as received_payments,
                    (SELECT COALESCE(SUM(value), 0) FROM payments WHERE status = 'RECEIVED'" . ($poloId ? " AND polo_id = ?" : "") . ") as total_value
            ");
            
            $execParams = [];
            if ($poloId) {
                $execParams = [$poloId, $poloId, $poloId, $poloId]; // Um para cada subquery
            }
            
            $stmt->execute($execParams);
            $stats = $stmt->fetch();
            
            // Estatísticas de splits
            $splitQuery = "
                SELECT 
                    COUNT(DISTINCT ps.wallet_id) as active_recipients,
                    SUM(CASE 
                        WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                        ELSE (p.value * ps.percentage_value / 100) 
                    END) as total_split_value
                FROM payment_splits ps
                JOIN payments p ON ps.payment_id = p.id
                WHERE p.status = 'RECEIVED'" . ($poloId ? " AND p.polo_id = ?" : "");
                
            $stmt = $this->pdo->prepare($splitQuery);
            $stmt->execute($poloId ? [$poloId] : []);
            $splitStats = $stmt->fetch();
            
            return [
                'total_customers' => $stats['total_customers'],
                'total_wallet_ids' => $stats['total_wallet_ids'],
                'total_split_accounts' => 0, // Manter para compatibilidade
                'total_payments' => $stats['total_payments'],
                'received_payments' => $stats['received_payments'],
                'total_value' => $stats['total_value'],
                'active_recipients' => $splitStats['active_recipients'] ?? 0,
                'total_split_value' => $splitStats['total_split_value'] ?? 0,
                'conversion_rate' => $stats['total_payments'] > 0 ? 
                    round(($stats['received_payments'] / $stats['total_payments']) * 100, 2) : 0
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas: " . $e->getMessage());
            return [
                'total_customers' => 0,
                'total_wallet_ids' => 0,
                'total_split_accounts' => 0,
                'total_payments' => 0,
                'received_payments' => 0,
                'total_value' => 0,
                'active_recipients' => 0,
                'total_split_value' => 0,
                'conversion_rate' => 0
            ];
        }
    }
    
    /**
     * Limpar logs antigos (mantido)
     */
    public function cleanOldLogs() {
        $cutoffDate = date('Y-m-d', strtotime('-' . LOG_RETENTION_DAYS . ' days'));
        
        $stmt = $this->pdo->prepare("DELETE FROM webhook_logs WHERE processed_at < ?");
        $stmt->execute([$cutoffDate]);
        
        return $stmt->rowCount();
    }
    
    // Métodos existentes mantidos para compatibilidade...
    public function saveSplitAccount($accountData) {
        // Implementação existente mantida
        try {
            // Se não especificar polo_id, usar o polo do usuário logado
            if (!isset($accountData['polo_id']) && isset($_SESSION['polo_id'])) {
                $accountData['polo_id'] = $_SESSION['polo_id'];
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO split_accounts (id, polo_id, wallet_id, name, email, cpf_cnpj, mobile_phone, status) 
                VALUES (:id, :polo_id, :wallet_id, :name, :email, :cpf_cnpj, :mobile_phone, :status)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name),
                    email = VALUES(email),
                    mobile_phone = VALUES(mobile_phone),
                    status = VALUES(status),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([
                'id' => $accountData['id'],
                'polo_id' => $accountData['polo_id'],
                'wallet_id' => $accountData['walletId'] ?? $accountData['id'],
                'name' => $accountData['name'],
                'email' => $accountData['email'],
                'cpf_cnpj' => $accountData['cpfCnpj'] ?? '',
                'mobile_phone' => $accountData['mobilePhone'] ?? null,
                'status' => $this->mapAccountStatus($accountData['status'] ?? 'ACTIVE')
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao salvar conta split: " . $e->getMessage());
            return false;
        }
    }
    
    private function mapAccountStatus($asaasStatus) {
        $statusMap = [
            'ACTIVE' => 'ACTIVE',
            'INACTIVE' => 'INACTIVE', 
            'PENDING' => 'PENDING',
            'AWAITING_ACTION_AUTHORIZATION' => 'PENDING',
            'AWAITING_ACCOUNT_VERIFICATION' => 'PENDING'
        ];
        
        return $statusMap[$asaasStatus] ?? 'PENDING';
    }
    
    public function savePaymentSplits($paymentId, $splits) {
        try {
            // Primeiro remove splits existentes
            $deleteStmt = $this->pdo->prepare("DELETE FROM payment_splits WHERE payment_id = ?");
            $deleteStmt->execute([$paymentId]);
            
            // Insere novos splits
            $insertStmt = $this->pdo->prepare("
                INSERT INTO payment_splits (payment_id, wallet_id, split_type, percentage_value, fixed_value) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($splits as $split) {
                $splitType = isset($split['fixedValue']) ? 'FIXED' : 'PERCENTAGE';
                $percentageValue = isset($split['percentualValue']) ? $split['percentualValue'] : null;
                $fixedValue = isset($split['fixedValue']) ? $split['fixedValue'] : null;
                
                $insertStmt->execute([
                    $paymentId,
                    $split['walletId'],
                    $splitType,
                    $percentageValue,
                    $fixedValue
                ]);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao salvar splits: " . $e->getMessage());
            return false;
        }
    }
    
    public function logWebhook($eventType, $paymentId, $payload, $status, $errorMessage = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO webhook_logs (event_type, payment_id, payload, status, error_message) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $eventType,
            $paymentId,
            json_encode($payload),
            $status,
            $errorMessage
        ]);
    }
}

/**
 * Classe AsaasConfig adaptada para multi-tenant
 */
class AsaasConfig {
    
    /**
     * Retorna instância configurada baseada no contexto do usuário
     */
    public static function getInstance($environment = null, $poloId = null) {
        // Verificar se há sistema de autenticação ativo
        if (isset($_SESSION['usuario_tipo'])) {
            // Usar configuração dinâmica
            require_once 'config_manager.php';
            $dynamicConfig = new DynamicAsaasConfig();
            
            if ($poloId && $_SESSION['usuario_tipo'] === 'master') {
                // Master pode acessar qualquer polo
                return $dynamicConfig->getPoloInstance($poloId);
            } else {
                // Usar configuração do contexto atual
                return $dynamicConfig->getInstance();
            }
        }
        
        // Fallback para configurações estáticas (compatibilidade)
        return self::getStaticInstance($environment);
    }
    
    /**
     * Configuração estática (compatibilidade com código existente)
     */
    private static function getStaticInstance($environment = null) {
        if ($environment === null) {
            $environment = defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'sandbox';
        }
        
        if ($environment === 'production') {
            $apiKey = defined('ASAAS_PRODUCTION_API_KEY') ? ASAAS_PRODUCTION_API_KEY : null;
        } else {
            $apiKey = defined('ASAAS_SANDBOX_API_KEY') ? ASAAS_SANDBOX_API_KEY : null;
        }
        
        if (empty($apiKey) || 
            $apiKey === 'SUA_API_KEY_PRODUCAO_AQUI' || 
            $apiKey === 'SUA_API_KEY_SANDBOX_AQUI') {
            throw new Exception("API Key não configurada para ambiente '{$environment}'. Configure no sistema de polos.");
        }
        
        return new AsaasSplitPayment($apiKey, $environment);
    }
}

/**
 * Classe SystemStats adaptada para multi-tenant
 */
class SystemStats {
    
    /**
     * Obter estatísticas baseadas no contexto do usuário
     */
    public static function getGeneralStats($poloId = null) {
        try {
            $db = DatabaseManager::getInstance();
            
            // Se há sistema de auth ativo, usar estatísticas filtradas
            if (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] !== 'master') {
                return $db->getPoloFilteredStats($_SESSION['polo_id']);
            }
            
            // Master ou sistema sem auth: estatísticas globais
            return $db->getPoloFilteredStats($poloId);
            
        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas: " . $e->getMessage());
            return [
                'total_customers' => 0,
                'total_wallet_ids' => 0,
                'total_split_accounts' => 0,
                'total_payments' => 0,
                'received_payments' => 0,
                'total_value' => 0,
                'active_recipients' => 0,
                'total_split_value' => 0,
                'conversion_rate' => 0
            ];
        }
    }
}

/**
 * Classe WalletManager adaptada para multi-tenant
 */
class WalletManager {
    
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    /**
     * Criar novo Wallet ID (com filtro por polo)
     */
    public function createWallet($name, $walletId, $description = null, $poloId = null) {
        // Se não especificar polo, usar o do usuário logado
        if ($poloId === null && isset($_SESSION['polo_id'])) {
            $poloId = $_SESSION['polo_id'];
        }
        
        // Verificar se já existe (no polo ou globalmente)
        $stmt = $this->db->getConnection()->prepare("
            SELECT COUNT(*) as count FROM wallet_ids 
            WHERE wallet_id = ?" . ($poloId ? " AND polo_id = ?" : "")
        );
        
        $params = [$walletId];
        if ($poloId) {
            $params[] = $poloId;
        }
        
        $stmt->execute($params);
        
        if ($stmt->fetch()['count'] > 0) {
            throw new Exception("Wallet ID já cadastrado" . ($poloId ? " neste polo" : ""));
        }
        
        $walletData = [
            'id' => uniqid('wallet_'),
            'polo_id' => $poloId,
            'wallet_id' => $walletId,
            'name' => $name,
            'description' => $description,
            'is_active' => 1
        ];
        
        if ($this->db->saveWalletId($walletData)) {
            return $walletData;
        } else {
            throw new Exception("Erro ao salvar Wallet ID");
        }
    }
    
    /**
     * Listar Wallet IDs (filtrado por polo)
     */
    public function listWallets($page = 1, $limit = 20, $search = null, $poloId = null) {
        $offset = ($page - 1) * $limit;
        
        // Se não especificar polo, usar o do usuário logado
        if ($poloId === null && isset($_SESSION['polo_id'])) {
            $poloId = $_SESSION['polo_id'];
        }
        
        $whereClause = '';
        $params = [];
        
        if ($poloId && (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'master')) {
            $whereClause = 'WHERE polo_id = ?';
            $params[] = $poloId;
        }
        
        if ($search) {
            $whereClause = $whereClause ? $whereClause . ' AND' : 'WHERE';
            $whereClause .= ' (name LIKE ? OR wallet_id LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT * FROM wallet_ids 
            {$whereClause}
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    // Outros métodos existentes mantidos...
    public function toggleStatus($walletId) {
        $stmt = $this->db->getConnection()->prepare("
            UPDATE wallet_ids 
            SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP 
            WHERE wallet_id = ?
        ");
        
        return $stmt->execute([$walletId]);
    }
    
    public function deleteWallet($walletId) {
        // Verificar se tem splits associados
        $stmt = $this->db->getConnection()->prepare("
            SELECT COUNT(*) as count FROM payment_splits WHERE wallet_id = ?
        ");
        $stmt->execute([$walletId]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            throw new Exception("Não é possível excluir. Wallet ID possui splits associados.");
        }
        
        $stmt = $this->db->getConnection()->prepare("DELETE FROM wallet_ids WHERE wallet_id = ?");
        return $stmt->execute([$walletId]);
    }
}

// Funções de utilidade para migração/instalação
function updateSystemForMultiTenant() {
    try {
        $db = DatabaseManager::getInstance();
        $connection = $db->getConnection();
        
        echo "🔄 Atualizando sistema para multi-tenant...\n";
        
        // Verificar e adicionar colunas polo_id se necessário
        $tabelas = ['customers', 'split_accounts', 'payments', 'wallet_ids'];
        
        foreach ($tabelas as $tabela) {
            $result = $connection->query("SHOW TABLES LIKE '{$tabela}'");
            if ($result->rowCount() > 0) {
                $result = $connection->query("SHOW COLUMNS FROM {$tabela} LIKE 'polo_id'");
                if ($result->rowCount() == 0) {
                    $connection->exec("ALTER TABLE {$tabela} ADD COLUMN polo_id INT NULL AFTER id");
                    $connection->exec("ALTER TABLE {$tabela} ADD INDEX idx_polo (polo_id)");
                    echo "  ✅ Coluna polo_id adicionada à tabela {$tabela}\n";
                }
            }
        }
        
        echo "✅ Sistema atualizado para multi-tenant\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ Erro na atualização: " . $e->getMessage() . "\n";
        return false;
    }
}

// Executar comandos via linha de comando
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    
    $command = isset($argv[1]) ? $argv[1] : '';
    
    switch ($command) {
        case 'update-multitenant':
            updateSystemForMultiTenant();
            break;
            
        case 'test-auth':
            echo "Testando sistema de autenticação...\n";
            if (file_exists(__DIR__ . '/auth.php')) {
                require_once 'auth.php';
                echo "✅ Sistema de autenticação disponível\n";
            } else {
                echo "❌ Sistema de autenticação não encontrado\n";
            }
            break;
            
        default:
            echo "Sistema de Split ASAAS Multi-Tenant v2.1\n";
            echo "=========================================\n\n";
            echo "Comandos disponíveis:\n";
            echo "  update-multitenant  - Atualizar sistema para multi-tenant\n";
            echo "  test-auth          - Testar sistema de autenticação\n";
            echo "  install            - Instalar sistema completo\n";
            echo "  health-check       - Verificar saúde do sistema\n\n";
            echo "Novo: Sistema com suporte a múltiplos polos!\n";
            break;
    }
}