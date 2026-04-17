<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

/*
if (!isset($_SESSION['access_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}
*/

$id = $_POST['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Verificar se há compras vinculadas
    $stmt = $db->prepare("SELECT COUNT(*) FROM compras WHERE fornecedor_id = :id");
    $stmt->execute([':id' => $id]);
    $total = (int) $stmt->fetchColumn();

    if ($total > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Não é possível excluir. Existem {$total} compra(s) vinculada(s)"
        ]);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM fornecedores WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Fornecedor excluído com sucesso']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir fornecedor', 'error' => $e->getMessage()]);
}