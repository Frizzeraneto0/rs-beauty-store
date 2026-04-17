<?php
/**
 * Excluir item da composição
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
    
    // Verificar se item existe
    $stmt = $db->prepare("SELECT id FROM composicoes_itens WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item não encontrado']);
        exit;
    }
    
    // Excluir item
    $stmt = $db->prepare("DELETE FROM composicoes_itens WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Item excluído com sucesso'
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao excluir item: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir item']);
}