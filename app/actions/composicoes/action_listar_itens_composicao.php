<?php
/**
 * Listar itens de uma composição
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
$composicaoId = $_GET['composicao'] ?? null;

// VALIDAÇÃO
if (!$composicaoId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da composição não informado']);
    exit;
}

try {
    $db = getPDO();
    
    // Buscar itens da composição
    $stmt = $db->prepare("
        SELECT 
            ci.id,
            ci.produto_id,
            ci.produto_variacao_id,
            ci.quantidade,
            p.nome AS produto_nome,
            GROUP_CONCAT(
                CONCAT(tv.nome, ': ', vv.valor) 
                ORDER BY tv.nome 
                SEPARATOR ' | '
            ) AS variacao_descricao
        FROM composicoes_itens ci
        INNER JOIN produtos p ON p.id = ci.produto_id
        LEFT JOIN produto_variacoes pv ON pv.id = ci.produto_variacao_id
        LEFT JOIN produto_variacao_valores pvv ON pvv.id_produto_variacao = pv.id
        LEFT JOIN valores_variacao vv ON vv.id = pvv.id_valor_variacao
        LEFT JOIN tipos_variacao tv ON tv.id = pvv.id_tipo_variacao
        WHERE ci.composicao_id = :composicao_id
        GROUP BY ci.id, ci.produto_id, ci.produto_variacao_id, ci.quantidade, p.nome
        ORDER BY p.nome
    ");
    
    $stmt->execute(['composicao_id' => $composicaoId]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'itens' => $itens
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao listar itens da composição: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao listar itens']);
}