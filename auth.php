<?php
/**
 * Sistema de Autenticação Multi-Tenant - VERSÃO FINAL CORRIGIDA
 * Arquivo: auth.php
 * 
 * IMPORTANTE: Este arquivo implementa verificação rigorosa de autenticação
 */

// Configurações de sessão seguras
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 7200); // 2 horas
    
    session_start();
}

/**
 * Classe principal de autenticação - VERSÃO FINAL
 */
class AuthSystem {
    
    private $db;
    private $sessionTimeout = 7200; // 2 horas
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900; // 15 minutos
    
    public function __construct() {
        if (class_exists('DatabaseManager')) {
            try {
                $this->db = DatabaseManager::getInstance();
            } catch (Exception $e) {
                error_log("Auth: Erro ao conectar com banco: " . $e->getMessage());
                $this->db = null;
            }
        }
    }
    
    /**
     * Fazer login do usuário
     */
    public function login($email, $senha, $lembrar = false) {
        try {
            if (!$this->isDatabaseAvailable()) {
                throw new Exception('Sistema temporariamente indisponível');
            }
            
            $usuario = $this->getUsuario($email);
            
            if (!$usuario) {
                $this->logAuditoria(null, null, 'login_falhou', 'usuarios', null, [
                    'email' => $email,
                    'motivo' => 'Usuario nao encontrado'
                ]);
                throw new Exception('Email ou senha inválidos');
            }
            
            // Verificar se está bloqueado
            if ($this->isUsuarioBloqueado($usuario)) {
                throw new Exception('Usuário temporariamente bloqueado por tentativas excessivas');
            }
            
            // Verificar se usuário está ativo
            if (!$usuario['is_active']) {
                throw new Exception('Usuário desativado. Entre em contato com o administrador');
            }
            
            // Verificar senha
            if (!password_verify($senha, $usuario['senha'])) {
                $this->incrementarTentativasLogin($usuario['id']);
                throw new Exception('Email ou senha inválidos');
            }
            
            // Verificar se polo está ativo (se aplicável)
            if ($usuario['polo_id'] && !$this->isPoloAtivo($usuario['polo_id'])) {
                throw new Exception('Seu polo foi desativado. Entre em contato com o administrador');
            }
            
            // Login bem-sucedido
            $this->limparTentativasLogin($usuario['id']);
            $this->criarSessao($usuario, $lembrar);
            $this->atualizarUltimoLogin($usuario['id']);
            
            $this->logAuditoria($usuario['id'], $usuario['polo_id'], 'login_sucesso', 'usuarios', $usuario['id']);
            
            return [
                'success' => true,
                'usuario' => $this->formatarUsuarioSessao($usuario),
                'redirect' => $this->getRedirectUrl($usuario)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Fazer logout
     */
    public function logout() {
        if ($this->isLogado()) {
            $usuarioId = $_SESSION['usuario_id'] ?? null;
            $poloId = $_SESSION['polo_id'] ?? null;
            
            // Log de auditoria
            if ($this->isDatabaseAvailable()) {
                $this->logAuditoria($usuarioId, $poloId, 'logout', 'usuarios', $usuarioId);
            }
            
            // Remover sessão do banco
            if (isset($_SESSION['sessao_id']) && $this->isDatabaseAvailable()) {
                try {
                    $stmt = $this->db->getConnection()->prepare("DELETE FROM sessoes WHERE id = ?");
                    $stmt->execute([$_SESSION['sessao_id']]);
                } catch (Exception $e) {
                    error_log("Auth: Erro ao remover sessão: " . $e->getMessage());
                }
            }
        }
        
        // Limpar sessão PHP
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            // Destruir cookie de sessão
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            session_destroy();
        }
        
        // Limpar cookie personalizado
        if (isset($_COOKIE['sessao_id'])) {
            setcookie('sessao_id', '', time() - 3600, '/');
        }
        
        return ['success' => true, 'message' => 'Logout realizado com sucesso'];
    }
    
    /**
     * Verificar se usuário está logado - VERSÃO RIGOROSA
     */
    public function isLogado() {
        // Verificações básicas da sessão
        if (!isset($_SESSION['usuario_id']) || 
            !isset($_SESSION['sessao_id']) || 
            !isset($_SESSION['usuario_email'])) {
            return false;
        }
        
        // Verificar se a sessão não expirou
        if (isset($_SESSION['ultima_atividade'])) {
            if (time() - $_SESSION['ultima_atividade'] > $this->sessionTimeout) {
                $this->logout();
                return false;
            }
        }
        
        // Verificar sessão no banco de dados
        if (!$this->validarSessao()) {
            $this->logout();
            return false;
        }
        
        // Verificar se usuário ainda está ativo
        if (!$this->isUsuarioAindaAtivo($_SESSION['usuario_id'])) {
            $this->logout();
            return false;
        }
        
        // Atualizar timestamp da última atividade
        $_SESSION['ultima_atividade'] = time();
        
        return true;
    }
    
    /**
     * Obter usuário atual
     */
    public function getUsuarioAtual() {
        if (!$this->isLogado()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['usuario_id'],
            'nome' => $_SESSION['usuario_nome'],
            'email' => $_SESSION['usuario_email'],
            'tipo' => $_SESSION['usuario_tipo'],
            'polo_id' => $_SESSION['polo_id'] ?? null,
            'polo_nome' => $_SESSION['polo_nome'] ?? null,
            'permissoes' => $_SESSION['permissoes'] ?? [],
            'is_active' => $_SESSION['is_active'] ?? true,
            'ultimo_login' => $_SESSION['ultimo_login'] ?? null
        ];
    }
    
    /**
     * Verificar permissão específica
     */
    public function temPermissao($permissao) {
        if (!$this->isLogado()) {
            return false;
        }
        
        // Master tem todas as permissões
        if (($_SESSION['usuario_tipo'] ?? '') === 'master') {
            return true;
        }
        
        // Definir permissões por tipo de usuário
        $permissoesPorTipo = [
            'admin_polo' => [
                'gerenciar_clientes', 
                'gerenciar_wallets', 
                'gerenciar_contas_split', 
                'gerenciar_pagamentos',
                'ver_relatorios', 
                'configurar_polo',
                'testar_conexao',
                'sincronizar_contas'
            ],
            'operador' => [
                'gerenciar_clientes', 
                'gerenciar_wallets', 
                'gerenciar_pagamentos',
                'ver_relatorios'
            ]
        ];
        
        $tipoUsuario = $_SESSION['usuario_tipo'] ?? '';
        $permissoesPermitidas = $permissoesPorTipo[$tipoUsuario] ?? [];
        
        return in_array($permissao, $permissoesPermitidas);
    }
    
    /**
     * Verificar se é admin do polo
     */
    public function isAdminPolo() {
        return $this->isLogado() && ($_SESSION['usuario_tipo'] ?? '') === 'admin_polo';
    }
    
    /**
     * Verificar se é master admin
     */
    public function isMaster() {
        return $this->isLogado() && ($_SESSION['usuario_tipo'] ?? '') === 'master';
    }
    
    /**
     * Middleware para verificar login
     */
    public function requireLogin($redirect = 'login.php') {
        if (!$this->isLogado()) {
            // Salvar URL atual para redirect após login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
            
            // Fazer redirect
            if (!headers_sent()) {
                header("Location: {$redirect}");
                exit;
            } else {
                echo "<script>window.location.href = '{$redirect}';</script>";
                exit;
            }
        }
    }
    
    /**
     * Middleware para verificar permissão
     */
    public function requirePermission($permissao, $errorMessage = null) {
        $this->requireLogin();
        
        if (!$this->temPermissao($permissao)) {
            if ($errorMessage) {
                $_SESSION['error_message'] = $errorMessage;
            }
            
            if (!headers_sent()) {
                header('HTTP/1.0 403 Forbidden');
                header('Location: acesso_negado.php');
                exit;
            } else {
                showError(
                    $errorMessage ?? 'Você não tem permissão para acessar esta funcionalidade.',
                    'Acesso Negado'
                );
                exit;
            }
        }
    }
    
    // =====================================
    // MÉTODOS PRIVADOS
    // =====================================
    
    /**
     * Verificar se o banco está disponível
     */
    private function isDatabaseAvailable() {
        return $this->db !== null;
    }
    
    /**
     * Obter usuário pelo email
     */
    private function getUsuario($email) {
        if (!$this->isDatabaseAvailable()) {
            return null;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT u.*, p.nome as polo_nome, p.codigo as polo_codigo, p.is_active as polo_ativo
                FROM usuarios u
                LEFT JOIN polos p ON u.polo_id = p.id
                WHERE u.email = ?
            ");
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Auth: Erro ao buscar usuário: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verificar se usuário está bloqueado
     */
    private function isUsuarioBloqueado($usuario) {
        if ($usuario['tentativas_login'] >= $this->maxLoginAttempts) {
            return true;
        }
        
        if ($usuario['bloqueado_ate']) {
            return new DateTime() < new DateTime($usuario['bloqueado_ate']);
        }
        
        return false;
    }
    
    /**
     * Verificar se polo está ativo
     */
    private function isPoloAtivo($poloId) {
        if (!$this->isDatabaseAvailable()) {
            return true;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT is_active FROM polos WHERE id = ?
            ");
            $stmt->execute([$poloId]);
            $result = $stmt->fetch();
            return $result && $result['is_active'];
        } catch (Exception $e) {
            error_log("Auth: Erro ao verificar polo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se usuário ainda está ativo
     */
    private function isUsuarioAindaAtivo($usuarioId) {
        if (!$this->isDatabaseAvailable()) {
            return true;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT u.is_active, p.is_active as polo_ativo 
                FROM usuarios u
                LEFT JOIN polos p ON u.polo_id = p.id
                WHERE u.id = ?
            ");
            $stmt->execute([$usuarioId]);
            $result = $stmt->fetch();
            
            if (!$result || !$result['is_active']) {
                return false;
            }
            
            // Se tem polo, verificar se está ativo
            if ($result['polo_ativo'] !== null && !$result['polo_ativo']) {
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Auth: Erro ao verificar atividade do usuário: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Incrementar tentativas de login
     */
    private function incrementarTentativasLogin($usuarioId) {
        if (!$this->isDatabaseAvailable()) {
            return;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE usuarios 
                SET tentativas_login = tentativas_login + 1,
                    bloqueado_ate = CASE 
                        WHEN tentativas_login + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? SECOND)
                        ELSE bloqueado_ate
                    END
                WHERE id = ?
            ");
            $stmt->execute([$this->maxLoginAttempts, $this->lockoutTime, $usuarioId]);
        } catch (Exception $e) {
            error_log("Auth: Erro ao incrementar tentativas: " . $e->getMessage());
        }
    }
    
    /**
     * Limpar tentativas de login
     */
    private function limparTentativasLogin($usuarioId) {
        if (!$this->isDatabaseAvailable()) {
            return;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE usuarios 
                SET tentativas_login = 0, bloqueado_ate = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$usuarioId]);
        } catch (Exception $e) {
            error_log("Auth: Erro ao limpar tentativas: " . $e->getMessage());
        }
    }
    
    /**
     * Criar sessão segura
     */
    private function criarSessao($usuario, $lembrar = false) {
        // Regenerar ID da sessão para prevenir fixation
        session_regenerate_id(true);
        
        $sessaoId = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', time() + ($lembrar ? 86400 * 30 : $this->sessionTimeout));
        
        // Salvar sessão no banco
        if ($this->isDatabaseAvailable()) {
            try {
                // Primeiro, limpar sessões antigas do usuário
                $stmt = $this->db->getConnection()->prepare("
                    DELETE FROM sessoes 
                    WHERE usuario_id = ? OR expira_em < NOW()
                ");
                $stmt->execute([$usuario['id']]);
                
                // Criar nova sessão
                $stmt = $this->db->getConnection()->prepare("
                    INSERT INTO sessoes (id, usuario_id, polo_id, ip_address, user_agent, expira_em) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $sessaoId,
                    $usuario['id'],
                    $usuario['polo_id'],
                    $this->getClientIP(),
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $expiraEm
                ]);
            } catch (Exception $e) {
                error_log("Auth: Erro ao salvar sessão: " . $e->getMessage());
            }
        }
        
        // Configurar variáveis da sessão
        $_SESSION['sessao_id'] = $sessaoId;
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        $_SESSION['polo_id'] = $usuario['polo_id'];
        $_SESSION['polo_nome'] = $usuario['polo_nome'];
        $_SESSION['is_active'] = $usuario['is_active'];
        $_SESSION['ultimo_login'] = $usuario['ultimo_login'];
        $_SESSION['login_time'] = time();
        $_SESSION['ultima_atividade'] = time();
        $_SESSION['ip_address'] = $this->getClientIP();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Cookie se "lembrar"
        if ($lembrar && !headers_sent()) {
            setcookie('sessao_id', $sessaoId, [
                'expires' => time() + (86400 * 30),
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }
    
    /**
     * Validar sessão atual
     */
    private function validarSessao() {
        $sessaoId = $_SESSION['sessao_id'] ?? null;
        
        if (!$sessaoId || !$this->isDatabaseAvailable()) {
            return false;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT s.*, u.is_active as usuario_ativo, p.is_active as polo_ativo
                FROM sessoes s
                JOIN usuarios u ON s.usuario_id = u.id
                LEFT JOIN polos p ON s.polo_id = p.id
                WHERE s.id = ? AND s.expira_em > NOW()
            ");
            $stmt->execute([$sessaoId]);
            $sessao = $stmt->fetch();
            
            if (!$sessao) {
                return false;
            }
            
            // Verificar se usuário ainda está ativo
            if (!$sessao['usuario_ativo']) {
                return false;
            }
            
            // Verificar se polo ainda está ativo (se aplicável)
            if ($sessao['polo_id'] && !$sessao['polo_ativo']) {
                return false;
            }
            
            // Verificar IP (opcional - pode causar problemas com proxies)
            // if ($sessao['ip_address'] !== $this->getClientIP()) {
            //     return false;
            // }
            
            // Atualizar última atividade na sessão
            $stmt = $this->db->getConnection()->prepare("
                UPDATE sessoes SET ultima_atividade = NOW() WHERE id = ?
            ");
            $stmt->execute([$sessaoId]);
            
            return true;
        } catch (Exception $e) {
            error_log("Auth: Erro ao validar sessão: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualizar último login
     */
    private function atualizarUltimoLogin($usuarioId) {
        if (!$this->isDatabaseAvailable()) {
            return;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?
            ");
            $stmt->execute([$usuarioId]);
        } catch (Exception $e) {
            error_log("Auth: Erro ao atualizar último login: " . $e->getMessage());
        }
    }
    
    /**
     * Formatar usuário para sessão
     */
    private function formatarUsuarioSessao($usuario) {
        return [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'tipo' => $usuario['tipo'],
            'polo_id' => $usuario['polo_id'],
            'polo_nome' => $usuario['polo_nome'],
            'ultimo_login' => $usuario['ultimo_login'],
            'is_active' => $usuario['is_active']
        ];
    }
    
    /**
     * Obter URL de redirecionamento após login
     */
    private function getRedirectUrl($usuario) {
        // Verificar se há redirect específico na sessão
        if (isset($_SESSION['redirect_after_login'])) {
            $redirect = $_SESSION['redirect_after_login'];
            unset($_SESSION['redirect_after_login']);
            
            // Verificar se é uma URL segura
            if (strpos($redirect, 'login.php') === false && 
                strpos($redirect, 'logout') === false) {
                return $redirect;
            }
        }
        
        // Redirect padrão baseado no tipo
        switch ($usuario['tipo']) {
            case 'master':
                return 'admin_master.php';
            default:
                return 'index.php';
        }
    }
    
    /**
     * Obter IP do cliente
     */
    private function getClientIP() {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP', 
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_X_FORWARDED', 
            'HTTP_X_CLUSTER_CLIENT_IP', 
            'HTTP_FORWARDED_FOR', 
            'HTTP_FORWARDED', 
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Log de auditoria
     */
    private function logAuditoria($usuarioId, $poloId, $acao, $tabela = null, $registroId = null, $dadosNovos = null, $dadosAnteriores = null) {
        if (!$this->isDatabaseAvailable()) {
            return;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO auditoria (usuario_id, polo_id, acao, tabela, registro_id, dados_anteriores, dados_novos, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $usuarioId,
                $poloId,
                $acao,
                $tabela,
                $registroId,
                $dadosAnteriores ? json_encode($dadosAnteriores) : null,
                $dadosNovos ? json_encode($dadosNovos) : null,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Auth: Erro ao gravar auditoria: " . $e->getMessage());
        }
    }
    
    /**
     * Limpar sessões expiradas (método de limpeza)
     */
    public function limparSessoesExpiradas() {
        if (!$this->isDatabaseAvailable()) {
            return 0;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                DELETE FROM sessoes WHERE expira_em < NOW()
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Auth: Erro ao limpar sessões: " . $e->getMessage());
            return 0;
        }
    }
}

// Instância global para uso fácil
try {
    if (!isset($auth)) {
        $auth = new AuthSystem();
    }
} catch (Exception $e) {
    error_log("Erro ao inicializar AuthSystem global: " . $e->getMessage());
    $auth = null;
}