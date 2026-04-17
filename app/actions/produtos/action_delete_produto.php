<?php
/**
 * Excluir produto
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
        echo json_encode(['success' => false, 'message' => 'ID do produto não informado']);
        exit;
    }
    
    // Verificar se há vendas associadas
    $stmtVendas = $db->prepare("
        SELECT COUNT(*) 
        FROM vendas_itens vi
        INNER JOIN produto_variacoes pv ON vi.produto_variacao_id = pv.id
        WHERE pv.id_produto = :id
    ");
    $stmtVendas->execute(['id' => $id]);
    
    if ($stmtVendas->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Não é possível excluir produto com vendas associadas']);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    try {
        // Buscar variações do produto
        $stmtVariacoes = $db->prepare("SELECT id FROM produto_variacoes WHERE id_produto = :id");
        $stmtVariacoes->execute(['id' => $id]);
        $variacoes = $stmtVariacoes->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($variacoes as $variacaoId) {
            // Deletar do estoque
            $db->prepare("DELETE FROM estoque_atual WHERE id_produto_variacao = ?")->execute([$variacaoId]);
            
            // Deletar movimentações
            $db->prepare("DELETE FROM movimentacoes_estoque WHERE produto_variacao_id = ?")->execute([$variacaoId]);
            
            // Deletar valores da variação
            $db->prepare("DELETE FROM produto_variacao_valores WHERE id_produto_variacao = ?")->execute([$variacaoId]);
        }
        
        // Deletar variações
        $db->prepare("DELETE FROM produto_variacoes WHERE id_produto = ?")->execute([$id]);
        
        // Deletar imagens
        $db->prepare("DELETE FROM produtos_imagens WHERE produto_id = ?")->execute([$id]);
        
        // Deletar produto
        $db->prepare("DELETE FROM produtos WHERE id = ?")->execute([$id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Produto excluído com sucesso'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Erro ao excluir produto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir produto']);
}