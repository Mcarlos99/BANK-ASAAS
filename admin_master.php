<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Admin - Sistema IMEP Split ASAAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .master-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 20px;
            border-radius: 0 0 15px 15px;
            margin-bottom: 20px;
        }
        
        .polo-card {
            border-left: 4px solid #3498db;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .polo-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .polo-card.production {
            border-left-color: #e74c3c;
        }
        
        .polo-card.inactive {
            opacity: 0.7;
            border-left-color: #95a5a6;
        }
        
        .stats-overview {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .quick-action-card {
            background: linear-gradient(45deg, #16a085, #1abc9c);
            color: white;
            border: none;
            border-radius: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .quick-action-card:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(26,188,156,0.3);
        }
        
        .nav-pills .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 10px;
            margin: 5px 0;
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .nav-pills .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .user-type-badge {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            border: none;
            border-radius: 20px;
            padding: 8px 15px;
            font-weight: bold;
        }
        
        .section {
            display: none;
        }
        
        .section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .activity-item {
            border-left: 3px solid #3498db;
            background: #f8f9fa;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 0 8px 8px 0;
        }
        
        .metric-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
    </style>
</head>
<body class="bg-light">
    <?php
  
require_once 'bootstrap.php'; 
  
    // Verificar se é Master Admin
    $auth->requireLogin();
    if (!$auth->isMaster()) {
        header('Location: index.php');
        exit;
    }
    
    $usuario = $auth->getUsuarioAtual();
    $configManager = new ConfigManager();
    
    // Obter dados para o dashboard
    $polos = $configManager->listarPolos(true);
    $totalPolos = count($polos);
    $polosAtivos = count(array_filter($polos, fn($p) => $p['is_active']));
    
    // Estatísticas globais
    $stats = SystemStats::getGeneralStats();
    ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="d-flex flex-column p-3 text-white h-100">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-check display-4"></i>
                        <h5 class="mt-2">Master Admin</h5>
                        <span class="badge user-type-badge">
                            <i class="bi bi-crown"></i> ADMIN GERAL
                        </span>
                    </div>
                    
                    <hr class="my-3">
                    
                    <ul class="nav nav-pills flex-column mb-auto">
                        <li class="nav-item">
                            <a href="#" class="nav-link active" data-section="dashboard">
                                <i class="bi bi-speedometer2"></i> Dashboard Geral
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-link" data-section="polos">
                                <i class="bi bi-building"></i> Gerenciar Polos
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-link" data-section="usuarios">
                                <i class="bi bi-people"></i> Usuários do Sistema
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-link" data-section="configuracoes">
                                <i class="bi bi-gear"></i> Configurações APIs
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-link" data-section="relatorios">
                                <i class="bi bi-graph-up"></i> Relatórios Globais
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-link" data-section="auditoria">
                                <i class="bi bi-shield-exclamation"></i> Auditoria
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-link" data-section="sistema">
                                <i class="bi bi-tools"></i> Manutenção
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="my-3">
                    
                    <div class="text-center">
                        <small class="text-white-50">
                            Conectado como:<br>
                            <strong><?php echo htmlspecialchars($usuario['nome']); ?></strong>
                        </small>
                        <div class="mt-2">
                            <a href="api.php?action=logout" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-box-arrow-right"></i> Sair
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Conteúdo Principal -->
            <div class="col-md-9 col-lg-10">
                <!-- Header Master -->
                <div class="master-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2><i class="bi bi-shield-fill"></i> Painel Master Admin</h2>
                            <p class="mb-0">Controle total do sistema IMEP Split ASAAS</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="badge bg-light text-dark me-2">
                                <i class="bi bi-calendar"></i>
                                <?php echo date('d/m/Y H:i'); ?>
                            </div>
                            <div class="badge bg-warning text-dark">
                                <i class="bi bi-building"></i>
                                <?php echo $totalPolos; ?> Polos
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="container-fluid">
                    <!-- Dashboard Geral -->
                    <div id="dashboard-section" class="section active">
                        <!-- Estatísticas Overview -->
                        <div class="stats-overview">
                            <h4><i class="bi bi-bar-chart-fill"></i> Visão Geral do Sistema</h4>
                            <div class="row text-center mt-4">
                                <div class="col-md-2">
                                    <div class="metric-value"><?php echo $totalPolos; ?></div>
                                    <small>Polos Total</small>
                                </div>
                                <div class="col-md-2">
                                    <div class="metric-value text-success"><?php echo $polosAtivos; ?></div>
                                    <small>Polos Ativos</small>
                                </div>
                                <div class="col-md-2">
                                    <div class="metric-value"><?php echo number_format($stats['total_customers']); ?></div>
                                    <small>Clientes</small>
                                </div>
                                <div class="col-md-2">
                                    <div class="metric-value"><?php echo number_format($stats['total_payments']); ?></div>
                                    <small>Pagamentos</small>
                                </div>
                                <div class="col-md-2">
                                    <div class="metric-value"><?php echo number_format($stats['total_wallet_ids']); ?></div>
                                    <small>Wallet IDs</small>
                                </div>
                                <div class="col-md-2">
                                    <div class="metric-value text-warning">R$ <?php echo number_format($stats['total_value'], 0, ',', '.'); ?></div>
                                    <small>Total Processado</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ações Rápidas -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card quick-action-card text-center p-4" onclick="showSection('polos')">
                                    <i class="bi bi-plus-circle display-4"></i>
                                    <h5 class="mt-2">Novo Polo</h5>
                                    <p class="mb-0">Criar nova unidade</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card quick-action-card text-center p-4" onclick="showSection('usuarios')">
                                    <i class="bi bi-person-plus display-4"></i>
                                    <h5 class="mt-2">Novo Usuário</h5>
                                    <p class="mb-0">Cadastrar administrador</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card quick-action-card text-center p-4" onclick="showSection('configuracoes')">
                                    <i class="bi bi-gear-wide-connected display-4"></i>
                                    <h5 class="mt-2">Configurar APIs</h5>
                                    <p class="mb-0">Gerenciar credenciais</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card quick-action-card text-center p-4" onclick="showSection('relatorios')">
                                    <i class="bi bi-graph-up-arrow display-4"></i>
                                    <h5 class="mt-2">Relatórios</h5>
                                    <p class="mb-0">Análises globais</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Lista de Polos -->
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><i class="bi bi-building"></i> Status dos Polos</h5>
                                        <button class="btn btn-primary btn-sm" onclick="showSection('polos')">
                                            <i class="bi bi-plus"></i> Gerenciar
                                        </button>
                                    </div>
                                    <div class="card-body p-0">
                                        <?php foreach (array_slice($polos, 0, 5) as $polo): ?>
                                        <div class="polo-card card m-3 <?php echo $polo['asaas_environment'] === 'production' ? 'production' : ''; ?> <?php echo !$polo['is_active'] ? 'inactive' : ''; ?>">
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col-md-6">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($polo['nome']); ?></h6>
                                                        <small class="text-muted">
                                                            <i class="bi bi-geo-alt"></i>
                                                            <?php echo htmlspecialchars($polo['cidade']); ?>, <?php echo htmlspecialchars($polo['estado']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-3 text-center">
                                                        <span class="badge bg-<?php echo $polo['asaas_environment'] === 'production' ? 'danger' : 'warning'; ?>">
                                                            <?php echo strtoupper($polo['asaas_environment']); ?>
                                                        </span>
                                                        <br>
                                                        <small><?php echo $polo['total_usuarios']; ?> usuários</small>
                                                    </div>
                                                    <div class="col-md-3 text-end">
                                                        <span class="badge bg-<?php echo $polo['is_active'] ? 'success' : 'secondary'; ?>">
                                                            <?php echo $polo['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                                        </span>
                                                        <br>
                                                        <small><?php echo number_format($polo['total_pagamentos']); ?> pagamentos</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($polos) > 5): ?>
                                        <div class="text-center p-3">
                                            <button class="btn btn-outline-primary" onclick="showSection('polos')">
                                                Ver todos os <?php echo $totalPolos; ?> polos
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4">
                                <!-- Atividade Recente -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-activity"></i> Atividade Recente</h5>
                                    </div>
                                    <div class="card-body" id="recent-activity">
                                        <div class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Carregando...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Sistema Status -->
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h5><i class="bi bi-cpu"></i> Status do Sistema</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <small>Memória PHP</small>
                                            <div class="progress">
                                                <div class="progress-bar bg-info" style="width: 45%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <small>Espaço em Disco</small>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" style="width: 75%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <small>Conexões DB</small>
                                            <div class="progress">
                                                <div class="progress-bar bg-warning" style="width: 30%"></div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="d-grid">
                                            <button class="btn btn-outline-primary btn-sm" onclick="runHealthCheck()">
                                                <i class="bi bi-shield-check"></i> Verificar Sistema
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Gerenciar Polos -->
                    <div id="polos-section" class="section">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3><i class="bi bi-building"></i> Gerenciar Polos</h3>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPoloModal">
                                <i class="bi bi-plus-circle"></i> Novo Polo
                            </button>
                        </div>
                        
                        <div id="polos-list">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Usuários -->
                    <div id="usuarios-section" class="section">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3><i class="bi bi-people"></i> Usuários do Sistema</h3>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                <i class="bi bi-person-plus"></i> Novo Usuário
                            </button>
                        </div>
                        
                        <div id="users-list">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Configurações -->
                    <div id="configuracoes-section" class="section">
                        <h3><i class="bi bi-gear"></i> Configurações de APIs por Polo</h3>
                        <p class="text-muted mb-4">Gerencie as credenciais ASAAS de cada polo individualmente</p>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Como Master Admin:</strong> Você pode configurar as APIs de qualquer polo. 
                            As configurações são específicas por unidade e ambiente.
                        </div>
                        
                        <div class="text-center">
                            <a href="config_interface.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-gear-wide-connected"></i>
                                Acessar Painel de Configurações
                            </a>
                        </div>
                    </div>
                    
                    <!-- Seção Relatórios -->
                    <div id="relatorios-section" class="section">
                        <h3><i class="bi bi-graph-up"></i> Relatórios Globais</h3>
                        <p class="text-muted mb-4">Relatórios consolidados de todos os polos</p>
                        
                        <div id="reports-content">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Auditoria -->
                    <div id="auditoria-section" class="section">
                        <h3><i class="bi bi-shield-exclamation"></i> Auditoria do Sistema</h3>
                        <p class="text-muted mb-4">Log de todas as ações realizadas no sistema</p>
                        
                        <div id="audit-content">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção Manutenção -->
                    <div id="sistema-section" class="section">
                        <h3><i class="bi bi-tools"></i> Manutenção do Sistema</h3>
                        <p class="text-muted mb-4">Ferramentas de manutenção e monitoramento</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-database"></i> Banco de Dados</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-info" onclick="runDatabaseCheck()">
                                                <i class="bi bi-search"></i> Verificar Integridade
                                            </button>
                                            <button class="btn btn-outline-warning" onclick="cleanOldData()">
                                                <i class="bi bi-trash"></i> Limpar Dados Antigos
                                            </button>
                                            <button class="btn btn-outline-success" onclick="backupDatabase()">
                                                <i class="bi bi-download"></i> Criar Backup
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-shield"></i> Segurança</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-primary" onclick="checkSecurityStatus()">
                                                <i class="bi bi-shield-check"></i> Status de Segurança
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="viewFailedLogins()">
                                                <i class="bi bi-exclamation-triangle"></i> Tentativas Falharam
                                            </button>
                                            <button class="btn btn-outline-secondary" onclick="clearSessions()">
                                                <i class="bi bi-door-closed"></i> Limpar Sessões
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Criar Polo -->
    <div class="modal fade" id="createPoloModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Criar Novo Polo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createPoloForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nome do Polo *</label>
                                    <input type="text" class="form-control" name="nome" required
                                           placeholder="IMEP - Polo Brasília">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Código Único *</label>
                                    <input type="text" class="form-control" name="codigo" required
                                           placeholder="POLO_DF_001" style="text-transform: uppercase;">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Cidade *</label>
                                    <input type="text" class="form-control" name="cidade" required
                                           placeholder="Brasília">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Estado *</label>
                                    <select class="form-select" name="estado" required>
                                        <option value="">Selecione...</option>
                                        <option value="AC">Acre</option>
                                        <option value="AL">Alagoas</option>
                                        <option value="AP">Amapá</option>
                                        <option value="AM">Amazonas</option>
                                        <option value="BA">Bahia</option>
                                        <option value="CE">Ceará</option>
                                        <option value="DF">Distrito Federal</option>
                                        <option value="ES">Espírito Santo</option>
                                        <option value="GO">Goiás</option>
                                        <option value="MA">Maranhão</option>
                                        <option value="MT">Mato Grosso</option>
                                        <option value="MS">Mato Grosso do Sul</option>
                                        <option value="MG">Minas Gerais</option>
                                        <option value="PA">Pará</option>
                                        <option value="PB">Paraíba</option>
                                        <option value="PR">Paraná</option>
                                        <option value="PE">Pernambuco</option>
                                        <option value="PI">Piauí</option>
                                        <option value="RJ">Rio de Janeiro</option>
                                        <option value="RN">Rio Grande do Norte</option>
                                        <option value="RS">Rio Grande do Sul</option>
                                        <option value="RO">Rondônia</option>
                                        <option value="RR">Roraima</option>
                                        <option value="SC">Santa Catarina</option>
                                        <option value="SP">São Paulo</option>
                                        <option value="SE">Sergipe</option>
                                        <option value="TO">Tocantins</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email de Contato</label>
                                    <input type="email" class="form-control" name="email"
                                           placeholder="brasilia@imepedu.com.br">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" class="form-control" name="telefone"
                                           placeholder="(61) 99999-9999">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Endereço</label>
                                    <textarea class="form-control" name="endereco" rows="3"
                                              placeholder="Endereço completo do polo"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Ambiente Inicial</label>
                                    <select class="form-select" name="environment">
                                        <option value="sandbox">Sandbox (Recomendado)</option>
                                        <option value="production">Produção</option>
                                    </select>
                                    <small class="form-text text-muted">
                                        Você poderá alterar depois nas configurações
                                    </small>
                                </div>
                            </div>
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

    <!-- Modal Criar Usuário -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus"></i> Criar Novo Usuário
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createUserForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome Completo *</label>
                            <input type="text" class="form-control" name="nome" required
                                   placeholder="João Silva">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required
                                   placeholder="joao@imepedu.com.br">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Usuário *</label>
                            <select class="form-select" name="tipo" required>
                                <option value="">Selecione...</option>
                                <option value="master">Master Admin (Acesso Total)</option>
                                <option value="admin_polo">Admin de Polo</option>
                                <option value="operador">Operador</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="polo-select-div" style="display: none;">
                            <label class="form-label">Polo *</label>
                            <select class="form-select" name="polo_id">
                                <option value="">Selecione o polo...</option>
                                <?php foreach ($polos as $polo): ?>
                                    <?php if ($polo['is_active']): ?>
                                    <option value="<?php echo $polo['id']; ?>">
                                        <?php echo htmlspecialchars($polo['nome']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Senha Temporária *</label>
                            <input type="password" class="form-control" name="senha" required
                                   placeholder="Mínimo 8 caracteres">
                            <small class="form-text text-muted">
                                O usuário deverá alterar a senha no primeiro login
                            </small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Importante:</strong> Envie as credenciais de acesso por um canal seguro.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Criar Usuário
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navegação entre seções
        function showSection(sectionName) {
            // Ocultar todas as seções
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Mostrar seção selecionada
            document.getElementById(sectionName + '-section').classList.add('active');
            
            // Atualizar navegação
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`[data-section="${sectionName}"]`).classList.add('active');
            
            // Carregar dados específicos da seção
            loadSectionData(sectionName);
        }
        
        // Event listeners para navegação
        document.querySelectorAll('[data-section]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = e.target.closest('[data-section]').dataset.section;
                showSection(section);
            });
        });
        
        // Carregar dados específicos por seção
        function loadSectionData(section) {
            switch (section) {
                case 'polos':
                    loadPolosList();
                    break;
                case 'usuarios':
                    loadUsersList();
                    break;
                case 'relatorios':
                    loadReports();
                    break;
                case 'auditoria':
                    loadAuditLog();
                    break;
                case 'usuarios':
                    loadUsersList();
                    break;
            }
        }
        
        // Carregar lista de polos
        function loadPolosList() {
            const container = document.getElementById('polos-list');
            
            fetch('api.php?action=list-polos&incluir_inativos=true')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPolosList(data.data);
                    } else {
                        container.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i>
                                Erro ao carregar polos: ${data.error}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    container.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="bi bi-wifi-off"></i>
                            Erro de conexão: ${error.message}
                        </div>
                    `;
                });
        }
        
        function displayPolosList(polos) {
            const container = document.getElementById('polos-list');
            
            if (polos.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="bi bi-building display-1 text-muted"></i>
                        <h4 class="mt-3">Nenhum polo cadastrado</h4>
                        <p class="text-muted">Clique em "Novo Polo" para criar o primeiro.</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="row">';
            
            polos.forEach(polo => {
                const statusBadge = polo.is_active ? 
                    '<span class="badge bg-success">Ativo</span>' : 
                    '<span class="badge bg-secondary">Inativo</span>';
                    
                const envBadge = polo.asaas_environment === 'production' ?
                    '<span class="badge bg-danger">Produção</span>' :
                    '<span class="badge bg-warning">Sandbox</span>';
                
                html += `
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card polo-card ${polo.asaas_environment === 'production' ? 'production' : ''} ${!polo.is_active ? 'inactive' : ''}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title mb-0">${polo.nome}</h6>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="editPolo(${polo.id})">
                                                <i class="bi bi-pencil"></i> Editar
                                            </a></li>
                                            <li><a class="dropdown-item" href="config_interface.php?polo=${polo.id}">
                                                <i class="bi bi-gear"></i> Configurar APIs
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-${polo.is_active ? 'warning' : 'success'}" 
                                                   href="#" onclick="togglePoloStatus(${polo.id})">
                                                <i class="bi bi-${polo.is_active ? 'pause' : 'play'}"></i> 
                                                ${polo.is_active ? 'Desativar' : 'Ativar'}
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <p class="text-muted mb-2">
                                    <i class="bi bi-geo-alt"></i>
                                    ${polo.cidade}, ${polo.estado}
                                </p>
                                
                                <p class="text-muted mb-3">
                                    <small>Código: ${polo.codigo}</small>
                                </p>
                                
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="fw-bold">${polo.total_usuarios || 0}</div>
                                        <small class="text-muted">Usuários</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold">${polo.total_pagamentos || 0}</div>
                                        <small class="text-muted">Pagamentos</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold">${polo.wallet_ids || 0}</div>
                                        <small class="text-muted">Wallets</small>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    ${statusBadge}
                                    ${envBadge}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        // Carregar lista de usuários
        function loadUsersList() {
            const container = document.getElementById('users-list');
            
            fetch('api.php?action=list-users')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayUsersList(data.data);
                    } else {
                        container.innerHTML = `
                            <div class="alert alert-danger">
                                Erro ao carregar usuários: ${data.error}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    container.innerHTML = `
                        <div class="alert alert-warning">
                            Erro de conexão: ${error.message}
                        </div>
                    `;
                });
        }
        
        // Carregar atividade recente
        function loadRecentActivity() {
            const container = document.getElementById('recent-activity');
            
            fetch('api.php?action=recent-activity&limit=10')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayRecentActivity(data.data);
                    } else {
                        container.innerHTML = `
                            <div class="alert alert-warning">
                                <small>Erro ao carregar atividade</small>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    container.innerHTML = `
                        <div class="text-muted">
                            <small>Sem atividade recente</small>
                        </div>
                    `;
                });
        }
        
        function displayRecentActivity(activities) {
            const container = document.getElementById('recent-activity');
            
            if (activities.length === 0) {
                container.innerHTML = `
                    <div class="text-muted text-center">
                        <small>Nenhuma atividade recente</small>
                    </div>
                `;
                return;
            }
            
            let html = '';
            activities.forEach(activity => {
                const timeAgo = formatTimeAgo(activity.data_acao);
                const icon = getActivityIcon(activity.acao);
                
                html += `
                    <div class="activity-item">
                        <div class="d-flex">
                            <i class="bi bi-${icon} text-primary me-2"></i>
                            <div class="flex-grow-1">
                                <small class="fw-bold">${activity.acao_display || activity.acao}</small><br>
                                <small class="text-muted">
                                    ${activity.usuario_nome || 'Sistema'} • ${timeAgo}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function getActivityIcon(action) {
            const icons = {
                'login_sucesso': 'box-arrow-in-right',
                'criar_polo': 'building',
                'atualizar_config_asaas': 'gear',
                'create_wallet': 'wallet2',
                'create_payment': 'credit-card'
            };
            return icons[action] || 'activity';
        }
        
        function formatTimeAgo(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 60) return `${diffMins}m atrás`;
            if (diffHours < 24) return `${diffHours}h atrás`;
            return `${diffDays}d atrás`;
        }
        
        // Formulário criar polo
        document.getElementById('createPoloForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create-polo');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Criando...';
            submitBtn.disabled = true;
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.error || 'Polo criado com sucesso!');
                    bootstrap.Modal.getInstance(document.getElementById('createPoloModal')).hide();
                    this.reset();
                    
                    // Recarregar lista se estiver na seção de polos
                    if (document.querySelector('[data-section="polos"]').classList.contains('active')) {
                        loadPolosList();
                    }
                } else {
                    showAlert('danger', 'Erro ao criar polo: ' + data.error);
                }
            })
            .catch(error => {
                showAlert('danger', 'Erro de conexão: ' + error.message);
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Mostrar/ocultar seleção de polo baseado no tipo de usuário
        document.querySelector('select[name="tipo"]').addEventListener('change', function() {
            const poloDiv = document.getElementById('polo-select-div');
            const poloSelect = document.querySelector('select[name="polo_id"]');
            
            if (this.value === 'admin_polo' || this.value === 'operador') {
                poloDiv.style.display = 'block';
                poloSelect.required = true;
            } else {
                poloDiv.style.display = 'none';
                poloSelect.required = false;
                poloSelect.value = '';
            }
        });
        
        // Funções de manutenção
        function runHealthCheck() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Verificando...';
            btn.disabled = true;
            
            fetch('api.php?action=health-check')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Sistema funcionando corretamente!');
                    } else {
                        const issues = Array.isArray(data.data) ? data.data.join('<br>') : data.error;
                        showAlert('warning', 'Problemas encontrados:<br>' + issues);
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Erro na verificação: ' + error.message);
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }
        
        function cleanOldData() {
            if (confirm('Deseja limpar dados antigos (logs, sessões expiradas, etc.)?')) {
                fetch('api.php?action=clean-logs', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('success', data.error || 'Limpeza concluída com sucesso!');
                        } else {
                            showAlert('danger', 'Erro na limpeza: ' + data.error);
                        }
                    })
                    .catch(error => {
                        showAlert('danger', 'Erro: ' + error.message);
                    });
            }
        }
        
        function backupDatabase() {
            if (confirm('Deseja criar um backup completo do banco de dados?')) {
                fetch('api.php?action=backup', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('success', 'Backup criado: ' + data.data.filename);
                        } else {
                            showAlert('danger', 'Erro no backup: ' + data.error);
                        }
                    })
                    .catch(error => {
                        showAlert('danger', 'Erro: ' + error.message);
                    });
            }
        }
        
        // Função para mostrar alertas
        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            
            // Auto-remove após 5 segundos
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert.position-fixed');
                if (alerts.length > 0) {
                    alerts[alerts.length - 1].remove();
                }
            }, 5000);
        }
        

        // Carregar lista de usuários
function loadUsersList() {
    const container = document.getElementById('users-list');
    
    if (!container) {
        console.warn('Container users-list não encontrado');
        return;
    }
    
    // Mostrar loading
    container.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="text-muted">Carregando usuários...</p>
        </div>
    `;
    
    fetch('api.php?action=list-users')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayUsersList(data.data);
            } else {
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-exclamation-triangle"></i> Erro ao Carregar Usuários</h6>
                        <p>${data.error}</p>
                        <button class="btn btn-outline-danger btn-sm" onclick="loadUsersList()">
                            <i class="bi bi-arrow-clockwise"></i> Tentar Novamente
                        </button>
                    </div>
                `;
            }
        })
        .catch(error => {
            container.innerHTML = `
                <div class="alert alert-warning">
                    <h6><i class="bi bi-wifi-off"></i> Erro de Conexão</h6>
                    <p>Não foi possível carregar os usuários: ${error.message}</p>
                    <button class="btn btn-outline-warning btn-sm" onclick="loadUsersList()">
                        <i class="bi bi-arrow-clockwise"></i> Tentar Novamente
                    </button>
                </div>
            `;
        });
}

// Exibir lista de usuários
function displayUsersList(data) {
    const container = document.getElementById('users-list');
    const usuarios = data.usuarios || [];
    const summary = data.summary || {};
    
    if (usuarios.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="bi bi-people display-1 text-muted"></i>
                <h4 class="mt-3 text-muted">Nenhum usuário encontrado</h4>
                <p class="text-muted">Clique em "Novo Usuário" para criar o primeiro.</p>
            </div>
        `;
        return;
    }
    
    let html = `
        <!-- Resumo -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-2">
                                <h6>Total</h6>
                                <h4>${summary.total_usuarios || usuarios.length}</h4>
                            </div>
                            <div class="col-md-2">
                                <h6>Ativos</h6>
                                <h4 class="text-success">${summary.usuarios_ativos || 0}</h4>
                            </div>
                            <div class="col-md-2">
                                <h6>Online</h6>
                                <h4 class="text-info">${summary.usuarios_online || 0}</h4>
                            </div>
                            <div class="col-md-2">
                                <h6>Masters</h6>
                                <h4 class="text-primary">${summary.masters || 0}</h4>
                            </div>
                            <div class="col-md-2">
                                <h6>Admin Polos</h6>
                                <h4 class="text-warning">${summary.admin_polos || 0}</h4>
                            </div>
                            <div class="col-md-2">
                                <h6>Operadores</h6>
                                <h4 class="text-secondary">${summary.operadores || 0}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lista de Usuários -->
        <div class="row">
    `;
    
    usuarios.forEach(usuario => {
        const statusClass = usuario.is_active ? 'success' : 'secondary';
        const statusText = usuario.is_active ? 'Ativo' : 'Inativo';
        const onlineClass = usuario.status_atividade === 'online' ? 'success' : 'secondary';
        const onlineIcon = usuario.status_atividade === 'online' ? 'circle-fill' : 'circle';
        
        // Cor do card baseada no tipo
        const typeColors = {
            'master': 'border-danger',
            'admin_polo': 'border-warning', 
            'operador': 'border-info'
        };
        const cardClass = typeColors[usuario.tipo] || 'border-secondary';
        
        html += `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 ${cardClass}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1 d-flex align-items-center">
                                    ${usuario.nome}
                                    <i class="bi bi-${onlineIcon} text-${onlineClass} ms-2" title="${usuario.status_atividade}"></i>
                                </h6>
                                <p class="card-text text-muted mb-1">
                                    <i class="bi bi-envelope"></i> ${usuario.email}
                                </p>
                                ${usuario.polo_nome ? `<p class="card-text text-muted mb-1"><i class="bi bi-building"></i> ${usuario.polo_nome}</p>` : ''}
                            </div>
                            
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                        type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" onclick="editUser(${usuario.id})">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="viewUserDetails(${usuario.id})">
                                        <i class="bi bi-eye"></i> Ver Detalhes
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-${usuario.is_active ? 'warning' : 'success'}" 
                                           href="#" onclick="toggleUserStatus(${usuario.id}, ${usuario.is_active})">
                                        <i class="bi bi-${usuario.is_active ? 'pause' : 'play'}-circle"></i> 
                                        ${usuario.is_active ? 'Desativar' : 'Ativar'}
                                    </a></li>
                                    ${usuario.tipo !== 'master' ? `
                                    <li><a class="dropdown-item text-danger" 
                                           href="#" onclick="resetUserPassword(${usuario.id})">
                                        <i class="bi bi-key"></i> Resetar Senha
                                    </a></li>
                                    ` : ''}
                                </ul>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <span class="badge bg-${getTypeBadgeColor(usuario.tipo)} me-2">
                                ${usuario.tipo_formatado || usuario.tipo}
                            </span>
                            <span class="badge bg-${statusClass}">
                                ${statusText}
                            </span>
                        </div>
                        
                        <div class="row text-center small">
                            <div class="col-6">
                                <div class="text-muted">Último Login</div>
                                <div class="fw-bold">${usuario.ultimo_login_formatado || 'Nunca'}</div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">Tentativas</div>
                                <div class="fw-bold">${usuario.tentativas_login || 0}</div>
                            </div>
                        </div>
                        
                        ${usuario.bloqueado ? `
                        <div class="alert alert-warning mt-2 py-1 px-2 small">
                            <i class="bi bi-exclamation-triangle"></i> Usuário bloqueado
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Função auxiliar para cor do badge do tipo
function getTypeBadgeColor(tipo) {
    const colors = {
        'master': 'danger',
        'admin_polo': 'warning',
        'operador': 'info'
    };
    return colors[tipo] || 'secondary';
}

// Alternar status do usuário
function toggleUserStatus(userId, currentStatus) {
    const action = currentStatus ? 'desativar' : 'ativar';
    const confirmMessage = `Confirma ${action} este usuário?`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'toggle-user-status');
    formData.append('user_id', userId);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.error || `Usuário ${action} com sucesso!`);
            loadUsersList(); // Recarregar lista
        } else {
            showAlert('danger', 'Erro: ' + data.error);
        }
    })
    .catch(error => {
        showAlert('danger', 'Erro de conexão: ' + error.message);
    });
}

// Ver detalhes do usuário
function viewUserDetails(userId) {
    showAlert('info', 'Funcionalidade em desenvolvimento...');
    // TODO: Implementar modal com detalhes completos do usuário
}

// Editar usuário
function editUser(userId) {
    showAlert('info', 'Funcionalidade em desenvolvimento...');
    // TODO: Implementar modal de edição
}

// Resetar senha do usuário
function resetUserPassword(userId) {
    const novaSenha = prompt('Digite a nova senha (mínimo 6 caracteres):');
    
    if (!novaSenha || novaSenha.length < 6) {
        showAlert('warning', 'Senha deve ter pelo menos 6 caracteres');
        return;
    }
    
    if (!confirm('Confirma a alteração da senha? O usuário será desconectado.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reset-user-password');
    formData.append('user_id', userId);
    formData.append('nova_senha', novaSenha);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.error || 'Senha alterada com sucesso!');
            loadUsersList();
        } else {
            showAlert('danger', 'Erro: ' + data.error);
        }
    })
    .catch(error => {
        showAlert('danger', 'Erro de conexão: ' + error.message);
    });
}

// Formulário criar usuário (atualizar o event listener existente)
document.addEventListener('DOMContentLoaded', function() {
    const createUserForm = document.getElementById('createUserForm');
    if (createUserForm) {
        createUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create-user');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Criando...';
            submitBtn.disabled = true;
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.error || 'Usuário criado com sucesso!');
                    bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
                    this.reset();
                    
                    // Recarregar lista se estiver na seção de usuários
                    if (document.querySelector('[data-section="usuarios"]').classList.contains('active')) {
                        loadUsersList();
                    }
                } else {
                    showAlert('danger', 'Erro ao criar usuário: ' + data.error);
                }
            })
            .catch(error => {
                showAlert('danger', 'Erro de conexão: ' + error.message);
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

// ===== ATUALIZAR A FUNÇÃO loadSectionData EXISTENTE =====
// Encontre a função loadSectionData e adicione esta linha no switch:

/*
case 'usuarios':
    loadUsersList();
    break;
*/



        // Inicialização da página
        document.addEventListener('DOMContentLoaded', function() {
            // Carregar atividade recente
            loadRecentActivity();
            
            // Auto uppercase no código do polo
            const codigoInput = document.querySelector('input[name="codigo"]');
            if (codigoInput) {
                codigoInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                });
            }
        });
    </script>
</body>
</html>