<?php
/**
 * Interface Web para Sistema de Split ASAAS
 * Arquivo: index.php
 * Versão Completa com todas as melhorias
 */

// Incluir arquivos necessários
require_once 'bootstrap.php';

// Iniciar sessão para mensagens
session_start();

// Função para exibir mensagens
function showMessage($type = 'info', $message = '') {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message']['type'];
        $message = $_SESSION['message']['text'];
        unset($_SESSION['message']);
    }
    
    if ($message) {
        $alertClass = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];
        
        echo "<div class='alert {$alertClass[$type]} alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

// Função para definir mensagem na sessão
function setMessage($type, $message) {
    $_SESSION['message'] = ['type' => $type, 'text' => $message];
}

// Processar ações via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_wallet':
                $db = DatabaseManager::getInstance();
                
                $walletData = [
                    'id' => uniqid('wallet_'),
                    'wallet_id' => $_POST['wallet']['wallet_id'],
                    'name' => $_POST['wallet']['name'],
                    'description' => $_POST['wallet']['description'] ?? null,
                    'is_active' => 1
                ];
                
                $db->saveWalletId($walletData);
                setMessage('success', 'Wallet ID cadastrado com sucesso!');
                break;
                
            case 'create_customer':
                $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                $customer = $asaas->createCustomer($_POST['customer']);
                
                // Salvar no banco
                $db = DatabaseManager::getInstance();
                $db->saveCustomer($customer);
                
                setMessage('success', 'Cliente criado com sucesso! ID: ' . $customer['id']);
                break;
                
            case 'create_account':
                $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                
                // Processar dados antes de enviar
                $accountData = $_POST['account'];
                
                // Limpar CPF/CNPJ (apenas números)
                $accountData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $accountData['cpfCnpj']);
                
                // Limpar telefone (apenas números)
                $accountData['mobilePhone'] = preg_replace('/[^0-9]/', '', $accountData['mobilePhone']);
                
                // Limpar CEP (apenas números)
                $accountData['postalCode'] = preg_replace('/[^0-9]/', '', $accountData['postalCode']);
                
                // Converter incomeValue para inteiro
                $accountData['incomeValue'] = (int)$accountData['incomeValue'];
                
                $account = $asaas->createAccount($accountData);
                
                // Salvar no banco
                $db = DatabaseManager::getInstance();
                $db->saveSplitAccount($account);
                
                setMessage('success', 'Conta de split criada! Wallet ID: ' . $account['walletId']);
                break;
                
            case 'create_payment':
                $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                
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
                
                // Salvar no banco
                $db = DatabaseManager::getInstance();
                $db->savePayment($payment);
                if (!empty($splits)) {
                    $db->savePaymentSplits($payment['id'], $splits);
                }
                
                setMessage('success', 'Pagamento criado! <a href="' . $payment['invoiceUrl'] . '" target="_blank">Ver Cobrança</a>');
                break;
                
            case 'test_connection':
                $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                $accounts = $asaas->listAccounts(1, 0);
                setMessage('success', 'Conexão OK! ' . $accounts['totalCount'] . ' contas encontradas.');
                break;
                
            case 'sync_accounts':
                $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                $result = $asaas->syncAccountsFromAsaas();
                
                setMessage('success', $result['message']);
                break;
                
            case 'install_system':
                if (SystemInstaller::install()) {
                    setMessage('success', 'Sistema instalado com sucesso!');
                } else {
                    setMessage('error', 'Erro na instalação do sistema.');
                }
                break;
                
            case 'toggle_wallet_status':
                $db = DatabaseManager::getInstance();
                $walletId = $_POST['wallet_id'];
                $newStatus = $_POST['status'] == '1' ? 0 : 1;
                
                $stmt = $db->getConnection()->prepare("UPDATE wallet_ids SET is_active = ? WHERE id = ?");
                $stmt->execute([$newStatus, $walletId]);
                
                setMessage('success', 'Status do Wallet ID atualizado!');
                break;
                
            case 'delete_wallet':
                $db = DatabaseManager::getInstance();
                $walletId = $_POST['wallet_id'];
                
                $stmt = $db->getConnection()->prepare("DELETE FROM wallet_ids WHERE id = ?");
                $stmt->execute([$walletId]);
                
                setMessage('success', 'Wallet ID removido com sucesso!');
                break;
        }
        
    } catch (Exception $e) {
        setMessage('error', 'Erro: ' . $e->getMessage());
    }
    
    // Redirecionar para evitar reenvio de formulário
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Obter dados para a interface
$stats = SystemStats::getGeneralStats();
$customers = [];
$splitAccounts = [];
$payments = [];
$walletIds = [];

try {
    $db = DatabaseManager::getInstance();
    
    // Buscar clientes recentes
    $stmt = $db->getConnection()->query("SELECT * FROM customers ORDER BY created_at DESC LIMIT 5");
    $customers = $stmt->fetchAll();
    
    // Buscar contas de split
    $stmt = $db->getConnection()->query("SELECT * FROM split_accounts ORDER BY created_at DESC LIMIT 5");
    $splitAccounts = $stmt->fetchAll();
    
    // Buscar pagamentos recentes
    $stmt = $db->getConnection()->query("SELECT * FROM payments ORDER BY created_at DESC LIMIT 5");
    $payments = $stmt->fetchAll();
    
    // Buscar wallet IDs
    $stmt = $db->getConnection()->query("SELECT * FROM wallet_ids ORDER BY created_at DESC LIMIT 20");
    $walletIds = $stmt->fetchAll();
    
} catch (Exception $e) {
    // Banco provavelmente não está configurado
    $needsInstall = true;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Split ASAAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-stats {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .btn-gradient {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            color: white;
        }
        .btn-gradient:hover {
            background: linear-gradient(45deg, #764ba2, #667eea);
            color: white;
        }
        .wallet-card {
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        .wallet-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .wallet-id-code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px;
            font-size: 0.9em;
            color: #495057;
            cursor: pointer;
        }
        .wallet-id-code:hover {
            background: #e9ecef;
        }
        
        /* Timeline CSS */
        .timeline {
            position: relative;
            padding: 20px 0;
        }

        .timeline-item {
            position: relative;
            padding-left: 40px;
            margin-bottom: 30px;
        }

        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 30px;
            height: calc(100% + 10px);
            width: 2px;
            background: #dee2e6;
        }

        .timeline-marker {
            position: absolute;
            left: 0;
            top: 5px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #dee2e6;
        }

        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .timeline-content h6 {
            margin-bottom: 10px;
            color: #495057;
        }
    </style>
</head>
<body>
    <!-- parte 2 -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="d-flex flex-column p-3 text-white">
                    <h4 class="mb-3"><i class="bi bi-credit-card-2-front"></i> Split ASAAS</h4>
                    
                    <ul class="nav nav-pills flex-column mb-auto">
                        <li class="nav-item">
                            <a href="#dashboard" class="nav-link text-white active" data-section="dashboard">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="#customers" class="nav-link text-white" data-section="customers">
                                <i class="bi bi-people"></i> Clientes
                            </a>
                        </li>
                        <li>
                            <a href="#wallets" class="nav-link text-white" data-section="wallets">
                                <i class="bi bi-wallet2"></i> Wallet IDs
                            </a>
                        </li>
                        <li>
                            <a href="#accounts" class="nav-link text-white" data-section="accounts">
                                <i class="bi bi-bank"></i> Contas Split
                            </a>
                        </li>
                        <li>
                            <a href="#payments" class="nav-link text-white" data-section="payments">
                                <i class="bi bi-credit-card"></i> Pagamentos
                            </a>
                        </li>
                        <li>
                            <a href="#reports" class="nav-link text-white" data-section="reports">
                                <i class="bi bi-graph-up"></i> Relatórios
                            </a>
                        </li>
                        <li>
                            <a href="#settings" class="nav-link text-white" data-section="settings">
                                <i class="bi bi-gear"></i> Configurações
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Conteúdo Principal -->
            <div class="col-md-9 col-lg-10">
                <nav class="navbar navbar-expand-lg navbar-light bg-light">
                    <div class="container-fluid">
                        <span class="navbar-brand">Sistema de Split de Pagamentos</span>
                        <div class="d-flex">
                            <span class="badge bg-<?php echo ASAAS_ENVIRONMENT === 'production' ? 'danger' : 'warning'; ?> me-2">
                                <?php echo strtoupper(ASAAS_ENVIRONMENT); ?>
                            </span>
                            <button class="btn btn-outline-primary btn-sm" onclick="testConnection()">
                                <i class="bi bi-wifi"></i> Testar Conexão
                            </button>
                        </div>
                    </div>
                </nav>
                
                <div class="container-fluid p-4">
                    <?php showMessage(); ?>
                    
                    <?php if (isset($needsInstall)): ?>
                    <!-- Instalação Necessária -->
                    <div class="alert alert-warning">
                        <h4><i class="bi bi-exclamation-triangle"></i> Sistema não instalado</h4>
                        <p>Parece que o sistema ainda não foi configurado. Clique no botão abaixo para instalar.</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="install_system">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-download"></i> Instalar Sistema
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Dashboard -->
                    <div id="dashboard-section" class="section">
                        <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
                        
                        <?php if ($stats): ?>
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card card-stats">
                                    <div class="card-body text-center">
                                        <i class="bi bi-people display-4"></i>
                                        <h3><?php echo number_format($stats['total_customers']); ?></h3>
                                        <p>Clientes</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-stats">
                                    <div class="card-body text-center">
                                        <i class="bi bi-wallet2 display-4"></i>
                                        <h3><?php echo number_format(count($walletIds)); ?></h3>
                                        <p>Wallet IDs</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-stats">
                                    <div class="card-body text-center">
                                        <i class="bi bi-credit-card display-4"></i>
                                        <h3><?php echo number_format($stats['total_payments']); ?></h3>
                                        <p>Pagamentos</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-stats">
                                    <div class="card-body text-center">
                                        <i class="bi bi-currency-dollar display-4"></i>
                                        <h3>R$ <?php echo number_format($stats['total_value'], 2, ',', '.'); ?></h3>
                                        <p>Total Recebido</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Ações Rápidas -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-lightning"></i> Ações Rápidas</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-gradient" onclick="showSection('customers')">
                                                <i class="bi bi-person-plus"></i> Novo Cliente
                                            </button>
                                            <button class="btn btn-gradient" onclick="showSection('wallets')">
                                                <i class="bi bi-wallet-fill"></i> Novo Wallet ID
                                            </button>
                                            <button class="btn btn-gradient" onclick="showSection('payments')">
                                                <i class="bi bi-credit-card-2-front"></i> Novo Pagamento
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-clock-history"></i> Atividade Recente</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group list-group-flush">
                                            <?php if (!empty($payments)): ?>
                                                <?php foreach (array_slice($payments, 0, 3) as $payment): ?>
                                                <div class="list-group-item">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1">Pagamento #<?php echo substr($payment['id'], -8); ?></h6>
                                                        <small><?php echo date('d/m/Y', strtotime($payment['created_at'])); ?></small>
                                                    </div>
                                                    <p class="mb-1">R$ <?php echo number_format($payment['value'], 2, ',', '.'); ?></p>
                                                    <span class="badge bg-<?php echo $payment['status'] === 'RECEIVED' ? 'success' : 'warning'; ?>">
                                                        <?php echo $payment['status']; ?>
                                                    </span>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="text-muted">Nenhuma atividade recente</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Clientes -->
                    <div id="customers-section" class="section" style="display: none;">
                        <h2><i class="bi bi-people"></i> Clientes</h2>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-person-plus"></i> Novo Cliente</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="create_customer">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Nome Completo *</label>
                                                <input type="text" class="form-control" name="customer[name]" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Email *</label>
                                                <input type="email" class="form-control" name="customer[email]" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">CPF/CNPJ *</label>
                                                <input type="text" class="form-control" name="customer[cpfCnpj]" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Telefone</label>
                                                <input type="text" class="form-control" name="customer[mobilePhone]">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Endereço</label>
                                                <input type="text" class="form-control" name="customer[address]">
                                            </div>
                                            
                                            <button type="submit" class="btn btn-gradient">
                                                <i class="bi bi-save"></i> Criar Cliente
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-list"></i> Clientes Recentes</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($customers)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Nome</th>
                                                            <th>Email</th>
                                                            <th>Data</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($customers as $customer): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                                            <td><?php echo date('d/m/Y', strtotime($customer['created_at'])); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Nenhum cliente cadastrado</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- parte 3 -->
                    <!-- Nova Seção: Wallet IDs -->
                    <div id="wallets-section" class="section" style="display: none;">
                        <h2><i class="bi bi-wallet2"></i> Wallet IDs</h2>
                        <p class="text-muted">Gerencie os Wallet IDs para distribuição de splits de pagamento</p>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5><i class="bi bi-plus-circle"></i> Novo Wallet ID</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="create_wallet">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Nome/Razão Social *</label>
                                                <input type="text" class="form-control" name="wallet[name]" required 
                                                       placeholder="Ex: João Silva ou Empresa LTDA">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Wallet ID *</label>
                                                <input type="text" class="form-control" name="wallet[wallet_id]" required 
                                                       placeholder="Ex: 22e49670-27e4-4579-a4c1-205c8a40497c">
                                                <small class="form-text text-muted">
                                                    Copie o Wallet ID diretamente do painel ASAAS
                                                </small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Descrição (Opcional)</label>
                                                <textarea class="form-control" name="wallet[description]" rows="2" 
                                                          placeholder="Ex: Parceiro comercial, comissão de vendas, etc."></textarea>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-save"></i> Cadastrar Wallet ID
                                            </button>
                                            
                                            <div class="mt-3">
                                                <small class="text-info">
                                                    <i class="bi bi-info-circle"></i> 
                                                    <strong>Como obter o Wallet ID:</strong><br>
                                                    1. Acesse o painel ASAAS<br>
                                                    2. Vá em Integrações > Contas<br>
                                                    3. Copie o Wallet ID da conta desejada
                                                </small>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><i class="bi bi-list"></i> Wallet IDs Cadastrados</h5>
                                        <span class="badge bg-primary"><?php echo count($walletIds); ?> cadastrados</span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($walletIds)): ?>
                                            <div class="row">
                                                <?php foreach ($walletIds as $wallet): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card wallet-card h-100">
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
                                                                            <form method="POST" style="display: inline;">
                                                                                <input type="hidden" name="action" value="toggle_wallet_status">
                                                                                <input type="hidden" name="wallet_id" value="<?php echo $wallet['id']; ?>">
                                                                                <input type="hidden" name="status" value="<?php echo $wallet['is_active']; ?>">
                                                                                <button type="submit" class="dropdown-item">
                                                                                    <i class="bi bi-<?php echo $wallet['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                                                    <?php echo $wallet['is_active'] ? 'Desativar' : 'Ativar'; ?>
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <li><hr class="dropdown-divider"></li>
                                                                        <li>
                                                                            <form method="POST" style="display: inline;" 
                                                                                  onsubmit="return confirm('Confirma a exclusão?')">
                                                                                <input type="hidden" name="action" value="delete_wallet">
                                                                                <input type="hidden" name="wallet_id" value="<?php echo $wallet['id']; ?>">
                                                                                <button type="submit" class="dropdown-item text-danger">
                                                                                    <i class="bi bi-trash"></i> Excluir
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="wallet-id-code mb-2" onclick="copyToClipboard(this.textContent)">
                                                                <?php echo htmlspecialchars($wallet['wallet_id']); ?>
                                                                <i class="bi bi-clipboard float-end" title="Clique para copiar"></i>
                                                            </div>
                                                            
                                                            <?php if (!empty($wallet['description'])): ?>
                                                            <p class="card-text text-muted small mb-2">
                                                                <?php echo htmlspecialchars($wallet['description']); ?>
                                                            </p>
                                                            <?php endif; ?>
                                                            
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="badge bg-<?php echo $wallet['is_active'] ? 'success' : 'secondary'; ?>">
                                                                    <?php echo $wallet['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                                                </span>
                                                                <small class="text-muted">
                                                                    <?php echo date('d/m/Y', strtotime($wallet['created_at'])); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-5">
                                                <i class="bi bi-wallet2 display-1 text-muted"></i>
                                                <h5 class="mt-3 text-muted">Nenhum Wallet ID cadastrado</h5>
                                                <p class="text-muted">Cadastre seu primeiro Wallet ID para começar a usar splits</p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <div class="alert alert-info">
                                                <h6><i class="bi bi-lightbulb"></i> Dicas para Wallet IDs:</h6>
                                                <ul class="mb-0">
                                                    <li>Use nomes descritivos para facilitar a identificação</li>
                                                    <li>Mantenha os Wallet IDs organizados por tipo (parceiros, comissões, etc.)</li>
                                                    <li>Desative Wallet IDs que não estão mais em uso</li>
                                                    <li>Sempre valide o Wallet ID no painel ASAAS antes de cadastrar</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Contas Split -->
                    <div id="accounts-section" class="section" style="display: none;">
                        <h2><i class="bi bi-bank"></i> Contas de Split</h2>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-plus-circle"></i> Nova Conta Split</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="create_account">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Nome/Razão Social *</label>
                                                <input type="text" class="form-control" name="account[name]" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Email *</label>
                                                <input type="email" class="form-control" name="account[email]" required
                                                       onblur="checkEmailExists(this)">
                                                <div id="email-feedback" class="form-text"></div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">CPF/CNPJ *</label>
                                                <input type="text" class="form-control" name="account[cpfCnpj]" required 
                                                       placeholder="000.000.000-00 ou 00.000.000/0000-00">
                                                <small class="form-text text-muted">Para CNPJ, use o tipo "LTDA" ou "MEI"</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Telefone *</label>
                                                <input type="text" class="form-control" name="account[mobilePhone]" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Endereço *</label>
                                                <input type="text" class="form-control" name="account[address]" required 
                                                       placeholder="Rua, Avenida, etc.">
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <div class="mb-3">
                                                        <label class="form-label">Bairro *</label>
                                                        <input type="text" class="form-control" name="account[district]" required
                                                               placeholder="Centro, Comercial, etc.">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Número</label>
                                                        <input type="text" class="form-control" name="account[addressNumber]" 
                                                               placeholder="123">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">CEP *</label>
                                                        <input type="text" class="form-control" name="account[postalCode]" required
                                                               placeholder="00000-000">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Complemento</label>
                                                        <input type="text" class="form-control" name="account[complement]" 
                                                               placeholder="Apto, Sala, etc.">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Tipo de Empresa *</label>
                                                <select class="form-control" name="account[companyType]" required>
                                                    <option value="">Selecione o tipo</option>
                                                    <option value="MEI">MEI - Microempreendedor Individual</option>
                                                    <option value="LIMITED">LTDA - Sociedade Limitada</option>
                                                    <option value="INDIVIDUAL">Pessoa Física</option>
                                                    <option value="ASSOCIATION">Associação</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Renda/Faturamento Mensal *</label>
                                                <select class="form-control" name="account[incomeValue]" required>
                                                    <option value="">Selecione a faixa</option>
                                                    <option value="1500">Até R$ 1.500</option>
                                                    <option value="2500">R$ 1.500 a R$ 3.000</option>
                                                    <option value="4000">R$ 3.000 a R$ 5.000</option>
                                                    <option value="7500">R$ 5.000 a R$ 10.000</option>
                                                    <option value="15000">R$ 10.000 a R$ 20.000</option>
                                                    <option value="35000">R$ 20.000 a R$ 50.000</option>
                                                    <option value="75000">Acima de R$ 50.000</option>
                                                </select>
                                                <small class="form-text text-muted">Valor numérico do faturamento mensal</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Estado *</label>
                                                <select class="form-control" name="account[province]" required>
                                                    <option value="">Selecione o estado</option>
                                                    <option value="AC">Acre</option>
                                                    <option value="AL">Alagoas</option>
                                                    <option value="AP">Amapá</option>
                                                    <option value="AM">Amazonas</option>
                                                    <option value="BA">Bahia</option>
                                                    <option value="CE">Ceará</option>
                                                    <option value="DF">Distrito Federal</option>
                                                    <option value="ES">Espírito Santo</option>
                                                    <option value="GO">Goiás</option>
                                                    <option value="MA">Maranhão</option>
                                                    <option value="MT">Mato Grosso</option>
                                                    <option value="MS">Mato Grosso do Sul</option>
                                                    <option value="MG">Minas Gerais</option>
                                                    <option value="PA">Pará</option>
                                                    <option value="PB">Paraíba</option>
                                                    <option value="PR">Paraná</option>
                                                    <option value="PE">Pernambuco</option>
                                                    <option value="PI">Piauí</option>
                                                    <option value="RJ">Rio de Janeiro</option>
                                                    <option value="RN">Rio Grande do Norte</option>
                                                    <option value="RS">Rio Grande do Sul</option>
                                                    <option value="RO">Rondônia</option>
                                                    <option value="RR">Roraima</option>
                                                    <option value="SC">Santa Catarina</option>
                                                    <option value="SP">São Paulo</option>
                                                    <option value="SE">Sergipe</option>
                                                    <option value="TO">Tocantins</option>
                                                </select>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-gradient">
                                                <i class="bi bi-save"></i> Criar Conta
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><i class="bi bi-list"></i> Contas Cadastradas</h5>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="syncAccounts()">
                                            <i class="bi bi-arrow-clockwise"></i> Sincronizar
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($splitAccounts)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Nome</th>
                                                            <th>Wallet ID</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($splitAccounts as $account): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($account['name']); ?></strong><br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($account['email']); ?></small>
                                                            </td>
                                                            <td><code><?php echo $account['wallet_id']; ?></code></td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $account['status'] === 'ACTIVE' ? 'success' : 'warning'; ?>">
                                                                    <?php echo $account['status']; ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center">
                                                <p class="text-muted">Nenhuma conta cadastrada</p>
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="syncAccounts()">
                                                    <i class="bi bi-download"></i> Importar do ASAAS
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- parte 4 -->
                    <!-- Seção Pagamentos -->
                    <div id="payments-section" class="section" style="display: none;">
                        <h2><i class="bi bi-credit-card"></i> Pagamentos</h2>
                        
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-plus-circle"></i> Novo Pagamento com Split</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="create_payment">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Dados do Pagamento</h6>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Cliente ID *</label>
                                                <select class="form-control" name="payment[customer]" required>
                                                    <option value="">Selecione um cliente</option>
                                                    <?php foreach ($customers as $customer): ?>
                                                    <option value="<?php echo $customer['id']; ?>">
                                                        <?php echo htmlspecialchars($customer['name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Tipo de Cobrança *</label>
                                                <select class="form-control" name="payment[billingType]" required>
                                                    <option value="PIX">PIX</option>
                                                    <option value="BOLETO">Boleto</option>
                                                    <option value="CREDIT_CARD">Cartão de Crédito</option>
                                                    <option value="DEBIT_CARD">Cartão de Débito</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Valor *</label>
                                                <input type="number" class="form-control" name="payment[value]" step="0.01" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Descrição *</label>
                                                <input type="text" class="form-control" name="payment[description]" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Data de Vencimento *</label>
                                                <input type="date" class="form-control" name="payment[dueDate]" 
                                                       value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6>Configuração do Split</h6>
                                            
                                            <div id="splits-container">
                                                <div class="split-item mb-3 border p-3 rounded">
                                                    <div class="mb-2">
                                                        <label class="form-label">Wallet ID</label>
                                                        <select class="form-control" name="splits[0][walletId]">
                                                            <option value="">Selecione um Wallet ID</option>
                                                            <?php 
                                                            // Combinar Wallet IDs cadastrados e contas split
                                                            $allWallets = [];
                                                            
                                                            // Adicionar Wallet IDs simples
                                                            foreach ($walletIds as $wallet) {
                                                                if ($wallet['is_active']) {
                                                                    $allWallets[] = [
                                                                        'wallet_id' => $wallet['wallet_id'],
                                                                        'name' => $wallet['name'],
                                                                        'type' => 'Wallet ID'
                                                                    ];
                                                                }
                                                            }
                                                            
                                                            // Adicionar contas split
                                                            foreach ($splitAccounts as $account) {
                                                                $allWallets[] = [
                                                                    'wallet_id' => $account['wallet_id'],
                                                                    'name' => $account['name'],
                                                                    'type' => 'Conta Split'
                                                                ];
                                                            }
                                                            
                                                            foreach ($allWallets as $wallet): ?>
                                                            <option value="<?php echo $wallet['wallet_id']; ?>">
                                                                <?php echo htmlspecialchars($wallet['name']); ?> 
                                                                (<?php echo $wallet['type']; ?>)
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <label class="form-label">Percentual (%)</label>
                                                            <input type="number" class="form-control" name="splits[0][percentualValue]" step="0.01" max="100">
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label">Valor Fixo (R$)</label>
                                                            <input type="number" class="form-control" name="splits[0][fixedValue]" step="0.01">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addSplit()">
                                                <i class="bi bi-plus"></i> Adicionar Split
                                            </button>
                                            
                                            <div class="alert alert-info">
                                                <small>
                                                    <strong>💡 Dica:</strong> Use Wallet IDs para splits rápidos ou Contas Split para parceiros completos.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-gradient">
                                        <i class="bi bi-credit-card-2-front"></i> Criar Pagamento
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Lista de Pagamentos Melhorada -->
                        <div class="card mt-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="bi bi-list"></i> Pagamentos</h5>
                                <div class="btn-group">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" 
                                            data-bs-toggle="dropdown">
                                        <i class="bi bi-funnel"></i> Filtros
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><h6 class="dropdown-header">Status</h6></li>
                                        <li><a class="dropdown-item" href="#" onclick="loadPayments(1, 10, '')">Todos</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="loadPayments(1, 10, 'PENDING')">Pendentes</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="loadPayments(1, 10, 'RECEIVED')">Recebidos</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="loadPayments(1, 10, 'OVERDUE')">Vencidos</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><h6 class="dropdown-header">Tipo</h6></li>
                                        <li><a class="dropdown-item" href="#" onclick="loadPayments(1, 10, '', 'PIX')">PIX</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="loadPayments(1, 10, '', 'BOLETO')">Boleto</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="loadPayments(1, 10, '', 'CREDIT_CARD')">Cartão</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="payments-container">
                                    <!-- Os pagamentos serão carregados aqui via JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Relatórios -->
                    <div id="reports-section" class="section" style="display: none;">
                        <h2><i class="bi bi-graph-up"></i> Relatórios</h2>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-calendar"></i> Relatório por Período</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="report-form">
                                            <div class="mb-3">
                                                <label class="form-label">Data Inicial</label>
                                                <input type="date" class="form-control" id="start-date" value="<?php echo date('Y-m-01'); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Data Final</label>
                                                <input type="date" class="form-control" id="end-date" value="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-gradient" onclick="generateReport()">
                                                    <i class="bi bi-file-earmark-text"></i> Gerar Relatório
                                                </button>
                                                
                                                <button type="button" class="btn btn-outline-success" onclick="generateWalletReport()">
                                                    <i class="bi bi-wallet2"></i> Relatório de Wallet IDs
                                                </button>
                                                
                                                <button type="button" class="btn btn-outline-info" onclick="exportReport('csv')">
                                                    <i class="bi bi-download"></i> Exportar CSV
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-trophy"></i> Top Recebedores</h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="top-receivers">
                                            <p class="text-muted">Clique em "Gerar Relatório" para ver os dados</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5><i class="bi bi-bar-chart"></i> Resultados do Relatório</h5>
                            </div>
                            <div class="card-body">
                                <div id="report-results">
                                    <p class="text-muted">Os resultados aparecerão aqui após gerar o relatório</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Configurações -->
                    <div id="settings-section" class="section" style="display: none;">
                        <h2><i class="bi bi-gear"></i> Configurações</h2>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-key"></i> Configurações da API</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Ambiente Atual</label>
                                            <input type="text" class="form-control" value="<?php echo strtoupper(ASAAS_ENVIRONMENT); ?>" readonly>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">API Key (Sandbox)</label>
                                            <input type="password" class="form-control" value="<?php echo substr(ASAAS_SANDBOX_API_KEY, 0, 20) . '...'; ?>" readonly>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">API Key (Produção)</label>
                                            <input type="password" class="form-control" value="<?php echo substr(ASAAS_PRODUCTION_API_KEY, 0, 20) . '...'; ?>" readonly>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> 
                                            Para alterar as configurações, edite o arquivo <code>config_api.php</code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-tools"></i> Ferramentas do Sistema</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-primary" onclick="testConnection()">
                                                <i class="bi bi-wifi"></i> Testar Conexão API
                                            </button>
                                            
                                            <button class="btn btn-outline-secondary" onclick="healthCheck()">
                                                <i class="bi bi-heart-pulse"></i> Verificar Saúde do Sistema
                                            </button>
                                            
                                            <button class="btn btn-outline-warning" onclick="cleanLogs()">
                                                <i class="bi bi-trash"></i> Limpar Logs Antigos
                                            </button>
                                            
                                            <button class="btn btn-outline-success" onclick="backupDatabase()">
                                                <i class="bi bi-download"></i> Backup do Banco
                                            </button>
                                            
                                            <button class="btn btn-outline-info" onclick="showSystemInfo()">
                                                <i class="bi bi-info-circle"></i> Informações do Sistema
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h6><i class="bi bi-bar-chart-line"></i> Estatísticas Rápidas</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h6>Wallet IDs</h6>
                                                <h4><?php echo count($walletIds); ?></h4>
                                            </div>
                                            <div class="col-6">
                                                <h6>Contas Split</h6>
                                                <h4><?php echo count($splitAccounts); ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Webhook Configuration -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5><i class="bi bi-link"></i> Configuração de Webhook</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h6><i class="bi bi-info-circle"></i> URL do Webhook</h6>
                                    <p>Configure esta URL no painel do ASAAS:</p>
                                    <div class="input-group">
                                        <input type="text" class="form-control" readonly id="webhook-url" value="<?php 
                                            $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
                                            echo $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/webhook.php';
                                        ?>">
                                        <button class="btn btn-outline-secondary" onclick="copyWebhookUrl()">
                                            <i class="bi bi-clipboard"></i> Copiar
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Eventos Suportados:</h6>
                                        <ul>
                                            <li><strong>PAYMENT_RECEIVED</strong> - Pagamento confirmado</li>
                                            <li><strong>PAYMENT_OVERDUE</strong> - Pagamento vencido</li>
                                            <li><strong>PAYMENT_DELETED</strong> - Pagamento cancelado</li>
                                            <li><strong>PAYMENT_RESTORED</strong> - Pagamento restaurado</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Status do Webhook:</h6>
                                        <div id="webhook-status">
                                            <button class="btn btn-outline-primary btn-sm" onclick="testWebhook()">
                                                <i class="bi bi-activity"></i> Testar Webhook
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- parte 5 -->
                    </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let splitCounter = 1;
        
        // Navegação entre seções
        function showSection(section) {
            // Esconder todas as seções
            document.querySelectorAll('.section').forEach(el => el.style.display = 'none');
            
            // Mostrar seção selecionada
            document.getElementById(section + '-section').style.display = 'block';
            
            // Atualizar navegação
            document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
            document.querySelector(`[data-section="${section}"]`).classList.add('active');
            
            // Carregar dados específicos da seção
            if (section === 'payments') {
                loadPayments();
            }
        }
        
        // Event listeners para navegação
        document.querySelectorAll('[data-section]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = e.target.closest('[data-section]').dataset.section;
                showSection(section);
            });
        });
        
        // Função para copiar Wallet ID
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Wallet ID copiado para a área de transferência!', 'success');
            }).catch(() => {
                showToast('Erro ao copiar Wallet ID', 'error');
            });
        }
        
        // Função para copiar URL do webhook
        function copyWebhookUrl() {
            const input = document.getElementById('webhook-url');
            input.select();
            document.execCommand('copy');
            showToast('URL do webhook copiada!', 'success');
        }
        
        // Adicionar novo split
        function addSplit() {
            const container = document.getElementById('splits-container');
            const splitHtml = `
                <div class="split-item mb-3 border p-3 rounded">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label">Wallet ID</label>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSplit(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <select class="form-control mb-2" name="splits[${splitCounter}][walletId]">
                        <option value="">Selecione um Wallet ID</option>
                        <?php 
                        // Recriar lista de wallets para o JavaScript
                        foreach ($allWallets as $wallet): ?>
                        <option value="<?php echo $wallet['wallet_id']; ?>">
                            <?php echo htmlspecialchars($wallet['name']); ?> (<?php echo $wallet['type']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div class="row">
                        <div class="col-6">
                            <label class="form-label">Percentual (%)</label>
                            <input type="number" class="form-control" name="splits[${splitCounter}][percentualValue]" step="0.01" max="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valor Fixo (R$)</label>
                            <input type="number" class="form-control" name="splits[${splitCounter}][fixedValue]" step="0.01">
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', splitHtml);
            splitCounter++;
        }
        
        // Remover split
        function removeSplit(button) {
            button.closest('.split-item').remove();
        }
        
        // Testar conexão
        function testConnection() {
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Testando...';
            btn.disabled = true;
            
            fetch('api.php?action=test-api')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.error || 'Conexão estabelecida com sucesso!', 'success');
                    } else {
                        showToast('Erro na conexão: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showToast('Erro: ' + error.message, 'error');
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }
        
        // Verificar se email já existe
        function checkEmailExists(input) {
            const email = input.value.trim();
            const feedback = document.getElementById('email-feedback');
            
            if (!email) {
                feedback.innerHTML = '';
                input.classList.remove('border-warning', 'border-success', 'border-danger');
                return;
            }
            
            feedback.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split"></i> Verificando...</span>';
            
            // Simular verificação (você pode implementar uma chamada AJAX real aqui)
            setTimeout(() => {
                feedback.innerHTML = '<span class="text-success">✅ Email disponível!</span>';
                input.classList.remove('border-warning', 'border-danger');
                input.classList.add('border-success');
            }, 1000);
        }
        
        // Sincronizar contas do ASAAS
        function syncAccounts() {
            if (confirm('Deseja sincronizar as contas do painel ASAAS?')) {
                const btn = event.target.closest('button');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Sincronizando...';
                btn.disabled = true;
                
                fetch('api.php?action=sync-accounts')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.error || 'Contas sincronizadas com sucesso!', 'success');
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showToast('Erro na sincronização: ' + data.error, 'error');
                        }
                    })
                    .catch(error => {
                        showToast('Erro: ' + error.message, 'error');
                    })
                    .finally(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
            }
        }
        
        // Verificações do sistema
        function healthCheck() {
            fetch('api.php?action=health-check')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sistema funcionando corretamente!', 'success');
                    } else {
                        showToast('Problemas encontrados: ' + (Array.isArray(data.data) ? data.data.join(', ') : data.error), 'warning');
                    }
                })
                .catch(error => {
                    showToast('Erro na verificação: ' + error.message, 'error');
                });
        }
        
        function cleanLogs() {
            if (confirm('Deseja limpar os logs antigos?')) {
                fetch('api.php?action=clean-logs')
                    .then(response => response.json())
                    .then(data => {
                        showToast(data.error || 'Logs limpos com sucesso', 'success');
                    })
                    .catch(error => {
                        showToast('Erro: ' + error.message, 'error');
                    });
            }
        }
        
        function backupDatabase() {
            fetch('api.php?action=backup')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Backup criado: ' + data.data.filename, 'success');
                    } else {
                        showToast('Erro no backup: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showToast('Erro: ' + error.message, 'error');
                });
        }
        
        function showSystemInfo() {
            fetch('api.php?action=system-info')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSystemInfoModal(data.data);
                    } else {
                        showToast('Erro ao obter informações: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showToast('Erro: ' + error.message, 'error');
                });
        }
        
        function showSystemInfoModal(info) {
            const modalHtml = `
                <div class="modal fade" id="systemInfoModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-info-circle"></i> Informações do Sistema
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Sistema</h6>
                                        <p><strong>Versão:</strong> ${info.system_version || '2.0.0'}</p>
                                        <p><strong>PHP:</strong> ${info.php_version}</p>
                                        <p><strong>Ambiente:</strong> ${info.environment}</p>
                                        <p><strong>Banco:</strong> ${info.database}</p>
                                        <p><strong>Timezone:</strong> ${info.timezone}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Recursos</h6>
                                        <p><strong>Uso de Memória:</strong> ${info.memory_usage}</p>
                                        <p><strong>Limite de Memória:</strong> ${info.memory_limit}</p>
                                        <p><strong>Espaço Livre:</strong> ${info.disk_free}</p>
                                        <p><strong>Retenção de Logs:</strong> ${info.log_retention}</p>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Wallet IDs</h6>
                                        <p><strong>Total:</strong> ${info.wallet_ids_total || 0}</p>
                                        <p><strong>Ativos:</strong> ${info.wallet_ids_active || 0}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Horário do Servidor:</strong> ${info.server_time}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x"></i> Fechar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modal existente
            const existingModal = document.getElementById('systemInfoModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('systemInfoModal'));
            modal.show();
        }
        
        // Gerar relatório
        function generateReport() {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            if (!startDate || !endDate) {
                showToast('Selecione as datas para o relatório', 'warning');
                return;
            }
            
            fetch(`api.php?action=report&start=${startDate}&end=${endDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayReport(data.data.report);
                    } else {
                        showToast('Erro no relatório: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showToast('Erro: ' + error.message, 'error');
                });
        }
        
        function generateWalletReport() {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            if (!startDate || !endDate) {
                showToast('Selecione as datas para o relatório', 'warning');
                return;
            }
            
            fetch(`api.php?action=wallet-performance-report&start=${startDate}&end=${endDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayWalletReport(data.data);
                    } else {
                        showToast('Erro no relatório de wallets: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showToast('Erro: ' + error.message, 'error');
                });
        }
        
        function exportReport(format) {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            if (!startDate || !endDate) {
                showToast('Selecione as datas para exportar', 'warning');
                return;
            }
            
            const url = `api.php?action=export-report&format=${format}&start=${startDate}&end=${endDate}`;
            window.open(url, '_blank');
        }
        
        // Exibir relatório
        function displayReport(report) {
            const resultsDiv = document.getElementById('report-results');
            const topReceiversDiv = document.getElementById('top-receivers');
            
            // Resultados gerais
            resultsDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4>${report.total_payments}</h4>
                                <p>Pagamentos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4>R$ ${parseFloat(report.total_value || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h4>
                                <p>Total Recebido</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4>${report.splits ? Object.keys(report.splits).length : 0}</h4>
                                <p>Destinatários</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h4>R$ ${report.total_value && report.total_payments ? (report.total_value / report.total_payments).toFixed(2) : '0.00'}</h4>
                                <p>Ticket Médio</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Top recebedores
            if (report.splits && Object.keys(report.splits).length > 0) {
                let topHtml = '<div class="list-group">';
                Object.values(report.splits).forEach((split, index) => {
                    topHtml += `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">#${index + 1} - ${split.account_name || split.wallet_id}</h6>
                                <small>${split.payment_count} pagamentos • ${split.source_type || 'Desconhecido'}</small>
                            </div>
                            <span class="badge bg-primary rounded-pill">
                                R$ ${parseFloat(split.total_received || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                            </span>
                        </div>
                    `;
                });
                topHtml += '</div>';
                topReceiversDiv.innerHTML = topHtml;
            } else {
                topReceiversDiv.innerHTML = '<p class="text-muted">Nenhum split encontrado no período</p>';
            }
        }
        
        function displayWalletReport(wallets) {
            const resultsDiv = document.getElementById('report-results');
            
            let html = `
                <h6><i class="bi bi-wallet2"></i> Relatório de Performance dos Wallet IDs</h6>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Wallet ID</th>
                                <th>Nome</th>
                                <th>Status</th>
                                <th>Splits</th>
                                <th>Total Ganho</th>
                                <th>Média por Split</th>
                                <th>Período Ativo</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            wallets.forEach(wallet => {
                const totalEarned = parseFloat(wallet.total_earned || 0);
                const avgSplit = parseFloat(wallet.avg_split_value || 0);
                const statusBadge = wallet.is_active ? 'success' : 'secondary';
                const statusText = wallet.is_active ? 'Ativo' : 'Inativo';
                
                html += `
                    <tr>
                        <td><code>${wallet.wallet_id}</code></td>
                        <td>
                            <strong>${wallet.name}</strong>
                            ${wallet.description ? `<br><small class="text-muted">${wallet.description}</small>` : ''}
                        </td>
                        <td><span class="badge bg-${statusBadge}">${statusText}</span></td>
                        <td>${wallet.split_count || 0}</td>
                        <td><strong>R$ ${totalEarned.toFixed(2)}</strong></td>
                        <td>R$ ${avgSplit.toFixed(2)}</td>
                        <td>
                            ${wallet.first_split ? formatDate(wallet.first_split) : 'N/A'} - 
                            ${wallet.last_split ? formatDate(wallet.last_split) : 'N/A'}
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            resultsDiv.innerHTML = html;
        }
        
        // Ver detalhes do pagamento - VERSÃO MELHORADA
        function viewPayment(paymentId) {
            // Mostrar loading
            const loadingModal = createLoadingModal();
            document.body.appendChild(loadingModal);
            const modal = new bootstrap.Modal(loadingModal);
            modal.show();
            
            // Buscar detalhes do pagamento
            fetch(`api.php?action=payment-details&id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    modal.hide();
                    loadingModal.remove();
                    
                    if (data.success) {
                        showPaymentDetailsModal(data.data);
                    } else {
                        showErrorModal('Erro ao Carregar Pagamento', data.error || 'Erro desconhecido');
                    }
                })
                .catch(error => {
                    modal.hide();
                    loadingModal.remove();
                    showErrorModal('Erro de Conexão', 'Não foi possível carregar os detalhes do pagamento: ' + error.message);
                });
        }
        
        function createLoadingModal() {
            const modalHtml = `
                <div class="modal fade" id="loadingModal" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-body text-center p-4">
                                <div class="spinner-border text-primary mb-3" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                                <p class="mb-0">Carregando detalhes...</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const div = document.createElement('div');
            div.innerHTML = modalHtml;
            return div.firstElementChild;
        }
        
        function showErrorModal(title, message) {
            const errorModalHtml = `
                <div class="modal fade" id="errorModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-exclamation-triangle"></i> ${title}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-danger">
                                    <strong>Detalhes do erro:</strong><br>
                                    ${message}
                                </div>
                                
                                <div class="mt-3">
                                    <h6>Possíveis soluções:</h6>
                                    <ul>
                                        <li>Verifique sua conexão com a internet</li>
                                        <li>Tente atualizar a página</li>
                                        <li>Verifique se a API Key está configurada corretamente</li>
                                        <li>Consulte os logs do sistema para mais detalhes</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x"></i> Fechar
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
                                    <i class="bi bi-arrow-clockwise"></i> Recarregar Página
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modal anterior se existir
            const existingModal = document.getElementById('errorModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            document.body.insertAdjacentHTML('beforeend', errorModalHtml);
            const modal = new bootstrap.Modal(document.getElementById('errorModal'));
            modal.show();
        }
        
        function showPaymentDetailsModal(payment) {
            const statusClass = getStatusClass(payment.status);
            const statusIcon = getStatusIcon(payment.status);
            
            let splitsHtml = '';
            if (payment.splits && payment.splits.length > 0) {
                splitsHtml = `
                    <div class="mt-4">
                        <h6><i class="bi bi-share"></i> Splits do Pagamento (${payment.splits_count})</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Destinatário</th>
                                        <th>Tipo</th>
                                        <th>Configuração</th>
                                        <th class="text-end">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                payment.splits.forEach(split => {
                    const splitConfig = split.split_type === 'FIXED' 
                        ? `R$ ${parseFloat(split.fixed_value).toFixed(2)}`
                        : `${split.percentage_value}%`;
                        
                    const splitValue = parseFloat(split.calculated_split_value).toFixed(2);
                    
                    splitsHtml += `
                        <tr>
                            <td>
                                <strong>${split.recipient_name}</strong>
                                <br><small class="text-muted">${split.wallet_id}</small>
                            </td>
                            <td>
                                <span class="badge bg-${split.recipient_type === 'Wallet ID' ? 'primary' : 'info'}">
                                    ${split.recipient_type}
                                </span>
                            </td>
                            <td>${splitConfig}</td>
                            <td class="text-end"><strong>R$ ${splitValue}</strong></td>
                        </tr>
                    `;
                });
                
                splitsHtml += `
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <th colspan="3" class="text-end">Total dos Splits:</th>
                                        <th class="text-end">R$ ${parseFloat(payment.total_split_value).toFixed(2)}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                `;
            } else {
                splitsHtml = `
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Este pagamento não possui splits configurados.
                        </div>
                    </div>
                `;
            }
            
            // Links adicionais se disponíveis
            let linksHtml = '';
            if (payment.asaas_data) {
                const asaas = payment.asaas_data;
                linksHtml = `
                    <div class="mt-4">
                        <h6><i class="bi bi-link-45deg"></i> Links e Documentos</h6>
                        <div class="btn-group-vertical w-100">
                `;
                
                if (asaas.invoice_url) {
                    linksHtml += `
                        <a href="${asaas.invoice_url}" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-receipt"></i> Ver Fatura (#${asaas.invoice_number || 'N/A'})
                        </a>
                    `;
                }
                
                if (asaas.transaction_receipt_url) {
                    linksHtml += `
                        <a href="${asaas.transaction_receipt_url}" target="_blank" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-file-earmark-check"></i> Comprovante de Transação
                        </a>
                    `;
                }
                
                if (asaas.bank_slip_url) {
                    linksHtml += `
                        <a href="${asaas.bank_slip_url}" target="_blank" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-file-earmark-pdf"></i> Boleto Bancário
                        </a>
                    `;
                }
                
                linksHtml += '</div></div>';
            }
            
            const modalHtml = `
                <div class="modal fade" id="paymentModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-credit-card"></i> Detalhes do Pagamento
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <!-- Informações Básicas -->
                                        <div class="card mb-3">
                                            <div class="card-header">
                                                <h6><i class="bi bi-info-circle"></i> Informações do Pagamento</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>ID:</strong> <code>${payment.id}</code></p>
                                                        <p><strong>Valor:</strong> <span class="h5 text-success">R$ ${parseFloat(payment.value).toFixed(2)}</span></p>
                                                        <p><strong>Tipo:</strong> <span class="badge bg-secondary">${payment.billing_type}</span></p>
                                                        <p><strong>Status:</strong> <span class="badge bg-${statusClass}">${statusIcon} ${payment.status}</span></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Descrição:</strong> ${payment.description}</p>
                                                        <p><strong>Vencimento:</strong> ${formatDate(payment.due_date)}</p>
                                                        <p><strong>Criado em:</strong> ${formatDateTime(payment.created_at)}</p>
                                                        ${payment.received_date ? `<p><strong>Recebido em:</strong> ${formatDateTime(payment.received_date)}</p>` : ''}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Informações do Cliente -->
                                        <div class="card mb-3">
                                            <div class="card-header">
                                                <h6><i class="bi bi-person"></i> Cliente</h6>
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Nome:</strong> ${payment.customer_name || 'N/A'}</p>
                                                <p><strong>Email:</strong> ${payment.customer_email || 'N/A'}</p>
                                                <p><strong>Documento:</strong> ${payment.customer_document || 'N/A'}</p>
                                            </div>
                                        </div>
                                        
                                        ${splitsHtml}
                                        ${linksHtml}
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <!-- Ações Rápidas -->
                                        <div class="card mb-3">
                                            <div class="card-header">
                                                <h6><i class="bi bi-tools"></i> Ações</h6>
                                            </div>
                                            <div class="card-body d-grid gap-2">
                                                <button class="btn btn-outline-primary" onclick="refreshPaymentStatus('${payment.id}')">
                                                    <i class="bi bi-arrow-clockwise"></i> Atualizar Status
                                                </button>
                                                
                                                <button class="btn btn-outline-info" onclick="showPaymentHistory('${payment.id}')">
                                                    <i class="bi bi-clock-history"></i> Ver Histórico
                                                </button>
                                                
                                                <button class="btn btn-outline-secondary" onclick="copyPaymentInfo('${payment.id}')">
                                                    <i class="bi bi-clipboard"></i> Copiar Informações
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Resumo Financeiro -->
                                        <div class="card">
                                            <div class="card-header">
                                                <h6><i class="bi bi-calculator"></i> Resumo Financeiro</h6>
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Valor Total:</strong> R$ ${parseFloat(payment.value).toFixed(2)}</p>
                                                <p><strong>Splits:</strong> R$ ${parseFloat(payment.total_split_value || 0).toFixed(2)}</p>
                                                <p><strong>Restante:</strong> R$ ${(parseFloat(payment.value) - parseFloat(payment.total_split_value || 0)).toFixed(2)}</p>
                                                
                                                ${payment.asaas_data && payment.asaas_data.net_value ? 
                                                    `<hr><p><strong>Valor Líquido (ASAAS):</strong> R$ ${parseFloat(payment.asaas_data.net_value).toFixed(2)}</p>` : 
                                                    ''
                                                }
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x"></i> Fechar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modal existente
            const existingModal = document.getElementById('paymentModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        }
        
        // Funções auxiliares para modal de pagamento
        function getStatusClass(status) {
            const statusMap = {
                'RECEIVED': 'success',
                'PENDING': 'warning',
                'OVERDUE': 'danger',
                'CONFIRMED': 'info',
                'DELETED': 'dark'
            };
            return statusMap[status] || 'secondary';
        }
        
        function getStatusIcon(status) {
            const iconMap = {
                'RECEIVED': '✅',
                'PENDING': '⏳',
                'OVERDUE': '⚠️',
                'CONFIRMED': 'ℹ️',
                'DELETED': '❌'
            };
            return iconMap[status] || '❓';
        }
        
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR');
        }
        
        function formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
        }
        
        function refreshPaymentStatus(paymentId) {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Atualizando...';
            btn.disabled = true;
            
            fetch(`api.php?action=refresh-payment&id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Status atualizado com sucesso!', 'success');
                        // Recarregar os detalhes
                        setTimeout(() => viewPayment(paymentId), 1000);
                    } else {
                        showToast('Erro ao atualizar: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showToast('Erro de conexão: ' + error.message, 'error');
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }
        
        function showPaymentHistory(paymentId) {
            fetch(`api.php?action=payment-history&id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showHistoryModal(paymentId, data.data);
                    } else {
                        showToast('Erro ao carregar histórico: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showToast('Erro de conexão: ' + error.message, 'error');
                });
        }
        
        function showHistoryModal(paymentId, history) {
            let historyHtml = '';
            
            if (history.length > 0) {
                historyHtml = `
                    <div class="timeline">
                        ${history.map(item => `
                            <div class="timeline-item">
                                <div class="timeline-marker bg-${item.status === 'SUCCESS' ? 'success' : 'danger'}"></div>
                                <div class="timeline-content">
                                    <h6>${item.event}</h6>
                                    <p><strong>Status:</strong> ${item.status}</p>
                                    <p><strong>Status do Pagamento:</strong> ${item.payment_status}</p>
                                    ${item.error ? `<p><strong>Erro:</strong> <span class="text-danger">${item.error}</span></p>` : ''}
                                    <small class="text-muted">${formatDateTime(item.date)}</small>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                historyHtml = '<div class="alert alert-info">Nenhum histórico de webhook encontrado para este pagamento.</div>';
            }
            
            const modalHtml = `
                <div class="modal fade" id="historyModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-clock-history"></i> Histórico do Pagamento
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p><strong>Pagamento ID:</strong> <code>${paymentId}</code></p>
                                <hr>
                                ${historyHtml}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x"></i> Fechar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modal existente
            const existingModal = document.getElementById('historyModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('historyModal'));
            modal.show();
        }
        
        function copyPaymentInfo(paymentId) {
            const paymentInfo = `Pagamento ID: ${paymentId}\nLink: ${window.location.origin}${window.location.pathname}?payment=${paymentId}`;
            
            navigator.clipboard.writeText(paymentInfo).then(() => {
                showToast('Informações copiadas para a área de transferência!', 'success');
            }).catch(() => {
                showToast('Erro ao copiar informações', 'error');
            });
        }
        
        // Função para carregar pagamentos com melhor tratamento de erro
        function loadPayments(page = 1, limit = 10, status = '', billingType = '') {
            const container = document.getElementById('payments-container');
            
            if (!container) {
                console.warn('Container de pagamentos não encontrado');
                return;
            }
            
            // Mostrar loading
            container.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="text-muted">Carregando pagamentos...</p>
                </div>
            `;
            
            const params = new URLSearchParams({
                action: 'payments-summary',
                limit: limit,
                offset: (page - 1) * limit
            });
            
            if (status) params.set('status', status);
            if (billingType) params.set('billing_type', billingType);
            
            fetch(`api.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPayments(data.data.payments, data.data.totals, data.data.pagination);
                    } else {
                        container.innerHTML = `
                            <div class="alert alert-danger">
                                <h6><i class="bi bi-exclamation-triangle"></i> Erro ao Carregar Pagamentos</h6>
                                <p>${data.error}</p>
                                <button class="btn btn-outline-danger btn-sm" onclick="loadPayments()">
                                    <i class="bi bi-arrow-clockwise"></i> Tentar Novamente
                                </button>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    container.innerHTML = `
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-wifi-off"></i> Erro de Conexão</h6>
                            <p>Não foi possível carregar os pagamentos: ${error.message}</p>
                            <button class="btn btn-outline-warning btn-sm" onclick="loadPayments()">
                                <i class="bi bi-arrow-clockwise"></i> Tentar Novamente
                            </button>
                        </div>
                    `;
                });
        }
        
        function displayPayments(payments, totals, pagination) {
            const container = document.getElementById('payments-container');
            
            if (!payments || payments.length === 0) {
                container.innerHTML = `
                    <div class="text-center p-4">
                        <i class="bi bi-credit-card display-1 text-muted"></i>
                        <h5 class="mt-3 text-muted">Nenhum pagamento encontrado</h5>
                        <p class="text-muted">Crie seu primeiro pagamento para vê-lo aqui</p>
                        <button class="btn btn-primary" onclick="showSection('payments')">
                            <i class="bi bi-plus"></i> Criar Pagamento
                        </button>
                    </div>
                `;
                return;
            }
            
            let html = `
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h6>Total de Pagamentos</h6>
                                        <h4>${totals.total_count}</h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6>Valor Total</h6>
                                        <h4>R$ ${parseFloat(totals.total_value || 0).toFixed(2)}</h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6>Valor Recebido</h6>
                                        <h4 class="text-success">R$ ${parseFloat(totals.received_value || 0).toFixed(2)}</h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6>Taxa de Conversão</h6>
                                        <h4>${totals.total_value > 0 ? ((totals.received_value / totals.total_value) * 100).toFixed(1) : 0}%</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Splits</th>
                                <th>Vencimento</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            payments.forEach(payment => {
                const statusClass = getStatusClass(payment.status);
                const statusIcon = getStatusIcon(payment.status);
                
                html += `
                    <tr>
                        <td><code>${payment.id.substring(0, 8)}...</code></td>
                        <td>
                            <strong>${payment.customer_name || 'N/A'}</strong><br>
                            <small class="text-muted">${payment.description}</small>
                        </td>
                        <td><strong>R$ ${parseFloat(payment.value).toFixed(2)}</strong></td>
                        <td><span class="badge bg-secondary">${payment.billing_type}</span></td>
                        <td><span class="badge bg-${statusClass}">${statusIcon} ${payment.status}</span></td>
                        <td>
                            ${payment.splits_count > 0 ? 
                                `<span class="badge bg-info">${payment.splits_count} splits</span><br>
                                 <small class="text-muted">R$ ${parseFloat(payment.total_split_value || 0).toFixed(2)}</small>` :
                                '<span class="text-muted">Sem splits</span>'
                            }
                        </td>
                        <td>
                            ${formatDate(payment.due_date)}<br>
                            ${payment.received_date ? `<small class="text-success">Recebido: ${formatDate(payment.received_date)}</small>` : ''}
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewPayment('${payment.id}')" title="Ver detalhes">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="refreshPaymentStatus('${payment.id}')" title="Atualizar status">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-info" onclick="copyPaymentInfo('${payment.id}')" title="Copiar info">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            // Adicionar paginação se necessário
            if (pagination.total_count > pagination.limit) {
                const totalPages = Math.ceil(pagination.total_count / pagination.limit);
                const currentPage = Math.floor(pagination.offset / pagination.limit) + 1;
                
                html += `
                    <nav aria-label="Paginação de pagamentos">
                        <ul class="pagination justify-content-center">
                `;
                
                // Página anterior
                if (currentPage > 1) {
                    html += `
                        <li class="page-item">
                            <button class="page-link" onclick="loadPayments(${currentPage - 1})">
                                <i class="bi bi-chevron-left"></i> Anterior
                            </button>
                        </li>
                    `;
                }
                
                // Páginas
                for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                    html += `
                        <li class="page-item ${i === currentPage ? 'active' : ''}">
                            <button class="page-link" onclick="loadPayments(${i})">${i}</button>
                        </li>
                    `;
                }
                
                // Próxima página
                if (currentPage < totalPages) {
                    html += `
                        <li class="page-item">
                            <button class="page-link" onclick="loadPayments(${currentPage + 1})">
                                Próximo <i class="bi bi-chevron-right"></i>
                            </button>
                        </li>
                    `;
                }
                
                html += `
                        </ul>
                    </nav>
                `;
            }
            
            container.innerHTML = html;
        }
        
        // Função de toast para notificações
        function showToast(message, type = 'info') {
            const toastHtml = `
                <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
                    <div class="toast show" role="alert">
                        <div class="toast-header">
                            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} text-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} me-2"></i>
                            <strong class="me-auto">${type === 'success' ? 'Sucesso' : type === 'error' ? 'Erro' : 'Informação'}</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            ${message}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', toastHtml);
            
            // Remover após 5 segundos
            setTimeout(() => {
                const toasts = document.querySelectorAll('.toast');
                if (toasts.length > 0) {
                    toasts[toasts.length - 1].closest('div').remove();
                }
            }, 5000);
        }
        
        // Testar webhook
        function testWebhook() {
            fetch('api.php?action=test-webhook', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    event: 'PAYMENT_RECEIVED',
                    payment: {
                        id: 'test_webhook_' + Date.now(),
                        status: 'RECEIVED',
                        value: 10.00
                    }
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Webhook testado com sucesso!', 'success');
                } else {
                    showToast('Erro no teste do webhook: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showToast('Erro ao testar webhook: ' + error.message, 'error');
            });
        }
        
        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar dashboard por padrão
            showSection('dashboard');
            
            // Adicionar máscaras para CPF/CNPJ e CEP
            addInputMasks();
        });
        
        // Adicionar máscaras de input
        function addInputMasks() {
            // Máscara para CPF/CNPJ
            document.querySelectorAll('input[name*="cpfCnpj"]').forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    if (value.length <= 11) {
                        // CPF: 000.000.000-00
                        value = value.replace(/(\d{3})(\d)/, '$1.$2');
                        value = value.replace(/(\d{3})(\d)/, '$1.$2');
                        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                    } else {
                        // CNPJ: 00.000.000/0000-00
                        value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                        value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                        value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                        value = value.replace(/(\d{4})(\d)/, '$1-$2');
                    }
                    
                    e.target.value = value;
                });
            });
            
            // Máscara para CEP
            document.querySelectorAll('input[name*="postalCode"]').forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    value = value.replace(/^(\d{5})(\d)/, '$1-$2');
                    e.target.value = value;
                });
            });
            
            // Máscara para telefone
            document.querySelectorAll('input[name*="Phone"]').forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    // Limitar a 11 dígitos (celular com DDD)
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                    
                    if (value.length <= 10) {
                        // Telefone fixo: (00) 0000-0000
                        value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                        value = value.replace(/(\d{4})(\d)/, '$1-$2');
                    } else {
                        // Celular: (00) 00000-0000
                        value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                        value = value.replace(/(\d{5})(\d)/, '$1-$2');
                    }
                    
                    e.target.value = value;
                });
                
                // Ao sair do campo, garantir que está no formato correto para a API
                input.addEventListener('blur', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    // A API espera apenas números: 11987654321
                    e.target.dataset.apiValue = value;
                });
            });
        }
    </script>
</body>
</html>

