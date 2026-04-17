<?php
/**
 * Atualizar tipo de variação
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
$slug = trim($_POST['slug'] ?? '');

// VALIDAÇÃO
if (!$id || $nome === '' || $slug === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
    exit;
}

// UPDATE
try {
    $db = getPDO();
    
    // Verificar slug duplicado (exceto o próprio registro)
    $stmt = $db->prepare("SELECT id FROM tipos_variacao WHERE slug = :slug AND id != :id");
    $stmt->execute([':slug' => $slug, ':id' => $id]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Já existe um tipo com este slug']);
        exit;
    }

    $stmt = $db->prepare("
        UPDATE tipos_variacao 
        SET nome = :nome, slug = :slug, updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([':nome' => $nome, ':slug' => $slug, ':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Tipo atualizado com sucesso']);
    
} catch (Throwable $e) {
    error_log("Erro ao atualizar tipo de variação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar tipo', 'error' => $e->getMessage()]);
}