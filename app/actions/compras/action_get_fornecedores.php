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
    $fornecedores = $db->query("
        SELECT id, nome, cnpj, telefone, email
        FROM fornecedores
        ORDER BY nome
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'fornecedores' => $fornecedores]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar fornecedores',
        'error'   => $e->getMessage()
    ]);
}