<?php
/**
 * Script de Debug para Wallet IDs
 * Arquivo: debug_wallet.php
 * 
 * Execute este arquivo para verificar a estrutura da tabela e dados
 */

require_once 'bootstrap.php';

echo "<h2>Debug - Wallet IDs e Polo ID</h2>";

try {
    $db = DatabaseManager::getInstance();
    
    // 1. Verificar estrutura da tabela
    echo "<h3>1. Estrutura da tabela wallet_ids:</h3>";
    $stmt = $db->getConnection()->query("DESCRIBE wallet_ids");
    $structure = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($structure as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Verificar dados existentes
    echo "<h3>2. Dados atuais na tabela:</h3>";
    $stmt = $db->getConnection()->query("SELECT * FROM wallet_ids ORDER BY created_at DESC LIMIT 10");
    $data = $stmt->fetchAll();
    
    if (empty($data)) {
        echo "<p>Nenhum Wallet ID encontrado.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Polo ID</th><th>Wallet ID</th><th>Nome</th><th>Ativo</th><th>Criado</th></tr>";
        foreach ($data as $wallet) {
            echo "<tr>";
            echo "<td>{$wallet['id']}</td>";
            echo "<td>" . ($wallet['polo_id'] ?? '<span style="color:red;">NULL</span>') . "</td>";
            echo "<td>{$wallet['wallet_id']}</td>";
            echo "<td>{$wallet['name']}</td>";
            echo "<td>" . ($wallet['is_active'] ? 'Sim' : 'Não') . "</td>";
            echo "<td>{$wallet['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Verificar usuário atual
    echo "<h3>3. Informações do usuário atual:</h3>";
    if (isset($_SESSION['usuario_id'])) {
        echo "<p><strong>Usuário ID:</strong> {$_SESSION['usuario_id']}</p>";
        echo "<p><strong>Nome:</strong> {$_SESSION['usuario_nome']}</p>";
        echo "<p><strong>Email:</strong> {$_SESSION['usuario_email']}</p>";
        echo "<p><strong>Tipo:</strong> {$_SESSION['usuario_tipo']}</p>";
        echo "<p><strong>Polo ID:</strong> " . ($_SESSION['polo_id'] ?? '<span style="color:red;">NULL</span>') . "</p>";
        echo "<p><strong>Polo Nome:</strong> " . ($_SESSION['polo_nome'] ?? '<span style="color:red;">NULL</span>') . "</p>";
    } else {
        echo "<p style='color:red;'>Usuário não logado!</p>";
    }
    
    // 4. Verificar polos existentes
    echo "<h3>4. Polos cadastrados:</h3>";
    $stmt = $db->getConnection()->query("SELECT * FROM polos ORDER BY nome");
    $polos = $stmt->fetchAll();
    
    if (empty($polos)) {
        echo "<p style='color:red;'>Nenhum polo encontrado!</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Código</th><th>Ativo</th></tr>";
        foreach ($polos as $polo) {
            echo "<tr>";
            echo "<td>{$polo['id']}</td>";
            echo "<td>{$polo['nome']}</td>";
            echo "<td>{$polo['codigo']}</td>";
            echo "<td>" . ($polo['is_active'] ? 'Sim' : 'Não') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 5. Teste de inserção
    echo "<h3>5. Teste de inserção manual:</h3>";
    if (isset($_GET['test_insert'])) {
        $testData = [
            'id' => 'test_' . time(),
            'polo_id' => $_SESSION['polo_id'] ?? 1,
            'wallet_id' => '12345678-1234-1234-1234-123456789012',
            'name' => 'Teste Manual',
            'description' => 'Teste via debug',
            'is_active' => 1
        ];
        
        $result = $db->saveWalletId($testData);
        
        if ($result) {
            echo "<p style='color:green;'>✅ Teste de inserção: SUCESSO!</p>";
        } else {
            echo "<p style='color:red;'>❌ Teste de inserção: FALHOU!</p>";
        }
    } else {
        echo "<p><a href='?test_insert=1'>Clique aqui para testar inserção manual</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Voltar ao sistema</a></p>";
?>