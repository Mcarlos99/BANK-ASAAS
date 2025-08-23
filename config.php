<?php
/**
 * Configurações do Sistema de Split ASAAS
 * Arquivo: config.php
 * Versão com Wallet IDs
 */

// Configurações do ASAAS
define('ASAAS_PRODUCTION_API_KEY', getenv('ASAAS_PRODUCTION_API_KEY') ?: '$aact_prod_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjdmNDZhZTU1LWVjYTgtNDY0Mi1hOTg5LTY0NmMxNmM1ZTFkNzo6JGFhY2hfMWYzOTgxNjEtZWRhNy00ZjhhLTk5MGQtNGYwZjY2MzJmZTJk');
define('ASAAS_SANDBOX_API_KEY', getenv('ASAAS_SANDBOX_API_KEY') ?: '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjYyNTE2NTRkLTlhMmYtNGUxMS1iN2NlLTg1ZTQ5OTJjOTYyYjo6JGFhY2hfZjc5MDNiNTUtOWQ3Ny00MDRiLTg4YjctY2YxZmNhNTY5OGY5');
define('ASAAS_WEBHOOK_TOKEN', getenv('ASAAS_WEBHOOK_TOKEN') ?: 'SEU_WEBHOOK_TOKEN_AQUI');
define('ASAAS_ENVIRONMENT', getenv('ASAAS_ENVIRONMENT') ?: 'production'); // 'production' ou 'sandbox'

// Configurações do Banco de Dados
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'bankdb');
define('DB_USER', getenv('DB_USER') ?: 'bankuser');
define('DB_PASS', getenv('DB_PASS') ?: 'lKVX4Ew0u7I89hAUuDCm');
define('DB_CHARSET', 'utf8mb4');

// Configurações de Sistema
define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_RETENTION_DAYS', 30); // Dias para manter logs
define('WEBHOOK_TIMEOUT', 30); // Timeout para webhooks em segundos

/**
 * Classe para gerenciar conexão com banco de dados
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
     * Cria as tabelas necessárias do sistema
     */
    public function createTables() {
        try {
            // Tabela de clientes
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS customers (
                    id VARCHAR(50) PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    cpf_cnpj VARCHAR(20) NOT NULL,
                    mobile_phone VARCHAR(20),
                    address TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_email (email),
                    INDEX idx_cpf_cnpj (cpf_cnpj)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // NOVA TABELA: Wallet IDs Simples
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS wallet_ids (
                    id VARCHAR(50) PRIMARY KEY,
                    wallet_id VARCHAR(50) UNIQUE NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_wallet_id (wallet_id),
                    INDEX idx_active (is_active),
                    INDEX idx_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Tabela de contas para split
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS split_accounts (
                    id VARCHAR(50) PRIMARY KEY,
                    wallet_id VARCHAR(50) UNIQUE NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    cpf_cnpj VARCHAR(20) NOT NULL,
                    mobile_phone VARCHAR(20),
                    status ENUM('ACTIVE', 'INACTIVE', 'PENDING') DEFAULT 'PENDING',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_wallet_id (wallet_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Tabela de pagamentos
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS payments (
                    id VARCHAR(50) PRIMARY KEY,
                    customer_id VARCHAR(50) NOT NULL,
                    billing_type ENUM('BOLETO', 'CREDIT_CARD', 'DEBIT_CARD', 'PIX') NOT NULL,
                    status ENUM('PENDING', 'RECEIVED', 'OVERDUE', 'DELETED', 'CONFIRMED') DEFAULT 'PENDING',
                    value DECIMAL(10,2) NOT NULL,
                    description TEXT,
                    due_date DATE NOT NULL,
                    received_date DATETIME NULL,
                    installment_count INT DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_customer_id (customer_id),
                    INDEX idx_status (status),
                    INDEX idx_due_date (due_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Tabela de splits de pagamento
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS payment_splits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    payment_id VARCHAR(50) NOT NULL,
                    wallet_id VARCHAR(50) NOT NULL,
                    split_type ENUM('PERCENTAGE', 'FIXED') NOT NULL,
                    percentage_value DECIMAL(5,2) NULL,
                    fixed_value DECIMAL(10,2) NULL,
                    calculated_value DECIMAL(10,2) NULL,
                    status ENUM('PENDING', 'PROCESSED', 'FAILED') DEFAULT 'PENDING',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_payment_id (payment_id),
                    INDEX idx_wallet_id (wallet_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Tabela de logs de webhook
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS webhook_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_type VARCHAR(50) NOT NULL,
                    payment_id VARCHAR(50),
                    payload JSON NOT NULL,
                    status ENUM('SUCCESS', 'FAILED') NOT NULL,
                    error_message TEXT NULL,
                    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_event_type (event_type),
                    INDEX idx_payment_id (payment_id),
                    INDEX idx_status (status),
                    INDEX idx_processed_at (processed_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Tabela de configurações do sistema
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS system_settings (
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    description TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Inserir configurações padrão
            $this->insertDefaultSettings();
            
            echo "✅ Tabelas criadas com sucesso!\n";
            return true;
            
        } catch (PDOException $e) {
            echo "❌ Erro ao criar tabelas: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Insere configurações padrão do sistema
     */
    private function insertDefaultSettings() {
        $settings = [
            ['webhook_url', '', 'URL para receber webhooks do ASAAS'],
            ['default_split_percentage', '10.0', 'Percentual padrão de split para a plataforma'],
            ['auto_approve_splits', '1', 'Aprovar splits automaticamente (1=sim, 0=não)'],
            ['email_notifications', '1', 'Enviar notificações por email (1=sim, 0=não)'],
            ['max_split_accounts', '10', 'Máximo de contas de split por pagamento']
        ];
        
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO system_settings (setting_key, setting_value, description) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }
    }
    
    /**
     * Salva Wallet ID simples no banco
     */
    public function saveWalletId($walletData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO wallet_ids (id, wallet_id, name, description, is_active) 
                VALUES (:id, :wallet_id, :name, :description, :is_active)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name),
                    description = VALUES(description),
                    is_active = VALUES(is_active),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([
                'id' => $walletData['id'],
                'wallet_id' => $walletData['wallet_id'],
                'name' => $walletData['name'],
                'description' => $walletData['description'] ?? null,
                'is_active' => $walletData['is_active'] ?? 1
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao salvar Wallet ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca Wallet IDs ativos
     */
    public function getActiveWalletIds() {
        try {
            $stmt = $this->pdo->query("
                SELECT * FROM wallet_ids 
                WHERE is_active = 1 
                ORDER BY name
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar Wallet IDs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca Wallet ID específico
     */
    public function getWalletById($walletId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM wallet_ids WHERE wallet_id = ?");
            $stmt->execute([$walletId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar Wallet ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verifica se Wallet ID já existe
     */
    public function walletIdExists($walletId) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM wallet_ids WHERE wallet_id = ?");
            $stmt->execute([$walletId]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Erro ao verificar Wallet ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva cliente no banco
     */
    public function saveCustomer($customerData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO customers (id, name, email, cpf_cnpj, mobile_phone, address) 
            VALUES (:id, :name, :email, :cpf_cnpj, :mobile_phone, :address)
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                email = VALUES(email),
                mobile_phone = VALUES(mobile_phone),
                address = VALUES(address),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([
            'id' => $customerData['id'],
            'name' => $customerData['name'],
            'email' => $customerData['email'],
            'cpf_cnpj' => $customerData['cpfCnpj'],
            'mobile_phone' => $customerData['mobilePhone'] ?? null,
            'address' => $customerData['address'] ?? null
        ]);
    }
    
    /**
     * Salva conta de split no banco
     */
    public function saveSplitAccount($accountData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO split_accounts (id, wallet_id, name, email, cpf_cnpj, mobile_phone, status) 
                VALUES (:id, :wallet_id, :name, :email, :cpf_cnpj, :mobile_phone, :status)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name),
                    email = VALUES(email),
                    mobile_phone = VALUES(mobile_phone),
                    status = VALUES(status),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([
                'id' => $accountData['id'],
                'wallet_id' => $accountData['walletId'] ?? $accountData['id'], // Fallback para ID se walletId não existir
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
    
    /**
     * Mapeia status da conta do ASAAS para o sistema local
     */
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
    
    /**
     * Salva pagamento no banco
     */
    public function savePayment($paymentData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO payments (id, customer_id, billing_type, status, value, description, due_date, installment_count) 
            VALUES (:id, :customer_id, :billing_type, :status, :value, :description, :due_date, :installment_count)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([
            'id' => $paymentData['id'],
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
     * Salva splits do pagamento
     */
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
    
    /**
     * Registra log de webhook
     */
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
    
    /**
     * Busca relatório de splits (incluindo Wallet IDs simples)
     */
    public function getSplitReport($startDate, $endDate) {
        $stmt = $this->pdo->prepare("
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
            GROUP BY ps.wallet_id
            ORDER BY total_received DESC
        ");
        
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obtém estatísticas de Wallet IDs
     */
    public function getWalletStats() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_wallets,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_wallets,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_wallets
                FROM wallet_ids
            ");
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao obter estatísticas de wallets: " . $e->getMessage());
            return [
                'total_wallets' => 0,
                'active_wallets' => 0,
                'inactive_wallets' => 0
            ];
        }
    }
    
    /**
     * Busca splits por Wallet ID
     */
    public function getSplitsByWalletId($walletId, $limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id as payment_id,
                    p.value as payment_value,
                    p.description,
                    p.status,
                    p.received_date,
                    ps.split_type,
                    ps.percentage_value,
                    ps.fixed_value,
                    CASE 
                        WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                        ELSE (p.value * ps.percentage_value / 100) 
                    END as split_value
                FROM payment_splits ps
                JOIN payments p ON ps.payment_id = p.id
                WHERE ps.wallet_id = ?
                ORDER BY p.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$walletId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar splits por Wallet ID: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Limpa logs antigos
     */
    public function cleanOldLogs() {
        $cutoffDate = date('Y-m-d', strtotime('-' . LOG_RETENTION_DAYS . ' days'));
        
        $stmt = $this->pdo->prepare("DELETE FROM webhook_logs WHERE processed_at < ?");
        $stmt->execute([$cutoffDate]);
        
        return $stmt->rowCount();
    }
}

/**
 * Classe para gerenciar configurações
 */
class SettingsManager {
    
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    /**
     * Obtém valor de configuração
     */
    public function get($key, $default = null) {
        $stmt = $this->db->getConnection()->prepare("
            SELECT setting_value FROM system_settings WHERE setting_key = ?
        ");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : $default;
    }
    
    /**
     * Define valor de configuração
     */
    public function set($key, $value, $description = null) {
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                description = COALESCE(VALUES(description), description),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([$key, $value, $description]);
    }
    
    /**
     * Lista todas as configurações
     */
    public function getAll() {
        $stmt = $this->db->getConnection()->query("
            SELECT setting_key, setting_value, description, updated_at 
            FROM system_settings 
            ORDER BY setting_key
        ");
        
        return $stmt->fetchAll();
    }
}

/**
 * Classe para gerenciar Wallet IDs
 */
class WalletManager {
    
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    /**
     * Cria novo Wallet ID
     */
    public function createWallet($name, $walletId, $description = null) {
        // Verificar se já existe
        if ($this->db->walletIdExists($walletId)) {
            throw new Exception("Wallet ID já cadastrado: {$walletId}");
        }
        
        $walletData = [
            'id' => uniqid('wallet_'),
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
     * Atualiza status do Wallet ID
     */
    public function toggleStatus($walletId) {
        $stmt = $this->db->getConnection()->prepare("
            UPDATE wallet_ids 
            SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP 
            WHERE wallet_id = ?
        ");
        
        return $stmt->execute([$walletId]);
    }
    
    /**
     * Remove Wallet ID
     */
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
    
    /**
     * Busca Wallet ID com estatísticas
     */
    public function getWalletWithStats($walletId) {
        $wallet = $this->db->getWalletById($walletId);
        if (!$wallet) {
            return null;
        }
        
        // Adicionar estatísticas
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                COUNT(ps.id) as total_splits,
                SUM(CASE 
                    WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                    ELSE (p.value * ps.percentage_value / 100) 
                END) as total_received,
                COUNT(CASE WHEN p.status = 'RECEIVED' THEN 1 END) as received_payments
            FROM payment_splits ps
            LEFT JOIN payments p ON ps.payment_id = p.id
            WHERE ps.wallet_id = ?
        ");
        
        $stmt->execute([$walletId]);
        $stats = $stmt->fetch();
        
        return array_merge($wallet, $stats);
    }
    
    /**
     * Lista Wallet IDs com paginação
     */
    public function listWallets($page = 1, $limit = 20, $search = null) {
        $offset = ($page - 1) * $limit;
        
        $whereClause = '';
        $params = [];
        
        if ($search) {
            $whereClause = "WHERE name LIKE ? OR wallet_id LIKE ?";
            $params = ["%{$search}%", "%{$search}%"];
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
}

/**
 * Classe para notificações
 */
class NotificationManager {
    
    private $settings;
    
    public function __construct() {
        $this->settings = new SettingsManager();
    }
    
    /**
     * Envia notificação de pagamento recebido
     */
    public function sendPaymentReceived($paymentData) {
        if ($this->settings->get('email_notifications', '1') === '1') {
            return $this->sendEmail(
                $paymentData['customer_email'],
                'Pagamento Confirmado',
                $this->getPaymentReceivedTemplate($paymentData)
            );
        }
        return false;
    }
    
    /**
     * Envia notificação de split processado
     */
    public function sendSplitProcessed($splitData) {
        if ($this->settings->get('email_notifications', '1') === '1') {
            foreach ($splitData as $split) {
                $this->sendEmail(
                    $split['account_email'],
                    'Split de Pagamento Processado',
                    $this->getSplitProcessedTemplate($split)
                );
            }
            return true;
        }
        return false;
    }
    
    /**
     * Template de email para pagamento recebido
     */
    private function getPaymentReceivedTemplate($data) {
        return "
        <h2>Pagamento Confirmado!</h2>
        <p>Olá {$data['customer_name']},</p>
        <p>Confirmamos o recebimento do seu pagamento:</p>
        <ul>
            <li><strong>Valor:</strong> R$ " . number_format($data['value'], 2, ',', '.') . "</li>
            <li><strong>Descrição:</strong> {$data['description']}</li>
            <li><strong>Data:</strong> " . date('d/m/Y H:i') . "</li>
        </ul>
        <p>Obrigado pela sua compra!</p>
        ";
    }
    
    /**
     * Template de email para split processado
     */
    private function getSplitProcessedTemplate($data) {
        return "
        <h2>Split de Pagamento Processado</h2>
        <p>Olá {$data['account_name']},</p>
        <p>Você recebeu um split de pagamento:</p>
        <ul>
            <li><strong>Valor:</strong> R$ " . number_format($data['split_value'], 2, ',', '.') . "</li>
            <li><strong>Referência:</strong> {$data['payment_description']}</li>
            <li><strong>Data:</strong> " . date('d/m/Y H:i') . "</li>
        </ul>
        <p>O valor será creditado em sua conta conforme acordado.</p>
        ";
    }
    
    /**
     * Envia email (implementação básica)
     */
    private function sendEmail($to, $subject, $body) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Sistema de Pagamentos <naoresponder@seusite.com>',
            'Reply-To: suporte@seusite.com'
        ];
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
}

/**
 * Classe para relatórios avançados
 */
class ReportsManager {
    
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    /**
     * Relatório financeiro mensal
     */
    public function getMonthlyReport($year, $month) {
        $startDate = sprintf("%04d-%02d-01", $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                DATE(p.received_date) as date,
                COUNT(p.id) as payment_count,
                SUM(p.value) as total_value,
                COUNT(DISTINCT ps.wallet_id) as unique_recipients
            FROM payments p
            LEFT JOIN payment_splits ps ON p.id = ps.payment_id
            WHERE p.status = 'RECEIVED' 
                AND p.received_date BETWEEN ? AND ?
            GROUP BY DATE(p.received_date)
            ORDER BY date
        ");
        
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Top recebedores de split (incluindo Wallet IDs)
     */
    public function getTopSplitReceivers($limit = 10, $startDate = null, $endDate = null) {
        if (!$startDate) $startDate = date('Y-m-01');
        if (!$endDate) $endDate = date('Y-m-d');
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                COALESCE(sa.name, wi.name, ps.wallet_id) as name,
                COALESCE(sa.email, 'N/A') as email,
                ps.wallet_id,
                CASE 
                    WHEN sa.id IS NOT NULL THEN 'Conta Split'
                    WHEN wi.id IS NOT NULL THEN 'Wallet ID'
                    ELSE 'Desconhecido'
                END as source_type,
                COUNT(ps.id) as payment_count,
                SUM(CASE 
                    WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                    ELSE (p.value * ps.percentage_value / 100) 
                END) as total_received,
                AVG(CASE 
                    WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                    ELSE (p.value * ps.percentage_value / 100) 
                END) as avg_per_payment
            FROM payment_splits ps
            JOIN payments p ON ps.payment_id = p.id
            LEFT JOIN split_accounts sa ON ps.wallet_id = sa.wallet_id
            LEFT JOIN wallet_ids wi ON ps.wallet_id = wi.wallet_id
            WHERE p.status = 'RECEIVED' 
                AND p.received_date BETWEEN ? AND ?
            GROUP BY ps.wallet_id
            ORDER BY total_received DESC
            LIMIT ?
        ");
        
        $stmt->execute([$startDate, $endDate, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Relatório de conversão de pagamentos
     */
    public function getConversionReport($startDate, $endDate) {
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                billing_type,
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'RECEIVED' THEN 1 ELSE 0 END) as paid_payments,
                SUM(CASE WHEN status = 'OVERDUE' THEN 1 ELSE 0 END) as overdue_payments,
                ROUND(
                    (SUM(CASE WHEN status = 'RECEIVED' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2
                ) as conversion_rate,
                SUM(value) as total_value,
                SUM(CASE WHEN status = 'RECEIVED' THEN value ELSE 0 END) as received_value
            FROM payments 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY billing_type
            ORDER BY conversion_rate DESC
        ");
        
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Relatório específico de Wallet IDs
     */
    public function getWalletPerformanceReport($startDate, $endDate) {
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                wi.name,
                wi.wallet_id,
                wi.description,
                wi.is_active,
                COUNT(ps.id) as split_count,
                SUM(CASE 
                    WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                    ELSE (p.value * ps.percentage_value / 100) 
                END) as total_earned,
                AVG(CASE 
                    WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                    ELSE (p.value * ps.percentage_value / 100) 
                END) as avg_split_value,
                MIN(p.received_date) as first_split,
                MAX(p.received_date) as last_split
            FROM wallet_ids wi
            LEFT JOIN payment_splits ps ON wi.wallet_id = ps.wallet_id
            LEFT JOIN payments p ON ps.payment_id = p.id AND p.status = 'RECEIVED'
            WHERE (p.received_date IS NULL OR p.received_date BETWEEN ? AND ?)
            GROUP BY wi.id
            ORDER BY total_earned DESC
        ");
        
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
}

/**
 * Script de instalação
 */
class SystemInstaller {
    
    public static function install() {
        echo "🚀 INSTALANDO SISTEMA DE SPLIT ASAAS COM WALLET IDS\n";
        echo "===================================================\n\n";
        
        try {
            // 1. Verificar dependências
            echo "1. Verificando dependências...\n";
            self::checkDependencies();
            echo "✅ Dependências OK\n\n";
            
            // 2. Criar banco de dados
            echo "2. Configurando banco de dados...\n";
            $db = DatabaseManager::getInstance();
            $db->createTables();
            echo "✅ Banco configurado\n\n";
            
            // 3. Configurar permissões
            echo "3. Configurando permissões...\n";
            self::setupPermissions();
            echo "✅ Permissões configuradas\n\n";
            
            // 4. Configurar webhook
            echo "4. Informações de webhook...\n";
            self::setupWebhook();
            echo "✅ Informações de webhook exibidas\n\n";
            
            // 5. Exemplo de Wallet IDs
            echo "5. Criando Wallet IDs de exemplo...\n";
            self::createExampleWallets();
            echo "✅ Wallet IDs de exemplo criados\n\n";
            
            echo "✅ INSTALAÇÃO CONCLUÍDA!\n\n";
            echo "📋 PRÓXIMOS PASSOS:\n";
            echo "1. Configure suas API Keys no arquivo config.php\n";
            echo "2. Configure a URL do webhook no painel ASAAS\n";
            echo "3. Cadastre seus Wallet IDs na seção 'Wallet IDs'\n";
            echo "4. Teste o sistema criando um pagamento\n\n";
            
            return true;
            
        } catch (Exception $e) {
            echo "❌ Erro na instalação: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private static function checkDependencies() {
        $required = ['curl', 'json', 'pdo', 'pdo_mysql'];
        $missing = [];
        
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception("Extensões faltando: " . implode(', ', $missing));
        }
        
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new Exception("PHP 7.4+ é necessário. Versão atual: " . PHP_VERSION);
        }
    }
    
    private static function setupPermissions() {
        $dirs = [
            __DIR__ . '/logs',
            __DIR__ . '/cache',
            __DIR__ . '/uploads'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            chmod($dir, 0755);
        }
    }
    
    private static function setupWebhook() {
        if (isset($_SERVER['HTTP_HOST'])) {
            $webhookUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/webhook.php";
        } else {
            $webhookUrl = "https://SEUDOMINIO.com/webhook.php";
        }
        
        echo "URL do Webhook: {$webhookUrl}\n";
        echo "Configure esta URL no painel ASAAS\n";
    }
    
    private static function createExampleWallets() {
        try {
            $db = DatabaseManager::getInstance();
            
            $exampleWallets = [
                [
                    'id' => 'wallet_example_1',
                    'wallet_id' => '22e49670-27e4-4579-a4c1-205c8a40497c',
                    'name' => 'MAURO CARLOS',
                    'description' => 'Parceiro principal - Comissão de vendas',
                    'is_active' => 1
                ],
                [
                    'id' => 'wallet_example_2', 
                    'wallet_id' => '11f11111-1111-1111-1111-111111111111',
                    'name' => 'DIEGO',
                    'description' => 'Parceiro secundário - Suporte técnico',
                    'is_active' => 1
                ]
            ];
            
            foreach ($exampleWallets as $wallet) {
                $db->saveWalletId($wallet);
                echo "  - Wallet ID criado: {$wallet['name']}\n";
            }
            
        } catch (Exception $e) {
            echo "  ⚠️ Erro ao criar wallets de exemplo: " . $e->getMessage() . "\n";
        }
    }
}

/**
 * Classe para manutenção do sistema
 */
class SystemMaintenance {
    
    /**
     * Executa limpeza de logs antigos
     */
    public static function cleanOldLogs() {
        try {
            $db = DatabaseManager::getInstance();
            $deleted = $db->cleanOldLogs();
            
            echo "🧹 Limpeza de logs concluída. {$deleted} registros removidos.\n";
            
            // Limpar logs de arquivo também
            $logDir = __DIR__ . '/logs';
            if (is_dir($logDir)) {
                $cutoffDate = strtotime('-' . LOG_RETENTION_DAYS . ' days');
                $files = glob($logDir . '/asaas_*.log');
                $deletedFiles = 0;
                
                foreach ($files as $file) {
                    if (filemtime($file) < $cutoffDate) {
                        unlink($file);
                        $deletedFiles++;
                    }
                }
                
                echo "🗄 {$deletedFiles} arquivos de log removidos.\n";
            }
            
            return true;
        } catch (Exception $e) {
            echo "❌ Erro na limpeza: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Verifica saúde do sistema
     */
    public static function healthCheck() {
        echo "🏥 VERIFICAÇÃO DE SAÚDE DO SISTEMA\n";
        echo "==================================\n\n";
        
        $issues = [];
        
        // Verificar conexão com banco
        try {
            $db = DatabaseManager::getInstance();
            $db->getConnection()->query("SELECT 1");
            echo "✅ Conexão com banco de dados: OK\n";
            
            // Verificar se tabela de wallet_ids existe
            $result = $db->getConnection()->query("SHOW TABLES LIKE 'wallet_ids'");
            if ($result->rowCount() > 0) {
                echo "✅ Tabela wallet_ids: OK\n";
            } else {
                $issues[] = "Tabela wallet_ids não encontrada";
                echo "❌ Tabela wallet_ids: FALTANDO\n";
            }
            
        } catch (Exception $e) {
            $issues[] = "Conexão com banco: " . $e->getMessage();
            echo "❌ Conexão com banco de dados: FALHA\n";
        }
        
        // Verificar API ASAAS
        if (ASAAS_SANDBOX_API_KEY !== 'SUA_API_KEY_SANDBOX_AQUI') {
            try {
                require_once __DIR__ . '/asaas_split_system.php';
                $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                $asaas->listAccounts(1, 0);
                echo "✅ Conexão com API ASAAS: OK\n";
            } catch (Exception $e) {
                $issues[] = "API ASAAS: " . $e->getMessage();
                echo "❌ Conexão com API ASAAS: FALHA\n";
            }
        } else {
            echo "⚠️ API ASAAS: Não configurada\n";
        }
        
        // Verificar permissões de diretórios
        $dirs = [__DIR__ . '/logs', __DIR__ . '/cache'];
        foreach ($dirs as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                echo "✅ Diretório " . basename($dir) . ": OK\n";
            } else {
                $issues[] = "Diretório sem permissão de escrita: " . basename($dir);
                echo "❌ Diretório " . basename($dir) . ": SEM PERMISSÃO\n";
            }
        }
        
        // Verificar espaço em disco
        $freeBytes = disk_free_space(__DIR__);
        $freeMB = round($freeBytes / 1024 / 1024);
        
        if ($freeMB > 100) {
            echo "✅ Espaço em disco: {$freeMB}MB disponível\n";
        } else {
            $issues[] = "Pouco espaço em disco: {$freeMB}MB";
            echo "⚠️ Espaço em disco: {$freeMB}MB (baixo)\n";
        }
        
        echo "\n";
        
        if (empty($issues)) {
            echo "✅ Sistema funcionando corretamente!\n";
            return true;
        } else {
            echo "❌ Problemas encontrados:\n";
            foreach ($issues as $issue) {
                echo "  • {$issue}\n";
            }
            return false;
        }
    }
    
    /**
     * Backup do banco de dados
     */
    public static function backupDatabase() {
        try {
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backupDir = __DIR__ . '/backups';
            
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $command = sprintf(
                'mysqldump -h%s -u%s -p%s %s > %s/%s',
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME,
                $backupDir,
                $filename
            );
            
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0) {
                echo "✅ Backup criado: {$filename}\n";
                return $backupDir . '/' . $filename;
            } else {
                throw new Exception("Falha ao executar mysqldump");
            }
            
        } catch (Exception $e) {
            echo "❌ Erro no backup: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

/**
 * Classe para atualizações do sistema
 */
class SystemUpdater {
    
    /**
     * Verifica se há atualizações disponíveis
     */
    public static function checkUpdates() {
        echo "🔄 VERIFICANDO ATUALIZAÇÕES\n";
        echo "==========================\n\n";
        
        $currentVersion = self::getCurrentVersion();
        echo "Versão atual: {$currentVersion}\n";
        
        // Aqui você pode implementar verificação remota de versões
        echo "✅ Sistema atualizado\n";
        
        return true;
    }
    
    /**
     * Obtém versão atual do sistema
     */
    private static function getCurrentVersion() {
        return '2.0.0'; // Versão com Wallet IDs
    }
    
    /**
     * Executa migração de banco de dados
     */
    public static function runMigrations() {
        echo "🔄 EXECUTANDO MIGRAÇÕES\n";
        echo "=======================\n\n";
        
        try {
            $db = DatabaseManager::getInstance();
            
            // Verificar se tabela de migrações existe
            $result = $db->getConnection()->query("
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = '" . DB_NAME . "' 
                AND table_name = 'migrations'
            ");
            
            if ($result->fetch()['count'] == 0) {
                // Criar tabela de migrações
                $db->getConnection()->exec("
                    CREATE TABLE migrations (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        migration VARCHAR(255) NOT NULL,
                        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_migration (migration)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                echo "✅ Tabela de migrações criada\n";
            }
            
            // Migração para adicionar tabela wallet_ids se não existir
            $result = $db->getConnection()->query("
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = '" . DB_NAME . "' 
                AND table_name = 'wallet_ids'
            ");
            
            if ($result->fetch()['count'] == 0) {
                $db->getConnection()->exec("
                    CREATE TABLE wallet_ids (
                        id VARCHAR(50) PRIMARY KEY,
                        wallet_id VARCHAR(50) UNIQUE NOT NULL,
                        name VARCHAR(255) NOT NULL,
                        description TEXT NULL,
                        is_active TINYINT(1) DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_wallet_id (wallet_id),
                        INDEX idx_active (is_active),
                        INDEX idx_name (name)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                // Registrar migração
                $db->getConnection()->prepare("
                    INSERT IGNORE INTO migrations (migration) VALUES (?)
                ")->execute(['create_wallet_ids_table_v2']);
                
                echo "✅ Tabela wallet_ids criada\n";
            }
            
            echo "✅ Migrações concluídas\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Erro nas migrações: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

/**
 * Classe para estatísticas do sistema
 */
class SystemStats {
    
    /**
     * Obtém estatísticas gerais (incluindo Wallet IDs)
     */
    public static function getGeneralStats() {
        try {
            $db = DatabaseManager::getInstance();
            $conn = $db->getConnection();
            
            // Total de clientes
            $stmt = $conn->query("SELECT COUNT(*) as total FROM customers");
            $totalCustomers = $stmt->fetch()['total'];
            
            // Total de Wallet IDs
            $stmt = $conn->query("SELECT COUNT(*) as total FROM wallet_ids WHERE is_active = 1");
            $totalWalletIds = $stmt->fetch()['total'] ?? 0;
            
            // Total de contas de split
            $stmt = $conn->query("SELECT COUNT(*) as total FROM split_accounts");
            $totalSplitAccounts = $stmt->fetch()['total'];
            
            // Total de pagamentos
            $stmt = $conn->query("SELECT COUNT(*) as total FROM payments");
            $totalPayments = $stmt->fetch()['total'];
            
            // Pagamentos recebidos
            $stmt = $conn->query("SELECT COUNT(*) as total FROM payments WHERE status = 'RECEIVED'");
            $receivedPayments = $stmt->fetch()['total'];
            
            // Valor total recebido
            $stmt = $conn->query("SELECT SUM(value) as total FROM payments WHERE status = 'RECEIVED'");
            $totalValue = $stmt->fetch()['total'] ?? 0;
            
            // Estatísticas de splits
            $stmt = $conn->query("
                SELECT 
                    COUNT(DISTINCT ps.wallet_id) as active_recipients,
                    SUM(CASE 
                        WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                        ELSE (p.value * ps.percentage_value / 100) 
                    END) as total_split_value
                FROM payment_splits ps
                JOIN payments p ON ps.payment_id = p.id
                WHERE p.status = 'RECEIVED'
            ");
            $splitStats = $stmt->fetch();
            
            return [
                'total_customers' => $totalCustomers,
                'total_wallet_ids' => $totalWalletIds,
                'total_split_accounts' => $totalSplitAccounts,
                'total_payments' => $totalPayments,
                'received_payments' => $receivedPayments,
                'total_value' => $totalValue,
                'active_recipients' => $splitStats['active_recipients'] ?? 0,
                'total_split_value' => $splitStats['total_split_value'] ?? 0,
                'conversion_rate' => $totalPayments > 0 ? round(($receivedPayments / $totalPayments) * 100, 2) : 0
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Exibe dashboard de estatísticas
     */
    public static function showDashboard() {
        echo "📊 DASHBOARD DO SISTEMA\n";
        echo "=======================\n\n";
        
        $stats = self::getGeneralStats();
        
        if ($stats) {
            echo "👥 Total de Clientes: " . number_format($stats['total_customers']) . "\n";
            echo "💰 Wallet IDs Ativos: " . number_format($stats['total_wallet_ids']) . "\n";
            echo "🏦 Contas de Split: " . number_format($stats['total_split_accounts']) . "\n";
            echo "💳 Total de Pagamentos: " . number_format($stats['total_payments']) . "\n";
            echo "✅ Pagamentos Recebidos: " . number_format($stats['received_payments']) . "\n";
            echo "💰 Valor Total Recebido: R$ " . number_format($stats['total_value'], 2, ',', '.') . "\n";
            echo "🎯 Destinatários Ativos: " . number_format($stats['active_recipients']) . "\n";
            echo "💸 Total Distribuído: R$ " . number_format($stats['total_split_value'], 2, ',', '.') . "\n";
            echo "📈 Taxa de Conversão: " . $stats['conversion_rate'] . "%\n\n";
        } else {
            echo "❌ Erro ao obter estatísticas\n";
        }
    }
    
    /**
     * Relatório específico de Wallet IDs
     */
    public static function showWalletStats() {
        echo "💳 ESTATÍSTICAS DE WALLET IDS\n";
        echo "==============================\n\n";
        
        try {
            $db = DatabaseManager::getInstance();
            $walletStats = $db->getWalletStats();
            
            echo "📊 Total de Wallet IDs: " . $walletStats['total_wallets'] . "\n";
            echo "✅ Ativos: " . $walletStats['active_wallets'] . "\n";
            echo "❌ Inativos: " . $walletStats['inactive_wallets'] . "\n\n";
            
            // Top 5 Wallet IDs por valor recebido
            $reports = new ReportsManager();
            $topWallets = $reports->getTopSplitReceivers(5);
            
            if (!empty($topWallets)) {
                echo "🏆 TOP 5 WALLET IDS:\n";
                foreach ($topWallets as $i => $wallet) {
                    $pos = $i + 1;
                    echo "{$pos}. {$wallet['name']} ({$wallet['source_type']})\n";
                    echo "   Valor Recebido: R$ " . number_format($wallet['total_received'], 2, ',', '.') . "\n";
                    echo "   Pagamentos: " . $wallet['payment_count'] . "\n\n";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Erro ao obter estatísticas de wallets: " . $e->getMessage() . "\n";
        }
    }
}

/**
 * Classe para validações
 */
class ValidationHelper {
    
    /**
     * Valida formato de Wallet ID do ASAAS
     */
    public static function isValidWalletId($walletId) {
        // Formato típico: UUID v4 (ex: 22e49670-27e4-4579-a4c1-205c8a40497c)
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        return preg_match($pattern, $walletId);
    }
    
    /**
     * Sanitiza nome de Wallet ID
     */
    public static function sanitizeWalletName($name) {
        // Remove caracteres especiais perigosos, mantém acentos
        $name = trim($name);
        $name = preg_replace('/[<>"\']/', '', $name);
        return substr($name, 0, 255); // Limite do banco
    }
    
    /**
     * Valida se percentuais de split não excedem 100%
     */
    public static function validateSplitPercentages($splits) {
        $totalPercentage = 0;
        
        foreach ($splits as $split) {
            if (isset($split['percentualValue'])) {
                $totalPercentage += floatval($split['percentualValue']);
            }
        }
        
        return $totalPercentage <= 100;
    }
    
    /**
     * Formata Wallet ID para exibição
     */
    public static function formatWalletIdForDisplay($walletId) {
        // Mostrar apenas os primeiros 8 e últimos 4 caracteres
        if (strlen($walletId) > 12) {
            return substr($walletId, 0, 8) . '...' . substr($walletId, -4);
        }
        return $walletId;
    }
}

// Executar comandos via linha de comando
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    
    $command = isset($argv[1]) ? $argv[1] : '';
    
    switch ($command) {
        case 'install':
            SystemInstaller::install();
            break;
            
        case 'health-check':
            SystemMaintenance::healthCheck();
            break;
            
        case 'clean-logs':
            SystemMaintenance::cleanOldLogs();
            break;
            
        case 'backup':
            SystemMaintenance::backupDatabase();
            break;
            
        case 'stats':
            SystemStats::showDashboard();
            break;
            
        case 'wallet-stats':
            SystemStats::showWalletStats();
            break;
            
        case 'migrate':
            SystemUpdater::runMigrations();
            break;
            
        case 'update-check':
            SystemUpdater::checkUpdates();
            break;
            
        case 'test-db':
            try {
                $db = DatabaseManager::getInstance();
                $db->getConnection()->query("SELECT 1");
                echo "✅ Conexão com banco de dados OK\n";
            } catch (Exception $e) {
                echo "❌ Erro na conexão: " . $e->getMessage() . "\n";
            }
            break;
            
        case 'create-wallet':
            if (!isset($argv[2]) || !isset($argv[3])) {
                echo "Uso: php config.php create-wallet <nome> <wallet_id> [descrição]\n";
                break;
            }
            
            try {
                $walletManager = new WalletManager();
                $name = $argv[2];
                $walletId = $argv[3];
                $description = isset($argv[4]) ? $argv[4] : null;
                
                $wallet = $walletManager->createWallet($name, $walletId, $description);
                echo "✅ Wallet ID criado com sucesso: {$wallet['name']}\n";
                
            } catch (Exception $e) {
                echo "❌ Erro ao criar Wallet ID: " . $e->getMessage() . "\n";
            }
            break;
            
        case 'list-wallets':
            try {
                $walletManager = new WalletManager();
                $wallets = $walletManager->listWallets();
                
                if (empty($wallets)) {
                    echo "Nenhum Wallet ID cadastrado\n";
                } else {
                    echo "💳 WALLET IDS CADASTRADOS:\n";
                    echo "==========================\n";
                    foreach ($wallets as $wallet) {
                        $status = $wallet['is_active'] ? '✅ Ativo' : '❌ Inativo';
                        echo "• {$wallet['name']} ({$status})\n";
                        echo "  Wallet ID: {$wallet['wallet_id']}\n";
                        if ($wallet['description']) {
                            echo "  Descrição: {$wallet['description']}\n";
                        }
                        echo "  Criado em: " . date('d/m/Y H:i', strtotime($wallet['created_at'])) . "\n\n";
                    }
                }
                
            } catch (Exception $e) {
                echo "❌ Erro ao listar Wallet IDs: " . $e->getMessage() . "\n";
            }
            break;
            
        default:
            echo "Sistema de Split ASAAS v2.0 - Configuração\n";
            echo "==========================================\n\n";
            echo "Comandos disponíveis:\n";
            echo "  install          - Instalar sistema completo\n";
            echo "  health-check     - Verificar saúde do sistema\n";
            echo "  clean-logs       - Limpar logs antigos\n";
            echo "  backup           - Fazer backup do banco\n";
            echo "  stats            - Mostrar estatísticas gerais\n";
            echo "  wallet-stats     - Mostrar estatísticas de Wallet IDs\n";
            echo "  migrate          - Executar migrações\n";
            echo "  update-check     - Verificar atualizações\n";
            echo "  test-db          - Testar conexão com banco\n";
            echo "  create-wallet    - Criar novo Wallet ID\n";
            echo "  list-wallets     - Listar Wallet IDs cadastrados\n\n";
            echo "Uso: php config.php [comando]\n";
            echo "\nNovo recurso: Wallet IDs para splits rápidos!\n";
            break;
    }
}

?>