<?php
/**
 * Atualizar valor de variação
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
$tipoId = $_POST['id_tipo_variacao'] ?? null;
$valor = trim($_POST['valor'] ?? '');
$slug = trim($_POST['slug'] ?? '');

// VALIDAÇÃO
if (!$id || !$tipoId || $valor === '' || $slug === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
    exit;
}

// UPDATE
try {
    $db = getPDO();
    
    // Verificar slug duplicado no mesmo tipo (exceto o próprio registro)
    $stmt = $db->prepare("
        SELECT id 
        FROM valores_variacao 
        WHERE slug = :slug AND id_tipo_variacao = :tipo AND id != :id
    ");
    $stmt->execute([':slug' => $slug, ':tipo' => $tipoId, ':id' => $id]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Já existe um valor com este slug neste tipo']);
        exit;
    }

    $stmt = $db->prepare("
        UPDATE valores_variacao 
        SET valor = :valor, slug = :slug
        WHERE id = :id
    ");
    $stmt->execute([':valor' => $valor, ':slug' => $slug, ':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Valor atualizado com sucesso']);
    
} catch (Throwable $e) {
    error_log("Erro ao atualizar valor de variação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar valor', 'error' => $e->getMessage()]);
}