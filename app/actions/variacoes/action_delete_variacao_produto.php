<?php
/**
 * Excluir variação de produto
 */

/*
if (!isset($_SESSION['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}
*/

try {
    $db = getPDO();
    
    $id = $_POST['id'] ?? null;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID da variação não informado']);
        exit;
    }
    
    // Verificar se é a última variação do produto
    $stmt = $db->prepare("
        SELECT 
            pv.id_produto,
            (SELECT COUNT(*) FROM produto_variacoes WHERE id_produto = pv.id_produto) as total_variacoes
        FROM produto_variacoes pv
        WHERE pv.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $variacao = $stmt->fetch();
    
    if (!$variacao) {
        echo json_encode(['success' => false, 'message' => 'Variação não encontrada']);
        exit;
    }
    
    if ($variacao['total_variacoes'] <= 1) {
        echo json_encode(['success' => false, 'message' => 'Não é possível excluir a última variação do produto']);
        exit;
    }
    
    // Verificar se há movimentações de estoque
    $stmtMov = $db->prepare("
        SELECT COUNT(*) as total
        FROM movimentacoes_estoque
        WHERE produto_variacao_id = :id
    ");
    $stmtMov->execute(['id' => $id]);
    $movimentacoes = $stmtMov->fetchColumn();
    
    if ($movimentacoes > 0) {
        echo json_encode(['success' => false, 'message' => 'Não é possível excluir variação com movimentações de estoque']);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    try {
        // Deletar do estoque
        $stmtEstoque = $db->prepare("DELETE FROM estoque_atual WHERE id_produto_variacao = :id");
        $stmtEstoque->execute(['id' => $id]);
        
        // Deletar valores da variação
        $stmtValores = $db->prepare("DELETE FROM produto_variacao_valores WHERE id_produto_variacao = :id");
        $stmtValores->execute(['id' => $id]);
        
        // Deletar variação
        $stmtDelete = $db->prepare("DELETE FROM produto_variacoes WHERE id = :id");
        $stmtDelete->execute(['id' => $id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Variação excluída com sucesso'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Erro ao excluir variação: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir variação']);
}