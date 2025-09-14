<?php
/**
 * Script de Diagnóstico e Correção - Campos de Desconto
 * Execute este script para verificar e corrigir os campos de desconto
 */

// Salve este arquivo como: diagnostic_discount.php

require_once 'config.php';

echo "<h2>🔍 Diagnóstico dos Campos de Desconto</h2>";

try {
    $db = DatabaseManager::getInstance();
    $conn = $db->getConnection();
    
    // ===== 1. VERIFICAR ESTRUTURA DA TABELA INSTALLMENTS =====
    echo "<h3>1️⃣ Verificando estrutura da tabela 'installments':</h3>";
    
    $stmt = $conn->query("DESCRIBE installments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasDiscountFields = false;
    $discountFields = ['has_discount', 'discount_value', 'discount_type', 'discount_description'];
    $foundFields = [];
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
        
        if (in_array($column['Field'], $discountFields)) {
            $foundFields[] = $column['Field'];
            $hasDiscountFields = true;
        }
    }
    echo "</table>";
    
    echo "<p><strong>Campos de desconto encontrados:</strong> " . implode(', ', $foundFields) . "</p>";
    
    // ===== 2. VERIFICAR DADOS EXISTENTES =====
    echo "<h3>2️⃣ Verificando dados de mensalidades:</h3>";
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM installments");
    $total = $stmt->fetch()['total'];
    echo "<p>Total de mensalidades cadastradas: <strong>{$total}</strong></p>";
    
    if ($total > 0) {
        // Verificar se há campos de desconto
        if ($hasDiscountFields) {
            $stmt = $conn->query("
                SELECT 
                    installment_id,
                    customer_id,
                    description,
                    has_discount,
                    discount_value,
                    discount_type,
                    installment_count,
                    installment_value,
                    created_at
                FROM installments 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h4>📊 Últimas 5 mensalidades:</h4>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Cliente</th><th>Descrição</th><th>Tem Desconto</th><th>Valor Desconto</th><th>Tipo</th></tr>";
            
            foreach ($samples as $sample) {
                echo "<tr>";
                echo "<td>" . substr($sample['installment_id'], 0, 8) . "...</td>";
                echo "<td>" . $sample['customer_id'] . "</td>";
                echo "<td>" . $sample['description'] . "</td>";
                echo "<td>" . ($sample['has_discount'] ? 'SIM' : 'NÃO') . "</td>";
                echo "<td>R$ " . number_format($sample['discount_value'] ?? 0, 2, ',', '.') . "</td>";
                echo "<td>" . ($sample['discount_type'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
        } else {
            echo "<p style='color: red;'>❌ <strong>PROBLEMA ENCONTRADO:</strong> Campos de desconto não existem na tabela!</p>";
        }
    }
    
    // ===== 3. TESTAR CONSULTA DO INDEX.PHP =====
    echo "<h3>3️⃣ Testando consulta do index.php:</h3>";
    
    try {
        $testQuery = "SELECT i.*, 
                      c.name as customer_name, 
                      c.email as customer_email";
        
        if ($hasDiscountFields) {
            $testQuery .= ",
                          CASE WHEN i.has_discount = 1 AND i.discount_value > 0 THEN 
                              CONCAT('R$ ', FORMAT(i.discount_value, 2, 'de_DE'), ' por parcela') 
                          ELSE 'Sem desconto' 
                          END as discount_info";
        } else {
            $testQuery .= ", 'Sem desconto' as discount_info";
        }
        
        $testQuery .= " FROM installments i
                        LEFT JOIN customers c ON i.customer_id = c.id
                        ORDER BY i.created_at DESC 
                        LIMIT 3";
        
        $stmt = $conn->prepare($testQuery);
        $stmt->execute();
        $testResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>✅ Consulta executada com sucesso!</h4>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Cliente</th><th>Descrição</th><th>Info Desconto</th></tr>";
        
        foreach ($testResults as $result) {
            echo "<tr>";
            echo "<td>" . ($result['customer_name'] ?? $result['customer_id']) . "</td>";
            echo "<td>" . $result['description'] . "</td>";
            echo "<td><strong>" . $result['discount_info'] . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro na consulta: " . $e->getMessage() . "</p>";
    }
    
    // ===== 4. CORREÇÃO AUTOMÁTICA =====
    if (!$hasDiscountFields) {
        echo "<h3>4️⃣ 🔧 Aplicando correção automática:</h3>";
        
        try {
            // Adicionar campos de desconto
            $alterQueries = [
                "ALTER TABLE installments ADD COLUMN has_discount BOOLEAN DEFAULT 0 COMMENT 'Se a mensalidade tem desconto'",
                "ALTER TABLE installments ADD COLUMN discount_value DECIMAL(10,2) NULL COMMENT 'Valor do desconto por parcela'", 
                "ALTER TABLE installments ADD COLUMN discount_type ENUM('FIXED', 'PERCENTAGE') DEFAULT 'FIXED' COMMENT 'Tipo do desconto'",
                "ALTER TABLE installments ADD COLUMN discount_deadline_type ENUM('DUE_DATE', 'DAYS_BEFORE') DEFAULT 'DUE_DATE' COMMENT 'Prazo do desconto'",
                "ALTER TABLE installments ADD COLUMN discount_description TEXT NULL COMMENT 'Descrição do desconto'",
                "ALTER TABLE installments ADD INDEX idx_has_discount (has_discount)"
            ];
            
            foreach ($alterQueries as $i => $query) {
                try {
                    $conn->exec($query);
                    echo "<p style='color: green;'>✅ Campo " . ($i + 1) . " adicionado com sucesso</p>";
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                        echo "<p style='color: blue;'>ℹ️ Campo " . ($i + 1) . " já existe</p>";
                    } else {
                        echo "<p style='color: red;'>❌ Erro no campo " . ($i + 1) . ": " . $e->getMessage() . "</p>";
                    }
                }
            }
            
            echo "<h4>🎉 Correção aplicada! Recarregue a página para ver o resultado.</h4>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erro na correção: " . $e->getMessage() . "</p>";
        }
    }
    
    // ===== 5. VERIFICAR MENSALIDADE ESPECÍFICA =====
    echo "<h3>5️⃣ Verificando mensalidade específica (MAURO CARLOS):</h3>";
    
    $stmt = $conn->prepare("
        SELECT i.*, c.name as customer_name 
        FROM installments i
        LEFT JOIN customers c ON i.customer_id = c.id  
        WHERE c.name LIKE '%MAURO%' OR i.description LIKE '%teste%'
        ORDER BY i.created_at DESC
        LIMIT 1
    ");
    $stmt->execute();
    $mauroData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mauroData) {
        echo "<h4>📋 Dados da mensalidade do Mauro:</h4>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        foreach ($mauroData as $key => $value) {
            echo "<tr><td><strong>{$key}</strong></td><td>{$value}</td></tr>";
        }
        echo "</table>";
        
        // Verificar se tem desconto
        if (isset($mauroData['has_discount']) && $mauroData['has_discount'] && $mauroData['discount_value'] > 0) {
            echo "<p style='color: green;'>✅ <strong>Esta mensalidade TEM desconto de R$ " . number_format($mauroData['discount_value'], 2, ',', '.') . "</strong></p>";
        } else {
            echo "<p style='color: orange;'>⚠️ <strong>Esta mensalidade NÃO tem desconto cadastrado no banco</strong></p>";
            echo "<p>Possíveis causas:</p>";
            echo "<ul>";
            echo "<li>A mensalidade foi criada antes dos campos de desconto serem adicionados</li>";
            echo "<li>O desconto não foi salvo corretamente na criação</li>";
            echo "<li>Os dados de desconto estão em outra tabela</li>";
            echo "</ul>";
        }
    } else {
        echo "<p>❌ Mensalidade do Mauro não encontrada</p>";
    }
    
    // ===== 6. INSTRUÇÕES FINAIS =====
    echo "<h3>6️⃣ 📋 Próximos Passos:</h3>";
    echo "<ol>";
    echo "<li><strong>Se os campos foram adicionados:</strong> Recarregue o index.php - o problema deve estar resolvido</li>";
    echo "<li><strong>Se a mensalidade não tem desconto:</strong> Crie uma nova mensalidade de teste com desconto</li>"; 
    echo "<li><strong>Se ainda não funcionar:</strong> Verifique os logs do sistema em /logs/</li>";
    echo "</ol>";
    
    echo "<hr>";
    echo "<p><strong>⚡ TESTE RÁPIDO:</strong> <a href='index.php' target='_blank'>Clique aqui para voltar ao sistema</a> e verificar se o desconto aparece agora!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro geral: " . $e->getMessage() . "</p>";
}
?>