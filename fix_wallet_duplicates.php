/**
 * Script para executar via linha de comando ou navegador para limpar dados duplicados
 * Salve como: fix_wallet_duplicates.php
 */

/*
<?php
require_once 'config.php';

try {
    $db = DatabaseManager::getInstance();
    
    echo "🔍 Verificando Wallet IDs duplicados...\n";
    
    // Buscar possíveis duplicatas
    $stmt = $db->getConnection()->query("
        SELECT wallet_id, name, COUNT(*) as count, GROUP_CONCAT(id) as ids, GROUP_CONCAT(created_at) as dates
        FROM wallet_ids 
        GROUP BY wallet_id 
        HAVING COUNT(*) > 1
    ");
    $duplicates = $stmt->fetchAll();
    
    if (empty($duplicates)) {
        echo "✅ Nenhum Wallet ID duplicado encontrado!\n";
    } else {
        echo "⚠️  Encontrados " . count($duplicates) . " Wallet IDs duplicados:\n";
        
        foreach ($duplicates as $dup) {
            echo "UUID: {$dup['wallet_id']}\n";
            echo "Nome: {$dup['name']}\n";
            echo "Duplicatas: {$dup['count']}\n";
            echo "IDs: {$dup['ids']}\n";
            echo "Datas: {$dup['dates']}\n";
            echo "---\n";
        }
        
        if (isset($_GET['fix']) && $_GET['fix'] === 'true') {
            echo "🔧 Removendo duplicatas...\n";
            
            foreach ($duplicates as $dup) {
                $ids = explode(',', $dup['ids']);
                
                // Manter o primeiro (mais antigo) e remover os outros
                $keepId = array_shift($ids);
                echo "Mantendo ID: {$keepId}\n";
                
                foreach ($ids as $deleteId) {
                    $deleteStmt = $db->getConnection()->prepare("DELETE FROM wallet_ids WHERE id = ?");
                    $deleteStmt->execute([trim($deleteId)]);
                    echo "Removido ID: " . trim($deleteId) . "\n";
                }
            }
            
            echo "✅ Limpeza concluída!\n";
        } else {
            echo "\n🔧 Para corrigir automaticamente, acesse: " . $_SERVER['REQUEST_URI'] . "?fix=true\n";
        }
    }
    
    // Estatísticas finais
    $totalStmt = $db->getConnection()->query("SELECT COUNT(*) as total FROM wallet_ids");
    $total = $totalStmt->fetch()['total'];
    echo "\n📊 Total de Wallet IDs no sistema: {$total}\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
*/

?>