<?php
/**
 * Interface Web para Configurações ASAAS
 * Arquivo: config_interface.php
 */

require_once 'bootstrap.php';

// Verificar autenticação
if (!$auth || !$auth->isLogado()) {
    safeRedirect('login.php');
}

$usuario = $auth->getUsuarioAtual();

// Verificar se tem permissão para configurar
if (!$auth->isMaster() && !$auth->isAdminPolo()) {
    showError('Acesso negado. Você não tem permissão para acessar as configurações.', 'Acesso Negado');
    exit;
}

$message = '';
$messageType = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_config':
                $poloId = (int)$_POST['polo_id'];
                
                // Verificar permissão para este polo
                if (!$auth->isMaster() && $poloId !== $usuario['polo_id']) {
                    throw new Exception('Você não tem permissão para configurar este polo');
                }
                
                $config = [
                    'environment' => $_POST['environment'],
                    'production_key' => trim($_POST['production_key'] ?? ''),
                    'sandbox_key' => trim($_POST['sandbox_key'] ?? ''),
                    'webhook_token' => trim($_POST['webhook_token'] ?? '')
                ];
                
                $configManager->updateAsaasConfig($poloId, $config);
                $message = 'Configurações atualizadas com sucesso!';
                $messageType = 'success';
                break;
                
            case 'test_config':
                $poloId = (int)$_POST['polo_id'];
                $environment = $_POST['test_environment'] ?? null;
                
                if (!$auth->isMaster() && $poloId !== $usuario['polo_id']) {
                    throw new Exception('Você não tem permissão para testar este polo');
                }
                
                $result = $configManager->testAsaasConfig($poloId, $environment);
                
                if ($result['success']) {
                    $message = 'Teste realizado com sucesso: ' . $result['message'];
                    $messageType = 'success';
                } else {
                    $message = 'Falha no teste: ' . $result['message'];
                    $messageType = 'danger';
                }
                break;
                
            case 'create_polo':
                if (!$auth->isMaster()) {
                    throw new Exception('Apenas Master Admin pode criar polos');
                }
                
                $dados = [
                    'nome' => trim($_POST['nome']),
                    'codigo' => strtoupper(trim($_POST['codigo'])),
                    'cidade' => trim($_POST['cidade']),
                    'estado' => strtoupper(trim($_POST['estado'])),
                    'endereco' => trim($_POST['endereco'] ?? ''),
                    'telefone' => trim($_POST['telefone'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'asaas_environment' => $_POST['environment'] ?? 'sandbox'
                ];
                
                $poloId = $configManager->createPolo($dados);
                $message = 'Polo criado com sucesso! ID: ' . $poloId;
                $messageType = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = 'Erro: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Obter lista de polos baseado na permissão do usuário
if ($auth->isMaster()) {
    $polos = $configManager->listarPolos(true);
} else {
    // Admin de polo vê apenas seu polo
    $polos = [$configManager->getPoloConfig($usuario['polo_id'])];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações ASAAS - Sistema IMEP Split</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .config-card {
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .config-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .api-key-input {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        
        .masked-key {
            background: #f8f9fa;
            border: 1px dashed #dee2e6;
            padding: 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #6c757d;
        }
        
        .environment-badge {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        
        .test-result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        
        .test-result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .test-result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
        }
        
        .polo-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="bi bi-gear-fill"></i> Configurações ASAAS</h2>
                        <p class="text-muted mb-0">
                            Gerencie as configurações de API do ASAAS
                            <?php if (!$auth->isMaster()): ?>
                                - Polo: <?php echo htmlspecialchars($usuario['polo_nome']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo $auth->isMaster() ? 'admin_master.php' : 'index.php'; ?>" 
                           class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                        
                        <?php if ($auth->isMaster()): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPoloModal">
                            <i class="bi bi-plus-circle"></i> Novo Polo
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mensagens -->
        <?php if ($message): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Polos -->
        <?php foreach ($polos as $polo): ?>
        <?php 
        $stats = $configManager->getPoloStats($polo['id']); 
        $hasProductionKey = !empty($polo['asaas_production_api_key']);
        $hasSandboxKey = !empty($polo['asaas_sandbox_api_key']);
        ?>
        
        <div class="row mb-4">
            <div class="col-12">
                <div class="card config-card">
                    <!-- Header do Polo -->
                    <div class="polo-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">
                                    <i class="bi bi-building"></i>
                                    <?php echo htmlspecialchars($polo['nome']); ?>
                                </h4>
                                <p class="mb-0 opacity-75">
                                    <i class="bi bi-geo-alt"></i>
                                    <?php echo htmlspecialchars($polo['cidade']); ?>, <?php echo htmlspecialchars($polo['estado']); ?>
                                    - Código: <?php echo htmlspecialchars($polo['codigo']); ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <span class="environment-badge badge bg-<?php echo $polo['asaas_environment'] === 'production' ? 'danger' : 'warning'; ?>">
                                    <?php echo strtoupper($polo['asaas_environment']); ?>
                                </span>
                                <span class="badge bg-<?php echo $polo['is_active'] ? 'success' : 'secondary'; ?> ms-2">
                                    <?php echo $polo['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="row">
                            <!-- Estatísticas -->
                            <div class="col-lg-3">
                                <div class="stats-card p-3 mb-3">
                                    <h6><i class="bi bi-bar-chart"></i> Estatísticas</h6>
                                    <div class="row text-center mt-4">
                                        <div class="col-6">
                                            <div class="h5 mb-0"><?php echo $stats['usuarios_ativos']; ?></div>
                                            <small>Usuários</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="h5 mb-0"><?php echo $stats['clientes']; ?></div>
                                            <small>Clientes</small>
                                        </div>
                                        <div class="col-6 mt-2">
                                            <div class="h5 mb-0"><?php echo $stats['wallet_ids']; ?></div>
                                            <small>Wallet IDs</small>
                                        </div>
                                        <div class="col-6 mt-2">
                                            <div class="h5 mb-0"><?php echo $stats['pagamentos_recebidos']; ?></div>
                                            <small>Pagamentos</small>
                                        </div>
                                    </div>
                                    <hr class="my-2">
                                    <div class="text-center">
                                        <div class="h6 mb-0">R$ <?php echo number_format($stats['valor_total'], 2, ',', '.'); ?></div>
                                        <small>Total Recebido</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Configurações -->
                            <div class="col-lg-9">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_config">
                                    <input type="hidden" name="polo_id" value="<?php echo $polo['id']; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6><i class="bi bi-sliders"></i> Configurações Gerais</h6>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Ambiente Ativo</label>
                                                <select class="form-select" name="environment" onchange="toggleEnvironment(<?php echo $polo['id']; ?>, this.value)">
                                                    <option value="sandbox" <?php echo $polo['asaas_environment'] === 'sandbox' ? 'selected' : ''; ?>>
                                                        Sandbox (Testes)
                                                    </option>
                                                    <option value="production" <?php echo $polo['asaas_environment'] === 'production' ? 'selected' : ''; ?>>
                                                        Produção (Vendas Reais)
                                                    </option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Token do Webhook (Opcional)</label>
                                                <input type="text" 
                                                       class="form-control api-key-input" 
                                                       name="webhook_token" 
                                                       value="<?php echo htmlspecialchars($polo['asaas_webhook_token'] ?? ''); ?>"
                                                       placeholder="Token para validação de webhooks">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6><i class="bi bi-key"></i> API Keys</h6>
                                            
                                            <!-- API Key Sandbox -->
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    API Key Sandbox
                                                    <?php if ($hasSandboxKey): ?>
                                                        <i class="bi bi-check-circle-fill text-success" title="Configurada"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-exclamation-triangle-fill text-warning" title="Não configurada"></i>
                                                    <?php endif; ?>
                                                </label>
                                                
                                                <?php if ($hasSandboxKey): ?>
                                                    <div class="masked-key mb-2">
                                                        <?php echo substr($polo['asaas_sandbox_api_key'], 0, 20) . '...' . substr($polo['asaas_sandbox_api_key'], -8); ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary float-end" 
                                                                onclick="toggleApiKeyEdit('sandbox_<?php echo $polo['id']; ?>')">
                                                            <i class="bi bi-pencil"></i> Alterar
                                                        </button>
                                                    </div>
                                                    <input type="password" 
                                                           class="form-control api-key-input" 
                                                           id="sandbox_<?php echo $polo['id']; ?>"
                                                           name="sandbox_key" 
                                                           style="display: none;"
                                                           placeholder="$aact_YTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1...">
                                                <?php else: ?>
                                                    <input type="password" 
                                                           class="form-control api-key-input" 
                                                           name="sandbox_key" 
                                                           placeholder="$aact_YTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1...">
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- API Key Production -->
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    API Key Produção
                                                    <?php if ($hasProductionKey): ?>
                                                        <i class="bi bi-check-circle-fill text-success" title="Configurada"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-exclamation-triangle-fill text-warning" title="Não configurada"></i>
                                                    <?php endif; ?>
                                                    <span class="badge bg-danger ms-2">Cuidado!</span>
                                                </label>
                                                
                                                <?php if ($hasProductionKey): ?>
                                                    <div class="masked-key mb-2">
                                                        <?php echo substr($polo['asaas_production_api_key'], 0, 20) . '...' . substr($polo['asaas_production_api_key'], -8); ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger float-end" 
                                                                onclick="toggleApiKeyEdit('production_<?php echo $polo['id']; ?>')">
                                                            <i class="bi bi-pencil"></i> Alterar
                                                        </button>
                                                    </div>
                                                    <input type="password" 
                                                           class="form-control api-key-input" 
                                                           id="production_<?php echo $polo['id']; ?>"
                                                           name="production_key" 
                                                           style="display: none;"
                                                           placeholder="$aact_MTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1...">
                                                <?php else: ?>
                                                    <input type="password" 
                                                           class="form-control api-key-input" 
                                                           name="production_key" 
                                                           placeholder="$aact_MTU5YjRlZmI2N2J4NzMzNmNlNzMwNDdlNzE1...">
                                                <?php endif; ?>
                                                
                                                <small class="text-danger">
                                                    <i class="bi bi-shield-exclamation"></i>
                                                    Esta chave será usada para transações reais com dinheiro!
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Botões de Ação -->
                                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-outline-info" 
                                                    onclick="testConfig(<?php echo $polo['id']; ?>, 'sandbox')"
                                                    <?php echo $hasSandboxKey ? '' : 'disabled'; ?>>
                                                <i class="bi bi-play-circle"></i> Testar Sandbox
                                            </button>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="testConfig(<?php echo $polo['id']; ?>, 'production')"
                                                    <?php echo $hasProductionKey ? '' : 'disabled'; ?>>
                                                <i class="bi bi-play-circle"></i> Testar Produção
                                            </button>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle"></i> Salvar Configurações
                                        </button>
                                    </div>
                                </form>
                                
                                <!-- Resultado dos Testes -->
                                <div id="testResult_<?php echo $polo['id']; ?>" class="test-result"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($polos)): ?>
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-building display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">Nenhum polo encontrado</h4>
                    <p class="text-muted">
                        <?php if ($auth->isMaster()): ?>
                            Clique em "Novo Polo" para criar o primeiro polo.
                        <?php else: ?>
                            Entre em contato com o administrador para configurar seu polo.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal Criar Polo (apenas para master) -->
    <?php if ($auth->isMaster()): ?>
    <div class="modal fade" id="createPoloModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Criar Novo Polo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_polo">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nome do Polo *</label>
                                    <input type="text" class="form-control" name="nome" required
                                           placeholder="IMEP - Polo São Paulo">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Código do Polo *</label>
                                    <input type="text" class="form-control" name="codigo" required
                                           placeholder="POLO_SP_001" style="text-transform: uppercase;">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Cidade *</label>
                                    <input type="text" class="form-control" name="cidade" required
                                           placeholder="São Paulo">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Estado *</label>
                                    <select class="form-select" name="estado" required>
                                        <option value="">Selecione...</option>
                                        <option value="SP">São Paulo</option>
                                        <option value="RJ">Rio de Janeiro</option>
                                        <option value="MG">Minas Gerais</option>
                                        <option value="PR">Paraná</option>
                                        <option value="SC">Santa Catarina</option>
                                        <option value="RS">Rio Grande do Sul</option>
                                        <option value="BA">Bahia</option>
                                        <option value="GO">Goiás</option>
                                        <option value="DF">Distrito Federal</option>
                                        <!-- Adicionar mais estados conforme necessário -->
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email"
                                           placeholder="polo@imepedu.com.br">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" class="form-control" name="telefone"
                                           placeholder="(11) 99999-9999">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Endereço</label>
                                    <textarea class="form-control" name="endereco" rows="3"
                                              placeholder="Rua das Flores, 123 - Centro"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Ambiente Inicial</label>
                                    <select class="form-select" name="environment">
                                        <option value="sandbox">Sandbox (Recomendado)</option>
                                        <option value="production">Produção</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Dica:</strong> Após criar o polo, você poderá configurar as API Keys do ASAAS 
                            e criar usuários administrativos para gerenciá-lo.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Criar Polo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle edição de API Key
        function toggleApiKeyEdit(fieldId) {
            const input = document.getElementById(fieldId);
            const maskedDiv = input.previousElementSibling;
            
            if (input.style.display === 'none') {
                maskedDiv.style.display = 'none';
                input.style.display = 'block';
                input.focus();
            } else {
                maskedDiv.style.display = 'block';
                input.style.display = 'none';
            }
        }
        
        // Testar configuração
        function testConfig(poloId, environment) {
            const button = event.target;
            const originalText = button.innerHTML;
            const resultDiv = document.getElementById('testResult_' + poloId);
            
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Testando...';
            button.disabled = true;
            
            // Criar form temporário
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            form.innerHTML = `
                <input name="action" value="test_config">
                <input name="polo_id" value="${poloId}">
                <input name="test_environment" value="${environment}">
            `;
            
            document.body.appendChild(form);
            
            // Usar fetch para não recarregar a página
            const formData = new FormData(form);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Extrair mensagem de sucesso/erro do HTML retornado
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const alert = doc.querySelector('.alert');
                
                if (alert) {
                    const isSuccess = alert.classList.contains('alert-success');
                    const message = alert.textContent.trim();
                    
                    resultDiv.className = 'test-result ' + (isSuccess ? 'success' : 'error');
                    resultDiv.innerHTML = `
                        <i class="bi bi-${isSuccess ? 'check-circle' : 'exclamation-triangle'}"></i>
                        ${message}
                    `;
                    resultDiv.style.display = 'block';
                    
                    // Auto-hide após 5 segundos
                    setTimeout(() => {
                        resultDiv.style.display = 'none';
                    }, 5000);
                }
            })
            .catch(error => {
                resultDiv.className = 'test-result error';
                resultDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle"></i>
                    Erro na conexão: ${error.message}
                `;
                resultDiv.style.display = 'block';
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
                document.body.removeChild(form);
            });
        }
        
        // Toggle ambiente
        function toggleEnvironment(poloId, environment) {
            const card = document.querySelector(`input[name="polo_id"][value="${poloId}"]`).closest('.card');
            const badge = card.querySelector('.environment-badge');
            
            badge.textContent = environment.toUpperCase();
            badge.className = 'environment-badge badge bg-' + (environment === 'production' ? 'danger' : 'warning');
            
            // Mostrar aviso se for produção
            if (environment === 'production') {
                if (!confirm('ATENÇÃO: Você está alterando para PRODUÇÃO!\n\nEste ambiente processará pagamentos REAIS com dinheiro real.\n\nTem certeza que deseja continuar?')) {
                    // Reverter para sandbox
                    const select = event.target;
                    select.value = 'sandbox';
                    badge.textContent = 'SANDBOX';
                    badge.className = 'environment-badge badge bg-warning';
                }
            }
        }
        
        // Validação de API Key
        function validateApiKey(input) {
            const value = input.value.trim();
            const isValid = /^\$aact_[a-zA-Z0-9_]+$/.test(value);
            
            if (value && !isValid) {
                input.classList.add('is-invalid');
                
                let feedback = input.parentNode.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    input.parentNode.appendChild(feedback);
                }
                feedback.textContent = 'Formato inválido. Deve começar com $aact_';
            } else {
                input.classList.remove('is-invalid');
                const feedback = input.parentNode.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.remove();
                }
            }
        }
        
        // Inicialização da página
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar validação aos campos de API Key
            const apiKeyInputs = document.querySelectorAll('.api-key-input[name*="key"]');
            apiKeyInputs.forEach(input => {
                input.addEventListener('blur', () => validateApiKey(input));
                input.addEventListener('input', () => {
                    // Remover feedback de erro enquanto digita
                    input.classList.remove('is-invalid');
                    const feedback = input.parentNode.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.remove();
                    }
                });
            });
            
            // Auto uppercase no código do polo
            const codigoInput = document.querySelector('input[name="codigo"]');
            if (codigoInput) {
                codigoInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                });
            }
            
            // Validação dos formulários
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const environmentSelect = this.querySelector('select[name="environment"]');
                    const productionKeyInput = this.querySelector('input[name="production_key"]');
                    
                    // Verificar se está tentando usar produção sem API key
                    if (environmentSelect && environmentSelect.value === 'production') {
                        if (!productionKeyInput || !productionKeyInput.value.trim()) {
                            e.preventDefault();
                            alert('ERRO: Não é possível usar ambiente de PRODUÇÃO sem configurar a API Key de produção!');
                            return false;
                        }
                        
                        // Confirmação final para produção
                        if (!confirm('CONFIRMAÇÃO FINAL:\n\nVocê está salvando configurações de PRODUÇÃO!\n\nEste polo processará pagamentos REAIS.\n\nConfirma?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                    
                    // Validar formato das API Keys antes de enviar
                    const apiKeys = this.querySelectorAll('.api-key-input[name*="key"]');
                    let hasInvalidKey = false;
                    
                    apiKeys.forEach(input => {
                        const value = input.value.trim();
                        if (value && !/^\$aact_[a-zA-Z0-9_]+$/.test(value)) {
                            validateApiKey(input);
                            hasInvalidKey = true;
                        }
                    });
                    
                    if (hasInvalidKey) {
                        e.preventDefault();
                        alert('Por favor, corrija os erros nos campos de API Key antes de continuar.');
                        return false;
                    }
                    
                    // Mostrar loading no botão
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Salvando...';
                        submitBtn.disabled = true;
                        
                        // Restaurar botão se houver erro (timeout de segurança)
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 5000);
                    }
                });
            });
        });
        
        // Função para mostrar tooltips informativos
        function showTooltip(element, message) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip bs-tooltip-top show';
            tooltip.innerHTML = `
                <div class="tooltip-arrow"></div>
                <div class="tooltip-inner">${message}</div>
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = element.getBoundingClientRect();
            tooltip.style.position = 'fixed';
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
            tooltip.style.zIndex = '9999';
            
            // Remover após 3 segundos
            setTimeout(() => {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
            }, 3000);
        }
        
        // Copiar informações úteis
        function copyWebhookUrl(poloId) {
            const url = `${window.location.origin}/webhook.php`;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    showTooltip(event.target, 'URL copiada para a área de transferência!');
                });
            } else {
                // Fallback para navegadores mais antigos
                const textarea = document.createElement('textarea');
                textarea.value = url;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showTooltip(event.target, 'URL copiada!');
            }
        }
        
        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + S para salvar (primeira form visível)
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const firstForm = document.querySelector('form:not([style*="display: none"])');
                if (firstForm) {
                    firstForm.submit();
                }
            }
            
            // Esc para fechar modais
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    const modal = bootstrap.Modal.getInstance(openModal);
                    if (modal) {
                        modal.hide();
                    }
                }
            }
        });
        
        // Log para debug
        console.log('🔧 Interface de Configurações ASAAS carregada');
        console.log('💡 Dicas:');
        console.log('   Ctrl + S: Salvar configurações');
        console.log('   Esc: Fechar modal');
        
        // Verificar se há configurações pendentes
        document.addEventListener('DOMContentLoaded', function() {
            const unconfiguredPolos = document.querySelectorAll('.bi-exclamation-triangle-fill');
            if (unconfiguredPolos.length > 0) {
                console.warn(`⚠️ ${unconfiguredPolos.length} polo(s) com configuração incompleta`);
            }
        });
    </script>
</body>
</html>