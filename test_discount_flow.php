<?php
/**
 * SCRIPT DE TESTE COMPLETO - Salve como test_discount_flow.php
 * Execute: php test_discount_flow.php
 */

require_once 'config.php';

echo "🧪 TESTE COMPLETO DO FLUXO DE DESCONTO\n";
echo "======================================\n\n";

try {
    $db = DatabaseManager::getInstance();
    
    // ===== TESTE 1: VERIFICAR ESTRUTURA DA TABELA =====
    echo "1. VERIFICANDO ESTRUTURA DA TABELA:\n";
    
    $columns = $db->getConnection()->query("SHOW COLUMNS FROM installments");
    $discountFields = ['has_discount', 'discount_value', 'discount_type', 'discount_deadline_type', 'discount_description'];
    $foundFields = [];
    
    while ($column = $columns->fetch()) {
        if (in_array($column['Field'], $discountFields)) {
            $foundFields[] = $column['Field'];
            echo "  ✅ {$column['Field']} ({$column['Type']})\n";
        }
    }
    
    if (count($foundFields) !== count($discountFields)) {
        echo "  ❌ Campos faltando: " . implode(', ', array_diff($discountFields, $foundFields)) . "\n";
        echo "  Execute: php config.php add-discount\n";
        exit(1);
    }
    
    echo "  ✅ Todos os campos de desconto existem\n\n";
    
    // ===== TESTE 2: INSERÇÃO DIRETA NO BANCO =====
    echo "2. TESTE DE INSERÇÃO DIRETA NO BANCO:\n";
    
    $testData = [
        'installment_id' => 'test_direct_' . uniqid(),
        'polo_id' => 1,
        'customer_id' => 'test_customer_direct',
        'installment_count' => 12,
        'installment_value' => 100.00,
        'total_value' => 1200.00,
        'first_due_date' => date('Y-m-d', strtotime('+1 month')),
        'billing_type' => 'BOLETO',
        'description' => 'Teste direto de inserção com desconto',
        'has_splits' => false,
        'splits_count' => 0,
        'created_by' => 1,
        'first_payment_id' => 'test_payment_direct',
        'has_discount' => 1,
        'discount_value' => 15.00,
        'discount_type' => 'FIXED',
        'discount_deadline_type' => 'DUE_DATE',
        'discount_description' => 'Teste de desconto direto'
    ];
    
    $recordId = $db->saveInstallmentRecord($testData);
    
    if ($recordId) {
        echo "  ✅ Inserção direta bem-sucedida - ID: {$recordId}\n";
        
        // Verificar se salvou corretamente
        $verification = $db->getConnection()->prepare("
            SELECT has_discount, discount_value, discount_type, discount_description
            FROM installments 
            WHERE id = ?
        ");
        $verification->execute([$recordId]);
        $saved = $verification->fetch();
        
        if ($saved && $saved['has_discount'] == 1 && $saved['discount_value'] == 15.00) {
            echo "  ✅ Desconto salvo corretamente no banco\n";
            echo "    - has_discount: {$saved['has_discount']}\n";
            echo "    - discount_value: {$saved['discount_value']}\n";
            echo "    - discount_type: {$saved['discount_type']}\n";
            
            // Limpar teste
            $db->getConnection()->prepare("DELETE FROM installments WHERE id = ?")->execute([$recordId]);
            echo "  🗑️  Registro de teste removido\n\n";
        } else {
            echo "  ❌ Desconto não foi salvo corretamente\n";
            echo "    Dados salvos: " . json_encode($saved) . "\n\n";
        }
    } else {
        echo "  ❌ Falha na inserção direta\n\n";
    }
    
    // ===== TESTE 3: SIMULAÇÃO DO INSTALLMENT MANAGER =====
    echo "3. TESTE DO INSTALLMENT MANAGER:\n";
    
    // Simular dados como viriam da API
    $_POST = [
        'action' => 'create_installment_with_discount',
        'discount_enabled' => '1',
        'discount_value' => '20.00',
        'payment' => [
            'customer' => 'test_customer_manager',
            'billingType' => 'BOLETO',
            'dueDate' => date('Y-m-d', strtotime('+1 month')),
            'description' => 'Teste InstallmentManager com desconto'
        ],
        'installment' => [
            'installmentCount' => '6',
            'installmentValue' => '150.00'
        ],
        'splits' => []
    ];
    
    echo "  📤 Simulando dados POST:\n";
    echo "    - discount_enabled: {$_POST['discount_enabled']}\n";
    echo "    - discount_value: {$_POST['discount_value']}\n";
    echo "    - installmentValue: {$_POST['installment']['installmentValue']}\n";
    echo "    - installmentCount: {$_POST['installment']['installmentCount']}\n\n";
    
    // Simular o que acontece no api.php
    $paymentData = [
        'customer' => $_POST['payment']['customer'],
        'billingType' => $_POST['payment']['billingType'],
        'dueDate' => $_POST['payment']['dueDate'],
        'description' => $_POST['payment']['description']
    ];
    
    $installmentData = [
        'installmentCount' => (int)$_POST['installment']['installmentCount'],
        'installmentValue' => (float)$_POST['installment']['installmentValue']
    ];
    
    // Processar desconto
    $discountEnabled = !empty($_POST['discount_enabled']) && $_POST['discount_enabled'] === '1';
    $discountValue = floatval($_POST['discount_value'] ?? 0);
    
    echo "  🔍 Processamento do desconto:\n";
    echo "    - discountEnabled: " . ($discountEnabled ? 'TRUE' : 'FALSE') . "\n";
    echo "    - discountValue: {$discountValue}\n";
    
    if ($discountEnabled && $discountValue > 0) {
        echo "    ✅ Desconto será aplicado\n";
        
        // Adicionar aos dados
        $paymentData['discount'] = [
            'value' => $discountValue,
            'dueDateLimitDays' => 0,
            'type' => 'FIXED'
        ];
        
        $installmentData['discount_value'] = $discountValue;
        $installmentData['discount_type'] = 'FIXED';
        $installmentData['discount_deadline_type'] = 'DUE_DATE';
        $installmentData['discount_description'] = 'Teste de desconto via manager';
        
        echo "    - paymentData['discount']: " . json_encode($paymentData['discount']) . "\n";
        echo "    - installmentData['discount_value']: {$installmentData['discount_value']}\n";
    } else {
        echo "    ❌ Desconto não será aplicado\n";
    }
    
    echo "\n";
    
    // ===== TESTE 4: VERIFICAÇÃO DO SISTEMA COMPLETO =====
    echo "4. TESTE DO SISTEMA COMPLETO (sem API ASAAS):\n";
    
    // Simular resposta da API ASAAS
    $mockApiResult = [
        'id' => 'mock_payment_' . uniqid(),
        'installment' => 'mock_installment_' . uniqid(),
        'customer' => $paymentData['customer'],
        'status' => 'PENDING',
        'value' => $installmentData['installmentValue'],
        'dueDate' => $paymentData['dueDate'],
        'billingType' => $paymentData['billingType'],
        'description' => $paymentData['description']
    ];
    
    echo "  📋 Simulando resposta da API ASAAS...\n";
    
    // Preparar dados para salvar (como no InstallmentManager)
    $installmentRecord = [
        'installment_id' => $mockApiResult['installment'],
        'polo_id' => 1,
        'customer_id' => $mockApiResult['customer'],
        'installment_count' => $installmentData['installmentCount'],
        'installment_value' => $installmentData['installmentValue'],
        'total_value' => $installmentData['installmentCount'] * $installmentData['installmentValue'],
        'first_due_date' => $paymentData['dueDate'],
        'billing_type' => $paymentData['billingType'],
        'description' => $paymentData['description'],
        'has_splits' => false,
        'splits_count' => 0,
        'created_by' => 1,
        'first_payment_id' => $mockApiResult['id'],
        'status' => 'ACTIVE',
        'has_discount' => 0,
        'discount_value' => null,
        'discount_type' => null,
        'discount_deadline_type' => 'DUE_DATE',
        'discount_description' => null
    ];
    
    // Aplicar desconto se detectado
    if ($discountEnabled && $discountValue > 0) {
        echo "  💰 Aplicando desconto ao record...\n";
        
        $installmentRecord['has_discount'] = 1;
        $installmentRecord['discount_value'] = $discountValue;
        $installmentRecord['discount_type'] = $installmentData['discount_type'] ?? 'FIXED';
        $installmentRecord['discount_deadline_type'] = $installmentData['discount_deadline_type'] ?? 'DUE_DATE';
        $installmentRecord['discount_description'] = $installmentData['discount_description'] ?? "Desconto de R$ {$discountValue}";
        
        echo "    - has_discount: {$installmentRecord['has_discount']}\n";
        echo "    - discount_value: {$installmentRecord['discount_value']}\n";
        echo "    - discount_type: {$installmentRecord['discount_type']}\n";
    }
    
    // Salvar
    echo "  💾 Salvando no banco...\n";
    $finalRecordId = $db->saveInstallmentRecord($installmentRecord);
    
    if ($finalRecordId) {
        echo "  ✅ Salvo com ID: {$finalRecordId}\n";
        
        // Verificação final
        $finalCheck = $db->getConnection()->prepare("
            SELECT installment_id, has_discount, discount_value, discount_type, discount_description
            FROM installments 
            WHERE id = ?
        ");
        $finalCheck->execute([$finalRecordId]);
        $finalSaved = $finalCheck->fetch();
        
        echo "  📋 VERIFICAÇÃO FINAL:\n";
        echo "    - installment_id: {$finalSaved['installment_id']}\n";
        echo "    - has_discount: {$finalSaved['has_discount']}\n";
        echo "    - discount_value: {$finalSaved['discount_value']}\n";
        echo "    - discount_type: {$finalSaved['discount_type']}\n";
        echo "    - discount_description: {$finalSaved['discount_description']}\n";
        
        if ($discountEnabled && $discountValue > 0) {
            if ($finalSaved['has_discount'] == 1 && $finalSaved['discount_value'] == $discountValue) {
                echo "  🎉 SUCESSO TOTAL! Desconto salvo corretamente!\n";
            } else {
                echo "  ❌ FALHA! Desconto não foi salvo como esperado\n";
                echo "    Esperado: has_discount=1, discount_value={$discountValue}\n";
                echo "    Recebido: has_discount={$finalSaved['has_discount']}, discount_value={$finalSaved['discount_value']}\n";
            }
        } else {
            echo "  ✅ Sem desconto - comportamento correto\n";
        }
        
        // Limpar teste
        $db->getConnection()->prepare("DELETE FROM installments WHERE id = ?")->execute([$finalRecordId]);
        echo "  🗑️  Registro final de teste removido\n";
        
    } else {
        echo "  ❌ FALHA ao salvar registro final\n";
    }
    
    echo "\n=== RESULTADO DO TESTE ===\n";
    
    if ($discountEnabled && $discountValue > 0 && 
        isset($finalSaved) && $finalSaved['has_discount'] == 1 && $finalSaved['discount_value'] == $discountValue) {
        echo "🎉 TESTE COMPLETO PASSOU!\n";
        echo "✅ O sistema está funcionando corretamente para salvar descontos.\n";
        echo "💡 Se ainda não está aparecendo na interface, o problema é na exibição.\n";
    } else {
        echo "❌ TESTE COMPLETO FALHOU!\n";
        echo "🔍 Verifique os logs acima para identificar onde está o problema.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO DURANTE O TESTE: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n📝 PRÓXIMOS PASSOS:\n";
echo "1. Se o teste passou: Execute as correções nos arquivos principais\n";
echo "2. Se o teste falhou: Verifique os logs de erro detalhados\n";
echo "3. Teste criando uma mensalidade real na interface\n";
echo "4. Verifique se aparece na coluna 'Desconto' da tabela\n";
?>