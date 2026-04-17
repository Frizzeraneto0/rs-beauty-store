<?php
/**
 * Upload de imagem de produto
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
$produtoId = $_POST['produto_id'] ?? null;
$imagem = $_FILES['imagem'] ?? null;

// VALIDAÇÃO
if (!$produtoId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do produto não informado']);
    exit;
}

if (!$imagem || $imagem['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Erro no upload da imagem']);
    exit;
}

// Validar tipo de arquivo
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($imagem['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo de arquivo inválido. Use JPG, PNG, GIF ou WEBP']);
    exit;
}

// Validar tamanho (máx 5MB)
if ($imagem['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Imagem muito grande. Máximo 5MB']);
    exit;
}

try {
    $db = getPDO();
    
    // Verificar se o produto existe
    $stmt = $db->prepare("SELECT id FROM produtos WHERE id = :id");
    $stmt->execute([':id' => $produtoId]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
        exit;
    }
    
    // Gerar nome único para o arquivo
    $extensao = pathinfo($imagem['name'], PATHINFO_EXTENSION);
    $nomeArquivo = uniqid('produto_' . $produtoId . '_') . '.' . $extensao;
    
    // Upload do arquivo
    $url = uploadToLocal($imagem['tmp_name'], $nomeArquivo);
    
    // Buscar próxima ordem
    $stmtOrdem = $db->prepare("
        SELECT COALESCE(MAX(ordem), 0) + 1 as proxima_ordem
        FROM produtos_imagens
        WHERE produto_id = :produto_id
    ");
    $stmtOrdem->execute([':produto_id' => $produtoId]);
    $proximaOrdem = $stmtOrdem->fetchColumn();
    
    // Inserir no banco
    $stmt = $db->prepare("
        INSERT INTO produtos_imagens (produto_id, url, ordem)
        VALUES (:produto_id, :url, :ordem)
    ");
    
    $stmt->execute([
        ':produto_id' => $produtoId,
        ':url' => $url,
        ':ordem' => $proximaOrdem
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Imagem enviada com sucesso',
        'imagem_id' => $db->lastInsertId(),
        'url' => $url
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao fazer upload de imagem: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao fazer upload']);
}