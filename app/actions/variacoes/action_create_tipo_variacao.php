<?php
/**
 * Criar novo tipo de variação
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
    $slug = trim($_POST['slug'] ?? '');
    
    if (empty($nome) || empty($slug)) {
        echo json_encode(['success' => false, 'message' => 'Nome e slug são obrigatórios']);
        exit;
    }
    
    // Verificar se slug já existe
    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM tipos_variacao WHERE slug = :slug");
    $stmtCheck->execute(['slug' => $slug]);
    
    if ($stmtCheck->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Já existe um tipo com este slug']);
        exit;
    }
    
    $stmt = $db->prepare("
        INSERT INTO tipos_variacao (nome, slug)
        VALUES (:nome, :slug)
    ");
    
    $stmt->execute([
        'nome' => $nome,
        'slug' => $slug
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tipo de variação criado com sucesso',
        'tipo_id' => $db->lastInsertId()
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao criar tipo de variação: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao criar tipo de variação']);
}