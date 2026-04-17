<?php
/**
 * Atualizar quantidade do item da composição
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
$quantidade = $_POST['quantidade'] ?? null;

// VALIDAÇÃO
if (!$id || !$quantidade) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos']);
    exit;
}

if ($quantidade < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Quantidade deve ser maior que zero']);
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
    
    // Atualizar quantidade
    $stmt = $db->prepare("
        UPDATE composicoes_itens
        SET quantidade = :quantidade
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':quantidade' => $quantidade,
        ':id' => $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Quantidade atualizada com sucesso'
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao atualizar item: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar item']);
}