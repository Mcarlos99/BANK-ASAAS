<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Debug Wallet IDs</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .wallet { border: 1px solid #ddd; margin: 10px 0; padding: 10px; }
        .wallet.duplicate { background-color: #ffebee; border-color: #f44336; }
        .wallet.unique { background-color: #e8f5e8; border-color: #4caf50; }
        .actions { margin-top: 10px; }
        button { margin: 5px; padding: 5px 10px; }
        .btn-danger { background: #f44336; color: white; border: none; }
        .btn-success { background: #4caf50; color: white; border: none; }
    </style>
</head>
<body>
    <h1>🔍 Debug Wallet IDs</h1>
    
    <?php
    require_once 'config.php';
    
    try {
        $db = DatabaseManager::getInstance();
        
        echo "<h2>📊 Análise dos Wallet IDs</h2>";
        
        // Buscar todos os Wallet IDs
        $stmt = $db->getConnection()->query("
            SELECT id, polo_id, wallet_id, name, description, is_active, created_at
            FROM wallet_ids 
            ORDER BY id DESC
        ");
        $allWallets = $stmt->fetchAll();
        
        echo "<p><strong>Total no banco:</strong> " . count($allWallets) . "</p>";
        
        // Agrupar por UUID para detectar duplicatas
        $grouped = [];
        foreach ($allWallets as $wallet) {
            $uuid = $wallet['wallet_id'];
            if (!isset($grouped[$uuid])) {
                $grouped[$uuid] = [];
            }
            $grouped[$uuid][] = $wallet;
        }
        
        echo "<h3>🔍 Análise por UUID:</h3>";
        
        foreach ($grouped as $uuid => $wallets) {
            $count = count($wallets);
            $isDuplicate = $count > 1;
            
            echo "<div class='wallet " . ($isDuplicate ? 'duplicate' : 'unique') . "'>";
            echo "<h4>" . ($isDuplicate ? "⚠️ DUPLICADO" : "✅ ÚNICO") . "</h4>";
            echo "<p><strong>UUID:</strong> <code>{$uuid}</code></p>";
            echo "<p><strong>Registros:</strong> {$count}</p>";
            
            foreach ($wallets as $wallet) {
                echo "<div style='margin-left: 20px; border-left: 2px solid #ccc; padding-left: 10px;'>";
                echo "<strong>ID:</strong> {$wallet['id']} | ";
                echo "<strong>Nome:</strong> {$wallet['name']} | ";
                echo "<strong>Polo:</strong> " . ($wallet['polo_id'] ?: 'Global') . " | ";
                echo "<strong>Ativo:</strong> " . ($wallet['is_active'] ? 'Sim' : 'Não') . " | ";
                echo "<strong>Criado:</strong> {$wallet['created_at']}<br>";
                
                if (isset($wallet['description']) && $wallet['description']) {
                    echo "<strong>Descrição:</strong> {$wallet['description']}<br>";
                }
                
                if ($isDuplicate) {
                    echo "<div class='actions'>";
                    echo "<button class='btn-danger' onclick='deleteWalletRecord({$wallet['id']})'>🗑️ Excluir Este</button>";
                    echo "</div>";
                }
                echo "</div><br>";
            }
            echo "</div>";
        }
        
        // Verificar se há problema de nomes
        echo "<h3>🔍 Análise por Nome:</h3>";
        $stmt = $db->getConnection()->query("
            SELECT name, COUNT(*) as count, GROUP_CONCAT(id) as ids, GROUP_CONCAT(wallet_id) as uuids
            FROM wallet_ids 
            GROUP BY name 
            HAVING COUNT(*) > 1
        ");
        $nameConflicts = $stmt->fetchAll();
        
        if (empty($nameConflicts)) {
            echo "<p>✅ Nenhum conflito de nomes encontrado</p>";
        } else {
            foreach ($nameConflicts as $conflict) {
                echo "<div class='wallet duplicate'>";
                echo "<h4>⚠️ NOME DUPLICADO: {$conflict['name']}</h4>";
                echo "<p><strong>Registros:</strong> {$conflict['count']}</p>";
                echo "<p><strong>IDs:</strong> {$conflict['ids']}</p>";
                echo "<p><strong>UUIDs:</strong></p>";
                $uuids = explode(',', $conflict['uuids']);
                foreach ($uuids as $uuid) {
                    echo "<code>{$uuid}</code><br>";
                }
                echo "</div>";
            }
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #ffebee; padding: 15px; border: 1px solid #f44336;'>";
        echo "<h3>❌ Erro:</h3>";
        echo "<p>{$e->getMessage()}</p>";
        echo "</div>";
    }
    ?>
    
    <script>
    function deleteWalletRecord(id) {
        if (confirm('🗑️ Confirma a exclusão do registro ID: ' + id + '?')) {
            fetch('debug_wallets.php?action=delete&id=' + id)
            .then(response => response.text())
            .then(result => {
                alert(result);
                location.reload();
            })
            .catch(error => {
                alert('Erro: ' + error.message);
            });
        }
    }
    
    // Auto-refresh a cada 30 segundos
    setTimeout(() => location.reload(), 30000);
    </script>
    
    <?php
    // Processar exclusão via GET
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        try {
            $deleteId = (int)$_GET['id'];
            $stmt = $db->getConnection()->prepare("DELETE FROM wallet_ids WHERE id = ?");
            $result = $stmt->execute([$deleteId]);
            
            if ($result) {
                echo "✅ Registro ID {$deleteId} excluído com sucesso!";
            } else {
                echo "❌ Falha ao excluir registro ID {$deleteId}";
            }
        } catch (Exception $e) {
            echo "❌ Erro: " . $e->getMessage();
        }
        exit;
    }
    ?>
    
    <hr>
    <p><strong>🔄 Auto-refresh:</strong> Esta página recarrega automaticamente a cada 30 segundos</p>
    <p><strong>📋 Instruções:</strong></p>
    <ol>
        <li>Identifique registros duplicados (fundo vermelho)</li>
        <li>Mantenha apenas 1 registro por UUID</li>
        <li>Use o botão "🗑️ Excluir Este" para remover duplicatas</li>
        <li>Atualize o index.php com as correções fornecidas</li>
    </ol>
</body>
</html>
