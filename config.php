<?php
/**
 * Configurações do Sistema - ATUALIZADO COM SUPORTE A DESCONTO
 * Arquivo: config.php
 * Versão com mensalidades E desconto em parcelas
 */

// Configurações MASTER (usadas apenas pelo admin master)
define('ASAAS_PRODUCTION_API_KEY', getenv('ASAAS_PRODUCTION_API_KEY') ?: '$aact_prod_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjdmNDZhZTU1LWVjYTgtNDY0Mi1hOTg5LTY0NmMxNmM1ZTFkNzo6JGFhY2hfMWYzOTgxNjEtZWRhNy00ZjhhLTk5MGQtNGYwZjY2MzJmZTJk');
define('ASAAS_SANDBOX_API_KEY', getenv('ASAAS_SANDBOX_API_KEY') ?: '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjYyNTE2NTRkLTlhMmYtNGUxMS1iN2NlLTg1ZTQ5OTJjOTYyYjo6JGFhY2hfZjc5MDNiNTUtOWQ3Ny00MDRiLTg4YjctY2YxZmNhNTY5OGY5');
define('ASAAS_WEBHOOK_TOKEN', getenv('ASAAS_WEBHOOK_TOKEN') ?: 'SEU_WEBHOOK_TOKEN_AQUI');
define('ASAAS_ENVIRONMENT', getenv('ASAAS_ENVIRONMENT') ?: 'production');

// Configurações do Banco de Dados
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'bankdb');
define('DB_USER', getenv('DB_USER') ?: 'bankuser');
define('DB_PASS', getenv('DB_PASS') ?: 'lKVX4Ew0u7I89hAUuDCm');
define('DB_CHARSET', 'utf8mb4');

// Configurações de mensalidades COM DESCONTO
define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'INFO');
define('LOG_RETENTION_DAYS', 30);
define('WEBHOOK_TIMEOUT', 30);

// NOVAS CONFIGURAÇÕES PARA MENSALIDADES
define('MAX_INSTALLMENTS', 24);
define('MIN_INSTALLMENTS', 2);
define('MIN_INSTALLMENT_VALUE', 1.00);
define('MAX_INSTALLMENT_VALUE', 50000.00);
define('MAX_DISCOUNT_PERCENTAGE', 50); // 50% máximo de desconto
define('DEFAULT_DISCOUNT_TYPE', 'FIXED'); // Tipo padrão: valor fixo

/**
 * Classe DatabaseManager ATUALIZADA COM DESCONTO
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
            
            // Log de conexão bem-sucedida
            error_log("DatabaseManager: Conexão estabelecida com suporte a desconto");
            
        } catch (PDOException $e) {
            error_log("DatabaseManager: Erro na conexão: " . $e->getMessage());
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
     * ===== MÉTODO ATUALIZADO: SALVAR MENSALIDADE COM DESCONTO =====
     */
    public function saveInstallmentRecord($installmentData) {
        try {
            error_log("Salvando mensalidade COM DESCONTO: " . json_encode($installmentData));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO installments (
                    installment_id, polo_id, customer_id, installment_count, 
                    installment_value, total_value, first_due_date, 
                    billing_type, description, has_splits, splits_count, 
                    created_by, first_payment_id, status,
                    
                    -- NOVOS CAMPOS DE DESCONTO --
                    has_discount, discount_value, discount_type, 
                    discount_deadline_type, discount_description,
                    
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE',
                    ?, ?, ?, ?, ?, NOW()
                )
            ");
            
            $result = $stmt->execute([
                $installmentData['installment_id'],
                $installmentData['polo_id'],
                $installmentData['customer_id'],
                $installmentData['installment_count'],
                $installmentData['installment_value'],
                $installmentData['total_value'],
                $installmentData['first_due_date'],
                $installmentData['billing_type'],
                $installmentData['description'],
                $installmentData['has_splits'] ? 1 : 0,
                $installmentData['splits_count'],
                $installmentData['created_by'],
                $installmentData['first_payment_id'],
                
                // DADOS DO DESCONTO
                $installmentData['has_discount'] ?? 0,
                $installmentData['discount_value'] ?? null,
                $installmentData['discount_type'] ?? null,
                $installmentData['discount_deadline_type'] ?? null,
                $installmentData['discount_description'] ?? null
            ]);
            
            if ($result) {
                $recordId = $this->pdo->lastInsertId();
                error_log("Mensalidade com desconto salva - ID: {$recordId}");
                return $recordId;
            } else {
                throw new Exception("Falha ao executar inserção da mensalidade");
            }
            
        } catch (PDOException $e) {
            error_log("Erro ao salvar mensalidade com desconto: " . $e->getMessage());
            
            // Se tabela não existe, tentar criar
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                error_log("Tabela installments não existe, criando com campos de desconto...");
                if ($this->createInstallmentsTableWithDiscount()) {
                    return $this->saveInstallmentRecord($installmentData);
                }
            }
            
            throw new Exception("Erro ao salvar mensalidade: " . $e->getMessage());
        }
    }

        /**
     * ===== NOVA FUNÇÃO: CRIAR TABELA COM CAMPOS DE DESCONTO =====
     */
    public function createInstallmentsTableWithDiscount() {
        try {
            error_log("Criando tabela installments COM CAMPOS DE DESCONTO...");
            
            $sql = "CREATE TABLE IF NOT EXISTS installments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                installment_id VARCHAR(100) NOT NULL UNIQUE,
                polo_id INT NULL,
                customer_id VARCHAR(100) NOT NULL,
                installment_count INT NOT NULL,
                installment_value DECIMAL(10,2) NOT NULL,
                total_value DECIMAL(10,2) NOT NULL,
                first_due_date DATE NOT NULL,
                billing_type VARCHAR(20) NOT NULL,
                description TEXT,
                has_splits BOOLEAN DEFAULT 0,
                splits_count INT DEFAULT 0,
                created_by INT,
                first_payment_id VARCHAR(100),
                status ENUM('ACTIVE', 'CANCELLED', 'COMPLETED') DEFAULT 'ACTIVE',
                
                -- ===== CAMPOS DE DESCONTO =====
                has_discount BOOLEAN DEFAULT 0,
                discount_value DECIMAL(10,2) NULL COMMENT 'Valor fixo do desconto por parcela',
                discount_type ENUM('FIXED', 'PERCENTAGE') DEFAULT 'FIXED' COMMENT 'Tipo do desconto',
                discount_deadline_type ENUM('DUE_DATE', 'DAYS_BEFORE', 'CUSTOM') DEFAULT 'DUE_DATE' COMMENT 'Tipo de prazo',
                discount_description TEXT NULL COMMENT 'Descrição do desconto',
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_installment_id (installment_id),
                INDEX idx_polo_id (polo_id),
                INDEX idx_customer_id (customer_id),
                INDEX idx_status (status),
                INDEX idx_has_discount (has_discount),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
            COMMENT='Tabela de mensalidades parceladas COM SUPORTE A DESCONTO'";
            
            $this->pdo->exec($sql);
            error_log("Tabela installments criada com campos de desconto");
            
            // Criar também tabela de parcelas individuais
            $this->createInstallmentPaymentsTable();
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela com desconto: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criar tabela de mensalidades se não existir
     */
    public function createInstallmentsTable() {
        try {
            error_log("Criando tabela installments...");
            
            $sql = "CREATE TABLE IF NOT EXISTS installments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                installment_id VARCHAR(100) NOT NULL UNIQUE,
                polo_id INT NULL,
                customer_id VARCHAR(100) NOT NULL,
                installment_count INT NOT NULL,
                installment_value DECIMAL(10,2) NOT NULL,
                total_value DECIMAL(10,2) NOT NULL,
                first_due_date DATE NOT NULL,
                billing_type VARCHAR(20) NOT NULL,
                description TEXT,
                has_splits BOOLEAN DEFAULT 0,
                splits_count INT DEFAULT 0,
                created_by INT,
                first_payment_id VARCHAR(100),
                status ENUM('ACTIVE', 'CANCELLED', 'COMPLETED') DEFAULT 'ACTIVE',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_installment_id (installment_id),
                INDEX idx_polo_id (polo_id),
                INDEX idx_customer_id (customer_id),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
            error_log("Tabela installments criada com sucesso");
            
            // Criar também tabela de parcelas individuais
            $this->createInstallmentPaymentsTable();
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela installments: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ===== ATUALIZAR TABELA DE PARCELAS PARA RASTREAR DESCONTO APLICADO =====
     */
    public function createInstallmentPaymentsTable() {
        try {
            error_log("Criando/atualizando tabela installment_payments com campos de desconto...");
            
            $sql = "CREATE TABLE IF NOT EXISTS installment_payments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                installment_id VARCHAR(100) NOT NULL,
                payment_id VARCHAR(100) NOT NULL,
                installment_number INT NOT NULL,
                due_date DATE NOT NULL,
                value DECIMAL(10,2) NOT NULL,
                status VARCHAR(20) DEFAULT 'PENDING',
                paid_date DATETIME NULL,
                
                -- ===== CAMPOS PARA RASTREAR DESCONTO APLICADO =====
                original_value DECIMAL(10,2) NULL COMMENT 'Valor original sem desconto',
                discount_applied DECIMAL(10,2) DEFAULT 0 COMMENT 'Valor do desconto aplicado',
                discount_applied_date DATETIME NULL COMMENT 'Data em que desconto foi aplicado',
                final_value DECIMAL(10,2) NULL COMMENT 'Valor final após desconto',
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_installment_id (installment_id),
                INDEX idx_payment_id (payment_id),
                INDEX idx_due_date (due_date),
                INDEX idx_status (status),
                INDEX idx_discount_applied (discount_applied),
                
                FOREIGN KEY (installment_id) REFERENCES installments(installment_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Parcelas individuais COM RASTREAMENTO DE DESCONTO'";
            
            $this->pdo->exec($sql);
            error_log("Tabela installment_payments criada/atualizada com campos de desconto");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela installment_payments: " . $e->getMessage());
            return false;
        }
    }

        /**
     * ===== FUNÇÃO PARA REGISTRAR APLICAÇÃO DE DESCONTO =====
     */
    public function recordDiscountApplication($paymentId, $originalValue, $discountValue, $finalValue) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE installment_payments 
                SET 
                    original_value = ?,
                    discount_applied = ?,
                    discount_applied_date = NOW(),
                    final_value = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE payment_id = ?
            ");
            
            $result = $stmt->execute([$originalValue, $discountValue, $finalValue, $paymentId]);
            
            if ($result) {
                error_log("Desconto registrado: Payment {$paymentId} - Desconto: R$ {$discountValue}");
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar aplicação de desconto: " . $e->getMessage());
            return false;
        }
    }

    
    /**
     * Obter parcelamentos por período (com filtro de polo)
     */
    public function getInstallmentsByPeriod($startDate, $endDate, $poloId = null) {
        try {
            $query = "
                SELECT i.*, c.name as customer_name, c.email as customer_email,
                       u.nome as created_by_name,
                       COUNT(ip.id) as payments_made,
                       SUM(CASE WHEN ip.status = 'RECEIVED' THEN ip.value ELSE 0 END) as amount_received
                FROM installments i
                LEFT JOIN customers c ON i.customer_id = c.id
                LEFT JOIN usuarios u ON i.created_by = u.id
                LEFT JOIN installment_payments ip ON i.installment_id = ip.installment_id AND ip.status = 'RECEIVED'
                WHERE i.created_at BETWEEN ? AND ?
            ";
            
            $params = [$startDate, $endDate];
            
            if ($poloId !== null) {
                $query .= " AND i.polo_id = ?";
                $params[] = $poloId;
            }
            
            $query .= " GROUP BY i.id ORDER BY i.created_at DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar parcelamentos por período: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter informações de um parcelamento específico
     */
    public function getInstallmentInfo($installmentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT i.*, c.name as customer_name, c.email as customer_email,
                       u.nome as created_by_name
                FROM installments i
                LEFT JOIN customers c ON i.customer_id = c.id
                LEFT JOIN usuarios u ON i.created_by = u.id
                WHERE i.installment_id = ?
            ");
            
            $stmt->execute([$installmentId]);
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar informações do parcelamento: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Salvar parcela individual de um parcelamento
     */
    public function saveInstallmentPayment($installmentId, $paymentData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO installment_payments (
                    installment_id, payment_id, installment_number, 
                    due_date, value, status
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    paid_date = CASE WHEN VALUES(status) = 'RECEIVED' THEN NOW() ELSE paid_date END,
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([
                $installmentId,
                $paymentData['id'],
                $paymentData['installment_number'] ?? 1,
                $paymentData['dueDate'],
                $paymentData['value'],
                $paymentData['status'] ?? 'PENDING'
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao salvar parcela individual: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualizar status de uma parcela
     */
    public function updateInstallmentPaymentStatus($paymentId, $status) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE installment_payments 
                SET status = ?, 
                    paid_date = CASE WHEN ? = 'RECEIVED' THEN NOW() ELSE paid_date END,
                    updated_at = CURRENT_TIMESTAMP
                WHERE payment_id = ?
            ");
            
            return $stmt->execute([$status, $status, $paymentId]);
            
        } catch (PDOException $e) {
            error_log("Erro ao atualizar status da parcela: " . $e->getMessage());
            return false;
        }
    }
    
        /**
     * ===== FUNÇÃO ATUALIZADA: ESTATÍSTICAS COM DESCONTO =====
     */
    public function getInstallmentStatsWithDiscount($poloId = null) {
        try {
            $query = "
                SELECT 
                    COUNT(*) as total_installments,
                    SUM(installment_count) as total_payments_expected,
                    SUM(total_value) as total_value_expected,
                    AVG(installment_count) as avg_installments_per_customer,
                    AVG(installment_value) as avg_installment_value,
                    COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) as active_installments,
                    COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_installments,
                    COUNT(CASE WHEN has_splits = 1 THEN 1 END) as installments_with_splits,
                    
                    -- ===== ESTATÍSTICAS DE DESCONTO =====
                    COUNT(CASE WHEN has_discount = 1 THEN 1 END) as installments_with_discount,
                    SUM(CASE WHEN has_discount = 1 THEN (discount_value * installment_count) ELSE 0 END) as total_discount_potential,
                    AVG(CASE WHEN has_discount = 1 THEN discount_value ELSE NULL END) as avg_discount_value,
                    
                    -- Taxa de adoção do desconto
                    CASE 
                        WHEN COUNT(*) > 0 THEN 
                            ROUND((COUNT(CASE WHEN has_discount = 1 THEN 1 END) / COUNT(*)) * 100, 2)
                        ELSE 0 
                    END as discount_adoption_rate
                    
                FROM installments
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($poloId !== null) {
                $query .= " AND polo_id = ?";
                $params[] = $poloId;
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            
            // Adicionar estatísticas calculadas
            $result['discount_efficiency'] = 0;
            if ($result['installments_with_discount'] > 0 && $result['total_discount_potential'] > 0) {
                // Calcular eficiência do desconto (quantos descontos foram realmente utilizados)
                $stmt2 = $this->pdo->prepare("
                    SELECT COUNT(*) as discounts_used 
                    FROM installment_payments ip
                    JOIN installments i ON ip.installment_id = i.installment_id
                    WHERE i.has_discount = 1 AND ip.status = 'RECEIVED' AND ip.discount_applied > 0
                    " . ($poloId !== null ? " AND i.polo_id = ?" : "")
                );
                
                if ($poloId !== null) {
                    $stmt2->execute([$poloId]);
                } else {
                    $stmt2->execute();
                }
                
                $discountStats = $stmt2->fetch();
                $result['discounts_used'] = $discountStats['discounts_used'] ?? 0;
                
                // Calcular taxa de utilização do desconto
                if ($result['total_payments_expected'] > 0) {
                    $result['discount_efficiency'] = round(
                        ($result['discounts_used'] / $result['total_payments_expected']) * 100, 2
                    );
                }
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Erro ao obter estatísticas com desconto: " . $e->getMessage());
            return [
                'total_installments' => 0,
                'total_payments_expected' => 0,
                'total_value_expected' => 0,
                'avg_installments_per_customer' => 0,
                'avg_installment_value' => 0,
                'active_installments' => 0,
                'completed_installments' => 0,
                'installments_with_splits' => 0,
                'installments_with_discount' => 0,
                'total_discount_potential' => 0,
                'avg_discount_value' => 0,
                'discount_adoption_rate' => 0,
                'discount_efficiency' => 0,
                'discounts_used' => 0
            ];
        }
    }

        /**
     * ===== FUNÇÃO PARA ATUALIZAR TABELA EXISTENTE =====
     */
    public function addDiscountFieldsToExistingTable() {
        try {
            error_log("Adicionando campos de desconto à tabela existente...");
            
            // Verificar se campos já existem
            $stmt = $this->pdo->query("SHOW COLUMNS FROM installments LIKE 'has_discount'");
            if ($stmt->rowCount() > 0) {
                error_log("Campos de desconto já existem na tabela");
                return true;
            }
            
            // Adicionar campos de desconto
            $alterQueries = [
                "ALTER TABLE installments ADD COLUMN has_discount BOOLEAN DEFAULT 0 AFTER status",
                "ALTER TABLE installments ADD COLUMN discount_value DECIMAL(10,2) NULL COMMENT 'Valor fixo do desconto por parcela' AFTER has_discount",
                "ALTER TABLE installments ADD COLUMN discount_type ENUM('FIXED', 'PERCENTAGE') DEFAULT 'FIXED' COMMENT 'Tipo do desconto' AFTER discount_value",
                "ALTER TABLE installments ADD COLUMN discount_deadline_type ENUM('DUE_DATE', 'DAYS_BEFORE', 'CUSTOM') DEFAULT 'DUE_DATE' COMMENT 'Tipo de prazo' AFTER discount_type",
                "ALTER TABLE installments ADD COLUMN discount_description TEXT NULL COMMENT 'Descrição do desconto' AFTER discount_deadline_type",
                "ALTER TABLE installments ADD INDEX idx_has_discount (has_discount)"
            ];
            
            foreach ($alterQueries as $query) {
                try {
                    $this->pdo->exec($query);
                    error_log("Executado: " . $query);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                        error_log("Erro ao executar: " . $query . " - " . $e->getMessage());
                    }
                }
            }
            
            error_log("Campos de desconto adicionados à tabela installments");
            return true;
            
        } catch (PDOException $e) {
            error_log("Erro ao adicionar campos de desconto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter estatísticas de mensalidades (com filtro de polo)
     */
    public function getInstallmentStats($poloId = null) {
        try {
            $query = "
                SELECT 
                    COUNT(*) as total_installments,
                    SUM(installment_count) as total_payments_expected,
                    SUM(total_value) as total_value_expected,
                    AVG(installment_count) as avg_installments_per_customer,
                    AVG(installment_value) as avg_installment_value,
                    COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) as active_installments,
                    COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_installments,
                    COUNT(CASE WHEN has_splits = 1 THEN 1 END) as installments_with_splits
                FROM installments
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($poloId !== null) {
                $query .= " AND polo_id = ?";
                $params[] = $poloId;
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Erro ao obter estatísticas de parcelamentos: " . $e->getMessage());
            return [
                'total_installments' => 0,
                'total_payments_expected' => 0,
                'total_value_expected' => 0,
                'avg_installments_per_customer' => 0,
                'avg_installment_value' => 0,
                'active_installments' => 0,
                'completed_installments' => 0,
                'installments_with_splits' => 0
            ];
        }
    }
    
    /**
     * Obter mensalidades recentes (com filtro de polo)
     */
    public function getRecentInstallments($limit = 10, $poloId = null) {
        try {
            $query = "
                SELECT i.*, c.name as customer_name, c.email as customer_email,
                       (SELECT COUNT(*) FROM installment_payments ip WHERE ip.installment_id = i.installment_id AND ip.status = 'RECEIVED') as payments_received,
                       (SELECT SUM(value) FROM installment_payments ip WHERE ip.installment_id = i.installment_id AND ip.status = 'RECEIVED') as amount_received
                FROM installments i
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($poloId !== null) {
                $query .= " AND i.polo_id = ?";
                $params[] = $poloId;
            }
            
            $query .= " ORDER BY i.created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar mensalidades recentes: " . $e->getMessage());
            return [];
        }
    }
    
    // ====================================================
    // MÉTODOS EXISTENTES MANTIDOS E ATUALIZADOS
    // ====================================================
    
    /**
     * Salvar Wallet ID com suporte a polo - MÉTODO MANTIDO
     */
    public function saveWalletId($walletData) {
        try {
            error_log("=== INÍCIO VERIFICAÇÃO WALLET ID ===");
            error_log("Dados recebidos: " . json_encode($walletData));
            
            $walletId = $walletData['wallet_id'];
            $novoPoloId = $walletData['polo_id'];
            
            error_log("UUID: {$walletId}");
            error_log("Novo Polo ID: " . ($novoPoloId ?? 'NULL'));
            
            // VERIFICAÇÃO: Buscar apenas no MESMO polo
            if ($novoPoloId === null) {
                $checkStmt = $this->pdo->prepare("
                    SELECT id, name, polo_id FROM wallet_ids 
                    WHERE wallet_id = ? AND polo_id IS NULL
                ");
                $checkStmt->execute([$walletId]);
            } else {
                $checkStmt = $this->pdo->prepare("
                    SELECT id, name, polo_id FROM wallet_ids 
                    WHERE wallet_id = ? AND polo_id = ?
                ");
                $checkStmt->execute([$walletId, $novoPoloId]);
            }
            
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                $contexto = $novoPoloId ? "no polo ID {$novoPoloId}" : "como registro global";
                throw new Exception("Este Wallet ID já está cadastrado {$contexto}: {$existing['name']}");
            }
            
            // INSERIR NOVO REGISTRO
            $stmt = $this->pdo->prepare("
                INSERT INTO wallet_ids (id, polo_id, wallet_id, name, description, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $params = [
                $walletData['id'],
                $walletData['polo_id'],
                $walletData['wallet_id'],
                $walletData['name'],
                $walletData['description'] ?? null,
                $walletData['is_active'] ?? 1
            ];
            
            $result = $stmt->execute($params);
            
            if ($result) {
                error_log("✅ SUCCESS: Wallet ID inserido");
                error_log("=== FIM VERIFICAÇÃO WALLET ID ===");
                return true;
            } else {
                throw new Exception("Falha na inserção");
            }
            
        } catch (PDOException $e) {
            error_log("❌ ERRO PDO: " . $e->getMessage());
            
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception("Erro de chave duplicada no banco de dados");
            }
            
            throw new Exception("Erro do banco: " . $e->getMessage());
        }
    }
    
    /**
     * Buscar Wallet IDs do polo atual - MÉTODO MANTIDO
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
     * Salvar cliente com polo - MÉTODO MANTIDO
     */
    public function saveCustomer($customerData) {
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
     * Salvar pagamento com polo - MÉTODO ATUALIZADO PARA SUPORTAR MENSALIDADES
     */
    public function savePayment($paymentData) {
        try {
            if (!isset($paymentData['polo_id']) && isset($_SESSION['polo_id'])) {
                $paymentData['polo_id'] = $_SESSION['polo_id'];
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO payments (
                    id, polo_id, customer_id, billing_type, status, value, 
                    description, due_date, installment_count, installment_id, created_at
                ) VALUES (
                    :id, :polo_id, :customer_id, :billing_type, :status, :value, 
                    :description, :due_date, :installment_count, :installment_id, NOW()
                )
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $result = $stmt->execute([
                'id' => $paymentData['id'],
                'polo_id' => $paymentData['polo_id'],
                'customer_id' => $paymentData['customer'],
                'billing_type' => $paymentData['billingType'],
                'status' => $paymentData['status'],
                'value' => $paymentData['value'],
                'description' => $paymentData['description'],
                'due_date' => $paymentData['dueDate'],
                'installment_count' => $paymentData['installmentCount'] ?? 1,
                'installment_id' => $paymentData['installment'] ?? null
            ]);
            
            // Se é parte de um parcelamento, salvar também na tabela específica
            if (isset($paymentData['installment']) && $paymentData['installment']) {
                $this->saveInstallmentPayment($paymentData['installment'], $paymentData);
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Erro ao salvar pagamento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter estatísticas com filtro por polo - MÉTODO ATUALIZADO COM MENSALIDADES
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
                $execParams = [$poloId, $poloId, $poloId, $poloId];
            }
            
            $stmt->execute($execParams);
            $stats = $stmt->fetch();
            
            // NOVAS ESTATÍSTICAS DE MENSALIDADES
            $installmentStats = $this->getInstallmentStats($poloId);
            
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
                'total_split_accounts' => 0,
                'total_payments' => $stats['total_payments'],
                'received_payments' => $stats['received_payments'],
                'total_value' => $stats['total_value'],
                'active_recipients' => $splitStats['active_recipients'] ?? 0,
                'total_split_value' => $splitStats['total_split_value'] ?? 0,
                'conversion_rate' => $stats['total_payments'] > 0 ? 
                    round(($stats['received_payments'] / $stats['total_payments']) * 100, 2) : 0,
                    
                // NOVAS ESTATÍSTICAS DE MENSALIDADES
                'total_installments' => $installmentStats['total_installments'],
                'total_installment_value' => $installmentStats['total_value_expected'],
                'active_installments' => $installmentStats['active_installments'],
                'avg_installments_per_customer' => round($installmentStats['avg_installments_per_customer'], 1),
                'installments_with_splits' => $installmentStats['installments_with_splits']
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
                'conversion_rate' => 0,
                'total_installments' => 0,
                'total_installment_value' => 0,
                'active_installments' => 0,
                'avg_installments_per_customer' => 0,
                'installments_with_splits' => 0
            ];
        }
    }

        /**
     * ===== RELATÓRIO DE DESEMPENHO DO DESCONTO =====
     */
    public function getDiscountPerformanceReport($startDate, $endDate, $poloId = null) {
        try {
            $query = "
                SELECT 
                    -- Dados básicos da mensalidade
                    i.installment_id,
                    c.name as customer_name,
                    i.description,
                    i.installment_count,
                    i.installment_value,
                    i.discount_value,
                    
                    -- Estatísticas de uso do desconto
                    COUNT(ip.id) as total_installments,
                    COUNT(CASE WHEN ip.discount_applied > 0 THEN 1 END) as installments_with_discount_used,
                    SUM(ip.discount_applied) as total_discount_used,
                    
                    -- Economia do cliente
                    (i.discount_value * i.installment_count) as potential_savings,
                    SUM(ip.discount_applied) as actual_savings,
                    
                    -- Taxa de utilização do desconto
                    CASE 
                        WHEN COUNT(ip.id) > 0 THEN 
                            ROUND((COUNT(CASE WHEN ip.discount_applied > 0 THEN 1 END) / COUNT(ip.id)) * 100, 2)
                        ELSE 0 
                    END as discount_usage_rate,
                    
                    -- Pagamentos no prazo (que ganharam desconto)
                    COUNT(CASE WHEN ip.status = 'RECEIVED' AND ip.paid_date <= ip.due_date AND ip.discount_applied > 0 THEN 1 END) as on_time_with_discount,
                    
                    -- Receita com desconto vs sem desconto
                    SUM(CASE WHEN ip.status = 'RECEIVED' THEN ip.final_value ELSE 0 END) as revenue_with_discount,
                    SUM(CASE WHEN ip.status = 'RECEIVED' THEN ip.original_value ELSE 0 END) as revenue_without_discount
                    
                FROM installments i
                LEFT JOIN customers c ON i.customer_id = c.id
                LEFT JOIN installment_payments ip ON i.installment_id = ip.installment_id
                WHERE i.has_discount = 1 
                AND i.created_at BETWEEN ? AND ?
            ";
            
            $params = [$startDate, $endDate];
            
            if ($poloId !== null) {
                $query .= " AND i.polo_id = ?";
                $params[] = $poloId;
            }
            
            $query .= " GROUP BY i.id ORDER BY discount_usage_rate DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro ao gerar relatório de performance do desconto: " . $e->getMessage());
            return [];
        }
    }

    
    /**
     * Buscar relatório de splits com filtro por polo - MÉTODO MANTIDO
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
        
        if ($poloId !== null) {
            $baseQuery .= " AND p.polo_id = ?";
            $params[] = $poloId;
        }
        
        $baseQuery .= " GROUP BY ps.wallet_id ORDER BY total_received DESC";
        
        $stmt = $this->pdo->prepare($baseQuery);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

        /**
     * ===== FUNÇÃO ATUALIZADA: OBTER RELATÓRIO COM DESCONTO =====
     */
    public function getInstallmentReportWithDiscount($startDate, $endDate, $poloId = null) {
        try {
            $query = "
                SELECT i.*, c.name as customer_name, c.email as customer_email,
                       u.nome as created_by_name,
                       
                       -- Estatísticas de pagamento
                       COUNT(ip.id) as payments_made,
                       SUM(CASE WHEN ip.status = 'RECEIVED' THEN ip.value ELSE 0 END) as amount_received,
                       
                       -- ===== ESTATÍSTICAS DE DESCONTO =====
                       CASE 
                           WHEN i.has_discount = 1 THEN 
                               (i.discount_value * i.installment_count)
                           ELSE 0 
                       END as total_discount_potential,
                       
                       CASE 
                           WHEN i.has_discount = 1 THEN 
                               COUNT(CASE WHEN ip.status = 'RECEIVED' AND ip.discount_applied > 0 THEN 1 END)
                           ELSE 0 
                       END as payments_with_discount_applied,
                       
                       -- Percentual de conclusão
                       CASE 
                           WHEN i.total_value > 0 THEN 
                               ROUND((SUM(CASE WHEN ip.status = 'RECEIVED' THEN ip.value ELSE 0 END) / i.total_value) * 100, 2)
                           ELSE 0 
                       END as completion_percentage
                       
                FROM installments i
                LEFT JOIN customers c ON i.customer_id = c.id
                LEFT JOIN usuarios u ON i.created_by = u.id
                LEFT JOIN installment_payments ip ON i.installment_id = ip.installment_id
                WHERE i.created_at BETWEEN ? AND ?
            ";
            
            $params = [$startDate, $endDate];
            
            if ($poloId !== null) {
                $query .= " AND i.polo_id = ?";
                $params[] = $poloId;
            }
            
            $query .= " GROUP BY i.id ORDER BY i.created_at DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar relatório com desconto: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * NOVO: Relatório específico de mensalidades/parcelamentos
     */
    public function getInstallmentReport($startDate, $endDate, $poloId = null) {
        try {
            $query = "
                SELECT 
                    i.*,
                    c.name as customer_name,
                    c.email as customer_email,
                    u.nome as created_by_name,
                    COUNT(ip.id) as payments_made,
                    SUM(CASE WHEN ip.status = 'RECEIVED' THEN ip.value ELSE 0 END) as amount_received,
                    (i.total_value - COALESCE(SUM(CASE WHEN ip.status = 'RECEIVED' THEN ip.value ELSE 0 END), 0)) as amount_pending,
                    ROUND((COALESCE(SUM(CASE WHEN ip.status = 'RECEIVED' THEN ip.value ELSE 0 END), 0) / i.total_value) * 100, 2) as completion_percentage
                FROM installments i
                LEFT JOIN customers c ON i.customer_id = c.id
                LEFT JOIN usuarios u ON i.created_by = u.id
                LEFT JOIN installment_payments ip ON i.installment_id = ip.installment_id
                WHERE i.created_at BETWEEN ? AND ?
            ";
            
            $params = [$startDate, $endDate];
            
            if ($poloId !== null) {
                $query .= " AND i.polo_id = ?";
                $params[] = $poloId;
            }
            
            $query .= " GROUP BY i.id ORDER BY i.created_at DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro ao gerar relatório de parcelamentos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * NOVO: Relatório de performance de mensalidades por cliente
     */
    public function getCustomerInstallmentPerformance($startDate, $endDate, $poloId = null) {
        try {
            $query = "
                SELECT 
                    c.name as customer_name,
                    c.email as customer_email,
                    COUNT(i.id) as total_installments,
                    SUM(i.installment_count) as total_payments_expected,
                    SUM(i.total_value) as total_value_expected,
                    COUNT(ip.id) as total_payments_made,
                    SUM(CASE WHEN ip.status = 'RECEIVED' THEN ip.value ELSE 0 END) as total_amount_received,
                    ROUND(
                        (COUNT(ip.id) / NULLIF(SUM(i.installment_count), 0)) * 100, 2
                    ) as payment_completion_rate,
                    AVG(i.installment_value) as avg_installment_value
                FROM customers c
                JOIN installments i ON c.id = i.customer_id
                LEFT JOIN installment_payments ip ON i.installment_id = ip.installment_id AND ip.status = 'RECEIVED'
                WHERE i.created_at BETWEEN ? AND ?
            ";
            
            $params = [$startDate, $endDate];
            
            if ($poloId !== null) {
                $query .= " AND i.polo_id = ?";
                $params[] = $poloId;
            }
            
            $query .= " GROUP BY c.id ORDER BY total_amount_received DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro ao gerar relatório de performance por cliente: " . $e->getMessage());
            return [];
        }
    }
    
    // ====================================================
    // MÉTODOS EXISTENTES MANTIDOS
    // ====================================================
    
    public function saveSplitAccount($accountData) {
        try {
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

        /**
     * ===== FUNÇÃO DE MIGRAÇÃO AUTOMÁTICA =====
     */
    public function ensureDatabaseStructureWithDiscount() {
        try {
            error_log("Verificando estrutura do banco com suporte a desconto...");
            
            // Verificar se tabela installments existe
            $result = $this->pdo->query("SHOW TABLES LIKE 'installments'");
            if ($result->rowCount() == 0) {
                // Criar tabela nova com desconto
                $this->createInstallmentsTableWithDiscount();
            } else {
                // Adicionar campos de desconto à tabela existente
                $this->addDiscountFieldsToExistingTable();
            }
            
            // Verificar/criar tabela de parcelas
            $this->createInstallmentPaymentsTable();
            
            // Criar outras tabelas se necessário
            $this->createCustomersTable();
            $this->createPaymentsTable();
            $this->createWalletIdsTable();
            $this->createPaymentSplitsTable();
            $this->createWebhookLogsTable();
            
            error_log("Estrutura do banco com desconto verificada/criada");
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao verificar estrutura com desconto: " . $e->getMessage());
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
    
    /**
     * Limpar logs antigos
     */
    public function cleanOldLogs() {
        $cutoffDate = date('Y-m-d', strtotime('-' . LOG_RETENTION_DAYS . ' days'));
        
        $stmt = $this->pdo->prepare("DELETE FROM webhook_logs WHERE processed_at < ?");
        $stmt->execute([$cutoffDate]);
        
        return $stmt->rowCount();
    }
    
    /**
     * NOVO: Verificar e criar estrutura completa do banco
     */
    public function ensureDatabaseStructure() {
        try {
            error_log("Verificando estrutura do banco de dados...");
            
            // Verificar se tabelas principais existem
            $tables = [
                'customers' => $this->createCustomersTable(),
                'payments' => $this->createPaymentsTable(),
                'wallet_ids' => $this->createWalletIdsTable(),
                'payment_splits' => $this->createPaymentSplitsTable(),
                'installments' => $this->createInstallmentsTable(),
                'installment_payments' => $this->createInstallmentPaymentsTable(),
                'webhook_logs' => $this->createWebhookLogsTable()
            ];
            
            $created = 0;
            foreach ($tables as $tableName => $result) {
                if ($result) {
                    $created++;
                    error_log("Tabela {$tableName} verificada/criada");
                }
            }
            
            error_log("Estrutura do banco verificada - {$created} tabelas processadas");
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao verificar estrutura do banco: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criar tabela de clientes se não existir
     */
    private function createCustomersTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS customers (
                id VARCHAR(100) PRIMARY KEY,
                polo_id INT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                cpf_cnpj VARCHAR(20),
                mobile_phone VARCHAR(20),
                address TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_polo_id (polo_id),
                INDEX idx_email (email),
                INDEX idx_cpf_cnpj (cpf_cnpj)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela customers: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criar tabela de pagamentos se não existir - ATUALIZADA COM MENSALIDADES
     */
    private function createPaymentsTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS payments (
                id VARCHAR(100) PRIMARY KEY,
                polo_id INT NULL,
                customer_id VARCHAR(100),
                billing_type VARCHAR(20),
                status VARCHAR(20),
                value DECIMAL(10,2),
                description TEXT,
                due_date DATE,
                received_date DATETIME NULL,
                installment_count INT DEFAULT 1,
                installment_id VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_polo_id (polo_id),
                INDEX idx_customer_id (customer_id),
                INDEX idx_status (status),
                INDEX idx_installment_id (installment_id),
                INDEX idx_due_date (due_date),
                INDEX idx_received_date (received_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela payments: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criar tabela de wallet IDs se não existir
     */
    private function createWalletIdsTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS wallet_ids (
                id VARCHAR(100) PRIMARY KEY,
                polo_id INT NULL,
                wallet_id VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_wallet_polo (wallet_id, polo_id),
                INDEX idx_polo_id (polo_id),
                INDEX idx_wallet_id (wallet_id),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela wallet_ids: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criar tabela de splits se não existir
     */
    private function createPaymentSplitsTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS payment_splits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                payment_id VARCHAR(100) NOT NULL,
                wallet_id VARCHAR(100) NOT NULL,
                split_type ENUM('PERCENTAGE', 'FIXED') NOT NULL,
                percentage_value DECIMAL(5,2) NULL,
                fixed_value DECIMAL(10,2) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_payment_id (payment_id),
                INDEX idx_wallet_id (wallet_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela payment_splits: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criar tabela de logs de webhook se não existir
     */
    private function createWebhookLogsTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS webhook_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                event_type VARCHAR(50),
                payment_id VARCHAR(100),
                payload JSON,
                status VARCHAR(20),
                error_message TEXT,
                processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_event_type (event_type),
                INDEX idx_payment_id (payment_id),
                INDEX idx_processed_at (processed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela webhook_logs: " . $e->getMessage());
            return false;
        }
    }

    /**
 * ===== FUNÇÃO PARA ATUALIZAÇÃO VIA CLI =====
 */
    function updateSystemForDiscount() {
    try {
        echo "🏷️ Atualizando sistema para suporte a DESCONTO em mensalidades...\n";
        
        $db = DatabaseManager::getInstance();
        
        if ($db->ensureDatabaseStructureWithDiscount()) {
            echo "✅ Estrutura do banco atualizada para desconto\n";
            echo "🎯 Novas funcionalidades:\n";
            echo "   • Desconto fixo por parcela\n";
            echo "   • Desconto válido até vencimento\n";
            echo "   • Rastreamento de uso do desconto\n";
            echo "   • Relatórios de performance do desconto\n";
            echo "   • Máximo de " . MAX_DISCOUNT_PERCENTAGE . "% por parcela\n";
            return true;
        } else {
            echo "❌ Erro ao atualizar estrutura do banco\n";
            return false;
        }
        
    } catch (Exception $e) {
        echo "❌ Erro na atualização: " . $e->getMessage() . "\n";
        return false;
    }
}
}

// Executar comandos via linha de comando
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    
    $command = isset($argv[1]) ? $argv[1] : '';
    
    switch ($command) {
        case 'add-discount':
            updateSystemForDiscount();
            break;
            
        case 'test-discount':
            try {
                $db = DatabaseManager::getInstance();
                $stats = $db->getInstallmentStatsWithDiscount();
                
                echo "📊 Estatísticas de Desconto:\n";
                echo "================================\n";
                echo "Mensalidades com desconto: " . $stats['installments_with_discount'] . "\n";
                echo "Potencial de desconto: R$ " . number_format($stats['total_discount_potential'], 2, ',', '.') . "\n";
                echo "Taxa de adoção: " . $stats['discount_adoption_rate'] . "%\n";
                echo "Eficiência do desconto: " . $stats['discount_efficiency'] . "%\n";
                
            } catch (Exception $e) {
                echo "❌ Erro: " . $e->getMessage() . "\n";
            }
            break;
            
        default:
            echo "Sistema de Mensalidades com DESCONTO v3.4\n";
            echo "==========================================\n\n";
            echo "Comandos disponíveis:\n";
            echo "  add-discount    - Atualizar banco para suporte a desconto\n";
            echo "  test-discount   - Testar funcionalidades de desconto\n\n";
            echo "💰 Novo: Desconto automático até vencimento!\n";
            echo "🏷️ Funcionalidades:\n";
            echo "  • Desconto fixo por parcela\n";
            echo "  • Aplicação automática até vencimento\n";
            echo "  • Máximo " . MAX_DISCOUNT_PERCENTAGE . "% por parcela\n";
            echo "  • Relatórios de performance\n";
            break;
    }
}

// Executar migração automática quando arquivo for incluído
if (!defined('SKIP_AUTO_UPDATE')) {
    try {
        $db = DatabaseManager::getInstance();
        
        // Verificar se campos de desconto existem
        $result = $db->getConnection()->query("SHOW COLUMNS FROM installments LIKE 'has_discount'");
        if ($result->rowCount() == 0) {
            error_log("Campos de desconto não existem, adicionando automaticamente...");
            $db->addDiscountFieldsToExistingTable();
            error_log("Sistema atualizado para suporte a desconto automaticamente");
        }
        
    } catch (Exception $e) {
        error_log("Erro na migração automática para desconto: " . $e->getMessage());
    }
}


/**
 * Classe AsaasConfig adaptada para multi-tenant - MANTIDA
 */
class AsaasConfig {
    
    public static function getInstance($environment = null, $poloId = null) {
        if (isset($_SESSION['usuario_tipo'])) {
            require_once 'config_manager.php';
            $dynamicConfig = new DynamicAsaasConfig();
            
            if ($poloId && $_SESSION['usuario_tipo'] === 'master') {
                return $dynamicConfig->getPoloInstance($poloId);
            } else {
                return $dynamicConfig->getInstance();
            }
        }
        
        return self::getStaticInstance($environment);
    }
    
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
 * Classe SystemStats adaptada para multi-tenant + mensalidades - ATUALIZADA
 */
class SystemStats {
    
    public static function getGeneralStats($poloId = null) {
        try {
            $db = DatabaseManager::getInstance();
            
            if (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] !== 'master') {
                return $db->getPoloFilteredStats($_SESSION['polo_id']);
            }
            
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
                'conversion_rate' => 0,
                // NOVAS ESTATÍSTICAS DE MENSALIDADES
                'total_installments' => 0,
                'total_installment_value' => 0,
                'active_installments' => 0,
                'avg_installments_per_customer' => 0,
                'installments_with_splits' => 0
            ];
        }
    }
}

/**
 * Classe WalletManager adaptada para multi-tenant - MANTIDA E MELHORADA
 */
class WalletManager {
    
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    public function createWallet($name, $walletId, $description = null, $poloId = null) {
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
    
    public function listWallets($page = 1, $limit = 20, $search = null, $poloId = null) {
        $offset = ($page - 1) * $limit;
        
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
    
    public function getWalletWithStats($walletId) {
        $stmt = $this->db->getConnection()->prepare("
            SELECT wi.*, 
                   COUNT(ps.id) as usage_count,
                   COALESCE(SUM(
                       CASE WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                       ELSE (p.value * ps.percentage_value / 100) END
                   ), 0) as total_earned
            FROM wallet_ids wi
            LEFT JOIN payment_splits ps ON wi.wallet_id = ps.wallet_id
            LEFT JOIN payments p ON ps.payment_id = p.id AND p.status = 'RECEIVED'
            WHERE wi.wallet_id = ?
            GROUP BY wi.id
        ");
        
        $stmt->execute([$walletId]);
        return $stmt->fetch();
    }
    
    // Outros métodos mantidos...
    public function toggleStatus($walletId) {
        $stmt = $this->db->getConnection()->prepare("
            UPDATE wallet_ids 
            SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP 
            WHERE wallet_id = ?
        ");
        
        return $stmt->execute([$walletId]);
    }
    
    public function deleteWallet($walletId) {
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

/**
 * NOVA CLASSE: InstallmentManager - Gerenciador de Mensalidades
 */
class InstallmentManager {
    
    private $db;
    private $asaas;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    /**
     * Inicializar conexão com ASAAS baseada no contexto
     */
    private function initAsaas() {
        if ($this->asaas === null) {
            $this->asaas = AsaasConfig::getInstance();
        }
        return $this->asaas;
    }
    
    /**
     * Criar nova mensalidade parcelada
     */
    public function createInstallment($paymentData, $splitsData, $installmentData) {
        try {
            $asaas = $this->initAsaas();
            
            // Validar dados
            $this->validateInstallmentData($installmentData);
            
            // Criar parcelamento via API ASAAS
            $result = $asaas->createInstallmentPaymentWithSplit($paymentData, $splitsData, $installmentData);
            
            // Salvar informações locais
            $installmentRecord = [
                'installment_id' => $result['installment'],
                'polo_id' => $_SESSION['polo_id'] ?? null,
                'customer_id' => $result['customer'],
                'installment_count' => $installmentData['installmentCount'],
                'installment_value' => $installmentData['installmentValue'],
                'total_value' => $installmentData['installmentCount'] * $installmentData['installmentValue'],
                'first_due_date' => $paymentData['dueDate'],
                'billing_type' => $paymentData['billingType'],
                'description' => $paymentData['description'],
                'has_splits' => !empty($splitsData),
                'splits_count' => count($splitsData),
                'created_by' => $_SESSION['usuario_id'] ?? null,
                'first_payment_id' => $result['id']
            ];
            
            $this->db->saveInstallmentRecord($installmentRecord);
            
            // Salvar splits se houver
            if (!empty($splitsData)) {
                $this->db->savePaymentSplits($result['id'], $splitsData);
            }
            
            return [
                'success' => true,
                'data' => $result,
                'installment_record' => $installmentRecord
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao criar mensalidade: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validar dados de parcelamento
     */
    private function validateInstallmentData($installmentData) {
        $installmentCount = (int)($installmentData['installmentCount'] ?? 0);
        $installmentValue = (float)($installmentData['installmentValue'] ?? 0);
        
        if ($installmentCount < MIN_INSTALLMENTS || $installmentCount > MAX_INSTALLMENTS) {
            throw new Exception("Número de parcelas deve ser entre " . MIN_INSTALLMENTS . " e " . MAX_INSTALLMENTS);
        }
        
        if ($installmentValue < MIN_INSTALLMENT_VALUE || $installmentValue > MAX_INSTALLMENT_VALUE) {
            throw new Exception("Valor da parcela deve ser entre R$ " . number_format(MIN_INSTALLMENT_VALUE, 2, ',', '.') . 
                              " e R$ " . number_format(MAX_INSTALLMENT_VALUE, 2, ',', '.'));
        }
        
        return true;
    }
    
    /**
     * Obter todas as parcelas de uma mensalidade
     */
    public function getInstallmentPayments($installmentId) {
        try {
            $asaas = $this->initAsaas();
            return $asaas->getInstallmentPayments($installmentId);
        } catch (Exception $e) {
            error_log("Erro ao buscar parcelas: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Gerar carnê em PDF
     */
    public function generatePaymentBook($installmentId) {
        try {
            $asaas = $this->initAsaas();
            return $asaas->generateInstallmentPaymentBook($installmentId);
        } catch (Exception $e) {
            error_log("Erro ao gerar carnê: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obter relatório de mensalidades
     */
    public function getInstallmentReport($startDate, $endDate, $poloId = null) {
        try {
            return $this->db->getInstallmentReport($startDate, $endDate, $poloId);
        } catch (Exception $e) {
            error_log("Erro ao gerar relatório de mensalidades: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Atualizar status de uma mensalidade
     */
    public function updateInstallmentStatus($installmentId, $status) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE installments 
                SET status = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE installment_id = ?
            ");
            
            return $stmt->execute([$status, $installmentId]);
            
        } catch (PDOException $e) {
            error_log("Erro ao atualizar status da mensalidade: " . $e->getMessage());
            return false;
        }
    }
}

// Funções de utilidade para migração/instalação - ATUALIZADAS
function updateSystemForInstallments() {
    try {
        $db = DatabaseManager::getInstance();
        
        echo "🔄 Atualizando sistema para suporte a mensalidades...\n";
        
        // Verificar e criar estrutura completa
        if ($db->ensureDatabaseStructure()) {
            echo "✅ Estrutura do banco verificada/criada\n";
        } else {
            echo "❌ Erro ao verificar estrutura do banco\n";
            return false;
        }
        
        // Verificar se colunas necessárias existem nas tabelas existentes
        $updates = [
            'payments' => [
                'installment_count' => "ALTER TABLE payments ADD COLUMN installment_count INT DEFAULT 1",
                'installment_id' => "ALTER TABLE payments ADD COLUMN installment_id VARCHAR(100) NULL",
            ],
            'wallet_ids' => [
                'polo_id' => "ALTER TABLE wallet_ids ADD COLUMN polo_id INT NULL"
            ]
        ];
        
        foreach ($updates as $table => $columns) {
            foreach ($columns as $column => $sql) {
                try {
                    // Verificar se coluna já existe
                    $check = $db->getConnection()->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
                    if ($check->rowCount() == 0) {
                        $db->getConnection()->exec($sql);
                        echo "  ✅ Coluna {$column} adicionada à tabela {$table}\n";
                    }
                } catch (Exception $e) {
                    echo "  ⚠️ Erro ao adicionar coluna {$column} em {$table}: " . $e->getMessage() . "\n";
                }
            }
        }
        
        // Criar índices necessários se não existirem
        $indexes = [
            'payments' => [
                'idx_installment_id' => "CREATE INDEX idx_installment_id ON payments (installment_id)",
                'idx_received_date' => "CREATE INDEX idx_received_date ON payments (received_date)"
            ]
        ];
        
        foreach ($indexes as $table => $indexList) {
            foreach ($indexList as $indexName => $sql) {
                try {
                    $db->getConnection()->exec($sql);
                    echo "  ✅ Índice {$indexName} criado em {$table}\n";
                } catch (Exception $e) {
                    // Ignora erro se índice já existe
                    if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                        echo "  ⚠️ Aviso ao criar índice {$indexName}: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        
        echo "✅ Sistema atualizado para suporte a mensalidades\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ Erro na atualização: " . $e->getMessage() . "\n";
        return false;
    }
}

function checkInstallmentSystemHealth() {
    try {
        echo "🔍 Verificando saúde do sistema de mensalidades...\n";
        
        $db = DatabaseManager::getInstance();
        $issues = [];
        
        // Verificar tabelas necessárias
        $requiredTables = [
            'installments' => 'Tabela principal de mensalidades',
            'installment_payments' => 'Tabela de parcelas individuais',
            'payments' => 'Tabela de pagamentos (com suporte a parcelamento)',
            'wallet_ids' => 'Tabela de Wallet IDs',
            'customers' => 'Tabela de clientes'
        ];
        
        foreach ($requiredTables as $table => $description) {
            try {
                $result = $db->getConnection()->query("SELECT COUNT(*) FROM {$table}");
                $count = $result->fetchColumn();
                echo "  ✅ {$description}: {$count} registros\n";
            } catch (Exception $e) {
                $issues[] = "Tabela {$table} não existe ou está inacessível";
                echo "  ❌ {$description}: ERRO\n";
            }
        }
        
        // Verificar constantes de configuração
        $requiredConstants = [
            'MAX_INSTALLMENTS' => MAX_INSTALLMENTS,
            'MIN_INSTALLMENTS' => MIN_INSTALLMENTS,
            'MIN_INSTALLMENT_VALUE' => MIN_INSTALLMENT_VALUE,
            'MAX_INSTALLMENT_VALUE' => MAX_INSTALLMENT_VALUE
        ];
        
        echo "\n📋 Configurações de mensalidade:\n";
        foreach ($requiredConstants as $const => $value) {
            echo "  • {$const}: {$value}\n";
        }
        
        // Verificar funcionalidade do InstallmentManager
        try {
            $manager = new InstallmentManager();
            echo "  ✅ InstallmentManager: Funcional\n";
        } catch (Exception $e) {
            $issues[] = "InstallmentManager não está funcionando: " . $e->getMessage();
            echo "  ❌ InstallmentManager: ERRO\n";
        }
        
        // Verificar funcionalidade de relatórios
        try {
            $stats = $db->getInstallmentStats();
            echo "  ✅ Relatórios de mensalidade: Funcional\n";
            echo "    - Total de mensalidades: " . ($stats['total_installments'] ?? 0) . "\n";
            echo "    - Valor total esperado: R$ " . number_format($stats['total_value_expected'] ?? 0, 2, ',', '.') . "\n";
        } catch (Exception $e) {
            $issues[] = "Relatórios de mensalidade não estão funcionando: " . $e->getMessage();
            echo "  ❌ Relatórios: ERRO\n";
        }
        
        if (empty($issues)) {
            echo "\n🎉 Sistema de mensalidades está funcionando perfeitamente!\n";
            return true;
        } else {
            echo "\n⚠️ Problemas encontrados:\n";
            foreach ($issues as $issue) {
                echo "  • {$issue}\n";
            }
            return false;
        }
        
    } catch (Exception $e) {
        echo "❌ Erro na verificação: " . $e->getMessage() . "\n";
        return false;
    }
}

function generateInstallmentTestData() {
    try {
        echo "🧪 Gerando dados de teste para mensalidades...\n";
        
        $db = DatabaseManager::getInstance();
        
        // Verificar se já existem dados
        $stmt = $db->getConnection()->query("SELECT COUNT(*) FROM installments");
        $existingCount = $stmt->fetchColumn();
        
        if ($existingCount > 0) {
            echo "ℹ️ Já existem {$existingCount} mensalidades no sistema\n";
            if (!confirm("Deseja continuar adicionando dados de teste?")) {
                return false;
            }
        }
        
        // Dados de teste
        $testData = [
            [
                'installment_id' => 'test_inst_' . uniqid(),
                'polo_id' => 1,
                'customer_id' => 'test_customer_1',
                'installment_count' => 12,
                'installment_value' => 100.00,
                'total_value' => 1200.00,
                'first_due_date' => date('Y-m-d', strtotime('+1 month')),
                'billing_type' => 'BOLETO',
                'description' => 'Mensalidade Teste - Curso Técnico',
                'has_splits' => true,
                'splits_count' => 2,
                'created_by' => 1,
                'first_payment_id' => 'test_payment_1'
            ],
            [
                'installment_id' => 'test_inst_' . uniqid(),
                'polo_id' => 1,
                'customer_id' => 'test_customer_2',
                'installment_count' => 24,
                'installment_value' => 150.00,
                'total_value' => 3600.00,
                'first_due_date' => date('Y-m-d', strtotime('+2 weeks')),
                'billing_type' => 'PIX',
                'description' => 'Mensalidade Teste - Curso Superior',
                'has_splits' => false,
                'splits_count' => 0,
                'created_by' => 1,
                'first_payment_id' => 'test_payment_2'
            ]
        ];
        
        $created = 0;
        foreach ($testData as $data) {
            try {
                $db->saveInstallmentRecord($data);
                $created++;
                echo "  ✅ Mensalidade teste criada: {$data['description']}\n";
            } catch (Exception $e) {
                echo "  ❌ Erro ao criar mensalidade teste: " . $e->getMessage() . "\n";
            }
        }
        
        echo "✅ {$created} mensalidades de teste criadas\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ Erro ao gerar dados de teste: " . $e->getMessage() . "\n";
        return false;
    }
}

function confirm($message) {
    echo $message . " (s/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    return strtolower(trim($line)) === 's';
}

// Executar comandos via linha de comando
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    
    $command = isset($argv[1]) ? $argv[1] : '';
    
    switch ($command) {
        case 'update-installments':
            updateSystemForInstallments();
            break;
            
        case 'health-check':
            checkInstallmentSystemHealth();
            break;
            
        case 'test-data':
            generateInstallmentTestData();
            break;
            
        case 'create-tables':
            try {
                $db = DatabaseManager::getInstance();
                if ($db->ensureDatabaseStructure()) {
                    echo "✅ Todas as tabelas foram criadas/verificadas\n";
                } else {
                    echo "❌ Erro ao criar/verificar tabelas\n";
                }
            } catch (Exception $e) {
                echo "❌ Erro: " . $e->getMessage() . "\n";
            }
            break;
            
        case 'stats':
            try {
                $db = DatabaseManager::getInstance();
                $stats = $db->getInstallmentStats();
                
                echo "📊 Estatísticas do Sistema de Mensalidades:\n";
                echo "=========================================\n";
                echo "Total de mensalidades: " . $stats['total_installments'] . "\n";
                echo "Total de parcelas esperadas: " . $stats['total_payments_expected'] . "\n";
                echo "Valor total esperado: R$ " . number_format($stats['total_value_expected'], 2, ',', '.') . "\n";
                echo "Média de parcelas por mensalidade: " . round($stats['avg_installments_per_customer'], 1) . "\n";
                echo "Valor médio por parcela: R$ " . number_format($stats['avg_installment_value'], 2, ',', '.') . "\n";
                echo "Mensalidades ativas: " . $stats['active_installments'] . "\n";
                echo "Mensalidades concluídas: " . $stats['completed_installments'] . "\n";
                echo "Mensalidades com splits: " . $stats['installments_with_splits'] . "\n";
                
            } catch (Exception $e) {
                echo "❌ Erro ao obter estatísticas: " . $e->getMessage() . "\n";
            }
            break;
            
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
            echo "Sistema de Split ASAAS Multi-Tenant + Mensalidades v3.3\n";
            echo "=====================================================\n\n";
            echo "Comandos disponíveis:\n";
            echo "  update-installments  - Atualizar sistema para mensalidades\n";
            echo "  health-check        - Verificar saúde do sistema de mensalidades\n";
            echo "  test-data           - Gerar dados de teste para mensalidades\n";
            echo "  create-tables       - Criar/verificar todas as tabelas\n";
            echo "  stats              - Mostrar estatísticas de mensalidades\n";
            echo "  update-multitenant  - Atualizar sistema para multi-tenant\n";
            echo "  test-auth          - Testar sistema de autenticação\n\n";
            echo "🆕 Novo: Sistema com suporte a mensalidades parceladas!\n";
            echo "💳 Funcionalidades:\n";
            echo "  • Mensalidades de 2 a 24 parcelas\n";
            echo "  • Splits automáticos em todas as parcelas\n";
            echo "  • Geração de carnês em PDF\n";
            echo "  • Relatórios detalhados de mensalidades\n";
            echo "  • Controle multi-tenant por polo\n";
            break;
    }
}

// Função para atualização automática ao incluir o arquivo
function autoUpdateSystemIfNeeded() {
    try {
        $db = DatabaseManager::getInstance();
        
        // Verificar se tabela de installments existe
        $result = $db->getConnection()->query("SHOW TABLES LIKE 'installments'");
        if ($result->rowCount() == 0) {
            error_log("Tabela installments não existe, criando automaticamente...");
            $db->createInstallmentsTable();
            error_log("Sistema de mensalidades configurado automaticamente");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Erro na atualização automática: " . $e->getMessage());
        return false;
    }
}

// Executar atualização automática quando arquivo for incluído
if (!defined('SKIP_AUTO_UPDATE')) {
    autoUpdateSystemIfNeeded();
}

/**
 * Classe para validação de dados de mensalidade
 */
class InstallmentValidator {
    
    public static function validateInstallmentData($data) {
        $errors = [];
        
        // Validar número de parcelas
        $installmentCount = (int)($data['installmentCount'] ?? 0);
        if ($installmentCount < MIN_INSTALLMENTS) {
            $errors[] = 'Número mínimo de parcelas é ' . MIN_INSTALLMENTS;
        }
        if ($installmentCount > MAX_INSTALLMENTS) {
            $errors[] = 'Número máximo de parcelas é ' . MAX_INSTALLMENTS;
        }
        
        // Validar valor da parcela
        $installmentValue = (float)($data['installmentValue'] ?? 0);
        if ($installmentValue < MIN_INSTALLMENT_VALUE) {
            $errors[] = 'Valor mínimo da parcela é R$ ' . number_format(MIN_INSTALLMENT_VALUE, 2, ',', '.');
        }
        if ($installmentValue > MAX_INSTALLMENT_VALUE) {
            $errors[] = 'Valor máximo da parcela é R$ ' . number_format(MAX_INSTALLMENT_VALUE, 2, ',', '.');
        }
        
        // Validar data de vencimento
        if (empty($data['dueDate'])) {
            $errors[] = 'Data de vencimento é obrigatória';
        } else {
            $dueDate = strtotime($data['dueDate']);
            if ($dueDate < strtotime('today')) {
                $errors[] = 'Data de vencimento não pode ser anterior a hoje';
            }
            
            // Não pode ser muito distante (máximo 1 ano)
            $maxDate = strtotime('+1 year');
            if ($dueDate > $maxDate) {
                $errors[] = 'Data de vencimento muito distante (máximo 1 ano)';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public static function validateSplitsData($splits, $installmentValue) {
        $errors = [];
        $totalPercentage = 0;
        $totalFixedValue = 0;
        
        foreach ($splits as $split) {
            if (empty($split['walletId'])) {
                continue; // Split vazio, ignorar
            }
            
            if (!empty($split['percentualValue'])) {
                $percentage = floatval($split['percentualValue']);
                if ($percentage <= 0 || $percentage > 100) {
                    $errors[] = 'Percentual deve ser entre 0.01% e 100%';
                }
                $totalPercentage += $percentage;
            }
            
            if (!empty($split['fixedValue'])) {
                $fixedValue = floatval($split['fixedValue']);
                if ($fixedValue <= 0) {
                    $errors[] = 'Valor fixo deve ser maior que zero';
                }
                if ($fixedValue >= $installmentValue) {
                    $errors[] = 'Valor fixo não pode ser maior ou igual ao valor da parcela';
                }
                $totalFixedValue += $fixedValue;
            }
        }
        
        if ($totalPercentage > 100) {
            $errors[] = 'A soma dos percentuais não pode exceder 100%';
        }
        
        if ($totalFixedValue >= $installmentValue) {
            $errors[] = 'A soma dos valores fixos não pode ser maior ou igual ao valor da parcela';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'total_percentage' => $totalPercentage,
            'total_fixed_value' => $totalFixedValue
        ];
    }
}

/**
 * Classe utilitária para formatação de mensalidades
 */
class InstallmentFormatter {
    
    public static function formatInstallmentSummary($installmentData) {
        return [
            'parcelas' => $installmentData['installment_count'] . 'x',
            'valor_parcela' => 'R$ ' . number_format($installmentData['installment_value'], 2, ',', '.'),
            'valor_total' => 'R$ ' . number_format($installmentData['total_value'], 2, ',', '.'),
            'primeiro_vencimento' => date('d/m/Y', strtotime($installmentData['first_due_date'])),
            'tipo_cobranca' => $installmentData['billing_type'],
            'status_formatado' => self::getStatusLabel($installmentData['status'] ?? 'ACTIVE'),
            'progresso' => self::calculateProgress($installmentData)
        ];
    }
    
    public static function getStatusLabel($status) {
        $labels = [
            'ACTIVE' => 'Ativa',
            'COMPLETED' => 'Concluída',
            'CANCELLED' => 'Cancelada',
            'PENDING' => 'Pendente'
        ];
        
        return $labels[$status] ?? $status;
    }
    
    public static function calculateProgress($installmentData) {
        $paymentsReceived = $installmentData['payments_received'] ?? 0;
        $totalPayments = $installmentData['installment_count'] ?? 1;
        
        $percentage = $totalPayments > 0 ? ($paymentsReceived / $totalPayments) * 100 : 0;
        
        return [
            'percentage' => round($percentage, 1),
            'received' => $paymentsReceived,
            'total' => $totalPayments,
            'remaining' => $totalPayments - $paymentsReceived
        ];
    }
    
    public static function generateDueDatesPreview($firstDate, $installmentCount) {
        $dates = [];
        $currentDate = new DateTime($firstDate);
        
        for ($i = 0; $i < min($installmentCount, 6); $i++) {
            $dates[] = [
                'parcela' => $i + 1,
                'data' => $currentDate->format('d/m/Y'),
                'data_iso' => $currentDate->format('Y-m-d'),
                'mes_ano' => $currentDate->format('m/Y')
            ];
            
            $currentDate->add(new DateInterval('P1M'));
        }
        
        return $dates;
    }
}

// Log de inicialização
error_log("Sistema de mensalidades COM DESCONTO carregado - v3.4");
error_log("Configurações: MAX_DISCOUNT=" . MAX_DISCOUNT_PERCENTAGE . "%, TIPO=" . DEFAULT_DISCOUNT_TYPE);
?>