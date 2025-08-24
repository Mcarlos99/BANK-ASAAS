<?php
/**
 * Página de Login - VERSÃO CORRIGIDA
 * Arquivo: login.php
 */

// Incluir bootstrap do sistema (corrige problemas de headers)
require_once 'bootstrap.php';

// Se já está logado, redirecionar
if (isset($auth) && $auth && $auth->isLogado()) {
    $usuario = $auth->getUsuarioAtual();
    $redirect = ($usuario['tipo'] === 'master') ? 'admin_master.php' : 'index.php';
    safeRedirect($redirect);
}

$erro = '';
$sucesso = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $lembrar = isset($_POST['lembrar']);
    
    if (empty($email) || empty($senha)) {
        $erro = 'Email e senha são obrigatórios';
    } else {
        if ($auth) {
            $resultado = $auth->login($email, $senha, $lembrar);
            
            if ($resultado['success']) {
                safeRedirect($resultado['redirect']);
            } else {
                $erro = $resultado['message'];
            }
        } else {
            $erro = 'Sistema temporariamente indisponível. Tente novamente em alguns instantes.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema IMEP Split ASAAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 0;
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .login-left {
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="%23ffffff08" points="0,0 1000,300 1000,1000 0,700"/></svg>');
            background-size: cover;
        }
        
        .login-left .content {
            position: relative;
            z-index: 1;
        }
        
        .login-right {
            padding: 60px 40px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            padding: 8px 0;
            position: relative;
            padding-left: 25px;
        }
        
        .feature-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #4CAF50;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .login-left, .login-right {
                padding: 40px 30px;
            }
        }
        
        .loading {
            display: none;
        }
        
        .loading.show {
            display: inline-block;
        }
        
        .demo-credentials {
            position: fixed;
            bottom: 20px;
            left: 20px;
            max-width: 300px;
            z-index: 1000;
        }
        
        @media (max-width: 768px) {
            .demo-credentials {
                position: relative;
                bottom: auto;
                left: auto;
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="row g-0">
                <!-- Lado Esquerdo - Informações -->
                <div class="col-lg-6 login-left">
                    <div class="content">
                        <h2 class="mb-4">
                            <i class="bi bi-building"></i>
                            IMEP Split ASAAS
                        </h2>
                        
                        <p class="lead mb-4">
                            Sistema de gestão de pagamentos com split automático para todos os polos da rede IMEP.
                        </p>
                        
                        <ul class="feature-list">
                            <li>Gestão multi-polo independente</li>
                            <li>Split de pagamentos automatizado</li>
                            <li>Controle de Wallet IDs por polo</li>
                            <li>Relatórios financeiros detalhados</li>
                            <li>Integração completa com ASAAS</li>
                            <li>Auditoria completa de ações</li>
                        </ul>
                        
                        <div class="mt-4 pt-4" style="border-top: 1px solid rgba(255,255,255,0.2);">
                            <small>
                                <i class="bi bi-shield-check"></i>
                                Sistema seguro com autenticação multi-fator
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Lado Direito - Formulário -->
                <div class="col-lg-6 login-right">
                    <div class="text-center mb-4">
                        <h3>Bem-vindo de volta!</h3>
                        <p class="text-muted">Entre com suas credenciais para acessar o sistema</p>
                    </div>
                    
                    <!-- Mensagens -->
                    <?php if ($erro): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($erro); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($sucesso): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i>
                            <?php echo htmlspecialchars($sucesso); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Formulário de Login -->
                    <form method="POST" id="loginForm">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       required 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="seu@email.com">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="senha" 
                                       name="senha" 
                                       required 
                                       placeholder="Digite sua senha">
                                <button class="btn btn-outline-secondary" 
                                        type="button" 
                                        onclick="togglePassword()">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="lembrar" name="lembrar">
                            <label class="form-check-label" for="lembrar">
                                Manter-me conectado por 30 dias
                            </label>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-login text-white">
                                <span class="loading spinner-border spinner-border-sm me-2" role="status"></span>
                                <span class="btn-text">
                                    <i class="bi bi-box-arrow-in-right"></i>
                                    Entrar no Sistema
                                </span>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Informações de acesso -->
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Tipos de Acesso</h6>
                        <small>
                            <strong>Master Admin:</strong> Controle total do sistema<br>
                            <strong>Admin Polo:</strong> Administração do polo específico<br>
                            <strong>Operador:</strong> Uso operacional do polo
                        </small>
                    </div>
                    
                    <!-- Links úteis -->
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            Sistema desenvolvido para a rede IMEP<br>
                            Versão 2.1 Multi-Tenant
                        </small>
                    </div>
                    
                    <!-- Ambiente -->
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Ambiente: <span class="badge bg-<?php echo (defined('ASAAS_ENVIRONMENT') && ASAAS_ENVIRONMENT === 'production') ? 'danger' : 'warning'; ?>">
                                <?php echo defined('ASAAS_ENVIRONMENT') ? strtoupper(ASAAS_ENVIRONMENT) : 'DEVELOPMENT'; ?>
                            </span>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Credenciais Demo -->
    <div class="demo-credentials">
        <div class="card">
            <div class="card-header">
                <small><i class="bi bi-person-gear"></i> Credenciais de Acesso</small>
            </div>
            <div class="card-body p-2">
                <small>
                    <strong>Master Admin:</strong><br>
                    admin@imepedu.com.br<br>
                    <code>admin123</code><br><br>
                    
                    <strong>Admin São Paulo:</strong><br>
                    admin.sp@imepedu.com.br<br>
                    <code>polo2024</code><br><br>
                    
                    <strong>Admin Rio de Janeiro:</strong><br>
                    admin.rj@imepedu.com.br<br>
                    <code>polo2024</code>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('senha');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
        
        // Loading state on form submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loading = document.querySelector('.loading');
            const btnText = document.querySelector('.btn-text');
            const submitBtn = this.querySelector('button[type="submit"]');
            
            loading.classList.add('show');
            btnText.innerHTML = 'Entrando...';
            submitBtn.disabled = true;
            
            // Se houver erro, restaurar botão após um tempo
            setTimeout(() => {
                if (document.querySelector('.alert-danger')) {
                    loading.classList.remove('show');
                    btnText.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Entrar no Sistema';
                    submitBtn.disabled = false;
                }
            }, 3000);
        });
        
        // Auto-focus no primeiro campo vazio
        document.addEventListener('DOMContentLoaded', function() {
            const email = document.getElementById('email');
            const senha = document.getElementById('senha');
            
            if (!email.value) {
                email.focus();
            } else {
                senha.focus();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter para submeter
            if (e.ctrlKey && e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
        
        // Animações de entrada
        window.addEventListener('load', function() {
            const container = document.querySelector('.login-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';
            container.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
        
        // Auto-preenchimento para demonstração
        document.addEventListener('keydown', function(e) {
            if (e.altKey && e.key === '1') {
                document.getElementById('email').value = 'admin@imepedu.com.br';
                document.getElementById('senha').value = 'admin123';
                e.preventDefault();
            } else if (e.altKey && e.key === '2') {
                document.getElementById('email').value = 'admin.sp@imepedu.com.br';
                document.getElementById('senha').value = 'polo2024';
                e.preventDefault();
            }
        });
        
        // Mostrar dica de atalhos
        console.log('%cDicas de Login:', 'color: #667eea; font-weight: bold; font-size: 14px;');
        console.log('Alt + 1: Preencher Master Admin');
        console.log('Alt + 2: Preencher Admin São Paulo');
        console.log('Ctrl + Enter: Submeter formulário');
    </script>
</body>
</html>