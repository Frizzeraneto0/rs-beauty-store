<?php
/**
 * Criar nova categoria
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
$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');

// VALIDAÇÃO
if ($nome === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
    exit;
}

try {
    $db = getPDO();
    
    // Verificar se já existe categoria com este nome
    $stmt = $db->prepare("SELECT id FROM categorias WHERE nome = :nome");
    $stmt->execute([':nome' => $nome]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Já existe uma categoria com este nome']);
        exit;
    }
    
    // Inserir categoria
    $stmt = $db->prepare("
        INSERT INTO categorias (nome, descricao)
        VALUES (:nome, :descricao)
    ");
    
    $stmt->execute([
        ':nome' => $nome,
        ':descricao' => $descricao ?: null
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Categoria criada com sucesso',
        'categoria_id' => $db->lastInsertId()
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao criar categoria: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao criar categoria']);
}