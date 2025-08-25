<!DOCTYPE html>
<html>
<head>
    <title>Debug: Problema Polo Tucuruí vs AVA</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; }
        .error { background: #ffebee; border: 2px solid #f44336; padding: 15px; margin: 10px 0; }
        .success { background: #e8f5e8; border: 2px solid #4caf50; padding: 15px; margin: 10px 0; }
        .info { background: #e3f2fd; border: 2px solid #2196f3; padding: 15px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Debug: Problema Tucuruí (6) vs AVA (5)</h1>
    
    <?php
    require_once 'config.php';
    
    try {
        $db = DatabaseManager::getInstance();
        
        echo "<div class='info'>";
        echo "<h2>📋 Informações dos Polos</h2>";
        
        $polosStmt = $db->getConnection()->query("SELECT * FROM polos ORDER BY id");
        $polos = $polosStmt->fetchAll();
        
        echo "<table>";
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
        echo "</div>";
        
        echo "<div class='info'>";
        echo "<h2>📋 Todos os Wallet IDs no Sistema</h2>";
        
        $walletsStmt = $db->getConnection()->query("
            SELECT w.*, p.nome as polo_nome 
            FROM wallet_ids w 
            LEFT JOIN polos p ON w.polo_id = p.id 
            ORDER BY w.wallet_id, w.polo_id
        ");
        $wallets = $walletsStmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>ID</th><th>Polo ID</th><th>Polo Nome</th><th>Nome</th><th>UUID</th><th>Ativo</th></tr>";
        foreach ($wallets as $wallet) {
            echo "<tr>";
            echo "<td>{$wallet['id']}</td>";
            echo "<td>" . ($wallet['polo_id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($wallet['polo_nome'] ?? 'Global') . "</td>";
            echo "<td>{$wallet['name']}</td>";
            echo "<td>" . substr($wallet['wallet_id'], 0, 20) . "...</td>";
            echo "<td>" . ($wallet['is_active'] ? 'Sim' : 'Não') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
        
        echo "<div class='info'>";
        echo "<h2>🔍 Análise por UUID</h2>";
        
        $uuidsStmt = $db->getConnection()->query("
            SELECT 
                wallet_id,
                COUNT(*) as total,
                GROUP_CONCAT(DISTINCT w.polo_id ORDER BY w.polo_id) as polo_ids,
                GROUP_CONCAT(DISTINCT COALESCE(p.nome, 'Global') ORDER BY w.polo_id) as polo_nomes,
                GROUP_CONCAT(DISTINCT w.name ORDER BY w.polo_id) as nomes
            FROM wallet_ids w 
            LEFT JOIN polos p ON w.polo_id = p.id
            GROUP BY wallet_id
            HAVING COUNT(*) >= 1
            ORDER BY total DESC
        ");
        $uuids = $uuidsStmt->fetchAll();
        
        foreach ($uuids as $uuid) {
            $isMulti = $uuid['total'] > 1;
            $class = $isMulti ? 'success' : 'info';
            
            echo "<div class='{$class}'>";
            echo "<h4>" . ($isMulti ? '🔗 MULTI-POLO' : '⚡ ÚNICO') . "</h4>";
            echo "<p><strong>UUID:</strong> {$uuid['wallet_id']}</p>";
            echo "<p><strong>Quantidade:</strong> {$uuid['total']}</p>";
            echo "<p><strong>Polos IDs:</strong> {$uuid['polo_ids']}</p>";
            echo "<p><strong>Polos Nomes:</strong> {$uuid['polo_nomes']}</p>";
            echo "<p><strong>Nomes:</strong> {$uuid['nomes']}</p>";
            echo "</div>";
        }
        echo "</div>";
        
        // TESTE ESPECÍFICO
        if (isset($_GET['test_uuid'])) {
            $testUuid = $_GET['test_uuid'];
            $testPolo = (int)($_GET['test_polo'] ?? 6);
            
            echo "<div class='info'>";
            echo "<h2>🧪 TESTE: UUID {$testUuid} no Polo {$testPolo}</h2>";
            
            // Verificar se existe no polo específico
            $testStmt = $db->getConnection()->prepare("
                SELECT * FROM wallet_ids WHERE wallet_id = ? AND polo_id = ?
            ");
            $testStmt->execute([$testUuid, $testPolo]);
            $existsInPolo = $testStmt->fetch();
            
            if ($existsInPolo) {
                echo "<div class='error'>";
                echo "<h4>❌ JÁ EXISTE NO POLO {$testPolo}</h4>";
                echo "<pre>" . json_encode($existsInPolo, JSON_PRETTY_PRINT) . "</pre>";
                echo "</div>";
            } else {
                echo "<div class='success'>";
                echo "<h4>✅ NÃO EXISTE NO POLO {$testPolo} - PODE CADASTRAR!</h4>";
                echo "</div>";
            }
            
            // Verificar onde existe
            $whereStmt = $db->getConnection()->prepare("
                SELECT w.*, p.nome as polo_nome 
                FROM wallet_ids w 
                LEFT JOIN polos p ON w.polo_id = p.id 
                WHERE w.wallet_id = ?
            ");
            $whereStmt->execute([$testUuid]);
            $existsWhere = $whereStmt->fetchAll();
            
            if (!empty($existsWhere)) {
                echo "<h4>📍 UUID EXISTE EM:</h4>";
                foreach ($existsWhere as $where) {
                    echo "<div class='info'>";
                    echo "<p>Polo: " . ($where['polo_nome'] ?? 'Global') . " (ID: " . ($where['polo_id'] ?? 'NULL') . ")</p>";
                    echo "<p>Nome: {$where['name']}</p>";
                    echo "<p>DB ID: {$where['id']}</p>";
                    echo "</div>";
                }
            }
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<h3>❌ Erro:</h3>";
        echo "<p>{$e->getMessage()}</p>";
        echo "</div>";
    }
    ?>
    
    <hr>
    <h2>🧪 Testar UUID Específico</h2>
    <form method="GET">
        <p>
            <label>UUID para testar:</label><br>
            <input type="text" name="test_uuid" value="<?php echo htmlspecialchars($_GET['test_uuid'] ?? ''); ?>" size="50">
        </p>
        <p>
            <label>Polo ID de destino:</label><br>
            <input type="number" name="test_polo" value="<?php echo (int)($_GET['test_polo'] ?? 6); ?>">
        </p>
        <p>
            <button type="submit">🔍 Testar</button>
        </p>
    </form>
</body>
</html>