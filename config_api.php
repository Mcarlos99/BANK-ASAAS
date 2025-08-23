<?php
/**
 * Configuração Simples da API ASAAS
 * Arquivo: config_api.php
 * 
 * SUBSTITUA as chaves abaixo pelas suas chaves reais do ASAAS
 */

// ======================================
// CONFIGURAR SUAS CHAVES AQUI
// ======================================

// Sua API Key do ASAAS (Sandbox) - para testes
$ASAAS_SANDBOX_API_KEY = '$aact_prod_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjdmNDZhZTU1LWVjYTgtNDY0Mi1hOTg5LTY0NmMxNmM1ZTFkNzo6JGFhY2hfMWYzOTgxNjEtZWRhNy00ZjhhLTk5MGQtNGYwZjY2MzJmZTJk'; // SUBSTITUA AQUI

// Sua API Key do ASAAS (Produção) - para vendas reais  
$ASAAS_PRODUCTION_API_KEY = '$aact_prod_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjdmNDZhZTU1LWVjYTgtNDY0Mi1hOTg5LTY0NmMxNmM1ZTFkNzo6JGFhY2hfMWYzOTgxNjEtZWRhNy00ZjhhLTk5MGQtNGYwZjY2MzJmZTJk'; // SUBSTITUA AQUI

// Ambiente atual (sandbox ou production)
$ASAAS_ENVIRONMENT = 'production'; // Mude para 'production' quando for para venda real

// Token do Webhook (opcional)
$ASAAS_WEBHOOK_TOKEN = 'meu_token_secreto_123';

// ======================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// ======================================

$DB_HOST = 'localhost';
$DB_NAME = 'bankdb';
$DB_USER = 'bankuser';
$DB_PASS = 'lKVX4Ew0u7I89hAUuDCm';

// ======================================
// NÃO ALTERE DAQUI PARA BAIXO
// ======================================

// Definir constantes para o sistema usar
if (!defined('ASAAS_PRODUCTION_API_KEY')) {
    define('ASAAS_PRODUCTION_API_KEY', $ASAAS_PRODUCTION_API_KEY);
}

if (!defined('ASAAS_SANDBOX_API_KEY')) {
    define('ASAAS_SANDBOX_API_KEY', $ASAAS_SANDBOX_API_KEY);
}

if (!defined('ASAAS_ENVIRONMENT')) {
    define('ASAAS_ENVIRONMENT', $ASAAS_ENVIRONMENT);
}

if (!defined('ASAAS_WEBHOOK_TOKEN')) {
    define('ASAAS_WEBHOOK_TOKEN', $ASAAS_WEBHOOK_TOKEN);
}

if (!defined('DB_HOST')) {
    define('DB_HOST', $DB_HOST);
    define('DB_NAME', $DB_NAME);
    define('DB_USER', $DB_USER);
    define('DB_PASS', $DB_PASS);
    define('DB_CHARSET', 'utf8mb4');
}

if (!defined('LOG_LEVEL')) {
    define('LOG_LEVEL', 'INFO');
    define('LOG_RETENTION_DAYS', 30);
    define('WEBHOOK_TIMEOUT', 30);
}

// Função para verificar se as chaves estão configuradas
function verificarConfiguracao() {
    $problemas = [];
    
    if (ASAAS_SANDBOX_API_KEY === '$aact_YTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1') {
        $problemas[] = "Configure sua API Key de Sandbox";
    }
    
    if (ASAAS_PRODUCTION_API_KEY === '$aact_MTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1') {
        $problemas[] = "Configure sua API Key de Produção";
    }
    
    return $problemas;
}

// ======================================
// INTERFACE DE CONFIGURAÇÃO
// ======================================

// Só exibir a interface se este arquivo for acessado diretamente
if (basename($_SERVER['PHP_SELF']) === 'config_api.php') {
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração ASAAS API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="bi bi-gear"></i> Configuração do Sistema ASAAS</h3>
                    </div>
                    <div class="card-body">
                        
                        <?php
                        $problemas = verificarConfiguracao();
                        if (!empty($problemas)) {
                            echo "<div class='alert alert-warning'>";
                            echo "<strong>⚠️ Configuração Necessária:</strong><br>";
                            foreach ($problemas as $problema) {
                                echo "• {$problema}<br>";
                            }
                            echo "Edite o arquivo <code>config_api.php</code> e configure suas chaves do ASAAS.";
                            echo "</div>";
                        }
                        ?>
                        
                        <h5>🔧 Status da Configuração</h5>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Status</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>API Key Sandbox</td>
                                    <?php if (ASAAS_SANDBOX_API_KEY !== '$aact_YTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1'): ?>
                                        <td class="text-success">✅ Configurada</td>
                                        <td><code><?php echo substr(ASAAS_SANDBOX_API_KEY, 0, 20) . '...'; ?></code></td>
                                    <?php else: ?>
                                        <td class="text-danger">❌ Não configurada</td>
                                        <td>Padrão (inválida)</td>
                                    <?php endif; ?>
                                </tr>
                                
                                <tr>
                                    <td>API Key Produção</td>
                                    <?php if (ASAAS_PRODUCTION_API_KEY !== '$aact_MTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1'): ?>
                                        <td class="text-success">✅ Configurada</td>
                                        <td><code><?php echo substr(ASAAS_PRODUCTION_API_KEY, 0, 20) . '...'; ?></code></td>
                                    <?php else: ?>
                                        <td class="text-warning">⚠️ Não configurada</td>
                                        <td>Padrão (inválida)</td>
                                    <?php endif; ?>
                                </tr>
                                
                                <tr>
                                    <td>Ambiente Atual</td>
                                    <td class="text-info">ℹ️ <?php echo strtoupper(ASAAS_ENVIRONMENT); ?></td>
                                    <td><?php echo ASAAS_ENVIRONMENT; ?></td>
                                </tr>
                                
                                <tr>
                                    <td>Banco de Dados</td>
                                    <?php
                                    try {
                                        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                                        echo "<td class='text-success'>✅ Conectado</td>";
                                        echo "<td>" . DB_NAME . " em " . DB_HOST . "</td>";
                                    } catch (Exception $e) {
                                        echo "<td class='text-danger'>❌ Erro</td>";
                                        echo "<td>" . $e->getMessage() . "</td>";
                                    }
                                    ?>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>📋 Como Configurar:</h6>
                                    </div>
                                    <div class="card-body">
                                        <ol>
                                            <li><strong>Acesse:</strong> <a href="https://sandbox.asaas.com" target="_blank">Painel ASAAS</a></li>
                                            <li><strong>Login</strong> na sua conta</li>
                                            <li><strong>Vá em:</strong> Menu → Integrações → API</li>
                                            <li><strong>Copie</strong> sua API Key (começa com $aact_...)</li>
                                            <li><strong>Edite</strong> o arquivo config_api.php</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>🔗 Links Úteis:</h6>
                                    </div>
                                    <div class="card-body">
                                        <ul>
                                            <li><a href="https://sandbox.asaas.com" target="_blank">Painel Sandbox</a></li>
                                            <li><a href="https://www.asaas.com" target="_blank">Painel Produção</a></li>
                                            <li><a href="https://docs.asaas.com" target="_blank">Documentação</a></li>
                                            <li><a href="index.php">Voltar ao Sistema</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="index.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> Voltar ao Sistema
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}
?>