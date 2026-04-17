<?php
/**
 * Criar novo valor de variação
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
$tipoId = $_POST['id_tipo_variacao'] ?? null;
$valor = trim($_POST['valor'] ?? '');
$slug = trim($_POST['slug'] ?? '');

// VALIDAÇÃO
if (!$tipoId || $valor === '' || $slug === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
    exit;
}

// INSERT
try {
    $db = getPDO();
    
    // Verificar slug duplicado no mesmo tipo
    $stmt = $db->prepare("SELECT id FROM valores_variacao WHERE slug = :slug AND id_tipo_variacao = :tipo");
    $stmt->execute([':slug' => $slug, ':tipo' => $tipoId]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Já existe um valor com este slug neste tipo']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO valores_variacao (id_tipo_variacao, valor, slug) VALUES (:tipo, :valor, :slug)");
    $stmt->execute([':tipo' => $tipoId, ':valor' => $valor, ':slug' => $slug]);

    echo json_encode([
        'success' => true, 
        'message' => 'Valor criado com sucesso',
        'valor_id' => $db->lastInsertId()
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao criar valor de variação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao criar valor']);
}