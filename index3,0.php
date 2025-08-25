<?php
/**
 * Interface Principal do Sistema IMEP Split ASAAS - VERSÃO CORRIGIDA
 * Arquivo: index.php
 * Versão: 3.0 - Multi-Tenant com Autenticação Obrigatória
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

// Configurações de erro para desenvolvimento (remover em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros na tela
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
// CONFIGURAÇÃO DO CONTEXTO DO USUÁRIO
// ==================================================

// Determinar contexto baseado no tipo de usuário
$isMaster = $usuario['tipo'] === 'master';
$isAdminPolo = $usuario['tipo'] === 'admin_polo';
$isOperador = $usuario['tipo'] === 'operador';

// Configurar título e contexto da página
$pageTitle = 'Dashboard';
$pageSubtitle = 'Sistema de Split de Pagamentos ASAAS';

if ($isMaster) {
    $pageTitle = 'Master Dashboard';
    $pageSubtitle = 'Administração Central - Todos os Polos';
} elseif ($isAdminPolo) {
    $pageTitle = 'Admin Dashboard';
    $pageSubtitle = 'Administração do Polo: ' . $usuario['polo_nome'];
} else {
    $pageTitle = 'Operador Dashboard'; 
    $pageSubtitle = 'Polo: ' . $usuario['polo_nome'];
}

// Configurar permissões baseadas no tipo
$permissions = [
    'can_manage_users' => $isMaster,
    'can_manage_poles' => $isMaster,
    'can_view_all_data' => $isMaster || $isAdminPolo,
    'can_create_payments' => true, // Todos podem criar pagamentos
    'can_create_customers' => true, // Todos podem criar clientes
    'can_manage_wallets' => $isMaster || $isAdminPolo,
    'can_view_reports' => true, // Todos podem ver relatórios (filtrados por polo)
    'can_configure_asaas' => $isMaster || $isAdminPolo,
    'can_export_data' => $isMaster || $isAdminPolo
];

// ==================================================
// INICIALIZAR GERENCIADORES E DADOS
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

// Função para filtrar dados por polo (se necessário)
function applyPoloFilter($query, $params = []) {
    global $usuario, $isMaster;
    
    // Master vê todos os dados, outros usuários apenas do seu polo
    if (!$isMaster && $usuario['polo_id']) {
        // Adicionar filtro por polo se a query ainda não tiver
        if (stripos($query, 'WHERE') !== false) {
            $query .= " AND polo_id = ?";
        } else {
            $query .= " WHERE polo_id = ?";
        }
        $params[] = $usuario['polo_id'];
    }
    
    return ['query' => $query, 'params' => $params];
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
    'system_version' => '3.0 Multi-Tenant'
];
// ==================================================
// PROCESSAMENTO DE AÇÕES E FORMULÁRIOS
// ==================================================

// Processar ações via POST com validação de permissões
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Log da ação para auditoria
    error_log("Ação executada: {$action} por usuário: {$usuario['email']}");
    
    try {
        switch ($action) {
            
            // ==================================================
            // GERENCIAMENTO DE WALLET IDs
            // ==================================================
            
            case 'create_wallet':
                if (!$permissions['can_manage_wallets']) {
                    throw new Exception('Você não tem permissão para gerenciar Wallet IDs');
                }
                
                // Validações
                $name = trim($_POST['wallet']['name'] ?? '');
                $walletId = trim($_POST['wallet']['wallet_id'] ?? '');
                $description = trim($_POST['wallet']['description'] ?? '');
                
                if (empty($name) || empty($walletId)) {
                    throw new Exception('Nome e Wallet ID são obrigatórios');
                }
                
                // Validar formato do Wallet ID (UUID)
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $walletId)) {
                    throw new Exception('Formato de Wallet ID inválido. Use formato UUID (ex: 22e49670-27e4-4579-a4c1-205c8a40497c)');
                }
                
                // Criar Wallet ID
                $walletManager = new WalletManager();
                $wallet = $walletManager->createWallet($name, $walletId, $description, $usuario['polo_id']);
                
                // Log de auditoria
                if (class_exists('ConfigManager')) {
                    $auditData = [
                        'wallet_name' => $name,
                        'wallet_id' => $walletId,
                        'description' => $description
                    ];
                    // Log seria implementado via ConfigManager se disponível
                }
                
                setMessage('success', 'Wallet ID cadastrado com sucesso!', ['wallet_id' => $walletId]);
                break;
                
            case 'toggle_wallet_status':
                if (!$permissions['can_manage_wallets']) {
                    throw new Exception('Você não tem permissão para alterar status de Wallet IDs');
                }
                
                $walletDbId = $_POST['wallet_db_id'] ?? '';
                $currentStatus = (int)($_POST['current_status'] ?? 0);
                
                if (empty($walletDbId)) {
                    throw new Exception('ID do Wallet não especificado');
                }
                
                $db = DatabaseManager::getInstance();
                $newStatus = $currentStatus ? 0 : 1;
                
                // Verificar se o wallet pertence ao polo do usuário (se não for master)
                $filterResult = applyPoloFilter("SELECT id FROM wallet_ids WHERE id = ?", [$walletDbId]);
                $stmt = $db->getConnection()->prepare($filterResult['query']);
                $stmt->execute($filterResult['params']);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Wallet ID não encontrado ou você não tem permissão para alterá-lo');
                }
                
                // Atualizar status
                $stmt = $db->getConnection()->prepare("UPDATE wallet_ids SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$newStatus, $walletDbId]);
                
                setMessage('success', 'Status do Wallet ID ' . ($newStatus ? 'ativado' : 'desativado') . ' com sucesso!');
                break;
                
            case 'delete_wallet':
                if (!$permissions['can_manage_wallets']) {
                    throw new Exception('Você não tem permissão para excluir Wallet IDs');
                }
                
                $walletDbId = $_POST['wallet_db_id'] ?? '';
                
                if (empty($walletDbId)) {
                    throw new Exception('ID do Wallet não especificado');
                }
                
                $db = DatabaseManager::getInstance();
                
                // Verificar se tem splits associados antes de excluir
                $stmt = $db->getConnection()->prepare("
                    SELECT COUNT(*) as count 
                    FROM payment_splits ps 
                    JOIN wallet_ids wi ON ps.wallet_id = wi.wallet_id 
                    WHERE wi.id = ?
                ");
                $stmt->execute([$walletDbId]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    throw new Exception('Não é possível excluir. Este Wallet ID possui ' . $result['count'] . ' split(s) associado(s).');
                }
                
                // Verificar permissão por polo
                $filterResult = applyPoloFilter("SELECT wallet_id, name FROM wallet_ids WHERE id = ?", [$walletDbId]);
                $stmt = $db->getConnection()->prepare($filterResult['query']);
                $stmt->execute($filterResult['params']);
                $walletInfo = $stmt->fetch();
                
                if (!$walletInfo) {
                    throw new Exception('Wallet ID não encontrado ou você não tem permissão para excluí-lo');
                }
                
                // Excluir
                $stmt = $db->getConnection()->prepare("DELETE FROM wallet_ids WHERE id = ?");
                $stmt->execute([$walletDbId]);
                
                setMessage('success', "Wallet ID '{$walletInfo['name']}' removido com sucesso!");
                break;
                
            // ==================================================
            // GERENCIAMENTO DE CLIENTES
            // ==================================================
            
            case 'create_customer':
                // Validações
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
                
                // Limpar CPF/CNPJ
                $customerData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $customerData['cpfCnpj']);
                if (strlen($customerData['cpfCnpj']) !== 11 && strlen($customerData['cpfCnpj']) !== 14) {
                    throw new Exception('CPF deve ter 11 dígitos ou CNPJ deve ter 14 dígitos');
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
                
            // ==================================================
            // GERENCIAMENTO DE CONTAS SPLIT
            // ==================================================
            
            case 'create_account':
                if (!$permissions['can_manage_wallets']) {
                    throw new Exception('Você não tem permissão para criar contas de split');
                }
                
                $accountData = $_POST['account'] ?? [];
                
                // Validações obrigatórias
                $requiredFields = ['name', 'email', 'cpfCnpj', 'mobilePhone', 'address', 'province', 'postalCode'];
                foreach ($requiredFields as $field) {
                    if (empty($accountData[$field])) {
                        throw new Exception("Campo '{$field}' é obrigatório para criar conta split");
                    }
                }
                
                // Limpeza de dados
                $accountData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $accountData['cpfCnpj']);
                $accountData['mobilePhone'] = preg_replace('/[^0-9]/', '', $accountData['mobilePhone']);
                $accountData['postalCode'] = preg_replace('/[^0-9]/', '', $accountData['postalCode']);
                $accountData['incomeValue'] = (int)($accountData['incomeValue'] ?? 2500);
                
                // Validações de formato
                if (!filter_var($accountData['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Email inválido');
                }
                
                if (strlen($accountData['cpfCnpj']) !== 11 && strlen($accountData['cpfCnpj']) !== 14) {
                    throw new Exception('CPF deve ter 11 dígitos ou CNPJ deve ter 14 dígitos');
                }
                
                if (strlen($accountData['mobilePhone']) < 10) {
                    throw new Exception('Telefone deve ter pelo menos 10 dígitos');
                }
                
                if (strlen($accountData['postalCode']) !== 8) {
                    throw new Exception('CEP deve ter 8 dígitos');
                }
                
                // Criar conta via API
                $asaas = getContextualAsaasInstance();
                
                try {
                    $account = $asaas->createAccount($accountData);
                } catch (Exception $e) {
                    // Tratar erros específicos do ASAAS
                    $errorMessage = $e->getMessage();
                    
                    if (strpos($errorMessage, 'já está em uso') !== false || 
                        strpos($errorMessage, 'already exists') !== false) {
                        throw new Exception('Este email já está cadastrado no ASAAS. Use um email diferente.');
                    }
                    
                    if (strpos($errorMessage, 'invalid') !== false) {
                        throw new Exception('Dados inválidos: ' . $errorMessage);
                    }
                    
                    throw new Exception('Erro na API ASAAS: ' . $errorMessage);
                }
                
                // Salvar no banco com informações do polo
                $db = DatabaseManager::getInstance();
                $accountSaveData = array_merge($account, ['polo_id' => $usuario['polo_id']]);
                $db->saveSplitAccount($accountSaveData);
                
                setMessage('success', 'Conta de split criada com sucesso! Wallet ID: ' . $account['walletId'], ['wallet_id' => $account['walletId']]);
                break;
                
            // ==================================================
            // CRIAÇÃO DE PAGAMENTOS COM SPLIT
            // ==================================================
            
            case 'create_payment':
                $paymentData = $_POST['payment'] ?? [];
                $splitsData = $_POST['splits'] ?? [];
                
                // Validações do pagamento
                $requiredPaymentFields = ['customer', 'billingType', 'value', 'description', 'dueDate'];
                foreach ($requiredPaymentFields as $field) {
                    if (empty($paymentData[$field])) {
                        throw new Exception("Campo '{$field}' é obrigatório para criar pagamento");
                    }
                }
                
                // Validar valor
                $paymentValue = floatval($paymentData['value']);
                if ($paymentValue <= 0) {
                    throw new Exception('Valor do pagamento deve ser maior que zero');
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
                            if ($fixedValue >= $paymentValue) {
                                throw new Exception('Valor fixo do split não pode ser maior ou igual ao valor total do pagamento');
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
                    
                    if ($totalFixedValue >= $paymentValue) {
                        throw new Exception('A soma dos valores fixos não pode ser maior ou igual ao valor total do pagamento');
                    }
                    
                    // Validar combinação de percentual + fixo
                    $estimatedSplitValue = ($paymentValue * $totalPercentage / 100) + $totalFixedValue;
                    if ($estimatedSplitValue >= $paymentValue) {
                        throw new Exception('A combinação de valores fixos e percentuais excede o valor do pagamento');
                    }
                }
                
                // Criar pagamento via API
                $asaas = getContextualAsaasInstance();
                $payment = $asaas->createPaymentWithSplit($paymentData, $processedSplits);
                
                // Salvar no banco com informações do polo
                $db = DatabaseManager::getInstance();
                $paymentSaveData = array_merge($payment, ['polo_id' => $usuario['polo_id']]);
                $db->savePayment($paymentSaveData);
                
                if (!empty($processedSplits)) {
                    $db->savePaymentSplits($payment['id'], $processedSplits);
                }
                
                $invoiceLink = isset($payment['invoiceUrl']) ? 
                    " <a href='{$payment['invoiceUrl']}' target='_blank' class='btn btn-sm btn-outline-primary ms-2'><i class='bi bi-eye'></i> Ver Cobrança</a>" : '';
                    
                setMessage('success', 'Pagamento criado com sucesso! ID: ' . substr($payment['id'], 0, 8) . '...' . $invoiceLink, [
                    'payment_id' => $payment['id'],
                    'invoice_url' => $payment['invoiceUrl'] ?? null,
                    'splits_count' => count($processedSplits)
                ]);
                break;
                
            // ==================================================
            // AÇÕES DE SISTEMA
            // ==================================================
            
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
                
            case 'sync_accounts':
                if (!$permissions['can_manage_wallets']) {
                    throw new Exception('Você não tem permissão para sincronizar contas');
                }
                
                $asaas = getContextualAsaasInstance();
                $result = $asaas->syncAccountsFromAsaas();
                
                setMessage('success', $result['message'], ['synced_count' => $result['total_synced'] ?? 0]);
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
    global $message, $messageType, $errorDetails;
    
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
    echo htmlspecialchars($message);
    
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
$topWallets = [];
$recentActivity = [];
$systemHealth = [];

try {
    $db = DatabaseManager::getInstance();
    
    // ==================================================
    // ESTATÍSTICAS CONTEXTUAIS
    // ==================================================
    
    // Obter estatísticas baseadas no contexto do usuário
    if ($isMaster) {
        // Master vê estatísticas globais
        $stats = SystemStats::getGeneralStats();
        $contextLabel = 'Todos os Polos';
    } else {
        // Usuários de polo veem apenas dados do seu polo
        $stats = SystemStats::getGeneralStats($usuario['polo_id']);
        $contextLabel = $usuario['polo_nome'];
    }
    
    // Adicionar informações contextuais às estatísticas
    if ($stats) {
        $stats['context_label'] = $contextLabel;
        $stats['user_type'] = $usuario['tipo'];
        $stats['polo_filter'] = !$isMaster ? $usuario['polo_id'] : null;
    }
    
    // ==================================================
    // CLIENTES RECENTES
    // ==================================================
    
    $customerQuery = "SELECT * FROM customers ORDER BY created_at DESC LIMIT 10";
    $customerResult = applyPoloFilter($customerQuery);
    
    $stmt = $db->getConnection()->prepare($customerResult['query']);
    $stmt->execute($customerResult['params']);
    $customers = $stmt->fetchAll();
    
    // Adicionar informações extras aos clientes
    foreach ($customers as &$customer) {
        $customer['formatted_date'] = date('d/m/Y H:i', strtotime($customer['created_at']));
        $customer['masked_cpf'] = maskDocument($customer['cpf_cnpj'] ?? '');
        
        // Contar pagamentos do cliente
        $stmt = $db->getConnection()->prepare("SELECT COUNT(*) as count FROM payments WHERE customer_id = ?");
        $stmt->execute([$customer['id']]);
        $customer['payment_count'] = $stmt->fetch()['count'] ?? 0;
    }
    
    // ==================================================
    // CONTAS DE SPLIT
    // ==================================================
    
    $accountQuery = "SELECT * FROM split_accounts WHERE status = 'ACTIVE' ORDER BY created_at DESC LIMIT 10";
    $accountResult = applyPoloFilter($accountQuery);
    
    $stmt = $db->getConnection()->prepare($accountResult['query']);
    $stmt->execute($accountResult['params']);
    $splitAccounts = $stmt->fetchAll();
    
    // Adicionar informações extras às contas
    foreach ($splitAccounts as &$account) {
        $account['formatted_date'] = date('d/m/Y H:i', strtotime($account['created_at']));
        $account['masked_cpf'] = maskDocument($account['cpf_cnpj'] ?? '');
        $account['masked_wallet'] = maskWalletId($account['wallet_id'] ?? '');
        
        // Contar splits recebidos
        $stmt = $db->getConnection()->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(
                CASE WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                ELSE (p.value * ps.percentage_value / 100) END
            ), 0) as total_received
            FROM payment_splits ps 
            JOIN payments p ON ps.payment_id = p.id 
            WHERE ps.wallet_id = ? AND p.status = 'RECEIVED'
        ");
        $stmt->execute([$account['wallet_id']]);
        $splitInfo = $stmt->fetch();
        $account['splits_received'] = $splitInfo['count'] ?? 0;
        $account['total_received'] = $splitInfo['total_received'] ?? 0;
    }
    
    // ==================================================
    // PAGAMENTOS RECENTES
    // ==================================================
    
    $paymentQuery = "
        SELECT p.*, c.name as customer_name, c.email as customer_email,
               (SELECT COUNT(*) FROM payment_splits WHERE payment_id = p.id) as splits_count,
               (SELECT COALESCE(SUM(
                   CASE WHEN split_type = 'FIXED' THEN fixed_value 
                   ELSE (p.value * percentage_value / 100) END
               ), 0) FROM payment_splits WHERE payment_id = p.id) as total_split_value
        FROM payments p 
        LEFT JOIN customers c ON p.customer_id = c.id 
        ORDER BY p.created_at DESC LIMIT 15
    ";
    
    $paymentResult = applyPoloFilter($paymentQuery);
    $stmt = $db->getConnection()->prepare($paymentResult['query']);
    $stmt->execute($paymentResult['params']);
    $payments = $stmt->fetchAll();
    
    // Adicionar informações extras aos pagamentos
    foreach ($payments as &$payment) {
        $payment['formatted_date'] = date('d/m/Y H:i', strtotime($payment['created_at']));
        $payment['formatted_due_date'] = date('d/m/Y', strtotime($payment['due_date']));
        $payment['formatted_value'] = 'R$ ' . number_format($payment['value'], 2, ',', '.');
        $payment['status_class'] = getStatusClass($payment['status']);
        $payment['status_icon'] = getStatusIcon($payment['status']);
        $payment['is_overdue'] = $payment['status'] !== 'RECEIVED' && strtotime($payment['due_date']) < time();
        
        // Calcular valor restante após splits
        $payment['remaining_value'] = $payment['value'] - ($payment['total_split_value'] ?? 0);
        $payment['formatted_remaining'] = 'R$ ' . number_format($payment['remaining_value'], 2, ',', '.');
        
        // Formatear splits
        if ($payment['splits_count'] > 0) {
            $payment['splits_summary'] = $payment['splits_count'] . ' split' . ($payment['splits_count'] > 1 ? 's' : '') . 
                                       ' • R$ ' . number_format($payment['total_split_value'], 2, ',', '.');
        } else {
            $payment['splits_summary'] = 'Sem splits';
        }
    }
    
    // ==================================================
    // WALLET IDs
    // ==================================================
    
    $walletQuery = "
        SELECT wi.*, 
               (SELECT COUNT(*) FROM payment_splits ps 
                JOIN payments p ON ps.payment_id = p.id 
                WHERE ps.wallet_id = wi.wallet_id AND p.status = 'RECEIVED') as usage_count,
               (SELECT COALESCE(SUM(
                   CASE WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                   ELSE (p.value * ps.percentage_value / 100) END
               ), 0) FROM payment_splits ps 
                JOIN payments p ON ps.payment_id = p.id 
                WHERE ps.wallet_id = wi.wallet_id AND p.status = 'RECEIVED') as total_earned
        FROM wallet_ids wi 
        ORDER BY wi.created_at DESC LIMIT 50
    ";
    
    $walletResult = applyPoloFilter($walletQuery);
    $stmt = $db->getConnection()->prepare($walletResult['query']);
    $stmt->execute($walletResult['params']);
    $walletIds = $stmt->fetchAll();
    
    // Processar informações dos Wallet IDs
    foreach ($walletIds as &$wallet) {
        $wallet['formatted_date'] = date('d/m/Y H:i', strtotime($wallet['created_at']));
        $wallet['masked_wallet_id'] = maskWalletId($wallet['wallet_id']);
        $wallet['formatted_earned'] = 'R$ ' . number_format($wallet['total_earned'] ?? 0, 2, ',', '.');
        $wallet['status_badge'] = $wallet['is_active'] ? 'success' : 'secondary';
        $wallet['status_text'] = $wallet['is_active'] ? 'Ativo' : 'Inativo';
        $wallet['has_activity'] = ($wallet['usage_count'] ?? 0) > 0;
    }
    
    // ==================================================
    // TOP WALLET IDs (MAIS UTILIZADOS)
    // ==================================================
    
    $topWalletQuery = "
        SELECT wi.name, wi.wallet_id, wi.description,
               COUNT(ps.id) as split_count,
               COALESCE(SUM(
                   CASE WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                   ELSE (p.value * ps.percentage_value / 100) END
               ), 0) as total_received,
               MAX(p.received_date) as last_received
        FROM wallet_ids wi
        LEFT JOIN payment_splits ps ON wi.wallet_id = ps.wallet_id
        LEFT JOIN payments p ON ps.payment_id = p.id AND p.status = 'RECEIVED'
        WHERE wi.is_active = 1
    ";
    
    $topWalletResult = applyPoloFilter($topWalletQuery);
    $topWalletQuery = $topWalletResult['query'] . " GROUP BY wi.id HAVING split_count > 0 ORDER BY total_received DESC LIMIT 5";
    
    $stmt = $db->getConnection()->prepare($topWalletQuery);
    $stmt->execute($topWalletResult['params']);
    $topWallets = $stmt->fetchAll();
    
    foreach ($topWallets as &$topWallet) {
        $topWallet['formatted_total'] = 'R$ ' . number_format($topWallet['total_received'], 2, ',', '.');
        $topWallet['last_received_formatted'] = $topWallet['last_received'] ? 
            date('d/m/Y', strtotime($topWallet['last_received'])) : 'Nunca';
        $topWallet['avg_per_split'] = $topWallet['split_count'] > 0 ? 
            $topWallet['total_received'] / $topWallet['split_count'] : 0;
        $topWallet['formatted_avg'] = 'R$ ' . number_format($topWallet['avg_per_split'], 2, ',', '.');
    }
    
    // ==================================================
    // ATIVIDADE RECENTE (ÚLTIMAS AÇÕES)
    // ==================================================
    
    $activityQuery = "
        SELECT 'payment' as type, id, created_at, status, value, description as title, customer_id
        FROM payments 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'customer' as type, id, created_at, 'active' as status, 0 as value, name as title, id as customer_id
        FROM customers 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'wallet' as type, id, created_at, 
               CASE WHEN is_active THEN 'active' ELSE 'inactive' END as status, 
               0 as value, name as title, null as customer_id
        FROM wallet_ids 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC LIMIT 10
    ";
    
    $activityResult = applyPoloFilter($activityQuery);
    $stmt = $db->getConnection()->prepare($activityResult['query']);
    $stmt->execute($activityResult['params']);
    $recentActivity = $stmt->fetchAll();
    
    foreach ($recentActivity as &$activity) {
        $activity['formatted_date'] = date('d/m H:i', strtotime($activity['created_at']));
        $activity['relative_date'] = getRelativeTime($activity['created_at']);
        
        // Definir ícones e classes por tipo
        switch ($activity['type']) {
            case 'payment':
                $activity['icon'] = 'bi-credit-card';
                $activity['color'] = 'primary';
                $activity['formatted_value'] = 'R$ ' . number_format($activity['value'], 2, ',', '.');
                break;
            case 'customer':
                $activity['icon'] = 'bi-person-plus';
                $activity['color'] = 'success';
                break;
            case 'wallet':
                $activity['icon'] = 'bi-wallet2';
                $activity['color'] = 'info';
                break;
        }
    }
    
    // ==================================================
    // VERIFICAÇÃO DE SAÚDE DO SISTEMA
    // ==================================================
    
    $systemHealth = [
        'database' => true,
        'asaas_connection' => false,
        'permissions' => true,
        'configuration' => true,
        'last_check' => date('Y-m-d H:i:s')
    ];
    
    // Testar conexão ASAAS (apenas para admins)
    if ($permissions['can_configure_asaas']) {
        try {
            $asaas = getContextualAsaasInstance();
            $testResponse = $asaas->listAccounts(1, 0);
            $systemHealth['asaas_connection'] = true;
            $systemHealth['asaas_account_count'] = $testResponse['totalCount'] ?? 0;
        } catch (Exception $e) {
            $systemHealth['asaas_connection'] = false;
            $systemHealth['asaas_error'] = $e->getMessage();
        }
    }
    
    // Verificar configurações críticas
    $systemHealth['configuration'] = (
        defined('ASAAS_ENVIRONMENT') && 
        defined('DB_HOST') && 
        defined('DB_NAME')
    );
    
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

/**
 * Calcular tempo relativo
 */
function getRelativeTime($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) return 'agora há pouco';
    if ($difference < 3600) return floor($difference / 60) . ' min atrás';
    if ($difference < 86400) return floor($difference / 3600) . ' h atrás';
    if ($difference < 604800) return floor($difference / 86400) . ' dias atrás';
    
    return date('d/m/Y', $timestamp);
}

/**
 * Verificar se sistema precisa de configuração
 */
$needsConfiguration = false;
$configurationIssues = [];

// Verificar se as tabelas multi-tenant existem
try {
    $db = DatabaseManager::getInstance();
    $tables = ['polos', 'usuarios', 'sessoes', 'auditoria'];
    
    foreach ($tables as $table) {
        $result = $db->getConnection()->query("SHOW TABLES LIKE '{$table}'");
        if ($result->rowCount() == 0) {
            $needsConfiguration = true;
            $configurationIssues[] = "Tabela '{$table}' não encontrada";
        }
    }
} catch (Exception $e) {
    $needsConfiguration = true;
    $configurationIssues[] = "Erro de conexão com banco: " . $e->getMessage();
}

// Verificar configurações ASAAS
if (!$isMaster && $usuario['polo_id']) {
    try {
        $asaas = getContextualAsaasInstance();
    } catch (Exception $e) {
        $configurationIssues[] = "Configuração ASAAS do polo não encontrada";
    }
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
        
        .user-info {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
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
        
        .btn-action {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-action:hover {
            transform: translateY(-1px);
        }
        
        /* ===== WALLET CARDS ===== */
        .wallet-card {
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        
        .wallet-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-left-color: #764ba2;
        }
        
        .wallet-id-display {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.9em;
            color: #495057;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .wallet-id-display:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        /* ===== TABLES ===== */
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table thead th {
            background: var(--primary-gradient);
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
            transform: scale(1.01);
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
        
        /* ===== ATIVIDADE RECENTE ===== */
        .activity-item {
            border-left: 3px solid;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 0 8px 8px 0;
            background: white;
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            transform: translateX(3px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .activity-item.payment { border-left-color: #0d6efd; }
        .activity-item.customer { border-left-color: #198754; }
        .activity-item.wallet { border-left-color: #0dcaf0; }
        
        /* ===== BADGES CUSTOMIZADOS ===== */
        .badge {
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        .badge.bg-success { background: var(--success-gradient) !important; }
        .badge.bg-warning { background: var(--warning-gradient) !important; }
        .badge.bg-info { background: var(--info-gradient) !important; }
        
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
        
        /* ===== RESPONSIVO ===== */
        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                min-height: auto;
            }
            
            .card-stats {
                margin-bottom: 15px;
            }
            
            .wallet-card {
                margin-bottom: 15px;
            }
            
            .table-responsive {
                border-radius: 8px;
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
        
        /* ===== CONFIGURAÇÃO DE POLO ===== */
        .polo-context {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 8px 12px;
            color: white;
            font-size: 0.9em;
            font-weight: 500;
        }
        
        /* ===== SISTEMA HEALTH ===== */
        .health-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .health-indicator.healthy { background: #28a745; }
        .health-indicator.warning { background: #ffc107; }
        .health-indicator.error { background: #dc3545; }
        
        /* ===== TOOLTIPS CUSTOMIZADOS ===== */
        [data-bs-toggle="tooltip"] {
            cursor: help;
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
                        <small class="text-white-50">Sistema ASAAS v3.0</small>
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
                        
                        <?php if ($permissions['can_manage_wallets']): ?>
                        <a href="#" class="nav-link" data-section="wallets">
                            <i class="bi bi-wallet2"></i> Wallet IDs
                            <span class="badge bg-info ms-auto"><?php echo count($walletIds); ?></span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($permissions['can_manage_wallets']): ?>
                        <a href="#" class="nav-link" data-section="accounts">
                            <i class="bi bi-bank"></i> Contas Split
                            <span class="badge bg-success ms-auto"><?php echo count($splitAccounts); ?></span>
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
                    
                    <!-- Status do Sistema (Bottom) -->
                    <div class="mt-auto pt-3">
                        <div class="text-white-50 small">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>Sistema:</span>
                                <span class="health-indicator <?php echo $systemHealth['database'] && $systemHealth['configuration'] ? 'healthy' : 'warning'; ?>"></span>
                            </div>
                            
                            <?php if ($permissions['can_configure_asaas']): ?>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>ASAAS:</span>
                                <span class="health-indicator <?php echo $systemHealth['asaas_connection'] ? 'healthy' : 'error'; ?>"></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Ambiente:</span>
                                <span class="badge badge-sm <?php echo ASAAS_ENVIRONMENT === 'production' ? 'bg-danger' : 'bg-warning'; ?>">
                                    <?php echo strtoupper(ASAAS_ENVIRONMENT ?? 'DEV'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
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
                            <span class="environment-badge badge bg-<?php echo ASAAS_ENVIRONMENT === 'production' ? 'danger' : 'warning'; ?>">
                                <?php echo strtoupper(ASAAS_ENVIRONMENT ?? 'DEV'); ?>
                            </span>
                            
                            <!-- Botões de Ação Rápida -->
                            <div class="btn-group">
                                <button class="btn btn-outline-primary btn-sm" onclick="testConnection()" 
                                        data-bs-toggle="tooltip" title="Testar conexão com ASAAS">
                                    <i class="bi bi-wifi"></i>
                                </button>
                                
                                <?php if ($permissions['can_view_reports']): ?>
                                <button class="btn btn-outline-success btn-sm" onclick="showSection('reports')" 
                                        data-bs-toggle="tooltip" title="Ver relatórios">
                                    <i class="bi bi-graph-up"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-info btn-sm" onclick="refreshDashboard()" 
                                        data-bs-toggle="tooltip" title="Atualizar dashboard">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </nav>
                
                <!-- Área de Conteúdo -->
                <div class="container-fluid px-4">
                    
                    <!-- Mensagens de Feedback -->
                    <?php showMessage(); ?>
                    
                    <!-- Alerta de Configuração (se necessário) -->
                    <?php if ($needsConfiguration): ?>
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-exclamation-triangle me-2"></i>Configuração Necessária</h5>
                        <p class="mb-2">O sistema detectou alguns problemas que precisam ser corrigidos:</p>
                        <ul class="mb-3">
                            <?php foreach ($configurationIssues as $issue): ?>
                            <li><?php echo htmlspecialchars($issue); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="d-flex gap-2">
                            <?php if ($permissions['can_manage_users']): ?>
                            <a href="admin_master.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-tools"></i> Admin Master
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($permissions['can_configure_asaas']): ?>
                            <a href="config_interface.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-gear"></i> Configurações
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
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
                                <div class="card card-stats" style="background: var(--info-gradient);">
                                    <div class="card-body">
                                        <i class="bi bi-wallet2"></i>
                                        <h3><?php echo number_format($stats['total_wallet_ids'] ?? count($walletIds)); ?></h3>
                                        <p>Wallet IDs</p>
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
                        
                        <!-- Ações Rápidas e Atividade -->
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
                                            
                                            <?php if ($permissions['can_manage_wallets']): ?>
                                            <button class="btn btn-gradient" onclick="showSection('wallets')">
                                                <i class="bi bi-wallet-fill me-2"></i>Novo Wallet ID
                                                <small class="d-block">Cadastrar destinatário de splits</small>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-gradient" onclick="showSection('payments')">
                                                <i class="bi bi-credit-card-2-front me-2"></i>Novo Pagamento
                                                <small class="d-block">Criar cobrança com splits</small>
                                            </button>
                                            
                                            <?php if ($permissions['can_view_reports']): ?>
                                            <button class="btn btn-outline-primary" onclick="showSection('reports')">
                                                <i class="bi bi-graph-up me-2"></i>Ver Relatórios
                                                <small class="d-block">Análises e estatísticas</small>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><i class="bi bi-clock-history me-2"></i>Atividade Recente</h5>
                                        <button class="btn btn-outline-secondary btn-sm" onclick="refreshActivity()">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div id="recent-activity">
                                            <?php if (!empty($recentActivity)): ?>
                                                <?php foreach (array_slice($recentActivity, 0, 5) as $activity): ?>
                                                <div class="activity-item <?php echo $activity['type']; ?>">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <i class="bi <?php echo $activity['icon']; ?> me-2"></i>
                                                                <?php echo htmlspecialchars($activity['title']); ?>
                                                            </h6>
                                                            <?php if (isset($activity['formatted_value'])): ?>
                                                            <small class="text-muted"><?php echo $activity['formatted_value']; ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted"><?php echo $activity['relative_date']; ?></small>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="empty-state">
                                                    <i class="bi bi-activity"></i>
                                                    <p>Nenhuma atividade recente</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Top Wallet IDs e Informações Extras -->
                        <?php if (!empty($topWallets)): ?>
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-trophy me-2"></i>Top Wallet IDs - Mais Utilizados</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php foreach ($topWallets as $index => $wallet): ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="card wallet-card">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                                                #<?php echo $index + 1; ?>
                                                            </span>
                                                            <small class="text-muted"><?php echo $wallet['split_count']; ?> splits</small>
                                                        </div>
                                                        <h6 class="card-title"><?php echo htmlspecialchars($wallet['name']); ?></h6>
                                                        <div class="wallet-id-display mb-2">
                                                            <?php echo maskWalletId($wallet['wallet_id']); ?>
                                                        </div>
                                                        <div class="d-flex justify-content-between">
                                                            <strong class="text-success"><?php echo $wallet['formatted_total']; ?></strong>
                                                            <small class="text-muted">
                                                                Média: <?php echo $wallet['formatted_avg']; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ===== SEÇÃO CLIENTES ===== -->
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
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Endereço</label>
                                                <textarea class="form-control" name="customer[address]" rows="2"
                                                          placeholder="Endereço completo (opcional)"></textarea>
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
                                                            <th>Criado</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach (array_slice($customers, 0, 8) as $customer): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($customer['name']); ?></strong><br>
                                                                <small class="text-muted">
                                                                    Doc: <?php echo $customer['masked_cpf']; ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <small>
                                                                    <?php echo htmlspecialchars($customer['email']); ?><br>
                                                                    <?php echo $customer['payment_count']; ?> pagamento(s)
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted">
                                                                    <?php echo $customer['formatted_date']; ?>
                                                                </small>
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
                    
                    <!-- ===== SEÇÃO WALLET IDs ===== -->
                    <?php if ($permissions['can_manage_wallets']): ?>
                    <!-- <div id="wallets-section" class="section"> -->
                    <div id="wallets" class="section">
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
                                                <small class="form-text text-info">
                                                    <i class="bi bi-info-circle"></i>
                                                    Copie o Wallet ID do painel ASAAS
                                                </small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Descrição (Opcional)</label>
                                                <textarea class="form-control" name="wallet[description]" rows="2"
                                                          placeholder="Ex: Parceiro comercial, comissão de vendas..."></textarea>
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
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><i class="bi bi-list me-2"></i>Wallet IDs Cadastrados</h5>
                                        <span class="badge bg-info"><?php echo count($walletIds); ?></span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($walletIds)): ?>
                                            <div class="row">
                                                <?php foreach (array_slice($walletIds, 0, 12) as $wallet): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card wallet-card">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <h6 class="card-title mb-0">
                                                                    <?php echo htmlspecialchars($wallet['name']); ?>
                                                                </h6>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                                            type="button" data-bs-toggle="dropdown">
                                                                        <i class="bi bi-three-dots"></i>
                                                                    </button>
                                                                    <ul class="dropdown-menu">
                                                                        <li>
                                                                            <button class="dropdown-item" 
                                                                                    onclick="toggleWalletStatus('<?php echo $wallet['id']; ?>', <?php echo $wallet['is_active']; ?>)">
                                                                                <i class="bi bi-<?php echo $wallet['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                                                <?php echo $wallet['is_active'] ? 'Desativar' : 'Ativar'; ?>
                                                                            </button>
                                                                        </li>
                                                                        <li><hr class="dropdown-divider"></li>
                                                                        <li>
                                                                            <button class="dropdown-item text-danger" 
                                                                                    onclick="deleteWallet('<?php echo $wallet['id']; ?>', '<?php echo htmlspecialchars($wallet['name']); ?>')">
                                                                                <i class="bi bi-trash"></i> Excluir
                                                                            </button>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="wallet-id-display mb-2" 
                                                                 onclick="copyToClipboard('<?php echo $wallet['wallet_id']; ?>')">
                                                                <?php echo $wallet['masked_wallet_id']; ?>
                                                                <i class="bi bi-clipboard float-end"></i>
                                                            </div>
                                                            
                                                            <?php if (!empty($wallet['description'])): ?>
                                                            <p class="card-text text-muted small mb-2">
                                                                <?php echo htmlspecialchars($wallet['description']); ?>
                                                            </p>
                                                            <?php endif; ?>
                                                            
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="badge bg-<?php echo $wallet['status_badge']; ?>">
                                                                    <?php echo $wallet['status_text']; ?>
                                                                </span>
                                                                <div class="text-end">
                                                                    <?php if ($wallet['has_activity']): ?>
                                                                    <div class="text-success fw-bold"><?php echo $wallet['formatted_earned']; ?></div>
                                                                    <small class="text-muted"><?php echo $wallet['usage_count']; ?> uso(s)</small>
                                                                    <?php else: ?>
                                                                    <small class="text-muted">Sem atividade</small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="empty-state">
                                                <i class="bi bi-wallet2"></i>
                                                <h5>Nenhum Wallet ID cadastrado</h5>
                                                <p>Cadastre seu primeiro Wallet ID para começar a usar splits</p>
                                                <small class="text-muted">
                                                    Você precisará criar contas no painel ASAAS primeiro
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- ===== SEÇÃO PAGAMENTOS ===== -->
                    <div id="payments-section" class="section">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-plus-circle me-2"></i>Novo Pagamento com Split</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="payment-form">
                                    <input type="hidden" name="action" value="create_payment">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="border-bottom pb-2 mb-3">Dados do Pagamento</h6>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Cliente *</label>
                                                <select class="form-select" name="payment[customer]" required>
                                                    <option value="">Selecione um cliente</option>
                                                    <?php foreach ($customers as $customer): ?>
                                                    <option value="<?php echo $customer['id']; ?>">
                                                        <?php echo htmlspecialchars($customer['name']); ?> 
                                                        (<?php echo htmlspecialchars($customer['email']); ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Tipo de Cobrança *</label>
                                                        <select class="form-select" name="payment[billingType]" required>
                                                            <option value="PIX">PIX</option>
                                                            <option value="BOLETO">Boleto</option>
                                                            <option value="CREDIT_CARD">Cartão de Crédito</option>
                                                            <option value="DEBIT_CARD">Cartão de Débito</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Valor *</label>
                                                        <input type="number" class="form-control" name="payment[value]" 
                                                               step="0.01" min="1" required placeholder="0,00">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Descrição *</label>
                                                <input type="text" class="form-control" name="payment[description]" required
                                                       placeholder="Descrição da cobrança">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Data de Vencimento *</label>
                                                <input type="date" class="form-control" name="payment[dueDate]" 
                                                       value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6 class="border-bottom pb-2 mb-3">
                                                Configuração do Split 
                                                <small class="text-muted">(Opcional)</small>
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
                                                            <?php foreach ($splitAccounts as $account): ?>
                                                            <option value="<?php echo $account['wallet_id']; ?>">
                                                                <?php echo htmlspecialchars($account['name']); ?> (Conta Split)
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <label class="form-label">Percentual (%)</label>
                                                            <input type="number" class="form-control" name="splits[0][percentualValue]" 
                                                                   step="0.01" max="100" placeholder="0.00">
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label">Valor Fixo (R$)</label>
                                                            <input type="number" class="form-control" name="splits[0][fixedValue]" 
                                                                   step="0.01" placeholder="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addSplit()">
                                                    <i class="bi bi-plus me-1"></i>Adicionar Split
                                                </button>
                                                <small class="text-muted">Deixe vazio para não usar splits</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="confirm-payment">
                                            <label class="form-check-label text-muted" for="confirm-payment">
                                                Confirmo que os dados estão corretos
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-gradient" disabled id="submit-payment">
                                            <i class="bi bi-credit-card-2-front me-2"></i>Criar Pagamento
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Lista de Pagamentos Recentes -->
                        <?php if (!empty($payments)): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5><i class="bi bi-list me-2"></i>Pagamentos Recentes</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Cliente</th>
                                                <th>Valor</th>
                                                <th>Status</th>
                                                <th>Splits</th>
                                                <th>Vencimento</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($payments, 0, 10) as $payment): ?>
                                            <tr>
                                                <td>
                                                    <code><?php echo substr($payment['id'], 0, 8); ?>...</code>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($payment['customer_name'] ?? 'Cliente N/A'); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($payment['description']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo $payment['formatted_value']; ?></strong>
                                                    <?php if ($payment['is_overdue']): ?>
                                                    <br><small class="text-danger">Vencido</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $payment['status_class']; ?>">
                                                        <?php echo $payment['status_icon'] . ' ' . $payment['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo $payment['splits_summary']; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo $payment['formatted_due_date']; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                               onclick="viewPayment('<?php echo $payment['id']; ?>')" 
                                                               data-bs-toggle="tooltip" title="Ver detalhes">
                                                           <i class="bi bi-eye"></i>
                                                       </button>
                                                       <button class="btn btn-sm btn-outline-secondary" 
                                                               onclick="refreshPaymentStatus('<?php echo $payment['id']; ?>')" 
                                                               data-bs-toggle="tooltip" title="Atualizar status">
                                                           <i class="bi bi-arrow-clockwise"></i>
                                                       </button>
                                                       <button class="btn btn-sm btn-outline-info" 
                                                               onclick="copyPaymentInfo('<?php echo $payment['id']; ?>')" 
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
                   
                   <!-- ===== SEÇÃO RELATÓRIOS ===== -->
                   <?php if ($permissions['can_view_reports']): ?>
                   <div id="reports-section" class="section">
                       <div class="row">
                           <div class="col-md-6">
                               <div class="card">
                                   <div class="card-header">
                                       <h5><i class="bi bi-calendar me-2"></i>Gerar Relatório</h5>
                                   </div>
                                   <div class="card-body">
                                       <form id="report-form">
                                           <div class="mb-3">
                                               <label class="form-label">Data Inicial</label>
                                               <input type="date" class="form-control" id="start-date" 
                                                      value="<?php echo date('Y-m-01'); ?>">
                                           </div>
                                           
                                           <div class="mb-3">
                                               <label class="form-label">Data Final</label>
                                               <input type="date" class="form-control" id="end-date" 
                                                      value="<?php echo date('Y-m-d'); ?>">
                                           </div>
                                           
                                           <div class="d-grid gap-2">
                                               <button type="button" class="btn btn-gradient" onclick="generateReport()">
                                                   <i class="bi bi-file-earmark-text me-2"></i>Relatório Geral
                                               </button>
                                               
                                               <button type="button" class="btn btn-outline-success" onclick="generateWalletReport()">
                                                   <i class="bi bi-wallet2 me-2"></i>Relatório de Wallet IDs
                                               </button>
                                               
                                               <?php if ($permissions['can_export_data']): ?>
                                               <button type="button" class="btn btn-outline-info" onclick="exportReport('csv')">
                                                   <i class="bi bi-download me-2"></i>Exportar CSV
                                               </button>
                                               <?php endif; ?>
                                           </div>
                                       </form>
                                   </div>
                               </div>
                           </div>
                           
                           <div class="col-md-6">
                               <div class="card">
                                   <div class="card-header">
                                       <h5><i class="bi bi-graph-up me-2"></i>Resumo Rápido</h5>
                                   </div>
                                   <div class="card-body">
                                       <div id="quick-stats">
                                           <div class="row text-center">
                                               <div class="col-6">
                                                   <div class="mb-3">
                                                       <h4 class="text-primary mb-1"><?php echo count($payments); ?></h4>
                                                       <small class="text-muted">Pagamentos</small>
                                                   </div>
                                               </div>
                                               <div class="col-6">
                                                   <div class="mb-3">
                                                       <h4 class="text-success mb-1">
                                                           <?php 
                                                           $receivedCount = count(array_filter($payments, function($p) { 
                                                               return $p['status'] === 'RECEIVED'; 
                                                           }));
                                                           echo $receivedCount;
                                                           ?>
                                                       </h4>
                                                       <small class="text-muted">Recebidos</small>
                                                   </div>
                                               </div>
                                               <div class="col-12">
                                                   <hr>
                                                   <div class="mb-3">
                                                       <h5 class="text-success mb-1">
                                                           R$ <?php 
                                                           $totalReceived = array_sum(array_map(function($p) {
                                                               return $p['status'] === 'RECEIVED' ? $p['value'] : 0;
                                                           }, $payments));
                                                           echo number_format($totalReceived, 2, ',', '.');
                                                           ?>
                                                       </h5>
                                                       <small class="text-muted">Total Recebido (período atual)</small>
                                                   </div>
                                               </div>
                                           </div>
                                       </div>
                                   </div>
                               </div>
                           </div>
                       </div>
                       
                       <div class="card mt-4">
                           <div class="card-header">
                               <h5><i class="bi bi-bar-chart me-2"></i>Resultados do Relatório</h5>
                           </div>
                           <div class="card-body">
                               <div id="report-results">
                                   <div class="empty-state">
                                       <i class="bi bi-graph-up"></i>
                                       <p>Os resultados aparecerão aqui após gerar o relatório</p>
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
       // ===== CONFIGURAÇÃO GLOBAL =====
       const SystemConfig = <?php echo json_encode($jsContext); ?>;
       let currentSection = 'dashboard';
       let splitCounter = 1;
       
       console.log('🚀 Sistema IMEP Split ASAAS v3.0 carregado');
       console.log('👤 Usuário:', SystemConfig.user.nome, '(' + SystemConfig.user.tipo + ')');
       console.log('🏢 Contexto:', SystemConfig.user.polo_nome || 'Master');
       console.log('🔧 Ambiente:', SystemConfig.environment);
       
       // ===== NAVEGAÇÃO ENTRE SEÇÕES =====
       function showSection(section) {
           // Verificar permissões
           if (!checkSectionPermission(section)) {
               showToast('Você não tem permissão para acessar esta seção', 'warning');
               return;
           }
           
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
               
               // Ações específicas por seção
               switch(section) {
                   case 'payments':
                       loadRecentPayments();
                       break;
                   case 'reports':
                       updateQuickStats();
                       break;
               }
               
               // Log para analytics
               console.log('📍 Seção alterada para:', section);
           }
       }
       
       function checkSectionPermission(section) {
           const permissionMap = {
               'wallets': SystemConfig.permissions.can_manage_wallets,
               'accounts': SystemConfig.permissions.can_manage_wallets,
               'reports': SystemConfig.permissions.can_view_reports
           };
           
           return permissionMap[section] !== false;
       }
       
       // Event listeners para navegação
       document.addEventListener('DOMContentLoaded', function() {
           document.querySelectorAll('[data-section]').forEach(link => {
               link.addEventListener('click', (e) => {
                   e.preventDefault();
                   const section = e.target.closest('[data-section]').dataset.section;
                   showSection(section);
               });
           });
           
           // Inicialização
           initializeSystem();
       });
       
       // ===== INICIALIZAÇÃO DO SISTEMA =====
       function initializeSystem() {
           // Tooltips
           const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
           tooltipTriggerList.map(function (tooltipTriggerEl) {
               return new bootstrap.Tooltip(tooltipTriggerEl);
           });
           
           // Máscaras de input
           addInputMasks();
           
           // Validações de formulário
           setupFormValidations();
           
           // Auto-refresh de atividade a cada 5 minutos
           setInterval(refreshActivity, 300000);
           
           // Verificação de saúde do sistema a cada 10 minutos
           setInterval(checkSystemHealth, 600000);
           
           console.log('✅ Sistema inicializado com sucesso');
       }
       
       // ===== GERENCIAMENTO DE WALLET IDs =====
       function copyToClipboard(text) {
           if (navigator.clipboard) {
               navigator.clipboard.writeText(text).then(() => {
                   showToast('Wallet ID copiado para a área de transferência!', 'success');
               }).catch(() => {
                   fallbackCopyToClipboard(text);
               });
           } else {
               fallbackCopyToClipboard(text);
           }
       }
       
       function fallbackCopyToClipboard(text) {
           const textArea = document.createElement('textarea');
           textArea.value = text;
           document.body.appendChild(textArea);
           textArea.focus();
           textArea.select();
           
           try {
               document.execCommand('copy');
               showToast('Wallet ID copiado!', 'success');
           } catch (err) {
               showToast('Erro ao copiar. Use Ctrl+C manualmente.', 'error');
           }
           
           document.body.removeChild(textArea);
       }
       
       function toggleWalletStatus(walletDbId, currentStatus) {
           if (!confirm('Deseja ' + (currentStatus ? 'desativar' : 'ativar') + ' este Wallet ID?')) {
               return;
           }
           
           const formData = new FormData();
           formData.append('action', 'toggle_wallet_status');
           formData.append('wallet_db_id', walletDbId);
           formData.append('current_status', currentStatus);
           
           fetch('', {
               method: 'POST',
               body: formData
           })
           .then(response => response.text())
           .then(() => {
               showToast('Status alterado com sucesso!', 'success');
               setTimeout(() => location.reload(), 1500);
           })
           .catch(error => {
               showToast('Erro ao alterar status: ' + error.message, 'error');
           });
       }
       
       function deleteWallet(walletDbId, walletName) {
           if (!confirm(`Tem certeza que deseja excluir o Wallet ID "${walletName}"?\n\nEsta ação não pode ser desfeita.`)) {
               return;
           }
           
           const formData = new FormData();
           formData.append('action', 'delete_wallet');
           formData.append('wallet_db_id', walletDbId);
           
           fetch('', {
               method: 'POST',
               body: formData
           })
           .then(response => response.text())
           .then(() => {
               showToast('Wallet ID excluído com sucesso!', 'success');
               setTimeout(() => location.reload(), 1500);
           })
           .catch(error => {
               showToast('Erro ao excluir: ' + error.message, 'error');
           });
       }
       
       // ===== GERENCIAMENTO DE SPLITS EM PAGAMENTOS =====
       function addSplit() {
           const container = document.getElementById('splits-container');
           
           // Obter lista de wallet IDs para o novo split
           const walletOptions = Array.from(document.querySelector('select[name="splits[0][walletId]"]').options)
               .map(option => `<option value="${option.value}">${option.textContent}</option>`)
               .join('');
           
           const splitHtml = `
               <div class="split-item p-3 mb-3">
                   <div class="split-remove-btn">
                       <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSplit(this)">
                           <i class="bi bi-trash"></i>
                       </button>
                   </div>
                   
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
                                  step="0.01" max="100" placeholder="0.00">
                       </div>
                       <div class="col-6">
                           <label class="form-label">Valor Fixo (R$)</label>
                           <input type="number" class="form-control" name="splits[${splitCounter}][fixedValue]" 
                                  step="0.01" placeholder="0.00">
                       </div>
                   </div>
               </div>
           `;
           
           container.insertAdjacentHTML('beforeend', splitHtml);
           splitCounter++;
       }
       
       function removeSplit(button) {
           if (document.querySelectorAll('.split-item').length > 1) {
               button.closest('.split-item').remove();
           } else {
               showToast('Deve haver pelo menos um campo de split', 'warning');
           }
       }
       
       // ===== CONEXÃO E TESTES =====
       function testConnection() {
           const btn = event.target.closest('button');
           const originalText = btn.innerHTML;
           btn.innerHTML = '<span class="loading me-2"></span>Testando...';
           btn.disabled = true;
           
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
           })
           .finally(() => {
               btn.innerHTML = originalText;
               btn.disabled = false;
           });
       }
       
       function refreshDashboard() {
           showToast('Atualizando dashboard...', 'info');
           setTimeout(() => location.reload(), 1000);
       }
       
       function refreshActivity() {
           // Implementar refresh via AJAX se necessário
           console.log('🔄 Refresh automático da atividade');
       }
       
       // ===== RELATÓRIOS =====
       function generateReport() {
           const startDate = document.getElementById('start-date').value;
           const endDate = document.getElementById('end-date').value;
           
           if (!startDate || !endDate) {
               showToast('Selecione as datas para o relatório', 'warning');
               return;
           }
           
           if (new Date(startDate) > new Date(endDate)) {
               showToast('Data inicial não pode ser maior que a data final', 'warning');
               return;
           }
           
           const resultsDiv = document.getElementById('report-results');
           resultsDiv.innerHTML = `
               <div class="text-center p-4">
                   <div class="spinner-border text-primary mb-3" role="status"></div>
                   <p>Gerando relatório...</p>
               </div>
           `;
           
           // Simular chamada AJAX para relatório
           setTimeout(() => {
               resultsDiv.innerHTML = `
                   <div class="alert alert-success">
                       <h6><i class="bi bi-check-circle me-2"></i>Relatório Gerado</h6>
                       <p>Período: ${startDate} a ${endDate}</p>
                       <p>Contexto: ${SystemConfig.user.polo_nome || 'Todos os Polos'}</p>
                       <small>Funcionalidade de relatórios será implementada via API</small>
                   </div>
               `;
           }, 2000);
       }
       
       function generateWalletReport() {
           showToast('Gerando relatório de Wallet IDs...', 'info');
           // Implementar via API
       }
       
       function exportReport(format) {
           showToast(`Exportando relatório em formato ${format.toUpperCase()}...`, 'info');
           // Implementar via API
       }
       
       // ===== UTILITÁRIOS =====
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
               window.location.href = 'login.php?action=logout';
           }
       }
       
       // ===== MÁSCARAS DE INPUT =====
       function addInputMasks() {
           // CPF/CNPJ
           document.querySelectorAll('input[name*="cpfCnpj"]').forEach(input => {
               input.addEventListener('input', function(e) {
                   let value = e.target.value.replace(/\D/g, '');
                   
                   if (value.length <= 11) {
                       value = value.replace(/(\d{3})(\d)/, '$1.$2');
                       value = value.replace(/(\d{3})(\d)/, '$1.$2');
                       value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                   } else {
                       value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                       value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                       value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                       value = value.replace(/(\d{4})(\d)/, '$1-$2');
                   }
                   
                   e.target.value = value;
               });
           });
           
           // Telefone
           document.querySelectorAll('input[name*="Phone"]').forEach(input => {
               input.addEventListener('input', function(e) {
                   let value = e.target.value.replace(/\D/g, '');
                   
                   if (value.length > 11) value = value.substring(0, 11);
                   
                   if (value.length <= 10) {
                       value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                       value = value.replace(/(\d{4})(\d)/, '$1-$2');
                   } else {
                       value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                       value = value.replace(/(\d{5})(\d)/, '$1-$2');
                   }
                   
                   e.target.value = value;
               });
           });
       }
       
       // ===== VALIDAÇÕES DE FORMULÁRIO =====
       function setupFormValidations() {
           // Confirmação obrigatória para pagamento
           const confirmCheckbox = document.getElementById('confirm-payment');
           const submitButton = document.getElementById('submit-payment');
           
           if (confirmCheckbox && submitButton) {
               confirmCheckbox.addEventListener('change', function() {
                   submitButton.disabled = !this.checked;
               });
           }
           
           // Validação de Wallet ID UUID
           document.querySelectorAll('input[name*="wallet_id"]').forEach(input => {
               input.addEventListener('blur', function() {
                   const value = this.value.trim();
                   if (value && !isValidUUID(value)) {
                       this.classList.add('is-invalid');
                       showToast('Formato de Wallet ID inválido. Use formato UUID.', 'warning');
                   } else {
                       this.classList.remove('is-invalid');
                   }
               });
           });
       }
       
       function isValidUUID(uuid) {
           const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
           return uuidRegex.test(uuid);
       }
       
       // ===== MONITORAMENTO DO SISTEMA =====
       function checkSystemHealth() {
           // Verificação silenciosa da saúde do sistema
           console.log('🔍 Verificando saúde do sistema...');
       }
       
       function loadRecentPayments() {
           // Carregar pagamentos via AJAX se necessário
           console.log('📊 Carregando pagamentos recentes...');
       }
       
       function updateQuickStats() {
           // Atualizar estatísticas rápidas
           console.log('📈 Atualizando estatísticas...');
       }
       
       // ===== ATALHOS DE TECLADO =====
       document.addEventListener('keydown', function(e) {
           // Ctrl + 1-5 para navegação rápida
           if (e.ctrlKey && !e.shiftKey && !e.altKey) {
               const sections = ['dashboard', 'customers', 'wallets', 'payments', 'reports'];
               const key = parseInt(e.key);
               if (key >= 1 && key <= sections.length) {
                   e.preventDefault();
                   showSection(sections[key - 1]);
               }
           }
           
           // ESC para voltar ao dashboard
           if (e.key === 'Escape') {
               showSection('dashboard');
           }
       });
       
       // Log de inicialização completa
       window.addEventListener('load', function() {
           console.log('🎉 Sistema IMEP Split ASAAS v3.0 totalmente carregado');
           console.log('⌨️ Atalhos: Ctrl+1-5 para navegação, ESC para dashboard');
       });
   </script>
</body>
</html>    