<?php
/**
 * Atualizar produto existente
 */

/*
if (!isset($_SESSION['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}
*/

try {
    $db = getPDO();
    
    $id = $_POST['id'] ?? null;
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = $_POST['preco'] ?? 0;
    $preco_promocional = $_POST['preco_promocional'] ?? null;
    $categoria_id = $_POST['categoria_id'] ?? null;
    $ativo = $_POST['ativo'] ?? 1;
    
    // Validações
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID do produto não informado']);
        exit;
    }
    
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
        UPDATE produtos 
        SET nome = :nome,
            descricao = :descricao,
            preco = :preco,
            preco_promocional = :preco_promocional,
            categoria_id = :categoria_id,
            ativo = :ativo,
            updated_at = NOW()
        WHERE id = :id
    ");
    
    $stmt->execute([
        'id' => $id,
        'nome' => $nome,
        'descricao' => $descricao,
        'preco' => $preco,
        'preco_promocional' => $preco_promocional,
        'categoria_id' => $categoria_id,
        'ativo' => $ativo
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Produto atualizado com sucesso'
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao atualizar produto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar produto']);
}