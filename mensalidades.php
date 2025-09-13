<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensalidades Parceladas COM DESCONTO - IMEP Split ASAAS</title>
    
    <!-- Bootstrap 5.3 e Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --discount-gradient: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
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
        
        /* ===== ESTILOS PARA DESCONTO ===== */
        .discount-section {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.05) 0%, rgba(254, 202, 87, 0.05) 100%);
            border: 2px dashed #ff6b6b;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            position: relative;
        }
        
        .discount-enabled {
            background: var(--discount-gradient);
            color: white;
        }
        
        .discount-preview {
            background: white;
            border: 2px solid #ff6b6b;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            text-align: center;
        }
        
        .discount-toggle-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .discount-toggle-card.active {
            border-color: #ff6b6b;
            background: rgba(255, 107, 107, 0.1);
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
        
        .parcela-preview.with-discount {
            border-color: #ff6b6b;
            background: rgba(255, 107, 107, 0.05);
        }
        
        .valor-original {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .valor-com-desconto {
            color: #28a745;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .economia-badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
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
        
        .discount-summary {
            background: var(--discount-gradient);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-radius: 0 0 15px 15px;
            margin-bottom: 20px;
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
                <span class="badge bg-primary">Mensalidades com Desconto</span>
                <span class="badge bg-success">
                    <i class="bi bi-percent me-1"></i>
                    Desconto Automático
                </span>
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
        <!-- Seção: Criar Nova Mensalidade COM DESCONTO -->
        <div id="criar-section" class="section active">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5>
                                <i class="bi bi-calendar-plus me-2"></i>
                                Criar Plano de Mensalidades COM DESCONTO
                            </h5>
                            <small>Configure desconto automático válido até o vencimento de cada parcela</small>
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

                                                                <!-- ===== NOVA SEÇÃO: CONFIGURAÇÃO DE DESCONTO ===== -->
                                                                <div class="discount-section">
                                    <h6 class="text-danger mb-3">
                                        <i class="bi bi-percent me-2"></i>
                                        Configuração de Desconto (Opcional)
                                    </h6>
                                    
                                    <!-- Toggle para Ativar Desconto -->
                                    <div class="discount-toggle-card mb-3" id="discount-toggle" onclick="toggleDiscount()">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable-discount">
                                            <label class="form-check-label fw-bold" for="enable-discount">
                                                <i class="bi bi-tag me-2"></i>
                                                Oferecer Desconto para Pagamento até o Vencimento
                                            </label>
                                        </div>
                                        <small class="text-muted">
                                            O desconto será aplicado automaticamente a TODAS as parcelas se pagas até o dia do vencimento
                                        </small>
                                    </div>
                                    
                                    <!-- Configurações do Desconto -->
                                    <div id="discount-config" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label text-danger fw-bold">
                                                        <i class="bi bi-cash-coin me-1"></i>
                                                        Valor do Desconto (R$) *
                                                    </label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-danger text-white">R$</span>
                                                        <input type="number" class="form-control" name="discount_value" 
                                                               id="discount-value" step="0.01" min="0" 
                                                               placeholder="50,00">
                                                    </div>
                                                    <small class="form-text text-muted">
                                                        Valor fixo de desconto por parcela
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label text-info fw-bold">
                                                        <i class="bi bi-calendar-check me-1"></i>
                                                        Prazo do Desconto
                                                    </label>
                                                    <div class="form-control bg-light text-muted">
                                                        <i class="bi bi-clock me-1"></i>
                                                        Válido até o dia do vencimento
                                                    </div>
                                                    <small class="form-text text-info">
                                                        Desconto aplicado automaticamente se pago no prazo
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Preview do Desconto -->
                                        <div id="discount-preview" class="discount-preview" style="display: none;">
                                            <h6 class="text-success mb-2">
                                                <i class="bi bi-calculator me-2"></i>
                                                Simulação de Economia
                                            </h6>
                                            <div class="row">
                                                <div class="col-4">
                                                    <div class="fw-bold text-danger" id="discount-per-installment">R$ 0,00</div>
                                                    <small class="text-muted">Por parcela</small>
                                                </div>
                                                <div class="col-4">
                                                    <div class="fw-bold text-success" id="total-discount-potential">R$ 0,00</div>
                                                    <small class="text-muted">Economia total</small>
                                                </div>
                                                <div class="col-4">
                                                    <div class="fw-bold text-info" id="discount-percentage">0%</div>
                                                    <small class="text-muted">% de desconto</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                                        <i class="bi bi-calendar-check me-2"></i>Criar Mensalidades com Desconto
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                                <!-- Preview das Parcelas COM DESCONTO -->
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
                            
                            <!-- Cálculo Total COM DESCONTO -->
                            <div id="total-calculation" class="calculation-display mt-3" style="display: none;">
                                <div class="valor-destaque" id="valor-total-display">R$ 0,00</div>
                                <div class="text-muted mb-2">
                                    <span id="parcelas-info">0 mensalidades de R$ 0,00</span>
                                </div>
                                
                                <!-- Resumo do Desconto -->
                                <div id="discount-summary-card" class="discount-summary" style="display: none;">
                                    <h6 class="mb-2">
                                        <i class="bi bi-percent me-2"></i>
                                        Resumo do Desconto
                                    </h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="fw-bold" id="summary-discount-per-installment">R$ 0,00</div>
                                            <small>Por parcela</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="fw-bold" id="summary-total-economy">R$ 0,00</div>
                                            <small>Economia total</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Valores com desconto aplicado quando pago no prazo
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dicas COM DESCONTO -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6><i class="bi bi-lightbulb me-2"></i>Como Funciona o Desconto</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <small><strong>Automático:</strong> Aplicado se pago até o vencimento</small>
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <small><strong>Todas as parcelas:</strong> Desconto em cada mensalidade</small>
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <small><strong>Valor fixo:</strong> Mesmo desconto sempre</small>
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                                    <small><strong>Prazo:</strong> Apenas até o dia do vencimento</small>
                                </li>
                                <li class="mb-0">
                                    <i class="bi bi-info-circle text-info me-2"></i>
                                    <small><strong>Máximo:</strong> 50% do valor da parcela</small>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Calculadora de Economia -->
                    <div class="card mt-3" id="economy-calculator" style="display: none;">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-piggy-bank me-2"></i>
                                Calculadora de Economia
                            </h6>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <div class="h5 text-success" id="calc-total-savings">R$ 0,00</div>
                                <small class="text-muted">Economia total possível</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="fw-bold text-primary" id="calc-original-total">R$ 0,00</div>
                                    <small class="text-muted">Sem desconto</small>
                                </div>
                                <div class="col-6">
                                    <div class="fw-bold text-success" id="calc-with-discount">R$ 0,00</div>
                                    <small class="text-muted">Com desconto</small>
                                </div>
                            </div>
                            
                            <hr>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" id="savings-progress" style="width: 0%"></div>
                            </div>
                            <small class="text-muted mt-1">Percentual de economia</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ===== CONFIGURAÇÃO GLOBAL COM DESCONTO =====
        let currentSection = 'criar';
        let splitCounter = 1;
        let discountEnabled = false;
        
        // ===== FUNÇÕES DE DESCONTO =====
        
        /**
         * Ativar/Desativar seção de desconto
         */
        function toggleDiscount() {
            const checkbox = document.getElementById('enable-discount');
            const discountConfig = document.getElementById('discount-config');
            const toggleCard = document.getElementById('discount-toggle');
            
            discountEnabled = !discountEnabled;
            checkbox.checked = discountEnabled;
            
            if (discountEnabled) {
                discountConfig.style.display = 'block';
                toggleCard.classList.add('active');
                document.getElementById('discount-value').required = true;
                showToast('Desconto ativado! Configure o valor.', 'success');
            } else {
                discountConfig.style.display = 'none';
                toggleCard.classList.remove('active');
                document.getElementById('discount-value').required = false;
                document.getElementById('discount-value').value = '';
                hideDiscountPreview();
                showToast('Desconto desativado.', 'info');
            }
            
            updatePreview();
        }
        
        /**
         * Atualizar preview do desconto
         */
        function updateDiscountPreview() {
            const discountValue = parseFloat(document.getElementById('discount-value').value) || 0;
            const installmentValue = parseFloat(document.getElementById('valor-mensalidade').value) || 0;
            const installmentCount = parseInt(document.getElementById('quantidade-parcelas').value) || 0;
            
            const discountPreview = document.getElementById('discount-preview');
            
            if (discountEnabled && discountValue > 0 && installmentValue > 0) {
                // Validar se desconto não é maior que 50% da parcela
                const maxDiscount = installmentValue * 0.50;
                if (discountValue > maxDiscount) {
                    showToast(`Desconto muito alto! Máximo: R$ ${maxDiscount.toFixed(2).replace('.', ',')}`, 'warning');
                    document.getElementById('discount-value').value = maxDiscount.toFixed(2);
                    return updateDiscountPreview(); // Recalcular com valor corrigido
                }
                
                const totalDiscountPotential = discountValue * installmentCount;
                const discountPercentage = (discountValue / installmentValue) * 100;
                
                // Atualizar elementos do preview
                document.getElementById('discount-per-installment').textContent = 
                    `R$ ${discountValue.toFixed(2).replace('.', ',')}`;
                document.getElementById('total-discount-potential').textContent = 
                    `R$ ${totalDiscountPotential.toFixed(2).replace('.', ',')}`;
                document.getElementById('discount-percentage').textContent = 
                    `${discountPercentage.toFixed(1)}%`;
                
                discountPreview.style.display = 'block';
                
                // Atualizar calculadora de economia
                updateEconomyCalculator();
            } else {
                discountPreview.style.display = 'none';
            }
        }
        
        /**
         * Esconder preview do desconto
         */
        function hideDiscountPreview() {
            document.getElementById('discount-preview').style.display = 'none';
            document.getElementById('discount-summary-card').style.display = 'none';
            document.getElementById('economy-calculator').style.display = 'none';
        }
        
        /**
         * Atualizar calculadora de economia
         */
        function updateEconomyCalculator() {
            const discountValue = parseFloat(document.getElementById('discount-value').value) || 0;
            const installmentValue = parseFloat(document.getElementById('valor-mensalidade').value) || 0;
            const installmentCount = parseInt(document.getElementById('quantidade-parcelas').value) || 0;
            
            if (discountEnabled && discountValue > 0 && installmentValue > 0 && installmentCount > 0) {
                const originalTotal = installmentValue * installmentCount;
                const totalSavings = discountValue * installmentCount;
                const withDiscountTotal = originalTotal - totalSavings;
                const savingsPercentage = (totalSavings / originalTotal) * 100;
                
                // Atualizar elementos da calculadora
                document.getElementById('calc-total-savings').textContent = 
                    `R$ ${totalSavings.toFixed(2).replace('.', ',')}`;
                document.getElementById('calc-original-total').textContent = 
                    `R$ ${originalTotal.toFixed(2).replace('.', ',')}`;
                document.getElementById('calc-with-discount').textContent = 
                    `R$ ${withDiscountTotal.toFixed(2).replace('.', ',')}`;
                document.getElementById('savings-progress').style.width = `${savingsPercentage}%`;
                
                document.getElementById('economy-calculator').style.display = 'block';
            } else {
                document.getElementById('economy-calculator').style.display = 'none';
            }
        }
        
        /**
         * Preview das mensalidades COM DESCONTO
         */
        function updatePreview() {
            const valorMensalidade = parseFloat(document.getElementById('valor-mensalidade').value) || 0;
            const quantidadeParcelas = parseInt(document.getElementById('quantidade-parcelas').value) || 0;
            const dataPrimeiro = document.getElementById('data-primeiro').value;
            const discountValue = discountEnabled ? (parseFloat(document.getElementById('discount-value').value) || 0) : 0;
            
            const previewContainer = document.getElementById('preview-container');
            const totalCalculation = document.getElementById('total-calculation');
            const discountSummaryCard = document.getElementById('discount-summary-card');
            
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
            
            // Calcular datas e valores
            const datas = calcularDatasVencimento(dataPrimeiro, quantidadeParcelas);
            const valorTotal = valorMensalidade * quantidadeParcelas;
            const valorComDesconto = valorMensalidade - discountValue;
            const totalComDesconto = valorComDesconto * quantidadeParcelas;
            const economiaTotal = discountValue * quantidadeParcelas;
            
            // Atualizar preview COM DESCONTO
            let previewHtml = `
                <div class="small mb-3">
                    <strong>📅 Cronograma de Vencimentos${discountEnabled ? ' COM DESCONTO' : ''}:</strong>
                </div>
            `;
            
            datas.slice(0, Math.min(5, quantidadeParcelas)).forEach((data, index) => {
                const hasDiscount = discountEnabled && discountValue > 0;
                const parcelaClass = hasDiscount ? 'parcela-preview with-discount' : 'parcela-preview';
                
                previewHtml += `
                    <div class="${parcelaClass}">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong>Parcela ${index + 1}/${quantidadeParcelas}</strong></span>
                            <div class="text-end">
                                ${hasDiscount ? `
                                    <div class="valor-original">R$ ${valorMensalidade.toFixed(2).replace('.', ',')}</div>
                                    <div class="valor-com-desconto">R$ ${valorComDesconto.toFixed(2).replace('.', ',')}</div>
                                    <span class="economia-badge">-R$ ${discountValue.toFixed(2).replace('.', ',')}</span>
                                ` : `
                                    <span class="text-success fw-bold">R$ ${valorMensalidade.toFixed(2).replace('.', ',')}</span>
                                `}
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">${formatarData(data)}</small>
                            ${hasDiscount ? '<small class="text-success"><i class="bi bi-clock"></i> Desconto até vencimento</small>' : ''}
                        </div>
                    </div>
                `;
            });
            
            if (quantidadeParcelas > 5) {
                previewHtml += `
                    <div class="text-center text-muted small">
                        <i class="bi bi-three-dots"></i>
                        <br>e mais ${quantidadeParcelas - 5} mensalidades...
                        ${discountEnabled && discountValue > 0 ? '<br><span class="text-success">todas com desconto</span>' : ''}
                    </div>
                `;
            }
            
            previewContainer.innerHTML = previewHtml;
            
            // Atualizar cálculo total
            document.getElementById('valor-total-display').textContent = 
                `R$ ${valorTotal.toFixed(2).replace('.', ',')}`;
            document.getElementById('parcelas-info').textContent = 
                `${quantidadeParcelas} mensalidades de R$ ${valorMensalidade.toFixed(2).replace('.', ',')}`;
            
            // Mostrar resumo do desconto se ativo
            if (discountEnabled && discountValue > 0) {
                document.getElementById('summary-discount-per-installment').textContent = 
                    `R$ ${discountValue.toFixed(2).replace('.', ',')}`;
                document.getElementById('summary-total-economy').textContent = 
                    `R$ ${economiaTotal.toFixed(2).replace('.', ',')}`;
                discountSummaryCard.style.display = 'block';
            } else {
                discountSummaryCard.style.display = 'none';
            }
            
            totalCalculation.style.display = 'block';
            
            // Atualizar preview do desconto
            if (discountEnabled) {
                updateDiscountPreview();
            }
        }
        
        /**
         * Calcular datas de vencimento
         */
        function calcularDatasVencimento(dataPrimeiro, quantidade) {
            const datas = [];
            const dataBase = new Date(dataPrimeiro);
            
            for (let i = 0; i < quantidade; i++) {
                if (i === 0) {
                    datas.push(new Date(dataBase));
                } else {
                    const proximaData = new Date(dataBase);
                    proximaData.setMonth(dataBase.getMonth() + i);
                    
                    // Ajustar se o dia não existir no mês
                    if (proximaData.getDate() !== dataBase.getDate()) {
                        proximaData.setDate(0); // Último dia do mês anterior
                    }
                    
                    datas.push(proximaData);
                }
            }
            
            return datas.map(date => date.toISOString().split('T')[0]);
        }
        
        /**
         * Formatar data
         */
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
                    
                    data.data.foreach(customers => {
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
        
        /**
         * Validar formulário antes do envio
         */
        function validateForm() {
            const valorMensalidade = parseFloat(document.getElementById('valor-mensalidade').value) || 0;
            const discountValue = discountEnabled ? (parseFloat(document.getElementById('discount-value').value) || 0) : 0;
            
            // Validar desconto se ativo
            if (discountEnabled) {
                if (discountValue <= 0) {
                    showToast('Informe um valor de desconto válido!', 'error');
                    return false;
                }
                
                if (discountValue >= valorMensalidade) {
                    showToast('Desconto não pode ser maior ou igual ao valor da parcela!', 'error');
                    return false;
                }
                
                const maxDiscount = valorMensalidade * 0.50;
                if (discountValue > maxDiscount) {
                    showToast(`Desconto máximo: R$ ${maxDiscount.toFixed(2).replace('.', ',')} (50% da parcela)`, 'error');
                    return false;
                }
            }
            
            return true;
        }
        
        /**
         * Processar envio do formulário
         */
        async function submitForm(event) {
            event.preventDefault();
            
            if (!validateForm()) {
                return;
            }
            
            const formData = new FormData(document.getElementById('mensalidade-form'));
            
            // Adicionar dados do desconto se ativo
            if (discountEnabled) {
                const discountValue = parseFloat(document.getElementById('discount-value').value) || 0;
                formData.append('discount_enabled', '1');
                formData.append('discount_value', discountValue.toFixed(2));
                formData.append('discount_type', 'FIXED');
                formData.append('discount_deadline_type', 'DUE_DATE');
            } else {
                formData.append('discount_enabled', '0');
            }
            
            try {
                showToast('Criando mensalidades com desconto...', 'info');
                document.getElementById('submit-mensalidade').disabled = true;
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.text();
                
                if (result.includes('alert-success')) {
                    showToast('Mensalidades criadas com sucesso!', 'success');
                    
                    // Exibir resumo do que foi criado
                    const valorMensalidade = parseFloat(document.getElementById('valor-mensalidade').value);
                    const quantidadeParcelas = parseInt(document.getElementById('quantidade-parcelas').value);
                    const discountValue = discountEnabled ? (parseFloat(document.getElementById('discount-value').value) || 0) : 0;
                    
                    let successMessage = `✅ ${quantidadeParcelas} mensalidades criadas!<br>`;
                    successMessage += `💰 Valor: R$ ${valorMensalidade.toFixed(2).replace('.', ',')}<br>`;
                    
                    if (discountEnabled && discountValue > 0) {
                        const economiaTotal = discountValue * quantidadeParcelas;
                        successMessage += `🏷️ Desconto: R$ ${discountValue.toFixed(2).replace('.', ',')} por parcela<br>`;
                        successMessage += `💚 Economia total possível: R$ ${economiaTotal.toFixed(2).replace('.', ',')}`;
                    }
                    
                    setTimeout(() => {
                        if (confirm(successMessage + '\n\nDeseja criar outra mensalidade?')) {
                            location.reload();
                        } else {
                            window.location.href = 'index.php';
                        }
                    }, 2000);
                } else {
                    showToast('Erro ao criar mensalidades. Verifique os dados.', 'error');
                    document.getElementById('submit-mensalidade').disabled = false;
                }
            } catch (error) {
                showToast('Erro de conexão: ' + error.message, 'error');
                document.getElementById('submit-mensalidade').disabled = false;
            }
        }
        
        /**
         * Mostrar toast
         */
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
            // Event listeners para atualizar preview
            document.getElementById('valor-mensalidade').addEventListener('input', updatePreview);
            document.getElementById('quantidade-parcelas').addEventListener('change', updatePreview);
            document.getElementById('data-primeiro').addEventListener('change', updatePreview);
            document.getElementById('discount-value').addEventListener('input', function() {
                updateDiscountPreview();
                updatePreview();
            });
            
            // Controle do botão de envio
            document.getElementById('confirm-mensalidade').addEventListener('change', function() {
                document.getElementById('submit-mensalidade').disabled = !this.checked;
            });
            
            // Event listener para o formulário
            document.getElementById('mensalidade-form').addEventListener('submit', submitForm);
            
            // Carregar dados iniciais
            loadCustomers();
            loadWallets();
            
            console.log('🎉 Sistema de Mensalidades com Desconto carregado!');
            console.log('💰 Funcionalidade: Desconto até vencimento ativada');
        });
    </script>
</body>
</html>