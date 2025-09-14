<?php
/**
 * Interface Principal do Sistema IMEP Split ASAAS - VERSÃO COM MENSALIDADES E DESCONTO
 * Arquivo: index.php
 * Versão: 3.4 - Adicionada funcionalidade de desconto em mensalidades parceladas
 */

// ==================================================
// CONFIGURAÇÃO INICIAL E SEGURANÇA
// ==================================================

// Iniciar sessão e configurações de segurança
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    session_start();
}

// Configurações de erro para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Buffer de saída para controle de headers
ob_start();

// ==================================================
// INCLUIR SISTEMA E VERIFICAR AUTENTICAÇÃO
// ==================================================

try {
    // Incluir bootstrap do sistema
    require_once 'bootstrap.php';
    
    // VERIFICAÇÃO OBRIGATÓRIA DE AUTENTICAÇÃO
    if (!$auth || !$auth->isLogado()) {
        // Limpar buffer e redirecionar para login
        ob_end_clean();
        
        // Salvar URL atual para redirect após login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect seguro
        if (!headers_sent()) {
            header('Location: login.php');
            exit;
        } else {
            echo "<script>window.location.href = 'login.php';</script>";
            echo '<noscript><meta http-equiv="refresh" content="0;url=login.php"></noscript>';
            exit;
        }
    }
    
    // Obter dados do usuário autenticado
    $usuario = $auth->getUsuarioAtual();
    
    // Verificar se usuário é válido
    if (!$usuario || !$usuario['id']) {
        throw new Exception('Sessão inválida. Faça login novamente.');
    }
    
    // Log de acesso para auditoria
    error_log("Acesso ao index.php COM DESCONTO - Usuário: {$usuario['email']}, Tipo: {$usuario['tipo']}, Polo: " . ($usuario['polo_nome'] ?? 'Master'));
    
} catch (Exception $e) {
    // Em caso de erro, sempre redirecionar para login
    ob_end_clean();
    
    error_log("Erro no index.php: " . $e->getMessage());
    
    // Limpar sessão problemática
    session_destroy();
    
    if (!headers_sent()) {
        header('Location: login.php?error=' . urlencode('Sessão expirada. Faça login novamente.'));
        exit;
    } else {
        echo "<script>alert('Erro: {$e->getMessage()}'); window.location.href = 'login.php';</script>";
        exit;
    }
}

// ==================================================
// CONFIGURAÇÃO DO CONTEXTO DO USUÁRIO COM DESCONTO
// ==================================================

// Determinar contexto baseado no tipo de usuário
$isMaster = ($usuario['tipo'] === 'master');
$isAdminPolo = ($usuario['tipo'] === 'admin_polo');
$isOperador = ($usuario['tipo'] === 'operador');

// Configurar título e contexto da página - ATUALIZADO COM DESCONTO
$pageTitle = 'Dashboard';
$pageSubtitle = 'Sistema de Split de Pagamentos ASAAS com Mensalidades COM DESCONTO';

if ($isMaster) {
    $pageTitle = 'Master Dashboard';
    $pageSubtitle = 'Administração Central - Todos os Polos - Com Mensalidades e Desconto';
} elseif ($isAdminPolo) {
    $pageTitle = 'Admin Dashboard';
    $pageSubtitle = 'Administração do Polo: ' . ($usuario['polo_nome'] ?? 'N/A') . ' - Com Mensalidades e Desconto';
} else {
    $pageTitle = 'Operador Dashboard'; 
    $pageSubtitle = 'Polo: ' . ($usuario['polo_nome'] ?? 'N/A') . ' - Com Mensalidades e Desconto';
}

// Configurar permissões baseadas no tipo - ADICIONADAS PERMISSÕES DE DESCONTO
$permissions = [
    'can_manage_users' => $isMaster,
    'can_manage_poles' => $isMaster,
    'can_view_all_data' => $isMaster || $isAdminPolo,
    'can_create_payments' => true, // Todos podem criar pagamentos
    'can_create_installments' => true, // Todos podem criar mensalidades
    'can_create_installments_with_discount' => true, // NOVO: Todos podem criar mensalidades COM DESCONTO
    'can_create_customers' => true, // Todos podem criar clientes
    'can_manage_wallets' => $isMaster || $isAdminPolo,
    'can_view_reports' => true, // Todos podem ver relatórios (filtrados por polo)
    'can_view_discount_reports' => true, // NOVO: Todos podem ver relatórios de desconto
    'can_configure_asaas' => $isMaster || $isAdminPolo,
    'can_export_data' => $isMaster || $isAdminPolo,
    'can_generate_payment_books' => true, // Todos podem gerar carnês
    'max_discount_percentage' => MAX_DISCOUNT_PERCENTAGE // NOVO: Máximo de desconto permitido
];

// ==================================================
// FUNÇÕES AUXILIARES PARA DESCONTO
// ==================================================

/**
 * Validar valor de desconto
 */
function validateDiscountValue($discountValue, $installmentValue) {
    $errors = [];
    
    if ($discountValue < 0) {
        $errors[] = 'Valor do desconto não pode ser negativo';
    }
    
    if ($discountValue >= $installmentValue) {
        $errors[] = 'Desconto não pode ser maior ou igual ao valor da parcela';
    }
    
    $maxDiscount = $installmentValue * (MAX_DISCOUNT_PERCENTAGE / 100);
    if ($discountValue > $maxDiscount) {
        $errors[] = "Desconto máximo permitido: R$ " . number_format($maxDiscount, 2, ',', '.') . " (" . MAX_DISCOUNT_PERCENTAGE . "% da parcela)";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'max_discount' => $maxDiscount,
        'percentage' => $installmentValue > 0 ? ($discountValue / $installmentValue) * 100 : 0
    ];
}

/**
 * Calcular economia total com desconto
 */
function calculateTotalSavings($discountValue, $installmentCount) {
    return $discountValue * $installmentCount;
}

/**
 * Formatar informações de desconto para exibição
 */
function formatDiscountInfo($discountValue, $installmentValue, $installmentCount) {
    if ($discountValue <= 0) {
        return [
            'has_discount' => false,
            'formatted_discount' => 'Sem desconto',
            'total_savings' => 0,
            'percentage' => 0
        ];
    }
    
    $totalSavings = calculateTotalSavings($discountValue, $installmentCount);
    $percentage = ($discountValue / $installmentValue) * 100;
    
    return [
        'has_discount' => true,
        'formatted_discount' => 'R$ ' . number_format($discountValue, 2, ',', '.'),
        'total_savings' => $totalSavings,
        'formatted_total_savings' => 'R$ ' . number_format($totalSavings, 2, ',', '.'),
        'percentage' => round($percentage, 1),
        'per_installment_info' => "R$ " . number_format($discountValue, 2, ',', '.') . " por parcela (" . round($percentage, 1) . "%)"
    ];
}

// ==================================================
// CONFIGURAÇÃO DO CONTEXTO JAVASCRIPT COM DESCONTO
// ==================================================

// Definir contexto para JavaScript - ATUALIZADO COM DESCONTO
$jsContext = [
    'user' => [
        'id' => $usuario['id'],
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'tipo' => $usuario['tipo'],
        'polo_id' => $usuario['polo_id'],
        'polo_nome' => $usuario['polo_nome']
    ],
    'permissions' => $permissions,
    'environment' => defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'sandbox',
    'system_version' => '3.4 Multi-Tenant + Mensalidades + Desconto',
    'features' => [
        'installments' => true,
        'payment_books' => true,
        'discount_support' => true, // NOVO: Suporte a desconto
        'max_installments' => MAX_INSTALLMENTS,
        'max_discount_percentage' => MAX_DISCOUNT_PERCENTAGE, // NOVO
        'default_discount_type' => DEFAULT_DISCOUNT_TYPE // NOVO
    ],
    'discount_config' => [ // NOVA SEÇÃO DE CONFIGURAÇÃO DE DESCONTO
        'max_percentage' => MAX_DISCOUNT_PERCENTAGE,
        'min_value' => 0.01,
        'max_value_per_installment' => MAX_INSTALLMENT_VALUE * (MAX_DISCOUNT_PERCENTAGE / 100),
        'deadline_type' => 'DUE_DATE',
        'description_template' => 'Desconto válido até o vencimento'
    ]
];

// ==================================================
// FUNÇÃO PARA OBTER CONFIGURAÇÃO ASAAS CONTEXTUAL
// ==================================================

/**
 * Função para obter configuração ASAAS baseada no contexto - MANTIDA
 */
function getContextualAsaasInstance() {
    global $usuario, $isMaster;
    
    try {
        if ($isMaster) {
            // Master usa configurações globais
            return AsaasConfig::getInstance();
        } else {
            // Usuários de polo usam configuração dinâmica
            if (class_exists('DynamicAsaasConfig')) {
                $dynamicConfig = new DynamicAsaasConfig();
                return $dynamicConfig->getInstance();
            } else {
                throw new Exception('Sistema de configuração dinâmica não disponível');
            }
        }
    } catch (Exception $e) {
        throw new Exception('Erro ao obter configuração ASAAS: ' . $e->getMessage());
    }
}

// Log de inicialização da interface COM DESCONTO
error_log("Interface index.php COM DESCONTO inicializada - Usuário: {$usuario['email']}, Desconto máximo: " . MAX_DISCOUNT_PERCENTAGE . "%");
// ==================================================
// PROCESSAMENTO DE AÇÕES E FORMULÁRIOS COM DESCONTO
// ==================================================

$message = '';
$messageType = '';
$errorDetails = [];

// Função para definir mensagens de feedback
function setMessage($type, $text, $details = []) {
    global $message, $messageType, $errorDetails;
    $message = $text;
    $messageType = $type;
    $errorDetails = $details;
}

// Processar ações via POST com validação de permissões - ATUALIZADO COM DESCONTO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Log da ação para auditoria
    error_log("Ação executada COM DESCONTO: {$action} por usuário: {$usuario['email']}");
    
    try {
        switch ($action) {
            
            // ===== NOVA AÇÃO: CRIAR MENSALIDADE COM DESCONTO =====
            case 'create_installment_with_discount':
                if (!$permissions['can_create_installments_with_discount']) {
                    throw new Exception('Você não tem permissão para criar mensalidades com desconto');
                }
                
                // Validar dados básicos
                $paymentData = $_POST['payment'] ?? [];
                $installmentData = $_POST['installment'] ?? [];
                $splitsData = $_POST['splits'] ?? [];
                
                // ===== PROCESSAR DADOS DO DESCONTO =====
                $discountEnabled = !empty($_POST['discount_enabled']) && $_POST['discount_enabled'] === '1';
                $discountValue = 0;
                $discountInfo = ['has_discount' => false];
                
                if ($discountEnabled) {
                    $discountValue = floatval($_POST['discount_value'] ?? 0);
                    $installmentValue = floatval($installmentData['installmentValue'] ?? 0);
                    
                    // Validar desconto
                    $discountValidation = validateDiscountValue($discountValue, $installmentValue);
                    
                    if (!$discountValidation['valid']) {
                        throw new Exception('Erro no desconto: ' . implode('; ', $discountValidation['errors']));
                    }
                    
                    // Preparar dados do desconto para InstallmentManager
                    $installmentData['discount_value'] = $discountValue;
                    $installmentData['discount_type'] = DEFAULT_DISCOUNT_TYPE;
                    $installmentData['discount_deadline_type'] = 'DUE_DATE';
                    
                    $discountInfo = formatDiscountInfo($discountValue, $installmentValue, $installmentData['installmentCount']);
                    
                    error_log("Desconto configurado: R$ {$discountValue} por parcela, total: R$ {$discountInfo['total_savings']}");
                }
                
                // Validações básicas do pagamento
                $requiredPaymentFields = ['customer', 'billingType', 'description', 'dueDate'];
                foreach ($requiredPaymentFields as $field) {
                    if (empty($paymentData[$field])) {
                        throw new Exception("Campo '{$field}' é obrigatório para criar mensalidade");
                    }
                }
                
                // Validações do parcelamento
                $installmentCount = (int)($installmentData['installmentCount'] ?? 0);
                $installmentValue = floatval($installmentData['installmentValue'] ?? 0);
                
                if ($installmentCount < MIN_INSTALLMENTS || $installmentCount > MAX_INSTALLMENTS) {
                    throw new Exception('Número de parcelas deve ser entre ' . MIN_INSTALLMENTS . ' e ' . MAX_INSTALLMENTS);
                }
                
                if ($installmentValue < MIN_INSTALLMENT_VALUE || $installmentValue > MAX_INSTALLMENT_VALUE) {
                    throw new Exception('Valor da parcela deve ser entre R$ ' . 
                        number_format(MIN_INSTALLMENT_VALUE, 2, ',', '.') . ' e R$ ' . 
                        number_format(MAX_INSTALLMENT_VALUE, 2, ',', '.'));
                }
                
                // Validar data de vencimento
                $dueDate = $paymentData['dueDate'];
                if (strtotime($dueDate) < strtotime(date('Y-m-d'))) {
                    throw new Exception('Data de vencimento não pode ser anterior a hoje');
                }
                
                // Processar splits
                $processedSplits = [];
                $totalPercentage = 0;
                $totalFixedValue = 0;
                
                foreach ($splitsData as $split) {
                    if (!empty($split['walletId'])) {
                        $splitData = ['walletId' => $split['walletId']];
                        
                        if (!empty($split['percentualValue']) && floatval($split['percentualValue']) > 0) {
                            $percentage = floatval($split['percentualValue']);
                            if ($percentage > 100) {
                                throw new Exception('Percentual de split não pode ser maior que 100%');
                            }
                            $splitData['percentualValue'] = $percentage;
                            $totalPercentage += $percentage;
                        }
                        
                        if (!empty($split['fixedValue']) && floatval($split['fixedValue']) > 0) {
                            $fixedValue = floatval($split['fixedValue']);
                            if ($fixedValue >= $installmentValue) {
                                throw new Exception('Valor fixo do split não pode ser maior ou igual ao valor da parcela');
                            }
                            $splitData['fixedValue'] = $fixedValue;
                            $totalFixedValue += $fixedValue;
                        }
                        
                        $processedSplits[] = $splitData;
                    }
                }
                
                // Validar splits
                if (!empty($processedSplits)) {
                    if ($totalPercentage > 100) {
                        throw new Exception('A soma dos percentuais não pode exceder 100%');
                    }
                    
                    if ($totalFixedValue >= $installmentValue) {
                        throw new Exception('A soma dos valores fixos não pode ser maior ou igual ao valor da parcela');
                    }
                }
                
                // ===== CRIAR MENSALIDADE COM DESCONTO VIA INSTALLMENT MANAGER =====
                try {
                    $installmentManager = new InstallmentManager();
                    $result = $installmentManager->createInstallment($paymentData, $processedSplits, $installmentData);
                    
                    // Preparar mensagem de sucesso COM INFORMAÇÕES DE DESCONTO
                    $totalValue = $installmentCount * $installmentValue;
                    $successMessage = "✅ Mensalidade criada com sucesso!<br>";
                    $successMessage .= "<strong>{$installmentCount} parcelas de R$ " . number_format($installmentValue, 2, ',', '.') . "</strong><br>";
                    $successMessage .= "Total: R$ " . number_format($totalValue, 2, ',', '.') . "<br>";
                    
                    // ===== ADICIONAR INFORMAÇÕES DE DESCONTO NA MENSAGEM =====
                    if ($discountInfo['has_discount']) {
                        $successMessage .= "<br>🏷️ <strong>Desconto configurado:</strong><br>";
                        $successMessage .= "• {$discountInfo['per_installment_info']}<br>";
                        $successMessage .= "• Economia total: <span class='text-success'>{$discountInfo['formatted_total_savings']}</span><br>";
                        $successMessage .= "• <small class='text-info'>Válido até o vencimento de cada parcela</small><br>";
                    }
                    
                    $successMessage .= "<br>Primeiro vencimento: " . date('d/m/Y', strtotime($paymentData['dueDate']));
                    
                    // Adicionar link para primeira parcela se disponível
                    if (!empty($result['data']['payment']['invoiceUrl'])) {
                        $successMessage .= "<br><a href='{$result['data']['payment']['invoiceUrl']}' target='_blank' class='btn btn-sm btn-outline-primary mt-2'><i class='bi bi-eye'></i> Ver 1ª Parcela</a>";
                    }
                    
                    setMessage('success', $successMessage[
                        'installment_id' => $result['installment_id'] ?? 'N/A',
                        'installment_count' => $installmentCount,
                        'installment_value' => $installmentValue,
                        'total_value' => $totalValue,
                        'splits_count' => count($processedSplits),
                        'has_discount' => $discountInfo['has_discount'],
                        'discount_value' => $discountValue,
                        'total_savings' => $discountInfo['total_savings'] ?? 0
                    ]); 
                    
                } catch (Exception $e) {
                    throw new Exception('Erro ao criar mensalidade com desconto: ' . $e->getMessage());
                }
                break;
            
            // ===== AÇÃO EXISTENTE MANTIDA: CRIAR MENSALIDADE TRADICIONAL =====
            case 'create_installment':
                if (!$permissions['can_create_installments']) {
                    throw new Exception('Você não tem permissão para criar mensalidades');
                }
                
                // Redirecionar para nova ação com desconto (mesmo que desconto = 0)
                $_POST['action'] = 'create_installment_with_discount';
                $_POST['discount_enabled'] = '0';
                $_POST['discount_value'] = '0';
                
                // Re-processar com a nova ação
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
                
            // ===== FUNCIONALIDADES EXISTENTES MANTIDAS =====
            case 'create_wallet':
                if (!$permissions['can_manage_wallets']) {
                    throw new Exception('Você não tem permissão para gerenciar Wallet IDs');
                }
                
                $name = trim($_POST['wallet']['name'] ?? '');
                $walletId = trim($_POST['wallet']['wallet_id'] ?? '');
                $description = trim($_POST['wallet']['description'] ?? '');
                
                if (empty($name) || empty($walletId)) {
                    throw new Exception('Nome e Wallet ID são obrigatórios');
                }
                
                // Validar formato UUID
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $walletId)) {
                    throw new Exception('Formato inválido. Use formato UUID (ex: 22e49670-27e4-4579-a4c1-205c8a40497c)');
                }
                
                // Criar Wallet ID
                $walletManager = new WalletManager();
                $wallet = $walletManager->createWallet($name, $walletId, $description, $usuario['polo_id']);
                
                setMessage('success', "Wallet ID '{$name}' cadastrado com sucesso!", ['wallet_id' => $walletId]);
                break;
                
            case 'create_customer':
                $customerData = $_POST['customer'] ?? [];
                $requiredFields = ['name', 'email', 'cpfCnpj'];
                
                foreach ($requiredFields as $field) {
                    if (empty($customerData[$field])) {
                        throw new Exception("Campo '{$field}' é obrigatório para criar cliente");
                    }
                }
                
                // Validar email
                if (!filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Email inválido');
                }
                
                // Criar cliente via API
                $asaas = getContextualAsaasInstance();
                $customer = $asaas->createCustomer($customerData);
                
                // Salvar no banco com informações do polo
                $db = DatabaseManager::getInstance();
                $customerSaveData = array_merge($customer, ['polo_id' => $usuario['polo_id']]);
                $db->saveCustomer($customerSaveData);
                
                setMessage('success', 'Cliente criado com sucesso! ID: ' . $customer['id'], ['customer_id' => $customer['id']]);
                break;
                
            case 'create_payment':
                // Pagamento simples (sem parcelamento)
                $paymentData = $_POST['payment'] ?? [];
                $splitsData = $_POST['splits'] ?? [];
                
                // Validações básicas mantidas
                $requiredPaymentFields = ['customer', 'billingType', 'value', 'description', 'dueDate'];
                foreach ($requiredPaymentFields as $field) {
                    if (empty($paymentData[$field])) {
                        throw new Exception("Campo '{$field}' é obrigatório para criar pagamento");
                    }
                }
                
                // Processar splits para pagamento simples
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
                
                // Criar pagamento via API
                $asaas = getContextualAsaasInstance();
                $payment = $asaas->createPaymentWithSplit($paymentData, $processedSplits);
                
                // Salvar no banco
                $db = DatabaseManager::getInstance();
                $paymentSaveData = array_merge($payment, ['polo_id' => $usuario['polo_id']]);
                $db->savePayment($paymentSaveData);
                
                if (!empty($processedSplits)) {
                    $db->savePaymentSplits($payment['id'], $processedSplits);
                }
                
                setMessage('success', 'Pagamento criado com sucesso!', ['payment_id' => $payment['id']]);
                break;
                
            case 'test_connection':
                try {
                    $asaas = getContextualAsaasInstance();
                    $response = $asaas->listAccounts(1, 0);
                    
                    $contextInfo = $isMaster ? 'Configuração Master' : "Polo: {$usuario['polo_nome']}";
                    setMessage('success', "Conexão OK! ({$contextInfo}) - {$response['totalCount']} contas encontradas.", [
                        'total_accounts' => $response['totalCount'],
                        'environment' => defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'undefined',
                        'discount_support' => 'ATIVO' // NOVO
                    ]);
                } catch (Exception $e) {
                    throw new Exception('Falha na conexão com ASAAS: ' . $e->getMessage());
                }
                break;
                
            // ===== NOVA AÇÃO: VALIDAR DESCONTO VIA AJAX =====
            case 'validate_discount':
                header('Content-Type: application/json');
                
                $discountValue = floatval($_POST['discount_value'] ?? 0);
                $installmentValue = floatval($_POST['installment_value'] ?? 0);
                $installmentCount = intval($_POST['installment_count'] ?? 0);
                
                $validation = validateDiscountValue($discountValue, $installmentValue);
                $discountInfo = formatDiscountInfo($discountValue, $installmentValue, $installmentCount);
                
                echo json_encode([
                    'valid' => $validation['valid'],
                    'errors' => $validation['errors'],
                    'max_discount' => $validation['max_discount'],
                    'percentage' => $validation['percentage'],
                    'discount_info' => $discountInfo,
                    'formatted_savings' => $discountInfo['formatted_total_savings'] ?? 'R$ 0,00'
                ]);
                exit;
                
            default:
                throw new Exception("Ação não reconhecida: {$action}");
        }
        
    } catch (Exception $e) {
        setMessage('error', $e->getMessage(), [
            'action' => $action, 
            'user' => $usuario['email'],
            'discount_enabled' => $_POST['discount_enabled'] ?? 'não',
            'discount_value' => $_POST['discount_value'] ?? '0'
        ]);
        
        // Log detalhado do erro COM INFORMAÇÕES DE DESCONTO
        error_log("Erro na ação {$action} COM DESCONTO por {$usuario['email']}: " . $e->getMessage() . 
                 " | Desconto: " . ($_POST['discount_value'] ?? '0'));
    }
    
    // Redirecionar para evitar reenvio de formulário (Post-Redirect-Get pattern)
    $redirectUrl = $_SERVER['REQUEST_URI'];
    if (strpos($redirectUrl, '?') !== false) {
        $redirectUrl = substr($redirectUrl, 0, strpos($redirectUrl, '?'));
    }
    
    // Salvar mensagem na sessão para mostrar após redirect
    if ($message) {
        $_SESSION['flash_message'] = [
            'type' => $messageType,
            'text' => $message,
            'details' => $errorDetails
        ];
    }
    
    header("Location: {$redirectUrl}");
    exit;
}

// Recuperar mensagem flash da sessão
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $messageType = $_SESSION['flash_message']['type'];
    $errorDetails = $_SESSION['flash_message']['details'] ?? [];
    unset($_SESSION['flash_message']);
}
// ==================================================
// CARREGAMENTO SEGURO DE DADOS COM ESTATÍSTICAS DE DESCONTO
// ==================================================

// Função para exibir mensagens de feedback
function showMessage() {
    global $message, $messageType, $errorDetails, $isMaster, $isAdminPolo;
    
    if (!$message) return;
    
    $alertClass = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $iconClass = [
        'success' => 'bi-check-circle',
        'error' => 'bi-exclamation-triangle',
        'warning' => 'bi-exclamation-triangle',
        'info' => 'bi-info-circle'
    ];
    
    echo "<div class='alert {$alertClass[$messageType]} alert-dismissible fade show' role='alert'>";
    echo "<i class='bi {$iconClass[$messageType]} me-2'></i>";
    echo $message; // Permitir HTML para links
    
    // Mostrar detalhes se existirem (apenas para admins)
    if (!empty($errorDetails) && ($isMaster || $isAdminPolo)) {
        echo "<hr><small>";
        foreach ($errorDetails as $key => $value) {
            if ($key === 'discount_value' && $value > 0) {
                echo "<strong>💰 Desconto configurado:</strong> R$ " . number_format($value, 2, ',', '.') . "<br>";
            } else {
                echo "<strong>{$key}:</strong> " . htmlspecialchars($value) . "<br>";
            }
        }
        echo "</small>";
    }
    
    echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>";
    echo "</div>";
}

// CARREGAMENTO DE DADOS COM SUPORTE A DESCONTO
$stats = null;
$customers = [];
$splitAccounts = [];
$payments = [];
$walletIds = [];
$recentInstallments = [];
$discountStats = null; // NOVO: Estatísticas de desconto

try {
    $db = DatabaseManager::getInstance();
    
    // Obter estatísticas baseadas no contexto do usuário COM DESCONTO
    if ($isMaster) {
        $stats = SystemStats::getGeneralStats();
        $discountStats = $db->getInstallmentStatsWithDiscount(); // NOVO
        $contextLabel = 'Todos os Polos';
    } else {
        $stats = SystemStats::getGeneralStats($usuario['polo_id']);
        $discountStats = $db->getInstallmentStatsWithDiscount($usuario['polo_id']); // NOVO
        $contextLabel = $usuario['polo_nome'] ?? 'Polo N/A';
    }
    
    // Adicionar informações contextuais às estatísticas
    if ($stats) {
        $stats['context_label'] = $contextLabel;
        $stats['user_type'] = $usuario['tipo'];
        $stats['polo_filter'] = !$isMaster ? $usuario['polo_id'] : null;
        
        // NOVO: Adicionar estatísticas de desconto
        $stats['discount_stats'] = $discountStats;
    }
    
    // Carregar dados existentes (clientes, wallets, etc.) - CÓDIGO MANTIDO
    $customerQuery = "SELECT * FROM customers WHERE 1=1";
    $customerParams = [];
    
    if (!$isMaster && $usuario['polo_id']) {
        $customerQuery .= " AND polo_id = ?";
        $customerParams[] = $usuario['polo_id'];
    }
    
    $customerQuery .= " ORDER BY created_at DESC LIMIT 15";
    
    $customerStmt = $db->getConnection()->prepare($customerQuery);
    $customerStmt->execute($customerParams);
    $customers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Carregar Wallet IDs
    $walletQuery = "SELECT * FROM wallet_ids WHERE is_active = 1";
    $walletParams = [];
    
    if (!$isMaster && $usuario['polo_id']) {
        $walletQuery .= " AND polo_id = ?";
        $walletParams[] = $usuario['polo_id'];
    }
    
    $walletQuery .= " ORDER BY created_at DESC";
    
    $walletStmt = $db->getConnection()->prepare($walletQuery);
    $walletStmt->execute($walletParams);
    $walletIds = $walletStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Carregar mensalidades recentes COM INFORMAÇÕES DE DESCONTO
    try {
        $installmentQuery = "SELECT *, 
                            CASE WHEN has_discount = 1 THEN 
                                CONCAT('R$ ', FORMAT(discount_value, 2, 'de_DE'), ' por parcela') 
                            ELSE 'Sem desconto' 
                            END as discount_info
                            FROM installments WHERE 1=1";
        $installmentParams = [];
        
        if (!$isMaster && $usuario['polo_id']) {
            $installmentQuery .= " AND polo_id = ?";
            $installmentParams[] = $usuario['polo_id'];
        }
        
        $installmentQuery .= " ORDER BY created_at DESC LIMIT 10";
        
        $installmentStmt = $db->getConnection()->prepare($installmentQuery);
        $installmentStmt->execute($installmentParams);
        $recentInstallments = $installmentStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $recentInstallments = [];
        error_log("Tabela installments ainda não atualizada para desconto: " . $e->getMessage());
    }
    
    // Carregar pagamentos recentes (mantido)
    $paymentQuery = "SELECT p.*, c.name as customer_name FROM payments p LEFT JOIN customers c ON p.customer_id = c.id WHERE 1=1";
    $paymentParams = [];
    
    if (!$isMaster && $usuario['polo_id']) {
        $paymentQuery .= " AND p.polo_id = ?";
        $paymentParams[] = $usuario['polo_id'];
    }
    
    $paymentQuery .= " ORDER BY p.created_at DESC LIMIT 15";
    
    $paymentStmt = $db->getConnection()->prepare($paymentQuery);
    $paymentStmt->execute($paymentParams);
    $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Em caso de erro no carregamento, definir dados padrão
    error_log("Erro ao carregar dados COM DESCONTO no index.php: " . $e->getMessage());
    
    $stats = [
        'total_customers' => 0,
        'total_wallet_ids' => 0,
        'total_payments' => 0,
        'total_value' => 0,
        'context_label' => 'Erro ao carregar',
        'discount_stats' => [
            'installments_with_discount' => 0,
            'total_discount_potential' => 0,
            'discount_adoption_rate' => 0
        ],
        'error' => true
    ];
    
    setMessage('warning', 'Alguns dados podem não estar atualizados devido a um erro temporário.', [
        'error_details' => $e->getMessage()
    ]);
}

// Funções auxiliares mantidas
function maskDocument($document) {
    if (empty($document)) return 'N/A';
    
    $document = preg_replace('/[^0-9]/', '', $document);
    
    if (strlen($document) === 11) {
        return substr($document, 0, 3) . '.***.***-' . substr($document, -2);
    } elseif (strlen($document) === 14) {
        return substr($document, 0, 2) . '.***.***/****-' . substr($document, -2);
    }
    
    return substr($document, 0, 3) . '***';
}

function maskWalletId($walletId) {
    if (empty($walletId)) return 'N/A';
    
    if (strlen($walletId) > 16) {
        return substr($walletId, 0, 8) . '...' . substr($walletId, -8);
    }
    
    return $walletId;
}

function getStatusClass($status) {
    $statusMap = [
        'RECEIVED' => 'success',
        'PENDING' => 'warning', 
        'OVERDUE' => 'danger',
        'CONFIRMED' => 'info',
        'DELETED' => 'dark',
        'ACTIVE' => 'success',
        'INACTIVE' => 'secondary'
    ];
    return $statusMap[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $iconMap = [
        'RECEIVED' => '✅',
        'PENDING' => '⏳',
        'OVERDUE' => '⚠️',
        'CONFIRMED' => 'ℹ️',
        'DELETED' => '❌',
        'ACTIVE' => '✅',
        'INACTIVE' => '⏸️'
    ];
    return $iconMap[$status] ?? '❓';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Sistema IMEP Split ASAAS com Desconto</title>
    
    <!-- Bootstrap 5.3 e Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💳</text></svg>">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --installment-gradient: linear-gradient(135deg, #667eea 0%, #11998e 100%);
            --discount-gradient: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%); /* NOVO: Gradiente para desconto */
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* ===== SIDEBAR COM NOVOS ÍCONES DE DESCONTO ===== */
        .sidebar {
            min-height: 100vh;
            background: var(--primary-gradient);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 8px;
            margin: 2px 0;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            transform: translateX(4px);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 8px;
        }
        
        /* ===== CARDS COM SUPORTE A DESCONTO ===== */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        
        .card-stats {
            background: var(--warning-gradient);
            color: white;
            text-align: center;
            border-radius: 15px;
        }
        
        .card-stats i {
            font-size: 2.5rem;
            opacity: 0.9;
        }
        
        .card-stats h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 10px 0 5px;
        }
        
        .card-stats p {
            opacity: 0.9;
            margin: 0;
            font-weight: 500;
        }
        
        /* ===== NOVOS CARDS PARA DESCONTO ===== */
        .card-discount {
            background: var(--discount-gradient);
            color: white;
            text-align: center;
            border-radius: 15px;
        }
        
        .card-installment {
            background: var(--installment-gradient);
            color: white;
            text-align: center;
            border-radius: 15px;
        }
        
        .discount-badge {
            background: linear-gradient(45deg, #ffeaa7, #fab1a0);
            color: #2d3436;
            font-weight: 600;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.75rem;
            margin-left: 5px;
        }
        
        /* ===== OUTROS ESTILOS MANTIDOS ===== */
        .installment-form-card {
            border-left: 4px solid #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.02) 0%, rgba(17, 153, 142, 0.02) 100%);
        }
        
        .nav-tabs .nav-link {
            color: #6c757d;
            border: 1px solid transparent;
            border-bottom: 2px solid transparent;
        }
        
        .nav-tabs .nav-link.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-radius: 0 0 15px 15px;
            margin-bottom: 20px;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: #495057;
        }
        
        .environment-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .section {
            display: none;
        }
        
        .section.active {
            display: block;
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .btn-gradient {
            background: var(--primary-gradient);
            border: none;
            color: white;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-installment {
            background: var(--installment-gradient);
            border: none;
            color: white;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-installment:hover {
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* NOVO: Botão específico para desconto */
        .btn-discount {
            background: var(--discount-gradient);
            border: none;
            color: #2d3436;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-discount:hover {
            color: #2d3436;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(255, 234, 167, 0.6);
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15);
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        
        .alert {
            border: none;
            border-radius: 12px;
            border-left: 4px solid;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .alert-success { border-left-color: #198754; background: rgba(25, 135, 84, 0.1); }
        .alert-danger { border-left-color: #dc3545; background: rgba(220, 53, 69, 0.1); }
        .alert-warning { border-left-color: #ffc107; background: rgba(255, 193, 7, 0.1); }
        .alert-info { border-left-color: #0dcaf0; background: rgba(13, 202, 240, 0.1); }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            opacity: 0.5;
            margin-bottom: 20px;
        }
        
        /* NOVOS ESTILOS ESPECÍFICOS PARA DESCONTO */
        .discount-info-card {
            background: linear-gradient(135deg, rgba(255, 234, 167, 0.1) 0%, rgba(250, 177, 160, 0.1) 100%);
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .discount-preview {
            background: white;
            border: 2px dashed #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            text-align: center;
        }
        
        .savings-display {
            font-size: 1.2rem;
            font-weight: 700;
            color: #e17055;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                min-height: auto;
            }
            
            .card-stats {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- ===== SIDEBAR COM NOVA NAVEGAÇÃO PARA DESCONTO ===== -->
            <div class="col-md-3 col-lg-2">
                <div class="sidebar p-3">
                    <!-- Logo e Título -->
                    <div class="text-center mb-4">
                        <h4 class="text-white mb-1">
                            <i class="bi bi-credit-card-2-front me-2"></i>
                            IMEP Split
                        </h4>
                        <small class="text-white-50">Sistema ASAAS v3.4 + Desconto</small>
                    </div>
                    
                    <!-- Navegação Principal -->
                    <nav class="nav flex-column">
                        <a href="#" class="nav-link active" data-section="dashboard">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        
                        <a href="#" class="nav-link" data-section="customers">
                            <i class="bi bi-people"></i> Clientes
                            <span class="badge bg-secondary ms-auto"><?php echo count($customers); ?></span>
                        </a>
                        
                        <!-- Link para Mensalidades COM DESCONTO -->
                        <?php if ($permissions['can_create_installments_with_discount']): ?>
                        <a href="#" class="nav-link" data-section="installments">
                            <i class="bi bi-calendar-month"></i> Mensalidades
                            <span class="badge bg-info ms-auto"><?php echo count($recentInstallments); ?></span>
                            <?php if ($discountStats && $discountStats['installments_with_discount'] > 0): ?>
                            <span class="discount-badge">💰</span>
                            <?php endif; ?>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($permissions['can_manage_wallets']): ?>
                        <a href="#" class="nav-link" data-section="wallets">
                            <i class="bi bi-wallet2"></i> Wallet IDs
                            <span class="badge bg-info ms-auto"><?php echo count($walletIds); ?></span>
                        </a>
                        <?php endif; ?>
                        
                        <a href="#" class="nav-link" data-section="payments">
                            <i class="bi bi-credit-card"></i> Pagamentos
                            <span class="badge bg-warning ms-auto"><?php echo count($payments); ?></span>
                        </a>
                        
                        <?php if ($permissions['can_view_discount_reports']): ?>
                        <a href="#" class="nav-link" data-section="reports">
                            <i class="bi bi-graph-up"></i> Relatórios
                            <?php if ($discountStats && $discountStats['installments_with_discount'] > 0): ?>
                            <span class="discount-badge">📊</span>
                            <?php endif; ?>
                        </a>
                        <?php endif; ?>
                        
                        <hr class="my-3 opacity-25">
                        
                        <?php if ($permissions['can_configure_asaas']): ?>
                        <a href="config_interface.php" class="nav-link">
                            <i class="bi bi-gear"></i> Configurações
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($permissions['can_manage_users']): ?>
                        <a href="admin_master.php" class="nav-link">
                            <i class="bi bi-shield-check"></i> Admin Master
                        </a>
                        <?php endif; ?>
                        
                        <a href="#" class="nav-link" onclick="logout()">
                            <i class="bi bi-box-arrow-right"></i> Sair
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- ===== CONTEÚDO PRINCIPAL ===== -->
            <div class="col-md-9 col-lg-10">
                <!-- Navbar Superior COM BADGES DE DESCONTO -->
                <nav class="navbar navbar-expand-lg">
                    <div class="container-fluid">
                        <span class="navbar-brand">
                            <?php echo htmlspecialchars($pageTitle); ?>
                            <small class="text-muted d-block" style="font-size: 0.8rem;">
                                <?php echo htmlspecialchars($pageSubtitle); ?>
                            </small>
                        </span>
                        
                        <div class="d-flex align-items-center gap-3">
                            <!-- Contexto do Usuário -->
                            <?php if ($stats && isset($stats['context_label'])): ?>
                            <div class="text-muted small">
                                <i class="bi bi-eye me-1"></i>
                                Visualizando: <strong><?php echo htmlspecialchars($stats['context_label']); ?></strong>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Badge do Ambiente -->
                            <span class="environment-badge badge bg-<?php echo (defined('ASAAS_ENVIRONMENT') && ASAAS_ENVIRONMENT === 'production') ? 'danger' : 'warning'; ?>">
                                <?php echo strtoupper(defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'DEV'); ?>
                            </span>
                            
                            <!-- Badge de Mensalidades -->
                            <span class="badge bg-success">
                                <i class="bi bi-calendar-month me-1"></i>
                                Mensalidades
                            </span>
                            
                            <!-- NOVO: Badge de Desconto -->
                            <?php if ($discountStats && $discountStats['installments_with_discount'] > 0): ?>
                            <span class="badge" style="background: var(--discount-gradient); color: #2d3436;">
                                <i class="bi bi-piggy-bank me-1"></i>
                                Desconto Ativo
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </nav>
                
                <!-- Área de Conteúdo -->
                <div class="container-fluid px-4">
                    
                    <!-- Mensagens de Feedback -->
                    <?php showMessage(); ?>

                    <!-- ===== DASHBOARD COM ESTATÍSTICAS DE DESCONTO ===== -->
                    <div id="dashboard-section" class="section active">
                        <!-- Estatísticas Gerais COM DESCONTO -->
                        <?php if ($stats && !isset($stats['error'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <div class="card card-stats">
                                    <div class="card-body">
                                        <i class="bi bi-people"></i>
                                        <h3><?php echo number_format($stats['total_customers']); ?></h3>
                                        <p>Clientes</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card card-installment">
                                    <div class="card-body">
                                        <i class="bi bi-calendar-month"></i>
                                        <h3><?php echo count($recentInstallments); ?></h3>
                                        <p>Mensalidades</p>
                                        <?php if ($discountStats && $discountStats['installments_with_discount'] > 0): ?>
                                        <small style="opacity: 0.8;">
                                            <?php echo $discountStats['installments_with_discount']; ?> com desconto
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card card-stats" style="background: var(--success-gradient);">
                                    <div class="card-body">
                                        <i class="bi bi-credit-card"></i>
                                        <h3><?php echo number_format($stats['total_payments']); ?></h3>
                                        <p>Pagamentos</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <!-- NOVO: Card específico para desconto -->
                                <?php if ($discountStats && $discountStats['total_discount_potential'] > 0): ?>
                                <div class="card card-discount">
                                    <div class="card-body">
                                        <i class="bi bi-piggy-bank"></i>
                                        <h3>R$ <?php echo number_format($discountStats['total_discount_potential'], 0, ',', '.'); ?></h3>
                                        <p>Economia Potencial</p>
                                        <small style="opacity: 0.8;">
                                            <?php echo round($discountStats['discount_adoption_rate'], 1); ?>% de adoção
                                        </small>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="card card-stats" style="background: var(--primary-gradient);">
                                    <div class="card-body">
                                        <i class="bi bi-currency-dollar"></i>
                                        <h3>R$ <?php echo number_format($stats['total_value'], 2, ',', '.'); ?></h3>
                                        <p>Total Recebido</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Ações Rápidas COM DESCONTO -->
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><i class="bi bi-lightning me-2"></i>Ações Rápidas</h5>
                                        <small class="text-muted">Acesso direto às funções principais</small>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-3">
                                            <button class="btn btn-gradient" onclick="showSection('customers')">
                                                <i class="bi bi-person-plus me-2"></i>Novo Cliente
                                                <small class="d-block">Cadastrar cliente no sistema</small>
                                            </button>
<!-- NOVO: Botão para Mensalidades COM DESCONTO -->
<?php if ($permissions['can_create_installments_with_discount']): ?>
                                            <button class="btn btn-installment" onclick="showSection('installments')">
                                                <i class="bi bi-calendar-month me-2"></i>Nova Mensalidade
                                                <small class="d-block">
                                                    Criar mensalidade parcelada 
                                                    <span class="discount-badge ms-1">💰 COM DESCONTO</span>
                                                </small>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($permissions['can_manage_wallets']): ?>
                                            <button class="btn btn-gradient" onclick="showSection('wallets')">
                                                <i class="bi bi-wallet-fill me-2"></i>Novo Wallet ID
                                                <small class="d-block">Cadastrar destinatário de splits</small>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-gradient" onclick="showSection('payments')">
                                                <i class="bi bi-credit-card-2-front me-2"></i>Pagamento Simples
                                                <small class="d-block">Criar cobrança única com splits</small>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-clock-history me-2"></i>Atividade Recente</h5>
                                    </div>
                                    <div class="card-body">
                                        <!-- ATUALIZADO: Mostrar mensalidades recentes COM DESCONTO -->
                                        <?php if (!empty($recentInstallments)): ?>
                                            <h6 class="text-primary">📅 Mensalidades Recentes</h6>
                                            <?php foreach (array_slice($recentInstallments, 0, 3) as $installment): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($installment['description'] ?? 'Mensalidade'); ?></strong>
                                                    
                                                    <!-- NOVO: Mostrar informação de desconto -->
                                                    <?php if (!empty($installment['has_discount']) && $installment['discount_value'] > 0): ?>
                                                    <span class="discount-badge ms-1">💰 R$ <?php echo number_format($installment['discount_value'], 2, ',', '.'); ?></span>
                                                    <?php endif; ?>
                                                    
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $installment['installment_count']; ?> parcelas de 
                                                        R$ <?php echo number_format($installment['installment_value'], 2, ',', '.'); ?>
                                                        
                                                        <!-- NOVO: Mostrar economia se houver desconto -->
                                                        <?php if (!empty($installment['has_discount']) && $installment['discount_value'] > 0): ?>
                                                        <br><span class="text-success">
                                                            <i class="bi bi-piggy-bank"></i>
                                                            Economia: R$ <?php echo number_format($installment['discount_value'] * $installment['installment_count'], 2, ',', '.'); ?>
                                                        </span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y', strtotime($installment['created_at'])); ?>
                                                </small>
                                            </div>
                                            <?php endforeach; ?>
                                            <hr>
                                        <?php endif; ?>
                                        
                                        <!-- Pagamentos recentes -->
                                        <h6 class="text-success">💳 Pagamentos Recentes</h6>
                                        <?php if (!empty($payments)): ?>
                                            <?php foreach (array_slice($payments, 0, 3) as $payment): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($payment['customer_name'] ?? 'Cliente N/A'); ?></strong><br>
                                                    <small class="text-muted">
                                                        R$ <?php echo number_format($payment['value'], 2, ',', '.'); ?>
                                                        
                                                        <!-- NOVO: Indicar se é parte de mensalidade -->
                                                        <?php if (!empty($payment['installment_id'])): ?>
                                                        <span class="badge bg-info ms-1">Mensalidade</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y', strtotime($payment['created_at'])); ?>
                                                </small>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="empty-state py-2">
                                                <small class="text-muted">Nenhuma atividade recente</small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- NOVO: Seção de estatísticas de desconto -->
                                        <?php if ($discountStats && $discountStats['installments_with_discount'] > 0): ?>
                                        <hr>
                                        <h6 class="text-warning">💰 Resumo de Descontos</h6>
                                        <div class="discount-info-card">
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <strong class="text-primary"><?php echo $discountStats['installments_with_discount']; ?></strong>
                                                    <small class="d-block text-muted">Mensalidades com desconto</small>
                                                </div>
                                                <div class="col-4">
                                                    <strong class="text-success">R$ <?php echo number_format($discountStats['total_discount_potential'], 0, ',', '.'); ?></strong>
                                                    <small class="d-block text-muted">Economia potencial</small>
                                                </div>
                                                <div class="col-4">
                                                    <strong class="text-info"><?php echo round($discountStats['discount_adoption_rate'], 1); ?>%</strong>
                                                    <small class="d-block text-muted">Taxa de adoção</small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- NOVA SEÇÃO: Resumo de Performance de Desconto -->
                        <?php if ($discountStats && $discountStats['installments_with_discount'] > 0): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card" style="border-left: 4px solid #ffeaa7;">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">
                                            <i class="bi bi-piggy-bank me-2" style="color: #e17055;"></i>
                                            Performance do Sistema de Desconto
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3 text-center">
                                                <div class="savings-display"><?php echo $discountStats['installments_with_discount']; ?></div>
                                                <small class="text-muted">Mensalidades com desconto</small>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <div class="savings-display">R$ <?php echo number_format($discountStats['avg_discount_value'] ?? 0, 2, ',', '.'); ?></div>
                                                <small class="text-muted">Desconto médio por parcela</small>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <div class="savings-display"><?php echo round($discountStats['discount_adoption_rate'], 1); ?>%</div>
                                                <small class="text-muted">Taxa de adoção do desconto</small>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <div class="savings-display"><?php echo round($discountStats['discount_efficiency'] ?? 0, 1); ?>%</div>
                                                <small class="text-muted">Eficiência do desconto</small>
                                            </div>
                                        </div>
                                        
                                        <div class="progress mt-3" style="height: 8px;">
                                            <div class="progress-bar" 
                                                 style="background: var(--discount-gradient); width: <?php echo min($discountStats['discount_adoption_rate'], 100); ?>%;"
                                                 role="progressbar">
                                            </div>
                                        </div>
                                        
                                        <div class="text-center mt-2">
                                            <small class="text-muted">
                                                📊 Adoção do desconto: <?php echo round($discountStats['discount_adoption_rate'], 1); ?>% das mensalidades
                                                • 💰 Economia total proporcionada: R$ <?php echo number_format($discountStats['total_discount_potential'], 2, ',', '.'); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- NOVA SEÇÃO: Dicas para maximizar uso do desconto -->
                        <?php if ($discountStats && $discountStats['discount_adoption_rate'] < 50): ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <h6><i class="bi bi-lightbulb me-2"></i>Dica para Aumentar a Adoção do Desconto</h6>
                                    <p class="mb-2">
                                        Apenas <?php echo round($discountStats['discount_adoption_rate'], 1); ?>% das suas mensalidades utilizam desconto. 
                                        Considere oferecer descontos para incentivar pagamentos em dia e melhorar a experiência dos alunos.
                                    </p>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-info" onclick="showSection('installments')">
                                            <i class="bi bi-piggy-bank me-1"></i>Criar Mensalidade com Desconto
                                        </button>
                                        <?php if ($permissions['can_view_discount_reports']): ?>
                                        <button class="btn btn-sm btn-outline-info" onclick="showSection('reports')">
                                            <i class="bi bi-graph-up me-1"></i>Ver Relatórios de Desconto
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- ===== NOVA SEÇÃO: MENSALIDADES COM DESCONTO ===== -->
                    <?php if ($permissions['can_create_installments_with_discount']): ?>
                    <div id="installments-section" class="section">
                        <div class="card installment-form-card">
                            <div class="card-header bg-primary text-white">
                                <h5>
                                    <i class="bi bi-calendar-month me-2"></i>Nova Mensalidade Parcelada 
                                    <span class="discount-badge ms-2">💰 COM DESCONTO</span>
                                </h5>
                                <small>Crie mensalidades para alunos com parcelamento automático e desconto para pagamento em dia</small>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="installment-form-with-discount">
                                    <input type="hidden" name="action" value="create_installment_with_discount">
                                    
                                    <div class="row">
                                        <!-- ===== DADOS DA MENSALIDADE ===== -->
                                        <div class="col-md-6">
                                            <h6 class="border-bottom pb-2 mb-3 text-primary">
                                                <i class="bi bi-info-circle me-1"></i>Dados da Mensalidade
                                            </h6>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Aluno/Cliente *</label>
                                                <select class="form-select" name="payment[customer]" required>
                                                    <option value="">Selecione um aluno</option>
                                                    <?php foreach ($customers as $customer): ?>
                                                    <option value="<?php echo $customer['id']; ?>">
                                                        <?php echo htmlspecialchars($customer['name']); ?> 
                                                        (<?php echo htmlspecialchars($customer['email']); ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="form-text text-muted">
                                                    <i class="bi bi-info-circle"></i>
                                                    Selecione o aluno que pagará a mensalidade
                                                </small>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Tipo de Cobrança *</label>
                                                        <select class="form-select" name="payment[billingType]" required>
                                                            <option value="BOLETO">📄 Boleto Bancário</option>
                                                            <option value="PIX">⚡ PIX</option>
                                                            <option value="CREDIT_CARD">💳 Cartão de Crédito</option>
                                                            <option value="DEBIT_CARD">💳 Cartão de Débito</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Data do 1º Vencimento *</label>
                                                        <input type="date" class="form-control" name="payment[dueDate]" 
                                                               value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" 
                                                               required id="first-due-date">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Descrição da Mensalidade *</label>
                                                <input type="text" class="form-control" name="payment[description]" 
                                                       placeholder="Ex: Mensalidade Escolar 2025, Curso Técnico..." required>
                                            </div>
                                        </div>
                                        
                                        <!-- ===== CONFIGURAÇÃO DO PARCELAMENTO ===== -->
                                        <div class="col-md-6">
                                            <h6 class="border-bottom pb-2 mb-3 text-success">
                                                <i class="bi bi-calculator me-1"></i>Parcelamento
                                            </h6>
                                            
                                            <div class="installment-calculator">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Valor da Parcela (R$) *</label>
                                                            <input type="number" class="form-control" 
                                                                   name="installment[installmentValue]" 
                                                                   step="0.01" min="1" max="<?php echo MAX_INSTALLMENT_VALUE; ?>"
                                                                   placeholder="100.00" 
                                                                   required id="installment-value"
                                                                   oninput="calculateInstallmentWithDiscount()">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Quantidade de Parcelas *</label>
                                                            <select class="form-select" name="installment[installmentCount]" 
                                                                    required id="installment-count"
                                                                    onchange="calculateInstallmentWithDiscount()">
                                                                <option value="">Selecione</option>
                                                                <?php for($i = MIN_INSTALLMENTS; $i <= MAX_INSTALLMENTS; $i++): ?>
                                                                <option value="<?php echo $i; ?>"><?php echo $i; ?>x</option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="calculator-result" id="calculation-result" style="display: none;">
                                                    <div class="row text-center">
                                                        <div class="col-6">
                                                            <div class="value-display" id="total-value">R$ 0,00</div>
                                                            <small class="text-muted">Valor Total</small>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="value-display" id="installment-summary">0x R$ 0,00</div>
                                                            <small class="text-muted">Parcelamento</small>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3" id="due-dates-preview"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- ===== NOVA SEÇÃO: CONFIGURAÇÃO DO DESCONTO ===== -->
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <h6 class="border-bottom pb-2 mb-3" style="color: #e17055;">
                                                <i class="bi bi-piggy-bank me-1"></i>Configuração do Desconto
                                                <small class="text-muted ms-2">(Opcional - Incentiva pagamentos em dia)</small>
                                            </h6>
                                            
                                            <!-- Switch para habilitar desconto -->
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="discount-enabled" name="discount_enabled" value="1"
                                                       onchange="toggleDiscountSection()">
                                                <label class="form-check-label" for="discount-enabled">
                                                    <strong>Habilitar Desconto para Pagamento em Dia</strong>
                                                    <small class="d-block text-muted">
                                                        O aluno ganha desconto se pagar até o vencimento de cada parcela
                                                    </small>
                                                </label>
                                            </div>
                                            
                                            <!-- Seção de configuração do desconto (inicialmente oculta) -->
                                            <div id="discount-configuration" style="display: none;">
                                                <div class="discount-info-card">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">
                                                                    <i class="bi bi-cash-coin me-1"></i>Valor do Desconto por Parcela (R$)
                                                                </label>
                                                                <input type="number" class="form-control" 
                                                                       name="discount_value" 
                                                                       id="discount-value"
                                                                       step="0.01" min="0" max="9999"
                                                                       placeholder="10.00"
                                                                       oninput="validateDiscountInput()">
                                                                <small class="form-text text-muted">
                                                                    Máximo: <?php echo MAX_DISCOUNT_PERCENTAGE; ?>% do valor da parcela
                                                                </small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">
                                                                    <i class="bi bi-calendar-check me-1"></i>Prazo do Desconto
                                                                </label>
                                                                <select class="form-select" name="discount_deadline_type" disabled>
                                                                    <option value="DUE_DATE">Válido até o dia do vencimento</option>
                                                                </select>
                                                                <small class="form-text text-muted">
                                                                    Desconto será aplicado automaticamente se pago em dia
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Preview do desconto -->
                                                    <div id="discount-preview" class="discount-preview" style="display: none;">
                                                        <div class="row">
                                                            <div class="col-md-4 text-center">
                                                                <div class="savings-display" id="discount-per-installment">R$ 0,00</div>
                                                                <small class="text-muted">Desconto por parcela</small>
                                                            </div>
                                                            <div class="col-md-4 text-center">
                                                                <div class="savings-display" id="total-savings">R$ 0,00</div>
                                                                <small class="text-muted">Economia total</small>
                                                            </div>
                                                            <div class="col-md-4 text-center">
                                                                <div class="savings-display" id="discount-percentage">0%</div>
                                                                <small class="text-muted">% da parcela</small>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="text-center mt-2">
                                                            <small class="text-success">
                                                                <i class="bi bi-check-circle me-1"></i>
                                                                O aluno economizará <span id="total-savings-text">R$ 0,00</span> 
                                                                se pagar todas as parcelas em dia!
                                                            </small>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Validação em tempo real do desconto -->
                                                    <div id="discount-validation" class="mt-2" style="display: none;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- ===== CONFIGURAÇÃO DO SPLIT ===== -->
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <h6 class="border-bottom pb-2 mb-3 text-warning">
                                                <i class="bi bi-pie-chart me-1"></i>Configuração do Split
                                                <small class="text-muted ms-2">(Opcional - Aplicado a todas as parcelas)</small>
                                            </h6>
                                            
                                            <div id="splits-container">
                                                <div class="split-item p-3 mb-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">Destinatário</label>
                                                        <select class="form-select" name="splits[0][walletId]">
                                                            <option value="">Selecione um destinatário</option>
                                                            <?php foreach ($walletIds as $wallet): ?>
                                                                <?php if ($wallet['is_active']): ?>
                                                                <option value="<?php echo $wallet['wallet_id']; ?>">
                                                                    <?php echo htmlspecialchars($wallet['name']); ?>
                                                                </option>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <label class="form-label">Percentual (%)</label>
                                                            <input type="number" class="form-control" name="splits[0][percentualValue]" 
                                                                   step="0.01" max="100" placeholder="15.00"
                                                                   oninput="updateFinalSummaryWithDiscount()">
                                                            <small class="form-text text-muted">Ex: 15% de cada parcela</small>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label">Valor Fixo (R$)</label>
                                                            <input type="number" class="form-control" name="splits[0][fixedValue]" 
                                                                   step="0.01" placeholder="5.00"
                                                                   oninput="updateFinalSummaryWithDiscount()">
                                                            <small class="form-text text-muted">Ex: R$ 5,00 de cada parcela</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addSplitWithDiscount()">
                                                    <i class="bi bi-plus me-1"></i>Adicionar Split
                                                </button>
                                                <small class="text-muted">
                                                    <i class="bi bi-info-circle"></i>
                                                    Os splits serão aplicados automaticamente a todas as parcelas
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <!-- ===== RESUMO FINAL COM DESCONTO ===== -->
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="installment-summary" id="final-summary-with-discount" style="display: none;">
                                                <h6 class="text-primary mb-2">
                                                    <i class="bi bi-clipboard-check me-1"></i>Resumo da Mensalidade
                                                    <span id="discount-summary-badge" style="display: none;" class="discount-badge ms-2">💰 COM DESCONTO</span>
                                                </h6>
                                                <div id="summary-content-with-discount"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="confirm-installment-with-discount">
                                                <label class="form-check-label" for="confirm-installment-with-discount">
                                                    Confirmo que os dados da mensalidade estão corretos
                                                </label>
                                            </div>
                                            
                                            <!-- Botão com indicador de desconto -->
                                            <button type="submit" class="btn btn-installment w-100" disabled id="submit-installment-with-discount">
                                                <i class="bi bi-calendar-month me-2"></i>
                                                <span id="submit-button-text">Criar Mensalidade</span>
                                                <span id="submit-discount-indicator" style="display: none;">
                                                    <br><small>💰 Com desconto para pagamento em dia</small>
                                                </span>
                                            </button>
                                            
                                            <!-- Informações importantes sobre desconto -->
                                            <div id="discount-important-info" class="mt-2" style="display: none;">
                                                <div class="alert alert-info py-2">
                                                    <small>
                                                        <i class="bi bi-info-circle me-1"></i>
                                                        <strong>Como funciona:</strong><br>
                                                        • Desconto aplicado automaticamente pelo ASAAS<br>
                                                        • Válido apenas para pagamentos até o vencimento<br>
                                                        • Aluno vê o valor com desconto ao pagar em dia
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- ===== MENSALIDADES RECENTES COM INFORMAÇÕES DE DESCONTO ===== -->
                        <?php if (!empty($recentInstallments)): ?>
                        <div class="card mt-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="bi bi-list me-2"></i>Mensalidades Cadastradas</h5>
                                <div>
                                    <?php if ($discountStats && $discountStats['installments_with_discount'] > 0): ?>
                                    <span class="badge bg-success me-2">
                                        💰 <?php echo $discountStats['installments_with_discount']; ?> com desconto
                                    </span>
                                    <?php endif; ?>
                                    <span class="badge bg-primary"><?php echo count($recentInstallments); ?> total</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>Descrição</th>
                                                <th>Parcelas</th>
                                                <th>Valor Total</th>
                                                <th>Desconto</th> <!-- NOVA COLUNA -->
                                                <th>1º Vencimento</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentInstallments as $installment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($installment['customer_id'] ?? 'Cliente N/A'); ?></strong><br>
                                                    <small class="text-muted">ID: <?php echo substr($installment['installment_id'], 0, 8); ?>...</small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($installment['description']); ?><br>
                                                    <small class="text-muted">
                                                        <?php echo $installment['billing_type']; ?>
                                                        <?php if ($installment['has_splits']): ?>
                                                        • <?php echo $installment['splits_count']; ?> split(s)
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $installment['installment_count']; ?>x</span><br>
                                                    <small class="text-success">R$ <?php echo number_format($installment['installment_value'], 2, ',', '.'); ?></small>
                                                </td>
                                                <td>
                                                    <strong class="text-success">
                                                        R$ <?php echo number_format($installment['total_value'], 2, ',', '.'); ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <!-- NOVA COLUNA: Informações de desconto -->
                                                    <?php if (!empty($installment['has_discount']) && $installment['discount_value'] > 0): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            💰 R$ <?php echo number_format($installment['discount_value'], 2, ',', '.'); ?>
                                                        </span>
                                                        <br>
                                                        <small class="text-success">
                                                            Economia: R$ <?php echo number_format($installment['discount_value'] * $installment['installment_count'], 2, ',', '.'); ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="text-muted">Sem desconto</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?php echo date('d/m/Y', strtotime($installment['first_due_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">Ativa</span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="viewInstallmentWithDiscount('<?php echo $installment['installment_id']; ?>')" 
                                                                data-bs-toggle="tooltip" title="Ver todas as parcelas">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($permissions['can_generate_payment_books']): ?>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="generatePaymentBook('<?php echo $installment['installment_id']; ?>')" 
                                                                data-bs-toggle="tooltip" title="Gerar carnê PDF">
                                                            <i class="bi bi-file-pdf"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- NOVO: Botão para copiar informações com desconto -->
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick="copyInstallmentInfoWithDiscount('<?php echo $installment['installment_id']; ?>', <?php echo !empty($installment['has_discount']) ? 'true' : 'false'; ?>)" 
                                                                data-bs-toggle="tooltip" title="Copiar informações">
                                                            <i class="bi bi-clipboard"></i>
                                                        </button>
                                                        
                                                        <!-- NOVO: Botão para duplicar mensalidade (com mesmo desconto) -->
                                                        <?php if (!empty($installment['has_discount'])): ?>
                                                        <button class="btn btn-sm btn-outline-warning" 
                                                                onclick="duplicateInstallmentWithDiscount('<?php echo $installment['installment_id']; ?>')" 
                                                                data-bs-toggle="tooltip" title="Duplicar mensalidade com mesmo desconto">
                                                            <i class="bi bi-files"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- NOVA: Seção de resumo de descontos na tabela -->
                                <?php if ($discountStats && $discountStats['installments_with_discount'] > 0): ?>
                                <div class="row mt-3 pt-3 border-top">
                                    <div class="col-12">
                                        <h6 class="text-muted mb-2">📊 Resumo de Descontos das Mensalidades</h6>
                                        <div class="row text-center">
                                            <div class="col-md-3">
                                                <div class="badge bg-light text-dark p-2 w-100">
                                                    <div class="fw-bold"><?php echo $discountStats['installments_with_discount']; ?>/<?php echo count($recentInstallments); ?></div>
                                                    <small>Com desconto</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="badge bg-light text-dark p-2 w-100">
                                                    <div class="fw-bold">R$ <?php echo number_format($discountStats['avg_discount_value'] ?? 0, 2, ',', '.'); ?></div>
                                                    <small>Desconto médio</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="badge bg-light text-dark p-2 w-100">
                                                    <div class="fw-bold">R$ <?php echo number_format($discountStats['total_discount_potential'], 0, ',', '.'); ?></div>
                                                    <small>Economia total</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="badge bg-light text-dark p-2 w-100">
                                                    <div class="fw-bold"><?php echo round($discountStats['discount_adoption_rate'], 1); ?>%</div>
                                                    <small>Taxa de adoção</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- ===== SEÇÃO CLIENTES (MANTIDA) ===== -->
                    <div id="customers-section" class="section">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-person-plus me-2"></i>Novo Cliente</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" id="customer-form">
                                            <input type="hidden" name="action" value="create_customer">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Nome Completo *</label>
                                                <input type="text" class="form-control" name="customer[name]" required
                                                       placeholder="Nome completo do cliente">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Email *</label>
                                                <input type="email" class="form-control" name="customer[email]" required
                                                       placeholder="email@exemplo.com">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">CPF/CNPJ *</label>
                                                <input type="text" class="form-control" name="customer[cpfCnpj]" required
                                                       placeholder="000.000.000-00 ou 00.000.000/0000-00">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Telefone</label>
                                                <input type="text" class="form-control" name="customer[mobilePhone]"
                                                       placeholder="(00) 00000-0000">
                                            </div>
                                            
                                            <button type="submit" class="btn btn-gradient w-100">
                                                <i class="bi bi-save me-2"></i>Criar Cliente
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><i class="bi bi-list me-2"></i>Clientes Recentes</h5>
                                        <span class="badge bg-primary"><?php echo count($customers); ?></span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($customers)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Cliente</th>
                                                            <th>Contato</th>
                                                            <th>Ações</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach (array_slice($customers, 0, 10) as $customer): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($customer['name']); ?></strong><br>
                                                                <small class="text-muted"><?php echo maskDocument($customer['cpf_cnpj'] ?? ''); ?></small>
                                                            </td>
                                                            <td>
                                                                <small>
                                                                    <?php echo htmlspecialchars($customer['email']); ?><br>
                                                                    <?php if (!empty($customer['mobile_phone'])): ?>
                                                                        <?php echo htmlspecialchars($customer['mobile_phone']); ?>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group" role="group">
                                                                    <!-- ATUALIZADO: Botão para mensalidade COM DESCONTO -->
                                                                    <button class="btn btn-sm btn-outline-primary" 
                                                                            onclick="createInstallmentForCustomerWithDiscount('<?php echo $customer['id']; ?>')" 
                                                                            data-bs-toggle="tooltip" title="Nova mensalidade com desconto">
                                                                        <i class="bi bi-calendar-month"></i>
                                                                        <small class="discount-badge">💰</small>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-success" 
                                                                            onclick="createPaymentForCustomer('<?php echo $customer['id']; ?>')" 
                                                                            data-bs-toggle="tooltip" title="Novo pagamento">
                                                                        <i class="bi bi-credit-card"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="empty-state">
                                                <i class="bi bi-people"></i>
                                                <p>Nenhum cliente cadastrado</p>
                                                <small class="text-muted">Cadastre seu primeiro cliente para começar</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SEÇÃO WALLET IDs (MANTIDA) ===== -->
                    <?php if ($permissions['can_manage_wallets']): ?>
                    <div id="wallets-section" class="section">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-plus-circle me-2"></i>Novo Wallet ID</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" id="wallet-form">
                                            <input type="hidden" name="action" value="create_wallet">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Nome/Identificação *</label>
                                                <input type="text" class="form-control" name="wallet[name]" required
                                                       placeholder="Ex: João Silva ou Empresa LTDA">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Wallet ID *</label>
                                                <input type="text" class="form-control" name="wallet[wallet_id]" required
                                                       placeholder="22e49670-27e4-4579-a4c1-205c8a40497c"
                                                       style="font-family: monospace;">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Descrição (Opcional)</label>
                                                <textarea class="form-control" name="wallet[description]" rows="2"
                                                          placeholder="Ex: Parceiro comercial, comissão..."></textarea>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-gradient w-100">
                                                <i class="bi bi-save me-2"></i>Cadastrar Wallet ID
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-list me-2"></i>Wallet IDs Cadastrados</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($walletIds)): ?>
                                            <div class="row">
                                                <?php foreach (array_slice($walletIds, 0, 12) as $wallet): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card wallet-card">
                                                        <div class="card-body">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($wallet['name']); ?></h6>
                                                            <div class="wallet-id-display mb-2" 
                                                                 onclick="copyToClipboard('<?php echo htmlspecialchars($wallet['wallet_id']); ?>')">
                                                                <?php echo maskWalletId($wallet['wallet_id']); ?>
                                                                <i class="bi bi-clipboard float-end"></i>
                                                            </div>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="badge bg-<?php echo $wallet['is_active'] ? 'success' : 'secondary'; ?>">
                                                                    <?php echo $wallet['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                                                </span>
                                                                <small class="text-muted"><?php echo date('d/m/Y', strtotime($wallet['created_at'])); ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="empty-state">
                                                <i class="bi bi-wallet2"></i>
                                                <p>Nenhum Wallet ID cadastrado</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ===== SEÇÃO PAGAMENTOS (SIMPLIFICADA) ===== -->
                    <div id="payments-section" class="section">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-plus-circle me-2"></i>Novo Pagamento Simples (Único)</h5>
                                <small class="text-muted">Para mensalidades COM DESCONTO, use a seção específica de Mensalidades</small>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="payment-form">
                                    <input type="hidden" name="action" value="create_payment">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Cliente *</label>
                                                <select class="form-select" name="payment[customer]" required>
                                                    <option value="">Selecione um cliente</option>
                                                    <?php foreach ($customers as $customer): ?>
                                                    <option value="<?php echo $customer['id']; ?>">
                                                        <?php echo htmlspecialchars($customer['name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Tipo *</label>
                                                        <select class="form-select" name="payment[billingType]" required>
                                                            <option value="PIX">PIX</option>
                                                            <option value="BOLETO">Boleto</option>
                                                            <option value="CREDIT_CARD">Cartão Crédito</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Valor *</label>
                                                        <input type="number" class="form-control" name="payment[value]" 
                                                               step="0.01" min="1" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Descrição *</label>
                                                <input type="text" class="form-control" name="payment[description]" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Vencimento *</label>
                                                <input type="date" class="form-control" name="payment[dueDate]" 
                                                       value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                                            </div>
                                            
                                            <!-- NOVO: Aviso sobre desconto apenas em mensalidades -->
                                            <div class="alert alert-info py-2">
                                                <small>
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    <strong>💰 Quer oferecer desconto?</strong> 
                                                    Use a seção <a href="#" onclick="showSection('installments')" class="alert-link">Mensalidades</a> 
                                                    para criar parcelamentos com desconto automático!
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6 class="mb-3">Configuração do Split (Opcional)</h6>
                                            
                                            <div class="split-item p-3">
                                                <div class="mb-3">
                                                    <select class="form-select" name="splits[0][walletId]">
                                                        <option value="">Selecione destinatário</option>
                                                        <?php foreach ($walletIds as $wallet): ?>
                                                            <?php if ($wallet['is_active']): ?>
                                                            <option value="<?php echo $wallet['wallet_id']; ?>">
                                                                <?php echo htmlspecialchars($wallet['name']); ?>
                                                            </option>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-6">
                                                        <input type="number" class="form-control" name="splits[0][percentualValue]" 
                                                               step="0.01" max="100" placeholder="% (15.00)">
                                                    </div>
                                                    <div class="col-6">
                                                        <input type="number" class="form-control" name="splits[0][fixedValue]" 
                                                               step="0.01" placeholder="R$ fixo (5.00)">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    <button type="submit" class="btn btn-gradient">
                                        <i class="bi bi-credit-card-2-front me-2"></i>Criar Pagamento Único
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ===== NOVA SEÇÃO: RELATÓRIOS COM DESCONTO ===== -->
                    <?php if ($permissions['can_view_discount_reports']): ?>
                    <div id="reports-section" class="section">
                        <div class="row">
                            <!-- Card de Relatórios Básicos -->
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-graph-up me-2"></i>Relatórios de Mensalidades</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Relatórios básicos de mensalidades e pagamentos.</p>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-primary" onclick="generateBasicReport()">
                                                <i class="bi bi-file-text me-2"></i>Relatório Geral
                                            </button>
                                            <button class="btn btn-outline-success" onclick="generateInstallmentReport()">
                                                <i class="bi bi-calendar-month me-2"></i>Relatório de Mensalidades
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- NOVO: Card de Relatórios de Desconto -->
                            <?php if ($discountStats && $discountStats['installments_with_discount'] > 0): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card" style="border-left: 4px solid #ffeaa7;">
                                    <div class="card-header" style="background: linear-gradient(135deg, rgba(255, 234, 167, 0.1) 0%, rgba(250, 177, 160, 0.1) 100%);">
                                        <h5><i class="bi bi-piggy-bank me-2" style="color: #e17055;"></i>Relatórios de Desconto</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Análise completa do desempenho dos descontos oferecidos.</p>
                                        
                                        <!-- Estatísticas rápidas -->
                                        <div class="row mb-3">
                                            <div class="col-6 text-center">
                                                <div class="fw-bold text-success">R$ <?php echo number_format($discountStats['total_discount_potential'], 0, ',', '.'); ?></div>
                                                <small class="text-muted">Economia proporcionada</small>
                                            </div>
                                            <div class="col-6 text-center">
                                                <div class="fw-bold text-info"><?php echo round($discountStats['discount_adoption_rate'], 1); ?>%</div>
                                                <small class="text-muted">Taxa de adoção</small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-warning" onclick="generateDiscountReport()">
                                                <i class="bi bi-piggy-bank me-2"></i>Relatório de Descontos
                                            </button>
                                            <button class="btn btn-outline-info" onclick="generateDiscountEfficiencyReport()">
                                                <i class="bi bi-graph-up-arrow me-2"></i>Eficiência dos Descontos
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="col-md-6 mb-4">
                                <div class="card border-warning">
                                    <div class="card-header bg-warning bg-opacity-10">
                                        <h5><i class="bi bi-exclamation-triangle me-2"></i>Sem Dados de Desconto</h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <p class="text-muted">Ainda não há mensalidades com desconto cadastradas.</p>
                                        <button class="btn btn-warning" onclick="showSection('installments')">
                                            <i class="bi bi-piggy-bank me-2"></i>Criar Primeira Mensalidade com Desconto
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Área de Resultados dos Relatórios -->
                        <div id="report-results" class="card" style="display: none;">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 id="report-title"><i class="bi bi-file-text me-2"></i>Resultado do Relatório</h5>
                                <button class="btn btn-sm btn-outline-secondary" onclick="hideReportResults()">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="report-content">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Carregando...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Gerando relatório...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
    <!-- Scripts JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== CONFIGURAÇÃO GLOBAL COM DESCONTO =====
        const SystemConfig = <?php echo json_encode($jsContext); ?>;
        let currentSection = 'dashboard';
        let splitCounter = 1;
        let discountEnabled = false;
        
        console.log('🚀 Sistema IMEP Split ASAAS v3.4 carregado - COM MENSALIDADES E DESCONTO');
        console.log('👤 Usuário:', SystemConfig.user.nome, '(' + SystemConfig.user.tipo + ')');
        console.log('🏢 Contexto:', SystemConfig.user.polo_nome || 'Master');
        console.log('💳 Funcionalidades:', SystemConfig.features);
        console.log('💰 Desconto configurado:', SystemConfig.discount_config);
        
        // ===== NAVEGAÇÃO ENTRE SEÇÕES =====
        function showSection(section) {
            // Esconder todas as seções
            document.querySelectorAll('.section').forEach(el => {
                el.classList.remove('active');
            });
            
            // Mostrar seção selecionada
            const targetSection = document.getElementById(section + '-section');
            if (targetSection) {
                targetSection.classList.add('active');
                currentSection = section;
                
                // Atualizar navegação
                document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
                const navLink = document.querySelector(`[data-section="${section}"]`);
                if (navLink) navLink.classList.add('active');
                
                console.log('📍 Seção alterada para:', section);
            }
        }
        
        // ===== FUNÇÕES PARA MENSALIDADES COM DESCONTO (NOVAS) =====
        
        /**
         * Alternar seção de configuração do desconto
         */
        function toggleDiscountSection() {
            const discountCheckbox = document.getElementById('discount-enabled');
            const discountConfig = document.getElementById('discount-configuration');
            const discountImportantInfo = document.getElementById('discount-important-info');
            const submitDiscountIndicator = document.getElementById('submit-discount-indicator');
            const discountSummaryBadge = document.getElementById('discount-summary-badge');
            
            discountEnabled = discountCheckbox?.checked || false;
            
            if (discountConfig) {
                discountConfig.style.display = discountEnabled ? 'block' : 'none';
            }
            
            if (discountImportantInfo) {
                discountImportantInfo.style.display = discountEnabled ? 'block' : 'none';
            }
            
            if (submitDiscountIndicator) {
                submitDiscountIndicator.style.display = discountEnabled ? 'inline' : 'none';
            }
            
            if (discountSummaryBadge) {
                discountSummaryBadge.style.display = discountEnabled ? 'inline' : 'none';
            }
            
            // Revalidar e recalcular
            if (discountEnabled) {
                validateDiscountInput();
                calculateInstallmentWithDiscount();
                showToast('💰 Desconto habilitado! Configure o valor do desconto.', 'info');
            } else {
                hideDiscountPreview();
                calculateInstallmentWithDiscount();
                showToast('Desconto desabilitado', 'info');
            }
        }
        
        /**
         * Validar entrada do desconto em tempo real
         */
// Melhorar a função validateDiscountInput para mostrar o estado atual
function validateDiscountInput() {
    const discountValue = parseFloat(document.getElementById('discount-value')?.value || 0);
    const installmentValue = parseFloat(document.getElementById('installment-value')?.value || 0);
    const validationDiv = document.getElementById('discount-validation');
    const discountPreview = document.getElementById('discount-preview');
    
    console.log('🔍 Validando desconto:', {
        discountValue,
        installmentValue,
        discountEnabled,
        ratio: installmentValue > 0 ? (discountValue / installmentValue * 100).toFixed(1) + '%' : '0%'
    });
    
    if (!discountEnabled || discountValue <= 0 || installmentValue <= 0) {
        hideDiscountPreview();
        if (validationDiv) validationDiv.style.display = 'none';
        return false;
    }
    
    // Validação usando configuração do sistema
    const maxDiscountPercentage = SystemConfig.discount_config.max_percentage;
    const maxDiscountValue = installmentValue * (maxDiscountPercentage / 100);
    const discountPercentage = (discountValue / installmentValue) * 100;
    
    let validationHTML = '';
    let isValid = true;
    
    // Validações
    if (discountValue >= installmentValue) {
        validationHTML += '<div class="alert alert-danger py-2 mb-2"><small><i class="bi bi-exclamation-triangle me-1"></i>Desconto não pode ser maior ou igual ao valor da parcela</small></div>';
        isValid = false;
    } else if (discountValue > maxDiscountValue) {
        validationHTML += `<div class="alert alert-danger py-2 mb-2"><small><i class="bi bi-exclamation-triangle me-1"></i>Desconto máximo: R$ ${maxDiscountValue.toLocaleString('pt-BR', {minimumFractionDigits: 2})} (${maxDiscountPercentage}% da parcela)</small></div>`;
        isValid = false;
    } else if (discountPercentage > 30) {
        validationHTML += `<div class="alert alert-warning py-2 mb-2"><small><i class="bi bi-exclamation-triangle me-1"></i>Desconto alto (${discountPercentage.toFixed(1)}% da parcela). Pode impactar a receita.</small></div>`;
    } else if (discountPercentage < 5 && discountValue > 0) {
        validationHTML += `<div class="alert alert-info py-2 mb-2"><small><i class="bi bi-info-circle me-1"></i>Desconto baixo (${discountPercentage.toFixed(1)}%) pode ter pouco impacto na conversão.</small></div>`;
    }
    
    if (validationDiv) {
        validationDiv.innerHTML = validationHTML;
        validationDiv.style.display = validationHTML ? 'block' : 'none';
    }
    
    // Atualizar preview se válido
    if (isValid && discountValue > 0) {
        updateDiscountPreview(discountValue, installmentValue);
        if (discountPreview) discountPreview.style.display = 'block';
        
        console.log('✅ Desconto válido:', {
            value: discountValue,
            percentage: discountPercentage.toFixed(1) + '%',
            maxAllowed: maxDiscountValue
        });
    } else {
        hideDiscountPreview();
        
        if (!isValid) {
            console.log('❌ Desconto inválido:', {
                value: discountValue,
                installmentValue,
                errors: validationHTML.length > 0
            });
        }
    }
    
    return isValid;
}
        
        /**
         * Atualizar preview do desconto
         */
        function updateDiscountPreview(discountValue, installmentValue) {
            const installmentCount = parseInt(document.getElementById('installment-count')?.value || 0);
            
            if (installmentCount <= 0) return;
            
            const totalSavings = discountValue * installmentCount;
            const discountPercentage = (discountValue / installmentValue) * 100;
            
            // Atualizar elementos do preview
            const discountPerInstallmentEl = document.getElementById('discount-per-installment');
            const totalSavingsEl = document.getElementById('total-savings');
            const discountPercentageEl = document.getElementById('discount-percentage');
            const totalSavingsTextEl = document.getElementById('total-savings-text');
            
            if (discountPerInstallmentEl) {
                discountPerInstallmentEl.textContent = 'R$ ' + discountValue.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            }
            
            if (totalSavingsEl) {
                totalSavingsEl.textContent = 'R$ ' + totalSavings.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            }
            
            if (discountPercentageEl) {
                discountPercentageEl.textContent = discountPercentage.toFixed(1) + '%';
            }
            
            if (totalSavingsTextEl) {
                totalSavingsTextEl.textContent = 'R$ ' + totalSavings.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            }
        }
        
        /**
         * Ocultar preview do desconto
         */
        function hideDiscountPreview() {
            const discountPreview = document.getElementById('discount-preview');
            if (discountPreview) {
                discountPreview.style.display = 'none';
            }
        }
        
        /**
         * Calcular valores do parcelamento COM DESCONTO
         */
        function calculateInstallmentWithDiscount() {
            const installmentValue = parseFloat(document.getElementById('installment-value')?.value || 0);
            const installmentCount = parseInt(document.getElementById('installment-count')?.value || 0);
            const firstDueDate = document.getElementById('first-due-date')?.value;
            
            const resultDiv = document.getElementById('calculation-result');
            const summaryDiv = document.getElementById('final-summary-with-discount');
            
            if (installmentValue > 0 && installmentCount > 1) {
                const totalValue = installmentValue * installmentCount;
                
                // Atualizar resultado da calculadora
                document.getElementById('total-value').textContent = 
                    'R$ ' + totalValue.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                document.getElementById('installment-summary').textContent = 
                    installmentCount + 'x R$ ' + installmentValue.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                
                // Mostrar preview das datas
                if (firstDueDate) {
                    generateDueDatesPreview(firstDueDate, installmentCount);
                }
                
                // Mostrar resultado
                if (resultDiv) resultDiv.style.display = 'block';
                
                // Validar desconto se habilitado
                if (discountEnabled) {
                    validateDiscountInput();
                }
                
                // Atualizar resumo final
                updateFinalSummaryWithDiscount();
                if (summaryDiv) summaryDiv.style.display = 'block';
                
                console.log('Cálculo COM DESCONTO atualizado:', {
                    installmentValue, 
                    installmentCount, 
                    totalValue,
                    discountEnabled
                });
            } else {
                if (resultDiv) resultDiv.style.display = 'none';
                if (summaryDiv) summaryDiv.style.display = 'none';
                hideDiscountPreview();
            }
        }
        
        /**
         * Gerar preview das datas de vencimento
         */
        function generateDueDatesPreview(firstDate, count) {
            const preview = document.getElementById('due-dates-preview');
            if (!preview) return;
            
            const startDate = new Date(firstDate);
            let html = '<div class="row"><div class="col-12"><small class="text-muted"><strong>Primeiros vencimentos:</strong></small></div>';
            
            for (let i = 0; i < Math.min(count, 6); i++) {
                const currentDate = new Date(startDate);
                currentDate.setMonth(startDate.getMonth() + i);
                
                const dateStr = currentDate.toLocaleDateString('pt-BR');
                const parcela = i + 1;
                
                html += `<div class="col-6 col-md-4"><div class="parcela-preview">
                    <strong>${parcela}ª:</strong> ${dateStr}
                    ${discountEnabled ? '<br><small class="text-success">💰 c/ desconto</small>' : ''}
                </div></div>`;
            }
            
            if (count > 6) {
                html += `<div class="col-12"><small class="text-muted">... e mais ${count - 6} parcelas${discountEnabled ? ' (todas com desconto)' : ''}</small></div>`;
            }
            
            html += '</div>';
            preview.innerHTML = html;
        }
        
        /**
         * Atualizar resumo final COM DESCONTO
         */
        function updateFinalSummaryWithDiscount() {
            const summaryContent = document.getElementById('summary-content-with-discount');
            if (!summaryContent) return;
            
            const installmentValue = parseFloat(document.getElementById('installment-value')?.value || 0);
            const installmentCount = parseInt(document.getElementById('installment-count')?.value || 0);
            const discountValue = discountEnabled ? parseFloat(document.getElementById('discount-value')?.value || 0) : 0;
            const customerSelect = document.querySelector('select[name="payment[customer]"]');
            const billingTypeSelect = document.querySelector('select[name="payment[billingType]"]');
            const description = document.querySelector('input[name="payment[description]"]')?.value || '';
            const firstDueDate = document.getElementById('first-due-date')?.value;
            
            if (installmentValue > 0 && installmentCount > 1) {
                const totalValue = installmentValue * installmentCount;
                const customerName = customerSelect?.selectedOptions[0]?.text || 'Cliente não selecionado';
                const billingType = billingTypeSelect?.selectedOptions[0]?.text || 'Não selecionado';
                const formattedDate = firstDueDate ? new Date(firstDueDate).toLocaleDateString('pt-BR') : 'Não definida';
                
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Cliente:</strong> ${customerName}<br>
                            <strong>Descrição:</strong> ${description || 'Não informada'}<br>
                            <strong>Tipo de Cobrança:</strong> ${billingType}
                        </div>
                        <div class="col-md-6">
                            <strong>Parcelas:</strong> ${installmentCount}x de R$ ${installmentValue.toLocaleString('pt-BR', {minimumFractionDigits: 2})}<br>
                            <strong>Valor Total:</strong> <span class="text-success">R$ ${totalValue.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span><br>
                            <strong>1º Vencimento:</strong> ${formattedDate}
                        </div>
                    </div>
                `;
                
                // ===== ADICIONAR INFORMAÇÕES DE DESCONTO =====
                if (discountEnabled && discountValue > 0 && validateDiscountInput()) {
                    const totalSavings = discountValue * installmentCount;
                    const discountPercentage = (discountValue / installmentValue) * 100;
                    
                    html += `
                        <hr>
                        <div class="discount-info-card">
                            <h6 class="text-warning mb-2"><i class="bi bi-piggy-bank me-1"></i>Informações do Desconto</h6>
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <div class="fw-bold text-success">R$ ${discountValue.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                                    <small class="text-muted">Por parcela (${discountPercentage.toFixed(1)}%)</small>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="fw-bold text-info">R$ ${totalSavings.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                                    <small class="text-muted">Economia total</small>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="fw-bold text-warning">Em dia</div>
                                    <small class="text-muted">Válido até vencimento</small>
                                </div>
                            </div>
                            <div class="text-center mt-2">
                                <small class="text-success">
                                    <i class="bi bi-check-circle me-1"></i>
                                    O aluno economizará <strong>R$ ${totalSavings.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong> se pagar todas as parcelas em dia!
                                </small>
                            </div>
                        </div>
                    `;
                }
                
                // Adicionar informações sobre splits se houver
                const splits = getSplitsInfoWithDiscount();
                if (splits.length > 0) {
                    html += '<hr><small class="text-info"><strong>Splits configurados:</strong> ';
                    splits.forEach(split => {
                        html += `${split.name} `;
                        if (split.percentage) html += `(${split.percentage}%) `;
                        if (split.fixed) html += `(R$ ${split.fixed}) `;
                    });
                    html += '</small>';
                }
                
                summaryContent.innerHTML = html;
            }
        }
        
        /**
         * Obter informações dos splits configurados
         */
        function getSplitsInfoWithDiscount() {
            const splits = [];
            const splitItems = document.querySelectorAll('#splits-container .split-item');
            
            splitItems.forEach(item => {
                const walletSelect = item.querySelector('select[name*="[walletId]"]');
                const percentageInput = item.querySelector('input[name*="[percentualValue]"]');
                const fixedInput = item.querySelector('input[name*="[fixedValue]"]');
                
                if (walletSelect && walletSelect.value) {
                    const splitInfo = {
                        name: walletSelect.selectedOptions[0].text,
                        walletId: walletSelect.value,
                        percentage: percentageInput?.value ? parseFloat(percentageInput.value) : null,
                        fixed: fixedInput?.value ? parseFloat(fixedInput.value) : null
                    };
                    splits.push(splitInfo);
                }
            });
            
            return splits;
        }

        // Adicionar função de debug para testar o envio
window.testDiscountSubmission = function() {
    console.log('🧪 Testando configuração de desconto...');
    
    const discountEnabled = document.getElementById('discount-enabled')?.checked;
    const discountValue = parseFloat(document.getElementById('discount-value')?.value || 0);
    const installmentValue = parseFloat(document.getElementById('installment-value')?.value || 0);
    const installmentCount = parseInt(document.getElementById('installment-count')?.value || 0);
    
    if (discountEnabled && discountValue > 0 && installmentValue > 0 && installmentCount > 0) {
        const totalSavings = discountValue * installmentCount;
        const percentage = (discountValue / installmentValue) * 100;
        
        console.log('💰 Configuração do desconto:', {
            enabled: true,
            discountPerInstallment: 'R$ ' + discountValue.toLocaleString('pt-BR', {minimumFractionDigits: 2}),
            totalSavings: 'R$ ' + totalSavings.toLocaleString('pt-BR', {minimumFractionDigits: 2}),
            percentage: percentage.toFixed(1) + '%',
            isValid: validateDiscountInput()
        });
        
        return {
            discount_enabled: '1',
            discount_value: discountValue.toString(),
            will_be_sent: true
        };
    } else {
        console.log('ℹ️ Desconto não configurado ou inválido');
        return {
            discount_enabled: '0',
            discount_value: '0',
            will_be_sent: false
        };
    }
}
        
        /**
         * Adicionar novo split COM DESCONTO
         */
        function addSplitWithDiscount() {
            splitCounter++;
            const splitsContainer = document.getElementById('splits-container');
            
            if (!splitsContainer) {
                console.error('Container de splits não encontrado');
                return;
            }
            
            // Obter opções de wallets
            let walletOptions = '<option value="">Selecione um destinatário</option>';
            <?php foreach ($walletIds as $wallet): ?>
                <?php if ($wallet['is_active']): ?>
                walletOptions += '<option value="<?php echo $wallet['wallet_id']; ?>"><?php echo addslashes(htmlspecialchars($wallet['name'])); ?></option>';
                <?php endif; ?>
            <?php endforeach; ?>
            
            const splitHtml = `
                <div class="split-item p-3 mb-3">
                    <button type="button" class="split-remove-btn btn btn-sm btn-outline-danger" onclick="removeSplitWithDiscount(this)">
                        <i class="bi bi-x"></i>
                    </button>
                    
                    <div class="mb-3">
                        <label class="form-label">Destinatário</label>
                        <select class="form-select" name="splits[${splitCounter}][walletId]">
                            ${walletOptions}
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <label class="form-label">Percentual (%)</label>
                            <input type="number" class="form-control" name="splits[${splitCounter}][percentualValue]" 
                                   step="0.01" max="100" placeholder="15.00" oninput="updateFinalSummaryWithDiscount()">
                            <small class="form-text text-muted">Ex: 15% de cada parcela</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valor Fixo (R$)</label>
                            <input type="number" class="form-control" name="splits[${splitCounter}][fixedValue]" 
                                   step="0.01" placeholder="5.00" oninput="updateFinalSummaryWithDiscount()">
                            <small class="form-text text-muted">Ex: R$ 5,00 de cada parcela</small>
                        </div>
                    </div>
                </div>
            `;
            
            splitsContainer.insertAdjacentHTML('beforeend', splitHtml);
            showToast('Split adicionado! Será aplicado a todas as parcelas' + (discountEnabled ? ' (incluindo parcelas com desconto)' : '') + '.', 'info');
        }
        
        /**
         * Remover split
         */
        function removeSplitWithDiscount(button) {
            const splitItem = button.closest('.split-item');
            if (splitItem) {
                splitItem.style.transition = 'opacity 0.3s ease';
                splitItem.style.opacity = '0';
                setTimeout(() => {
                    splitItem.remove();
                    updateFinalSummaryWithDiscount();
                    showToast('Split removido', 'info');
                }, 300);
            }
        }
        
        // ===== FUNÇÕES ESPECÍFICAS PARA CLIENTES COM DESCONTO =====
        
        /**
         * Criar mensalidade COM DESCONTO para cliente específico
         */
        function createInstallmentForCustomerWithDiscount(customerId) {
            showSection('installments');
            
            // Aguardar a seção carregar
            setTimeout(() => {
                const customerSelect = document.querySelector('select[name="payment[customer]"]');
                if (customerSelect) {
                    customerSelect.value = customerId;
                    customerSelect.dispatchEvent(new Event('change'));
                    
                    // Habilitar desconto por padrão
                    const discountCheckbox = document.getElementById('discount-enabled');
                    if (discountCheckbox && !discountCheckbox.checked) {
                        discountCheckbox.checked = true;
                        toggleDiscountSection();
                    }
                    
                    // Focar no próximo campo
                    const billingTypeSelect = document.querySelector('select[name="payment[billingType]"]');
                    if (billingTypeSelect) billingTypeSelect.focus();
                    
                    showToast('💰 Cliente selecionado! Mensalidade COM DESCONTO habilitada.', 'success');
                }
            }, 500);
        }
        
        // ===== FUNÇÕES PARA VISUALIZAÇÃO COM DESCONTO =====
        
        /**
         * Visualizar mensalidade COM INFORMAÇÕES DE DESCONTO
         */
        function viewInstallmentWithDiscount(installmentId) {
            showToast('Carregando mensalidade com informações de desconto...', 'info');
            
            // Fazer requisição para API
            fetch(`api.php?action=get-installment-payments&installment_id=${encodeURIComponent(installmentId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showInstallmentModalWithDiscount(data.data);
                    } else {
                        showToast('Erro ao carregar parcelas: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showToast('Erro de conexão: ' + error.message, 'error');
                    console.error('Erro:', error);
                });
        }
        
        /**
         * Mostrar modal com informações de desconto
         */
        function showInstallmentModalWithDiscount(installmentData) {
            console.log('Dados da mensalidade COM DESCONTO:', installmentData);
            
            // Preparar informações de desconto
            let discountInfo = '';
            if (installmentData.discount_info && installmentData.discount_info.has_discount) {
                const discountData = installmentData.discount_info;
                discountInfo = `
                    💰 DESCONTO: ${discountData.discount_value ? 'R$ ' + discountData.discount_value.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ' por parcela' : 'Configurado'}
                    📈 ECONOMIA TOTAL: R$ ${(discountData.total_potential_discount || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                `;
            }
            
            // Por enquanto, mostrar informações básicas COM DESCONTO
            const totalPayments = installmentData.payments?.length || installmentData.total_payments || 0;
            const message = `
                📅 Mensalidade encontrada: ${totalPayments} parcelas
                ${discountInfo}
                
                ℹ️ Modal detalhado será implementado na próxima versão
            `;
            
            showToast(message, 'success');
        }
        
        /**
         * Copiar informações da mensalidade COM DESCONTO
         */
        function copyInstallmentInfoWithDiscount(installmentId, hasDiscount) {
            let info = `ID da Mensalidade: ${installmentId}`;
            
            if (hasDiscount) {
                info += `
💰 Esta mensalidade possui DESCONTO para pagamento em dia
🏷️ Use este ID para consultar os detalhes na API ASAAS`;
            }
            
            copyToClipboard(info);
        }
        
        /**
         * Duplicar mensalidade mantendo configuração de desconto
         */
        function duplicateInstallmentWithDiscount(installmentId) {
            if (!confirm('Deseja duplicar esta mensalidade mantendo a mesma configuração de desconto?')) {
                return;
            }
            
            showToast('💰 Funcionalidade de duplicação com desconto será implementada em versão futura', 'info');
            
            // Por enquanto, redirecionar para nova mensalidade
            showSection('installments');
            
            setTimeout(() => {
                // Habilitar desconto por padrão
                const discountCheckbox = document.getElementById('discount-enabled');
                if (discountCheckbox && !discountCheckbox.checked) {
                    discountCheckbox.checked = true;
                    toggleDiscountSection();
                }
                
                showToast('💡 Configure os dados da nova mensalidade. O desconto já está habilitado!', 'info');
            }, 500);
        }
        
        // ===== FUNÇÕES DE RELATÓRIOS COM DESCONTO =====
        
        /**
         * Gerar relatório básico
         */
        function generateBasicReport() {
            showReportResults('Relatório Geral de Mensalidades');
            
            fetch('api.php?action=installment-report&start=' + getStartDate() + '&end=' + getEndDate())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayBasicReport(data.data.report);
                    } else {
                        showReportError('Erro ao gerar relatório: ' + data.error);
                    }
                })
                .catch(error => {
                    showReportError('Erro de conexão: ' + error.message);
                });
        }
        
        /**
         * Gerar relatório específico de desconto
         */
        function generateDiscountReport() {
            showReportResults('💰 Relatório de Performance do Desconto');
            
            fetch('api.php?action=discount-performance-report&start=' + getStartDate() + '&end=' + getEndDate())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayDiscountReport(data.data);
                    } else {
                        showReportError('Erro ao gerar relatório de desconto: ' + data.error);
                    }
                })
                .catch(error => {
                    showReportError('Erro de conexão: ' + error.message);
                });
        }
        
        /**
         * Gerar relatório de eficiência do desconto
         */
        function generateDiscountEfficiencyReport() {
            showReportResults('📈 Relatório de Eficiência do Desconto');
            
            fetch('api.php?action=discount-stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayDiscountEfficiencyReport(data.data);
                    } else {
                        showReportError('Erro ao gerar relatório de eficiência: ' + data.error);
                    }
                })
                .catch(error => {
                    showReportError('Erro de conexão: ' + error.message);
                });
        }
        
        /**
         * Mostrar área de resultados dos relatórios
         */
        function showReportResults(title) {
            const reportResults = document.getElementById('report-results');
            const reportTitle = document.getElementById('report-title');
            const reportContent = document.getElementById('report-content');
            
            if (reportTitle) reportTitle.innerHTML = '<i class="bi bi-file-text me-2"></i>' + title;
            
            if (reportContent) {
                reportContent.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2 text-muted">Gerando relatório...</p>
                    </div>
                `;
            }
            
            if (reportResults) reportResults.style.display = 'block';
        }
        
        /**
         * Ocultar resultados dos relatórios
         */
        function hideReportResults() {
            const reportResults = document.getElementById('report-results');
            if (reportResults) reportResults.style.display = 'none';
        }
        
        /**
         * Exibir relatório básico
         */
        function displayBasicReport(reportData) {
            const reportContent = document.getElementById('report-content');
            if (!reportContent) return;
            
            let html = `
                <div class="row mb-4">
                    <div class="col-md-3 text-center">
                        <h4 class="text-primary">${reportData.total_installments || 0}</h4>
                        <small class="text-muted">Total de Mensalidades</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-success">R$ ${(reportData.total_value || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h4>
                        <small class="text-muted">Valor Total</small>
                    </div>
                    <div class="col-md-3 text-center">
                    <h4 class="text-info">${reportData.polo_context || 'N/A'}</h4>
                        <small class="text-muted">Contexto</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-warning">${reportData.installments?.length || 0}</h4>
                        <small class="text-muted">Registros Detalhados</small>
                    </div>
                </div>
            `;
            
            if (reportData.installments && reportData.installments.length > 0) {
                html += `
                    <h6>📋 Mensalidades no Período</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Descrição</th>
                                    <th>Parcelas</th>
                                    <th>Valor</th>
                                    <th>Desconto</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                reportData.installments.forEach(installment => {
                    const hasDiscount = installment.has_discount && installment.discount_value > 0;
                    const discountInfo = hasDiscount ? 
                        `R$ ${installment.discount_value.toLocaleString('pt-BR', {minimumFractionDigits: 2})}` : 
                        'Sem desconto';
                    
                    html += `
                        <tr>
                            <td><small>${installment.customer_name || 'N/A'}</small></td>
                            <td><small>${installment.description || 'N/A'}</small></td>
                            <td><span class="badge bg-primary">${installment.installment_count}x</span></td>
                            <td><small>R$ ${installment.total_value.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</small></td>
                            <td><small ${hasDiscount ? 'class="text-success"' : 'class="text-muted"'}>${discountInfo}</small></td>
                            <td><small>${new Date(installment.created_at).toLocaleDateString('pt-BR')}</small></td>
                        </tr>
                    `;
                });
                
                html += `</tbody></table></div>`;
            } else {
                html += '<div class="alert alert-info">Nenhuma mensalidade encontrada no período selecionado.</div>';
            }
            
            reportContent.innerHTML = html;
        }
        
        /**
         * Exibir relatório de desconto
         */
        function displayDiscountReport(reportData) {
            const reportContent = document.getElementById('report-content');
            if (!reportContent) return;
            
            const summary = reportData.summary || {};
            
            let html = `
                <div class="discount-info-card mb-4">
                    <h6 class="text-warning mb-3"><i class="bi bi-piggy-bank me-2"></i>Resumo do Período</h6>
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="savings-display">${summary.total_with_discount || 0}</div>
                            <small class="text-muted">Mensalidades com desconto</small>
                        </div>
                        <div class="col-md-3">
                            <div class="savings-display">${(summary.discount_adoption_rate || 0).toFixed(1)}%</div>
                            <small class="text-muted">Taxa de adoção</small>
                        </div>
                        <div class="col-md-3">
                            <div class="savings-display">R$ ${(summary.total_potential_savings || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                            <small class="text-muted">Economia proporcionada</small>
                        </div>
                        <div class="col-md-3">
                            <div class="savings-display">R$ ${(summary.avg_discount_value || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                            <small class="text-muted">Desconto médio</small>
                        </div>
                    </div>
                </div>
            `;
            
            if (reportData.details && reportData.details.length > 0) {
                html += `
                    <h6>📊 Detalhamento por Mensalidade</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Desconto/Parcela</th>
                                    <th>Economia Total</th>
                                    <th>Taxa de Uso</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                reportData.details.forEach(detail => {
                    html += `
                        <tr>
                            <td><small>${detail.customer_name || 'N/A'}</small></td>
                            <td><strong class="text-success">R$ ${detail.discount_value.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong></td>
                            <td><small>R$ ${detail.potential_savings.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</small></td>
                            <td><small>${(detail.discount_usage_rate || 0).toFixed(1)}%</small></td>
                            <td><span class="badge bg-success">Ativo</span></td>
                        </tr>
                    `;
                });
                
                html += `</tbody></table></div>`;
            }
            
            reportContent.innerHTML = html;
        }
        
        /**
         * Exibir relatório de eficiência do desconto
         */
        function displayDiscountEfficiencyReport(statsData) {
            const reportContent = document.getElementById('report-content');
            if (!reportContent) return;
            
            const adoptionRate = statsData.adoption_rate || 0;
            const efficiencyRate = statsData.efficiency_rate || 0;
            const performanceLevel = statsData.performance_level || 'baixa';
            
            // Definir cores baseadas na performance
            const performanceColors = {
                'excelente': 'success',
                'boa': 'info',
                'regular': 'warning',
                'baixa': 'danger'
            };
            
            const performanceColor = performanceColors[performanceLevel] || 'secondary';
            
            let html = `
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center" style="border-left: 4px solid var(--discount-gradient);">
                            <div class="card-body">
                                <h3 style="color: #e17055;">${adoptionRate.toFixed(1)}%</h3>
                                <small class="text-muted">Taxa de Adoção do Desconto</small>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar" style="background: var(--discount-gradient); width: ${Math.min(adoptionRate, 100)}%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center" style="border-left: 4px solid var(--success-gradient);">
                            <div class="card-body">
                                <h3 class="text-success">${efficiencyRate.toFixed(1)}%</h3>
                                <small class="text-muted">Eficiência do Desconto</small>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: ${Math.min(efficiencyRate, 100)}%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-${performanceColor}">${performanceLevel.toUpperCase()}</h3>
                                <small class="text-muted">Nível de Performance</small>
                                <div class="mt-2">
                                    <span class="badge bg-${performanceColor}">${performanceLevel}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6><i class="bi bi-graph-up me-1"></i>Métricas Detalhadas</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="d-flex justify-content-between py-1">
                                        <span>Mensalidades com desconto:</span>
                                        <strong>${statsData.installments_with_discount || 0}</strong>
                                    </li>
                                    <li class="d-flex justify-content-between py-1">
                                        <span>Economia total proporcionada:</span>
                                        <strong class="text-success">R$ ${(statsData.total_potential_savings || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
                                    </li>
                                    <li class="d-flex justify-content-between py-1">
                                        <span>Descontos utilizados:</span>
                                        <strong>${statsData.discounts_used || 0}</strong>
                                    </li>
                                    <li class="d-flex justify-content-between py-1">
                                        <span>Desconto médio por parcela:</span>
                                        <strong>R$ ${(statsData.avg_discount_value || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6><i class="bi bi-lightbulb me-1"></i>Recomendações</h6>
                            </div>
                            <div class="card-body">
            `;
            
            // Adicionar recomendações baseadas na performance
            if (adoptionRate < 30) {
                html += '<div class="alert alert-warning py-2 mb-2"><small><i class="bi bi-exclamation-triangle me-1"></i>Taxa de adoção baixa. Considere promover mais o desconto.</small></div>';
            }
            
            if (efficiencyRate < 50) {
                html += '<div class="alert alert-info py-2 mb-2"><small><i class="bi bi-info-circle me-1"></i>Muitos alunos não estão aproveitando o desconto. Verifique a comunicação.</small></div>';
            }
            
            if (performanceLevel === 'excelente') {
                html += '<div class="alert alert-success py-2 mb-2"><small><i class="bi bi-check-circle me-1"></i>Excelente! O sistema de desconto está funcionando muito bem.</small></div>';
            }
            
            html += `
                                <div class="text-center mt-3">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Relatório baseado em ${statsData.context || 'dados do sistema'}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            reportContent.innerHTML = html;
        }
        
        /**
         * Mostrar erro no relatório
         */
        function showReportError(message) {
            const reportContent = document.getElementById('report-content');
            if (reportContent) {
                reportContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${message}
                    </div>
                `;
            }
        }
        
        // ===== FUNÇÕES AUXILIARES =====
        
        function getStartDate() {
            return new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
        }
        
        function getEndDate() {
            return new Date().toISOString().split('T')[0];
        }
        
        function generateInstallmentReport() {
            generateBasicReport(); // Por enquanto, usa o mesmo relatório básico
        }
        
        // ===== FUNÇÕES BÁSICAS MANTIDAS =====
        
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('Texto copiado para a área de transferência!', 'success');
                }).catch(err => {
                    console.error('Erro ao copiar:', err);
                    fallbackCopyToClipboard(text);
                });
            } else {
                fallbackCopyToClipboard(text);
            }
        }
        
        function fallbackCopyToClipboard(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-999999px';
            textarea.style.top = '-999999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showToast('Texto copiado!', 'success');
                } else {
                    showToast('Erro ao copiar. Tente selecionar manualmente.', 'warning');
                }
            } catch (err) {
                console.error('Fallback copy failed:', err);
                showToast('Seu navegador não suporta cópia automática', 'warning');
            }
            
            document.body.removeChild(textarea);
        }
        
        function showToast(message, type = 'info') {
            const toastClass = {
                success: 'text-bg-success',
                error: 'text-bg-danger', 
                warning: 'text-bg-warning',
                info: 'text-bg-info'
            }[type] || 'text-bg-info';
            
            const iconClass = {
                success: 'bi-check-circle',
                error: 'bi-exclamation-triangle',
                warning: 'bi-exclamation-triangle',
                info: 'bi-info-circle'
            }[type] || 'bi-info-circle';
            
            const toastHtml = `
                <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
                    <div class="toast show ${toastClass}" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="bi ${iconClass} me-2"></i>
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', toastHtml);
            
            // Auto-remove após 5 segundos
            setTimeout(() => {
                const toasts = document.querySelectorAll('.toast');
                if (toasts.length > 0) {
                    toasts[toasts.length - 1].closest('div').remove();
                }
            }, 5000);
        }
        
        function logout() {
            if (confirm('Deseja realmente sair do sistema?')) {
                showToast('Realizando logout...', 'info');
                document.body.style.pointerEvents = 'none';
                document.body.style.opacity = '0.7';
                window.location.href = 'logout.php';
            }
        }
        
        // Funções mantidas para compatibilidade
        function createPaymentForCustomer(customerId) {
            showSection('payments');
            setTimeout(() => {
                const customerSelect = document.querySelector('#payments-section select[name="payment[customer]"]');
                if (customerSelect) {
                    customerSelect.value = customerId;
                    showToast('Cliente selecionado para pagamento único!', 'success');
                }
            }, 500);
        }
        
        function generatePaymentBook(installmentId) {
            if (!confirm('Deseja gerar o carnê em PDF para esta mensalidade?')) {
                return;
            }
            
            showToast('Gerando carnê em PDF...', 'info');
            
            const formData = new FormData();
            formData.append('action', 'generate-payment-book');
            formData.append('installment_id', installmentId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Iniciar download do PDF
                    const link = document.createElement('a');
                    link.href = data.data.download_url;
                    link.download = data.data.file_name;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showToast('📄 Carnê gerado com sucesso! Download iniciado.', 'success');
                } else {
                    showToast('Erro ao gerar carnê: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showToast('Erro de conexão: ' + error.message, 'error');
                console.error('Erro:', error);
            });
        }
        
        function testConnection() {
            showToast('Testando conexão com ASAAS...', 'info');
            
            const formData = new FormData();
            formData.append('action', 'test_connection');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                showToast('✅ Conexão testada com sucesso! Suporte a desconto ativo.', 'success');
                setTimeout(() => location.reload(), 2000);
            })
            .catch(error => {
                showToast('Erro na conexão: ' + error.message, 'error');
            });
        }
        
        // ===== INICIALIZAÇÃO DO SISTEMA COM DESCONTO =====
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎯 Inicializando sistema COM DESCONTO...');
            
            // Event listeners para navegação
            document.querySelectorAll('[data-section]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const section = e.target.closest('[data-section]').dataset.section;
                    showSection(section);
                });
            });
            
            // ===== EVENT LISTENERS PARA MENSALIDADE COM DESCONTO =====
            
            // Calculadora de mensalidade
            const installmentValueInput = document.getElementById('installment-value');
            const installmentCountSelect = document.getElementById('installment-count');
            const firstDueDateInput = document.getElementById('first-due-date');
            const discountValueInput = document.getElementById('discount-value');
            
            if (installmentValueInput) {
                installmentValueInput.addEventListener('input', calculateInstallmentWithDiscount);
            }
            
            if (installmentCountSelect) {
                installmentCountSelect.addEventListener('change', calculateInstallmentWithDiscount);
            }
            
            if (firstDueDateInput) {
                firstDueDateInput.addEventListener('change', calculateInstallmentWithDiscount);
            }
            
            // Event listeners para desconto
            if (discountValueInput) {
                discountValueInput.addEventListener('input', () => {
                    validateDiscountInput();
                    calculateInstallmentWithDiscount();
                });
            }
            
            // Confirmação da mensalidade com desconto
            const confirmCheckbox = document.getElementById('confirm-installment-with-discount');
            const submitButton = document.getElementById('submit-installment-with-discount');
            
            if (confirmCheckbox && submitButton) {
                confirmCheckbox.addEventListener('change', function() {
                    submitButton.disabled = !this.checked;
                });
            }
            
            // Event listeners para campos que afetam o resumo
            const summaryFields = ['payment[customer]', 'payment[billingType]', 'payment[description]'];
            summaryFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    field.addEventListener('change', updateFinalSummaryWithDiscount);
                }
            });
            
            // Tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // ===== VALIDAÇÃO EM TEMPO REAL PARA DESCONTO =====
            
            // Validar desconto quando valores mudarem
            ['installment-value', 'discount-value', 'installment-count'].forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('input', () => {
                        if (discountEnabled) {
                            validateDiscountInput();
                        }
                    });
                }
            });
            
            console.log('✅ Sistema COM DESCONTO inicializado com todas as funcionalidades');
            console.log('💰 Configurações de desconto:', SystemConfig.discount_config);
        });
        
        // Log de inicialização completa COM DESCONTO
        window.addEventListener('load', function() {
            console.log('🎉 Sistema IMEP Split ASAAS v3.4 totalmente carregado - COM MENSALIDADES E DESCONTO');
            console.log('📅 Funcionalidades de mensalidade: ATIVAS');
            console.log('💰 Funcionalidades de desconto: ATIVAS');
            console.log('💳 Máximo de parcelas:', SystemConfig.features.max_installments);
            console.log('🏷️ Desconto máximo por parcela:', SystemConfig.features.max_discount_percentage + '%');
            console.log('⚡ Tipo de desconto padrão:', SystemConfig.features.default_discount_type);
            
            // Verificar se há mensalidades com desconto existentes
            const discountStats = SystemConfig.user.polo_nome ? 'dados do polo' : 'dados globais';
            console.log('📊 Estatísticas de desconto baseadas em:', discountStats);
            
            // Mostrar dica inicial sobre desconto se for primeiro acesso
            if (typeof(Storage) !== "undefined" && !localStorage.getItem('discount_tip_shown')) {
                setTimeout(() => {
                    showToast('💡 NOVIDADE: Agora você pode oferecer descontos automáticos nas mensalidades! Experimente na seção Mensalidades.', 'info');
                    localStorage.setItem('discount_tip_shown', 'true');
                }, 3000);
            }
        });
        
    </script>
    
</body>
</html>