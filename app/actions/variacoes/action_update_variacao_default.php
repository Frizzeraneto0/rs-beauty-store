<?php
/**
 * Atualizar variação padrão (sem atributos) adicionando valores
 */

/*
if (!isset($_SESSION['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}
*/

try {
    $db = getPDO();
    
    $variacaoId = $_POST['variacao_id'] ?? null;
    $valores = $_POST['valores'] ?? [];
    
    if (empty($variacaoId)) {
        echo json_encode(['success' => false, 'message' => 'ID da variação não informado']);
        exit;
    }
    
    if (empty($valores) || !is_array($valores)) {
        echo json_encode(['success' => false, 'message' => 'Selecione pelo menos um valor']);
        exit;
    }
    
    // Verificar se a variação existe e não tem valores
    $stmt = $db->prepare("
        SELECT pv.id_produto,
               (SELECT COUNT(*) FROM produto_variacao_valores WHERE id_produto_variacao = pv.id) as qtd_valores
        FROM produto_variacoes pv
        WHERE pv.id = :variacao_id
    ");
    $stmt->execute(['variacao_id' => $variacaoId]);
    $variacao = $stmt->fetch();
    
    if (!$variacao) {
        echo json_encode(['success' => false, 'message' => 'Variação não encontrada']);
        exit;
    }
    
    if ($variacao['qtd_valores'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Esta variação já possui valores']);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    try {
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
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Atributos adicionados com sucesso'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Erro ao atualizar variação default: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar atributos']);
}