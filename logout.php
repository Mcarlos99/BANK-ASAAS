<?php
/**
 * Endpoint de Logout - Sistema IMEP Split ASAAS
 * Arquivo: logout.php
 * 
 * Este arquivo deve ser criado na raiz do projeto
 */

// Iniciar sessão de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Incluir sistema de autenticação
    require_once 'bootstrap.php';
    
    // Verificar se o sistema de auth está disponível
    if (!$auth) {
        throw new Exception('Sistema de autenticação não disponível');
    }
    
    // Obter dados do usuário antes do logout para log
    $usuario = null;
    if ($auth->isLogado()) {
        $usuario = $auth->getUsuarioAtual();
    }
    
    // Executar logout
    $resultado = $auth->logout();
    
    // Log da ação
    if ($usuario) {
        error_log("Logout realizado: {$usuario['email']} ({$usuario['tipo']}) - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    // Redirecionar para login com mensagem de sucesso
    $message = $resultado['success'] ? 'Logout realizado com sucesso' : 'Erro no logout';
    header('Location: login.php?message=' . urlencode($message));
    exit;
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro no logout: " . $e->getMessage());
    
    // Fallback: destruir sessão manualmente
    session_unset();
    session_destroy();
    
    // Destruir cookie de sessão se existir
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destruir outros cookies relacionados
    if (isset($_COOKIE['sessao_id'])) {
        setcookie('sessao_id', '', time() - 3600, '/');
    }
    
    // Redirecionar mesmo em caso de erro
    header('Location: login.php?error=' . urlencode('Sessão encerrada'));
    exit;
}
?>