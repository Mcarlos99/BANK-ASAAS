<?php
/**
 * SCRIPT PARA EXECUTAR VIA LINHA DE COMANDO
 * Execute: php fix_discount_fields.php
 * Salve este arquivo como fix_discount_fields.php
 */

require_once 'config.php';

echo "🔧 Verificando e corrigindo campos de desconto...\n";

try {
    $db = DatabaseManager::getInstance();
    
    // Verificar se tabela installments existe
    $checkTable = $db->getConnection()->query("SHOW TABLES LIKE 'installments'");
    if ($checkTable->rowCount() == 0) {
        echo "❌ Tabela installments não existe!\n";
        echo "Execute: php config.php create-tables\n";
        exit(1);
    }
    
    echo "✅ Tabela installments existe\n";
    
    // Verificar campos de desconto
    $checkFields = $db->getConnection()->query("SHOW COLUMNS FROM installments LIKE 'has_discount'");
    if ($checkFields->rowCount() == 0) {
        echo "⚠️ Campos de desconto não existem, adicionando...\n";
        
        $alterQueries = [
            "ALTER TABLE installments ADD COLUMN has_discount BOOLEAN DEFAULT 0 AFTER status",
            "ALTER TABLE installments ADD COLUMN discount_value DECIMAL(10,2) NULL COMMENT 'Valor fixo do desconto por parcela' AFTER has_discount",
            "ALTER TABLE installments ADD COLUMN discount_type ENUM('FIXED', 'PERCENTAGE') DEFAULT 'FIXED' COMMENT 'Tipo do desconto' AFTER discount_value",
            "ALTER TABLE installments ADD COLUMN discount_deadline_type ENUM('DUE_DATE', 'DAYS_BEFORE', 'CUSTOM') DEFAULT 'DUE_DATE' COMMENT 'Tipo de prazo' AFTER discount_type",
            "ALTER TABLE installments ADD COLUMN discount_description TEXT NULL COMMENT 'Descrição do desconto' AFTER discount_deadline_type",
            "ALTER TABLE installments ADD INDEX idx_has_discount (has_discount)"
        ];
        
        foreach ($alterQueries as $query) {
            try {
                $db->getConnection()->exec($query);
                echo "  ✅ Executado: " . substr($query, 0, 50) . "...\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                    echo "  ⚠️ Campo já existe: " . substr($query, 0, 50) . "...\n";
                } else {
                    echo "  ❌ Erro: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "✅ Campos de desconto adicionados!\n";
    } else {
        echo "✅ Campos de desconto já existem\n";
    }
    
    // Verificar estrutura final
    echo "\n📋 Verificando estrutura final da tabela:\n";
    $columns = $db->getConnection()->query("SHOW COLUMNS FROM installments");
    
    $discountFields = ['has_discount', 'discount_value', 'discount_type', 'discount_deadline_type', 'discount_description'];
    $foundFields = [];
    
    while ($column = $columns->fetch()) {
        if (in_array($column['Field'], $discountFields)) {
            $foundFields[] = $column['Field'];
            echo "  ✅ {$column['Field']} ({$column['Type']})\n";
        }
    }
    
    if (count($foundFields) === count($discountFields)) {
        echo "\n🎉 Todos os campos de desconto estão presentes!\n";
        
        // Testar inserção
        echo "\n🧪 Testando inserção com desconto...\n";
        
        $testData = [
            'installment_id' => 'test_' . uniqid(),
            'polo_id' => 1,
            'customer_id' => 'test_customer',
            'installment_count' => 12,
            'installment_value' => 100.00,
            'total_value' => 1200.00,
            'first_due_date' => date('Y-m-d', strtotime('+1 month')),
            'billing_type' => 'BOLETO',
            'description' => 'Teste de mensalidade com desconto',
            'has_splits' => false,
            'splits_count' => 0,
            'created_by' => 1,
            'first_payment_id' => 'test_payment',
            'has_discount' => 1,
            'discount_value' => 10.00,
            'discount_type' => 'FIXED',
            'discount_deadline_type' => 'DUE_DATE',
            'discount_description' => 'Teste de desconto'
        ];
        
        try {
            $recordId = $db->saveInstallmentRecord($testData);
            echo "  ✅ Teste inserção: ID {$recordId}\n";
            
            // Verificar se salvou
            $verification = $db->getConnection()->prepare("
                SELECT has_discount, discount_value, discount_type 
                FROM installments 
                WHERE id = ?
            ");
            $verification->execute([$recordId]);
            $saved = $verification->fetch();
            
            if ($saved && $saved['has_discount'] == 1 && $saved['discount_value'] == 10.00) {
                echo "  ✅ Desconto salvo corretamente!\n";
                
                // Limpar teste
                $db->getConnection()->prepare("DELETE FROM installments WHERE id = ?")->execute([$recordId]);
                echo "  🗑️ Registro de teste removido\n";
                
                echo "\n🎉 CORREÇÃO CONCLUÍDA COM SUCESSO!\n";
                echo "💰 O sistema agora salvará os descontos corretamente.\n";
                
            } else {
                echo "  ❌ Desconto não foi salvo corretamente\n";
                echo "  Dados salvos: " . json_encode($saved) . "\n";
            }
            
        } catch (Exception $e) {
            echo "  ❌ Erro no teste: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "\n❌ Campos faltando: " . implode(', ', array_diff($discountFields, $foundFields)) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n📝 INSTRUÇÕES FINAIS:\n";
echo "1. Execute este script para corrigir os campos: php fix_discount_fields.php\n";
echo "2. Teste criando uma nova mensalidade com desconto\n";
echo "3. Verifique se o desconto aparece na coluna 'Desconto' da tabela\n";
echo "4. Se ainda não funcionar, execute: php config.php add-discount\n";
?>