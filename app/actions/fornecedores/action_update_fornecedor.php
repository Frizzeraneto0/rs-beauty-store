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

$id       = $_POST['id']       ?? null;
$nome     = trim($_POST['nome']     ?? '');
$cnpj     = trim($_POST['cnpj']     ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$email    = trim($_POST['email']    ?? '');

if (!$id || $nome === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    $stmt = $db->prepare("
        UPDATE fornecedores SET
            nome      = :nome,
            cnpj      = :cnpj,
            telefone  = :telefone,
            email     = :email
        WHERE id = :id
    ");

    $stmt->execute([
        ':id'       => $id,
        ':nome'     => $nome,
        ':cnpj'     => $cnpj     ?: null,
        ':telefone' => $telefone ?: null,
        ':email'    => $email    ?: null,
    ]);

    echo json_encode(['success' => true, 'message' => 'Fornecedor atualizado com sucesso']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar fornecedor', 'error' => $e->getMessage()]);
}