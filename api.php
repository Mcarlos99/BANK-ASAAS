<?php
/**
 * API Endpoint para funcionalidades AJAX
 * Arquivo: api.php
 * Versão com Wallet IDs
 */

require_once 'config.php';
require_once 'asaas_split_system.php';

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

// Função para resposta JSON
function jsonResponse($success, $data = null, $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error,
        'timestamp' => time()
    ]);
    exit();
}

// Obter ação da requisição
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // ===== WALLET IDS =====
        
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
            
        case 'update-wallet-status':
            try {
                $walletId = $_POST['wallet_id'] ?? '';
                
                if (empty($walletId)) {
                    jsonResponse(false, null, "Wallet ID é obrigatório");
                }
                
                $walletManager = new WalletManager();
                $success = $walletManager->toggleStatus($walletId);
                
                if ($success) {
                    jsonResponse(true, null, "Status do Wallet ID atualizado");
                } else {
                    jsonResponse(false, null, "Erro ao atualizar status");
                }
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'delete-wallet':
            try {
                $walletId = $_POST['wallet_id'] ?? '';
                
                if (empty($walletId)) {
                    jsonResponse(false, null, "Wallet ID é obrigatório");
                }
                
                $walletManager = new WalletManager();
                $success = $walletManager->deleteWallet($walletId);
                
                if ($success) {
                    jsonResponse(true, null, "Wallet ID removido com sucesso");
                } else {
                    jsonResponse(false, null, "Erro ao remover Wallet ID");
                }
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'validate-wallet-id':
            try {
                $walletId = $_GET['wallet_id'] ?? '';
                
                if (empty($walletId)) {
                    jsonResponse(false, null, "Wallet ID é obrigatório");
                }
                
                // Validar formato
                if (!ValidationHelper::isValidWalletId($walletId)) {
                    jsonResponse(false, null, "Formato inválido. Use formato UUID (ex: 22e49670-27e4-4579-a4c1-205c8a40497c)");
                }
                
                // Verificar se já existe
                $db = DatabaseManager::getInstance();
                if ($db->walletIdExists($walletId)) {
                    jsonResponse(false, null, "Este Wallet ID já está cadastrado");
                }
                
                jsonResponse(true, ['wallet_id' => $walletId], "Wallet ID válido e disponível");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na validação: " . $e->getMessage());
            }
            break;
            
        case 'wallet-splits':
            try {
                $walletId = $_GET['wallet_id'] ?? '';
                $limit = (int)($_GET['limit'] ?? 50);
                
                if (empty($walletId)) {
                    jsonResponse(false, null, "Wallet ID é obrigatório");
                }
                
                $db = DatabaseManager::getInstance();
                $splits = $db->getSplitsByWalletId($walletId, $limit);
                
                jsonResponse(true, $splits, "Splits do Wallet ID carregados");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao carregar splits: " . $e->getMessage());
            }
            break;
            
        // ===== FUNCIONALIDADES EXISTENTES =====
        
        case 'health-check':
            $issues = [];
            
            // Verificar banco de dados
            try {
                $db = DatabaseManager::getInstance();
                $db->getConnection()->query("SELECT 1");
                
                // Verificar tabela wallet_ids
                $result = $db->getConnection()->query("SHOW TABLES LIKE 'wallet_ids'");
                if ($result->rowCount() == 0) {
                    $issues[] = "Tabela wallet_ids não encontrada. Execute as migrações.";
                }
                
            } catch (Exception $e) {
                $issues[] = "Banco de dados: " . $e->getMessage();
            }
            
            // Verificar API ASAAS
            try {
                if (ASAAS_SANDBOX_API_KEY !== 'SUA_API_KEY_SANDBOX_AQUI') {
                    $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                    $asaas->listAccounts(1, 0);
                } else {
                    $issues[] = "API ASAAS: Credenciais não configuradas";
                }
            } catch (Exception $e) {
                $issues[] = "API ASAAS: " . $e->getMessage();
            }
            
            // Verificar espaço em disco
            $freeBytes = disk_free_space(__DIR__);
            $freeMB = round($freeBytes / 1024 / 1024);
            if ($freeMB < 100) {
                $issues[] = "Espaço em disco baixo: {$freeMB}MB";
            }
            
            if (empty($issues)) {
                jsonResponse(true, null, "Sistema funcionando corretamente!");
            } else {
                jsonResponse(false, $issues, "Problemas encontrados no sistema");
            }
            break;
            
        case 'clean-logs':
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
                
                jsonResponse(true, [
                    'database_rows' => $deletedRows,
                    'log_files' => $deletedFiles
                ], "Limpeza concluída: {$deletedRows} registros e {$deletedFiles} arquivos removidos");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na limpeza: " . $e->getMessage());
            }
            break;
            
        case 'backup':
            try {
                // Criar diretório de backup se não existir
                $backupDir = __DIR__ . '/backups';
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                
                $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $backupPath = $backupDir . '/' . $filename;
                
                // Executar mysqldump
                $command = sprintf(
                    'mysqldump -h%s -u%s %s %s > %s 2>&1',
                    escapeshellarg(DB_HOST),
                    escapeshellarg(DB_USER),
                    !empty(DB_PASS) ? '-p' . escapeshellarg(DB_PASS) : '',
                    escapeshellarg(DB_NAME),
                    escapeshellarg($backupPath)
                );
                
                $output = [];
                $returnVar = 0;
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0 && file_exists($backupPath) && filesize($backupPath) > 0) {
                    jsonResponse(true, [
                        'filename' => $filename,
                        'size' => filesize($backupPath),
                        'path' => $backupPath
                    ], "Backup criado com sucesso");
                } else {
                    jsonResponse(false, null, "Falha ao criar backup. Verifique as credenciais do MySQL.");
                }
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro no backup: " . $e->getMessage());
            }
            break;
            
        case 'report':
            try {
                $startDate = $_GET['start'] ?? date('Y-m-01');
                $endDate = $_GET['end'] ?? date('Y-m-d');
                
                $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                $report = $asaas->getSplitReport($startDate, $endDate);
                
                // Obter estatísticas adicionais do banco
                $db = DatabaseManager::getInstance();
                
                // Total de pagamentos no período
                $stmt = $db->getConnection()->prepare("
                    SELECT COUNT(*) as total_payments, SUM(value) as total_value
                    FROM payments 
                    WHERE status = 'RECEIVED' 
                    AND received_date BETWEEN ? AND ?
                ");
                $stmt->execute([$startDate, $endDate]);
                $stats = $stmt->fetch();
                
                // Relatório de splits do banco (incluindo Wallet IDs)
                $splitReport = $db->getSplitReport($startDate, $endDate);
                
                $reportData = [
                    'period' => ['start' => $startDate, 'end' => $endDate],
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
            
        case 'wallet-performance-report':
            try {
                $startDate = $_GET['start'] ?? date('Y-m-01');
                $endDate = $_GET['end'] ?? date('Y-m-d');
                
                $reports = new ReportsManager();
                $walletReport = $reports->getWalletPerformanceReport($startDate, $endDate);
                
                jsonResponse(true, $walletReport, "Relatório de performance dos Wallet IDs gerado");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro no relatório de performance: " . $e->getMessage());
            }
            break;
            
        case 'payment':
            try {
                $paymentId = $_GET['id'] ?? '';
                
                if (empty($paymentId)) {
                    jsonResponse(false, null, "ID do pagamento é obrigatório");
                }
                
                $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                $payment = $asaas->getPayment($paymentId);
                
                jsonResponse(true, ['payment' => $payment], "Pagamento encontrado");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao buscar pagamento: " . $e->getMessage());
            }
            break;
            
        case 'stats':
            try {
                $stats = SystemStats::getGeneralStats();
                
                if ($stats) {
                    jsonResponse(true, $stats, "Estatísticas obtidas com sucesso");
                } else {
                    jsonResponse(false, null, "Erro ao obter estatísticas");
                }
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro: " . $e->getMessage());
            }
            break;
            
        case 'customers':
            try {
                $db = DatabaseManager::getInstance();
                $stmt = $db->getConnection()->query("
                    SELECT id, name, email, cpf_cnpj, created_at 
                    FROM customers 
                    ORDER BY created_at DESC 
                    LIMIT 50
                ");
                $customers = $stmt->fetchAll();
                
                jsonResponse(true, $customers, "Clientes carregados");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao carregar clientes: " . $e->getMessage());
            }
            break;
            
        case 'split-accounts':
            try {
                $limit = (int)($_GET['limit'] ?? 20);
                $offset = (int)($_GET['offset'] ?? 0);
                
                $db = DatabaseManager::getInstance();
                $stmt = $db->getConnection()->prepare("
                    SELECT id, wallet_id, name, email, cpf_cnpj, status, created_at 
                    FROM split_accounts 
                    ORDER BY created_at DESC 
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$limit, $offset]);
                $accounts = $stmt->fetchAll();
                
                jsonResponse(true, $accounts, "Contas de split carregadas");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao carregar contas: " . $e->getMessage());
            }
            break;
            
        case 'sync-accounts':
            try {
                $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                $result = $asaas->syncAccountsFromAsaas();
                
                jsonResponse(true, $result, $result['message']);
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na sincronização: " . $e->getMessage());
            }
            break;
            
        case 'payments':
            try {
                $limit = (int)($_GET['limit'] ?? 20);
                $offset = (int)($_GET['offset'] ?? 0);
                $status = $_GET['status'] ?? '';
                
                $db = DatabaseManager::getInstance();
                $whereClause = '';
                $params = [];
                
                if (!empty($status)) {
                    $whereClause = "WHERE status = ?";
                    $params[] = $status;
                }
                
                $stmt = $db->getConnection()->prepare("
                    SELECT p.*, c.name as customer_name
                    FROM payments p
                    LEFT JOIN customers c ON p.customer_id = c.id
                    {$whereClause}
                    ORDER BY p.created_at DESC 
                    LIMIT ? OFFSET ?
                ");
                
                $params[] = $limit;
                $params[] = $offset;
                $stmt->execute($params);
                $payments = $stmt->fetchAll();
                
                jsonResponse(true, $payments, "Pagamentos carregados");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao carregar pagamentos: " . $e->getMessage());
            }
            break;
            
        case 'webhook-logs':
            try {
                $limit = (int)($_GET['limit'] ?? 20);
                
                $db = DatabaseManager::getInstance();
                $stmt = $db->getConnection()->prepare("
                    SELECT * FROM webhook_logs 
                    ORDER BY processed_at DESC 
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                $logs = $stmt->fetchAll();
                
                jsonResponse(true, $logs, "Logs de webhook carregados");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao carregar logs: " . $e->getMessage());
            }
            break;
            
        case 'test-api':
            try {
                if (ASAAS_SANDBOX_API_KEY === 'SUA_API_KEY_SANDBOX_AQUI') {
                    jsonResponse(false, null, "Configure a API Key antes de testar");
                }
                
                $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                $response = $asaas->listAccounts(1, 0);
                
                jsonResponse(true, [
                    'environment' => ASAAS_ENVIRONMENT,
                    'total_accounts' => $response['totalCount'],
                    'response_time' => 'OK'
                ], "Conexão com API ASAAS estabelecida com sucesso");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na API ASAAS: " . $e->getMessage());
            }
            break;
            
        case 'conversion-report':
            try {
                $startDate = $_GET['start'] ?? date('Y-m-01');
                $endDate = $_GET['end'] ?? date('Y-m-d');
                
                $reports = new ReportsManager();
                $conversionData = $reports->getConversionReport($startDate, $endDate);
                
                jsonResponse(true, $conversionData, "Relatório de conversão gerado");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro no relatório de conversão: " . $e->getMessage());
            }
            break;
            
        case 'top-receivers':
            try {
                $limit = (int)($_GET['limit'] ?? 10);
                $startDate = $_GET['start'] ?? date('Y-m-01');
                $endDate = $_GET['end'] ?? date('Y-m-d');
                
                $reports = new ReportsManager();
                $topReceivers = $reports->getTopSplitReceivers($limit, $startDate, $endDate);
                
                jsonResponse(true, $topReceivers, "Top recebedores carregados");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao carregar top recebedores: " . $e->getMessage());
            }
            break;
            
        case 'monthly-data':
            try {
                $year = (int)($_GET['year'] ?? date('Y'));
                $month = (int)($_GET['month'] ?? date('n'));
                
                $reports = new ReportsManager();
                $monthlyData = $reports->getMonthlyReport($year, $month);
                
                jsonResponse(true, $monthlyData, "Dados mensais carregados");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro nos dados mensais: " . $e->getMessage());
            }
            break;
            
        case 'system-info':
            try {
                $db = DatabaseManager::getInstance();
                $walletStats = $db->getWalletStats();
                
                $info = [
                    'php_version' => PHP_VERSION,
                    'environment' => ASAAS_ENVIRONMENT,
                    'log_retention' => LOG_RETENTION_DAYS . ' dias',
                    'database' => DB_NAME,
                    'timezone' => date_default_timezone_get(),
                    'server_time' => date('Y-m-d H:i:s'),
                    'disk_free' => round(disk_free_space(__DIR__) / 1024 / 1024) . 'MB',
                    'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
                    'memory_limit' => ini_get('memory_limit'),
                    'wallet_ids_total' => $walletStats['total_wallets'],
                    'wallet_ids_active' => $walletStats['active_wallets'],
                    'system_version' => '2.0.0'
                ];
                
                jsonResponse(true, $info, "Informações do sistema obtidas");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao obter informações: " . $e->getMessage());
            }
            break;
            
        case 'validate-cpf':
            try {
                $cpf = preg_replace('/[^0-9]/', '', $_GET['cpf'] ?? '');
                
                if (strlen($cpf) !== 11) {
                    jsonResponse(false, null, "CPF deve ter 11 dígitos");
                }
                
                // Validação básica de CPF
                if (preg_match('/(\d)\1{10}/', $cpf)) {
                    jsonResponse(false, null, "CPF inválido");
                }
                
                // Cálculo dos dígitos verificadores
                for ($t = 9; $t < 11; $t++) {
                    for ($d = 0, $c = 0; $c < $t; $c++) {
                        $d += $cpf[$c] * (($t + 1) - $c);
                    }
                    $d = ((10 * $d) % 11) % 10;
                    if ($cpf[$c] != $d) {
                        jsonResponse(false, null, "CPF inválido");
                    }
                }
                
                jsonResponse(true, ['cpf' => $cpf], "CPF válido");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na validação: " . $e->getMessage());
            }
            break;
            
        case 'validate-cnpj':
            try {
                $cnpj = preg_replace('/[^0-9]/', '', $_GET['cnpj'] ?? '');
                
                if (strlen($cnpj) !== 14) {
                    jsonResponse(false, null, "CNPJ deve ter 14 dígitos");
                }
                
                // Validação básica de CNPJ
                if (preg_match('/(\d)\1{13}/', $cnpj)) {
                    jsonResponse(false, null, "CNPJ inválido");
                }
                
                // Cálculo dos dígitos verificadores
                $weights1 = [5,4,3,2,9,8,7,6,5,4,3,2];
                $weights2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
                
                $sum = 0;
                for ($i = 0; $i < 12; $i++) {
                    $sum += $cnpj[$i] * $weights1[$i];
                }
                $digit1 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);
                
                $sum = 0;
                for ($i = 0; $i < 13; $i++) {
                    $sum += $cnpj[$i] * $weights2[$i];
                }
                $digit2 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);
                
                if ($cnpj[12] != $digit1 || $cnpj[13] != $digit2) {
                    jsonResponse(false, null, "CNPJ inválido");
                }
                
                jsonResponse(true, ['cnpj' => $cnpj], "CNPJ válido");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na validação: " . $e->getMessage());
            }
            break;
            
        case 'search-customer':
            try {
                $term = $_GET['term'] ?? '';
                
                if (strlen($term) < 3) {
                    jsonResponse(false, null, "Digite pelo menos 3 caracteres");
                }
                
                $db = DatabaseManager::getInstance();
                $stmt = $db->getConnection()->prepare("
                    SELECT id, name, email, cpf_cnpj 
                    FROM customers 
                    WHERE name LIKE ? OR email LIKE ? OR cpf_cnpj LIKE ?
                    ORDER BY name 
                    LIMIT 20
                ");
                
                $searchTerm = "%{$term}%";
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
                $customers = $stmt->fetchAll();
                
                jsonResponse(true, $customers, count($customers) . " clientes encontrados");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na busca: " . $e->getMessage());
            }
            break;
            
        case 'dashboard-data':
            try {
                $stats = SystemStats::getGeneralStats();
                
                // Dados adicionais para gráficos
                $db = DatabaseManager::getInstance();
                
                // Pagamentos dos últimos 7 dias
                $stmt = $db->getConnection()->query("
                    SELECT DATE(created_at) as date, COUNT(*) as count, SUM(value) as total
                    FROM payments 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date
                ");
                $weeklyData = $stmt->fetchAll();
                
                // Distribuição por tipo de pagamento
                $stmt = $db->getConnection()->query("
                    SELECT billing_type, COUNT(*) as count
                    FROM payments 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY billing_type
                ");
                $billingTypeData = $stmt->fetchAll();
                
                // Estatísticas de Wallet IDs
                $stmt = $db->getConnection()->query("
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
                    WHERE wi.is_active = 1
                    GROUP BY wi.id
                    ORDER BY total_received DESC
                    LIMIT 5
                ");
                $topWallets = $stmt->fetchAll();
                
                jsonResponse(true, [
                    'stats' => $stats,
                    'weekly_data' => $weeklyData,
                    'billing_types' => $billingTypeData,
                    'top_wallets' => $topWallets
                ], "Dados do dashboard carregados");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao carregar dashboard: " . $e->getMessage());
            }
            break;
            
        case 'export-report':
            try {
                $format = $_GET['format'] ?? 'csv';
                $startDate = $_GET['start'] ?? date('Y-m-01');
                $endDate = $_GET['end'] ?? date('Y-m-d');
                
                $db = DatabaseManager::getInstance();
                $stmt = $db->getConnection()->prepare("
                    SELECT 
                        p.id,
                        p.value,
                        p.billing_type,
                        p.status,
                        p.created_at,
                        c.name as customer_name,
                        GROUP_CONCAT(
                            CONCAT(
                                COALESCE(sa.name, wi.name, ps.wallet_id), 
                                ':', 
                                CASE 
                                    WHEN ps.split_type = 'FIXED' THEN CONCAT('R
                , ps.fixed_value)
                                    ELSE CONCAT(ps.percentage_value, '%')
                                END
                            ) SEPARATOR '; '
                        ) as splits
                    FROM payments p
                    LEFT JOIN customers c ON p.customer_id = c.id
                    LEFT JOIN payment_splits ps ON p.id = ps.payment_id
                    LEFT JOIN split_accounts sa ON ps.wallet_id = sa.wallet_id
                    LEFT JOIN wallet_ids wi ON ps.wallet_id = wi.wallet_id
                    WHERE p.created_at BETWEEN ? AND ?
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
                
                $stmt->execute([$startDate, $endDate]);
                $data = $stmt->fetchAll();
                
                if ($format === 'csv') {
                    $filename = 'relatorio_splits_' . date('Y-m-d') . '.csv';
                    $csv = "ID,Valor,Tipo,Status,Data,Cliente,Splits\n";
                    
                    foreach ($data as $row) {
                        $csv .= sprintf(
                            "%s,%s,%s,%s,%s,%s,%s\n",
                            $row['id'],
                            $row['value'],
                            $row['billing_type'],
                            $row['status'],
                            $row['created_at'],
                            '"' . str_replace('"', '""', $row['customer_name']) . '"',
                            '"' . str_replace('"', '""', $row['splits'] ?? '') . '"'
                        );
                    }
                    
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Content-Length: ' . strlen($csv));
                    echo $csv;
                    exit();
                } else {
                    jsonResponse(true, $data, "Dados exportados");
                }
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na exportação: " . $e->getMessage());
            }
            break;
            
        case 'migrate-system':
            try {
                // Executar migrações necessárias para Wallet IDs
                $updater = new SystemUpdater();
                $result = $updater->runMigrations();
                
                if ($result) {
                    jsonResponse(true, null, "Sistema migrado com sucesso! Tabela wallet_ids criada.");
                } else {
                    jsonResponse(false, null, "Erro na migração do sistema");
                }
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na migração: " . $e->getMessage());
            }
            break;
            
        // ===== FUNCIONALIDADES ESPECIAIS PARA WALLET IDS =====
        
        case 'bulk-import-wallets':
            try {
                $wallets = $_POST['wallets'] ?? [];
                
                if (empty($wallets)) {
                    jsonResponse(false, null, "Nenhum Wallet ID fornecido para importação");
                }
                
                $walletManager = new WalletManager();
                $imported = 0;
                $errors = [];
                
                foreach ($wallets as $walletData) {
                    try {
                        $name = $walletData['name'] ?? '';
                        $walletId = $walletData['wallet_id'] ?? '';
                        $description = $walletData['description'] ?? null;
                        
                        if (empty($name) || empty($walletId)) {
                            $errors[] = "Nome e Wallet ID são obrigatórios para: " . json_encode($walletData);
                            continue;
                        }
                        
                        $walletManager->createWallet($name, $walletId, $description);
                        $imported++;
                        
                    } catch (Exception $e) {
                        $errors[] = "Erro ao importar {$walletId}: " . $e->getMessage();
                    }
                }
                
                $message = "{$imported} Wallet IDs importados com sucesso";
                if (!empty($errors)) {
                    $message .= ". Erros: " . implode('; ', $errors);
                }
                
                jsonResponse(true, [
                    'imported' => $imported,
                    'errors' => $errors
                ], $message);
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro na importação em lote: " . $e->getMessage());
            }
            break;
            
        case 'generate-wallet-template':
            try {
                // Gerar template CSV para importação de Wallet IDs
                $template = "nome,wallet_id,descricao\n";
                $template .= "EXEMPLO PARCEIRO 1,22e49670-27e4-4579-a4c1-205c8a40497c,Comissão de vendas\n";
                $template .= "EXEMPLO PARCEIRO 2,11f11111-1111-1111-1111-111111111111,Suporte técnico\n";
                
                $filename = 'template_wallet_ids.csv';
                
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($template));
                echo $template;
                exit();
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao gerar template: " . $e->getMessage());
            }
            break;

            case 'payment-details':
                try {
                    $paymentId = $_GET['id'] ?? '';
                    
                    if (empty($paymentId)) {
                        jsonResponse(false, null, "ID do pagamento é obrigatório");
                    }
                    
                    $db = DatabaseManager::getInstance();
                    
                    // Primeiro, buscar no banco local
                    $stmt = $db->getConnection()->prepare("
                        SELECT 
                            p.*,
                            c.name as customer_name,
                            c.email as customer_email,
                            c.cpf_cnpj as customer_document
                        FROM payments p
                        LEFT JOIN customers c ON p.customer_id = c.id
                        WHERE p.id = ?
                    ");
                    $stmt->execute([$paymentId]);
                    $localPayment = $stmt->fetch();
                    
                    if (!$localPayment) {
                        jsonResponse(false, null, "Pagamento não encontrado no banco local");
                    }
                    
                    // Buscar splits do pagamento
                    $stmt = $db->getConnection()->prepare("
                        SELECT 
                            ps.*,
                            COALESCE(sa.name, wi.name, 'Destinatário Desconhecido') as recipient_name,
                            CASE 
                                WHEN sa.id IS NOT NULL THEN 'Conta Split'
                                WHEN wi.id IS NOT NULL THEN 'Wallet ID'
                                ELSE 'Desconhecido'
                            END as recipient_type,
                            CASE 
                                WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                                ELSE (? * ps.percentage_value / 100) 
                            END as calculated_split_value
                        FROM payment_splits ps
                        LEFT JOIN split_accounts sa ON ps.wallet_id = sa.wallet_id
                        LEFT JOIN wallet_ids wi ON ps.wallet_id = wi.wallet_id
                        WHERE ps.payment_id = ?
                    ");
                    $stmt->execute([$localPayment['value'], $paymentId]);
                    $splits = $stmt->fetchAll();
                    
                    // Tentar buscar detalhes atualizados do ASAAS (opcional)
                    $asaasPayment = null;
                    try {
                        $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                        $asaasPayment = $asaas->getPayment($paymentId);
                    } catch (Exception $e) {
                        // Se falhar, continuar com dados locais
                        error_log("Erro ao buscar pagamento do ASAAS: " . $e->getMessage());
                    }
                    
                    // Combinar dados locais com dados do ASAAS (se disponíveis)
                    $paymentDetails = [
                        'id' => $localPayment['id'],
                        'customer_name' => $localPayment['customer_name'],
                        'customer_email' => $localPayment['customer_email'],
                        'customer_document' => $localPayment['customer_document'],
                        'value' => $localPayment['value'],
                        'description' => $localPayment['description'],
                        'billing_type' => $localPayment['billing_type'],
                        'status' => $localPayment['status'],
                        'due_date' => $localPayment['due_date'],
                        'received_date' => $localPayment['received_date'],
                        'created_at' => $localPayment['created_at'],
                        'installment_count' => $localPayment['installment_count'],
                        'splits' => $splits,
                        'splits_count' => count($splits),
                        'total_split_value' => array_sum(array_column($splits, 'calculated_split_value'))
                    ];
                    
                    // Se conseguiu dados do ASAAS, adicionar informações extras
                    if ($asaasPayment) {
                        $paymentDetails['asaas_data'] = [
                            'invoice_url' => $asaasPayment['invoiceUrl'] ?? null,
                            'invoice_number' => $asaasPayment['invoiceNumber'] ?? null,
                            'net_value' => $asaasPayment['netValue'] ?? null,
                            'confirmed_date' => $asaasPayment['confirmedDate'] ?? null,
                            'payment_date' => $asaasPayment['paymentDate'] ?? null,
                            'credit_date' => $asaasPayment['creditDate'] ?? null,
                            'transaction_receipt_url' => $asaasPayment['transactionReceiptUrl'] ?? null,
                            'pix_qr_code' => $asaasPayment['pixQrCodeId'] ?? null,
                            'bank_slip_url' => $asaasPayment['bankSlipUrl'] ?? null
                        ];
                    }
                    
                    jsonResponse(true, $paymentDetails, "Detalhes do pagamento carregados");
                    
                } catch (Exception $e) {
                    error_log("Erro em payment-details: " . $e->getMessage());
                    jsonResponse(false, null, "Erro ao carregar detalhes: " . $e->getMessage());
                }
                break;
            
            case 'payment-history':
                try {
                    $paymentId = $_GET['id'] ?? '';
                    
                    if (empty($paymentId)) {
                        jsonResponse(false, null, "ID do pagamento é obrigatório");
                    }
                    
                    $db = DatabaseManager::getInstance();
                    
                    // Buscar histórico de webhooks para este pagamento
                    $stmt = $db->getConnection()->prepare("
                        SELECT 
                            event_type,
                            status,
                            error_message,
                            processed_at,
                            payload
                        FROM webhook_logs 
                        WHERE payment_id = ?
                        ORDER BY processed_at DESC
                        LIMIT 20
                    ");
                    $stmt->execute([$paymentId]);
                    $history = $stmt->fetchAll();
                    
                    // Processar histórico para exibição
                    $processedHistory = [];
                    foreach ($history as $item) {
                        $payload = json_decode($item['payload'], true);
                        $processedHistory[] = [
                            'event' => $item['event_type'],
                            'status' => $item['status'],
                            'error' => $item['error_message'],
                            'date' => $item['processed_at'],
                            'payment_status' => $payload['payment']['status'] ?? 'N/A'
                        ];
                    }
                    
                    jsonResponse(true, $processedHistory, "Histórico do pagamento carregado");
                    
                } catch (Exception $e) {
                    jsonResponse(false, null, "Erro ao carregar histórico: " . $e->getMessage());
                }
                break;
            
            case 'refresh-payment':
                try {
                    $paymentId = $_GET['id'] ?? '';
                    
                    if (empty($paymentId)) {
                        jsonResponse(false, null, "ID do pagamento é obrigatório");
                    }
                    
                    // Buscar dados atualizados do ASAAS
                    $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
                    $asaasPayment = $asaas->getPayment($paymentId);
                    
                    if (!$asaasPayment) {
                        jsonResponse(false, null, "Pagamento não encontrado no ASAAS");
                    }
                    
                    // Atualizar banco local
                    $db = DatabaseManager::getInstance();
                    $stmt = $db->getConnection()->prepare("
                        UPDATE payments 
                        SET status = ?, 
                            received_date = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    
                    $receivedDate = null;
                    if ($asaasPayment['status'] === 'RECEIVED' && isset($asaasPayment['paymentDate'])) {
                        $receivedDate = $asaasPayment['paymentDate'];
                    }
                    
                    $stmt->execute([
                        $asaasPayment['status'],
                        $receivedDate,
                        $paymentId
                    ]);
                    
                    jsonResponse(true, [
                        'status' => $asaasPayment['status'],
                        'received_date' => $receivedDate,
                        'updated' => true
                    ], "Status do pagamento atualizado");
                    
                } catch (Exception $e) {
                    jsonResponse(false, null, "Erro ao atualizar pagamento: " . $e->getMessage());
                }
                break;
            
            case 'payments-summary':
                try {
                    $limit = (int)($_GET['limit'] ?? 20);
                    $offset = (int)($_GET['offset'] ?? 0);
                    $status = $_GET['status'] ?? '';
                    $billingType = $_GET['billing_type'] ?? '';
                    
                    $db = DatabaseManager::getInstance();
                    
                    $whereConditions = [];
                    $params = [];
                    
                    if (!empty($status)) {
                        $whereConditions[] = "p.status = ?";
                        $params[] = $status;
                    }
                    
                    if (!empty($billingType)) {
                        $whereConditions[] = "p.billing_type = ?";
                        $params[] = $billingType;
                    }
                    
                    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
                    
                    $stmt = $db->getConnection()->prepare("
                        SELECT 
                            p.id,
                            p.value,
                            p.description,
                            p.billing_type,
                            p.status,
                            p.due_date,
                            p.received_date,
                            p.created_at,
                            c.name as customer_name,
                            COUNT(ps.id) as splits_count,
                            SUM(CASE 
                                WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                                ELSE (p.value * ps.percentage_value / 100) 
                            END) as total_split_value
                        FROM payments p
                        LEFT JOIN customers c ON p.customer_id = c.id
                        LEFT JOIN payment_splits ps ON p.id = ps.payment_id
                        {$whereClause}
                        GROUP BY p.id
                        ORDER BY p.created_at DESC 
                        LIMIT ? OFFSET ?
                    ");
                    
                    $params[] = $limit;
                    $params[] = $offset;
                    $stmt->execute($params);
                    $payments = $stmt->fetchAll();
                    
                    // Buscar totais
                    $stmt = $db->getConnection()->prepare("
                        SELECT 
                            COUNT(DISTINCT p.id) as total_count,
                            SUM(p.value) as total_value,
                            SUM(CASE WHEN p.status = 'RECEIVED' THEN p.value ELSE 0 END) as received_value
                        FROM payments p
                        LEFT JOIN customers c ON p.customer_id = c.id
                        {$whereClause}
                    ");
                    
                    // Remover os parâmetros de LIMIT/OFFSET para o count
                    $countParams = array_slice($params, 0, -2);
                    $stmt->execute($countParams);
                    $totals = $stmt->fetch();
                    
                    jsonResponse(true, [
                        'payments' => $payments,
                        'totals' => $totals,
                        'pagination' => [
                            'limit' => $limit,
                            'offset' => $offset,
                            'total_count' => $totals['total_count']
                        ]
                    ], "Resumo dos pagamentos carregado");
                    
                } catch (Exception $e) {
                    jsonResponse(false, null, "Erro ao carregar resumo: " . $e->getMessage());
                }
                break;
            
        case 'wallet-usage-analytics':
            try {
                $walletId = $_GET['wallet_id'] ?? '';
                $days = (int)($_GET['days'] ?? 30);
                
                if (empty($walletId)) {
                    jsonResponse(false, null, "Wallet ID é obrigatório");
                }
                
                $db = DatabaseManager::getInstance();
                
                // Dados de uso por dia
                $stmt = $db->getConnection()->prepare("
                    SELECT 
                        DATE(p.created_at) as date,
                        COUNT(ps.id) as split_count,
                        SUM(CASE 
                            WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                            ELSE (p.value * ps.percentage_value / 100) 
                        END) as daily_total
                    FROM payment_splits ps
                    JOIN payments p ON ps.payment_id = p.id
                    WHERE ps.wallet_id = ? 
                        AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                        AND p.status = 'RECEIVED'
                    GROUP BY DATE(p.created_at)
                    ORDER BY date DESC
                ");
                
                $stmt->execute([$walletId, $days]);
                $dailyData = $stmt->fetchAll();
                
                // Estatísticas gerais
                $stmt = $db->getConnection()->prepare("
                    SELECT 
                        COUNT(ps.id) as total_splits,
                        SUM(CASE 
                            WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                            ELSE (p.value * ps.percentage_value / 100) 
                        END) as total_earned,
                        AVG(CASE 
                            WHEN ps.split_type = 'FIXED' THEN ps.fixed_value 
                            ELSE (p.value * ps.percentage_value / 100) 
                        END) as avg_split,
                        MIN(p.created_at) as first_split,
                        MAX(p.created_at) as last_split
                    FROM payment_splits ps
                    JOIN payments p ON ps.payment_id = p.id
                    WHERE ps.wallet_id = ? AND p.status = 'RECEIVED'
                ");
                
                $stmt->execute([$walletId]);
                $generalStats = $stmt->fetch();
                
                jsonResponse(true, [
                    'daily_data' => $dailyData,
                    'general_stats' => $generalStats
                ], "Analytics do Wallet ID carregados");
                
            } catch (Exception $e) {
                jsonResponse(false, null, "Erro ao carregar analytics: " . $e->getMessage());
            }
            break;
            
        default:
            jsonResponse(false, null, "Ação não encontrada: {$action}");
            break;
    }
    
} catch (Exception $e) {
    jsonResponse(false, null, "Erro interno: " . $e->getMessage());
}

// Se chegou até aqui, ação não foi encontrada
jsonResponse(false, null, "Nenhuma ação especificada");

?>