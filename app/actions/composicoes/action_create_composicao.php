<?php
/**
 * Criar nova composição
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
$tipo = $_POST['tipo'] ?? '';
$precoCompra = $_POST['preco_compra'] ?? null;
$precoVenda = $_POST['preco_venda'] ?? null;
$ativo = $_POST['ativo'] ?? 1;

// VALIDAÇÃO
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
    
    // Verificar se já existe composição com este nome
    $stmt = $db->prepare("SELECT id FROM composicoes WHERE nome = :nome");
    $stmt->execute([':nome' => $nome]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Já existe uma composição com este nome']);
        exit;
    }
    
    // Inserir composição
    $stmt = $db->prepare("
        INSERT INTO composicoes (nome, descricao, tipo, preco_compra, preco_venda, ativo)
        VALUES (:nome, :descricao, :tipo, :preco_compra, :preco_venda, :ativo)
    ");
    
    $stmt->execute([
        ':nome' => $nome,
        ':descricao' => $descricao ?: null,
        ':tipo' => $tipo,
        ':preco_compra' => $precoCompra ?: null,
        ':preco_venda' => $precoVenda ?: null,
        ':ativo' => $ativo
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Composição criada com sucesso',
        'composicao_id' => $db->lastInsertId()
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao criar composição: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao criar composição']);
}