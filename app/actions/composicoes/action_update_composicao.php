<?php
/**
 * Atualizar composição
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
$tipo = $_POST['tipo'] ?? '';
$precoCompra = $_POST['preco_compra'] ?? null;
$precoVenda = $_POST['preco_venda'] ?? null;
$ativo = $_POST['ativo'] ?? 1;

// VALIDAÇÃO
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

if ($nome === '' || $tipo === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome e tipo são obrigatórios']);
    exit;
}

if (!in_array($tipo, ['compra', 'venda'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
    exit;
}

try {
    $db = getPDO();
    
    // Verificar se composição existe
    $stmt = $db->prepare("SELECT id FROM composicoes WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Composição não encontrada']);
        exit;
    }
    
    // Verificar duplicação de nome
    $stmt = $db->prepare("SELECT id FROM composicoes WHERE nome = :nome AND id != :id");
    $stmt->execute([':nome' => $nome, ':id' => $id]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Já existe outra composição com este nome']);
        exit;
    }
    
    // Atualizar composição
    $stmt = $db->prepare("
        UPDATE composicoes
        SET nome = :nome,
            descricao = :descricao,
            tipo = :tipo,
            preco_compra = :preco_compra,
            preco_venda = :preco_venda,
            ativo = :ativo
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':nome' => $nome,
        ':descricao' => $descricao ?: null,
        ':tipo' => $tipo,
        ':preco_compra' => $precoCompra ?: null,
        ':preco_venda' => $precoVenda ?: null,
        ':ativo' => $ativo,
        ':id' => $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Composição atualizada com sucesso'
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao atualizar composição: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar composição']);
}