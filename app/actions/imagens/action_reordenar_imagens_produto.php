<?php
/**
 * Reordenar imagens de produto (drag and drop)
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

// Ler JSON do body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// DADOS
$produtoId = $data['produto_id'] ?? null;
$imagens = $data['imagens'] ?? [];

// VALIDAÇÃO
if (!$produtoId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do produto não informado']);
    exit;
}

if (empty($imagens) || !is_array($imagens)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lista de imagens inválida']);
    exit;
}

try {
    $db = getPDO();
    
    // Iniciar transação
    $db->beginTransaction();
    
    try {
        $stmt = $db->prepare("
            UPDATE produtos_imagens 
            SET ordem = :ordem
            WHERE id = :id AND produto_id = :produto_id
        ");
        
        foreach ($imagens as $imagem) {
            if (!isset($imagem['id']) || !isset($imagem['ordem'])) {
                throw new Exception('Dados de imagem inválidos');
            }
            
            $stmt->execute([
                ':ordem' => $imagem['ordem'],
                ':id' => $imagem['id'],
                ':produto_id' => $produtoId
            ]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Ordem atualizada com sucesso'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Throwable $e) {
    error_log("Erro ao reordenar imagens: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao reordenar imagens']);
}