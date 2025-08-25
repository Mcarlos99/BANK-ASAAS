<?php
/**
 * Debug para Exclusão de Wallet ID
 * Arquivo: debug_delete_wallet.php
 */

require_once 'bootstrap.php';

echo "<h2>🔍 Debug - Exclusão de Wallet ID</h2>";

try {
    $db = DatabaseManager::getInstance();
    $conn = $db->getConnection();
    
    // 1. Verificar estrutura da tabela wallet_ids
    echo "<h3>1. Estrutura da tabela wallet_ids:</h3>";
    $stmt = $conn->query("DESCRIBE wallet_ids");
    $structure = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($structure as $column) {
        echo "<tr>";
        echo "<td><strong>{$column['Field']}</strong></td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Verificar todos os Wallet IDs atuais
    echo "<h3>2. Todos os Wallet IDs cadastrados:</h3>";
    $stmt = $conn->query("SELECT * FROM wallet_ids ORDER BY created_at DESC");
    $wallets = $stmt->fetchAll();
    
    if (empty($wallets)) {
        echo "<p style='color: orange;'>⚠️ Nenhum Wallet ID encontrado na tabela!</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
        echo "<tr><th>ID (PK)</th><th>Polo ID</th><th>Wallet ID</th><th>Nome</th><th>Ativo</th><th>Splits</th><th>Criado</th><th>Ações</th></tr>";
        
        foreach ($wallets as $wallet) {
            // Contar splits associados
            $stmtSplits = $conn->prepare("SELECT COUNT(*) as count FROM payment_splits WHERE wallet_id = ?");
            $stmtSplits->execute([$wallet['wallet_id']]);
            $splitsCount = $stmtSplits->fetch()['count'];
            
            echo "<tr>";
            echo "<td><code>{$wallet['id']}</code></td>";
            echo "<td>" . ($wallet['polo_id'] ?? '<span style="color:red;">NULL</span>') . "</td>";
            echo "<td><code>" . substr($wallet['wallet_id'], 0, 12) . "...</code></td>";
            echo "<td><strong>{$wallet['name']}</strong></td>";
            echo "<td>" . ($wallet['is_active'] ? '✅' : '❌') . "</td>";
            echo "<td><span style='color: " . ($splitsCount > 0 ? 'red' : 'green') . ";'>{$splitsCount}</span></td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($wallet['created_at'])) . "</td>";
            echo "<td>";
            if ($splitsCount == 0) {
                echo "<a href='?delete_test={$wallet['id']}' onclick='return confirm(\"Testar exclusão do wallet {$wallet['name']}?\")' style='color: red;'>🗑️ Testar Exclusão</a>";
            } else {
                echo "<span style='color: red;'>❌ Tem splits</span>";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Verificar splits existentes
    echo "<h3>3. Splits associados:</h3>";
    $stmt = $conn->query("
        SELECT ps.*, p.id as payment_id, p.status, wi.name as wallet_name
        FROM payment_splits ps 
        LEFT JOIN payments p ON ps.payment_id = p.id
        LEFT JOIN wallet_ids wi ON ps.wallet_id = wi.wallet_id
        ORDER BY ps.id DESC LIMIT 10
    ");
    $splits = $stmt->fetchAll();
    
    if (empty($splits)) {
        echo "<p style='color: green;'>✅ Nenhum split encontrado - todos os wallets podem ser excluídos!</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
        echo "<tr><th>ID Split</th><th>Wallet ID</th><th>Nome Wallet</th><th>Payment ID</th><th>Status Payment</th><th>Tipo Split</th><th>Valor</th></tr>";
        
        foreach ($splits as $split) {
            echo "<tr>";
            echo "<td>{$split['id']}</td>";
            echo "<td><code>" . substr($split['wallet_id'], 0, 12) . "...</code></td>";
            echo "<td>{$split['wallet_name']}</td>";
            echo "<td>{$split['payment_id']}</td>";
            echo "<td><span style='color: " . ($split['status'] == 'RECEIVED' ? 'green' : 'orange') . ";'>{$split['status']}</span></td>";
            echo "<td>{$split['split_type']}</td>";
            echo "<td>";
            if ($split['split_type'] == 'FIXED') {
                echo "R$ " . number_format($split['fixed_value'], 2, ',', '.');
            } else {
                echo $split['percentage_value'] . "%";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Teste de exclusão se solicitado
    if (isset($_GET['delete_test'])) {
        $deleteId = $_GET['delete_test'];
        echo "<h3>4. 🧪 Teste de Exclusão:</h3>";
        
        // Buscar informações do wallet
        $stmt = $conn->prepare("SELECT * FROM wallet_ids WHERE id = ?");
        $stmt->execute([$deleteId]);
        $walletToDelete = $stmt->fetch();
        
        if (!$walletToDelete) {
            echo "<p style='color: red;'>❌ Wallet com ID '{$deleteId}' não encontrado!</p>";
        } else {
            echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; margin: 10px 0;'>";
            echo "<h4>Wallet a ser excluído:</h4>";
            echo "<p><strong>ID:</strong> {$walletToDelete['id']}</p>";
            echo "<p><strong>Nome:</strong> {$walletToDelete['name']}</p>";
            echo "<p><strong>Wallet ID:</strong> {$walletToDelete['wallet_id']}</p>";
            echo "<p><strong>Polo ID:</strong> " . ($walletToDelete['polo_id'] ?? 'NULL') . "</p>";
            echo "</div>";
            
            // Verificar splits
            $stmtCheck = $conn->prepare("SELECT COUNT(*) as count FROM payment_splits WHERE wallet_id = ?");
            $stmtCheck->execute([$walletToDelete['wallet_id']]);
            $splitsCount = $stmtCheck->fetch()['count'];
            
            if ($splitsCount > 0) {
                echo "<p style='color: red;'>❌ <strong>NÃO PODE EXCLUIR:</strong> Tem {$splitsCount} split(s) associado(s)</p>";
            } else {
                echo "<p style='color: green;'>✅ <strong>PODE EXCLUIR:</strong> Nenhum split associado</p>";
                
                if (isset($_GET['confirm_delete'])) {
                    // Executar exclusão
                    $stmt = $conn->prepare("DELETE FROM wallet_ids WHERE id = ?");
                    $result = $stmt->execute([$deleteId]);
                    $rowsAffected = $stmt->rowCount();
                    
                    echo "<div style='background: " . ($result && $rowsAffected > 0 ? '#d4edda' : '#f8d7da') . "; padding: 15px; margin: 10px 0;'>";
                    echo "<h4>Resultado da Exclusão:</h4>";
                    echo "<p><strong>Query executada:</strong> " . ($result ? 'Sim' : 'Não') . "</p>";
                    echo "<p><strong>Linhas afetadas:</strong> {$rowsAffected}</p>";
                    
                    if ($result && $rowsAffected > 0) {
                        echo "<p style='color: green;'>✅ <strong>SUCESSO:</strong> Wallet ID excluído com sucesso!</p>";
                    } else {
                        echo "<p style='color: red;'>❌ <strong>FALHA:</strong> Não foi possível excluir o Wallet ID!</p>";
                    }
                    echo "</div>";
                    
                    echo "<p><a href='?' style='color: blue;'>🔄 Atualizar página</a></p>";
                } else {
                    echo "<p>";
                    echo "<a href='?delete_test={$deleteId}&confirm_delete=1' onclick='return confirm(\"CONFIRMA A EXCLUSÃO?\")' style='background: red; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px;'>🗑️ CONFIRMAR EXCLUSÃO</a>";
                    echo " ";
                    echo "<a href='?' style='background: gray; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px;'>❌ Cancelar</a>";
                    echo "</p>";
                }
            }
        }
    }
    
    // 5. Informações da sessão atual
    echo "<h3>5. Informações do usuário atual:</h3>";
    if (isset($_SESSION['usuario_id'])) {
        echo "<div style='background: #e3f2fd; padding: 15px; border: 1px solid #bbdefb;'>";
        echo "<p><strong>Usuário ID:</strong> {$_SESSION['usuario_id']}</p>";
        echo "<p><strong>Nome:</strong> {$_SESSION['usuario_nome']}</p>";
        echo "<p><strong>Email:</strong> {$_SESSION['usuario_email']}</p>";
        echo "<p><strong>Tipo:</strong> {$_SESSION['usuario_tipo']}</p>";
        echo "<p><strong>Polo ID:</strong> " . ($_SESSION['polo_id'] ?? '<span style="color:red;">NULL</span>') . "</p>";
        echo "<p><strong>Polo Nome:</strong> " . ($_SESSION['polo_nome'] ?? '<span style="color:red;">NULL</span>') . "</p>";
        echo "</div>";
    } else {
        echo "<p style='color:red;'>❌ Usuário não logado!</p>";
        echo "<p><a href='login.php'>👤 Fazer Login</a></p>";
    }
    
    // 6. SQL para verificar manualmente
    echo "<h3>6. SQLs para verificação manual:</h3>";
    echo "<div style='background: #f5f5f5; padding: 15px; font-family: monospace;'>";
    echo "<p><strong>Verificar todos os Wallet IDs:</strong></p>";
    echo "<code>SELECT * FROM wallet_ids;</code><br><br>";
    
    echo "<p><strong>Verificar splits por Wallet ID:</strong></p>";
    echo "<code>SELECT ps.*, p.status FROM payment_splits ps LEFT JOIN payments p ON ps.payment_id = p.id WHERE ps.wallet_id = 'SEU_WALLET_ID_AQUI';</code><br><br>";
    
    echo "<p><strong>Excluir Wallet ID manualmente:</strong></p>";
    echo "<code>DELETE FROM wallet_ids WHERE id = 'ID_DO_REGISTRO_AQUI';</code><br><br>";
    
    echo "<p><strong>Verificar se exclusão funcionou:</strong></p>";
    echo "<code>SELECT COUNT(*) as total FROM wallet_ids;</code><br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'><strong>ERRO:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php' style='color: blue;'>← Voltar ao Sistema</a></p>";
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { width: 100%; }
    th, td { padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
</style>