<?php

/**
 * Sistema de Split de Pagamentos - ASAAS
 * Versão para Produção
 * 
 * Autor: Sistema de Pagamentos
 * Data: 2025
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
                'User-Agent: ASAAS-Split-System/1.0 (PHP/' . PHP_VERSION . ')'
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
     * Verifica se um email já está em uso no ASAAS
     */
    public function checkEmailExists($email) {
        try {
            $this->log("Verificando se email existe: {$email}");
            
            // Tentar buscar por email nas contas
            $response = $this->makeRequest("/accounts?email=" . urlencode($email));
            
            return [
                'exists' => $response['totalCount'] > 0,
                'accounts' => $response['data'] ?? [],
                'total' => $response['totalCount'] ?? 0
            ];
            
        } catch (Exception $e) {
            $this->log("Erro ao verificar email: " . $e->getMessage(), 'ERROR');
            return [
                'exists' => false,
                'accounts' => [],
                'total' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cria uma conta com verificação prévia de email
     */
    public function createAccountWithCheck($accountData) {
        try {
            $this->log("Iniciando criação de conta com verificação para: " . $accountData['name']);
            
            // Primeiro, verificar se o email já existe
            $emailCheck = $this->checkEmailExists($accountData['email']);
            
            if ($emailCheck['exists']) {
                $this->log("Email já existe no ASAAS. Total encontrado: " . $emailCheck['total']);
                
                // Retornar informações sobre contas existentes
                return [
                    'success' => false,
                    'error_type' => 'email_exists',
                    'message' => "O email {$accountData['email']} já está cadastrado no ASAAS.",
                    'existing_accounts' => $emailCheck['accounts'],
                    'suggestions' => $this->generateEmailSuggestions($accountData['name'], $accountData['email'])
                ];
            }
            
            // Se não existe, criar normalmente
            return $this->createAccount($accountData);
            
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Se erro contém "já está em uso", fornecer sugestões
            if (strpos($errorMessage, 'já está em uso') !== false || 
                strpos($errorMessage, 'already in use') !== false) {
                
                return [
                    'success' => false,
                    'error_type' => 'email_exists',
                    'message' => "Email já está em uso por outro usuário do ASAAS.",
                    'suggestions' => $this->generateEmailSuggestions($accountData['name'], $accountData['email'])
                ];
            }
            
            // Outros erros, repassar normalmente
            throw $e;
        }
    }
    
    /**
     * Gera sugestões de email baseadas no nome e email original
     */
    private function generateEmailSuggestions($name, $originalEmail) {
        $emailParts = explode('@', $originalEmail);
        $localPart = $emailParts[0];
        $domain = isset($emailParts[1]) ? $emailParts[1] : 'empresa.com.br';
        
        // Limpar nome para usar como base
        $cleanName = strtolower($name);
        $cleanName = preg_replace('/[^a-z0-9\s]/', '', $cleanName);
        $cleanName = preg_replace('/\s+/', '', $cleanName);
        $cleanName = substr($cleanName, 0, 15);
        
        $suggestions = [
            $localPart . '1@' . $domain,
            $localPart . '2@' . $domain,
            $localPart . '.alt@' . $domain,
            'financeiro@' . $domain,
            'contato@' . $domain,
            'admin@' . $domain,
            $cleanName . '@' . $domain,
            $cleanName . '.adm@' . $domain,
            $localPart . '@gmail.com',
            $localPart . '@outlook.com'
        ];
        
        // Remover duplicatas e email original
        $suggestions = array_unique($suggestions);
        $suggestions = array_filter($suggestions, function($email) use ($originalEmail) {
            return $email !== $originalEmail;
        });
        
        return array_values($suggestions);
    }
    
    /**
     * Busca uma conta específica
     */
    public function getAccount($accountId) {
        try {
            $endpoint = "/accounts/{$accountId}";
            return $this->makeRequest($endpoint);
        } catch (Exception $e) {
            $this->log("Erro ao buscar conta {$accountId}: " . $e->getMessage(), 'ERROR');
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
     * Cria cobrança com split de pagamento
     */
    public function createPaymentWithSplit($paymentData, $splitData) {
        try {
            $this->log("Iniciando criação de pagamento com split - Valor: R$ " . $paymentData['value']);
            
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
            
            $data = [
                'customer' => $paymentData['customer'],
                'billingType' => $paymentData['billingType'],
                'dueDate' => $paymentData['dueDate'],
                'value' => $paymentData['value'],
                'description' => $paymentData['description'],
                'split' => $splitData
            ];
            
            // Adicionar campos opcionais
            if (isset($paymentData['installmentCount'])) {
                $data['installmentCount'] = $paymentData['installmentCount'];
            }
            
            if (isset($paymentData['installmentValue'])) {
                $data['installmentValue'] = $paymentData['installmentValue'];
            }
            
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
            
            $this->log("Pagamento criado com sucesso - ID: " . $response['id']);
            return $response;
            
        } catch (Exception $e) {
            $this->log("Erro ao criar pagamento: " . $e->getMessage(), 'ERROR');
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
}

// Classe para configuração do sistema
class AsaasConfig {
    
    // Configurações de ambiente - CORRIGIDO para usar as constantes
    const PRODUCTION_API_KEY = ASAAS_PRODUCTION_API_KEY;
    const SANDBOX_API_KEY = ASAAS_SANDBOX_API_KEY;
    const WEBHOOK_TOKEN = ASAAS_WEBHOOK_TOKEN;
    
    // Configurações do banco de dados (opcional)
    const DB_HOST = DB_HOST;
    const DB_NAME = DB_NAME;
    const DB_USER = DB_USER;
    const DB_PASS = DB_PASS;
    
    /**
     * Retorna instância configurada do sistema
     */
    public static function getInstance($environment = null) {
        // Usar ambiente definido nas configurações se não especificado
        if ($environment === null) {
            $environment = defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'sandbox';
        }
        
        // Usar as constantes definidas no config.php
        if ($environment === 'production') {
            $apiKey = defined('ASAAS_PRODUCTION_API_KEY') ? ASAAS_PRODUCTION_API_KEY : null;
        } else {
            $apiKey = defined('ASAAS_SANDBOX_API_KEY') ? ASAAS_SANDBOX_API_KEY : null;
        }
        
        // Verificar se a API Key foi configurada
        if (empty($apiKey) || 
            $apiKey === 'SUA_API_KEY_PRODUCAO_AQUI' || 
            $apiKey === 'SUA_API_KEY_SANDBOX_AQUI' ||
            $apiKey === '$aact_YTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1' ||
            $apiKey === '$aact_MTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1') {
            throw new Exception("API Key não configurada para ambiente '{$environment}'. Edite o arquivo config_api.php e configure sua chave do ASAAS.");
        }
        
        return new AsaasSplitPayment($apiKey, $environment);
    }
}

// Exemplo de uso do sistema
class ExampleUsage {
    
    public static function exemploCompleto() {
        try {
            // Inicializar sistema
            $asaas = AsaasConfig::getInstance('sandbox'); // Mudar para 'production'
            
            // 1. Criar cliente
            $customer = $asaas->createCustomer([
                'name' => 'João Silva',
                'email' => 'joao@exemplo.com',
                'cpfCnpj' => '12345678901',
                'mobilePhone' => '11987654321',
                'address' => 'Rua das Flores, 123',
                'addressNumber' => '123',
                'province' => 'Centro',
                'postalCode' => '12345-678'
            ]);
            
            echo "Cliente criado: " . $customer['id'] . "\n";
            
            // 2. Criar contas para split (se necessário)
            $account1 = $asaas->createAccount([
                'name' => 'Parceiro 1',
                'email' => 'parceiro1@exemplo.com',
                'cpfCnpj' => '98765432100',
                'mobilePhone' => '11876543210',
                'address' => 'Rua do Comércio, 456',
                'province' => 'Comercial',
                'postalCode' => '54321-987'
            ]);
            
            echo "Conta 1 criada: " . $account1['id'] . "\n";
            
            // 3. Criar pagamento com split
            $payment = $asaas->createPaymentWithSplit(
                // Dados do pagamento
                [
                    'customer' => $customer['id'],
                    'billingType' => 'BOLETO', // PIX, CREDIT_CARD, DEBIT_CARD
                    'dueDate' => date('Y-m-d', strtotime('+7 days')),
                    'value' => 100.00,
                    'description' => 'Venda com split de pagamento'
                ],
                // Configuração do split
                [
                    [
                        'walletId' => $account1['walletId'],
                        'percentualValue' => 30.00 // 30% para o parceiro
                    ],
                    [
                        'walletId' => 'WALLET_ID_PRINCIPAL',
                        'percentualValue' => 70.00 // 70% para a conta principal
                    ]
                ]
            );
            
            echo "Pagamento criado: " . $payment['id'] . "\n";
            echo "Link para pagamento: " . $payment['invoiceUrl'] . "\n";
            
            return $payment;
            
        } catch (Exception $e) {
            echo "Erro: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public static function exemploWebhook() {
        try {
            $asaas = AsaasConfig::getInstance('production');
            
            // Simular dados de webhook
            $webhookData = [
                'event' => 'PAYMENT_RECEIVED',
                'payment' => [
                    'id' => 'pay_123456789',
                    'value' => 100.00,
                    'status' => 'RECEIVED'
                ]
            ];
            
            $result = $asaas->processWebhook($webhookData);
            echo "Webhook processado: " . json_encode($result) . "\n";
            
        } catch (Exception $e) {
            echo "Erro no webhook: " . $e->getMessage() . "\n";
        }
    }
    
    public static function exemploRelatorio() {
        try {
            $asaas = AsaasConfig::getInstance('production');
            
            $report = $asaas->getSplitReport('2025-01-01', '2025-01-31');
            
            echo "=== RELATÓRIO DE SPLITS ===\n";
            echo "Período: " . $report['period']['start'] . " a " . $report['period']['end'] . "\n";
            echo "Total de pagamentos: " . $report['total_payments'] . "\n";
            echo "Valor total: R$ " . number_format($report['total_value'], 2, ',', '.') . "\n\n";
            
            foreach ($report['splits'] as $split) {
                echo "Wallet: " . $split['wallet_id'] . "\n";
                echo "Valor recebido: R$ " . number_format($split['total_received'], 2, ',', '.') . "\n";
                echo "Quantidade de pagamentos: " . $split['payment_count'] . "\n\n";
            }
            
        } catch (Exception $e) {
            echo "Erro no relatório: " . $e->getMessage() . "\n";
        }
    }
}

// Script para processar webhook (webhook.php)
class WebhookHandler {
    
    public static function handle() {
        // Verificar se é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed');
        }
        
        // Capturar dados
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_ASAAS_SIGNATURE'] ?? '';
        
        if (empty($payload) || empty($signature)) {
            http_response_code(400);
            exit('Invalid webhook data');
        }
        
        try {
            $asaas = AsaasConfig::getInstance('production');
            
            // Validar webhook
            if (!$asaas->validateWebhook($payload, $signature, AsaasConfig::WEBHOOK_TOKEN)) {
                http_response_code(401);
                exit('Unauthorized');
            }
            
            // Processar webhook
            $webhookData = json_decode($payload, true);
            $result = $asaas->processWebhook($webhookData);
            
            // Responder com sucesso
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode($result);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo 'Erro interno: ' . $e->getMessage();
        }
    }
}

// Descomente para testar
// ExampleUsage::exemploCompleto();

?>