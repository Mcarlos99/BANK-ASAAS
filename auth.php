<?php
/**
 * Sistema de Autenticação Multi-Tenant - VERSÃO CORRIGIDA
 * Arquivo: auth.php
 * 
 * Fix: Resolve problemas com headers e sessões
 */

// Iniciar sessão de forma segura - verificar se já não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    // Configurar sessão antes de iniciar
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    
    session_start();
}

/**
 * Classe principal de autenticação - VERSÃO CORRIGIDA
 */
class AuthSystem {
    
    private $db;
    private $sessionTimeout = 7200; // 2 horas em segundos
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900; // 15 minutos em segundos
    
    public function __construct() {
        // Verificar se DatabaseManager existe antes de instanciar
        if (class_exists('DatabaseManager')) {
            try {
                $this->db = DatabaseManager::getInstance();
            } catch (Exception $e) {
                error_log("Auth: Erro ao conectar com banco: " . $e->getMessage());
                $this->db = null;
            }
        } else {
            error_log("Auth: Classe DatabaseManager não encontrada");
            $this->db = null;
        }
    }
    
    /**
     * Verificar se o banco está disponível
     */
    private function isDatabaseAvailable() {
        return $this->db !== null;
    }
    
    /**
     * Fazer login do usuário
     */
    public function login($email, $senha, $lembrar = false) {
        try {
            if (!$this->isDatabaseAvailable()) {
                throw new Exception('Sistema temporariamente indisponível. Tente novamente em alguns minutos.');
            }
            
            // Verificar se usuário existe e não está bloqueado
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
                throw new Exception('Usuário temporariamente bloqueado. Tente novamente mais tarde.');
            }
            
            // Verificar se usuário está ativo
            if (!$usuario['is_active']) {
                throw new Exception('Usuário desativado. Entre em contato com o administrador.');
            }
            
            // Verificar senha
            if (!password_verify($senha, $usuario['senha'])) {
                $this->incrementarTentativasLogin($usuario['id']);
                $this->logAuditoria($usuario['id'], $usuario['polo_id'], 'login_falhou', 'usuarios', $usuario['id'], [
                    'motivo' => 'Senha incorreta'
                ]);
                throw new Exception('Email ou senha inválidos');
            }
            
            // Verificar se polo está ativo (se não for master)
            if ($usuario['polo_id'] && !$this->isPoloAtivo($usuario['polo_id'])) {
                throw new Exception('Polo desativado. Entre em contato com o administrador.');
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
                    error_log("Auth: Erro ao remover sessão do banco: " . $e->getMessage());
                }
            }
        }
        
        // Limpar sessão
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        
        // Limpar cookie se existir
        if (isset($_COOKIE['sessao_id'])) {
            setcookie('sessao_id', '', time() - 3600, '/');
        }
        
        return ['success' => true, 'message' => 'Logout realizado com sucesso'];
    }
    
    /**
     * Verificar se usuário está logado
     */
    public function isLogado() {
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['sessao_id'])) {
            return false;
        }
        
        return $this->validarSessao();
    }
    
    /**
     * Obter usuário atual
     */
    public function getUsuarioAtual() {
        if (!$this->isLogado()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['usuario_id'] ?? null,
            'nome' => $_SESSION['usuario_nome'] ?? null,
            'email' => $_SESSION['usuario_email'] ?? null,
            'tipo' => $_SESSION['usuario_tipo'] ?? null,
            'polo_id' => $_SESSION['polo_id'] ?? null,
            'polo_nome' => $_SESSION['polo_nome'] ?? null,
            'permissoes' => $_SESSION['permissoes'] ?? []
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
        
        // Verificar permissões específicas
        $permissoes = $_SESSION['permissoes'] ?? [];
        return in_array($permissao, $permissoes) || in_array('*', $permissoes);
    }
    
    /**
     * Verificar se é admin do polo atual
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
     * Obter configurações do ASAAS para o polo atual
     */
    public function getAsaasConfig() {
        if (!$this->isLogado()) {
            throw new Exception('Usuário não logado');
        }
        
        if (!$this->isDatabaseAvailable()) {
            // Fallback para configurações globais se banco não disponível
            return [
                'environment' => defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'sandbox',
                'production_key' => defined('ASAAS_PRODUCTION_API_KEY') ? ASAAS_PRODUCTION_API_KEY : null,
                'sandbox_key' => defined('ASAAS_SANDBOX_API_KEY') ? ASAAS_SANDBOX_API_KEY : null,
                'webhook_token' => defined('ASAAS_WEBHOOK_TOKEN') ? ASAAS_WEBHOOK_TOKEN : null
            ];
        }
        
        // Master usa configurações globais
        if ($this->isMaster()) {
            return [
                'environment' => defined('ASAAS_ENVIRONMENT') ? ASAAS_ENVIRONMENT : 'sandbox',
                'production_key' => defined('ASAAS_PRODUCTION_API_KEY') ? ASAAS_PRODUCTION_API_KEY : null,
                'sandbox_key' => defined('ASAAS_SANDBOX_API_KEY') ? ASAAS_SANDBOX_API_KEY : null,
                'webhook_token' => defined('ASAAS_WEBHOOK_TOKEN') ? ASAAS_WEBHOOK_TOKEN : null
            ];
        }
        
        // Usuários de polo usam configurações específicas
        $poloId = $_SESSION['polo_id'] ?? null;
        if (!$poloId) {
            throw new Exception('Polo não identificado');
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT asaas_environment, asaas_production_api_key, asaas_sandbox_api_key, asaas_webhook_token 
                FROM polos WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$poloId]);
            $polo = $stmt->fetch();
            
            if (!$polo) {
                throw new Exception('Polo não encontrado ou inativo');
            }
            
            return [
                'environment' => $polo['asaas_environment'] ?? 'sandbox',
                'production_key' => $polo['asaas_production_api_key'],
                'sandbox_key' => $polo['asaas_sandbox_api_key'],
                'webhook_token' => $polo['asaas_webhook_token']
            ];
        } catch (Exception $e) {
            error_log("Auth: Erro ao obter config ASAAS: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Middleware para verificar login
     */
    public function requireLogin($redirect = 'login.php') {
        if (!$this->isLogado()) {
            // Salvar URL atual para redirect após login
            if (!isset($_SESSION['redirect_after_login'])) {
                $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
            }
            
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
    public function requirePermission($permissao, $redirect = 'acesso_negado.php') {
        $this->requireLogin();
        
        if (!$this->temPermissao($permissao)) {
            if (!headers_sent()) {
                header("Location: {$redirect}");
                exit;
            } else {
                echo "<script>window.location.href = '{$redirect}';</script>";
                exit;
            }
        }
    }
    
    // =====================================
    // MÉTODOS PRIVADOS
    // =====================================
    
    /**
     * Obter usuário pelo email
     */
    private function getUsuario($email) {
        if (!$this->isDatabaseAvailable()) {
            return null;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT u.*, p.nome as polo_nome, p.codigo as polo_codigo
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
        if (!$usuario['bloqueado_ate']) {
            return $usuario['tentativas_login'] >= $this->maxLoginAttempts;
        }
        
        return new DateTime() < new DateTime($usuario['bloqueado_ate']);
    }
    
    /**
     * Verificar se polo está ativo
     */
    private function isPoloAtivo($poloId) {
        if (!$this->isDatabaseAvailable()) {
            return true; // Assumir ativo se não pode verificar
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("SELECT is_active FROM polos WHERE id = ?");
            $stmt->execute([$poloId]);
            $result = $stmt->fetch();
            return $result && $result['is_active'];
        } catch (Exception $e) {
            error_log("Auth: Erro ao verificar polo: " . $e->getMessage());
            return true;
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
     * Criar sessão
     */
    private function criarSessao($usuario, $lembrar = false) {
        $sessaoId = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', time() + ($lembrar ? 86400 * 30 : $this->sessionTimeout));
        
        // Salvar sessão no banco se disponível
        if ($this->isDatabaseAvailable()) {
            try {
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
                error_log("Auth: Erro ao salvar sessão no banco: " . $e->getMessage());
            }
        }
        
        // Configurar sessão PHP
        $_SESSION['sessao_id'] = $sessaoId;
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        $_SESSION['polo_id'] = $usuario['polo_id'];
        $_SESSION['polo_nome'] = $usuario['polo_nome'];
        $_SESSION['permissoes'] = json_decode($usuario['permissoes'] ?? '[]', true);
        
        // Cookie se "lembrar"
        if ($lembrar && !headers_sent()) {
            setcookie('sessao_id', $sessaoId, time() + (86400 * 30), '/', '', 
                isset($_SERVER['HTTPS']), true);
        }
    }
    
    /**
     * Validar sessão atual
     */
    private function validarSessao() {
        $sessaoId = $_SESSION['sessao_id'] ?? ($_COOKIE['sessao_id'] ?? null);
        
        if (!$sessaoId) {
            return false;
        }
        
        if (!$this->isDatabaseAvailable()) {
            // Se banco não disponível, validar apenas por tempo de sessão PHP
            return true;
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT * FROM sessoes WHERE id = ? AND expira_em > NOW()
            ");
            $stmt->execute([$sessaoId]);
            $sessao = $stmt->fetch();
            
            if (!$sessao) {
                return false;
            }
            
            // Atualizar última atividade
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
            'ultimo_login' => $usuario['ultimo_login']
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
            return $redirect;
        }
        
        // Redirect padrão baseado no tipo de usuário
        switch ($usuario['tipo']) {
            case 'master':
                return 'admin_master.php';
            case 'admin_polo':
                return 'index.php';
            default:
                return 'index.php';
        }
    }
    
    /**
     * Obter IP do cliente
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return 'unknown';
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
}

/**
 * Classe para gerenciar polos - VERSÃO CORRIGIDA
 */
class PoloManager {
    
    private $db;
    private $auth;
    
    public function __construct() {
        if (class_exists('DatabaseManager')) {
            try {
                $this->db = DatabaseManager::getInstance();
            } catch (Exception $e) {
                error_log("PoloManager: Erro ao conectar com banco: " . $e->getMessage());
                $this->db = null;
            }
        }
        $this->auth = new AuthSystem();
    }
    
    /**
     * Verificar se o banco está disponível
     */
    private function isDatabaseAvailable() {
        return $this->db !== null;
    }
    
    /**
     * Listar polos (apenas para master)
     */
    public function listarPolos($incluirInativos = false) {
        if (!$this->auth->isMaster()) {
            throw new Exception('Acesso negado');
        }
        
        if (!$this->isDatabaseAvailable()) {
            throw new Exception('Sistema temporariamente indisponível');
        }
        
        $whereClause = $incluirInativos ? '' : 'WHERE is_active = 1';
        
        try {
            $stmt = $this->db->getConnection()->query("
                SELECT p.*, 
                       (SELECT COUNT(*) FROM usuarios WHERE polo_id = p.id AND is_active = 1) as total_usuarios
                FROM polos p 
                {$whereClause}
                ORDER BY nome
            ");
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("PoloManager: Erro ao listar polos: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Outros métodos mantidos conforme versão anterior...
}

// Instância global para uso fácil - com verificação
try {
    $auth = new AuthSystem();
} catch (Exception $e) {
    error_log("Erro ao inicializar AuthSystem: " . $e->getMessage());
    $auth = null;
}
?>