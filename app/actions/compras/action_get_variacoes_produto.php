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

$produtoId = $_GET['produto'] ?? null;

if (!$produtoId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Produto não informado']);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT id, id_produto
        FROM produto_variacoes
        WHERE id_produto = :produto_id
        ORDER BY id
    ");
    $stmt->execute([':produto_id' => $produtoId]);
    $variacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtValores = $db->prepare("
        SELECT 
            tv.nome as tipo_nome,
            vv.valor as valor_nome
        FROM produto_variacao_valores pvv
        JOIN tipos_variacao tv ON tv.id = pvv.id_tipo_variacao
        JOIN valores_variacao vv ON vv.id = pvv.id_valor_variacao
        WHERE pvv.id_produto_variacao = :variacao_id
        ORDER BY tv.nome
    ");

    foreach ($variacoes as &$variacao) {
        $stmtValores->execute([':variacao_id' => $variacao['id']]);
        $valores = $stmtValores->fetchAll(PDO::FETCH_ASSOC);

        $partes = [];
        foreach ($valores as $v) {
            $partes[] = $v['tipo_nome'] . ': ' . $v['valor_nome'];
        }
        $variacao['descricao'] = implode(', ', $partes) ?: 'Padrão';
    }

    echo json_encode(['success' => true, 'variacoes' => $variacoes]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar variações',
        'error'   => $e->getMessage()
    ]);
}