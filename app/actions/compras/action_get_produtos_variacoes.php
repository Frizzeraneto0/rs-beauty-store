<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

/*
session_start();
if (!isset($_SESSION['access_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}
*/

try {
    $stmt = $db->query("
        SELECT id, nome, descricao, preco
        FROM produtos
        WHERE ativo = 1
        ORDER BY nome
    ");

    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'  => true,
        'produtos' => $produtos
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar produtos',
        'error'   => $e->getMessage()
    ]);
}