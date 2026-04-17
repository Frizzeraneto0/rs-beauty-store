<?php
/**
 * Adicionar item à composição
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
$composicaoId = $_POST['composicao_id'] ?? null;
$produtoId = $_POST['produto_id'] ?? null;
$variacaoId = $_POST['produto_variacao_id'] ?? null;
$quantidade = $_POST['quantidade'] ?? null;

// VALIDAÇÃO
if (!$composicaoId || !$produtoId || !$quantidade) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios']);
    exit;
}

if ($quantidade < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Quantidade deve ser maior que zero']);
    exit;
}

try {
    $db = getPDO();
    
    // Se não informou variação, pegar a default do produto
    if (!$variacaoId) {
        $stmt = $db->prepare("
            SELECT id 
            FROM produto_variacoes 
            WHERE id_produto = :produto_id
            ORDER BY id
            LIMIT 1
        ");
        $stmt->execute(['produto_id' => $produtoId]);
        $variacaoId = $stmt->fetchColumn();
        
        if (!$variacaoId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Produto não possui variações cadastradas']);
            exit;
        }
    }
    
    // Verificar se item já existe na composição
    $stmt = $db->prepare("
        SELECT id 
        FROM composicoes_itens 
        WHERE composicao_id = :composicao_id 
        AND produto_id = :produto_id 
        AND produto_variacao_id = :variacao_id
    ");
    $stmt->execute([
        'composicao_id' => $composicaoId,
        'produto_id' => $produtoId,
        'variacao_id' => $variacaoId
    ]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Este item já foi adicionado à composição']);
        exit;
    }
    
    // Inserir item
    $stmt = $db->prepare("
        INSERT INTO composicoes_itens (composicao_id, produto_id, produto_variacao_id, quantidade)
        VALUES (:composicao_id, :produto_id, :variacao_id, :quantidade)
    ");
    
    $stmt->execute([
        'composicao_id' => $composicaoId,
        'produto_id' => $produtoId,
        'variacao_id' => $variacaoId,
        'quantidade' => $quantidade
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Item adicionado com sucesso',
        'item_id' => $db->lastInsertId()
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao criar item da composição: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar item']);
}