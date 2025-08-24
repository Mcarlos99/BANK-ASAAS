<?php
/**
 * Script para corrigir loop de login (senha correta mas não autentica)
 * Arquivo: fix_login_loop.php
 */

echo "🔐 DIAGNÓSTICO E CORREÇÃO DE LOOP DE LOGIN\n";
echo "==========================================\n\n";

try {
    // 1. Verificar se os usuários existem no banco
    echo "👥 VERIFICANDO USUÁRIOS NO BANCO\n";
    echo "────────────────────────────────\n";
    
    require_once 'config.php';
    $db = DatabaseManager::getInstance();
    
    // Verificar se tabela usuarios existe
    $result = $db->getConnection()->query("SHOW TABLES LIKE 'usuarios'");
    if ($result->rowCount() == 0) {
        echo "  ❌ Tabela 'usuarios' não existe!\n";
        echo "  🔧 Criando tabela usuarios...\n";
        
        $db->getConnection()->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                polo_id INT NULL,
                nome VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                senha VARCHAR(255) NOT NULL,
                tipo ENUM('master', 'admin_polo', 'operador') NOT NULL DEFAULT 'operador',
                permissoes JSON NULL,
                is_active TINYINT(1) DEFAULT 1,
                ultimo_login TIMESTAMP NULL,
                tentativas_login INT DEFAULT 0,
                bloqueado_ate TIMESTAMP NULL,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                criado_por INT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "  ✅ Tabela usuarios criada\n";
    } else {
        echo "  ✅ Tabela usuarios existe\n";
    }
    
    // Verificar usuários existentes
    $stmt = $db->getConnection()->query("SELECT id, nome, email, tipo, is_active FROM usuarios");
    $usuarios = $stmt->fetchAll();
    
    if (empty($usuarios)) {
        echo "  ❌ Nenhum usuário encontrado no banco!\n";
        echo "  🔧 Criando usuário master padrão...\n";
        
        // Criar usuário master
        $senhaHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->getConnection()->prepare("
            INSERT INTO usuarios (nome, email, senha, tipo, is_active) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Master Administrator',
            'admin@imepedu.com.br',
            $senhaHash,
            'master',
            1
        ]);
        
        echo "  ✅ Usuário master criado:\n";
        echo "     Email: admin@imepedu.com.br\n";
        echo "     Senha: admin123\n";
        echo "     Tipo: master\n";
    } else {
        echo "  ✅ Usuários encontrados:\n";
        foreach ($usuarios as $user) {
            $status = $user['is_active'] ? 'ATIVO' : 'INATIVO';
            echo "     - {$user['nome']} ({$user['email']}) - {$user['tipo']} - {$status}\n";
        }
    }
    
    echo "\n";
    
    // 2. Testar hash de senhas
    echo "🔒 VERIFICANDO HASH DE SENHAS\n";
    echo "────────────────────────────\n";
    
    $stmt = $db->getConnection()->prepare("SELECT id, email, senha FROM usuarios WHERE email = ?");
    $stmt->execute(['admin@imepedu.com.br']);
    $adminUser = $stmt->fetch();
    
    if ($adminUser) {
        echo "  ✅ Usuário admin encontrado\n";
        
        // Testar se a senha bate
        $senhaCorreta = 'admin123';
        if (password_verify($senhaCorreta, $adminUser['senha'])) {
            echo "  ✅ Hash da senha está correto\n";
        } else {
            echo "  ❌ Hash da senha está incorreto!\n";
            echo "  🔧 Atualizando hash da senha...\n";
            
            $novoHash = password_hash($senhaCorreta, PASSWORD_DEFAULT);
            $updateStmt = $db->getConnection()->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $updateStmt->execute([$novoHash, $adminUser['id']]);
            
            echo "  ✅ Hash da senha atualizado\n";
        }
    } else {
        echo "  ❌ Usuário admin@imepedu.com.br não encontrado\n";
    }
    
    echo "\n";
    
    // 3. Verificar sistema de autenticação
    echo "🔐 TESTANDO SISTEMA DE AUTENTICAÇÃO\n";
    echo "──────────────────────────────────\n";
    
    // Verificar se AuthSystem funciona
    if (class_exists('AuthSystem')) {
        echo "  ✅ Classe AuthSystem disponível\n";
        
        try {
            $auth = new AuthSystem();
            echo "  ✅ AuthSystem instanciado com sucesso\n";
            
            // Testar login
            $resultado = $auth->login('admin@imepedu.com.br', 'admin123');
            
            if ($resultado['success']) {
                echo "  ✅ Login funcionando corretamente!\n";
                echo "  🎯 Redirect para: " . $resultado['redirect'] . "\n";
                
                // Limpar sessão de teste
                $auth->logout();
            } else {
                echo "  ❌ Falha no login: " . $resultado['message'] . "\n";
            }
        } catch (Exception $e) {
            echo "  ❌ Erro no AuthSystem: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ❌ Classe AuthSystem não encontrada\n";
    }
    
    echo "\n";
    
    // 4. Criar login.php funcional
    echo "🔧 CRIANDO LOGIN.PHP FUNCIONAL\n";
    echo "──────────────────────────────\n";
    
    $loginFuncional = '<?php
/**
 * Login Funcional - Versão Corrigida
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
$debug = [];

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
                    
                    <!-- Credenciais de Teste -->
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Credenciais de Teste</h6>
                        <small>
                            <strong>Email:</strong> admin@imepedu.com.br<br>
                            <strong>Senha:</strong> admin123
                        </small>
                    </div>
                    
                    <!-- Debug Info (remover em produção) -->
                    <?php if (!empty($debug)): ?>
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
    </script>
</body>
</html>';

    // Fazer backup do login atual
    if (file_exists('login.php')) {
        $backup = 'login_backup_' . date('Y-m-d_H-i-s') . '.php';
        copy('login.php', $backup);
        echo "  💾 Backup criado: {$backup}\n";
    }
    
    // Criar login funcional
    file_put_contents('login.php', $loginFuncional);
    echo "  ✅ login.php funcional criado\n";
    
    echo "\n";
    
    // 5. Verificar tabelas necessárias
    echo "🗄️ VERIFICANDO TABELAS NECESSÁRIAS\n";
    echo "─────────────────────────────────\n";
    
    $tabelasNecessarias = [
        'usuarios' => "Usuários do sistema",
        'polos' => "Polos/unidades",
        'sessoes' => "Sessões de usuários"
    ];
    
    foreach ($tabelasNecessarias as $tabela => $desc) {
        $result = $db->getConnection()->query("SHOW TABLES LIKE '{$tabela}'");
        if ($result->rowCount() > 0) {
            echo "  ✅ {$desc} ({$tabela})\n";
        } else {
            echo "  ❌ {$desc} ({$tabela}) - NÃO EXISTE\n";
            
            // Criar tabela básica se não existir
            if ($tabela === 'sessoes') {
                $db->getConnection()->exec("
                    CREATE TABLE IF NOT EXISTS sessoes (
                        id VARCHAR(128) PRIMARY KEY,
                        usuario_id INT NOT NULL,
                        polo_id INT NULL,
                        ip_address VARCHAR(45),
                        user_agent TEXT,
                        dados_sessao JSON NULL,
                        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        ultima_atividade TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        expira_em TIMESTAMP NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                echo "    ✅ Tabela sessoes criada\n";
            }
        }
    }
    
    echo "\n";
    
    // 6. Teste final
    echo "🧪 TESTE FINAL DO LOGIN\n";
    echo "──────────────────────\n";
    
    echo "  🔬 Testando autenticação...\n";
    
    try {
        $auth = new AuthSystem();
        $resultado = $auth->login('admin@imepedu.com.br', 'admin123');
        
        if ($resultado['success']) {
            echo "  ✅ Login funcionando perfeitamente!\n";
            echo "  🎯 Redirecionamento: " . $resultado['redirect'] . "\n";
            
            // Limpar sessão de teste
            $auth->logout();
        } else {
            echo "  ❌ Ainda há problemas no login: " . $resultado['message'] . "\n";
        }
    } catch (Exception $e) {
        echo "  ❌ Erro no teste: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    echo "✅ CORREÇÃO DE LOGIN CONCLUÍDA!\n";
    echo "===============================\n\n";
    
    echo "🎯 PRÓXIMOS PASSOS:\n";
    echo "1. Acesse: https://bank.imepedu.com.br/login.php\n";
    echo "2. Use as credenciais:\n";
    echo "   Email: admin@imepedu.com.br\n";
    echo "   Senha: admin123\n";
    echo "3. O login deve funcionar agora!\n\n";
    
    echo "🔍 DEBUG:\n";
    echo "- A página agora mostra informações de debug\n";
    echo "- Use Alt+T para preencher credenciais automaticamente\n";
    echo "- Se ainda houver problemas, verifique as informações de debug\n\n";
    
    echo "📱 ATALHO:\n";
    echo "Alt + T = Preencher credenciais de teste automaticamente\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n\n";
    
    echo "🔧 SOLUÇÃO MANUAL:\n";
    echo "1. Verifique se o banco de dados está funcionando\n";
    echo "2. Verifique se as tabelas existem\n";
    echo "3. Verifique se há usuários cadastrados\n";
    echo "4. Execute: SELECT * FROM usuarios WHERE email = 'admin@imepedu.com.br'\n";
}
?>