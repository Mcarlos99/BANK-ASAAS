<?php

/**
 * Sistema de Split de Pagamentos - ASAAS - VERSÃO COM PARCELAMENTO
 * Versão para Produção - CORRIGIDA E MELHORADA
 * 
 * Autor: Sistema de Pagamentos
 * Data: 2025
 * Novidade: Suporte completo a parcelamento/mensalidades
 */

class AsaasSplitPayment {
    
    private $apiKey;
    private $baseUrl;
    private $environment;
    private $logFile;
    
    public function __construct($apiKey, $environment = 'production') {
        $this->apiKey = $apiKey;
        $this->environment = $environment;
        $this->baseUrl = ($environment === 'production') ? 
            'https://www.asaas.com/api/v3' : 
            'https://sandbox.asaas.com/api/v3';
        $this->logFile = __DIR__ . '/logs/asaas_' . date('Y-m-d') . '.log';
        
        // Criar diretório de logs se não existir
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
    }
    
    /**
     * Registra logs do sistema
     */
    private function log($message, $type = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Executa requisições para API do ASAAS
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'access_token: ' . $this->apiKey,
                'User-Agent: ASAAS-Split-System/2.0 (PHP/' . PHP_VERSION . ')'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        
        if ($data && ($method === 'POST' || $method === 'PUT')) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($error) {
            $this->log("Erro cURL: {$error}", 'ERROR');
            throw new Exception("Erro de conexão: {$error}");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = isset($decodedResponse['errors']) ? 
                json_encode($decodedResponse['errors']) : 
                'Erro desconhecido';
            $this->log("Erro API [{$httpCode}]: {$errorMsg}", 'ERROR');
            throw new Exception("Erro na API: {$errorMsg}");
        }
        
        $this->log("Requisição executada: {$method} {$endpoint} - HTTP {$httpCode}");
        return $decodedResponse;
    }
    
    /**
     * ===== NOVA FUNCIONALIDADE: CRIAR PAGAMENTO PARCELADO COM SPLIT =====
     * Cria mensalidade/parcelamento com configuração de splits
     */

     public function createInstallmentPaymentWithSplit($paymentData, $splitData, $installmentData) {
        try {
            $installmentCount = (int)$installmentData['installmentCount'];
            $installmentValue = (float)$installmentData['installmentValue'];
            
            $this->log("Iniciando criação de parcelamento - Parcelas: {$installmentCount} x R$ {$installmentValue}", 'INFO');
            
            // ===== LOG DE DEBUG PARA DESCONTO =====
            $this->log("PaymentData recebido: " . json_encode($paymentData), 'DEBUG');
            
            // Validar dados do parcelamento
            if ($installmentCount < 2 || $installmentCount > 24) {
                throw new Exception("Número de parcelas deve ser entre 2 e 24");
            }
            
            if ($installmentValue <= 0) {
                throw new Exception("Valor da parcela deve ser maior que zero");
            }
            
            // Validar splits
            $totalPercentage = 0;
            $totalFixedValue = 0;
            
            foreach ($splitData as $split) {
                if (empty($split['walletId'])) {
                    continue;
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
            
            // ===== PREPARAR DADOS PARA API ASAAS =====
            $data = [
                'customer' => $paymentData['customer'],
                'billingType' => $paymentData['billingType'],
                'dueDate' => $paymentData['dueDate'],
                'installmentCount' => $installmentCount,
                'installmentValue' => $installmentValue,
                'description' => $paymentData['description'],
                'split' => $splitData
            ];
    
            // ===== CORREÇÃO PRINCIPAL: VERIFICAÇÃO CORRETA DO DESCONTO =====
            // Verificar no paymentData primeiro
            if (isset($paymentData['discount']) && 
                is_array($paymentData['discount']) && 
                !empty($paymentData['discount']['value']) && 
                $paymentData['discount']['value'] > 0) {
                
                $data['discount'] = $paymentData['discount'];
                $this->log("✅ DESCONTO ADICIONADO À REQUISIÇÃO ASAAS (via paymentData): " . json_encode($paymentData['discount']), 'SUCCESS');
                
            } 
            // Se não estiver no paymentData, verificar no installmentData
            elseif (isset($installmentData['discount_value']) && 
                    $installmentData['discount_value'] > 0) {
                
                $data['discount'] = [
                    'value' => (float)$installmentData['discount_value'],
                    'dueDateLimitDays' => 0,
                    'type' => 'FIXED'
                ];
                $this->log("✅ DESCONTO ADICIONADO À REQUISIÇÃO ASAAS (via installmentData): R$ {$installmentData['discount_value']}", 'SUCCESS');
                
            } else {
                $this->log("ℹ️ NENHUM DESCONTO CONFIGURADO - dados recebidos OK", 'INFO');
            }
    
            // Adicionar campos opcionais
            if (isset($paymentData['interest'])) {
                $data['interest'] = $paymentData['interest'];
            }
            
            if (isset($paymentData['fine'])) {
                $data['fine'] = $paymentData['fine'];
            }
            
            // Adicionar informações adicionais sobre mensalidade
            if (isset($installmentData['description_suffix'])) {
                $data['description'] = $paymentData['description'] . ' - ' . $installmentData['description_suffix'];
            }
            
            $this->log("DADOS FINAIS PARA API ASAAS: " . json_encode($data, JSON_UNESCAPED_UNICODE), 'DEBUG');
            
            // ===== FAZER REQUISIÇÃO PARA API =====
            $response = $this->makeRequest('/payments', 'POST', $data);
            
            // ===== VERIFICAR SE DESCONTO FOI APLICADO =====
            if (isset($response['discount']) && $response['discount']['value'] > 0) {
                $this->log("✅ DESCONTO APLICADO COM SUCESSO: R$ {$response['discount']['value']}", 'SUCCESS');
            } else {
                $this->log("⚠️ DESCONTO NÃO APLICADO - Resposta: " . json_encode($response['discount'] ?? 'SEM CAMPO DISCOUNT'), 'WARNING');
            }
            
            // Log de sucesso
            $this->log("Parcelamento criado com sucesso - ID: {$response['id']}", 'SUCCESS');
            $this->log("Installment ID: {$response['installment']}", 'INFO');
            
            // Adicionar informações úteis ao retorno
            $response['installment_info'] = [
                'installment_count' => $installmentCount,
                'installment_value' => $installmentValue,
                'total_value' => $installmentCount * $installmentValue,
                'first_due_date' => $paymentData['dueDate'],
                'split_applied_to_all' => true,
                'splits_count' => count($splitData),
                'has_discount' => isset($data['discount']) && $data['discount']['value'] > 0,
                'discount_per_installment' => isset($data['discount']) ? $data['discount']['value'] : 0
            ];
            
            return $response;
            
        } catch (Exception $e) {
            $this->log("Erro ao criar parcelamento: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Buscar todas as parcelas de um parcelamento
     */
    public function getInstallmentPayments($installmentId) {
        try {
            $this->log("Buscando parcelas do installment: {$installmentId}");
            
            $response = $this->makeRequest("/installments/{$installmentId}/payments");
            
            $this->log("Encontradas " . count($response['data']) . " parcelas");
            return $response;
            
        } catch (Exception $e) {
            $this->log("Erro ao buscar parcelas: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Gerar carnê em PDF de um parcelamento
     */
    public function generateInstallmentPaymentBook($installmentId) {
        try {
            $this->log("Gerando carnê para installment: {$installmentId}");
            
            // Fazer requisição para gerar o carnê
            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->baseUrl . "/installments/{$installmentId}/paymentBook",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60, // Mais tempo para gerar PDF
                CURLOPT_HTTPHEADER => [
                    'access_token: ' . $this->apiKey,
                    'User-Agent: ASAAS-Split-System/2.0'
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            
            $pdfContent = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            
            curl_close($curl);
            
            if ($error) {
                throw new Exception("Erro ao gerar carnê: {$error}");
            }
            
            if ($httpCode !== 200) {
                throw new Exception("Erro HTTP {$httpCode} ao gerar carnê");
            }
            
            $this->log("Carnê gerado com sucesso");
            
            return [
                'success' => true,
                'pdf_content' => $pdfContent,
                'content_type' => 'application/pdf',
                'filename' => "carne_parcelas_{$installmentId}.pdf"
            ];
            
        } catch (Exception $e) {
            $this->log("Erro ao gerar carnê: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Calcular próximas datas de vencimento
     */
    public function calculateInstallmentDueDates($firstDueDate, $installmentCount) {
        $dueDates = [];
        $currentDate = new DateTime($firstDueDate);
        
        for ($i = 0; $i < $installmentCount; $i++) {
            $dueDates[] = [
                'installment' => $i + 1,
                'due_date' => $currentDate->format('Y-m-d'),
                'formatted_date' => $currentDate->format('d/m/Y'),
                'month_year' => $currentDate->format('m/Y')
            ];
            
            // Adicionar 1 mês para próxima parcela
            $currentDate->add(new DateInterval('P1M'));
        }
        
        return $dueDates;
    }
    
    /**
     * ===== MÉTODO ORIGINAL MANTIDO PARA COMPATIBILIDADE =====
     * Cria cobrança simples com split (1x)
     */
    public function createPaymentWithSplit($paymentData, $splitData) {
        try {
            $this->log("Iniciando criação de pagamento simples - Valor: R$ " . $paymentData['value']);
            
            // Validar se a soma dos splits não excede 100%
            $totalPercentage = 0;
            $totalFixed = 0;
            
            foreach ($splitData as $split) {
                if (isset($split['percentualValue'])) {
                    $totalPercentage += $split['percentualValue'];
                }
                if (isset($split['fixedValue'])) {
                    $totalFixed += $split['fixedValue'];
                }
            }
            
            if ($totalPercentage > 100) {
                throw new Exception("A soma dos percentuais não pode exceder 100%");
            }
            
            if ($totalFixed >= $paymentData['value']) {
                throw new Exception("A soma dos valores fixos não pode ser maior ou igual ao valor total do pagamento");
            }
            
            $data = [
                'customer' => $paymentData['customer'],
                'billingType' => $paymentData['billingType'],
                'dueDate' => $paymentData['dueDate'],
                'value' => $paymentData['value'],
                'description' => $paymentData['description'],
                'split' => $splitData
            ];
            
            // Adicionar campos opcionais
            if (isset($paymentData['discount'])) {
                $data['discount'] = $paymentData['discount'];
            }
            
            if (isset($paymentData['interest'])) {
                $data['interest'] = $paymentData['interest'];
            }
            
            if (isset($paymentData['fine'])) {
                $data['fine'] = $paymentData['fine'];
            }
            
            $response = $this->makeRequest('/payments', 'POST', $data);
            
            $this->log("Pagamento simples criado com sucesso - ID: " . $response['id']);
            return $response;
            
        } catch (Exception $e) {
            $this->log("Erro ao criar pagamento simples: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * ===== MÉTODOS EXISTENTES MANTIDOS =====
     */
    
    /**
     * Cria uma conta para receber splits
     */
    public function createAccount($accountData) {
        try {
            $this->log("Iniciando criação de conta para: " . $accountData['name']);
            
            $data = [
                'name' => $accountData['name'],
                'email' => $accountData['email'],
                'cpfCnpj' => $accountData['cpfCnpj'],
                'mobilePhone' => $accountData['mobilePhone'],
                'address' => $accountData['address'],
                'addressNumber' => $accountData['addressNumber'] ?? '',
                'province' => $accountData['province'], // Estado (sigla)
                'postalCode' => $accountData['postalCode'],
                'companyType' => $accountData['companyType'] ?? 'INDIVIDUAL',
                'incomeValue' => (int)($accountData['incomeValue'] ?? 2500) // Valor numérico
            ];
            
            // Adicionar campos opcionais
            if (isset($accountData['birthDate'])) {
                $data['birthDate'] = $accountData['birthDate'];
            }
            
            if (isset($accountData['complement']) && !empty($accountData['complement'])) {
                $data['complement'] = $accountData['complement'];
            }
            
            if (isset($accountData['district']) && !empty($accountData['district'])) {
                $data['district'] = $accountData['district'];
            }
            
            $response = $this->makeRequest('/accounts', 'POST', $data);
            
            $this->log("Conta criada com sucesso - ID: " . $response['id']);
            return $response;
            
        } catch (Exception $e) {
            $this->log("Erro ao criar conta: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Lista contas cadastradas
     */
    public function listAccounts($limit = 100, $offset = 0) {
        try {
            $endpoint = "/accounts?limit={$limit}&offset={$offset}";
            return $this->makeRequest($endpoint);
        } catch (Exception $e) {
            $this->log("Erro ao listar contas: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Cria cliente
     */
    public function createCustomer($customerData) {
        try {
            $this->log("Criando cliente: " . $customerData['name']);
            
            $data = [
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                'cpfCnpj' => $customerData['cpfCnpj'],
                'mobilePhone' => $customerData['mobilePhone']
            ];
            
            // Adicionar campos opcionais
            if (isset($customerData['address'])) {
                $data['address'] = $customerData['address'];
            }
            
            if (isset($customerData['addressNumber'])) {
                $data['addressNumber'] = $customerData['addressNumber'];
            }
            
            if (isset($customerData['complement'])) {
                $data['complement'] = $customerData['complement'];
            }
            
            if (isset($customerData['province'])) {
                $data['province'] = $customerData['province'];
            }
            
            if (isset($customerData['postalCode'])) {
                $data['postalCode'] = $customerData['postalCode'];
            }
            
            $response = $this->makeRequest('/customers', 'POST', $data);
            
            $this->log("Cliente criado - ID: " . $response['id']);
            return $response;
            
        } catch (Exception $e) {
            $this->log("Erro ao criar cliente: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Busca informações de um pagamento
     */
    public function getPayment($paymentId) {
        try {
            return $this->makeRequest("/payments/{$paymentId}");
        } catch (Exception $e) {
            $this->log("Erro ao buscar pagamento {$paymentId}: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Lista pagamentos
     */
    public function listPayments($filters = []) {
        try {
            $queryString = '';
            if (!empty($filters)) {
                $queryString = '?' . http_build_query($filters);
            }
            
            return $this->makeRequest("/payments{$queryString}");
        } catch (Exception $e) {
            $this->log("Erro ao listar pagamentos: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Sincroniza contas do ASAAS com o banco local
     */
    public function syncAccountsFromAsaas() {
        try {
            $this->log("Iniciando sincronização de contas do ASAAS");
            
            $db = DatabaseManager::getInstance();
            $offset = 0;
            $limit = 100;
            $totalSynced = 0;
            
            do {
                $response = $this->listAccounts($limit, $offset);
                $accounts = $response['data'] ?? [];
                
                foreach ($accounts as $account) {
                    // Salvar/atualizar conta no banco local
                    $saved = $db->saveSplitAccount($account);
                    if ($saved) {
                        $totalSynced++;
                        $this->log("Conta sincronizada: " . $account['name'] . " (ID: " . $account['id'] . ")");
                    }
                }
                
                $offset += $limit;
                
            } while (count($accounts) === $limit && $response['hasMore']);
            
            $this->log("Sincronização concluída. {$totalSynced} contas sincronizadas.");
            
            return [
                'success' => true,
                'total_synced' => $totalSynced,
                'message' => "Sincronização concluída. {$totalSynced} contas sincronizadas."
            ];
            
        } catch (Exception $e) {
            $this->log("Erro na sincronização: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Processa webhook do ASAAS
     */
    public function processWebhook($webhookData) {
        try {
            $this->log("Processando webhook - Evento: " . $webhookData['event']);
            
            switch ($webhookData['event']) {
                case 'PAYMENT_RECEIVED':
                    return $this->handlePaymentReceived($webhookData['payment']);
                    break;
                    
                case 'PAYMENT_OVERDUE':
                    return $this->handlePaymentOverdue($webhookData['payment']);
                    break;
                    
                case 'PAYMENT_DELETED':
                    return $this->handlePaymentDeleted($webhookData['payment']);
                    break;
                    
                case 'PAYMENT_RESTORED':
                    return $this->handlePaymentRestored($webhookData['payment']);
                    break;
                    
                default:
                    $this->log("Evento não tratado: " . $webhookData['event'], 'WARNING');
                    return ['status' => 'ignored'];
            }
            
        } catch (Exception $e) {
            $this->log("Erro ao processar webhook: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Trata pagamento recebido
     */
    private function handlePaymentReceived($payment) {
        $this->log("Pagamento recebido - ID: " . $payment['id'] . " - Valor: R$ " . $payment['value']);
        
        // Aqui você pode implementar sua lógica específica
        // Exemplo: atualizar banco de dados, enviar email, etc.
        
        return ['status' => 'processed', 'action' => 'payment_received'];
    }
    
    /**
     * Trata pagamento vencido
     */
    private function handlePaymentOverdue($payment) {
        $this->log("Pagamento vencido - ID: " . $payment['id'], 'WARNING');
        
        // Implementar lógica para pagamentos vencidos
        
        return ['status' => 'processed', 'action' => 'payment_overdue'];
    }
    
    /**
     * Trata pagamento deletado
     */
    private function handlePaymentDeleted($payment) {
        $this->log("Pagamento deletado - ID: " . $payment['id'], 'WARNING');
        
        // Implementar lógica para pagamentos deletados
        
        return ['status' => 'processed', 'action' => 'payment_deleted'];
    }
    
    /**
     * Trata pagamento restaurado
     */
    private function handlePaymentRestored($payment) {
        $this->log("Pagamento restaurado - ID: " . $payment['id']);
        
        // Implementar lógica para pagamentos restaurados
        
        return ['status' => 'processed', 'action' => 'payment_restored'];
    }
    
    /**
     * Valida webhook (verificação de segurança)
     */
    public function validateWebhook($payload, $signature, $webhookToken) {
        $expectedSignature = hash_hmac('sha256', $payload, $webhookToken);
        
        if (!hash_equals($expectedSignature, $signature)) {
            $this->log("Webhook com assinatura inválida", 'ERROR');
            return false;
        }
        
        return true;
    }
    
    /**
     * Gera relatório de splits
     */
    public function getSplitReport($startDate, $endDate) {
        try {
            $filters = [
                'dateCreated[ge]' => $startDate,
                'dateCreated[le]' => $endDate,
                'status' => 'RECEIVED'
            ];
            
            $payments = $this->listPayments($filters);
            
            $report = [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'total_payments' => 0,
                'total_value' => 0,
                'splits' => []
            ];
            
            foreach ($payments['data'] as $payment) {
                if (isset($payment['split']) && !empty($payment['split'])) {
                    $report['total_payments']++;
                    $report['total_value'] += $payment['value'];
                    
                    foreach ($payment['split'] as $split) {
                        $walletId = $split['walletId'];
                        if (!isset($report['splits'][$walletId])) {
                            $report['splits'][$walletId] = [
                                'wallet_id' => $walletId,
                                'total_received' => 0,
                                'payment_count' => 0
                            ];
                        }
                        
                        $splitValue = isset($split['fixedValue']) ? 
                            $split['fixedValue'] : 
                            ($payment['value'] * $split['percentualValue'] / 100);
                            
                        $report['splits'][$walletId]['total_received'] += $splitValue;
                        $report['splits'][$walletId]['payment_count']++;
                    }
                }
            }
            
            $this->log("Relatório gerado - Período: {$startDate} a {$endDate}");
            return $report;
            
        } catch (Exception $e) {
            $this->log("Erro ao gerar relatório: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * ===== NOVOS MÉTODOS PARA RELATÓRIOS DE PARCELAMENTO =====
     */
    
    /**
     * Gerar relatório específico de parcelamentos
     */
    public function getInstallmentReport($startDate, $endDate) {
        try {
            $this->log("Gerando relatório de parcelamentos - Período: {$startDate} a {$endDate}");
            
            $filters = [
                'dateCreated[ge]' => $startDate,
                'dateCreated[le]' => $endDate,
                'installmentCount[ge]' => 2 // Apenas parcelados
            ];
            
            $payments = $this->listPayments($filters);
            
            $report = [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'total_installments' => 0,
                'total_value' => 0,
                'installment_details' => []
            ];
            
            foreach ($payments['data'] as $payment) {
                if (isset($payment['installment']) && isset($payment['installmentCount'])) {
                    $installmentId = $payment['installment'];
                    
                    if (!isset($report['installment_details'][$installmentId])) {
                        $report['installment_details'][$installmentId] = [
                            'installment_id' => $installmentId,
                            'customer_name' => $payment['customer']['name'] ?? 'N/A',
                            'installment_count' => $payment['installmentCount'],
                            'installment_value' => $payment['value'],
                            'total_value' => $payment['installmentCount'] * $payment['value'],
                            'created_at' => $payment['dateCreated'],
                            'due_date' => $payment['dueDate'],
                            'description' => $payment['description'],
                            'billing_type' => $payment['billingType'],
                            'has_splits' => !empty($payment['split'])
                        ];
                        
                        $report['total_installments']++;
                        $report['total_value'] += $payment['installmentCount'] * $payment['value'];
                    }
                }
            }
            
            $this->log("Relatório de parcelamentos gerado - {$report['total_installments']} parcelamentos encontrados");
            return $report;
            
        } catch (Exception $e) {
            $this->log("Erro ao gerar relatório de parcelamentos: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
}

/**
 * ===== CLASSE DE UTILIDADES PARA PARCELAMENTO =====
 */
class InstallmentHelper {
    
    /**
     * Validar dados de parcelamento
     */
    public static function validateInstallmentData($installmentData) {
        $errors = [];
        
        // Validar número de parcelas
        $installmentCount = (int)($installmentData['installmentCount'] ?? 0);
        if ($installmentCount < 2) {
            $errors[] = 'Número de parcelas deve ser maior que 1';
        }
        if ($installmentCount > 24) {
            $errors[] = 'Número máximo de parcelas é 24';
        }
        
        // Validar valor da parcela
        $installmentValue = (float)($installmentData['installmentValue'] ?? 0);
        if ($installmentValue <= 0) {
            $errors[] = 'Valor da parcela deve ser maior que zero';
        }
        
        // Validar data de vencimento
        if (empty($installmentData['dueDate'])) {
            $errors[] = 'Data de vencimento é obrigatória';
        } else {
            $dueDate = strtotime($installmentData['dueDate']);
            if ($dueDate < strtotime('today')) {
                $errors[] = 'Data de vencimento não pode ser anterior a hoje';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Formatar dados de parcelamento para exibição
     */
    public static function formatInstallmentData($installmentData) {
        return [
            'parcelas' => $installmentData['installmentCount'] . 'x',
            'valor_parcela' => 'R$ ' . number_format($installmentData['installmentValue'], 2, ',', '.'),
            'valor_total' => 'R$ ' . number_format($installmentData['installmentCount'] * $installmentData['installmentValue'], 2, ',', '.'),
            'primeiro_vencimento' => date('d/m/Y', strtotime($installmentData['dueDate'])),
            'tipo_cobranca' => $installmentData['billingType'] ?? 'BOLETO'
        ];
    }
    
    /**
     * Calcular datas de vencimento mensais
     */
    public static function calculateMonthlyDueDates($firstDueDate, $installmentCount) {
        $dueDates = [];
        $currentDate = new DateTime($firstDueDate);
        
        for ($i = 0; $i < $installmentCount; $i++) {
            $dueDates[] = [
                'parcela' => $i + 1,
                'vencimento' => $currentDate->format('Y-m-d'),
                'vencimento_formatado' => $currentDate->format('d/m/Y'),
                'mes_ano' => $currentDate->format('m/Y'),
                'mes_nome' => self::getMonthName($currentDate->format('n')),
                'ano' => $currentDate->format('Y')
            ];
            
            // Avançar para o próximo mês, mantendo o mesmo dia
            $currentDate->add(new DateInterval('P1M'));
        }
        
        return $dueDates;
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
     * Gerar descrição automática para mensalidades
     */
    public static function generateInstallmentDescriptions($baseDescription, $installmentCount, $startDate) {
        $descriptions = [];
        $currentDate = new DateTime($startDate);
        
        for ($i = 1; $i <= $installmentCount; $i++) {
            $monthYear = $currentDate->format('m/Y');
            $monthName = self::getMonthName($currentDate->format('n'));
            
            $descriptions[] = [
                'parcela' => $i,
                'description' => $baseDescription . " - {$i}ª parcela ({$monthName}/{$currentDate->format('Y')})",
                'month_year' => $monthYear,
                'due_date' => $currentDate->format('Y-m-d')
            ];
            
            $currentDate->add(new DateInterval('P1M'));
        }
        
        return $descriptions;
    }
    
    /**
     * Validar se data é válida para primeiro vencimento
     */
    public static function validateFirstDueDate($dueDate) {
        $today = new DateTime();
        $inputDate = new DateTime($dueDate);
        
        // Não pode ser anterior a hoje
        if ($inputDate < $today) {
            return [
                'valid' => false,
                'error' => 'Data de vencimento não pode ser anterior a hoje'
            ];
        }
        
        // Não pode ser muito distante (máximo 1 ano)
        $maxDate = clone $today;
        $maxDate->add(new DateInterval('P1Y'));
        
        if ($inputDate > $maxDate) {
            return [
                'valid' => false,
                'error' => 'Data de vencimento muito distante (máximo 1 ano)'
            ];
        }
        
        return ['valid' => true];
    }
}

/**
 * ===== EXEMPLO DE USO DO SISTEMA COM PARCELAMENTO =====
 */
class InstallmentExampleUsage {
    
    public static function exemploMensalidadeAluno() {
        try {
            echo "=== EXEMPLO: MENSALIDADE DE ALUNO ===\n";
            
            // Configurar sistema
            $asaas = AsaasConfig::getInstance('sandbox');
            
            // 1. DADOS DO ALUNO (CLIENTE)
            $cliente = [
                'name' => 'DENISE MIRANDA',
                'email' => 'denise.miranda@exemplo.com',
                'cpfCnpj' => '12345678901',
                'mobilePhone' => '11987654321'
            ];
            
            // 2. CRIAR CLIENTE NO ASAAS
            $customerCreated = $asaas->createCustomer($cliente);
            echo "Cliente criado: " . $customerCreated['id'] . "\n";
            
            // 3. DADOS DA MENSALIDADE
            $mensalidade = [
                'customer' => $customerCreated['id'],
                'billingType' => 'BOLETO', // ou 'PIX'
                'description' => 'Mensalidade Escolar 2025',
                'dueDate' => '2025-09-05' // Data do primeiro vencimento
            ];
            
            // 4. DADOS DO PARCELAMENTO
            $parcelamento = [
                'installmentCount' => 24,    // 24 parcelas
                'installmentValue' => 100.00, // R$ 100,00 cada
                'description_suffix' => 'Curso Técnico'
            ];
            
            // 5. CONFIGURAÇÃO DO SPLIT (será aplicado em todas as parcelas)
            $splits = [
                [
                    'walletId' => '22e49670-27e4-4579-a4c1-205c8a40497c',
                    'percentualValue' => 15.00 // 15% para a escola
                ],
                [
                    'walletId' => '33f59780-38f5-5680-b5d2-306d9b50b8e4',
                    'fixedValue' => 5.00 // R$ 5,00 fixo para sistema
                ]
            ];
            
            // 6. CRIAR MENSALIDADE PARCELADA
            echo "Criando mensalidade parcelada...\n";
            $resultado = $asaas->createInstallmentPaymentWithSplit(
                $mensalidade, 
                $splits, 
                $parcelamento
            );
            
            // 7. RESULTADO
            echo "✅ MENSALIDADE CRIADA COM SUCESSO!\n";
            echo "ID do Parcelamento: " . $resultado['installment'] . "\n";
            echo "Primeira Parcela ID: " . $resultado['id'] . "\n";
            echo "Total: " . $resultado['installment_info']['installment_count'] . " parcelas de R$ " . 
                 number_format($resultado['installment_info']['installment_value'], 2, ',', '.') . "\n";
            echo "Valor Total: R$ " . number_format($resultado['installment_info']['total_value'], 2, ',', '.') . "\n";
            echo "Primeiro Vencimento: " . date('d/m/Y', strtotime($resultado['installment_info']['first_due_date'])) . "\n";
            
            // 8. GERAR CARNÊ EM PDF
            echo "\nGerando carnê em PDF...\n";
            $carne = $asaas->generateInstallmentPaymentBook($resultado['installment']);
            
            if ($carne['success']) {
                // Salvar PDF
                $pdfFile = __DIR__ . '/carne_denise_miranda.pdf';
                file_put_contents($pdfFile, $carne['pdf_content']);
                echo "Carnê salvo em: " . $pdfFile . "\n";
            }
            
            // 9. CALCULAR DATAS DE VENCIMENTO
            echo "\nDatas de Vencimento:\n";
            $datas = $asaas->calculateInstallmentDueDates($mensalidade['dueDate'], $parcelamento['installmentCount']);
            
            foreach (array_slice($datas, 0, 5) as $data) { // Mostrar apenas as 5 primeiras
                echo "Parcela {$data['installment']}: {$data['formatted_date']} ({$data['month_year']})\n";
            }
            echo "... e mais " . ($parcelamento['installmentCount'] - 5) . " parcelas\n";
            
            return $resultado;
            
        } catch (Exception $e) {
            echo "❌ ERRO: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public static function exemploListarParcelas($installmentId) {
        try {
            echo "\n=== LISTANDO TODAS AS PARCELAS ===\n";
            
            $asaas = AsaasConfig::getInstance('sandbox');
            
            // Buscar todas as parcelas do parcelamento
            $parcelas = $asaas->getInstallmentPayments($installmentId);
            
            echo "Total de parcelas: " . count($parcelas['data']) . "\n\n";
            
            foreach ($parcelas['data'] as $index => $parcela) {
                $numero = $index + 1;
                echo "Parcela {$numero}:\n";
                echo "  ID: " . $parcela['id'] . "\n";
                echo "  Valor: R$ " . number_format($parcela['value'], 2, ',', '.') . "\n";
                echo "  Vencimento: " . date('d/m/Y', strtotime($parcela['dueDate'])) . "\n";
                echo "  Status: " . $parcela['status'] . "\n";
                echo "  Link: " . ($parcela['invoiceUrl'] ?? 'N/A') . "\n";
                echo "\n";
            }
            
        } catch (Exception $e) {
            echo "❌ ERRO: " . $e->getMessage() . "\n";
        }
    }
}

// Exemplo de classe para relatórios específicos de mensalidades
class InstallmentReports {
    
    public static function relatorioMensalidades($startDate, $endDate) {
        try {
            $asaas = AsaasConfig::getInstance();
            $report = $asaas->getInstallmentReport($startDate, $endDate);
            
            echo "=== RELATÓRIO DE MENSALIDADES/PARCELAMENTOS ===\n";
            echo "Período: {$report['period']['start']} a {$report['period']['end']}\n";
            echo "Total de Parcelamentos: {$report['total_installments']}\n";
            echo "Valor Total: R$ " . number_format($report['total_value'], 2, ',', '.') . "\n\n";
            
            foreach ($report['installment_details'] as $installment) {
                echo "Cliente: {$installment['customer_name']}\n";
                echo "Parcelas: {$installment['installment_count']}x de R$ " . 
                     number_format($installment['installment_value'], 2, ',', '.') . "\n";
                echo "Total: R$ " . number_format($installment['total_value'], 2, ',', '.') . "\n";
                echo "Tipo: {$installment['billing_type']}\n";
                echo "Splits: " . ($installment['has_splits'] ? 'Sim' : 'Não') . "\n";
                echo "Descrição: {$installment['description']}\n";
                echo "---\n";
            }
            
            return $report;
            
        } catch (Exception $e) {
            echo "❌ ERRO: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Descomente para testar
// InstallmentExampleUsage::exemploMensalidadeAluno();

?>