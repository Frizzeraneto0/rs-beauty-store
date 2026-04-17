<?php
/**
 * Excluir imagem de produto
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

/*
if (!isset($_SESSION['access_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}
*/
// DADOS
$id = $_POST['id'] ?? null;

// VALIDAÇÃO
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da imagem não informado']);
    exit;
}

try {
    $db = getPDO();
    
    // Buscar URL da imagem
    $stmt = $db->prepare("SELECT url, produto_id FROM produtos_imagens WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $imagem = $stmt->fetch();
    
    if (!$imagem) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Imagem não encontrada']);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    try {
        // Deletar do banco
        $stmt = $db->prepare("DELETE FROM produtos_imagens WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // Reordenar imagens restantes
        $stmt = $db->prepare("
            SELECT id
            FROM produtos_imagens
            WHERE produto_id = :produto_id
            ORDER BY ordem
        ");
        $stmt->execute([':produto_id' => $imagem['produto_id']]);
        $imagensRestantes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmtUpdate = $db->prepare("UPDATE produtos_imagens SET ordem = :ordem WHERE id = :id");
        foreach ($imagensRestantes as $index => $imagemId) {
            $stmtUpdate->execute([
                ':ordem' => $index + 1,
                ':id' => $imagemId
            ]);
        }
        
        $db->commit();
        
        // Tentar deletar arquivo físico (não crítico se falhar)
        try {
            deleteLocalFile($imagem['url']);
        } catch (Exception $e) {
            error_log("Aviso: Não foi possível deletar arquivo físico: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Imagem excluída com sucesso'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Throwable $e) {
    error_log("Erro ao excluir imagem: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir imagem']);
}