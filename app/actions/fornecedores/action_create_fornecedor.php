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

$nome     = trim($_POST['nome']     ?? '');
$cnpj     = trim($_POST['cnpj']     ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$email    = trim($_POST['email']    ?? '');

if ($nome === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
    exit;
}

try {
    $stmt = $db->prepare("
        INSERT INTO fornecedores (nome, cnpj, telefone, email)
        VALUES (:nome, :cnpj, :telefone, :email)
    ");

    $stmt->execute([
        ':nome'     => $nome,
        ':cnpj'     => $cnpj     ?: null,
        ':telefone' => $telefone ?: null,
        ':email'    => $email    ?: null,
    ]);

    echo json_encode(['success' => true, 'message' => 'Fornecedor criado com sucesso']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao criar fornecedor', 'error' => $e->getMessage()]);
}