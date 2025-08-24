<?php
/**
 * Webhook Handler para ASAAS - Versão Melhorada
 * Arquivo: webhook.php
 * 
 * Este arquivo deve ser acessível via URL pública
 * Exemplo: https://bank.imepedu.com.br/webhook.php
 */

// Log detalhado para debug (pode remover depois)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros na tela
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/webhook_errors.log');

// Criar diretório de logs se não existir
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Log de debug
function debugLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents(__DIR__ . '/logs/webhook_debug.log', $logMessage, FILE_APPEND | LOCK_EX);
}

debugLog("=== WEBHOOK RECEBIDO ===");
debugLog("Método: " . $_SERVER['REQUEST_METHOD']);
debugLog("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'));

try {
    // Incluir arquivos necessários
    require_once 'bootstrap.php';
    
    debugLog("Arquivos incluídos com sucesso");
    
    // Configurar headers para CORS se necessário
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type, Asaas-Signature');
    header('Content-Type: application/json');
    
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        debugLog("Método inválido: " . $_SERVER['REQUEST_METHOD']);
        http_response_code(405);
        exit(json_encode(['error' => 'Method not allowed']));
    }
    
    // Capturar dados brutos
    $rawPayload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_ASAAS_SIGNATURE'] ?? '';
    
    debugLog("Payload recebido: " . substr($rawPayload, 0, 200) . "...");
    debugLog("Signature: " . $signature);
    
    // Validações básicas
    if (empty($rawPayload)) {
        debugLog("Payload vazio");
        http_response_code(400);
        exit(json_encode(['error' => 'Empty payload']));
    }
    
    // Verificar se as constantes estão definidas
    if (!defined('ASAAS_ENVIRONMENT')) {
        debugLog("ASAAS_ENVIRONMENT não definido");
        throw new Exception("Configuração ASAAS_ENVIRONMENT não encontrada");
    }
    
    if (!defined('ASAAS_PRODUCTION_API_KEY')) {
        debugLog("ASAAS_PRODUCTION_API_KEY não definido");
        throw new Exception("Configuração ASAAS_PRODUCTION_API_KEY não encontrada");
    }
    
    debugLog("Ambiente: " . ASAAS_ENVIRONMENT);
    debugLog("API Key configurada: " . (ASAAS_PRODUCTION_API_KEY ? 'SIM' : 'NÃO'));
    
    // Verificar se API key está configurada corretamente
    $apiKey = ASAAS_ENVIRONMENT === 'production' ? ASAAS_PRODUCTION_API_KEY : ASAAS_SANDBOX_API_KEY;
    
    if (empty($apiKey) || 
        $apiKey === '$aact_MTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1' || 
        $apiKey === 'SUA_API_KEY_PRODUCAO_AQUI' ||
        $apiKey === 'SUA_API_KEY_SANDBOX_AQUI') {
        
        debugLog("API Key não configurada corretamente");
        throw new Exception("API Key não configurada para ambiente '" . ASAAS_ENVIRONMENT . "'. Configure no arquivo config_api.php");
    }
    
    // Inicializar sistema
    try {
        $asaas = AsaasConfig::getInstance(ASAAS_ENVIRONMENT);
        debugLog("Sistema ASAAS inicializado");
    } catch (Exception $e) {
        debugLog("Erro ao inicializar ASAAS: " . $e->getMessage());
        throw $e;
    }
    
    // Decodificar dados JSON
    $webhookData = json_decode($rawPayload, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        debugLog("Erro ao decodificar JSON: " . json_last_error_msg());
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]));
    }
    
    debugLog("Evento: " . ($webhookData['event'] ?? 'N/A'));
    debugLog("Payment ID: " . ($webhookData['payment']['id'] ?? 'N/A'));
    
    // Validar assinatura do webhook (se configurado)
    if (!empty(ASAAS_WEBHOOK_TOKEN) && !empty($signature)) {
        debugLog("Validando assinatura do webhook");
        if (!$asaas->validateWebhook($rawPayload, $signature, ASAAS_WEBHOOK_TOKEN)) {
            debugLog("Assinatura inválida");
            http_response_code(401);
            exit(json_encode(['error' => 'Invalid signature']));
        }
        debugLog("Assinatura válida");
    } else {
        debugLog("Validação de assinatura pulada (token não configurado)");
    }
    
    // Processar webhook
    debugLog("Processando webhook...");
    $result = $asaas->processWebhook($webhookData);
    debugLog("Webhook processado: " . json_encode($result));
    
    // Salvar no banco de dados também
    try {
        $db = DatabaseManager::getInstance();
        
        // Atualizar status do pagamento se for PAYMENT_RECEIVED
        if ($webhookData['event'] === 'PAYMENT_RECEIVED') {
            $paymentId = $webhookData['payment']['id'];
            $receivedDate = $webhookData['payment']['paymentDate'] ?? date('Y-m-d');
            
            debugLog("Atualizando pagamento {$paymentId} para RECEIVED");
            
            $stmt = $db->getConnection()->prepare("
                UPDATE payments 
                SET status = 'RECEIVED', received_date = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$receivedDate, $paymentId]);
            
            debugLog("Pagamento atualizado no banco");
        }
        
        // Log do webhook
        $db->logWebhook(
            $webhookData['event'],
            $webhookData['payment']['id'] ?? null,
            $webhookData,
            'SUCCESS',
            null
        );
        
        debugLog("Webhook salvo no banco");
        
    } catch (Exception $e) {
        debugLog("Erro ao salvar no banco: " . $e->getMessage());
        // Não falhar o webhook por erro de banco
    }
    
    // Resposta de sucesso
    http_response_code(200);
    $response = [
        'status' => 'success',
        'message' => 'Webhook processed successfully',
        'event' => $webhookData['event'] ?? 'unknown',
        'payment_id' => $webhookData['payment']['id'] ?? 'unknown',
        'processed_at' => date('Y-m-d H:i:s'),
        'environment' => ASAAS_ENVIRONMENT,
        'data' => $result
    ];
    
    debugLog("Resposta enviada: " . json_encode($response));
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log do erro detalhado
    $errorMessage = $e->getMessage();
    $errorFile = $e->getFile();
    $errorLine = $e->getLine();
    
    debugLog("ERRO: {$errorMessage}");
    debugLog("Arquivo: {$errorFile}:{$errorLine}");
    debugLog("Stack trace: " . $e->getTraceAsString());
    
    // Log no arquivo de erro também
    error_log("Webhook Error: {$errorMessage} in {$errorFile}:{$errorLine}");
    
    // Tentar salvar erro no banco
    try {
        if (class_exists('DatabaseManager')) {
            $db = DatabaseManager::getInstance();
            $db->logWebhook(
                $_POST['event'] ?? 'unknown',
                $_POST['payment']['id'] ?? null,
                json_decode($rawPayload ?? '{}', true),
                'FAILED',
                $errorMessage
            );
        }
    } catch (Exception $dbError) {
        debugLog("Erro ao salvar erro no banco: " . $dbError->getMessage());
    }
    
    // Resposta de erro detalhada para debug
    http_response_code(500);
    $errorResponse = [
        'status' => 'error',
        'message' => $errorMessage,
        'environment' => defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'undefined',
        'api_key_configured' => defined('ASAAS_PRODUCTION_API_KEY') ? (ASAAS_PRODUCTION_API_KEY ? 'yes' : 'no') : 'undefined',
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'file' => basename($errorFile),
            'line' => $errorLine,
            'payload_size' => strlen($rawPayload ?? ''),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ]
    ];
    
    echo json_encode($errorResponse);
}

debugLog("=== FIM DO WEBHOOK ===\n");
?>