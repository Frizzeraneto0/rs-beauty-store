<?php
/**
 * Criar nova variação de produto
 */

/*
if (!isset($_SESSION['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}
*/

try {
    $db = getPDO();
    
    $produtoId = $_POST['id_produto'] ?? null;
    $valores = $_POST['valores'] ?? [];
    
    if (empty($produtoId)) {
        echo json_encode(['success' => false, 'message' => 'ID do produto não informado']);
        exit;
    }
    
    if (empty($valores) || !is_array($valores)) {
        echo json_encode(['success' => false, 'message' => 'Selecione pelo menos um valor de variação']);
        exit;
    }
    
    // Verificar se já existe variação com os mesmos valores
    $placeholders = str_repeat('?,', count($valores) - 1) . '?';
    
    $checkStmt = $db->prepare("
        SELECT pv.id
        FROM produto_variacoes pv
        WHERE pv.id_produto = ?
        AND (
            SELECT COUNT(*)
            FROM produto_variacao_valores pvv
            WHERE pvv.id_produto_variacao = pv.id
            AND pvv.id_valor_variacao IN ($placeholders)
        ) = ?
        AND (
            SELECT COUNT(*)
            FROM produto_variacao_valores pvv2
            WHERE pvv2.id_produto_variacao = pv.id
        ) = ?
        LIMIT 1
    ");
    
    $params = array_merge([$produtoId], $valores, [count($valores), count($valores)]);
    $checkStmt->execute($params);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe uma variação com estes valores']);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    try {
        // Criar nova variação
        $stmt = $db->prepare("INSERT INTO produto_variacoes (id_produto) VALUES (:id_produto)");
        $stmt->execute(['id_produto' => $produtoId]);
        
        $variacaoId = $db->lastInsertId();
        
        // Associar valores à variação
        $stmtValor = $db->prepare("
            INSERT INTO produto_variacao_valores (id_produto_variacao, id_tipo_variacao, id_valor_variacao)
            SELECT :variacao_id, vv.id_tipo_variacao, vv.id
            FROM valores_variacao vv
            WHERE vv.id = :valor_id
        ");
        
        foreach ($valores as $valorId) {
            $stmtValor->execute([
                'variacao_id' => $variacaoId,
                'valor_id' => $valorId
            ]);
        }
        
        // Criar entrada no estoque para a nova variação
        $stmtEstoque = $db->prepare("
            INSERT INTO estoque_atual (id_produto_variacao, quantidade)
            VALUES (:variacao_id, 0)
        ");
        $stmtEstoque->execute(['variacao_id' => $variacaoId]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Variação criada com sucesso',
            'variacao_id' => $variacaoId
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Erro ao criar variação: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao criar variação']);
}