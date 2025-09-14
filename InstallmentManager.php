<?php
/**
 * Gerenciador Completo de Mensalidades/Parcelamentos - ASAAS COM DESCONTO
 * Arquivo: InstallmentManager.php
 * Versão: 3.4 - Sistema completo de mensalidades parceladas + DESCONTO
 * 
 * Autor: Sistema IMEP Split ASAAS
 * Data: 2025
 */

require_once 'bootstrap.php';

/**
 * Classe principal para gerenciar mensalidades parceladas COM DESCONTO
 */
class InstallmentManager {
    
    private $db;
    private $asaas;
    private $auth;
    private $logFile;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->auth = new AuthSystem();
        $this->logFile = __DIR__ . '/logs/installment_' . date('Y-m-d') . '.log';
        
        // Criar diretório de logs se não existir
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
        
        $this->log("InstallmentManager inicializado com suporte a desconto");
    }
    
    /**
     * Log personalizado para mensalidades
     */
    private function log($message, $type = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $userId = $_SESSION['usuario_id'] ?? 'SYSTEM';
        $poloId = $_SESSION['polo_id'] ?? 'GLOBAL';
        
        $logMessage = "[{$timestamp}] [{$type}] [User:{$userId}] [Polo:{$poloId}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Log também no error_log do PHP para casos críticos
        if (in_array($type, ['ERROR', 'CRITICAL'])) {
            error_log("InstallmentManager [{$type}]: {$message}");
        }
    }
    
    /**
     * Inicializar conexão com ASAAS baseada no contexto atual
     */
    private function initAsaas() {
        if ($this->asaas === null) {
            try {
                // Usar configuração dinâmica baseada no usuário logado
                if ($this->auth->isMaster()) {
                    $this->asaas = AsaasConfig::getInstance();
                } else {
                    if (class_exists('DynamicAsaasConfig')) {
                        $dynamicConfig = new DynamicAsaasConfig();
                        $this->asaas = $dynamicConfig->getInstance();
                    } else {
                        $this->asaas = AsaasConfig::getInstance();
                    }
                }
                
                $this->log("Conexão ASAAS inicializada com sucesso");
            } catch (Exception $e) {
                $this->log("Erro ao inicializar ASAAS: " . $e->getMessage(), 'ERROR');
                throw new Exception('Erro na configuração ASAAS: ' . $e->getMessage());
            }
        }
        
        return $this->asaas;
    }
       
    /**
     * ===== MÉTODO PRINCIPAL: CRIAR MENSALIDADE COM DESCONTO =====
     * Criar nova mensalidade parcelada completa COM DESCONTO
     */

     public function createInstallment($paymentData, $splitsData = [], $installmentData = []) {
        try {
            // ===== HOTFIX: CAPTURAR DESCONTO DIRETO DO $_POST =====
            $this->log("=== HOTFIX: INÍCIO CAPTURA DESCONTO ===");
            
            // Capturar dados de desconto diretamente do $_POST antes de qualquer processamento
            $postDiscountEnabled = !empty($_POST['discount_enabled']) && $_POST['discount_enabled'] === '1';
            $postDiscountValue = floatval($_POST['discount_value'] ?? 0);
            
            $this->log("CAPTURA DIRETA DO \$_POST:");
            $this->log("- \$_POST['discount_enabled']: " . ($_POST['discount_enabled'] ?? 'AUSENTE'));
            $this->log("- \$_POST['discount_value']: " . ($_POST['discount_value'] ?? 'AUSENTE'));
            $this->log("- postDiscountEnabled (processado): " . ($postDiscountEnabled ? 'TRUE' : 'FALSE'));
            $this->log("- postDiscountValue (processado): {$postDiscountValue}");
            
            // Se detectou desconto no POST mas não está nos dados recebidos, forçar inclusão
            if ($postDiscountEnabled && $postDiscountValue > 0) {
                
                $this->log("🔧 DESCONTO DETECTADO NO POST - FORÇANDO INCLUSÃO NOS DADOS");
                
                // Forçar inclusão no paymentData se não existir
                if (!isset($paymentData['discount']) || empty($paymentData['discount']['value'])) {
                    $paymentData['discount'] = [
                        'value' => $postDiscountValue,
                        'dueDateLimitDays' => 0,
                        'type' => 'FIXED'
                    ];
                    $this->log("✅ paymentData['discount'] FORÇADO: " . json_encode($paymentData['discount']));
                }
                
                // Forçar inclusão no installmentData se não existir
                if (empty($installmentData['discount_value'])) {
                    $installmentData['discount_value'] = $postDiscountValue;
                    $installmentData['discount_type'] = 'FIXED';
                    $installmentData['discount_deadline_type'] = 'DUE_DATE';
                    $installmentData['discount_description'] = "Desconto de R$ " . number_format($postDiscountValue, 2, ',', '.') . " por parcela";
                    
                    $this->log("✅ installmentData FORÇADO com desconto:");
                    $this->log("- discount_value: {$installmentData['discount_value']}");
                    $this->log("- discount_type: {$installmentData['discount_type']}");
                    $this->log("- discount_deadline_type: {$installmentData['discount_deadline_type']}");
                }
                
                $this->log("🎯 DADOS CORRIGIDOS COM DESCONTO DO POST");
                
            } else {
                $this->log("ℹ️ Nenhum desconto detectado no POST ou valor inválido");
            }
            
            $this->log("=== HOTFIX: FIM CAPTURA DESCONTO ===");
            
            // ===== DEBUG INICIAL =====
            $this->log("================================================");
            $this->log("=== INSTALLMENT MANAGER DEBUG MÁXIMO - INÍCIO ===");
            $this->log("================================================");
            
            $this->log("1. DADOS RECEBIDOS NO INSTALLMENT MANAGER (APÓS HOTFIX):");
            $this->log("   paymentData: " . json_encode($paymentData, JSON_UNESCAPED_UNICODE));
            $this->log("   splitsData: " . json_encode($splitsData, JSON_UNESCAPED_UNICODE));
            $this->log("   installmentData: " . json_encode($installmentData, JSON_UNESCAPED_UNICODE));
            
            // ===== ANÁLISE DE DESCONTO NOS DADOS RECEBIDOS =====
            $this->log("2. ANÁLISE DE DESCONTO NOS DADOS RECEBIDOS:");
            
            // Verificar no paymentData
            if (isset($paymentData['discount'])) {
                $this->log("   ✅ paymentData['discount'] ENCONTRADO:");
                $this->log("      " . json_encode($paymentData['discount']));
            } else {
                $this->log("   ❌ paymentData['discount'] NÃO ENCONTRADO");
            }
            
            // Verificar no installmentData
            if (isset($installmentData['discount_value'])) {
                $this->log("   ✅ installmentData['discount_value'] ENCONTRADO: " . $installmentData['discount_value']);
            } else {
                $this->log("   ❌ installmentData['discount_value'] NÃO ENCONTRADO");
            }
            
            // Verificar campos relacionados
            $discountFields = ['discount_type', 'discount_deadline_type', 'discount_description'];
            foreach ($discountFields as $field) {
                if (isset($installmentData[$field])) {
                    $this->log("   ✅ installmentData['{$field}'] = " . $installmentData[$field]);
                } else {
                    $this->log("   ❌ installmentData['{$field}'] NÃO ENCONTRADO");
                }
            }
            
            // ===== VALIDAÇÕES BÁSICAS =====
            $this->log("3. EXECUTANDO VALIDAÇÕES BÁSICAS...");
            $this->validateInstallmentData($installmentData);
            $this->validatePaymentData($paymentData);
            
            if (!empty($splitsData)) {
                $this->validateSplitsData($splitsData, $installmentData['installmentValue']);
            }
            $this->log("   ✅ Validações básicas concluídas");
            
            // ===== INICIALIZAR ASAAS =====
            $this->log("4. INICIALIZANDO ASAAS...");
            $asaas = $this->initAsaas();
            $this->log("   ✅ ASAAS inicializado");
            
            // ===== DEBUG: DADOS ANTES DE ENVIAR PARA API =====
            $this->log("5. DADOS ANTES DE ENVIAR PARA API ASAAS:");
            $this->log("   paymentData: " . json_encode($paymentData));
            $this->log("   splitsData: " . json_encode($splitsData));
            $this->log("   installmentData: " . json_encode($installmentData));
            
            // ===== CRIAR PARCELAMENTO VIA API ASAAS =====
            $this->log("6. ENVIANDO PARA API ASAAS...");
            $apiResult = $asaas->createInstallmentPaymentWithSplit($paymentData, $splitsData, $installmentData);
            
            $this->log("7. RESPOSTA DA API ASAAS:");
            $this->log(json_encode($apiResult, JSON_UNESCAPED_UNICODE));
            
            if (!$apiResult || empty($apiResult['installment'])) {
                throw new Exception('Resposta inválida da API ASAAS');
            }
            
            $this->log("✅ Parcelamento criado na API ASAAS - ID: {$apiResult['installment']}");
            
            // ===== VERIFICAR DESCONTO NA RESPOSTA =====
            if (isset($apiResult['discount']) && $apiResult['discount']['value'] > 0) {
                $this->log("✅ DESCONTO APLICADO COM SUCESSO na API: R$ {$apiResult['discount']['value']}");
            } else {
                $this->log("❌ DESCONTO NÃO APLICADO - Resposta discount: " . json_encode($apiResult['discount'] ?? 'AUSENTE'));
            }
            
            // ===== PREPARAR DADOS PARA SALVAR NO BANCO - VERSÃO CORRIGIDA =====
            $this->log("8. PREPARANDO DADOS PARA SALVAR NO BANCO...");
            
            $installmentRecord = [
                'installment_id' => $apiResult['installment'],
                'polo_id' => $this->auth->getUsuarioAtual()['polo_id'] ?? null,
                'customer_id' => $apiResult['customer'],
                'installment_count' => $installmentData['installmentCount'],
                'installment_value' => $installmentData['installmentValue'],
                'total_value' => $installmentData['installmentCount'] * $installmentData['installmentValue'],
                'first_due_date' => $paymentData['dueDate'],
                'billing_type' => $paymentData['billingType'],
                'description' => $paymentData['description'],
                'has_splits' => !empty($splitsData),
                'splits_count' => count($splitsData),
                'created_by' => $this->auth->getUsuarioAtual()['id'] ?? null,
                'first_payment_id' => $apiResult['id'],
                'status' => 'ACTIVE'
            ];
            
            $this->log("=== DEBUG CRÍTICO: PREPARAÇÃO DOS DADOS DE DESCONTO ===");
            
            // ===== DETECTAR DESCONTO DE TODAS AS FONTES POSSÍVEIS =====
            $discountDetected = false;
            $discountValue = 0;
            $discountSource = '';
            
            $this->log("9. VERIFICANDO FONTES DE DESCONTO:");
            
            // FONTE 1: paymentData['discount']
            if (isset($paymentData['discount']) && 
                is_array($paymentData['discount']) && 
                !empty($paymentData['discount']['value']) && 
                $paymentData['discount']['value'] > 0) {
                
                $discountDetected = true;
                $discountValue = floatval($paymentData['discount']['value']);
                $discountSource = 'paymentData[discount]';
                $this->log("   ✅ FONTE 1 - paymentData[discount]: R$ {$discountValue}");
            }
            
            // FONTE 2: installmentData['discount_value']
            if (!$discountDetected && !empty($installmentData['discount_value']) && $installmentData['discount_value'] > 0) {
                $discountDetected = true;
                $discountValue = floatval($installmentData['discount_value']);
                $discountSource = 'installmentData[discount_value]';
                $this->log("   ✅ FONTE 2 - installmentData[discount_value]: R$ {$discountValue}");
            }
            
            // FONTE 3: $_POST direto (fallback)
            if (!$discountDetected && !empty($_POST['discount_enabled']) && 
                $_POST['discount_enabled'] === '1' && 
                !empty($_POST['discount_value']) && 
                floatval($_POST['discount_value']) > 0) {
                
                $discountDetected = true;
                $discountValue = floatval($_POST['discount_value']);
                $discountSource = 'POST direto';
                $this->log("   ✅ FONTE 3 - POST direto: R$ {$discountValue}");
            }
            
            // FONTE 4: Detectar por log anterior (último recurso)
            if (!$discountDetected && file_exists($this->logFile)) {
                $logContent = file_get_contents($this->logFile);
                if (preg_match('/Desconto configurado: R\$ ([\d,\.]+)/', $logContent, $matches)) {
                    $logDiscountValue = floatval(str_replace(',', '.', str_replace('.', '', $matches[1])));
                    if ($logDiscountValue > 0) {
                        $discountDetected = true;
                        $discountValue = $logDiscountValue;
                        $discountSource = 'log detection';
                        $this->log("   ✅ FONTE 4 - Log detection: R$ {$discountValue}");
                    }
                }
            }
            
            $this->log("10. RESULTADO DA DETECÇÃO:");
            $this->log("   - Detectado: " . ($discountDetected ? 'SIM' : 'NÃO'));
            $this->log("   - Valor: R$ {$discountValue}");
            $this->log("   - Fonte: {$discountSource}");
            
            // ===== APLICAR DESCONTO AO RECORD =====
            if ($discountDetected && $discountValue > 0) {
                $this->log("11. APLICANDO DESCONTO AO INSTALLMENT RECORD:");
                
                $installmentRecord['has_discount'] = 1;
                $installmentRecord['discount_value'] = $discountValue;
                $installmentRecord['discount_type'] = $installmentData['discount_type'] ?? 'FIXED';
                $installmentRecord['discount_deadline_type'] = $installmentData['discount_deadline_type'] ?? 'DUE_DATE';
                $installmentRecord['discount_description'] = $installmentData['discount_description'] ?? "Desconto de R$ " . number_format($discountValue, 2, ',', '.') . " por parcela";
                
                $this->log("   ✅ DESCONTO APLICADO:");
                $this->log("   - has_discount: " . $installmentRecord['has_discount']);
                $this->log("   - discount_value: " . $installmentRecord['discount_value']);
                $this->log("   - discount_type: " . $installmentRecord['discount_type']);
                $this->log("   - discount_deadline_type: " . $installmentRecord['discount_deadline_type']);
                $this->log("   - discount_description: " . $installmentRecord['discount_description']);
                
            } else {
                $this->log("11. NENHUM DESCONTO APLICADO AO RECORD");
                
                // Aplicar valores padrão explícitos
                $installmentRecord['has_discount'] = 0;
                $installmentRecord['discount_value'] = null;
                $installmentRecord['discount_type'] = null;
                $installmentRecord['discount_deadline_type'] = 'DUE_DATE';
                $installmentRecord['discount_description'] = null;
                
                $this->log("   - Valores padrão aplicados (sem desconto)");
            }
            
            $this->log("12. INSTALLMENT RECORD FINAL PARA SALVAR:");
            $this->log(json_encode($installmentRecord, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // ===== VALIDAÇÕES ANTES DE SALVAR =====
            if (empty($installmentRecord['installment_id'])) {
                throw new Exception('installment_id está vazio - não é possível salvar');
            }
            
            if (empty($installmentRecord['customer_id'])) {
                throw new Exception('customer_id está vazio - não é possível salvar');
            }
            
            $this->log("13. INICIANDO SALVAMENTO NO BANCO...");
            
            // Salvar registro principal da mensalidade
            $recordId = $this->db->saveInstallmentRecord($installmentRecord);
            
            if ($recordId) {
                $this->log("14. ✅ SALVO COM SUCESSO - ID LOCAL: {$recordId}");
                
                // ===== VERIFICAÇÃO CRÍTICA IMEDIATA =====
                $this->log("15. VERIFICAÇÃO CRÍTICA IMEDIATA:");
                
                $verificacao = $this->db->getInstallmentInfo($installmentRecord['installment_id']);
                if ($verificacao) {
                    $this->log("   DADOS VERIFICADOS NO BANCO:");
                    $this->log("   - has_discount: " . ($verificacao['has_discount'] ?? 'NULL'));
                    $this->log("   - discount_value: " . ($verificacao['discount_value'] ?? 'NULL'));
                    $this->log("   - discount_type: " . ($verificacao['discount_type'] ?? 'NULL'));
                    $this->log("   - discount_deadline_type: " . ($verificacao['discount_deadline_type'] ?? 'NULL'));
                    $this->log("   - discount_description: " . ($verificacao['discount_description'] ?? 'NULL'));
                    
                    // ===== SE DESCONTO ESPERADO MAS NÃO ESTÁ NO BANCO =====
                    if ($discountDetected && $discountValue > 0) {
                        if (empty($verificacao['has_discount']) || empty($verificacao['discount_value']) || 
                            $verificacao['discount_value'] != $discountValue) {
                            
                            $this->log("   🚨 PROBLEMA: Desconto esperado mas não está correto no banco!");
                            $this->log("   Esperado: has_discount=1, discount_value={$discountValue}");
                            $this->log("   Encontrado: has_discount=" . ($verificacao['has_discount'] ?? 'NULL') . 
                                      ", discount_value=" . ($verificacao['discount_value'] ?? 'NULL'));
                            
                            // ===== CORREÇÃO FORÇADA DIRETA =====
                            $this->log("   🔧 EXECUTANDO CORREÇÃO FORÇADA DIRETA...");
                            
                            $updateStmt = $this->db->getConnection()->prepare("
                                UPDATE installments 
                                SET has_discount = 1, 
                                    discount_value = ?, 
                                    discount_type = 'FIXED',
                                    discount_description = ?
                                WHERE installment_id = ?
                            ");
                            
                            $updateDescription = "Desconto de R$ " . number_format($discountValue, 2, ',', '.') . " por parcela - corrigido";
                            
                            if ($updateStmt->execute([$discountValue, $updateDescription, $installmentRecord['installment_id']])) {
                                $this->log("   ✅ CORREÇÃO FORÇADA EXECUTADA COM SUCESSO!");
                                
                                // Verificar novamente
                                $verificacao2 = $this->db->getInstallmentInfo($installmentRecord['installment_id']);
                                $this->log("   VERIFICAÇÃO APÓS CORREÇÃO:");
                                $this->log("   - has_discount: " . ($verificacao2['has_discount'] ?? 'NULL'));
                                $this->log("   - discount_value: " . ($verificacao2['discount_value'] ?? 'NULL'));
                                $this->log("   - discount_type: " . ($verificacao2['discount_type'] ?? 'NULL'));
                                
                                if ($verificacao2['has_discount'] == 1 && $verificacao2['discount_value'] == $discountValue) {
                                    $this->log("   🎉 CORREÇÃO BEM-SUCEDIDA! Desconto agora está correto.");
                                } else {
                                    $this->log("   ❌ CORREÇÃO FALHOU! Ainda não está correto.");
                                }
                                
                            } else {
                                $this->log("   ❌ ERRO NA CORREÇÃO FORÇADA!");
                                $errorInfo = $updateStmt->errorInfo();
                                $this->log("   Erro SQL: " . json_encode($errorInfo));
                            }
                            
                        } else {
                            $this->log("   ✅ DESCONTO SALVO CORRETAMENTE!");
                        }
                    } else {
                        $this->log("   ✅ SEM DESCONTO - COMPORTAMENTO CORRETO");
                    }
                    
                } else {
                    $this->log("   ❌ ERRO: Mensalidade não encontrada no banco após salvar!");
                    throw new Exception('Mensalidade não encontrada após salvamento');
                }
                
            } else {
                $this->log("   ❌ ERRO: Falha ao salvar installmentRecord!");
                throw new Exception('Falha ao salvar dados da mensalidade no banco');
            }
            
            // ===== CONTINUAR COM RESTO DO PROCESSAMENTO =====
            $this->log("16. SALVANDO DADOS ADICIONAIS...");
            
            // Salvar primeiro pagamento no banco
            $paymentSaveData = array_merge($apiResult, [
                'polo_id' => $installmentRecord['polo_id']
            ]);
            $this->db->savePayment($paymentSaveData);
            $this->log("   ✅ Primeiro pagamento salvo");
            
            // Salvar splits se houver
            if (!empty($splitsData)) {
                $this->db->savePaymentSplits($apiResult['id'], $splitsData);
                $this->log("   ✅ Splits salvos para o pagamento: " . count($splitsData) . " destinatários");
            }
            
            // Buscar e salvar todas as parcelas criadas
            $this->log("17. SINCRONIZANDO PARCELAS...");
            $this->syncInstallmentPayments($apiResult['installment']);
            $this->log("   ✅ Parcelas sincronizadas");
            
            // ===== PREPARAR RESPOSTA COMPLETA =====
            $response = [
                'success' => true,
                'installment_id' => $apiResult['installment'],
                'first_payment_id' => $apiResult['id'],
                'installment_record' => $installmentRecord,
                'api_response' => $apiResult,
                'local_record_id' => $recordId,
                'summary' => [
                    'total_installments' => $installmentData['installmentCount'],
                    'installment_value' => $installmentData['installmentValue'],
                    'total_value' => $installmentRecord['total_value'],
                    'first_due_date' => $paymentData['dueDate'],
                    'billing_type' => $paymentData['billingType'],
                    'splits_configured' => count($splitsData),
                    'has_discount' => $discountDetected,
                    'discount_value' => $discountValue,
                    'discount_per_installment' => $discountValue,
                    'total_discount_potential' => $discountDetected ? $discountValue * $installmentData['installmentCount'] : 0,
                    'discount_deadline' => 'Até o dia do vencimento de cada parcela',
                    'discount_source' => $discountSource
                ]
            ];
            
            // Adicionar informações de desconto ao summary da resposta se detectado
            if ($discountDetected && $discountValue > 0) {
                $response['summary']['discount_info'] = [
                    'enabled' => true,
                    'value_per_installment' => $discountValue,
                    'total_savings' => $discountValue * $installmentData['installmentCount'],
                    'percentage_per_installment' => round(($discountValue / $installmentData['installmentValue']) * 100, 2),
                    'description' => "Desconto de R$ " . number_format($discountValue, 2, ',', '.') . " por parcela",
                    'source' => $discountSource
                ];
            }
            
            $this->log("================================================");
            $this->log("=== INSTALLMENT MANAGER DEBUG MÁXIMO - FIM ===");
            $this->log("================================================");
            $this->log("18. MENSALIDADE CRIADA COM SUCESSO!");
            $this->log("   - ID: " . $response['installment_id']);
            $this->log("   - Parcelas: " . $response['summary']['total_installments']);
            $this->log("   - Valor total: R$ " . number_format($response['summary']['total_value'], 2, ',', '.'));
            $this->log("   - Desconto: " . ($discountDetected ? "R$ {$discountValue} por parcela" : 'Sem desconto'));
            $this->log("   - Economia total: R$ " . number_format($response['summary']['total_discount_potential'], 2, ',', '.'));
            
            return $response;
            
        } catch (Exception $e) {
            $this->log("❌ ERRO no InstallmentManager: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }



        /**
     * ===== NOVA FUNÇÃO: VALIDAR E PREPARAR DESCONTO =====
     * Validar e preparar dados do desconto
     */
    private function validateAndPrepareDiscount($installmentData) {
        $discountValue = floatval($installmentData['discount_value'] ?? 0);
        $installmentValue = floatval($installmentData['installmentValue'] ?? 0);
        
        // Validações do desconto
        if ($discountValue <= 0) {
            throw new Exception('Valor do desconto deve ser maior que zero');
        }
        
        if ($discountValue >= $installmentValue) {
            throw new Exception('Valor do desconto não pode ser maior ou igual ao valor da parcela');
        }
        
        // Validar se desconto não é muito alto (máximo 50% da parcela)
        $maxDiscount = $installmentValue * 0.50;
        if ($discountValue > $maxDiscount) {
            throw new Exception("Desconto muito alto. Máximo permitido: R$ " . number_format($maxDiscount, 2, ',', '.') . " (50% da parcela)");
        }
        
        return [
            'value' => $discountValue,
            'dueDateLimitDays' => 0,
            'type' => 'FIXED'
        ];
    }

    /**
     * Sincronizar todas as parcelas de um parcelamento com o banco local
     */
    public function syncInstallmentPayments($installmentId) {
        try {
            $this->log("Sincronizando parcelas do parcelamento: {$installmentId}");
            
            $asaas = $this->initAsaas();
            $paymentsResponse = $asaas->getInstallmentPayments($installmentId);
            
            if (!$paymentsResponse || empty($paymentsResponse['data'])) {
                $this->log("Nenhuma parcela encontrada para sincronizar", 'WARNING');
                return false;
            }
            
            $syncedCount = 0;
            foreach ($paymentsResponse['data'] as $index => $payment) {
                try {
                    // Determinar número da parcela
                    $installmentNumber = $index + 1;
                    
                    // Preparar dados da parcela
                    $paymentData = [
                        'id' => $payment['id'],
                        'installment_number' => $installmentNumber,
                        'dueDate' => $payment['dueDate'],
                        'value' => $payment['value'],
                        'status' => $payment['status']
                    ];
                    
                    // Salvar parcela individual
                    if ($this->db->saveInstallmentPayment($installmentId, $paymentData)) {
                        $syncedCount++;
                    }
                    
                } catch (Exception $e) {
                    $this->log("Erro ao sincronizar parcela {$installmentNumber}: " . $e->getMessage(), 'WARNING');
                }
            }
            
            $this->log("Sincronização concluída - {$syncedCount} parcelas sincronizadas");
            return $syncedCount;
            
        } catch (Exception $e) {
            $this->log("Erro na sincronização de parcelas: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Obter informações completas de uma mensalidade COM DESCONTO
     */
    public function getInstallmentDetails($installmentId) {
        try {
            $this->log("Buscando detalhes da mensalidade com desconto: {$installmentId}");
            
            // Buscar informações básicas no banco local
            $installmentInfo = $this->db->getInstallmentInfo($installmentId);
            
            if (!$installmentInfo) {
                throw new Exception('Mensalidade não encontrada no banco local');
            }
            
            // Buscar parcelas da API ASAAS
            $asaas = $this->initAsaas();
            $paymentsResponse = $asaas->getInstallmentPayments($installmentId);
            
            // Buscar parcelas do banco local
            $localPayments = $this->getLocalInstallmentPayments($installmentId);
            
            // Calcular estatísticas COM DESCONTO
            $stats = $this->calculateInstallmentStatsWithDiscount($installmentInfo, $paymentsResponse['data'] ?? []);
            
            $details = [
                'installment_info' => $installmentInfo,
                'payments_from_api' => $paymentsResponse['data'] ?? [],
                'local_payments' => $localPayments,
                'statistics' => $stats,
                'summary' => [
                    'installment_id' => $installmentId,
                    'customer_name' => $installmentInfo['customer_name'],
                    'total_payments' => count($paymentsResponse['data'] ?? []),
                    'payments_received' => $stats['payments_received'],
                    'amount_received' => $stats['total_received'],
                    'amount_pending' => $stats['amount_pending'],
                    'completion_percentage' => $stats['completion_percentage'],
                    'next_due_date' => $stats['next_due_date'],
                    'status' => $installmentInfo['status'],
                    
                    // ===== INFORMAÇÕES DE DESCONTO =====
                    'has_discount' => !empty($installmentInfo['has_discount']),
                    'discount_value' => $installmentInfo['discount_value'] ?? 0,
                    'discount_description' => $installmentInfo['discount_description'] ?? '',
                    'total_discount_applied' => $stats['total_discount_applied'] ?? 0,
                    'total_discount_potential' => $stats['total_discount_potential'] ?? 0
                ]
            ];
            
            $this->log("Detalhes da mensalidade obtidos com desconto - {$stats['payments_received']}/{$installmentInfo['installment_count']} parcelas pagas");
            
            return $details;
            
        } catch (Exception $e) {
            $this->log("Erro ao obter detalhes da mensalidade: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
        /**
     * ===== NOVA FUNÇÃO: CALCULAR ESTATÍSTICAS COM DESCONTO =====
     * Calcular estatísticas de uma mensalidade COM INFORMAÇÕES DE DESCONTO
     */
    private function calculateInstallmentStatsWithDiscount($installmentInfo, $apiPayments) {
        $paymentsReceived = 0;
        $totalReceived = 0;
        $nextDueDate = null;
        $totalDiscountApplied = 0;
        
        foreach ($apiPayments as $payment) {
            if ($payment['status'] === 'RECEIVED') {
                $paymentsReceived++;
                $totalReceived += $payment['value'];
                
                // Verificar se houve desconto aplicado
                if (isset($payment['discount']) && $payment['discount']['value'] > 0) {
                    $totalDiscountApplied += $payment['discount']['value'];
                }
            } elseif (empty($nextDueDate) && $payment['status'] === 'PENDING') {
                $nextDueDate = $payment['dueDate'];
            }
        }
        
        $totalExpected = $installmentInfo['total_value'];
        $amountPending = $totalExpected - $totalReceived;
        $completionPercentage = $totalExpected > 0 ? ($totalReceived / $totalExpected) * 100 : 0;
        
        // Calcular potencial total de desconto
        $discountValue = floatval($installmentInfo['discount_value'] ?? 0);
        $totalDiscountPotential = $discountValue * $installmentInfo['installment_count'];
        
        return [
            'payments_received' => $paymentsReceived,
            'total_payments' => count($apiPayments),
            'total_received' => $totalReceived,
            'amount_pending' => $amountPending,
            'completion_percentage' => round($completionPercentage, 2),
            'next_due_date' => $nextDueDate,
            'is_completed' => $completionPercentage >= 100,
            'is_overdue' => $this->hasOverduePayments($apiPayments),
            
            // ===== ESTATÍSTICAS DE DESCONTO =====
            'total_discount_applied' => $totalDiscountApplied,
            'total_discount_potential' => $totalDiscountPotential,
            'discount_per_installment' => $discountValue,
            'has_discount' => $discountValue > 0,
            'discount_utilization_rate' => $totalDiscountPotential > 0 ? ($totalDiscountApplied / $totalDiscountPotential) * 100 : 0
        ];
    }
    
    
    /**
     * Gerar carnê em PDF para uma mensalidade
     */
    public function generatePaymentBook($installmentId, $options = []) {
        try {
            $this->log("Gerando carnê PDF para mensalidade: {$installmentId}");
            
            // Verificar se mensalidade existe
            $installmentInfo = $this->db->getInstallmentInfo($installmentId);
            if (!$installmentInfo) {
                throw new Exception('Mensalidade não encontrada');
            }
            
            // Verificar permissões
            if (!$this->auth->temPermissao('can_generate_payment_books')) {
                throw new Exception('Você não tem permissão para gerar carnês');
            }
            
            // Gerar carnê via API ASAAS
            $asaas = $this->initAsaas();
            $paymentBook = $asaas->generateInstallmentPaymentBook($installmentId);
            
            if (!$paymentBook['success']) {
                throw new Exception('Erro ao gerar carnê na API ASAAS');
            }
            
            // Preparar informações do arquivo
            $fileName = $this->generatePaymentBookFileName($installmentInfo, $options);
            $filePath = __DIR__ . '/temp/' . $fileName;
            
            // Criar diretório temp se não existir
            if (!is_dir(__DIR__ . '/temp')) {
                mkdir(__DIR__ . '/temp', 0755, true);
            }
            
            // Salvar PDF
            $bytesWritten = file_put_contents($filePath, $paymentBook['pdf_content']);
            
            if ($bytesWritten === false) {
                throw new Exception('Erro ao salvar arquivo PDF');
            }
            
            // Registrar geração do carnê
            $this->logPaymentBookGeneration($installmentId, $fileName, $bytesWritten);
            
            $result = [
                'success' => true,
                'file_name' => $fileName,
                'file_path' => 'temp/' . $fileName,
                'download_url' => 'download.php?file=' . urlencode($fileName),
                'size_bytes' => $bytesWritten,
                'size_formatted' => $this->formatBytes($bytesWritten),
                'generated_at' => date('Y-m-d H:i:s'),
                'installment_info' => [
                    'customer_name' => $installmentInfo['customer_name'],
                    'installment_count' => $installmentInfo['installment_count'],
                    'total_value' => $installmentInfo['total_value']
                ]
            ];
            
            $this->log("Carnê gerado com sucesso - Arquivo: {$fileName} ({$this->formatBytes($bytesWritten)})");
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("Erro ao gerar carnê: " . $e->getMessage(), 'ERROR');
            throw new Exception('Erro ao gerar carnê: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualizar status de uma mensalidade
     */
    public function updateInstallmentStatus($installmentId, $newStatus) {
        try {
            $this->log("Atualizando status da mensalidade {$installmentId} para {$newStatus}");
            
            // Validar status
            $validStatuses = ['ACTIVE', 'COMPLETED', 'CANCELLED', 'SUSPENDED'];
            if (!in_array($newStatus, $validStatuses)) {
                throw new Exception('Status inválido: ' . $newStatus);
            }
            
            // Verificar permissões
            if (!$this->auth->temPermissao('can_manage_installments')) {
                throw new Exception('Você não tem permissão para alterar status de mensalidades');
            }
            
            // Atualizar no banco
            $stmt = $this->db->getConnection()->prepare("
                UPDATE installments 
                SET status = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE installment_id = ? AND (polo_id = ? OR ? IS NULL)
            ");
            
            $poloId = $this->auth->getUsuarioAtual()['polo_id'];
            $isMaster = $this->auth->isMaster();
            
            $result = $stmt->execute([
                $newStatus,
                $installmentId,
                $isMaster ? null : $poloId,
                $isMaster ? 1 : 0
            ]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->log("Status atualizado com sucesso");
                
                // Log de auditoria
                $this->logStatusChange($installmentId, $newStatus);
                
                return [
                    'success' => true,
                    'installment_id' => $installmentId,
                    'new_status' => $newStatus,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            } else {
                throw new Exception('Mensalidade não encontrada ou sem permissão');
            }
            
        } catch (Exception $e) {
            $this->log("Erro ao atualizar status: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    // ====================================================
    // MÉTODOS DE RELATÓRIOS
    // ====================================================
    
    /**
     * Gerar relatório completo de mensalidades COM DESCONTO
     */
    public function generateInstallmentReport($startDate, $endDate, $options = []) {
        try {
            $this->log("Gerando relatório de mensalidades com desconto - Período: {$startDate} a {$endDate}");
            
            $poloId = $this->auth->isMaster() ? ($options['polo_id'] ?? null) : $this->auth->getUsuarioAtual()['polo_id'];
            
            // Buscar dados do banco COM DESCONTO
            $installments = $this->db->getInstallmentReportWithDiscount($startDate, $endDate, $poloId);
            $stats = $this->db->getInstallmentStatsWithDiscount($poloId);
            
            // Buscar dados adicionais da API se solicitado
            if ($options['include_api_data'] ?? false) {
                $installments = $this->enrichInstallmentsWithApiData($installments);
            }
            
            // Calcular métricas do relatório COM DESCONTO
            $metrics = $this->calculateReportMetricsWithDiscount($installments, $stats);
            
            // Agrupar dados por diferentes critérios
            $groupings = [
                'by_month' => $this->groupInstallmentsByMonth($installments),
                'by_customer' => $this->groupInstallmentsByCustomer($installments),
                'by_billing_type' => $this->groupInstallmentsByBillingType($installments),
                'by_status' => $this->groupInstallmentsByStatus($installments),
                'by_discount' => $this->groupInstallmentsByDiscount($installments) // NOVO
            ];
            
            $report = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => (strtotime($endDate) - strtotime($startDate)) / 86400 + 1
                ],
                'context' => [
                    'polo_id' => $poloId,
                    'polo_name' => $this->auth->getUsuarioAtual()['polo_nome'] ?? 'Todos os polos',
                    'user_type' => $this->auth->getUsuarioAtual()['tipo'],
                    'generated_by' => $this->auth->getUsuarioAtual()['nome'],
                    'generated_at' => date('Y-m-d H:i:s')
                ],
                'summary' => $metrics,
                'installments' => $installments,
                'groupings' => $groupings,
                'statistics' => $stats
            ];
            
            $this->log("Relatório gerado com desconto - {$metrics['total_installments']} mensalidades, " .
                      "Desconto potencial: R$ " . number_format($metrics['total_discount_potential'], 2, ',', '.'));
            
            return $report;
            
        } catch (Exception $e) {
            $this->log("Erro ao gerar relatório: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

        /**
     * ===== NOVA FUNÇÃO: CALCULAR MÉTRICAS COM DESCONTO =====
     * Calcular métricas para relatório COM DESCONTO
     */
    private function calculateReportMetricsWithDiscount($installments, $stats) {
        $totalInstallments = count($installments);
        $totalValue = array_sum(array_column($installments, 'total_value'));
        $totalReceived = array_sum(array_column($installments, 'amount_received'));
        $totalPending = $totalValue - $totalReceived;
        
        // ===== CÁLCULOS DE DESCONTO =====
        $totalDiscountPotential = 0;
        $installmentsWithDiscount = 0;
        $totalDiscountValue = 0;
        
        foreach ($installments as $installment) {
            if (!empty($installment['has_discount']) && $installment['discount_value'] > 0) {
                $installmentsWithDiscount++;
                $discountPerInstallment = floatval($installment['discount_value']);
                $installmentCount = intval($installment['installment_count']);
                $totalDiscountValue += $discountPerInstallment * $installmentCount;
                $totalDiscountPotential += $discountPerInstallment * $installmentCount;
            }
        }
        
        return [
            'total_installments' => $totalInstallments,
            'total_value' => $totalValue,
            'total_received' => $totalReceived,
            'total_pending' => $totalPending,
            'avg_installment_value' => $totalInstallments > 0 ? $totalValue / $totalInstallments : 0,
            'avg_completion_rate' => $totalInstallments > 0 ? 
                array_sum(array_column($installments, 'completion_percentage')) / $totalInstallments : 0,
            'active_installments' => count(array_filter($installments, function($i) { 
                return $i['status'] === 'ACTIVE'; 
            })),
            'completed_installments' => count(array_filter($installments, function($i) { 
                return $i['status'] === 'COMPLETED'; 
            })),
            'with_splits' => count(array_filter($installments, function($i) { 
                return $i['has_splits']; 
            })),
            'collection_rate' => $totalValue > 0 ? ($totalReceived / $totalValue) * 100 : 0,
            
            // ===== MÉTRICAS DE DESCONTO =====
            'installments_with_discount' => $installmentsWithDiscount,
            'total_discount_potential' => $totalDiscountPotential,
            'avg_discount_per_installment' => $installmentsWithDiscount > 0 ? ($totalDiscountValue / $installmentsWithDiscount) : 0,
            'discount_adoption_rate' => $totalInstallments > 0 ? ($installmentsWithDiscount / $totalInstallments) * 100 : 0
        ];
    }
    
        /**
     * ===== NOVA FUNÇÃO: AGRUPAR POR DESCONTO =====
     * Agrupar mensalidades por uso de desconto
     */
    private function groupInstallmentsByDiscount($installments) {
        $grouped = [
            'with_discount' => [
                'label' => 'Com Desconto',
                'count' => 0,
                'total_value' => 0,
                'total_discount' => 0
            ],
            'without_discount' => [
                'label' => 'Sem Desconto',
                'count' => 0,
                'total_value' => 0,
                'total_discount' => 0
            ]
        ];
        
        foreach ($installments as $installment) {
            $hasDiscount = !empty($installment['has_discount']) && $installment['discount_value'] > 0;
            $group = $hasDiscount ? 'with_discount' : 'without_discount';
            
            $grouped[$group]['count']++;
            $grouped[$group]['total_value'] += $installment['total_value'];
            
            if ($hasDiscount) {
                $grouped[$group]['total_discount'] += ($installment['discount_value'] * $installment['installment_count']);
            }
        }
        
        return array_values($grouped);
    }
    

    
    /**
     * Relatório de performance por cliente
     */
    public function generateCustomerPerformanceReport($startDate, $endDate) {
        try {
            $this->log("Gerando relatório de performance por cliente");
            
            $poloId = $this->auth->isMaster() ? null : $this->auth->getUsuarioAtual()['polo_id'];
            
            $customers = $this->db->getCustomerInstallmentPerformance($startDate, $endDate, $poloId);
            
            // Adicionar rankings e métricas
            foreach ($customers as &$customer) {
                $customer['payment_rate'] = $customer['total_payments_expected'] > 0 ? 
                    ($customer['total_payments_made'] / $customer['total_payments_expected']) * 100 : 0;
                    
                $customer['avg_delay'] = $this->calculateAvgPaymentDelay($customer['customer_email']);
                
                $customer['risk_level'] = $this->calculateCustomerRiskLevel($customer);
                
                $customer['formatted'] = [
                    'total_value_expected' => 'R$ ' . number_format($customer['total_value_expected'], 2, ',', '.'),
                    'total_amount_received' => 'R$ ' . number_format($customer['total_amount_received'], 2, ',', '.'),
                    'payment_rate' => number_format($customer['payment_rate'], 1) . '%',
                    'avg_installment_value' => 'R$ ' . number_format($customer['avg_installment_value'], 2, ',', '.')
                ];
            }
            
            // Ordenar por performance
            usort($customers, function($a, $b) {
                return $b['payment_rate'] <=> $a['payment_rate'];
            });
            
            $report = [
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'total_customers' => count($customers),
                'customers' => $customers,
                'top_performers' => array_slice($customers, 0, 10),
                'at_risk_customers' => array_filter($customers, function($c) { 
                    return $c['risk_level'] === 'HIGH'; 
                }),
                'summary' => [
                    'avg_payment_rate' => array_sum(array_column($customers, 'payment_rate')) / count($customers),
                    'total_expected' => array_sum(array_column($customers, 'total_value_expected')),
                    'total_received' => array_sum(array_column($customers, 'total_amount_received'))
                ]
            ];
            
            $this->log("Relatório de performance gerado - {$report['total_customers']} clientes analisados");
            
            return $report;
            
        } catch (Exception $e) {
            $this->log("Erro no relatório de performance: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    // ====================================================
    // MÉTODOS DE WEBHOOK E SINCRONIZAÇÃO
    // ====================================================
    
    /**
     * Processar webhook relacionado a mensalidades
     */
    public function processInstallmentWebhook($webhookData) {
        try {
            $this->log("Processando webhook de mensalidade - Evento: {$webhookData['event']}");
            
            $event = $webhookData['event'];
            $payment = $webhookData['payment'] ?? [];
            
            if (empty($payment['id'])) {
                throw new Exception('ID do pagamento não encontrado no webhook');
            }
            
            // Verificar se o pagamento pertence a alguma mensalidade
            $installmentId = $this->findInstallmentByPayment($payment['id']);
            
            if (!$installmentId) {
                $this->log("Pagamento {$payment['id']} não pertence a nenhuma mensalidade cadastrada", 'INFO');
                return ['status' => 'ignored', 'reason' => 'payment_not_installment'];
            }
            
            $this->log("Webhook relacionado à mensalidade {$installmentId}");
            
            // Processar evento específico
            switch ($event) {
                case 'PAYMENT_RECEIVED':
                    return $this->handleInstallmentPaymentReceived($installmentId, $payment);
                    
                case 'PAYMENT_OVERDUE':
                    return $this->handleInstallmentPaymentOverdue($installmentId, $payment);
                    
                case 'PAYMENT_DELETED':
                    return $this->handleInstallmentPaymentDeleted($installmentId, $payment);
                    
                case 'PAYMENT_RESTORED':
                    return $this->handleInstallmentPaymentRestored($installmentId, $payment);
                    
                default:
                    $this->log("Evento de webhook não tratado: {$event}", 'WARNING');
                    return ['status' => 'ignored', 'reason' => 'event_not_handled'];
            }
            
        } catch (Exception $e) {
            $this->log("Erro ao processar webhook de mensalidade: " . $e->getMessage(), 'ERROR');
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Tratar pagamento de parcela recebido
     */
    private function handleInstallmentPaymentReceived($installmentId, $payment) {
        try {
            $this->log("Processando pagamento recebido - Parcela da mensalidade {$installmentId}");
            
            // Atualizar status da parcela no banco
            $this->db->updateInstallmentPaymentStatus($payment['id'], 'RECEIVED');
            
            // Verificar se a mensalidade foi totalmente paga
            $installmentDetails = $this->getInstallmentDetails($installmentId);
            $completionRate = $installmentDetails['statistics']['completion_percentage'];
            
            if ($completionRate >= 100) {
                // Mensalidade completamente paga
                $this->updateInstallmentStatus($installmentId, 'COMPLETED');
                $this->log("Mensalidade {$installmentId} marcada como COMPLETED");
            }
            
            // Registrar evento
            $this->logInstallmentEvent($installmentId, 'PAYMENT_RECEIVED', [
                'payment_id' => $payment['id'],
                'value' => $payment['value'] ?? 0,
                'completion_rate' => $completionRate
            ]);
            
            return [
                'status' => 'processed',
                'action' => 'installment_payment_received',
                'installment_id' => $installmentId,
                'completion_rate' => $completionRate
            ];
            
        } catch (Exception $e) {
            $this->log("Erro ao processar pagamento recebido: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Tratar parcela vencida
     */
    private function handleInstallmentPaymentOverdue($installmentId, $payment) {
        try {
            $this->log("Processando parcela vencida - Mensalidade {$installmentId}");
            
            // Atualizar status da parcela
            $this->db->updateInstallmentPaymentStatus($payment['id'], 'OVERDUE');
            
            // Verificar quantas parcelas estão vencidas
            $overdueCount = $this->countOverduePayments($installmentId);
            
            // Se muitas parcelas vencidas, considerar suspender mensalidade
            if ($overdueCount >= 3) {
                $this->log("Mensalidade {$installmentId} com {$overdueCount} parcelas vencidas - Considerando suspensão");
                // Pode implementar lógica automática ou notificação aqui
            }
            
            $this->logInstallmentEvent($installmentId, 'PAYMENT_OVERDUE', [
                'payment_id' => $payment['id'],
                'overdue_count' => $overdueCount
            ]);
            
            return [
                'status' => 'processed',
                'action' => 'installment_payment_overdue',
                'installment_id' => $installmentId,
                'overdue_count' => $overdueCount
            ];
            
        } catch (Exception $e) {
            $this->log("Erro ao processar parcela vencida: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    // ====================================================
    // MÉTODOS AUXILIARES E UTILITÁRIOS
    // ====================================================
    
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
     * Validar dados do pagamento
     */
    private function validatePaymentData($paymentData) {
        $required = ['customer', 'billingType', 'description', 'dueDate'];
        
        foreach ($required as $field) {
            if (empty($paymentData[$field])) {
                throw new Exception("Campo '{$field}' é obrigatório");
            }
        }
        
        // Validar data de vencimento
        if (strtotime($paymentData['dueDate']) < strtotime('today')) {
            throw new Exception('Data de vencimento não pode ser anterior a hoje');
        }
        
        // Validar se não é muito distante (máximo 1 ano)
        if (strtotime($paymentData['dueDate']) > strtotime('+1 year')) {
            throw new Exception('Data de vencimento muito distante (máximo 1 ano)');
        }
        
        return true;
    }
    
    /**
     * Validar dados de splits
     */
    private function validateSplitsData($splitsData, $installmentValue) {
        $totalPercentage = 0;
        $totalFixedValue = 0;
        
        foreach ($splitsData as $split) {
            if (empty($split['walletId'])) {
                continue; // Split vazio, ignorar
            }
            
            if (!empty($split['percentualValue'])) {
                $percentage = floatval($split['percentualValue']);
                if ($percentage <= 0 || $percentage > 100) {
                    throw new Exception('Percentual de split deve ser entre 0.01% e 100%');
                }
                $totalPercentage += $percentage;
            }
            
            if (!empty($split['fixedValue'])) {
                $fixedValue = floatval($split['fixedValue']);
                if ($fixedValue <= 0) {
                    throw new Exception('Valor fixo de split deve ser maior que zero');
                }
                if ($fixedValue >= $installmentValue) {
                    throw new Exception('Valor fixo não pode ser maior ou igual ao valor da parcela');
                }
                $totalFixedValue += $fixedValue;
            }
        }
        
        if ($totalPercentage > 100) {
            throw new Exception('A soma dos percentuais não pode exceder 100%');
        }
        
        if ($totalFixedValue >= $installmentValue) {
            throw new Exception('A soma dos valores fixos não pode ser maior ou igual ao valor da parcela');
        }
        
        return true;
    }
    
    /**
     * Buscar parcelas locais de uma mensalidade
     */
    private function getLocalInstallmentPayments($installmentId) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT * FROM installment_payments 
                WHERE installment_id = ? 
                ORDER BY installment_number
            ");
            
            $stmt->execute([$installmentId]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $this->log("Erro ao buscar parcelas locais: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Calcular estatísticas de uma mensalidade
     */
    private function calculateInstallmentStats($installmentInfo, $apiPayments) {
        $paymentsReceived = 0;
        $totalReceived = 0;
        $nextDueDate = null;
        
        foreach ($apiPayments as $payment) {
            if ($payment['status'] === 'RECEIVED') {
                $paymentsReceived++;
                $totalReceived += $payment['value'];
            } elseif (empty($nextDueDate) && $payment['status'] === 'PENDING') {
                $nextDueDate = $payment['dueDate'];
            }
        }
        
        $totalExpected = $installmentInfo['total_value'];
        $amountPending = $totalExpected - $totalReceived;
        $completionPercentage = $totalExpected > 0 ? ($totalReceived / $totalExpected) * 100 : 0;
        
        return [
            'payments_received' => $paymentsReceived,
            'total_payments' => count($apiPayments),
            'total_received' => $totalReceived,
            'amount_pending' => $amountPending,
            'completion_percentage' => round($completionPercentage, 2),
            'next_due_date' => $nextDueDate,
            'is_completed' => $completionPercentage >= 100,
            'is_overdue' => $this->hasOverduePayments($apiPayments)
        ];
    }
    
    /**
     * Verificar se há parcelas vencidas
     */
    private function hasOverduePayments($payments) {
        $today = date('Y-m-d');
        
        foreach ($payments as $payment) {
            if ($payment['status'] !== 'RECEIVED' && $payment['dueDate'] < $today) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Contar parcelas vencidas de uma mensalidade
     */
    private function countOverduePayments($installmentId) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT COUNT(*) as count FROM installment_payments 
                WHERE installment_id = ? 
                AND status NOT IN ('RECEIVED', 'DELETED') 
                AND due_date < CURDATE()
            ");
            
            $stmt->execute([$installmentId]);
            $result = $stmt->fetch();
            
            return (int)($result['count'] ?? 0);
            
        } catch (PDOException $e) {
            $this->log("Erro ao contar parcelas vencidas: " . $e->getMessage(), 'ERROR');
            return 0;
        }
    }
    
    /**
     * Encontrar mensalidade por ID de pagamento
     */
    private function findInstallmentByPayment($paymentId) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT installment_id FROM payments 
                WHERE id = ? AND installment_id IS NOT NULL
            ");
            
            $stmt->execute([$paymentId]);
            $result = $stmt->fetch();
            
            return $result ? $result['installment_id'] : null;
            
        } catch (PDOException $e) {
            $this->log("Erro ao buscar mensalidade por pagamento: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * Gerar nome de arquivo para carnê
     */
    private function generatePaymentBookFileName($installmentInfo, $options = []) {
        $customerName = preg_replace('/[^a-zA-Z0-9]/', '_', $installmentInfo['customer_name']);
        $customerName = substr($customerName, 0, 20); // Limitar tamanho
        
        $date = date('Y-m-d');
        $time = date('His');
        $installmentId = substr($installmentInfo['installment_id'], -8);
        
        return "carne_{$customerName}_{$installmentId}_{$date}_{$time}.pdf";
    }
    
    /**
     * Formatar bytes para exibição
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Calcular métricas para relatório
     */
    private function calculateReportMetrics($installments, $stats) {
        $totalInstallments = count($installments);
        $totalValue = array_sum(array_column($installments, 'total_value'));
        $totalReceived = array_sum(array_column($installments, 'amount_received'));
        $totalPending = $totalValue - $totalReceived;
        
        return [
            'total_installments' => $totalInstallments,
            'total_value' => $totalValue,
            'total_received' => $totalReceived,
            'total_pending' => $totalPending,
            'avg_installment_value' => $totalInstallments > 0 ? $totalValue / $totalInstallments : 0,
            'avg_completion_rate' => $totalInstallments > 0 ? 
                array_sum(array_column($installments, 'completion_percentage')) / $totalInstallments : 0,
            'active_installments' => count(array_filter($installments, function($i) { 
                return $i['status'] === 'ACTIVE'; 
            })),
            'completed_installments' => count(array_filter($installments, function($i) { 
                return $i['status'] === 'COMPLETED'; 
            })),
            'with_splits' => count(array_filter($installments, function($i) { 
                return $i['has_splits']; 
            })),
            'collection_rate' => $totalValue > 0 ? ($totalReceived / $totalValue) * 100 : 0
        ];
    }
    
    /**
     * Agrupar mensalidades por mês
     */
    private function groupInstallmentsByMonth($installments) {
        $grouped = [];
        
        foreach ($installments as $installment) {
            $month = date('Y-m', strtotime($installment['created_at']));
            
            if (!isset($grouped[$month])) {
                $grouped[$month] = [
                    'month' => $month,
                    'month_formatted' => date('m/Y', strtotime($installment['created_at'])),
                    'count' => 0,
                    'total_value' => 0,
                    'total_received' => 0
                ];
            }
            
            $grouped[$month]['count']++;
            $grouped[$month]['total_value'] += $installment['total_value'];
            $grouped[$month]['total_received'] += $installment['amount_received'] ?? 0;
        }
        
        return array_values($grouped);
    }
    
    /**
     * Agrupar mensalidades por cliente
     */
    private function groupInstallmentsByCustomer($installments) {
        $grouped = [];
        
        foreach ($installments as $installment) {
            $customerId = $installment['customer_id'];
            
            if (!isset($grouped[$customerId])) {
                $grouped[$customerId] = [
                    'customer_id' => $customerId,
                    'customer_name' => $installment['customer_name'],
                    'count' => 0,
                    'total_value' => 0,
                    'total_received' => 0
                ];
            }
            
            $grouped[$customerId]['count']++;
            $grouped[$customerId]['total_value'] += $installment['total_value'];
            $grouped[$customerId]['total_received'] += $installment['amount_received'] ?? 0;
        }
        
        return array_values($grouped);
    }
    
    /**
     * Agrupar mensalidades por tipo de cobrança
     */
    private function groupInstallmentsByBillingType($installments) {
        $grouped = [];
        
        foreach ($installments as $installment) {
            $billingType = $installment['billing_type'];
            
            if (!isset($grouped[$billingType])) {
                $grouped[$billingType] = [
                    'billing_type' => $billingType,
                    'count' => 0,
                    'total_value' => 0,
                    'total_received' => 0
                ];
            }
            
            $grouped[$billingType]['count']++;
            $grouped[$billingType]['total_value'] += $installment['total_value'];
            $grouped[$billingType]['total_received'] += $installment['amount_received'] ?? 0;
        }
        
        return array_values($grouped);
    }
    
    /**
     * Agrupar mensalidades por status
     */
    private function groupInstallmentsByStatus($installments) {
        $grouped = [];
        
        foreach ($installments as $installment) {
            $status = $installment['status'];
            
            if (!isset($grouped[$status])) {
                $grouped[$status] = [
                    'status' => $status,
                    'count' => 0,
                    'total_value' => 0
                ];
            }
            
            $grouped[$status]['count']++;
            $grouped[$status]['total_value'] += $installment['total_value'];
        }
        
        return array_values($grouped);
    }
    
    /**
     * Calcular atraso médio de pagamentos de um cliente
     */
    private function calculateAvgPaymentDelay($customerEmail) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT AVG(DATEDIFF(paid_date, due_date)) as avg_delay
                FROM installment_payments ip
                JOIN installments i ON ip.installment_id = i.installment_id
                JOIN customers c ON i.customer_id = c.id
                WHERE c.email = ? AND ip.status = 'RECEIVED' AND ip.paid_date > ip.due_date
            ");
            
            $stmt->execute([$customerEmail]);
            $result = $stmt->fetch();
            
            return max(0, (float)($result['avg_delay'] ?? 0));
            
        } catch (PDOException $e) {
            $this->log("Erro ao calcular atraso médio: " . $e->getMessage(), 'WARNING');
            return 0;
        }
    }
    
    /**
     * Calcular nível de risco do cliente
     */
    private function calculateCustomerRiskLevel($customerData) {
        $paymentRate = $customerData['payment_rate'] ?? 0;
        $avgDelay = $customerData['avg_delay'] ?? 0;
        
        if ($paymentRate >= 90 && $avgDelay <= 5) {
            return 'LOW';
        } elseif ($paymentRate >= 70 && $avgDelay <= 15) {
            return 'MEDIUM';
        } else {
            return 'HIGH';
        }
    }
    
    /**
     * Enriquecer dados com informações da API
     */
    private function enrichInstallmentsWithApiData($installments) {
        try {
            $asaas = $this->initAsaas();
            
            foreach ($installments as &$installment) {
                try {
                    // Buscar dados atualizados da API
                    $apiData = $asaas->getInstallmentPayments($installment['installment_id']);
                    
                    if ($apiData && !empty($apiData['data'])) {
                        $installment['api_payments'] = $apiData['data'];
                        $installment['api_stats'] = $this->calculateInstallmentStats($installment, $apiData['data']);
                    }
                    
                } catch (Exception $e) {
                    $this->log("Erro ao enriquecer dados da mensalidade {$installment['installment_id']}: " . $e->getMessage(), 'WARNING');
                }
            }
            
            return $installments;
            
        } catch (Exception $e) {
            $this->log("Erro ao enriquecer dados com API: " . $e->getMessage(), 'WARNING');
            return $installments; // Retorna dados originais se falhar
        }
    }
    
    // ====================================================
    // MÉTODOS DE LOG E AUDITORIA
    // ====================================================
    
    /**
     * Registrar geração de carnê
     */
    private function logPaymentBookGeneration($installmentId, $fileName, $fileSize) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO installment_logs (installment_id, action, details, created_by, created_at) 
                VALUES (?, 'PAYMENT_BOOK_GENERATED', ?, ?, NOW())
            ");
            
            $details = json_encode([
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            $stmt->execute([
                $installmentId,
                $details,
                $this->auth->getUsuarioAtual()['id'] ?? null
            ]);
            
        } catch (PDOException $e) {
            // Se tabela não existe, criar
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                $this->createInstallmentLogsTable();
                // Tentar novamente
                $this->logPaymentBookGeneration($installmentId, $fileName, $fileSize);
            }
        }
    }
    
    /**
     * Registrar mudança de status
     */
    private function logStatusChange($installmentId, $newStatus) {
        try {
            $this->logInstallmentEvent($installmentId, 'STATUS_CHANGED', [
                'new_status' => $newStatus,
                'changed_by' => $this->auth->getUsuarioAtual()['nome'] ?? 'Sistema'
            ]);
        } catch (Exception $e) {
            $this->log("Erro ao registrar mudança de status: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Registrar evento de mensalidade
     */
    private function logInstallmentEvent($installmentId, $event, $details = []) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO installment_logs (installment_id, action, details, created_by, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $installmentId,
                $event,
                json_encode($details),
                $this->auth->getUsuarioAtual()['id'] ?? null
            ]);
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                $this->createInstallmentLogsTable();
                $this->logInstallmentEvent($installmentId, $event, $details);
            }
        }
    }
    
    /**
     * Criar tabela de logs se não existir
     */
    private function createInstallmentLogsTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS installment_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                installment_id VARCHAR(100) NOT NULL,
                action VARCHAR(50) NOT NULL,
                details JSON,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_installment_id (installment_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->getConnection()->exec($sql);
            $this->log("Tabela installment_logs criada");
            
        } catch (PDOException $e) {
            $this->log("Erro ao criar tabela de logs: " . $e->getMessage(), 'ERROR');
        }
    }
    
    // ====================================================
    // MÉTODOS DE WEBHOOK ESPECÍFICOS
    // ====================================================
    
    /**
     * Tratar parcela deletada
     */
    private function handleInstallmentPaymentDeleted($installmentId, $payment) {
        try {
            $this->log("Processando parcela deletada - Mensalidade {$installmentId}");
            
            $this->db->updateInstallmentPaymentStatus($payment['id'], 'DELETED');
            
            $this->logInstallmentEvent($installmentId, 'PAYMENT_DELETED', [
                'payment_id' => $payment['id'],
                'reason' => 'deleted_via_webhook'
            ]);
            
            return [
                'status' => 'processed',
                'action' => 'installment_payment_deleted',
                'installment_id' => $installmentId
            ];
            
        } catch (Exception $e) {
            $this->log("Erro ao processar parcela deletada: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Tratar parcela restaurada
     */
    private function handleInstallmentPaymentRestored($installmentId, $payment) {
        try {
            $this->log("Processando parcela restaurada - Mensalidade {$installmentId}");
            
            $this->db->updateInstallmentPaymentStatus($payment['id'], $payment['status']);
            
            $this->logInstallmentEvent($installmentId, 'PAYMENT_RESTORED', [
                'payment_id' => $payment['id'],
                'new_status' => $payment['status']
            ]);
            
            return [
                'status' => 'processed',
                'action' => 'installment_payment_restored',
                'installment_id' => $installmentId
            ];
            
        } catch (Exception $e) {
            $this->log("Erro ao processar parcela restaurada: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    // ====================================================
    // MÉTODOS UTILITÁRIOS PÚBLICOS
    // ====================================================
    
    /**
     * Obter estatísticas rápidas de uma mensalidade
     */
    public function getQuickInstallmentStats($installmentId) {
        try {
            $installmentInfo = $this->db->getInstallmentInfo($installmentId);
            
            if (!$installmentInfo) {
                return null;
            }
            
            // Contar parcelas pagas localmente (mais rápido)
            $stmt = $this->db->getConnection()->prepare("
                SELECT 
                    COUNT(*) as total_payments,
                    COUNT(CASE WHEN status = 'RECEIVED' THEN 1 END) as payments_received,
                    SUM(CASE WHEN status = 'RECEIVED' THEN value ELSE 0 END) as amount_received
                FROM installment_payments 
                WHERE installment_id = ?
            ");
            
            $stmt->execute([$installmentId]);
            $stats = $stmt->fetch();
            
            $completionRate = $installmentInfo['total_value'] > 0 ? 
                (($stats['amount_received'] ?? 0) / $installmentInfo['total_value']) * 100 : 0;
            
            return [
                'installment_id' => $installmentId,
                'customer_name' => $installmentInfo['customer_name'],
                'total_payments' => $installmentInfo['installment_count'],
                'payments_received' => $stats['payments_received'] ?? 0,
                'amount_received' => $stats['amount_received'] ?? 0,
                'total_value' => $installmentInfo['total_value'],
                'completion_percentage' => round($completionRate, 2),
                'status' => $installmentInfo['status'],
                'created_at' => $installmentInfo['created_at']
            ];
            
        } catch (Exception $e) {
            $this->log("Erro ao obter estatísticas rápidas: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * Listar mensalidades recentes do usuário atual
     */
    public function getRecentInstallments($limit = 10) {
        try {
            $poloId = $this->auth->isMaster() ? null : $this->auth->getUsuarioAtual()['polo_id'];
            return $this->db->getRecentInstallments($limit, $poloId);
            
        } catch (Exception $e) {
            $this->log("Erro ao buscar mensalidades recentes: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Verificar se usuário pode acessar uma mensalidade
     */
    public function canAccessInstallment($installmentId) {
        try {
            $installmentInfo = $this->db->getInstallmentInfo($installmentId);
            
            if (!$installmentInfo) {
                return false;
            }
            
            // Master acessa tudo
            if ($this->auth->isMaster()) {
                return true;
            }
            
            // Usuário deve ser do mesmo polo
            $userPoloId = $this->auth->getUsuarioAtual()['polo_id'];
            return $installmentInfo['polo_id'] == $userPoloId;
            
        } catch (Exception $e) {
            $this->log("Erro ao verificar acesso: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Obter estatísticas gerais do sistema de mensalidades
     */
    public function getSystemStats() {
        try {
            $poloId = $this->auth->isMaster() ? null : $this->auth->getUsuarioAtual()['polo_id'];
            
            $stats = $this->db->getInstallmentStats($poloId);
            $stats['context'] = $poloId ? 'Polo específico' : 'Sistema completo';
            $stats['generated_at'] = date('Y-m-d H:i:s');
            
            return $stats;
            
        } catch (Exception $e) {
            $this->log("Erro ao obter estatísticas do sistema: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Limpar arquivos temporários antigos
     */
    public function cleanupTempFiles($daysOld = 7) {
        try {
            $tempDir = __DIR__ . '/temp';
            
            if (!is_dir($tempDir)) {
                return ['cleaned' => 0, 'message' => 'Diretório temp não existe'];
            }
            
            $cutoffTime = strtotime("-{$daysOld} days");
            $files = glob($tempDir . '/carne_*.pdf');
            $cleaned = 0;
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $cleaned++;
                    }
                }
            }
            
            $this->log("Limpeza de arquivos temporários: {$cleaned} arquivos removidos");
            
            return [
                'cleaned' => $cleaned,
                'message' => "{$cleaned} arquivos temporários removidos",
                'cutoff_days' => $daysOld
            ];
            
        } catch (Exception $e) {
            $this->log("Erro na limpeza de arquivos temporários: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
}

/**
 * Classe para manipulação de datas de mensalidades
 */
class InstallmentDateCalculator {
    
    /**
     * Calcular próximas datas de vencimento
     */
    public static function calculateDueDates($firstDate, $installmentCount) {
        $dates = [];
        $currentDate = new DateTime($firstDate);
        
        for ($i = 0; $i < $installmentCount; $i++) {
            $dates[] = [
                'installment' => $i + 1,
                'due_date' => $currentDate->format('Y-m-d'),
                'formatted_date' => $currentDate->format('d/m/Y'),
                'month_year' => $currentDate->format('m/Y'),
                'month_name' => self::getMonthName($currentDate->format('n')),
                'year' => $currentDate->format('Y'),
                'weekday' => $currentDate->format('l'),
                'is_weekend' => in_array($currentDate->format('N'), [6, 7])
            ];
            
            // Próximo mês
            $currentDate->add(new DateInterval('P1M'));
        }
        
        return $dates;
    }
    
    /**
     * Obter nome do mês em português
     */
    private static function getMonthName($monthNumber) {
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
            4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
            7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
            10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];
        
        return $months[(int)$monthNumber] ?? 'Mês ' . $monthNumber;
    }
    
    /**
     * Ajustar data para dia útil se necessário
     */
    public static function adjustToBusinessDay($date) {
        $dateObj = new DateTime($date);
        $dayOfWeek = $dateObj->format('N'); // 1 = Segunda, 7 = Domingo
        
        // Se for sábado (6), mover para segunda (adicionar 2 dias)
        if ($dayOfWeek == 6) {
            $dateObj->add(new DateInterval('P2D'));
        }
        // Se for domingo (7), mover para segunda (adicionar 1 dia)
        elseif ($dayOfWeek == 7) {
            $dateObj->add(new DateInterval('P1D'));
        }
        
        return $dateObj->format('Y-m-d');
    }
}

/**
 * Classe para formatação de dados de mensalidades
 */
class InstallmentFormatter {
    
    /**
     * Formatar resumo de mensalidade
     */
    public static function formatSummary($installmentData) {
        return [
            'id' => $installmentData['installment_id'],
            'customer' => $installmentData['customer_name'] ?? 'N/A',
            'installments' => $installmentData['installment_count'] . 'x',
            'installment_value' => 'R$ ' . number_format($installmentData['installment_value'], 2, ',', '.'),
            'total_value' => 'R$ ' . number_format($installmentData['total_value'], 2, ',', '.'),
            'first_due_date' => date('d/m/Y', strtotime($installmentData['first_due_date'])),
            'billing_type' => self::formatBillingType($installmentData['billing_type']),
            'status' => self::formatStatus($installmentData['status']),
            'has_splits' => $installmentData['has_splits'] ? 'Sim' : 'Não',
            'created_at' => date('d/m/Y H:i', strtotime($installmentData['created_at']))
        ];
    }
    
    /**
     * Formatar tipo de cobrança
     */
    private static function formatBillingType($billingType) {
        $types = [
            'BOLETO' => '📄 Boleto',
            'PIX' => '⚡ PIX',
            'CREDIT_CARD' => '💳 Cartão de Crédito',
            'DEBIT_CARD' => '💳 Cartão de Débito'
        ];
        
        return $types[$billingType] ?? $billingType;
    }
    
    /**
     * Formatar status
     */
    private static function formatStatus($status) {
        $statuses = [
            'ACTIVE' => '✅ Ativa',
            'COMPLETED' => '🎉 Concluída',
            'CANCELLED' => '❌ Cancelada',
            'SUSPENDED' => '⏸️ Suspensa'
        ];
        
        return $statuses[$status] ?? $status;
    }
    
    /**
     * Formatar progresso de pagamento
     */
    public static function formatProgress($received, $total, $amountReceived = 0, $totalValue = 0) {
        $percentage = $total > 0 ? ($received / $total) * 100 : 0;
        $amountPercentage = $totalValue > 0 ? ($amountReceived / $totalValue) * 100 : 0;
        
        return [
            'payments' => [
                'received' => $received,
                'total' => $total,
                'remaining' => $total - $received,
                'percentage' => round($percentage, 1)
            ],
            'amount' => [
                'received' => $amountReceived,
                'total' => $totalValue,
                'remaining' => $totalValue - $amountReceived,
                'percentage' => round($amountPercentage, 1),
                'received_formatted' => 'R$ ' . number_format($amountReceived, 2, ',', '.'),
                'total_formatted' => 'R$ ' . number_format($totalValue, 2, ',', '.'),
                'remaining_formatted' => 'R$ ' . number_format($totalValue - $amountReceived, 2, ',', '.')
            ],
            'status_class' => self::getProgressStatusClass($percentage),
            'is_completed' => $percentage >= 100
        ];
    }
    
    /**
     * Obter classe CSS baseada no progresso
     */
    private static function getProgressStatusClass($percentage) {
        if ($percentage >= 100) return 'success';
        if ($percentage >= 75) return 'info';
        if ($percentage >= 50) return 'warning';
        return 'danger';
    }
}

/**
 * Classe para notificações de mensalidades
 */
class InstallmentNotificationManager {
    
    private $installmentManager;
    private $db;
    
    public function __construct() {
        $this->installmentManager = new InstallmentManager();
        $this->db = DatabaseManager::getInstance();
    }
    
    /**
     * Verificar mensalidades que precisam de notificação
     */
    public function checkForNotifications() {
        try {
            // Mensalidades com parcelas vencendo em 3 dias
            $upcomingPayments = $this->getUpcomingPayments(3);
            
            // Mensalidades com parcelas vencidas
            $overduePayments = $this->getOverduePayments();
            
            // Mensalidades completadas recentemente (últimos 7 dias)
            $completedInstallments = $this->getRecentlyCompleted(7);
            
            return [
                'upcoming_payments' => $upcomingPayments,
                'overdue_payments' => $overduePayments,
                'completed_installments' => $completedInstallments,
                'notifications_count' => count($upcomingPayments) + count($overduePayments) + count($completedInstallments)
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao verificar notificações: " . $e->getMessage());
            return [
                'upcoming_payments' => [],
                'overdue_payments' => [],
                'completed_installments' => [],
                'notifications_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Buscar parcelas que vencem em X dias
     */
    private function getUpcomingPayments($daysAhead) {
        $targetDate = date('Y-m-d', strtotime("+{$daysAhead} days"));
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                i.installment_id,
                i.customer_id,
                c.name as customer_name,
                c.email as customer_email,
                ip.installment_number,
                ip.due_date,
                ip.value,
                i.description
            FROM installment_payments ip
            JOIN installments i ON ip.installment_id = i.installment_id
            JOIN customers c ON i.customer_id = c.id
            WHERE ip.due_date = ? 
            AND ip.status = 'PENDING'
            AND i.status = 'ACTIVE'
            ORDER BY c.name, ip.installment_number
        ");
        
        $stmt->execute([$targetDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar parcelas vencidas
     */
    private function getOverduePayments() {
        $today = date('Y-m-d');
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                i.installment_id,
                i.customer_id,
                c.name as customer_name,
                c.email as customer_email,
                ip.installment_number,
                ip.due_date,
                ip.value,
                i.description,
                DATEDIFF(?, ip.due_date) as days_overdue
            FROM installment_payments ip
            JOIN installments i ON ip.installment_id = i.installment_id
            JOIN customers c ON i.customer_id = c.id
            WHERE ip.due_date < ? 
            AND ip.status NOT IN ('RECEIVED', 'DELETED')
            AND i.status = 'ACTIVE'
            ORDER BY days_overdue DESC, c.name, ip.installment_number
        ");
        
        $stmt->execute([$today, $today]);
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar mensalidades completadas recentemente
     */
    private function getRecentlyCompleted($daysBack) {
        $startDate = date('Y-m-d', strtotime("-{$daysBack} days"));
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                i.installment_id,
                i.customer_id,
                c.name as customer_name,
                i.installment_count,
                i.total_value,
                i.updated_at
            FROM installments i
            JOIN customers c ON i.customer_id = c.id
            WHERE i.status = 'COMPLETED'
            AND i.updated_at >= ?
            ORDER BY i.updated_at DESC
        ");
        
        $stmt->execute([$startDate]);
        return $stmt->fetchAll();
    }
}

/**
 * Classe para exportação de dados de mensalidades
 */
class InstallmentExporter {
    
    private $installmentManager;
    
    public function __construct() {
        $this->installmentManager = new InstallmentManager();
    }
    
    /**
     * Exportar mensalidades para CSV
     */
    public function exportToCSV($startDate, $endDate, $options = []) {
        try {
            $report = $this->installmentManager->generateInstallmentReport($startDate, $endDate, $options);
            
            $filename = "mensalidades_{$startDate}_{$endDate}_" . date('YmdHis') . '.csv';
            $filepath = __DIR__ . '/temp/' . $filename;
            
            // Criar diretório se não existir
            if (!is_dir(__DIR__ . '/temp')) {
                mkdir(__DIR__ . '/temp', 0755, true);
            }
            
            $file = fopen($filepath, 'w');
            
            // Cabeçalho
            $headers = [
                'ID Mensalidade',
                'Cliente',
                'Email Cliente',
                'Qtd Parcelas',
                'Valor Parcela',
                'Valor Total',
                'Primeiro Vencimento',
                'Tipo Cobrança',
                'Tem Splits',
                'Qtd Splits',
                'Status',
                'Criado em',
                'Criado por'
            ];
            
            fputcsv($file, $headers, ';');
            
            // Dados
            foreach ($report['installments'] as $installment) {
                $row = [
                    $installment['installment_id'],
                    $installment['customer_name'] ?? '',
                    $installment['customer_email'] ?? '',
                    $installment['installment_count'],
                    number_format($installment['installment_value'], 2, ',', '.'),
                    number_format($installment['total_value'], 2, ',', '.'),
                    date('d/m/Y', strtotime($installment['first_due_date'])),
                    $installment['billing_type'],
                    $installment['has_splits'] ? 'Sim' : 'Não',
                    $installment['splits_count'],
                    $installment['status'],
                    date('d/m/Y H:i', strtotime($installment['created_at'])),
                    $installment['created_by_name'] ?? ''
                ];
                
                fputcsv($file, $row, ';');
            }
            
            fclose($file);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'records_exported' => count($report['installments'])
            ];
            
        } catch (Exception $e) {
            throw new Exception('Erro ao exportar CSV: ' . $e->getMessage());
        }
    }
    
    /**
     * Exportar relatório detalhado para Excel (usando CSV com formatação especial)
     */
    public function exportDetailedReport($startDate, $endDate) {
        try {
            $report = $this->installmentManager->generateInstallmentReport($startDate, $endDate, [
                'include_api_data' => true
            ]);
            
            $filename = "relatorio_detalhado_mensalidades_{$startDate}_{$endDate}_" . date('YmdHis') . '.csv';
            $filepath = __DIR__ . '/temp/' . $filename;
            
            if (!is_dir(__DIR__ . '/temp')) {
                mkdir(__DIR__ . '/temp', 0755, true);
            }
            
            $file = fopen($filepath, 'w');
            
            // Escrever informações do relatório
            fputcsv($file, ['RELATÓRIO DETALHADO DE MENSALIDADES'], ';');
            fputcsv($file, ['Período:', $startDate . ' a ' . $endDate], ';');
            fputcsv($file, ['Gerado em:', date('d/m/Y H:i:s')], ';');
            fputcsv($file, ['Contexto:', $report['context']['polo_name']], ';');
            fputcsv($file, [], ';'); // Linha vazia
            
            // Resumo
            fputcsv($file, ['RESUMO EXECUTIVO'], ';');
            fputcsv($file, ['Total de Mensalidades:', $report['summary']['total_installments']], ';');
            fputcsv($file, ['Valor Total:', 'R$ ' . number_format($report['summary']['total_value'], 2, ',', '.')], ';');
            fputcsv($file, ['Valor Recebido:', 'R$ ' . number_format($report['summary']['total_received'], 2, ',', '.')], ';');
            fputcsv($file, ['Taxa de Cobrança:', number_format($report['summary']['collection_rate'], 2, ',', '.') . '%'], ';');
            fputcsv($file, [], ';'); // Linha vazia
            
            // Cabeçalho detalhado
            $detailedHeaders = [
                'ID Mensalidade',
                'Cliente',
                'Email',
                'Descrição',
                'Qtd Parcelas',
                'Valor Parcela',
                'Valor Total',
                'Valor Recebido',
                'Parcelas Pagas',
                '% Conclusão',
                'Primeiro Vencimento',
                'Tipo Cobrança',
                'Status',
                'Tem Splits',
                'Qtd Splits',
                'Criado em',
                'Atualizado em'
            ];
            
            fputcsv($file, ['DETALHAMENTO POR MENSALIDADE'], ';');
            fputcsv($file, $detailedHeaders, ';');
            
            // Dados detalhados
            foreach ($report['installments'] as $installment) {
                $completionRate = $installment['total_value'] > 0 ? 
                    (($installment['amount_received'] ?? 0) / $installment['total_value']) * 100 : 0;
                
                $row = [
                    $installment['installment_id'],
                    $installment['customer_name'] ?? '',
                    $installment['customer_email'] ?? '',
                    $installment['description'] ?? '',
                    $installment['installment_count'],
                    'R$ ' . number_format($installment['installment_value'], 2, ',', '.'),
                    'R$ ' . number_format($installment['total_value'], 2, ',', '.'),
                    'R$ ' . number_format($installment['amount_received'] ?? 0, 2, ',', '.'),
                    $installment['payments_made'] ?? 0,
                    number_format($completionRate, 1, ',', '.') . '%',
                    date('d/m/Y', strtotime($installment['first_due_date'])),
                    $installment['billing_type'],
                    $installment['status'],
                    $installment['has_splits'] ? 'Sim' : 'Não',
                    $installment['splits_count'],
                    date('d/m/Y H:i', strtotime($installment['created_at'])),
                    date('d/m/Y H:i', strtotime($installment['updated_at'] ?? $installment['created_at']))
                ];
                
                fputcsv($file, $row, ';');
            }
            
            fclose($file);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'type' => 'detailed_report'
            ];
            
        } catch (Exception $e) {
            throw new Exception('Erro ao exportar relatório detalhado: ' . $e->getMessage());
        }
    }
}

/**
 * Classe utilitária para comandos CLI do sistema de mensalidades
 */
class InstallmentCLI {
    
    private $manager;
    
    public function __construct() {
        $this->manager = new InstallmentManager();
    }
    
    /**
     * Executar comando CLI
     */
    public function execute($command, $args = []) {
        switch ($command) {
            case 'sync-all':
                return $this->syncAllInstallments();
                
            case 'cleanup-temp':
                $days = $args['days'] ?? 7;
                return $this->manager->cleanupTempFiles($days);
                
            case 'health-check':
                return $this->healthCheck();
                
            case 'notifications':
                return $this->checkNotifications();
                
            case 'stats':
                return $this->showStats();
                
            default:
                return ['error' => 'Comando não reconhecido: ' . $command];
        }
    }
    
    /**
     * Sincronizar todas as mensalidades com a API
     */
    private function syncAllInstallments() {
        try {
            echo "🔄 Sincronizando mensalidades com API ASAAS...\n";
            
            $db = DatabaseManager::getInstance();
            $stmt = $db->getConnection()->query("SELECT installment_id FROM installments WHERE status = 'ACTIVE'");
            $installments = $stmt->fetchAll();
            
            $synced = 0;
            $errors = 0;
            
            foreach ($installments as $installment) {
                try {
                    $count = $this->manager->syncInstallmentPayments($installment['installment_id']);
                    if ($count !== false) {
                        $synced++;
                        echo "  ✅ {$installment['installment_id']}: {$count} parcelas sincronizadas\n";
                    } else {
                        $errors++;
                        echo "  ❌ {$installment['installment_id']}: Erro na sincronização\n";
                    }
                } catch (Exception $e) {
                    $errors++;
                    echo "  ❌ {$installment['installment_id']}: {$e->getMessage()}\n";
                }
            }
            
            echo "\n📊 Resultado: {$synced} sincronizadas, {$errors} erros\n";
            
            return [
                'synced' => $synced,
                'errors' => $errors,
                'total' => count($installments)
            ];
            
        } catch (Exception $e) {
            echo "❌ Erro geral: {$e->getMessage()}\n";
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Verificação de saúde do sistema
     */
    private function healthCheck() {
        echo "🔍 Verificando saúde do sistema de mensalidades...\n\n";
        
        $issues = [];
        
        // Verificar banco de dados
        try {
            $stats = $this->manager->getSystemStats();
            echo "✅ Banco de dados: Conectado\n";
            echo "   • {$stats['total_installments']} mensalidades cadastradas\n";
            echo "   • {$stats['active_installments']} mensalidades ativas\n";
        } catch (Exception $e) {
            echo "❌ Banco de dados: {$e->getMessage()}\n";
            $issues[] = 'Problema no banco de dados';
        }
        
        // Verificar API ASAAS
        try {
            $asaas = AsaasConfig::getInstance();
            $test = $asaas->listAccounts(1, 0);
            echo "✅ API ASAAS: Conectada ({$test['totalCount']} contas)\n";
        } catch (Exception $e) {
            echo "❌ API ASAAS: {$e->getMessage()}\n";
            $issues[] = 'Problema na API ASAAS';
        }
        
        // Verificar diretórios
        $dirs = [
            __DIR__ . '/temp' => 'Diretório temporário',
            __DIR__ . '/logs' => 'Diretório de logs'
        ];
        
        foreach ($dirs as $dir => $name) {
            if (!is_dir($dir)) {
                echo "⚠️ {$name}: Não existe (será criado quando necessário)\n";
            } elseif (!is_writable($dir)) {
                echo "❌ {$name}: Sem permissão de escrita\n";
                $issues[] = "Sem permissão em {$dir}";
            } else {
                echo "✅ {$name}: OK\n";
            }
        }
        
        // Verificar notificações
        try {
            $notificationManager = new InstallmentNotificationManager();
            $notifications = $notificationManager->checkForNotifications();
            echo "✅ Sistema de notificações: OK\n";
            echo "   • {$notifications['notifications_count']} notificações pendentes\n";
        } catch (Exception $e) {
            echo "❌ Sistema de notificações: {$e->getMessage()}\n";
            $issues[] = 'Problema nas notificações';
        }
        
        echo "\n";
        
        if (empty($issues)) {
            echo "🎉 Sistema funcionando perfeitamente!\n";
            return ['status' => 'healthy', 'issues' => []];
        } else {
            echo "⚠️ Problemas encontrados:\n";
            foreach ($issues as $issue) {
                echo "  • {$issue}\n";
            }
            return ['status' => 'issues', 'issues' => $issues];
        }
    }
    
    /**
     * Verificar notificações
     */
    private function checkNotifications() {
        try {
            $notificationManager = new InstallmentNotificationManager();
            $notifications = $notificationManager->checkForNotifications();
            
            echo "🔔 Verificação de Notificações\n";
            echo "==============================\n\n";
            
            if ($notifications['notifications_count'] == 0) {
                echo "✅ Nenhuma notificação pendente\n";
                return $notifications;
            }
            
            // Parcelas a vencer
            if (!empty($notifications['upcoming_payments'])) {
                echo "⏰ Parcelas vencendo em 3 dias: " . count($notifications['upcoming_payments']) . "\n";
                foreach ($notifications['upcoming_payments'] as $payment) {
                    echo "   • {$payment['customer_name']} - Parcela {$payment['installment_number']} - R$ " . 
                         number_format($payment['value'], 2, ',', '.') . "\n";
                }
                echo "\n";
            }
            
            // Parcelas vencidas
            if (!empty($notifications['overdue_payments'])) {
                echo "⚠️ Parcelas vencidas: " . count($notifications['overdue_payments']) . "\n";
                foreach ($notifications['overdue_payments'] as $payment) {
                    echo "   • {$payment['customer_name']} - Parcela {$payment['installment_number']} - " . 
                         "{$payment['days_overdue']} dias de atraso\n";
                }
                echo "\n";
            }
            
            // Mensalidades completadas
            if (!empty($notifications['completed_installments'])) {
                echo "🎉 Mensalidades completadas recentemente: " . count($notifications['completed_installments']) . "\n";
                foreach ($notifications['completed_installments'] as $installment) {
                    echo "   • {$installment['customer_name']} - R$ " . 
                         number_format($installment['total_value'], 2, ',', '.') . "\n";
                }
            }
            
            return $notifications;
            
        } catch (Exception $e) {
            echo "❌ Erro: {$e->getMessage()}\n";
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Mostrar estatísticas
     */
    private function showStats() {
        try {
            $stats = $this->manager->getSystemStats();
            
            echo "📊 Estatísticas do Sistema de Mensalidades\n";
            echo "=========================================\n\n";
            
            echo "Total de mensalidades: " . number_format($stats['total_installments']) . "\n";
            echo "Total de parcelas esperadas: " . number_format($stats['total_payments_expected']) . "\n";
            echo "Valor total esperado: R$ " . number_format($stats['total_value_expected'], 2, ',', '.') . "\n";
            echo "Média de parcelas por mensalidade: " . number_format($stats['avg_installments_per_customer'], 1, ',', '.') . "\n";
            echo "Valor médio por parcela: R$ " . number_format($stats['avg_installment_value'], 2, ',', '.') . "\n\n";
            
            echo "Status das mensalidades:\n";
            echo "• Ativas: " . $stats['active_installments'] . "\n";
            echo "• Concluídas: " . $stats['completed_installments'] . "\n";
            echo "• Com splits: " . $stats['installments_with_splits'] . "\n\n";
            
            echo "Gerado em: " . $stats['generated_at'] . "\n";
            echo "Contexto: " . $stats['context'] . "\n";
            
            return $stats;
            
        } catch (Exception $e) {
            echo "❌ Erro: {$e->getMessage()}\n";
            return ['error' => $e->getMessage()];
        }
    }
}

// ====================================================
// EXECUÇÃO VIA LINHA DE COMANDO
// ====================================================

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    
    $command = isset($argv[1]) ? $argv[1] : '';
    $args = array_slice($argv, 2);
    
    // Converter argumentos para array associativo
    $parsedArgs = [];
    foreach ($args as $arg) {
        if (strpos($arg, '=') !== false) {
            list($key, $value) = explode('=', $arg, 2);
            $parsedArgs[ltrim($key, '--')] = $value;
        }
    }
    
    $cli = new InstallmentCLI();
    
    if (empty($command)) {
        echo "Sistema de Mensalidades IMEP Split ASAAS v3.3\n";
        echo "=============================================\n\n";
        echo "Comandos disponíveis:\n";
        echo "  sync-all          - Sincronizar todas as mensalidades com API\n";
        echo "  cleanup-temp      - Limpar arquivos temporários (--days=7)\n";
        echo "  health-check      - Verificar saúde do sistema\n";
        echo "  notifications     - Verificar notificações pendentes\n";
        echo "  stats            - Mostrar estatísticas do sistema\n\n";
        echo "Exemplos:\n";
        echo "  php " . basename(__FILE__) . " sync-all\n";
        echo "  php " . basename(__FILE__) . " cleanup-temp --days=30\n";
        echo "  php " . basename(__FILE__) . " health-check\n";
        exit(0);
    }
    
    try {
        $result = $cli->execute($command, $parsedArgs);
        
        if (isset($result['error'])) {
            exit(1);
        } else {
            exit(0);
        }
        
    } catch (Exception $e) {
        echo "❌ Erro fatal: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Log de inicialização do sistema de mensalidades COM DESCONTO
error_log("Sistema de mensalidades COM DESCONTO carregado - v3.4");
?>