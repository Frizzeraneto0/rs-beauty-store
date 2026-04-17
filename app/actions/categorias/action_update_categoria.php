<?php
/**
 * Atualizar categoria
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
$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');

// VALIDAÇÃO
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

if ($nome === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
    exit;
}

try {
    $db = getPDO();
    
    // Verificar se categoria existe
    $stmt = $db->prepare("SELECT id FROM categorias WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Categoria não encontrada']);
        exit;
    }
    
    // Verificar se já existe outra categoria com este nome
    $stmt = $db->prepare("SELECT id FROM categorias WHERE nome = :nome AND id != :id");
    $stmt->execute([':nome' => $nome, ':id' => $id]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Já existe outra categoria com este nome']);
        exit;
    }
    
    // Atualizar categoria
    $stmt = $db->prepare("
        UPDATE categorias
        SET nome = :nome, descricao = :descricao, updated_at = NOW()
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':nome' => $nome,
        ':descricao' => $descricao ?: null,
        ':id' => $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Categoria atualizada com sucesso'
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao atualizar categoria: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar categoria']);
}