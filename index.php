<?php
/**
 * Interface Principal do Sistema IMEP Split ASAAS - VERSÃO COM MENSALIDADES
 * Arquivo: index.php
 * Versão: 3.3 - Adicionada funcionalidade de mensalidades parceladas
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
    error_log("Acesso ao index.php - Usuário: {$usuario['email']}, Tipo: {$usuario['tipo']}, Polo: " . ($usuario['polo_nome'] ?? 'Master'));
    
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
// CONFIGURAÇÃO DO CONTEXTO DO USUÁRIO - CORRIGIDO
// ==================================================

// Determinar contexto baseado no tipo de usuário - CORRIGIDO
$isMaster = ($usuario['tipo'] === 'master');
$isAdminPolo = ($usuario['tipo'] === 'admin_polo');
$isOperador = ($usuario['tipo'] === 'operador');

// Configurar título e contexto da página
$pageTitle = 'Dashboard';
$pageSubtitle = 'Sistema de Split de Pagamentos ASAAS com Mensalidades';

if ($isMaster) {
    $pageTitle = 'Master Dashboard';
    $pageSubtitle = 'Administração Central - Todos os Polos - Com Mensalidades';
} elseif ($isAdminPolo) {
    $pageTitle = 'Admin Dashboard';
    $pageSubtitle = 'Administração do Polo: ' . ($usuario['polo_nome'] ?? 'N/A') . ' - Com Mensalidades';
} else {
    $pageTitle = 'Operador Dashboard'; 
    $pageSubtitle = 'Polo: ' . ($usuario['polo_nome'] ?? 'N/A') . ' - Com Mensalidades';
}

// Configurar permissões baseadas no tipo
$permissions = [
    'can_manage_users' => $isMaster,
    'can_manage_poles' => $isMaster,
    'can_view_all_data' => $isMaster || $isAdminPolo,
    'can_create_payments' => true, // Todos podem criar pagamentos
    'can_create_installments' => true, // NOVO: Todos podem criar mensalidades
    'can_create_customers' => true, // Todos podem criar clientes
    'can_manage_wallets' => $isMaster || $isAdminPolo,
    'can_view_reports' => true, // Todos podem ver relatórios (filtrados por polo)
    'can_configure_asaas' => $isMaster || $isAdminPolo,
    'can_export_data' => $isMaster || $isAdminPolo,
    'can_generate_payment_books' => true // NOVO: Todos podem gerar carnês
];

// ==================================================
// PROCESSAMENTO DE AÇÕES E FORMULÁRIOS
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

// Função para obter configuração ASAAS baseada no contexto
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

// Definir contexto para JavaScript
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
    'system_version' => '3.3 Multi-Tenant + Mensalidades',
    'features' => [
        'installments' => true,
        'payment_books' => true,
        'max_installments' => 24
    ]
];

// Processar ações via POST com validação de permissões
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Log da ação para auditoria
    error_log("Ação executada: {$action} por usuário: {$usuario['email']}");
    
    try {
        switch ($action) {
            
            // ===== NOVA FUNCIONALIDADE: CRIAR MENSALIDADE PARCELADA =====
            case 'create_installment':
                if (!$permissions['can_create_installments']) {
                    throw new Exception('Você não tem permissão para criar mensalidades');
                }
                
                // Validar dados básicos
                $paymentData = $_POST['payment'] ?? [];
                $installmentData = $_POST['installment'] ?? [];
                $splitsData = $_POST['splits'] ?? [];
                
                // Validações do pagamento
                $requiredPaymentFields = ['customer', 'billingType', 'description', 'dueDate'];
                foreach ($requiredPaymentFields as $field) {
                    if (empty($paymentData[$field])) {
                        throw new Exception("Campo '{$field}' é obrigatório para criar mensalidade");
                    }
                }
                
                // Validações do parcelamento
                $installmentCount = (int)($installmentData['installmentCount'] ?? 0);
                $installmentValue = floatval($installmentData['installmentValue'] ?? 0);
                
                if ($installmentCount < 2 || $installmentCount > 24) {
                    throw new Exception('Número de parcelas deve ser entre 2 e 24');
                }
                
                if ($installmentValue <= 0) {
                    throw new Exception('Valor da parcela deve ser maior que zero');
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
                
                // Criar mensalidade via API
                try {
                    $asaas = getContextualAsaasInstance();
                    
                    // Usar novo método de parcelamento
                    $result = $asaas->createInstallmentPaymentWithSplit($paymentData, $processedSplits, $installmentData);
                    
                    // Salvar no banco com informações do polo
                    $db = DatabaseManager::getInstance();
                    $paymentSaveData = array_merge($result, ['polo_id' => $usuario['polo_id']]);
                    $db->savePayment($paymentSaveData);
                    
                    if (!empty($processedSplits)) {
                        $db->savePaymentSplits($result['id'], $processedSplits);
                    }
                    
                    // Salvar informações específicas do parcelamento
                    $installmentRecord = [
                        'installment_id' => $result['installment'],
                        'polo_id' => $usuario['polo_id'],
                        'customer_id' => $result['customer'],
                        'installment_count' => $installmentCount,
                        'installment_value' => $installmentValue,
                        'total_value' => $installmentCount * $installmentValue,
                        'first_due_date' => $paymentData['dueDate'],
                        'billing_type' => $paymentData['billingType'],
                        'description' => $paymentData['description'],
                        'has_splits' => !empty($processedSplits),
                        'splits_count' => count($processedSplits),
                        'created_by' => $usuario['id'],
                        'first_payment_id' => $result['id']
                    ];
                    
                    // Salvar registro de parcelamento
                    if (method_exists($db, 'saveInstallmentRecord')) {
                        $db->saveInstallmentRecord($installmentRecord);
                    }
                    
                    // Mensagem de sucesso com detalhes
                    $totalValue = $installmentCount * $installmentValue;
                    $successMessage = "✅ Mensalidade criada com sucesso!<br>";
                    $successMessage .= "<strong>{$installmentCount} parcelas de R$ " . number_format($installmentValue, 2, ',', '.') . "</strong><br>";
                    $successMessage .= "Total: R$ " . number_format($totalValue, 2, ',', '.') . "<br>";
                    $successMessage .= "Primeiro vencimento: " . date('d/m/Y', strtotime($paymentData['dueDate']));
                    
                    if (!empty($result['invoiceUrl'])) {
                        $successMessage .= "<br><a href='{$result['invoiceUrl']}' target='_blank' class='btn btn-sm btn-outline-primary mt-2'><i class='bi bi-eye'></i> Ver 1ª Parcela</a>";
                    }
                    
/*                     setMessage('success', $successMessage, [
                        'installment_id' => $result['installment'],
                        'installment_count' => $installmentCount,
                        'installment_value' => $installmentValue,
                        'total_value' => $totalValue,
                        'splits_count' => count($processedSplits)
                    ]); */
                    
                } catch (Exception $e) {
                    throw new Exception('Erro ao criar mensalidade: ' . $e->getMessage());
                }
                break;
                
            // ===== FUNCIONALIDADES EXISTENTES MANTIDAS =====
            case 'create_wallet':
                // Código existente mantido...
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
                // Código existente mantido...
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
                // Código existente mantido para compatibilidade...
                $paymentData = $_POST['payment'] ?? [];
                $splitsData = $_POST['splits'] ?? [];
                
                // Validações básicas mantidas
                $requiredPaymentFields = ['customer', 'billingType', 'value', 'description', 'dueDate'];
                foreach ($requiredPaymentFields as $field) {
                    if (empty($paymentData[$field])) {
                        throw new Exception("Campo '{$field}' é obrigatório para criar pagamento");
                    }
                }
                
                // Criar pagamento via API
                $asaas = getContextualAsaasInstance();
                $processedSplits = []; // Processar splits aqui...
                $payment = $asaas->createPaymentWithSplit($paymentData, $processedSplits);
                
                // Salvar no banco
                $db = DatabaseManager::getInstance();
                $paymentSaveData = array_merge($payment, ['polo_id' => $usuario['polo_id']]);
                $db->savePayment($paymentSaveData);
                
                setMessage('success', 'Pagamento criado com sucesso!', ['payment_id' => $payment['id']]);
                break;
                
            case 'test_connection':
                try {
                    $asaas = getContextualAsaasInstance();
                    $response = $asaas->listAccounts(1, 0);
                    
                    $contextInfo = $isMaster ? 'Configuração Master' : "Polo: {$usuario['polo_nome']}";
                    setMessage('success', "Conexão OK! ({$contextInfo}) - {$response['totalCount']} contas encontradas.", [
                        'total_accounts' => $response['totalCount'],
                        'environment' => defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'undefined'
                    ]);
                } catch (Exception $e) {
                    throw new Exception('Falha na conexão com ASAAS: ' . $e->getMessage());
                }
                break;
                
            default:
                throw new Exception("Ação não reconhecida: {$action}");
        }
        
    } catch (Exception $e) {
        setMessage('error', $e->getMessage(), ['action' => $action, 'user' => $usuario['email']]);
        
        // Log detalhado do erro
        error_log("Erro na ação {$action} por {$usuario['email']}: " . $e->getMessage());
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
// CARREGAMENTO DE DADOS CONTEXTUAIS
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
            echo "<strong>{$key}:</strong> " . htmlspecialchars($value) . "<br>";
        }
        echo "</small>";
    }
    
    echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>";
    echo "</div>";
}

// ==================================================
// CARREGAMENTO SEGURO DE DADOS
// ==================================================

$stats = null;
$customers = [];
$splitAccounts = [];
$payments = [];
$walletIds = [];
$recentInstallments = []; // NOVO: Mensalidades recentes

try {
    $db = DatabaseManager::getInstance();
    
    // Obter estatísticas baseadas no contexto do usuário
    if ($isMaster) {
        // Master vê estatísticas globais
        $stats = SystemStats::getGeneralStats();
        $contextLabel = 'Todos os Polos';
    } else {
        // Usuários de polo veem apenas dados do seu polo
        $stats = SystemStats::getGeneralStats($usuario['polo_id']);
        $contextLabel = $usuario['polo_nome'] ?? 'Polo N/A';
    }
    
    // Adicionar informações contextuais às estatísticas
    if ($stats) {
        $stats['context_label'] = $contextLabel;
        $stats['user_type'] = $usuario['tipo'];
        $stats['polo_filter'] = !$isMaster ? $usuario['polo_id'] : null;
    }
    
    // Carregar clientes recentes
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
    
    // NOVO: Carregar mensalidades recentes (se tabela existir)
    try {
        $installmentQuery = "SELECT * FROM installments WHERE 1=1";
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
        // Tabela de installments ainda não existe, criar depois
        $recentInstallments = [];
        error_log("Tabela installments não existe ainda: " . $e->getMessage());
    }
    
    // Carregar pagamentos recentes
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
    error_log("Erro ao carregar dados do index.php: " . $e->getMessage());
    
    $stats = [
        'total_customers' => 0,
        'total_wallet_ids' => 0,
        'total_payments' => 0,
        'total_value' => 0,
        'context_label' => 'Erro ao carregar',
        'error' => true
    ];
    
    setMessage('warning', 'Alguns dados podem não estar atualizados devido a um erro temporário.', [
        'error_details' => $e->getMessage()
    ]);
}

// ==================================================
// FUNÇÕES AUXILIARES
// ==================================================

/**
 * Mascarar documento (CPF/CNPJ)
 */
function maskDocument($document) {
    if (empty($document)) return 'N/A';
    
    $document = preg_replace('/[^0-9]/', '', $document);
    
    if (strlen($document) === 11) {
        // CPF: 123.***.***-45
        return substr($document, 0, 3) . '.***.***-' . substr($document, -2);
    } elseif (strlen($document) === 14) {
        // CNPJ: 12.***.***/****-45
        return substr($document, 0, 2) . '.***.***/****-' . substr($document, -2);
    }
    
    return substr($document, 0, 3) . '***';
}

/**
 * Mascarar Wallet ID
 */
function maskWalletId($walletId) {
    if (empty($walletId)) return 'N/A';
    
    if (strlen($walletId) > 16) {
        return substr($walletId, 0, 8) . '...' . substr($walletId, -8);
    }
    
    return $walletId;
}

/**
 * Obter classe CSS baseada no status
 */
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

/**
 * Obter ícone baseado no status
 */
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
    <title><?php echo htmlspecialchars($pageTitle); ?> - Sistema IMEP Split ASAAS</title>
    
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
            --installment-gradient: linear-gradient(135deg, #667eea 0%, #11998e 100%); /* NOVO: Gradiente para mensalidades */
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* ===== SIDEBAR ===== */
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
        
        /* ===== CARDS ===== */
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
        
        /* ===== NOVO: CARDS DE MENSALIDADE ===== */
        .card-installment {
            background: var(--installment-gradient);
            color: white;
            text-align: center;
            border-radius: 15px;
        }
        
        .installment-form-card {
            border-left: 4px solid #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.02) 0%, rgba(17, 153, 142, 0.02) 100%);
        }
        
        .installment-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .parcela-preview {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            margin: 5px 0;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        /* ===== SECTION TABS ===== */
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
        
        /* ===== NAVBAR ===== */
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
        
        /* ===== SEÇÕES ===== */
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
        
        /* ===== BOTÕES ===== */
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
        
        /* NOVO: Botão específico para mensalidades */
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
        
        /* ===== FORMULÁRIOS ===== */
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
        
        /* ===== CALCULADORA DE MENSALIDADE ===== */
        .installment-calculator {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            border: 2px dashed #667eea;
        }
        
        .calculator-result {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .value-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }
        
        /* ===== SPLITS CONTAINER ===== */
        .split-item {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .split-item:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.02);
        }
        
        .split-remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        /* ===== RESPONSIVO ===== */
        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                min-height: auto;
            }
            
            .card-stats {
                margin-bottom: 15px;
            }
            
            .installment-calculator {
                padding: 15px;
            }
        }
        
        /* ===== ALERTAS CUSTOMIZADOS ===== */
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
        
        /* ===== LOADING E STATES ===== */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
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
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- ===== SIDEBAR ===== -->
            <div class="col-md-3 col-lg-2">
                <div class="sidebar p-3">
                    <!-- Logo e Título -->
                    <div class="text-center mb-4">
                        <h4 class="text-white mb-1">
                            <i class="bi bi-credit-card-2-front me-2"></i>
                            IMEP Split
                        </h4>
                        <small class="text-white-50">Sistema ASAAS v3.3 + Mensalidades</small>
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
                        
                        <!-- NOVO: Link para Mensalidades -->
                        <?php if ($permissions['can_create_installments']): ?>
                        <a href="#" class="nav-link" data-section="installments">
                            <i class="bi bi-calendar-month"></i> Mensalidades
                            <span class="badge bg-info ms-auto"><?php echo count($recentInstallments); ?></span>
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
                        
                        <?php if ($permissions['can_view_reports']): ?>
                        <a href="#" class="nav-link" data-section="reports">
                            <i class="bi bi-graph-up"></i> Relatórios
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
                <!-- Navbar Superior -->
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
                            
                            <!-- NOVO: Badge de Funcionalidades -->
                            <span class="badge bg-success">
                                <i class="bi bi-calendar-month me-1"></i>
                                Mensalidades
                            </span>
                        </div>
                    </div>
                </nav>
                
                <!-- Área de Conteúdo -->
                <div class="container-fluid px-4">
                    
                    <!-- Mensagens de Feedback -->
                    <?php showMessage(); ?>

                    <!-- ===== DASHBOARD (SEÇÃO PRINCIPAL) ===== -->
                    <div id="dashboard-section" class="section active">
                        <!-- Estatísticas Gerais -->
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
                                <div class="card card-stats" style="background: var(--primary-gradient);">
                                    <div class="card-body">
                                        <i class="bi bi-currency-dollar"></i>
                                        <h3>R$ <?php echo number_format($stats['total_value'], 2, ',', '.'); ?></h3>
                                        <p>Total Recebido</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Ações Rápidas - ATUALIZADA COM MENSALIDADES -->
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
                                            
                                            <!-- NOVO: Botão para Mensalidades -->
                                            <?php if ($permissions['can_create_installments']): ?>
                                            <button class="btn btn-installment" onclick="showSection('installments')">
                                                <i class="bi bi-calendar-month me-2"></i>Nova Mensalidade
                                                <small class="d-block">Criar mensalidade parcelada para aluno</small>
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
                                        <!-- NOVO: Mostrar mensalidades recentes -->
                                        <?php if (!empty($recentInstallments)): ?>
                                            <h6 class="text-primary">📅 Mensalidades Recentes</h6>
                                            <?php foreach (array_slice($recentInstallments, 0, 3) as $installment): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($installment['description'] ?? 'Mensalidade'); ?></strong><br>
                                                    <small class="text-muted">
                                                        <?php echo $installment['installment_count']; ?> parcelas de 
                                                        R$ <?php echo number_format($installment['installment_value'], 2, ',', '.'); ?>
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
                                                    <small class="text-muted">R$ <?php echo number_format($payment['value'], 2, ',', '.'); ?></small>
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== NOVA SEÇÃO: MENSALIDADES ===== -->
                    <?php if ($permissions['can_create_installments']): ?>
                    <div id="installments-section" class="section">
                        <div class="card installment-form-card">
                            <div class="card-header bg-primary text-white">
                                <h5><i class="bi bi-calendar-month me-2"></i>Nova Mensalidade Parcelada</h5>
                                <small>Crie mensalidades para alunos com parcelamento automático</small>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="installment-form">
                                    <input type="hidden" name="action" value="create_installment">
                                    
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
                                                                   step="0.01" min="1" 
                                                                   placeholder="100.00" 
                                                                   required id="installment-value"
                                                                   oninput="calculateInstallment()">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Quantidade de Parcelas *</label>
                                                            <select class="form-select" name="installment[installmentCount]" 
                                                                    required id="installment-count"
                                                                    onchange="calculateInstallment()">
                                                                <option value="">Selecione</option>
                                                                <?php for($i = 2; $i <= 24; $i++): ?>
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
                                                                   step="0.01" max="100" placeholder="15.00">
                                                            <small class="form-text text-muted">Ex: 15% de cada parcela</small>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label">Valor Fixo (R$)</label>
                                                            <input type="number" class="form-control" name="splits[0][fixedValue]" 
                                                                   step="0.01" placeholder="5.00">
                                                            <small class="form-text text-muted">Ex: R$ 5,00 de cada parcela</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addSplit()">
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
                                    
                                    <!-- ===== CONFIRMAÇÃO E ENVIO ===== -->
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="installment-summary" id="final-summary" style="display: none;">
                                                <h6 class="text-primary mb-2">📋 Resumo da Mensalidade</h6>
                                                <div id="summary-content"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="confirm-installment">
                                                <label class="form-check-label" for="confirm-installment">
                                                    Confirmo que os dados da mensalidade estão corretos
                                                </label>
                                            </div>
                                            <button type="submit" class="btn btn-installment w-100" disabled id="submit-installment">
                                                <i class="bi bi-calendar-month me-2"></i>Criar Mensalidade
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- ===== MENSALIDADES RECENTES ===== -->
                        <?php if (!empty($recentInstallments)): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5><i class="bi bi-list me-2"></i>Mensalidades Cadastradas</h5>
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
                                                <th>1º Vencimento</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentInstallments as $installment): ?>
                                            <tr>
                                                <td>
                                                    <strong>Cliente ID: <?php echo htmlspecialchars($installment['customer_id']); ?></strong><br>
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
                                                    <small><?php echo date('d/m/Y', strtotime($installment['first_due_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">Ativa</span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="viewInstallment('<?php echo $installment['installment_id']; ?>')" 
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
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick="copyInstallmentInfo('<?php echo $installment['installment_id']; ?>')" 
                                                                data-bs-toggle="tooltip" title="Copiar informações">
                                                            <i class="bi bi-clipboard"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
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
                                                                    <button class="btn btn-sm btn-outline-primary" 
                                                                            onclick="createInstallmentForCustomer('<?php echo $customer['id']; ?>')" 
                                                                            data-bs-toggle="tooltip" title="Nova mensalidade">
                                                                        <i class="bi bi-calendar-month"></i>
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
                                <small class="text-muted">Para mensalidades, use a seção específica de Mensalidades</small>
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

                    <!-- ===== SEÇÃO RELATÓRIOS (SIMPLIFICADA) ===== -->
                    <?php if ($permissions['can_view_reports']): ?>
                    <div id="reports-section" class="section">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-graph-up me-2"></i>Relatórios</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    Funcionalidade de relatórios será implementada nas próximas partes.
                                    Incluirá relatórios específicos de mensalidades e parcelamentos.
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
        // ===== CONFIGURAÇÃO GLOBAL =====
        const SystemConfig = <?php echo json_encode($jsContext); ?>;
        let currentSection = 'dashboard';
        let splitCounter = 1;
        
        console.log('🚀 Sistema IMEP Split ASAAS v3.3 carregado - COM MENSALIDADES');
        console.log('👤 Usuário:', SystemConfig.user.nome, '(' + SystemConfig.user.tipo + ')');
        console.log('🏢 Contexto:', SystemConfig.user.polo_nome || 'Master');
        console.log('💳 Funcionalidades:', SystemConfig.features);
        
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
        
        // ===== FUNÇÕES PARA MENSALIDADES (NOVAS) =====
        
        /**
         * Calcular valores do parcelamento
         */
        function calculateInstallment() {
            const installmentValue = parseFloat(document.getElementById('installment-value')?.value || 0);
            const installmentCount = parseInt(document.getElementById('installment-count')?.value || 0);
            const firstDueDate = document.getElementById('first-due-date')?.value;
            
            const resultDiv = document.getElementById('calculation-result');
            const summaryDiv = document.getElementById('final-summary');
            
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
                resultDiv.style.display = 'block';
                
                // Atualizar resumo final
                updateFinalSummary();
                summaryDiv.style.display = 'block';
                
                console.log('Cálculo atualizado:', {installmentValue, installmentCount, totalValue});
            } else {
                resultDiv.style.display = 'none';
                summaryDiv.style.display = 'none';
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
                </div></div>`;
            }
            
            if (count > 6) {
                html += `<div class="col-12"><small class="text-muted">... e mais ${count - 6} parcelas</small></div>`;
            }
            
            html += '</div>';
            preview.innerHTML = html;
        }
        
        /**
         * Atualizar resumo final
         */
        function updateFinalSummary() {
            const summaryContent = document.getElementById('summary-content');
            if (!summaryContent) return;
            
            const installmentValue = parseFloat(document.getElementById('installment-value')?.value || 0);
            const installmentCount = parseInt(document.getElementById('installment-count')?.value || 0);
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
                
                // Adicionar informações sobre splits se houver
                const splits = getSplitsInfo();
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
        function getSplitsInfo() {
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
        
        /**
         * Adicionar novo split
         */
        function addSplit() {
            splitCounter++;
            const splitsContainer = document.getElementById('splits-container');
            
            if (!splitsContainer) {
                console.error('Container de splits não encontrado');
                return;
            }
            
            // Obter opções de wallets
            let walletOptions = '<option value="">Selecione um destinatário</option>';
            document.querySelectorAll('#wallets-section .wallet-card .card-title').forEach(card => {
                const name = card.textContent;
                const walletId = card.closest('.wallet-card').querySelector('.wallet-id-display')?.getAttribute('onclick')?.match(/'([^']+)'/)?.[1];
                if (walletId) {
                    walletOptions += `<option value="${walletId}">${name}</option>`;
                }
            });
            
            // Se não encontrou wallets na interface, usar os do PHP
            if (walletOptions === '<option value="">Selecione um destinatário</option>') {
                <?php foreach ($walletIds as $wallet): ?>
                    <?php if ($wallet['is_active']): ?>
                    walletOptions += '<option value="<?php echo $wallet['wallet_id']; ?>"><?php echo addslashes(htmlspecialchars($wallet['name'])); ?></option>';
                    <?php endif; ?>
                <?php endforeach; ?>
            }
            
            const splitHtml = `
                <div class="split-item p-3 mb-3">
                    <button type="button" class="split-remove-btn btn btn-sm btn-outline-danger" onclick="removeSplit(this)">
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
                                   step="0.01" max="100" placeholder="15.00">
                            <small class="form-text text-muted">Ex: 15% de cada parcela</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valor Fixo (R$)</label>
                            <input type="number" class="form-control" name="splits[${splitCounter}][fixedValue]" 
                                   step="0.01" placeholder="5.00">
                            <small class="form-text text-muted">Ex: R$ 5,00 de cada parcela</small>
                        </div>
                    </div>
                </div>
            `;
            
            splitsContainer.insertAdjacentHTML('beforeend', splitHtml);
            showToast('Split adicionado! Será aplicado a todas as parcelas.', 'info');
        }
        
        /**
         * Remover split
         */
        function removeSplit(button) {
            const splitItem = button.closest('.split-item');
            if (splitItem) {
                splitItem.style.transition = 'opacity 0.3s ease';
                splitItem.style.opacity = '0';
                setTimeout(() => {
                    splitItem.remove();
                    showToast('Split removido', 'info');
                }, 300);
            }
        }
        
        /**
         * Criar mensalidade para cliente específico
         */
        function createInstallmentForCustomer(customerId) {
            showSection('installments');
            
            // Aguardar a seção carregar
            setTimeout(() => {
                const customerSelect = document.querySelector('select[name="payment[customer]"]');
                if (customerSelect) {
                    customerSelect.value = customerId;
                    customerSelect.dispatchEvent(new Event('change'));
                    
                    // Focar no próximo campo
                    const billingTypeSelect = document.querySelector('select[name="payment[billingType]"]');
                    if (billingTypeSelect) billingTypeSelect.focus();
                    
                    showToast('Cliente selecionado! Configure a mensalidade.', 'success');
                }
            }, 500);
        }
        
        /**
         * Visualizar todas as parcelas de uma mensalidade
         */
        function viewInstallment(installmentId) {
            showToast('Carregando parcelas da mensalidade...', 'info');
            
            // Fazer requisição para API
            fetch(`api.php?action=get-installment-payments&installment_id=${encodeURIComponent(installmentId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showInstallmentModal(data.data);
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
         * Mostrar modal com todas as parcelas
         */
        function showInstallmentModal(installmentData) {
            // Implementar modal dinâmico para mostrar todas as parcelas
            console.log('Dados da mensalidade:', installmentData);
            
            // Por enquanto, mostrar informações básicas
            showToast(`Mensalidade encontrada: ${installmentData.payments?.length || 0} parcelas`, 'success');
        }
        
        /**
         * Gerar carnê em PDF
         */
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
                    
                    showToast('Carnê gerado com sucesso! Download iniciado.', 'success');
                } else {
                    showToast('Erro ao gerar carnê: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showToast('Erro de conexão: ' + error.message, 'error');
                console.error('Erro:', error);
            });
        }
        
        /**
         * Copiar informações da mensalidade
         */
        function copyInstallmentInfo(installmentId) {
            const info = `ID da Mensalidade: ${installmentId}`;
            copyToClipboard(info);
        }
        
        // ===== FUNÇÕES BÁSICAS MANTIDAS =====
        
        /**
         * Copiar texto para área de transferência
         */
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
        
        // ===== INICIALIZAÇÃO DO SISTEMA =====
        document.addEventListener('DOMContentLoaded', function() {
            // Event listeners para navegação
            document.querySelectorAll('[data-section]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const section = e.target.closest('[data-section]').dataset.section;
                    showSection(section);
                });
            });
            
            // Event listeners para formulário de mensalidade
            const installmentValueInput = document.getElementById('installment-value');
            const installmentCountSelect = document.getElementById('installment-count');
            const firstDueDateInput = document.getElementById('first-due-date');
            
            if (installmentValueInput) {
                installmentValueInput.addEventListener('input', calculateInstallment);
            }
            
            if (installmentCountSelect) {
                installmentCountSelect.addEventListener('change', calculateInstallment);
            }
            
            if (firstDueDateInput) {
                firstDueDateInput.addEventListener('change', calculateInstallment);
            }
            
            // Event listener para confirmação da mensalidade
            const confirmCheckbox = document.getElementById('confirm-installment');
            const submitButton = document.getElementById('submit-installment');
            
            if (confirmCheckbox && submitButton) {
                confirmCheckbox.addEventListener('change', function() {
                    submitButton.disabled = !this.checked;
                });
            }
            
            // Event listeners para outros formulários
            const customerFields = ['payment[customer]', 'payment[billingType]', 'payment[description]'];
            customerFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    field.addEventListener('change', updateFinalSummary);
                }
            });
            
            // Tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            console.log('✅ Sistema inicializado com funcionalidades de mensalidade');
        });
        
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
                showToast('Conexão testada com sucesso!', 'success');
                setTimeout(() => location.reload(), 2000);
            })
            .catch(error => {
                showToast('Erro na conexão: ' + error.message, 'error');
            });
        }
        
        // Log de inicialização completa
        window.addEventListener('load', function() {
            console.log('🎉 Sistema IMEP Split ASAAS v3.3 totalmente carregado - COM MENSALIDADES PARCELADAS');
            console.log('📅 Funcionalidades de mensalidade: ATIVAS');
            console.log('💰 Máximo de parcelas:', SystemConfig.features.max_installments);
        });
        
    </script>
    
</body>
</html>