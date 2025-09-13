<?php
/**
 * Script de Verificação - Sistema de Desconto
 * Arquivo: verify_discount_system.php
 * 
 * Execute este script para verificar se tudo está instalado corretamente
 */

echo "<h2>🔍 Verificação do Sistema de Desconto</h2>";
echo "<hr>";

$checks = [];
$errors = [];
$warnings = [];

// 1. Verificar se arquivos existem
echo "<h3>📁 Verificando Arquivos</h3>";

$requiredFiles = [
    'installment_discount_manager.php' => 'Gerenciador de Desconto',
    'api.php' => 'API Principal',
    'index.php' => 'Interface Principal',
    'bootstrap.php' => 'Bootstrap do Sistema'
];

foreach ($requiredFiles as $file => $desc) {
    if (file_exists($file)) {
        echo "✅ {$desc}: <strong>{$file}</strong><br>";
        $checks[] = "Arquivo {$file} existe";
    } else {
        echo "❌ {$desc}: <strong>{$file}</strong> NÃO ENCONTRADO<br>";
        $errors[] = "Arquivo {$file} não encontrado";
    }
}

// 2. Verificar classes
echo "<hr><h3>🔧 Verificando Classes</h3>";

try {
    if (file_exists('installment_discount_manager.php')) {
        require_once 'installment_discount_manager.php';
        
        if (class_exists('InstallmentDiscountManager')) {
            echo "✅ Classe <strong>InstallmentDiscountManager</strong> carregada<br>";
            $checks[] = "InstallmentDiscountManager disponível";
            
            // Testar instanciação
            try {
                if (file_exists('bootstrap.php')) {
                    require_once 'bootstrap.php';
                }
                
                $manager = new InstallmentDiscountManager();
                echo "✅ <strong>InstallmentDiscountManager</strong> instanciado com sucesso<br>";
                $checks[] = "Instanciação bem-sucedida";
            } catch (Exception $e) {
                echo "⚠️ Erro ao instanciar: " . htmlspecialchars($e->getMessage()) . "<br>";
                $warnings[] = "Erro na instanciação: " . $e->getMessage();
            }
        } else {
            echo "❌ Classe <strong>InstallmentDiscountManager</strong> não encontrada<br>";
            $errors[] = "Classe InstallmentDiscountManager não carregada";
        }
    }
} catch (Exception $e) {
    echo "❌ Erro ao carregar classes: " . htmlspecialchars($e->getMessage()) . "<br>";
    $errors[] = "Erro ao carregar: " . $e->getMessage();
}

// 3. Verificar banco de dados
echo "<hr><h3>🗄️ Verificando Banco de Dados</h3>";

try {
    if (class_exists('DatabaseManager')) {
        $db = DatabaseManager::getInstance();
        echo "✅ Conexão com banco: <strong>OK</strong><br>";
        $checks[] = "Conexão com banco estabelecida";
        
        // Verificar tabelas
        $tables = ['installments', 'installment_discounts'];
        foreach ($tables as $table) {
            try {
                $result = $db->getConnection()->query("SHOW TABLES LIKE '{$table}'");
                if ($result->rowCount() > 0) {
                    echo "✅ Tabela <strong>{$table}</strong>: existe<br>";
                    $checks[] = "Tabela {$table} existe";
                    
                    // Verificar colunas específicas se for installments
                    if ($table === 'installments') {
                        $discountColumns = ['has_discount', 'discount_type', 'discount_value'];
                        foreach ($discountColumns as $column) {
                            $columnCheck = $db->getConnection()->query("SHOW COLUMNS FROM installments LIKE '{$column}'");
                            if ($columnCheck->rowCount() > 0) {
                                echo "✅ Coluna <strong>{$column}</strong>: existe<br>";
                                $checks[] = "Coluna {$column} adicionada";
                            } else {
                                echo "⚠️ Coluna <strong>{$column}</strong>: não existe (será criada automaticamente)<br>";
                                $warnings[] = "Coluna {$column} será criada na primeira execução";
                            }
                        }
                    }
                } else {
                    echo "⚠️ Tabela <strong>{$table}</strong>: não existe (será criada automaticamente)<br>";
                    $warnings[] = "Tabela {$table} será criada na primeira execução";
                }
            } catch (Exception $e) {
                echo "❌ Erro ao verificar tabela {$table}: " . htmlspecialchars($e->getMessage()) . "<br>";
                $errors[] = "Erro na tabela {$table}: " . $e->getMessage();
            }
        }
    } else {
        echo "❌ DatabaseManager não disponível<br>";
        $errors[] = "DatabaseManager não encontrado";
    }
} catch (Exception $e) {
    echo "❌ Erro no banco: " . htmlspecialchars($e->getMessage()) . "<br>";
    $errors[] = "Erro no banco: " . $e->getMessage();
}

// 4. Verificar API
echo "<hr><h3>🔌 Verificando API</h3>";

$apiFile = 'api.php';
if (file_exists($apiFile)) {
    $apiContent = file_get_contents($apiFile);
    
    $requiredCases = [
        'create_installment_with_discount' => 'Criar mensalidade com desconto',
        'get-installment-with-discount' => 'Buscar mensalidade com desconto (opcional)',
        'discount-report' => 'Relatório de descontos (opcional)'
    ];
    
    foreach ($requiredCases as $case => $desc) {
        if (strpos($apiContent, "case '{$case}':") !== false) {
            echo "✅ Case <strong>{$case}</strong>: encontrado<br>";
            $checks[] = "API case {$case} adicionado";
        } else {
            if ($case === 'create_installment_with_discount') {
                echo "❌ Case <strong>{$case}</strong>: NÃO ENCONTRADO (OBRIGATÓRIO)<br>";
                $errors[] = "Case obrigatório {$case} não encontrado na API";
            } else {
                echo "⚠️ Case <strong>{$case}</strong>: não encontrado (opcional)<br>";
                $warnings[] = "Case opcional {$case} não adicionado";
            }
        }
    }
} else {
    echo "❌ Arquivo api.php não encontrado<br>";
    $errors[] = "Arquivo api.php não existe";
}

// 5. Verificar interface
echo "<hr><h3>🖥️ Verificando Interface</h3>";

$indexFile = 'index.php';
if (file_exists($indexFile)) {
    $indexContent = file_get_contents($indexFile);
    
    $interfaceElements = [
        'create_installment_with_discount' => 'Action do formulário',
        'enable-discount' => 'Checkbox de ativar desconto',
        'discount-type' => 'Select do tipo de desconto',
        'discount-value' => 'Input do valor do desconto'
    ];
    
    foreach ($interfaceElements as $element => $desc) {
        if (strpos($indexContent, $element) !== false) {
            echo "✅ {$desc}: encontrado<br>";
            $checks[] = "Interface {$desc} presente";
        } else {
            echo "⚠️ {$desc}: não encontrado<br>";
            $warnings[] = "Elemento de interface {$desc} não encontrado";
        }
    }
} else {
    echo "❌ Arquivo index.php não encontrado<br>";
    $errors[] = "Arquivo index.php não existe";
}

// 6. Resumo final
echo "<hr><h3>📊 Resumo da Verificação</h3>";

echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>✅ VERIFICAÇÕES PASSARAM:</strong> " . count($checks) . "<br>";
foreach (array_slice($checks, 0, 5) as $check) {
    echo "• " . htmlspecialchars($check) . "<br>";
}
if (count($checks) > 5) {
    echo "• ... e mais " . (count($checks) - 5) . " verificações<br>";
}
echo "</div>";

if (!empty($warnings)) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>⚠️ AVISOS:</strong> " . count($warnings) . "<br>";
    foreach ($warnings as $warning) {
        echo "• " . htmlspecialchars($warning) . "<br>";
    }
    echo "</div>";
}

if (!empty($errors)) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ ERROS ENCONTRADOS:</strong> " . count($errors) . "<br>";
    foreach ($errors as $error) {
        echo "• " . htmlspecialchars($error) . "<br>";
    }
    echo "</div>";
}

// 7. Instruções finais
echo "<hr><h3>🎯 Próximos Passos</h3>";

if (empty($errors)) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
    echo "<strong>🎉 SISTEMA PRONTO!</strong><br>";
    echo "O sistema de desconto está instalado e funcionando.<br><br>";
    echo "<strong>Para testar:</strong><br>";
    echo "1. Acesse sua interface principal<br>";
    echo "2. Vá em 'Mensalidades'<br>";
    echo "3. Procure pela seção 'Sistema de Desconto'<br>";
    echo "4. Teste criando uma mensalidade com desconto<br>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<strong>🔧 CORREÇÕES NECESSÁRIAS:</strong><br>";
    
    if (in_array('Case obrigatório create_installment_with_discount não encontrado na API', $errors)) {
        echo "<br><strong>1. Adicionar código na API:</strong><br>";
        echo "• Abra o arquivo <code>api.php</code><br>";
        echo "• Localize a linha com <code>switch (\$action) {</code><br>";
        echo "• Adicione o código do patch logo após um case existente<br>";
        echo "• Salve o arquivo<br>";
    }
    
    if (in_array('Arquivo installment_discount_manager.php não encontrado', $errors)) {
        echo "<br><strong>2. Criar arquivo do gerenciador:</strong><br>";
        echo "• Crie o arquivo <code>installment_discount_manager.php</code><br>";
        echo "• Cole o código completo do gerenciador<br>";
        echo "• Salve na raiz do projeto<br>";
    }
    
    echo "<br><strong>3. Após as correções:</strong><br>";
    echo "• Execute este script novamente<br>";
    echo "• Teste a funcionalidade na interface<br>";
    echo "</div>";
}

// 8. Informações técnicas
echo "<hr><h3>ℹ️ Informações Técnicas</h3>";
echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";
echo "<strong>Diretório Atual:</strong> " . __DIR__ . "<br>";
echo "<strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "<br>";
echo "<strong>Memória Usada:</strong> " . round(memory_get_usage() / 1024 / 1024, 2) . " MB<br>";

// 9. Links úteis
echo "<hr><h3>🔗 Links Úteis</h3>";
echo "<a href='api.php?action=test-api' target='_blank'>Testar API ASAAS</a><br>";
echo "<a href='.' target='_blank'>Interface Principal</a><br>";
if (file_exists('installment_discount_manager.php')) {
    echo "<a href='installment_discount_manager.php?migrate' target='_blank'>Executar Migração</a><br>";
}

echo "<hr>";
echo "<small>Sistema de Verificação v1.0 - " . date('Y-m-d H:i:s') . "</small>";

?>