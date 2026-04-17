<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

/*
session_start();
if (!isset($_SESSION['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}
*/

$variacaoId = $_GET['variacao'] ?? null;

if (!$variacaoId) {
    echo json_encode(['success' => false, 'message' => 'ID da variação não informado']);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT
            me.id,
            me.quantidade,
            me.motivo,
            DATE_FORMAT(me.criado_em, '%d/%m/%Y %H:%i') AS criado_em,
            tm.descricao        AS tipo_nome,
            LOWER(tm.descricao) AS tipo_operacao
        FROM movimentacoes_estoque me
        INNER JOIN tipo_movimentacao tm ON me.tipo_id = tm.id
        WHERE me.produto_variacao_id = :variacao_id
        ORDER BY me.criado_em DESC
        LIMIT 100
    ");

    $stmt->execute([':variacao_id' => $variacaoId]);
    $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'        => true,
        'movimentacoes'  => $movimentacoes
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar histórico: ' . $e->getMessage()
    ]);
}