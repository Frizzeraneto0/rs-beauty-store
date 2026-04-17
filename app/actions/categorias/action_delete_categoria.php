<?php
/**
 * Excluir categoria
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
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

try {
    $db = getPDO();
    
    // Verificar se categoria existe
    $stmt = $db->prepare("SELECT nome FROM categorias WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $categoria = $stmt->fetch();
    
    if (!$categoria) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Categoria não encontrada']);
        exit;
    }
    
    // Verificar se existem produtos vinculados
    $stmt = $db->prepare("SELECT COUNT(*) FROM produtos WHERE categoria_id = :id");
    $stmt->execute([':id' => $id]);
    $totalProdutos = $stmt->fetchColumn();
    
    if ($totalProdutos > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Não é possível excluir. Existem {$totalProdutos} produto(s) vinculado(s) a esta categoria."
        ]);
        exit;
    }
    
    // Excluir categoria
    $stmt = $db->prepare("DELETE FROM categorias WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Categoria excluída com sucesso'
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao excluir categoria: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir categoria']);
}