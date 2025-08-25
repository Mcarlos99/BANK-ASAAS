<?php
/**
 * API Endpoint Multi-Tenant para funcionalidades AJAX - COM PARCELAMENTO
 * Arquivo: api.php
 * Versão com suporte a múltiplos polos E mensalidades parceladas
 */

require_once 'bootstrap.php';

// Configurar headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Processar requisição OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar autenticação para todas as ações
$auth = new AuthSystem();
if (!$auth->isLogado()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Usuário não autenticado',
        'redirect' => 'login.php'
    ]);
    exit();
}

$usuario = $auth->getUsuarioAtual();
$configManager = new ConfigManager();

// Função para resposta JSON
function jsonResponse($success, $data = null, $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error,
        'timestamp' => time(),
        'user_context' => [
            'tipo' => $_SESSION['usuario_tipo'] ?? 'unknown',
            'polo_id' => $_SESSION['polo_id'] ?? null
        ]
    ]);
    exit();
}

// Obter ação da requisição
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // ===== CONFIGURAÇÕES DE POLO (MANTIDAS) =====
        
        case 'get-polo-config':
            $auth->requirePermission('configurar_polo');
            
            $poloId = (int)($_GET['polo_id'] ?? $_SESSION['polo_id']);
            
            if (!$auth->isMaster() && $poloId !== $_SESSION['polo_id']) {
                jsonResponse(false, null, "Acesso negado a este polo");
            }
            
            $config = $configManager->getPoloConfig($poloId);
            
            // Mascarar chaves para segurança
            if (!empty($config['asaas_production_api_key'])) {
                $config['asaas_production_api_key_masked'] = substr($config['asaas_production_api_key'], 0, 20) . '...' . substr($config['asaas_production_api_key'], -8);
                unset($config['asaas_production_api_key']);
            }
            
            if (!empty($config['asaas_sandbox_api_key'])) {
                $config['asaas_sandbox_api_key_masked'] = substr($config['asaas_sandbox_api_key'], 0, 20) . '...' . substr($config['asaas_sandbox_api_key'], -8);
                unset($config['asaas_sandbox_api_key']);
            }
            
            jsonResponse(true, $config, "Configuração do polo obtida");
            break;
            
        case 'update-polo-config':
            $auth->requirePermission('configurar_polo');
            
            $poloId = (int)$_POST['polo_id'];
            $config = [
                'environment' => $_POST['environment'],
                'production_key' => trim($_POST['production_key'] ?? ''),
                'sandbox_key' => trim($_POST['sandbox_key'] ?? ''),
                'webhook_token' => trim($_POST['webhook_token'] ?? '')
            ];
            
            $configManager->updateAsaasConfig($poloId, $config);
            
            jsonResponse(true, null, "Configurações atualizadas com sucesso");
            break;
            
        case 'test-polo-config':
            $auth->requirePermission('configurar_polo');
            
            $poloId = (int)$_POST['polo_id'];
            $environment = $_POST['environment'] ?? null;
            
            $result = $configManager->testAsaasConfig($poloId, $environment);
            
            if ($result['success']) {
                jsonResponse(true, $result['data'], $result['message']);
            } else {
                jsonResponse(false, null, $result['message']);
            }
            break;
            
        // ===== WALLET IDs COM FILTRO POR POLO (MANTIDAS) =====
        
        case 'create-wallet':
            try {
                $name = $_POST['name'] ?? '';
                $walletId = $_POST['wallet_id'] ?? '';
                $description = $_POST['description'] ?? null;
                
                if (empty($name) || empty($walletId)) {
                    jsonResponse(false, null, "Nome e Wallet ID são obrigatórios");
                }
                
                // Validar formato do Wallet ID
                if (!ValidationHelper::isValidWalletId($walletId)) {
                    jsonResponse(false, null, "Formato de Wallet ID inválido. Use formato UUID (ex: 22e49670-27e4-4579-a4c1-205c8a40497c)");
                }
                
                $walletManager = new WalletManager();
                $wallet = $walletManager->createWallet($name, $walletId, $description);
                
                jsonResponse(true, $wallet, "Wallet ID cadastrado com sucesso!");
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'list-wallets':
            try {
                $page = (int)($_GET['page'] ?? 1);
                $limit = (int)($_GET['limit'] ?? 20);
                $search = $_GET['search'] ?? null;
                
                $walletManager = new WalletManager();
                $wallets = $walletManager->listWallets($page, $limit, $search);
                
                jsonResponse(true, $wallets, "Wallet IDs carregados");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao carregar Wallet IDs: " . $e->getMessage());
            }
            break;
            
        case 'get-wallet':
            try {
                $walletId = $_GET['wallet_id'] ?? '';
                
                if (empty($walletId)) {
                    jsonResponse(false, null, "Wallet ID é obrigatório");
                }
                
                $walletManager = new WalletManager();
                $wallet = $walletManager->getWalletWithStats($walletId);
                
                if (!$wallet) {
                    jsonResponse(false, null, "Wallet ID não encontrado");
                }
                
                jsonResponse(true, $wallet, "Wallet ID encontrado");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao buscar Wallet ID: " . $e->getMessage());
            }
            break;
            
        // ===== FUNCIONALIDADES EXISTENTES ADAPTADAS =====
        
        case 'create-customer':
            try {
                // Usar configuração dinâmica baseada no polo do usuário
                $dynamicAsaas = new DynamicAsaasConfig();
                $asaas = $dynamicAsaas->getInstance();
                
                $customer = $asaas->createCustomer($_POST['customer']);
                
                // Salvar no banco com polo_id
                $db = DatabaseManager::getInstance();
                $db->saveCustomer($customer);
                
                jsonResponse(true, $customer, 'Cliente criado com sucesso! ID: ' . $customer['id']);
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'create-account':
            try {
                // Usar configuração dinâmica baseada no polo do usuário
                $dynamicAsaas = new DynamicAsaasConfig();
                $asaas = $dynamicAsaas->getInstance();
                
                // Processar dados antes de enviar
                $accountData = $_POST['account'];
                
                // Limpar dados como no original
                $accountData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $accountData['cpfCnpj']);
                $accountData['mobilePhone'] = preg_replace('/[^0-9]/', '', $accountData['mobilePhone']);
                $accountData['postalCode'] = preg_replace('/[^0-9]/', '', $accountData['postalCode']);
                $accountData['incomeValue'] = (int)$accountData['incomeValue'];
                
                $account = $asaas->createAccount($accountData);
                
                // Salvar no banco com polo_id
                $db = DatabaseManager::getInstance();
                $db->saveSplitAccount($account);
                
                jsonResponse(true, $account, 'Conta de split criada! Wallet ID: ' . $account['walletId']);
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        // ===== NOVA FUNCIONALIDADE: CRIAR PAGAMENTO PARCELADO =====
        
        case 'create-installment-payment':
            try {
                // Usar configuração dinâmica baseada no polo do usuário
                $dynamicAsaas = new DynamicAsaasConfig();
                $asaas = $dynamicAsaas->getInstance();
                
                // Dados do pagamento base
                $paymentData = $_POST['payment'] ?? [];
                
                // Dados do parcelamento
                $installmentData = $_POST['installment'] ?? [];
                
                // Validações básicas
                if (empty($paymentData['customer'])) {
                    jsonResponse(false, null, 'Cliente é obrigatório');
                }
                
                if (empty($installmentData['installmentCount']) || $installmentData['installmentCount'] < 2) {
                    jsonResponse(false, null, 'Número de parcelas deve ser maior que 1');
                }
                
                if (empty($installmentData['installmentValue']) || $installmentData['installmentValue'] <= 0) {
                    jsonResponse(false, null, 'Valor da parcela deve ser maior que zero');
                }
                
                // Validar número máximo de parcelas
                if ($installmentData['installmentCount'] > 24) {
                    jsonResponse(false, null, 'Número máximo de parcelas é 24');
                }
                
                // Validar data de vencimento
                if (empty($paymentData['dueDate'])) {
                    jsonResponse(false, null, 'Data de vencimento é obrigatória');
                }
                
                if (strtotime($paymentData['dueDate']) < strtotime('today')) {
                    jsonResponse(false, null, 'Data de vencimento não pode ser anterior a hoje');
                }
                
                // Preparar dados dos splits
                $splits = [];
                if (isset($_POST['splits'])) {
                    foreach ($_POST['splits'] as $split) {
                        if (!empty($split['walletId'])) {
                            $splitData = ['walletId' => $split['walletId']];
                            
                            if (!empty($split['percentualValue'])) {
                                $splitData['percentualValue'] = floatval($split['percentualValue']);
                            }
                            if (!empty($split['fixedValue'])) {
                                $splitData['fixedValue'] = floatval($split['fixedValue']);
                            }
                            
                            $splits[] = $splitData;
                        }
                    }
                }
                
                // Usar nova função de parcelamento
                $payment = $asaas->createInstallmentPaymentWithSplit($paymentData, $splits, $installmentData);
                
                // Salvar no banco com polo_id
                $db = DatabaseManager::getInstance();
                $paymentSaveData = array_merge($payment, ['polo_id' => $usuario['polo_id']]);
                $db->savePayment($paymentSaveData);
                
                // Salvar informações do parcelamento
                if (!empty($splits)) {
                    $db->savePaymentSplits($payment['id'], $splits);
                }
                
                // Salvar dados específicos do parcelamento na nova tabela
                $installmentRecord = [
                    'installment_id' => $payment['installment'],
                    'polo_id' => $usuario['polo_id'],
                    'customer_id' => $payment['customer'],
                    'installment_count' => $installmentData['installmentCount'],
                    'installment_value' => $installmentData['installmentValue'],
                    'total_value' => $installmentData['installmentCount'] * $installmentData['installmentValue'],
                    'first_due_date' => $paymentData['dueDate'],
                    'billing_type' => $paymentData['billingType'],
                    'description' => $paymentData['description'],
                    'has_splits' => !empty($splits),
                    'splits_count' => count($splits),
                    'created_by' => $usuario['id'],
                    'first_payment_id' => $payment['id']
                ];
                
                $db->saveInstallmentRecord($installmentRecord);
                
                // Resposta com informações completas
                $responseMessage = "Mensalidade criada! " . 
                    $installmentData['installmentCount'] . " parcelas de R$ " . 
                    number_format($installmentData['installmentValue'], 2, ',', '.') . 
                    " (Total: R$ " . number_format($installmentRecord['total_value'], 2, ',', '.') . ")";
                
                if (!empty($payment['invoiceUrl'])) {
                    $responseMessage .= ' <a href="' . $payment['invoiceUrl'] . '" target="_blank" class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-eye"></i> Ver 1ª Parcela</a>';
                }
                
                jsonResponse(true, [
                    'payment' => $payment,
                    'installment_info' => $payment['installment_info'],
                    'installment_record' => $installmentRecord
                ], $responseMessage);
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        // ===== NOVA: BUSCAR PARCELAS DE UM PARCELAMENTO =====
        
        case 'get-installment-payments':
            try {
                $installmentId = $_GET['installment_id'] ?? '';
                
                if (empty($installmentId)) {
                    jsonResponse(false, null, 'ID do parcelamento é obrigatório');
                }
                
                // Usar configuração dinâmica
                $dynamicAsaas = new DynamicAsaasConfig();
                $asaas = $dynamicAsaas->getInstance();
                
                // Buscar todas as parcelas
                $payments = $asaas->getInstallmentPayments($installmentId);
                
                // Adicionar informações extras
                $db = DatabaseManager::getInstance();
                $installmentInfo = $db->getInstallmentInfo($installmentId);
                
                jsonResponse(true, [
                    'payments' => $payments,
                    'installment_info' => $installmentInfo,
                    'total_payments' => count($payments['data'] ?? [])
                ], "Parcelas carregadas com sucesso");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao buscar parcelas: " . $e->getMessage());
            }
            break;
            
        // ===== NOVA: GERAR CARNÊ EM PDF =====
        
        case 'generate-payment-book':
            try {
                $installmentId = $_POST['installment_id'] ?? '';
                
                if (empty($installmentId)) {
                    jsonResponse(false, null, 'ID do parcelamento é obrigatório');
                }
                
                // Usar configuração dinâmica
                $dynamicAsaas = new DynamicAsaasConfig();
                $asaas = $dynamicAsaas->getInstance();
                
                // Gerar carnê
                $paymentBook = $asaas->generateInstallmentPaymentBook($installmentId);
                
                if ($paymentBook['success']) {
                    // Salvar PDF temporariamente ou retornar URL
                    $fileName = 'carne_' . $installmentId . '_' . date('YmdHis') . '.pdf';
                    $filePath = __DIR__ . '/temp/' . $fileName;
                    
                    // Criar diretório temp se não existir
                    if (!is_dir(__DIR__ . '/temp')) {
                        mkdir(__DIR__ . '/temp', 0755, true);
                    }
                    
                    file_put_contents($filePath, $paymentBook['pdf_content']);
                    
                    jsonResponse(true, [
                        'file_name' => $fileName,
                        'file_path' => 'temp/' . $fileName,
                        'download_url' => 'download.php?file=' . urlencode($fileName),
                        'size' => strlen($paymentBook['pdf_content'])
                    ], "Carnê gerado com sucesso!");
                } else {
                    jsonResponse(false, null, "Erro ao gerar carnê");
                }
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao gerar carnê: " . $e->getMessage());
            }
            break;
            
        // ===== NOVA: CALCULAR DATAS DE VENCIMENTO =====
        
        case 'calculate-due-dates':
            try {
                $firstDueDate = $_GET['first_due_date'] ?? '';
                $installmentCount = (int)($_GET['installment_count'] ?? 0);
                
                if (empty($firstDueDate) || $installmentCount < 2 || $installmentCount > 24) {
                    jsonResponse(false, null, 'Dados inválidos para calcular datas');
                }
                
                // Validar data
                if (strtotime($firstDueDate) < strtotime('today')) {
                    jsonResponse(false, null, 'Data de vencimento não pode ser anterior a hoje');
                }
                
                // Usar configuração dinâmica
                $dynamicAsaas = new DynamicAsaasConfig();
                $asaas = $dynamicAsaas->getInstance();
                
                // Calcular datas
                $dueDates = $asaas->calculateInstallmentDueDates($firstDueDate, $installmentCount);
                
                jsonResponse(true, [
                    'due_dates' => $dueDates,
                    'first_due_date' => $firstDueDate,
                    'last_due_date' => end($dueDates)['due_date'],
                    'installment_count' => $installmentCount
                ], "Datas calculadas com sucesso");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao calcular datas: " . $e->getMessage());
            }
            break;
            
        // ===== FUNCIONALIDADE ORIGINAL MANTIDA PARA COMPATIBILIDADE =====
        
        case 'create-payment':
            try {
                // Usar configuração dinâmica baseada no polo do usuário
                $dynamicAsaas = new DynamicAsaasConfig();
                $asaas = $dynamicAsaas->getInstance();
                
                // Preparar dados do split
                $splits = [];
                if (isset($_POST['splits'])) {
                    foreach ($_POST['splits'] as $split) {
                        if (!empty($split['walletId'])) {
                            $splitData = ['walletId' => $split['walletId']];
                            
                            if (!empty($split['percentualValue'])) {
                                $splitData['percentualValue'] = floatval($split['percentualValue']);
                            }
                            if (!empty($split['fixedValue'])) {
                                $splitData['fixedValue'] = floatval($split['fixedValue']);
                            }
                            
                            $splits[] = $splitData;
                        }
                    }
                }
                
                $payment = $asaas->createPaymentWithSplit($_POST['payment'], $splits);
                
                // Salvar no banco com polo_id
                $db = DatabaseManager::getInstance();
                $db->savePayment($payment);
                if (!empty($splits)) {
                    $db->savePaymentSplits($payment['id'], $splits);
                }
                
                jsonResponse(true, $payment, 'Pagamento criado! <a href="' . $payment['invoiceUrl'] . '" target="_blank">Ver Cobrança</a>');
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        // ===== RELATÓRIOS ATUALIZADOS COM SUPORTE A PARCELAMENTOS =====
        
        case 'installment-report':
            try {
                $startDate = $_GET['start'] ?? date('Y-m-01');
                $endDate = $_GET['end'] ?? date('Y-m-d');
                
                // Usar configuração dinâmica
                $dynamicAsaas = new DynamicAsaasConfig();
                $asaas = $dynamicAsaas->getInstance();
                
                $report = $asaas->getInstallmentReport($startDate, $endDate);
                
                // Obter estatísticas do banco com filtro por polo
                $db = DatabaseManager::getInstance();
                
                // Para master, usar polo específico se fornecido
                $poloId = null;
                if ($auth->isMaster() && isset($_GET['polo_id'])) {
                    $poloId = (int)$_GET['polo_id'];
                } elseif (!$auth->isMaster()) {
                    $poloId = $_SESSION['polo_id'];
                }
                
                // Buscar dados locais de parcelamentos
                $localInstallments = $db->getInstallmentsByPeriod($startDate, $endDate, $poloId);
                
                $reportData = [
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'polo_context' => $poloId ? $_SESSION['polo_nome'] ?? 'Polo ID: ' . $poloId : 'Todos os polos',
                    'total_installments' => $report['total_installments'],
                    'total_value' => $report['total_value'],
                    'installments' => $report['installment_details'],
                    'local_data' => $localInstallments,
                    'summary' => [
                        'avg_installment_count' => 0,
                        'avg_installment_value' => 0,
                        'most_used_billing_type' => 'BOLETO'
                    ]
                ];
                
                // Calcular médias
                if ($report['total_installments'] > 0) {
                    $totalParcelas = 0;
                    $totalValorParcela = 0;
                    $billingTypes = [];
                    
                    foreach ($report['installment_details'] as $installment) {
                        $totalParcelas += $installment['installment_count'];
                        $totalValorParcela += $installment['installment_value'];
                        
                        $bt = $installment['billing_type'];
                        $billingTypes[$bt] = ($billingTypes[$bt] ?? 0) + 1;
                    }
                    
                    $reportData['summary']['avg_installment_count'] = round($totalParcelas / $report['total_installments'], 1);
                    $reportData['summary']['avg_installment_value'] = round($totalValorParcela / $report['total_installments'], 2);
                    $reportData['summary']['most_used_billing_type'] = array_key_first(arsort($billingTypes) ? $billingTypes : ['BOLETO' => 1]);
                }
                
                jsonResponse(true, ['report' => $reportData], "Relatório de parcelamentos gerado com sucesso");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao gerar relatório de parcelamentos: " . $e->getMessage());
            }
            break;
            
        // ===== OUTRAS FUNCIONALIDADES EXISTENTES MANTIDAS =====
        
        case 'test-api':
            try {
                // Usar configuração dinâmica baseada no polo do usuário
                $dynamicAsaas = new DynamicAsaasConfig();
                $asaas = $dynamicAsaas->getInstance();
                
                $response = $asaas->listAccounts(1, 0);
                
                $poloInfo = $auth->isMaster() ? 'Master' : $_SESSION['polo_nome'];
                
                jsonResponse(true, [
                    'polo' => $poloInfo,
                    'total_accounts' => $response['totalCount'],
                    'response_time' => 'OK',
                    'installment_support' => true // Nova funcionalidade
                ], "Conexão com API ASAAS estabelecida com sucesso");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na API ASAAS: " . $e->getMessage());
            }
            break;
            
        case 'sync-accounts':
            try {
                // Usar configuração dinâmica baseada no polo do usuário
                $dynamicAsaas = new DynamicAsaasConfig();
                $asaas = $dynamicAsaas->getInstance();
                
                $result = $asaas->syncAccountsFromAsaas();
                
                jsonResponse(true, $result, $result['message']);
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na sincronização: " . $e->getMessage());
            }
            break;
            
        // ===== RELATÓRIOS COM FILTRO POR POLO (MANTIDOS) =====
        
        case 'report':
            try {
                $startDate = $_GET['start'] ?? date('Y-m-01');
                $endDate = $_GET['end'] ?? date('Y-m-d');
                
                // Usar configuração dinâmica baseada no polo do usuário
                $dynamicAsaas = new DynamicAsaasConfig();
                $asaas = $dynamicAsaas->getInstance();
                
                $report = $asaas->getSplitReport($startDate, $endDate);
                
                // Obter estatísticas do banco com filtro por polo
                $db = DatabaseManager::getInstance();
                
                // Para master, usar polo específico se fornecido
                $poloId = null;
                if ($auth->isMaster() && isset($_GET['polo_id'])) {
                    $poloId = (int)$_GET['polo_id'];
                } elseif (!$auth->isMaster()) {
                    $poloId = $_SESSION['polo_id'];
                }
                
                // Total de pagamentos no período (filtrado por polo)
                $whereClause = "WHERE status = 'RECEIVED' AND received_date BETWEEN ? AND ?";
                $params = [$startDate, $endDate];
                
                if ($poloId) {
                    $whereClause .= " AND polo_id = ?";
                    $params[] = $poloId;
                }
                
                $stmt = $db->getConnection()->prepare("
                    SELECT COUNT(*) as total_payments, SUM(value) as total_value
                    FROM payments 
                    {$whereClause}
                ");
                $stmt->execute($params);
                $stats = $stmt->fetch();
                
                // Relatório de splits do banco (filtrado por polo)
                $splitReport = $db->getSplitReport($startDate, $endDate, $poloId);
                
                $reportData = [
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'polo_context' => $poloId ? $_SESSION['polo_nome'] ?? 'Polo ID: ' . $poloId : 'Todos os polos',
                    'total_payments' => $stats['total_payments'] ?? 0,
                    'total_value' => $stats['total_value'] ?? 0,
                    'splits' => []
                ];
                
                foreach ($splitReport as $split) {
                    $reportData['splits'][$split['wallet_id']] = [
                        'account_name' => $split['account_name'],
                        'wallet_id' => $split['wallet_id'],
                        'payment_count' => $split['payment_count'],
                        'total_received' => $split['total_received'],
                        'source_type' => $split['source_type'] ?? 'Desconhecido'
                    ];
                }
                
                jsonResponse(true, ['report' => $reportData], "Relatório gerado com sucesso");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao gerar relatório: " . $e->getMessage());
            }
            break;
            
        // ===== CONTINUE COM OUTRAS FUNCIONALIDADES EXISTENTES... =====
        // (Mantidas todas as outras funcionalidades como no arquivo original)
        
        case 'wallet-performance-report':
            try {
                $startDate = $_GET['start'] ?? date('Y-m-01');
                $endDate = $_GET['end'] ?? date('Y-m-d');
                
                $reports = new ReportsManager();
                
                // Filtrar por polo se necessário
                $poloId = null;
                if (!$auth->isMaster()) {
                    $poloId = $_SESSION['polo_id'];
                } elseif (isset($_GET['polo_id'])) {
                    $poloId = (int)$_GET['polo_id'];
                }
                
                $walletReport = $reports->getWalletPerformanceReport($startDate, $endDate, $poloId);
                
                jsonResponse(true, $walletReport, "Relatório de performance dos Wallet IDs gerado");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro no relatório de performance: " . $e->getMessage());
            }
            break;
            
        // ===== ESTATÍSTICAS COM FILTRO POR POLO =====
        
        case 'stats':
            try {
                // Usar estatísticas filtradas por polo
                $poloId = null;
                if (!$auth->isMaster()) {
                    $poloId = $_SESSION['polo_id'];
                } elseif (isset($_GET['polo_id'])) {
                    $poloId = (int)$_GET['polo_id'];
                }
                
                $stats = SystemStats::getGeneralStats($poloId);
                
                if ($stats) {
                    $stats['context'] = $poloId ? ($_SESSION['polo_nome'] ?? 'Polo ID: ' . $poloId) : 'Global';
                    $stats['installment_support'] = true; // Nova funcionalidade
                    jsonResponse(true, $stats, "Estatísticas obtidas com sucesso");
                } else {
                    jsonResponse(false, null, "Erro ao obter estatísticas");
                }
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro: " . $e->getMessage());
            }
            break;
            
        case 'dashboard-data':
            try {
                // Usar estatísticas filtradas
                $poloId = !$auth->isMaster() ? $_SESSION['polo_id'] : null;
                $stats = SystemStats::getGeneralStats($poloId);
                
                // Dados adicionais para gráficos (filtrados por polo)
                $db = DatabaseManager::getInstance();
                
                $whereClause = '';
                $params = [];
                if ($poloId) {
                    $whereClause = 'WHERE polo_id = ?';
                    $params = [$poloId];
                }
                
                // Pagamentos dos últimos 7 dias
                $stmt = $db->getConnection()->prepare("
                    SELECT DATE(created_at) as date, COUNT(*) as count, SUM(value) as total
                    FROM payments 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" . 
                    ($poloId ? " AND polo_id = ?" : "") . "
                    GROUP BY DATE(created_at)
                    ORDER BY date
                ");
                $stmt->execute($poloId ? [$poloId] : []);
                $weeklyData = $stmt->fetchAll();
                
                // Distribuição por tipo de pagamento
                $stmt = $db->getConnection()->prepare("
                    SELECT billing_type, COUNT(*) as count
                    FROM payments 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" .
                    ($poloId ? " AND polo_id = ?" : "") . "
                    GROUP BY billing_type
                ");
                $stmt->execute($poloId ? [$poloId] : []);
                $billingTypeData = $stmt->fetchAll();
                
                // Estatísticas de Wallet IDs (filtradas por polo)
                $stmt = $db->getConnection()->prepare("
                    SELECT 
                        wi.name,
                        COUNT(ps.id) as split_count,
                        SUM(CASE 
                            WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                            ELSE (p.value * ps.percentage_value / 100) 
                        END) as total_received
                    FROM wallet_ids wi
                    LEFT JOIN payment_splits ps ON wi.wallet_id = ps.wallet_id
                    LEFT JOIN payments p ON ps.payment_id = p.id AND p.status = 'RECEIVED'
                    WHERE wi.is_active = 1" . ($poloId ? " AND wi.polo_id = ?" : "") . "
                    GROUP BY wi.id
                    ORDER BY total_received DESC
                    LIMIT 5
                ");
                $stmt->execute($poloId ? [$poloId] : []);
                $topWallets = $stmt->fetchAll();
                
                // NOVA: Estatísticas de parcelamentos
                $stmt = $db->getConnection()->prepare("
                    SELECT 
                        COUNT(*) as total_installments,
                        SUM(installment_count) as total_parcelas,
                        SUM(total_value) as total_installment_value,
                        AVG(installment_count) as avg_parcelas,
                        AVG(installment_value) as avg_valor_parcela
                    FROM installments 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" .
                    ($poloId ? " AND polo_id = ?" : "")
                );
                $stmt->execute($poloId ? [$poloId] : []);
                $installmentStats = $stmt->fetch();
                
                jsonResponse(true, [
                    'stats' => $stats,
                    'weekly_data' => $weeklyData,
                    'billing_types' => $billingTypeData,
                    'top_wallets' => $topWallets,
                    'installment_stats' => $installmentStats, // NOVO
                    'context' => $poloId ? $_SESSION['polo_nome'] : 'Global'
                ], "Dados do dashboard carregados");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao carregar dashboard: " . $e->getMessage());
            }
            break;
            
        // ===== FUNCIONALIDADES DE POLO (MASTER ONLY) =====
        
        case 'list-polos':
            $auth->requirePermission('gerenciar_polos');
            
            try {
                $incluirInativos = isset($_GET['incluir_inativos']) && $_GET['incluir_inativos'] === 'true';
                $polos = $configManager->listarPolos($incluirInativos);
                
                jsonResponse(true, $polos, "Lista de polos obtida");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao listar polos: " . $e->getMessage());
            }
            break;
            
        case 'create-polo':
            $auth->requirePermission('gerenciar_polos');
            
            try {
                $dados = [
                    'nome' => trim($_POST['nome']),
                    'codigo' => strtoupper(trim($_POST['codigo'])),
                    'cidade' => trim($_POST['cidade']),
                    'estado' => strtoupper(trim($_POST['estado'])),
                    'endereco' => trim($_POST['endereco'] ?? ''),
                    'telefone' => trim($_POST['telefone'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'asaas_environment' => $_POST['environment'] ?? 'sandbox'
                ];
                
                $poloId = $configManager->createPolo($dados);
                
                jsonResponse(true, ['polo_id' => $poloId], "Polo criado com sucesso! ID: $poloId");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao criar polo: " . $e->getMessage());
            }
            break;
            
        case 'get-polo-stats':
            try {
                $poloId = (int)($_GET['polo_id'] ?? $_SESSION['polo_id']);
                
                if (!$auth->isMaster() && $poloId !== $_SESSION['polo_id']) {
                    jsonResponse(false, null, "Acesso negado a este polo");
                }
                
                $stats = $configManager->getPoloStats($poloId);
                
                // NOVA: Adicionar estatísticas de parcelamentos
                $db = DatabaseManager::getInstance();
                $stmt = $db->getConnection()->prepare("
                    SELECT 
                        COUNT(*) as installments_count,
                        SUM(total_value) as installments_value,
                        AVG(installment_count) as avg_installments
                    FROM installments 
                    WHERE polo_id = ?
                ");
                $stmt->execute([$poloId]);
                $installmentStats = $stmt->fetch();
                
                $stats['installments'] = [
                    'total_installments' => (int)$installmentStats['installments_count'],
                    'total_value' => (float)$installmentStats['installments_value'],
                    'avg_installments' => round((float)$installmentStats['avg_installments'], 1)
                ];
                
                jsonResponse(true, $stats, "Estatísticas do polo obtidas");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao obter estatísticas: " . $e->getMessage());
            }
            break;
            
        // ===== FUNCIONALIDADES DE USUÁRIOS (MASTER ONLY) =====

        case 'list-users':
            $auth->requirePermission('gerenciar_usuarios');
            
            try {
                $page = (int)($_GET['page'] ?? 1);
                $limit = (int)($_GET['limit'] ?? 50);
                $search = $_GET['search'] ?? null;
                $poloFilter = $_GET['polo_id'] ?? null;
                $tipoFilter = $_GET['tipo'] ?? null;
                
                $db = DatabaseManager::getInstance();
                $whereConditions = [];
                $params = [];
                
                // Filtro por polo
                if ($poloFilter) {
                    $whereConditions[] = "u.polo_id = ?";
                    $params[] = $poloFilter;
                }
                
                // Filtro por tipo
                if ($tipoFilter) {
                    $whereConditions[] = "u.tipo = ?";
                    $params[] = $tipoFilter;
                }
                
                // Filtro de busca
                if ($search) {
                    $whereConditions[] = "(u.nome LIKE ? OR u.email LIKE ?)";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                }
                
                $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
                
                // Buscar usuários
                $offset = ($page - 1) * $limit;
                $stmt = $db->getConnection()->prepare("
                    SELECT u.*, 
                           p.nome as polo_nome, 
                           p.codigo as polo_codigo,
                           (SELECT COUNT(*) FROM sessoes WHERE usuario_id = u.id AND expira_em > NOW()) as sessoes_ativas
                    FROM usuarios u
                    LEFT JOIN polos p ON u.polo_id = p.id
                    {$whereClause}
                    ORDER BY u.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                
                $stmt->execute(array_merge($params, [$limit, $offset]));
                $usuarios = $stmt->fetchAll();
                
                // Contar total
                $stmtCount = $db->getConnection()->prepare("
                    SELECT COUNT(*) as total
                    FROM usuarios u
                    LEFT JOIN polos p ON u.polo_id = p.id
                    {$whereClause}
                ");
                $stmtCount->execute($params);
                $total = $stmtCount->fetch()['total'];
                
                // Mascarar senhas e adicionar informações extras
                foreach ($usuarios as &$usuario) {
                    unset($usuario['senha']); // Remover senha do resultado
                    
                    // Status da última atividade
                    $usuario['status_atividade'] = $usuario['sessoes_ativas'] > 0 ? 'online' : 'offline';
                    
                    // Tempo desde último login
                    if ($usuario['ultimo_login']) {
                        $ultimoLogin = new DateTime($usuario['ultimo_login']);
                        $agora = new DateTime();
                        $diff = $agora->diff($ultimoLogin);
                        
                        if ($diff->days > 0) {
                            $usuario['ultimo_login_formatado'] = $diff->days . ' dias atrás';
                        } elseif ($diff->h > 0) {
                            $usuario['ultimo_login_formatado'] = $diff->h . ' horas atrás';
                        } else {
                            $usuario['ultimo_login_formatado'] = 'Há pouco';
                        }
                    } else {
                        $usuario['ultimo_login_formatado'] = 'Nunca';
                    }
                    
                    // Tipo de usuário formatado
                    $tiposFormatados = [
                        'master' => 'Master Admin',
                        'admin_polo' => 'Admin do Polo',
                        'operador' => 'Operador'
                    ];
                    $usuario['tipo_formatado'] = $tiposFormatados[$usuario['tipo']] ?? $usuario['tipo'];
                }
                
                jsonResponse(true, [
                    'usuarios' => $usuarios,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => ceil($total / $limit),
                        'total_records' => $total,
                        'per_page' => $limit
                    ],
                    'filters' => [
                        'search' => $search,
                        'polo_id' => $poloFilter,
                        'tipo' => $tipoFilter
                    ]
                ], "Lista de usuários obtida com sucesso");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao listar usuários: " . $e->getMessage());
            }
            break;

        case 'create-user':
            $auth->requirePermission('gerenciar_usuarios');
            
            try {
                $db = DatabaseManager::getInstance();
                
                // Validar dados obrigatórios
                $requiredFields = ['nome', 'email', 'tipo', 'senha'];
                foreach ($requiredFields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Campo '{$field}' é obrigatório");
                    }
                }
                
                // Verificar se email já existe
                $stmt = $db->getConnection()->prepare("SELECT COUNT(*) as count FROM usuarios WHERE email = ?");
                $stmt->execute([$_POST['email']]);
                if ($stmt->fetch()['count'] > 0) {
                    throw new Exception("Este email já está em uso por outro usuário");
                }
                
                // Validar tipo de usuário
                $tiposValidos = ['master', 'admin_polo', 'operador'];
                if (!in_array($_POST['tipo'], $tiposValidos)) {
                    throw new Exception("Tipo de usuário inválido");
                }
                
                // Para admin_polo e operador, polo_id é obrigatório
                $poloId = null;
                if (in_array($_POST['tipo'], ['admin_polo', 'operador'])) {
                    if (empty($_POST['polo_id'])) {
                        throw new Exception("Polo é obrigatório para este tipo de usuário");
                    }
                    $poloId = (int)$_POST['polo_id'];
                    
                    // Verificar se polo existe e está ativo
                    $stmt = $db->getConnection()->prepare("SELECT COUNT(*) as count FROM polos WHERE id = ? AND is_active = 1");
                    $stmt->execute([$poloId]);
                    if ($stmt->fetch()['count'] == 0) {
                        throw new Exception("Polo selecionado não existe ou está inativo");
                    }
                }
                
                // Validar senha
                if (strlen($_POST['senha']) < 6) {
                    throw new Exception("Senha deve ter pelo menos 6 caracteres");
                }
                
                // Criar usuário
                $senhaHash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
                
                $stmt = $db->getConnection()->prepare("
                    INSERT INTO usuarios (polo_id, nome, email, senha, tipo, criado_por, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                
                $stmt->execute([
                    $poloId,
                    trim($_POST['nome']),
                    trim($_POST['email']),
                    $senhaHash,
                    $_POST['tipo'],
                    $_SESSION['usuario_id']
                ]);
                
                $usuarioId = $db->getConnection()->lastInsertId();
                
                // Log de auditoria
                if ($auth->isLogado()) {
                    $stmt = $db->getConnection()->prepare("
                        INSERT INTO auditoria (usuario_id, polo_id, acao, tabela, registro_id, dados_novos, ip_address, user_agent) 
                        VALUES (?, ?, 'criar_usuario', 'usuarios', ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $_SESSION['usuario_id'],
                        $poloId,
                        $usuarioId,
                        json_encode([
                            'nome' => $_POST['nome'],
                            'email' => $_POST['email'],
                            'tipo' => $_POST['tipo'],
                            'polo_id' => $poloId
                        ]),
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                }
                
                // Buscar dados do usuário criado para retorno
                $stmt = $db->getConnection()->prepare("
                    SELECT u.*, p.nome as polo_nome 
                    FROM usuarios u 
                    LEFT JOIN polos p ON u.polo_id = p.id 
                    WHERE u.id = ?
                ");
                $stmt->execute([$usuarioId]);
                $novoUsuario = $stmt->fetch();
                
                // Remover senha do retorno
                unset($novoUsuario['senha']);
                
                jsonResponse(true, [
                    'usuario' => $novoUsuario,
                    'message' => 'Usuário criado com sucesso!'
                ], "Usuário '{$_POST['nome']}' criado com sucesso");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao criar usuário: " . $e->getMessage());
            }
            break;

        // ===== CONTINUE COM OUTRAS FUNCIONALIDADES... =====
        
        case 'health-check':
            $issues = [];
            
            // Verificar banco de dados
            try {
                $db = DatabaseManager::getInstance();
                $db->getConnection()->query("SELECT 1");
                
                // Verificar tabelas do sistema multi-tenant
                $tables = ['polos', 'usuarios', 'sessoes', 'auditoria', 'wallet_ids', 'installments']; // NOVA TABELA
                foreach ($tables as $table) {
                    $result = $db->getConnection()->query("SHOW TABLES LIKE '{$table}'");
                    if ($result->rowCount() == 0) {
                        $issues[] = "Tabela {$table} não encontrada. Execute as migrações.";
                    }
                }
                
            } catch (Exception $e) {
                $issues[] = "Banco de dados: " . $e->getMessage();
            }
            
            // Verificar configuração do polo atual
            try {
                if (!$auth->isMaster() && $_SESSION['polo_id']) {
                    $config = $configManager->getPoloConfig($_SESSION['polo_id']);
                    
                    $environment = $config['asaas_environment'];
                    $apiKey = $environment === 'production' ? 
                        $config['asaas_production_api_key'] : 
                        $config['asaas_sandbox_api_key'];
                        
                    if (empty($apiKey)) {
                        $issues[] = "API Key não configurada para ambiente {$environment} do polo";
                    } else {
                        // Testar conexão
                        $testResult = $configManager->testAsaasConfig($_SESSION['polo_id']);
                        if (!$testResult['success']) {
                            $issues[] = "Falha na conexão com ASAAS: " . $testResult['message'];
                        }
                    }
                }
                
            } catch (Exception $e) {
                $issues[] = "Configuração do polo: " . $e->getMessage();
            }
            
            // Verificar espaço em disco
            $freeBytes = disk_free_space(__DIR__);
            $freeMB = round($freeBytes / 1024 / 1024);
            if ($freeMB < 100) {
                $issues[] = "Espaço em disco baixo: {$freeMB}MB";
            }
            
            if (empty($issues)) {
                jsonResponse(true, [
                    'polo_context' => $_SESSION['polo_nome'] ?? 'Master',
                    'user_type' => $_SESSION['usuario_tipo'],
                    'installment_support' => true // NOVA FUNCIONALIDADE
                ], "Sistema funcionando corretamente!");
            } else {
                jsonResponse(false, $issues, "Problemas encontrados no sistema");
            }
            break;
            
        case 'system-info':
            try {
                $db = DatabaseManager::getInstance();
                
                // Estatísticas baseadas no contexto do usuário
                $poloId = !$auth->isMaster() ? $_SESSION['polo_id'] : null;
                $walletStats = $poloId ? 
                    $configManager->getPoloStats($poloId) : 
                    ['wallet_ids' => 0, 'usuarios_ativos' => 0];
                
                $info = [
                    'php_version' => PHP_VERSION,
                    'context' => $auth->isMaster() ? 'Master Admin' : $_SESSION['polo_nome'],
                    'user_type' => $_SESSION['usuario_tipo'],
                    'polo_id' => $_SESSION['polo_id'] ?? 'N/A',
                    'log_retention' => LOG_RETENTION_DAYS . ' dias',
                    'database' => DB_NAME,
                    'timezone' => date_default_timezone_get(),
                    'server_time' => date('Y-m-d H:i:s'),
                    'disk_free' => round(disk_free_space(__DIR__) / 1024 / 1024) . 'MB',
                    'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
                    'memory_limit' => ini_get('memory_limit'),
                    'wallet_ids_total' => $walletStats['wallet_ids'] ?? 0,
                    'usuarios_polo' => $walletStats['usuarios_ativos'] ?? 0,
                    'system_version' => '2.1.0 Multi-Tenant + Parcelamento', // VERSÃO ATUALIZADA
                    'installment_support' => true,
                    'max_installments' => 24
                ];
                
                jsonResponse(true, $info, "Informações do sistema obtidas");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao obter informações: " . $e->getMessage());
            }
            break;
            
        // ===== FUNCIONALIDADES DE LIMPEZA E MANUTENÇÃO =====
        
        case 'clean-logs':
            $auth->requirePermission('manter_sistema');
            
            try {
                $db = DatabaseManager::getInstance();
                $deletedRows = $db->cleanOldLogs();
                
                // Limpar arquivos de log também
                $logDir = __DIR__ . '/logs';
                $deletedFiles = 0;
                if (is_dir($logDir)) {
                    $cutoffDate = strtotime('-' . LOG_RETENTION_DAYS . ' days');
                    $files = glob($logDir . '/asaas_*.log');
                    
                    foreach ($files as $file) {
                        if (filemtime($file) < $cutoffDate) {
                            unlink($file);
                            $deletedFiles++;
                        }
                    }
                }
                
                // NOVO: Limpar arquivos temporários de carnês antigos
                $tempDir = __DIR__ . '/temp';
                $deletedTempFiles = 0;
                if (is_dir($tempDir)) {
                    $tempFiles = glob($tempDir . '/carne_*.pdf');
                    $tempCutoff = strtotime('-7 days'); // Carnês mais antigos que 7 dias
                    
                    foreach ($tempFiles as $file) {
                        if (filemtime($file) < $tempCutoff) {
                            unlink($file);
                            $deletedTempFiles++;
                        }
                    }
                }
                
                jsonResponse(true, [
                    'database_rows' => $deletedRows,
                    'log_files' => $deletedFiles,
                    'temp_files' => $deletedTempFiles
                ], "Limpeza concluída: {$deletedRows} registros, {$deletedFiles} logs e {$deletedTempFiles} carnês temporários removidos");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na limpeza: " . $e->getMessage());
            }
            break;
            
        default:
            jsonResponse(false, null, "Ação não encontrada: {$action}");
            break;
    }
    
} catch (Exception $e) {
    // Log do erro com contexto do usuário
    error_log("API Error - User: " . ($_SESSION['usuario_email'] ?? 'unknown') . 
              ", Polo: " . ($_SESSION['polo_id'] ?? 'none') . 
              ", Action: {$action}, Error: " . $e->getMessage());
              
    jsonResponse(false, null, "Erro interno: " . $e->getMessage());
}

// Se chegou até aqui, ação não foi encontrada
jsonResponse(false, null, "Nenhuma ação especificada");
?>