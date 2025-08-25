<!DOCTYPE html>
<html>
<head>
    <title>Verificação Multi-Polo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .multi-polo { background: #e3f2fd; border: 1px solid #2196f3; padding: 15px; margin: 10px 0; }
        .single-polo { background: #f1f8e9; border: 1px solid #4caf50; padding: 15px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>🔍 Verificação Multi-Polo de Wallet IDs</h1>
    
    <?php
    require_once 'config.php';
    
    try {
        $db = DatabaseManager::getInstance();
        
        echo "<h2>📊 Análise de UUIDs por Polo</h2>";
        
        // Buscar agrupamento por UUID
        $stmt = $db->getConnection()->query("
            SELECT 
                wi.wallet_id,
                COUNT(*) as total_registros,
                GROUP_CONCAT(DISTINCT wi.id ORDER BY wi.id) as db_ids,
                GROUP_CONCAT(DISTINCT wi.name ORDER BY wi.id) as nomes,
                GROUP_CONCAT(DISTINCT COALESCE(p.nome, 'Global') ORDER BY wi.id) as polos,
                GROUP_CONCAT(DISTINCT wi.polo_id ORDER BY wi.id) as polo_ids
            FROM wallet_ids wi
            LEFT JOIN polos p ON wi.polo_id = p.id
            GROUP BY wi.wallet_id
            ORDER BY total_registros DESC, wi.wallet_id
        ");
        $agrupados = $stmt->fetchAll();
        
        $multiPoloCount = 0;
        $singlePoloCount = 0;
        
        foreach ($agrupados as $grupo) {
            $isMultiPolo = $grupo['total_registros'] > 1;
            
            if ($isMultiPolo) {
                $multiPoloCount++;
                $class = 'multi-polo';
                $icon = '🔗';
                $title = 'MULTI-POLO';
            } else {
                $singlePoloCount++;
                $class = 'single-polo';
                $icon = '⚡';
                $title = 'ÚNICO';
            }
            
            echo "<div class='{$class}'>";
            echo "<h3>{$icon} {$title} - UUID: {$grupo['wallet_id']}</h3>";
            echo "<p><strong>Total de registros:</strong> {$grupo['total_registros']}</p>";
            echo "<p><strong>IDs do banco:</strong> {$grupo['db_ids']}</p>";
            echo "<p><strong>Nomes:</strong> {$grupo['nomes']}</p>";
            echo "<p><strong>Polos:</strong> {$grupo['polos']}</p>";
            echo "<p><strong>IDs dos polos:</strong> {$grupo['polo_ids']}</p>";
            echo "</div>";
        }
        
        echo "<h2>📈 Resumo</h2>";
        echo "<table>";
        echo "<tr><th>Tipo</th><th>Quantidade</th><th>Descrição</th></tr>";
        echo "<tr><td>🔗 Multi-Polo</td><td>{$multiPoloCount}</td><td>UUIDs usados em múltiplos polos</td></tr>";
        echo "<tr><td>⚡ Únicos</td><td>{$singlePoloCount}</td><td>UUIDs usados em apenas um polo</td></tr>";
        echo "<tr><td><strong>Total</strong></td><td><strong>" . ($multiPoloCount + $singlePoloCount) . "</strong></td><td>Total de UUIDs únicos</td></tr>";
        echo "</table>";
        
        if ($multiPoloCount > 0) {
            echo "<div class='multi-polo'>";
            echo "<h3>✅ Sistema Multi-Polo Funcionando!</h3>";
            echo "<p>Você tem {$multiPoloCount} UUID(s) sendo reutilizado(s) em múltiplos polos, o que é permitido e recomendado pelo ASAAS.</p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #ffebee; padding: 15px; border: 1px solid #f44336;'>";
        echo "<h3>❌ Erro:</h3>";
        echo "<p>{$e->getMessage()}</p>";
        echo "</div>";
    }
    ?>
    
    <hr>
    <h2>💡 Informações Importantes</h2>
    <ul>
        <li><strong>Multi-Polo é PERMITIDO:</strong> O mesmo UUID pode ser usado em diferentes polos</li>
        <li><strong>Mesmo Polo é IMPEDIDO:</strong> Não pode ter o mesmo UUID duas vezes no mesmo polo</li>
        <li><strong>ASAAS recomenda:</strong> Reutilizar Wallet IDs entre diferentes contas/polos</li>
        <li><strong>Vantagem:</strong> Simplifica gestão de comissionados que trabalham com múltiplos polos</li>
    </ul>
</body>
</html>

<?php

// =============================================================================
// 6. DOCUMENTAÇÃO PARA O USUÁRIO
// =============================================================================

/*
INSTRUÇÕES DE USO MULTI-POLO:

1. CENÁRIO COMUM:
   - João trabalha como comissionado para Polo A e Polo B
   - Ele tem uma conta ASAAS com UUID: 22e49670-27e4-4579-a4c1-205c8a40497c
   - Agora você pode cadastrar o mesmo UUID nos dois polos
   - João receberá comissões de ambos os polos na mesma conta ASAAS

2. COMO CADASTRAR:
   - Vá para o Polo A → Wallet IDs → Cadastre João com UUID completo
   - Vá para o Polo B → Wallet IDs → Cadastre João com o MESMO UUID
   - Sistema vai permitir e mostrar que o UUID é usado em múltiplos polos

3. VANTAGENS:
   - Comissionados não precisam de múltiplas contas ASAAS
   - Gestão simplificada de pagamentos
   - Conformidade com recomendações do ASAAS

4. LIMITAÇÕES:
   - Não pode cadastrar o mesmo UUID duas vezes no MESMO polo
   - Nomes podem ser diferentes (ex: "João - Vendas" no Polo A, "João Silva" no Polo B)

5. MONITORAMENTO:
   - Use check_multi_polo.php para verificar uso
   - Interface mostra indicador quando UUID é multi-polo
   - Logs registram todas as operações
*/
