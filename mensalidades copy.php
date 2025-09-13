<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensalidades Parceladas - IMEP Split ASAAS</title>
    
    <!-- Bootstrap 5.3 e Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        
        .btn-gradient {
            background: var(--primary-gradient);
            border: none;
            color: white;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .parcela-preview {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            transition: all 0.3s ease;
        }
        
        .parcela-preview:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.02);
        }
        
        .plano-card {
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .plano-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-left-color: #764ba2;
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .section {
            display: none;
        }
        
        .section.active {
            display: block;
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .split-item {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            position: relative;
        }
        
        .split-remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        .calculation-display {
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 2px solid #e1bee7;
        }
        
        .valor-destaque {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1976d2;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-radius: 0 0 15px 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-credit-card-2-front me-2"></i>
                IMEP Split ASAAS
            </a>
            
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-primary">Mensalidades Parceladas</span>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Navegação por Seções -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <nav class="nav nav-pills nav-fill">
                            <a class="nav-link active" href="#" data-section="criar">
                                <i class="bi bi-plus-circle me-2"></i>Nova Mensalidade
                            </a>
                            <a class="nav-link" href="#" data-section="listar">
                                <i class="bi bi-list me-2"></i>Planos Ativos
                            </a>
                            <a class="nav-link" href="#" data-section="relatorio">
                                <i class="bi bi-graph-up me-2"></i>Relatórios
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção: Criar Nova Mensalidade -->
        <div id="criar-section" class="section active">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-calendar-plus me-2"></i>Criar Plano de Mensalidades</h5>
                        </div>
                        <div class="card-body">
                            <form id="mensalidade-form" method="POST">
                                <input type="hidden" name="action" value="criar_mensalidade">
                                
                                <!-- Dados do Cliente -->
                                <h6 class="border-bottom pb-2 mb-3">📋 Dados do Cliente</h6>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Cliente *</label>
                                            <select class="form-select" name="customer_id" id="customer-select" required>
                                                <option value="">Selecione um cliente</option>
                                                <!-- Clientes serão carregados via JavaScript -->
                                            </select>
                                            <small class="form-text text-muted">
                                                <i class="bi bi-info-circle"></i>
                                                Caso o cliente não esteja na lista, cadastre-o primeiro
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configurações da Mensalidade -->
                                <h6 class="border-bottom pb-2 mb-3 mt-4">💰 Configurações da Mensalidade</h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de Cobrança *</label>
                                            <select class="form-select" name="billing_type" required>
                                                <option value="BOLETO">Boleto Bancário</option>
                                                <option value="PIX">PIX</option>
                                                <option value="CREDIT_CARD">Cartão de Crédito</option>
                                                <option value="DEBIT_CARD">Cartão de Débito</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Valor por Mensalidade *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="number" class="form-control" name="valor_mensalidade" 
                                                       id="valor-mensalidade" step="0.01" min="1" required 
                                                       placeholder="100,00">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Quantidade de Parcelas *</label>
                                            <select class="form-select" name="quantidade_parcelas" id="quantidade-parcelas" required>
                                                <option value="">Selecione...</option>
                                                <?php for($i = 1; $i <= 24; $i++): ?>
                                                <option value="<?php echo $i; ?>"><?php echo $i; ?>x</option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Data do Primeiro Vencimento *</label>
                                            <input type="date" class="form-control" name="data_primeiro_vencimento" 
                                                   id="data-primeiro" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Descrição *</label>
                                    <input type="text" class="form-control" name="descricao" required
                                           placeholder="Ex: Mensalidade do Curso de Programação">
                                    <small class="form-text text-muted">
                                        Será adicionado automaticamente "- Parcela X/Y" em cada cobrança
                                    </small>
                                </div>
                                
                                <!-- Configuração de Split -->
                                <h6 class="border-bottom pb-2 mb-3 mt-4">🔄 Split de Pagamentos (Opcional)</h6>
                                
                                <div id="splits-container">
                                    <div class="split-item">
                                        <div class="mb-3">
                                            <label class="form-label">Destinatário do Split</label>
                                            <select class="form-select" name="splits[0][walletId]">
                                                <option value="">Nenhum split (opcional)</option>
                                                <!-- Wallet IDs serão carregados via JavaScript -->
                                            </select>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-6">
                                                <label class="form-label">Percentual (%)</label>
                                                <input type="number" class="form-control" name="splits[0][percentualValue]" 
                                                       step="0.01" max="100" placeholder="0.00">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Valor Fixo (R$)</label>
                                                <input type="number" class="form-control" name="splits[0][fixedValue]" 
                                                       step="0.01" placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addSplit()">
                                        <i class="bi bi-plus me-1"></i>Adicionar Mais Splits
                                    </button>
                                    <small class="text-muted">O split será aplicado em todas as mensalidades</small>
                                </div>
                                
                                <!-- Botão de Envio -->
                                <hr>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confirm-mensalidade">
                                        <label class="form-check-label text-muted" for="confirm-mensalidade">
                                            Confirmo que os dados estão corretos
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-gradient" id="submit-mensalidade" disabled>
                                        <i class="bi bi-calendar-check me-2"></i>Criar Mensalidades
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Preview das Parcelas -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="bi bi-calculator me-2"></i>Preview das Mensalidades</h6>
                        </div>
                        <div class="card-body">
                            <div id="preview-container">
                                <div class="text-center text-muted">
                                    <i class="bi bi-calculator" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="mt-3">Preencha os campos para ver o preview</p>
                                </div>
                            </div>
                            
                            <!-- Cálculo Total -->
                            <div id="total-calculation" class="calculation-display mt-3" style="display: none;">
                                <div class="valor-destaque" id="valor-total-display">R$ 0,00</div>
                                <div class="text-muted">
                                    <span id="parcelas-info">0 mensalidades de R$ 0,00</span>
                                </div>
                                <hr>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Valores com splits já aplicados
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dicas -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6><i class="bi bi-lightbulb me-2"></i>Dicas Importantes</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <small>Todas as mensalidades são criadas automaticamente</small>
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <small>O split é aplicado em cada parcela</small>
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <small>Vencimentos mensais automáticos</small>
                                </li>
                                <li class="mb-0">
                                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                                    <small>Máximo de 24 parcelas</small>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção: Listar Planos -->
        <div id="listar-section" class="section">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="bi bi-list me-2"></i>Planos de Mensalidades</h5>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm" onclick="refreshPlanos()">
                                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="exportPlanos()">
                                    <i class="bi bi-download"></i> Exportar
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="planos-container">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Carregando...</span>
                                    </div>
                                    <p class="mt-2">Carregando planos...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção: Relatórios -->
        <div id="relatorio-section" class="section">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-graph-up me-2"></i>Relatório de Mensalidades</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Data Inicial</label>
                                        <input type="date" class="form-control" id="relatorio-inicio" 
                                               value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Data Final</label>
                                        <input type="date" class="form-control" id="relatorio-fim" 
                                               value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-gradient" onclick="gerarRelatorio()">
                                    <i class="bi bi-file-earmark-text me-2"></i>Gerar Relatório
                                </button>
                            </div>
                            
                            <div id="relatorio-results" class="mt-4" style="display: none;">
                                <!-- Resultados aparecerão aqui -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="bi bi-speedometer2 me-2"></i>Estatísticas Rápidas</h6>
                        </div>
                        <div class="card-body">
                            <div id="stats-container">
                                <!-- Estatísticas serão carregadas aqui -->
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Carregando...</span>
                                    </div>
                                    <p class="mt-2">Carregando estatísticas...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes do Plano -->
    <div class="modal fade" id="planoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-check me-2"></i>
                        Detalhes do Plano de Mensalidades
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="plano-details">
                        <!-- Detalhes do plano aparecerão aqui -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-danger" id="cancelar-plano-btn" onclick="cancelarPlano()">
                        <i class="bi bi-x-circle me-2"></i>Cancelar Plano
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ===== CONFIGURAÇÃO GLOBAL =====
        let currentSection = 'criar';
        let splitCounter = 1;
        let selectedPlanoId = null;
        
        // ===== NAVEGAÇÃO ENTRE SEÇÕES =====
        function showSection(section) {
            // Esconder todas as seções
            document.querySelectorAll('.section').forEach(el => {
                el.classList.remove('active');
            });
            
            // Mostrar seção selecionada
            const targetSection = document.getElementById(section + '-section');
            if (targetSection) {
                targetSection.classList.add('active');
                currentSection = section;
                
                // Atualizar navegação
                document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
                const navLink = document.querySelector(`[data-section="${section}"]`);
                if (navLink) navLink.classList.add('active');
                
                // Carregar dados específicos da seção
                if (section === 'listar') {
                    loadPlanos();
                } else if (section === 'relatorio') {
                    loadStats();
                }
            }
        }
        
        // ===== GESTÃO DE SPLITS =====
        function addSplit() {
            splitCounter++;
            const splitsContainer = document.getElementById('splits-container');
            
            const splitHtml = `
                <div class="split-item">
                    <button type="button" class="split-remove-btn btn btn-sm btn-outline-danger" onclick="removeSplit(this)">
                        <i class="bi bi-x"></i>
                    </button>
                    
                    <div class="mb-3">
                        <label class="form-label">Destinatário do Split</label>
                        <select class="form-select" name="splits[${splitCounter}][walletId]">
                            <option value="">Selecione um destinatário</option>
                            <!-- Wallet IDs serão carregados -->
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <label class="form-label">Percentual (%)</label>
                            <input type="number" class="form-control" name="splits[${splitCounter}][percentualValue]" 
                                   step="0.01" max="100" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valor Fixo (R$)</label>
                            <input type="number" class="form-control" name="splits[${splitCounter}][fixedValue]" 
                                   step="0.01" placeholder="0.00">
                        </div>
                    </div>
                </div>
            `;
            
            splitsContainer.insertAdjacentHTML('beforeend', splitHtml);
            showToast('Split adicionado!', 'info');
        }
        
        function removeSplit(button) {
            const splitItem = button.closest('.split-item');
            if (splitItem) {
                splitItem.style.transition = 'opacity 0.3s ease';
                splitItem.style.opacity = '0';
                setTimeout(() => {
                    splitItem.remove();
                    updatePreview();
                    showToast('Split removido', 'info');
                }, 300);
            }
        }
        
        // ===== PREVIEW DAS MENSALIDADES =====
        function updatePreview() {
            const valorMensalidade = parseFloat(document.getElementById('valor-mensalidade').value) || 0;
            const quantidadeParcelas = parseInt(document.getElementById('quantidade-parcelas').value) || 0;
            const dataPrimeiro = document.getElementById('data-primeiro').value;
            
            const previewContainer = document.getElementById('preview-container');
            const totalCalculation = document.getElementById('total-calculation');
            
            if (valorMensalidade <= 0 || quantidadeParcelas <= 0) {
                previewContainer.innerHTML = `
                    <div class="text-center text-muted">
                        <i class="bi bi-calculator" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-3">Preencha os campos para ver o preview</p>
                    </div>
                `;
                totalCalculation.style.display = 'none';
                return;
            }
            
            // Calcular datas
            const datas = calcularDatasVencimento(dataPrimeiro, quantidadeParcelas);
            const valorTotal = valorMensalidade * quantidadeParcelas;
            
            // Atualizar preview
            let previewHtml = `
                <div class="small mb-3">
                    <strong>📅 Cronograma de Vencimentos:</strong>
                </div>
            `;
            
            datas.slice(0, Math.min(5, quantidadeParcelas)).forEach((data, index) => {
                previewHtml += `
                    <div class="parcela-preview">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong>Parcela ${index + 1}/${quantidadeParcelas}</strong></span>
                            <span class="text-success fw-bold">R$ ${valorMensalidade.toFixed(2).replace('.', ',')}</span>
                        </div>
                        <small class="text-muted">${formatarData(data)}</small>
                    </div>
                `;
            });
            
            if (quantidadeParcelas > 5) {
                previewHtml += `
                    <div class="text-center text-muted small">
                        <i class="bi bi-three-dots"></i>
                        <br>e mais ${quantidadeParcelas - 5} mensalidades...
                    </div>
                `;
            }
            
            previewContainer.innerHTML = previewHtml;
            
            // Atualizar cálculo total
            document.getElementById('valor-total-display').textContent = 
                `R$ ${valorTotal.toFixed(2).replace('.', ',')}`;
            document.getElementById('parcelas-info').textContent = 
                `${quantidadeParcelas} mensalidades de R$ ${valorMensalidade.toFixed(2).replace('.', ',')}`;
            
            totalCalculation.style.display = 'block';
        }
        
        function calcularDatasVencimento(dataPrimeiro, quantidade) {
            const datas = [];
            const dataBase = new Date(dataPrimeiro);
            
            for (let i = 0; i < quantidade; i++) {
                if (i === 0) {
                    datas.push(new Date(dataBase));
                } else {
                    const proximaData = new Date(dataBase);
                    proximaData.setMonth(proximaData.getMonth() + i);
                    
                    // Ajustar se o dia não existir no mês
                    if (proximaData.getDate() !== dataBase.getDate()) {
                        proximaData.setDate(0); // Último dia do mês anterior
                    }
                    
                    datas.push(proximaData);
                }
            }
            
            return datas.map(date => date.toISOString().split('T')[0]);
        }
        
        function formatarData(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR', { 
                day: '2-digit', 
                month: 'long', 
                year: 'numeric' 
            });
        }
        
        // ===== CARREGAMENTO DE DADOS =====
        async function loadCustomers() {
            try {
                const response = await fetch('api.php?action=list-customers');
                const data = await response.json();
                
                if (data.success) {
                    const select = document.getElementById('customer-select');
                    select.innerHTML = '<option value="">Selecione um cliente</option>';
                    
                    data.data.forEach(customer => {
                        select.innerHTML += `
                            <option value="${customer.id}">
                                ${customer.name} (${customer.email})
                            </option>
                        `;
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar clientes:', error);
            }
        }
        
        async function loadWallets() {
            try {
                const response = await fetch('api.php?action=list-wallets');
                const data = await response.json();
                
                if (data.success) {
                    document.querySelectorAll('select[name*="[walletId]"]').forEach(select => {
                        const currentValue = select.value;
                        select.innerHTML = '<option value="">Nenhum split (opcional)</option>';
                        
                        data.data.forEach(wallet => {
                            if (wallet.is_active) {
                                select.innerHTML += `
                                    <option value="${wallet.wallet_id}" ${currentValue === wallet.wallet_id ? 'selected' : ''}>
                                        ${wallet.name}
                                    </option>
                                `;
                            }
                        });
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar wallets:', error);
            }
        }
        
        async function loadPlanos() {
            const container = document.getElementById('planos-container');
            
            try {
                const response = await fetch('api.php?action=list-planos-mensalidade');
                const data = await response.json();
                
                if (data.success && data.data.length > 0) {
                    let html = '';
                    
                    data.data.forEach(plano => {
                        const progress = (plano.parcelas_pagas / plano.quantidade_parcelas) * 100;
                        const statusClass = plano.status === 'ATIVO' ? 'success' : 
                                          plano.status === 'CANCELADO' ? 'danger' : 'secondary';
                        
                        html += `
                            <div class="col-md-6 mb-3">
                                <div class="card plano-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">${plano.customer_name}</h6>
                                            <span class="status-badge badge bg-${statusClass}">${plano.status}</span>
                                        </div>
                                        
                                        <p class="text-muted small mb-2">${plano.descricao}</p>
                                        
                                        <div class="row text-center mb-3">
                                            <div class="col-4">
                                                <div class="fw-bold">R$ ${parseFloat(plano.valor_mensalidade).toFixed(2).replace('.', ',')}</div>
                                                <small class="text-muted">Por mês</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold">${plano.quantidade_parcelas}x</div>
                                                <small class="text-muted">Parcelas</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold text-success">R$ ${parseFloat(plano.total_valor).toFixed(2).replace('.', ',')}</div>
                                                <small class="text-muted">Total</small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <small>Progresso</small>
                                                <small>${plano.parcelas_pagas}/${plano.quantidade_parcelas} pagas</small>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" style="width: ${progress}%"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                Criado em ${new Date(plano.created_at).toLocaleDateString('pt-BR')}
                                            </small>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-outline-primary btn-sm" 
                                                        onclick="viewPlano(${plano.id})">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                ${plano.status === 'ATIVO' ? `
                                                <button class="btn btn-outline-danger btn-sm" 
                                                        onclick="confirmCancelPlano(${plano.id}, '${plano.customer_name}')">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                                ` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = `<div class="row">${html}</div>`;
                } else {
                    container.innerHTML = `
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x" style="font-size: 4rem; opacity: 0.3;"></i>
                            <h5 class="mt-3">Nenhum plano de mensalidades encontrado</h5>
                            <p>Crie seu primeiro plano na aba "Nova Mensalidade"</p>
                        </div>
                    `;
                }
            } catch (error) {
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        Erro ao carregar planos: ${error.message}
                    </div>
                `;
            }
        }
        
        // ===== AÇÕES DO SISTEMA =====
        async function viewPlano(planoId) {
            selectedPlanoId = planoId;
            
            try {
                const response = await fetch(`api.php?action=get-plano-details&plano_id=${planoId}`);
                const data = await response.json();
                
                if (data.success) {
                    displayPlanoDetails(data.data);
                    new bootstrap.Modal(document.getElementById('planoModal')).show();
                } else {
                    showToast('Erro ao carregar detalhes do plano', 'error');
                }
            } catch (error) {
                showToast('Erro de conexão: ' + error.message, 'error');
            }
        }
        
        function displayPlanoDetails(plano) {
            const detailsContainer = document.getElementById('plano-details');
            
            const statusClass = plano.status === 'ATIVO' ? 'success' : 
                              plano.status === 'CANCELADO' ? 'danger' : 'secondary';
            
            let html = `
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h6>${plano.customer_name}</h6>
                        <p class="text-muted mb-1">${plano.customer_email}</p>
                        <p class="mb-0">${plano.descricao}</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-${statusClass} mb-2">${plano.status}</span>
                        <br>
                        <small class="text-muted">ID: ${plano.id}</small>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-3 text-center">
                        <div class="border rounded p-3">
                            <div class="h5 text-primary mb-1">R$ ${parseFloat(plano.valor_mensalidade).toFixed(2).replace('.', ',')}</div>
                            <small class="text-muted">Valor Mensal</small>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="border rounded p-3">
                            <div class="h5 text-info mb-1">${plano.quantidade_parcelas}</div>
                            <small class="text-muted">Parcelas</small>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="border rounded p-3">
                            <div class="h5 text-success mb-1">${plano.parcelas_pagas}</div>
                            <small class="text-muted">Pagas</small>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="border rounded p-3">
                            <div class="h5 text-warning mb-1">R$ ${parseFloat(plano.valor_recebido || 0).toFixed(2).replace('.', ',')}</div>
                            <small class="text-muted">Recebido</small>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Progresso do Plano</strong>
                        <span>${plano.percentual_concluido.toFixed(1)}%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: ${plano.percentual_concluido}%"></div>
                    </div>
                </div>
            `;
            
            if (plano.pagamentos && plano.pagamentos.length > 0) {
                html += `
                    <h6 class="border-bottom pb-2 mb-3">📋 Mensalidades</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Parcela</th>
                                    <th>Vencimento</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                plano.pagamentos.forEach(pagamento => {
                    const statusClass = getPaymentStatusClass(pagamento.status);
                    const statusText = getPaymentStatusText(pagamento.status);
                    
                    html += `
                        <tr>
                            <td><strong>${pagamento.numero_parcela}/${plano.quantidade_parcelas}</strong></td>
                            <td>${new Date(pagamento.due_date).toLocaleDateString('pt-BR')}</td>
                            <td>R$ ${parseFloat(pagamento.value).toFixed(2).replace('.', ',')}</td>
                            <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-outline-primary btn-sm" 
                                            onclick="copyPaymentId('${pagamento.id}')" 
                                            data-bs-toggle="tooltip" title="Copiar ID">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                    ${pagamento.invoice_url ? `
                                    <a href="${pagamento.invoice_url}" target="_blank" 
                                       class="btn btn-outline-success btn-sm" 
                                       data-bs-toggle="tooltip" title="Ver cobrança">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    ` : ''}
                                </div>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            detailsContainer.innerHTML = html;
        }
        
        function getPaymentStatusClass(status) {
            const statusMap = {
                'RECEIVED': 'success',
                'PENDING': 'warning',
                'OVERDUE': 'danger',
                'CANCELED': 'secondary',
                'CONFIRMED': 'info'
            };
            return statusMap[status] || 'secondary';
        }
        
        function getPaymentStatusText(status) {
            const statusMap = {
                'RECEIVED': 'Pago',
                'PENDING': 'Pendente',
                'OVERDUE': 'Vencido',
                'CANCELED': 'Cancelado',
                'CONFIRMED': 'Confirmado'
            };
            return statusMap[status] || status;
        }
        
        function confirmCancelPlano(planoId, customerName) {
            if (confirm(`⚠️ ATENÇÃO: Deseja cancelar o plano de mensalidades de "${customerName}"?\n\nEsta ação irá:\n- Cancelar todas as parcelas pendentes\n- Manter as parcelas já pagas\n- Não poderá ser desfeita\n\nConfirma o cancelamento?`)) {
                const motivo = prompt('Informe o motivo do cancelamento (opcional):') || '';
                cancelarPlanoAction(planoId, motivo);
            }
        }
        
        async function cancelarPlanoAction(planoId, motivo) {
            try {
                showToast('Cancelando plano...', 'info');
                
                const formData = new FormData();
                formData.append('action', 'cancelar_plano');
                formData.append('plano_id', planoId);
                formData.append('motivo', motivo);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.text();
                
                if (result.includes('alert-success')) {
                    showToast('Plano cancelado com sucesso!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showToast('Erro ao cancelar plano', 'error');
                }
            } catch (error) {
                showToast('Erro de conexão: ' + error.message, 'error');
            }
        }
        
        function cancelarPlano() {
            if (selectedPlanoId) {
                const motivo = prompt('Informe o motivo do cancelamento (opcional):') || '';
                cancelarPlanoAction(selectedPlanoId, motivo);
                bootstrap.Modal.getInstance(document.getElementById('planoModal')).hide();
            }
        }
        
        // ===== FUNÇÕES DE RELATÓRIO =====
        async function gerarRelatorio() {
            const inicio = document.getElementById('relatorio-inicio').value;
            const fim = document.getElementById('relatorio-fim').value;
            const resultsContainer = document.getElementById('relatorio-results');
            
            if (!inicio || !fim) {
                showToast('Selecione o período para o relatório', 'warning');
                return;
            }
            
            try {
                showToast('Gerando relatório...', 'info');
                resultsContainer.style.display = 'block';
                resultsContainer.innerHTML = `
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Gerando...</span>
                        </div>
                        <p class="mt-2">Processando dados...</p>
                    </div>
                `;
                
                const response = await fetch(`api.php?action=relatorio-mensalidades&inicio=${inicio}&fim=${fim}`);
                const data = await response.json();
                
                if (data.success) {
                    displayRelatorioResults(data.data, inicio, fim);
                    showToast('Relatório gerado com sucesso!', 'success');
                } else {
                    throw new Error(data.error || 'Erro desconhecido');
                }
            } catch (error) {
                resultsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        Erro ao gerar relatório: ${error.message}
                    </div>
                `;
                showToast('Erro ao gerar relatório', 'error');
            }
        }
        
        function displayRelatorioResults(data, inicio, fim) {
            const container = document.getElementById('relatorio-results');
            
            let html = `
                <div class="alert alert-info">
                    <h6>📊 Relatório de Mensalidades</h6>
                    <p><strong>Período:</strong> ${new Date(inicio).toLocaleDateString('pt-BR')} a ${new Date(fim).toLocaleDateString('pt-BR')}</p>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-primary">${data.total_planos || 0}</h4>
                                <p class="text-muted mb-0">Planos Criados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-success">${data.total_mensalidades || 0}</h4>
                                <p class="text-muted mb-0">Mensalidades</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-warning">${data.mensalidades_pagas || 0}</h4>
                                <p class="text-muted mb-0">Pagas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-info">R$ ${parseFloat(data.valor_total || 0).toFixed(2).replace('.', ',')}</h4>
                                <p class="text-muted mb-0">Valor Total</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if (data.planos && data.planos.length > 0) {
                html += `
                    <div class="card">
                        <div class="card-header">
                            <h6>📋 Planos do Período</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Valor Mensal</th>
                                            <th>Parcelas</th>
                                            <th>Status</th>
                                            <th>Progresso</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                
                data.planos.forEach(plano => {
                    const progress = (plano.parcelas_pagas / plano.quantidade_parcelas) * 100;
                    html += `
                        <tr>
                            <td>
                                <strong>${plano.customer_name}</strong><br>
                                <small class="text-muted">${plano.descricao}</small>
                            </td>
                            <td>R$ ${parseFloat(plano.valor_mensalidade).toFixed(2).replace('.', ',')}</td>
                            <td>${plano.quantidade_parcelas}x</td>
                            <td><span class="badge bg-${plano.status === 'ATIVO' ? 'success' : 'secondary'}">${plano.status}</span></td>
                            <td>
                                <div class="progress" style="width: 100px; height: 20px;">
                                    <div class="progress-bar" style="width: ${progress}%">${Math.round(progress)}%</div>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }
        
        async function loadStats() {
            const container = document.getElementById('stats-container');
            
            try {
                const response = await fetch('api.php?action=stats-mensalidades');
                const data = await response.json();
                
                if (data.success) {
                    const stats = data.data;
                    
                    container.innerHTML = `
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border rounded p-3 mb-3">
                                    <h5 class="text-primary">${stats.planos_ativos || 0}</h5>
                                    <small class="text-muted">Planos Ativos</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 mb-3">
                                    <h5 class="text-success">${stats.mensalidades_hoje || 0}</h5>
                                    <small class="text-muted">Vencem Hoje</small>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="border rounded p-3 mb-3">
                                    <h6 class="text-warning">R$ ${parseFloat(stats.receita_mensal || 0).toFixed(2).replace('.', ',')}</h6>
                                    <small class="text-muted">Receita Mensal Prevista</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h6 class="mb-3">🎯 Metas do Mês</h6>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Meta de Recebimentos</small>
                                <small>${stats.meta_progresso || 0}%</small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: ${stats.meta_progresso || 0}%"></div>
                            </div>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle"></i>
                            Erro ao carregar estatísticas
                        </div>
                    `;
                }
            } catch (error) {
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        Erro de conexão
                    </div>
                `;
            }
        }
        
        // ===== FUNÇÕES AUXILIARES =====
        function copyPaymentId(paymentId) {
            navigator.clipboard.writeText(paymentId).then(() => {
                showToast('ID copiado!', 'success');
            });
        }
        
        function refreshPlanos() {
            loadPlanos();
            showToast('Lista atualizada!', 'info');
        }
        
        function exportPlanos() {
            showToast('Funcionalidade em desenvolvimento', 'info');
        }
        
        function showToast(message, type = 'info') {
            const toastClass = {
                success: 'text-bg-success',
                error: 'text-bg-danger', 
                warning: 'text-bg-warning',
                info: 'text-bg-info'
            }[type] || 'text-bg-info';
            
            const iconClass = {
                success: 'bi-check-circle',
                error: 'bi-exclamation-triangle',
                warning: 'bi-exclamation-triangle',
                info: 'bi-info-circle'
            }[type] || 'bi-info-circle';
            
            const toastHtml = `
                <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
                    <div class="toast show ${toastClass}" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="bi ${iconClass} me-2"></i>
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', toastHtml);
            
            setTimeout(() => {
                const toasts = document.querySelectorAll('.toast');
                if (toasts.length > 0) {
                    toasts[toasts.length - 1].closest('div').remove();
                }
            }, 5000);
        }
        
        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            // Navegação por seções
            document.querySelectorAll('[data-section]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const section = e.target.closest('[data-section]').dataset.section;
                    showSection(section);
                });
            });
            
            // Atualizar preview quando campos mudarem
            document.getElementById('valor-mensalidade').addEventListener('input', updatePreview);
            document.getElementById('quantidade-parcelas').addEventListener('change', updatePreview);
            document.getElementById('data-primeiro').addEventListener('change', updatePreview);
            
            // Controle do botão de envio
            document.getElementById('confirm-mensalidade').addEventListener('change', function() {
                document.getElementById('submit-mensalidade').disabled = !this.checked;
            });
            
            // Carregar dados iniciais
            loadCustomers();
            loadWallets();
            
            // Tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            console.log('🎉 Sistema de Mensalidades Parceladas carregado!');
        });
    </script>
</body>
</html>