<?php
/**
 * Login Funcional - Versão Corrigida com Logout
 * Arquivo: login.php
 */

// Configuração básica
session_start();
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Incluir apenas arquivos essenciais
require_once "config.php";
require_once "auth.php";

$erro = "";
$sucesso = "";
$debug = [];

// Verificar mensagens de logout
if (isset($_GET['message'])) {
    $sucesso = $_GET['message'];
}

if (isset($_GET['error'])) {
    $erro = $_GET['error'];
}

// Debug: verificar se POST foi recebido
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $debug[] = "POST recebido";
    $debug[] = "Action: " . ($_POST["action"] ?? "não definido");
}

// Processar login
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "login") {
    
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";
    $lembrar = isset($_POST["lembrar"]);
    
    $debug[] = "Email: " . $email;
    $debug[] = "Senha: " . (empty($senha) ? "vazia" : "preenchida");
    
    if (empty($email) || empty($senha)) {
        $erro = "Email e senha são obrigatórios";
        $debug[] = "Erro: campos vazios";
    } else {
        try {
            $auth = new AuthSystem();
            $debug[] = "AuthSystem instanciado";
            
            $resultado = $auth->login($email, $senha, $lembrar);
            $debug[] = "Resultado do login: " . json_encode($resultado);
            
            if ($resultado["success"]) {
                $debug[] = "Login bem-sucedido, redirecionando...";
                
                // Redirecionamento seguro
                $redirect = $resultado["redirect"] ?? "index.php";
                header("Location: " . $redirect);
                exit;
            } else {
                $erro = $resultado["message"];
                $debug[] = "Erro de login: " . $erro;
            }
            
        } catch (Exception $e) {
            $erro = "Erro do sistema: " . $e->getMessage();
            $debug[] = "Exceção: " . $e->getMessage();
            $debug[] = "Arquivo: " . $e->getFile() . ":" . $e->getLine();
        }
    }
}

// Verificar se já está logado (apenas se não for POST)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    try {
        $auth = new AuthSystem();
        if ($auth->isLogado()) {
            $usuario = $auth->getUsuarioAtual();
            $redirect = ($usuario["tipo"] === "master") ? "admin_master.php" : "index.php";
            header("Location: " . $redirect);
            exit;
        }
    } catch (Exception $e) {
        $debug[] = "Erro ao verificar login existente: " . $e->getMessage();
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
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .login-card { background: rgba(255,255,255,0.95); border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .btn-login { background: linear-gradient(45deg, #667eea, #764ba2); border: none; }
        .debug-info { background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; font-size: 12px; margin-top: 10px; }
        .alert { border-radius: 8px; }
    </style>
</head>
<body class="d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-card p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-building display-4 text-primary"></i>
                        <h2>IMEP Split ASAAS</h2>
                        <p class="text-muted">Sistema de Gestão de Pagamentos</p>
                    </div>
                    
                    <!-- Mensagens -->
                    <?php if ($sucesso): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($sucesso); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($erro): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($erro); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Formulário -->
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   required 
                                   value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="senha" 
                                   name="senha" 
                                   required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="lembrar" name="lembrar">
                            <label class="form-check-label" for="lembrar">Lembrar-me</label>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-login text-white">
                                <i class="bi bi-box-arrow-in-right"></i> Entrar
                            </button>
                        </div>
                    </form>
                    
                    <!-- Credenciais de Teste 
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Credenciais de Teste</h6>
                        <small>
                            <strong>Email:</strong> admin@imepedu.com.br<br>
                            <strong>Senha:</strong> a123
                        </small>
                    </div>-->
                    
                    <!-- Debug Info (remover em produção) -->
                    <?php if (!empty($debug) && defined('DEBUG') && DEBUG): ?>
                        <div class="debug-info">
                            <strong>Debug Info:</strong><br>
                            <?php foreach ($debug as $info): ?>
                                <?php echo htmlspecialchars($info); ?><br>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Sistema Multi-Tenant v2.1<br>
                            <?php echo date("Y-m-d H:i:s"); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus no email se estiver vazio
        document.addEventListener("DOMContentLoaded", function() {
            const emailField = document.getElementById("email");
            if (!emailField.value) {
                emailField.focus();
            } else {
                document.getElementById("senha").focus();
            }
        });
        
        // Preencher credenciais de teste (Alt+T)
        document.addEventListener("keydown", function(e) {
            if (e.altKey && e.key === "t") {
                e.preventDefault();
                document.getElementById("email").value = "admin@imepedu.com.br";
                document.getElementById("senha").value = "admin123";
                document.getElementById("senha").focus();
            }
        });
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener("DOMContentLoaded", function() {
            const alerts = document.querySelectorAll('.alert-success, .alert-info');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>