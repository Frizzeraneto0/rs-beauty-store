<?php
/**
 * Excluir composição
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
    
    // Verificar se composição existe
    $stmt = $db->prepare("SELECT nome FROM composicoes WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $composicao = $stmt->fetch();
    
    if (!$composicao) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Composição não encontrada']);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    try {
        // Excluir itens da composição
        $stmt = $db->prepare("DELETE FROM composicoes_itens WHERE composicao_id = :id");
        $stmt->execute([':id' => $id]);
        
        // Excluir composição
        $stmt = $db->prepare("DELETE FROM composicoes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Composição excluída com sucesso'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Throwable $e) {
    error_log("Erro ao excluir composição: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir composição']);
}