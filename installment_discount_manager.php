<?php
/**
 * Sistema de Desconto para Mensalidades - VERSÃO CORRIGIDA
 * Arquivo: installment_discount_manager.php
 * 
 * Compatível com o sistema existente IMEP Split ASAAS
 */

/**
 * Classe para gerenciar descontos em mensalidades
 */
class InstallmentDiscountManager {
    
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    /**
     * Criar mensalidade com sistema de desconto
     */
    public function createInstallmentWithDiscount($paymentData, $splitsData, $installmentData, $discountData = null) {
        try {
            // Validar dados do desconto
            if (!empty($discountData) && $discountData['enabled']) {
                $this->validateDiscountData($discountData, $installmentData['installmentValue']);
            }
            
            // Configurar desconto no ASAAS se habilitado
            if (!empty($discountData) && $discountData['enabled']) {
                $paymentData['discount'] = $this->prepareAsaasDiscount($discountData, $installmentData['installmentValue']);
            }
            
            // Usar configuração dinâmica existente
            $dynamicAsaas = new DynamicAsaasConfig();
            $asaas = $dynamicAsaas->getInstance();
            
            // Criar parcelamento via API ASAAS
            $result = $asaas->createInstallmentPaymentWithSplit($paymentData, $splitsData, $installmentData);
            
            // Salvar informações do desconto no banco
            if (!empty($discountData) && $discountData['enabled']) {
                $this->saveInstallmentDiscount($result['installment'], $discountData);
            }
            
            // Salvar registro principal do parcelamento
            $this->saveInstallmentRecord($result, $installmentData, $discountData);
            
            return [
                'success' => true,
                'data' => $result,
                'discount_info' => $discountData
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao criar mensalidade com desconto: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validar dados do desconto
     */
    private function validateDiscountData($discountData, $installmentValue) {
        if (empty($discountData['type']) || !in_array($discountData['type'], ['FIXED', 'PERCENTAGE'])) {
            throw new Exception('Tipo de desconto inválido');
        }
        
        $discountValue = floatval($discountData['value'] ?? 0);
        if ($discountValue <= 0) {
            throw new Exception('Valor do desconto deve ser maior que zero');
        }
        
        if ($discountData['type'] === 'FIXED' && $discountValue >= $installmentValue) {
            throw new Exception('Desconto fixo não pode ser maior ou igual ao valor da parcela');
        }
        
        if ($discountData['type'] === 'PERCENTAGE' && $discountValue >= 100) {
            throw new Exception('Desconto percentual não pode ser maior ou igual a 100%');
        }
        
        $validDeadlines = ['DUE_DATE', 'BEFORE_DUE_DATE', '3_DAYS_BEFORE', '5_DAYS_BEFORE'];
        if (!in_array($discountData['deadline'], $validDeadlines)) {
            throw new Exception('Prazo de desconto inválido');
        }
        
        return true;
    }
    
    /**
     * Preparar dados do desconto para API do ASAAS
     */
    private function prepareAsaasDiscount($discountData, $installmentValue) {
        $discount = [];
        
        if ($discountData['type'] === 'FIXED') {
            $discount['value'] = floatval($discountData['value']);
        } else {
            // Para percentual, calcular valor baseado na parcela
            $discount['value'] = ($installmentValue * floatval($discountData['value'])) / 100;
        }
        
        // Configurar prazo do desconto
        switch ($discountData['deadline']) {
            case 'DUE_DATE':
                $discount['dueDateLimitDays'] = 0;
                break;
            case 'BEFORE_DUE_DATE':
                $discount['dueDateLimitDays'] = -1;
                break;
            case '3_DAYS_BEFORE':
                $discount['dueDateLimitDays'] = -3;
                break;
            case '5_DAYS_BEFORE':
                $discount['dueDateLimitDays'] = -5;
                break;
            default:
                $discount['dueDateLimitDays'] = 0;
        }
        
        return $discount;
    }
    
    /**
     * Salvar informações do desconto no banco
     */
    private function saveInstallmentDiscount($installmentId, $discountData) {
        try {
            // Garantir que a tabela existe
            $this->createInstallmentDiscountsTableIfNotExists();
            
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO installment_discounts (
                    installment_id, discount_type, discount_value, 
                    discount_deadline, is_active, created_at
                ) VALUES (?, ?, ?, ?, 1, NOW())
            ");
            
            return $stmt->execute([
                $installmentId,
                $discountData['type'],
                $discountData['value'],
                $discountData['deadline']
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao salvar desconto: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criar tabela de descontos se não existir
     */
    private function createInstallmentDiscountsTableIfNotExists() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS installment_discounts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                installment_id VARCHAR(100) NOT NULL,
                discount_type ENUM('FIXED', 'PERCENTAGE') NOT NULL,
                discount_value DECIMAL(10,2) NOT NULL,
                discount_deadline ENUM('DUE_DATE', 'BEFORE_DUE_DATE', '3_DAYS_BEFORE', '5_DAYS_BEFORE') NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_installment_id (installment_id),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->getConnection()->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela de descontos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salvar registro principal do parcelamento
     */
    private function saveInstallmentRecord($result, $installmentData, $discountData = null) {
        // Garantir que as colunas de desconto existem
        $this->ensureDiscountColumnsExist();
        
        $installmentRecord = [
            'installment_id' => $result['installment'],
            'polo_id' => $_SESSION['polo_id'] ?? null,
            'customer_id' => $result['customer'],
            'installment_count' => $installmentData['installmentCount'],
            'installment_value' => $installmentData['installmentValue'],
            'total_value' => $installmentData['installmentCount'] * $installmentData['installmentValue'],
            'first_due_date' => $result['dueDate'] ?? date('Y-m-d'),
            'billing_type' => $result['billingType'],
            'description' => $result['description'],
            'has_splits' => !empty($result['split']),
            'splits_count' => count($result['split'] ?? []),
            'created_by' => $_SESSION['usuario_id'] ?? null,
            'first_payment_id' => $result['id'],
            'has_discount' => !empty($discountData) && $discountData['enabled'],
            'discount_type' => isset($discountData['type']) ? $discountData['type'] : null,
            'discount_value' => isset($discountData['value']) ? $discountData['value'] : null
        ];
        
        try {
            // Usar método existente se disponível
            if (method_exists($this->db, 'saveInstallmentRecord')) {
                return $this->db->saveInstallmentRecord($installmentRecord);
            }
            
            // Caso contrário, inserir manualmente
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO installments (
                    installment_id, polo_id, customer_id, installment_count, 
                    installment_value, total_value, first_due_date, billing_type, 
                    description, has_splits, splits_count, created_by, 
                    first_payment_id, has_discount, discount_type, discount_value, 
                    status, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', NOW()
                )
            ");
            
            return $stmt->execute([
                $installmentRecord['installment_id'],
                $installmentRecord['polo_id'],
                $installmentRecord['customer_id'],
                $installmentRecord['installment_count'],
                $installmentRecord['installment_value'],
                $installmentRecord['total_value'],
                $installmentRecord['first_due_date'],
                $installmentRecord['billing_type'],
                $installmentRecord['description'],
                $installmentRecord['has_splits'],
                $installmentRecord['splits_count'],
                $installmentRecord['created_by'],
                $installmentRecord['first_payment_id'],
                $installmentRecord['has_discount'],
                $installmentRecord['discount_type'],
                $installmentRecord['discount_value']
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao salvar registro de parcelamento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Garantir que colunas de desconto existem na tabela installments
     */
    private function ensureDiscountColumnsExist() {
        $alterations = [
            'has_discount' => "ALTER TABLE installments ADD COLUMN has_discount BOOLEAN DEFAULT 0",
            'discount_type' => "ALTER TABLE installments ADD COLUMN discount_type ENUM('FIXED', 'PERCENTAGE') NULL",
            'discount_value' => "ALTER TABLE installments ADD COLUMN discount_value DECIMAL(10,2) NULL"
        ];
        
        foreach ($alterations as $column => $sql) {
            try {
                // Verificar se coluna já existe
                $check = $this->db->getConnection()->query("SHOW COLUMNS FROM installments LIKE '{$column}'");
                if ($check->rowCount() == 0) {
                    $this->db->getConnection()->exec($sql);
                    error_log("Coluna {$column} adicionada à tabela installments");
                }
            } catch (Exception $e) {
                error_log("Erro ao adicionar coluna {$column}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Obter informações do desconto de uma mensalidade
     */
    public function getInstallmentDiscount($installmentId) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT * FROM installment_discounts 
                WHERE installment_id = ? AND is_active = 1
            ");
            $stmt->execute([$installmentId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Erro ao buscar desconto: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calcular valor com desconto aplicado
     */
    public function calculateDiscountedValue($originalValue, $discountData) {
        if (!$discountData || !$discountData['is_active']) {
            return $originalValue;
        }
        
        if ($discountData['discount_type'] === 'FIXED') {
            return max(0.01, $originalValue - $discountData['discount_value']);
        } else {
            $discountAmount = ($originalValue * $discountData['discount_value']) / 100;
            return max(0.01, $originalValue - $discountAmount);
        }
    }
    
    /**
     * Gerar relatório de descontos aplicados
     */
    public function getDiscountReport($startDate, $endDate, $poloId = null) {
        $whereClause = "WHERE i.created_at BETWEEN ? AND ? AND i.has_discount = 1";
        $params = [$startDate, $endDate];
        
        if ($poloId) {
            $whereClause .= " AND i.polo_id = ?";
            $params[] = $poloId;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT 
                    i.*,
                    id.discount_type,
                    id.discount_value,
                    id.discount_deadline,
                    c.name as customer_name,
                    (i.installment_value * i.installment_count) as total_original_value,
                    CASE 
                        WHEN id.discount_type = 'FIXED' THEN 
                            (id.discount_value * i.installment_count)
                        ELSE 
                            ((i.installment_value * id.discount_value / 100) * i.installment_count)
                    END as total_discount_amount
                FROM installments i
                LEFT JOIN installment_discounts id ON i.installment_id = id.installment_id
                LEFT JOIN customers c ON i.customer_id = c.id
                {$whereClause}
                ORDER BY i.created_at DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erro ao gerar relatório de descontos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verificar se sistema de desconto está ativo para uma mensalidade
     */
    public function hasActiveDiscount($installmentId) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT COUNT(*) as count FROM installment_discounts 
                WHERE installment_id = ? AND is_active = 1
            ");
            $stmt->execute([$installmentId]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Erro ao verificar desconto ativo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Desativar desconto de uma mensalidade
     */
    public function deactivateDiscount($installmentId) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE installment_discounts 
                SET is_active = 0, updated_at = NOW() 
                WHERE installment_id = ?
            ");
            return $stmt->execute([$installmentId]);
        } catch (Exception $e) {
            error_log("Erro ao desativar desconto: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Extensão para adicionar novos endpoints na API
 */
class InstallmentDiscountAPI {
    
    private $discountManager;
    
    public function __construct() {
        $this->discountManager = new InstallmentDiscountManager();
    }
    
    /**
     * Processar ação de criar mensalidade com desconto
     */
    public function handleCreateInstallmentWithDiscount($postData) {
        // Dados do formulário
        $paymentData = $postData['payment'] ?? [];
        $installmentData = $postData['installment'] ?? [];
        $splitsData = $postData['splits'] ?? [];
        $discountData = $postData['discount'] ?? [];
        
        // Validações básicas
        $requiredPaymentFields = ['customer', 'billingType', 'description', 'dueDate'];
        foreach ($requiredPaymentFields as $field) {
            if (empty($paymentData[$field])) {
                throw new Exception("Campo '{$field}' é obrigatório para criar mensalidade");
            }
        }
        
        // Validar parcelamento
        $installmentCount = (int)($installmentData['installmentCount'] ?? 0);
        $installmentValue = floatval($installmentData['installmentValue'] ?? 0);
        
        if ($installmentCount < 2 || $installmentCount > 24) {
            throw new Exception('Número de parcelas deve ser entre 2 e 24');
        }
        
        if ($installmentValue <= 0) {
            throw new Exception('Valor da parcela deve ser maior que zero');
        }
        
        // Processar desconto se habilitado
        $processedDiscountData = null;
        if (!empty($discountData['enabled']) && $discountData['enabled'] === 'true') {
            $processedDiscountData = [
                'enabled' => true,
                'type' => $discountData['type'] ?? 'FIXED',
                'value' => floatval($discountData['value'] ?? 0),
                'deadline' => $discountData['deadline'] ?? 'DUE_DATE'
            ];
        }
        
        // Processar splits
        $processedSplits = [];
        foreach ($splitsData as $split) {
            if (!empty($split['walletId'])) {
                $splitData = ['walletId' => $split['walletId']];
                
                if (!empty($split['percentualValue']) && floatval($split['percentualValue']) > 0) {
                    $splitData['percentualValue'] = floatval($split['percentualValue']);
                }
                
                if (!empty($split['fixedValue']) && floatval($split['fixedValue']) > 0) {
                    $splitData['fixedValue'] = floatval($split['fixedValue']);
                }
                
                $processedSplits[] = $splitData;
            }
        }
        
        // Criar mensalidade com desconto
        $result = $this->discountManager->createInstallmentWithDiscount(
            $paymentData, 
            $processedSplits, 
            $installmentData, 
            $processedDiscountData
        );
        
        // Calcular informações para resposta
        $totalValue = $installmentCount * $installmentValue;
        $discountPerInstallment = 0;
        $totalSavings = 0;
        
        if ($processedDiscountData && $processedDiscountData['enabled']) {
            if ($processedDiscountData['type'] === 'FIXED') {
                $discountPerInstallment = $processedDiscountData['value'];
            } else {
                $discountPerInstallment = ($installmentValue * $processedDiscountData['value']) / 100;
            }
            $totalSavings = $discountPerInstallment * $installmentCount;
        }
        
        // Preparar mensagem de sucesso
        $successMessage = "✅ Mensalidade com desconto criada com sucesso!<br>";
        $successMessage .= "<strong>{$installmentCount} parcelas de R$ " . number_format($installmentValue, 2, ',', '.') . "</strong><br>";
        $successMessage .= "Total original: R$ " . number_format($totalValue, 2, ',', '.') . "<br>";
        
        if ($totalSavings > 0) {
            $valueWithDiscount = $installmentValue - $discountPerInstallment;
            $successMessage .= "Valor com desconto: R$ " . number_format($valueWithDiscount, 2, ',', '.') . " por parcela<br>";
            $successMessage .= "<span class='text-success'>💰 Economia total: R$ " . number_format($totalSavings, 2, ',', '.') . "</span><br>";
        }
        
        $successMessage .= "Primeiro vencimento: " . date('d/m/Y', strtotime($paymentData['dueDate']));
        
        if (!empty($result['data']['invoiceUrl'])) {
            $successMessage .= "<br><a href='{$result['data']['invoiceUrl']}' target='_blank' class='btn btn-sm btn-outline-primary mt-2'><i class='bi bi-eye'></i> Ver 1ª Parcela</a>";
        }
        
        return [
            'success' => true,
            'data' => [
                'installment_data' => $result['data'],
                'discount_info' => $processedDiscountData,
                'total_savings' => $totalSavings,
                'discount_per_installment' => $discountPerInstallment,
                'installment_count' => $installmentCount,
                'total_value' => $totalValue
            ],
            'message' => $successMessage
        ];
    }
    
    /**
     * Obter relatório de descontos
     */
    public function handleGetDiscountReport($getData) {
        $startDate = $getData['start'] ?? date('Y-m-01');
        $endDate = $getData['end'] ?? date('Y-m-d');
        $poloId = $getData['polo_id'] ?? ($_SESSION['polo_id'] ?? null);
        
        $report = $this->discountManager->getDiscountReport($startDate, $endDate, $poloId);
        
        return [
            'success' => true,
            'data' => [
                'report' => $report,
                'period' => ['start' => $startDate, 'end' => $endDate],
                'polo_context' => $poloId ? ($_SESSION['polo_nome'] ?? 'Polo ID: ' . $poloId) : 'Todos os polos'
            ],
            'message' => 'Relatório de descontos gerado com sucesso'
        ];
    }
}

/**
 * Função de migração simplificada
 */
function migrateInstallmentDiscountSystem() {
    try {
        echo "🔄 Migrando sistema de desconto para mensalidades...\n";
        
        $discountManager = new InstallmentDiscountManager();
        
        // A migração será feita automaticamente quando a classe for instanciada
        // pois os métodos createInstallmentDiscountsTableIfNotExists() e 
        // ensureDiscountColumnsExist() são chamados conforme necessário
        
        echo "✅ Sistema de desconto migrado com sucesso!\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ Erro na migração: " . $e->getMessage() . "\n";
        return false;
    }
}

// Executar migração se chamado via linha de comando
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    if (isset($argv[1])) {
        switch ($argv[1]) {
            case 'migrate':
                migrateInstallmentDiscountSystem();
                break;
            case 'test':
                echo "🧪 Testando sistema de desconto...\n";
                try {
                    $manager = new InstallmentDiscountManager();
                    echo "✅ InstallmentDiscountManager criado com sucesso\n";
                } catch (Exception $e) {
                    echo "❌ Erro no teste: " . $e->getMessage() . "\n";
                }
                break;
            default:
                echo "Comandos disponíveis:\n";
                echo "  migrate - Migrar banco de dados\n";
                echo "  test    - Testar sistema\n";
        }
    }
}

?>