<?php
/**
 * Criar novo produto
 */

/*
if (!isset($_SESSION['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}
*/

try {
    $db = getPDO();
    
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = $_POST['preco'] ?? 0;
    $preco_promocional = $_POST['preco_promocional'] ?? null;
    $categoria_id = $_POST['categoria_id'] ?? null;
    $ativo = $_POST['ativo'] ?? 1;
    
    // Validações
    if (empty($nome)) {
        echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
        exit;
    }
    
    if (empty($preco) || $preco <= 0) {
        echo json_encode(['success' => false, 'message' => 'Preço inválido']);
        exit;
    }
    
    // Converter valores vazios para null
    if (empty($preco_promocional)) $preco_promocional = null;
    if (empty($categoria_id)) $categoria_id = null;
    
    $stmt = $db->prepare("
        INSERT INTO produtos (nome, descricao, preco, preco_promocional, categoria_id, ativo)
        VALUES (:nome, :descricao, :preco, :preco_promocional, :categoria_id, :ativo)
    ");
    
    $stmt->execute([
        'nome' => $nome,
        'descricao' => $descricao,
        'preco' => $preco,
        'preco_promocional' => $preco_promocional,
        'categoria_id' => $categoria_id,
        'ativo' => $ativo
    ]);
    
    $produtoId = $db->lastInsertId();
    
    // Criar variação padrão automaticamente
    $stmtVariacao = $db->prepare("INSERT INTO produto_variacoes (id_produto) VALUES (:id_produto)");
    $stmtVariacao->execute(['id_produto' => $produtoId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Produto criado com sucesso',
        'produto_id' => $produtoId
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao criar produto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao criar produto']);
}