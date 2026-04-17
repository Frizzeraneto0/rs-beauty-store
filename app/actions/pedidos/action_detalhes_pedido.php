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

$pedidoId = $_GET['id'] ?? null;

if (!$pedidoId) {
    echo json_encode(['success' => false, 'message' => 'ID do pedido não informado']);
    exit;
}

try {
    // ========================================
    // DADOS DO PEDIDO
    // ========================================
    $stmt = $db->prepare("
        SELECT
            v.id,
            v.valor_total,
            DATE_FORMAT(v.data_venda, '%d/%m/%Y %H:%i') AS data_venda,
            sp.descricao  AS status_descricao,
            sp.cor        AS status_cor,
            sp.icone      AS status_icone,
            spg.descricao AS status_pagamento,
            u.nome        AS cliente_nome,
            u.email       AS cliente_email,
            u.telefone    AS cliente_telefone
        FROM vendas v
        LEFT JOIN status_pedido    sp  ON v.status_pedido_id    = sp.id
        LEFT JOIN status_pagamento spg ON v.status_pagamento_id = spg.id
        LEFT JOIN usuarios         u   ON v.usuario_id          = u.id
        WHERE v.id = :pedido_id
    ");
    $stmt->execute([':pedido_id' => $pedidoId]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
        exit;
    }

    // ========================================
    // ITENS DO PEDIDO
    // ========================================
    $stmt = $db->prepare("
        SELECT
            vi.id,
            vi.quantidade,
            vi.preco_unitario_final,
            vi.subtotal,
            vi.valor_desconto,
            p.nome  AS produto_nome,
            GROUP_CONCAT(vv.valor ORDER BY vv.valor SEPARATOR ' / ') AS variacao,
            c.nome  AS composicao_nome
        FROM vendas_itens vi
        LEFT JOIN produto_variacoes pv  ON vi.produto_variacao_id = pv.id
        LEFT JOIN produtos          p   ON pv.id_produto          = p.id
        LEFT JOIN produto_variacao_valores pvv ON pvv.id_produto_variacao = pv.id
        LEFT JOIN valores_variacao  vv  ON pvv.id_valor_variacao  = vv.id
        LEFT JOIN composicoes       c   ON vi.composicao_id       = c.id
        WHERE vi.venda_id = :pedido_id
        GROUP BY vi.id, vi.quantidade, vi.preco_unitario_final, vi.subtotal,
                 vi.valor_desconto, p.nome, c.nome
        ORDER BY vi.id
    ");
    $stmt->execute([':pedido_id' => $pedidoId]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $itensFormatados = array_map(function($item) {
        return [
            'id'             => $item['id'],
            'produto_nome'   => $item['composicao_nome'] ?? $item['produto_nome'] ?? 'Produto não identificado',
            'variacao'       => $item['variacao'],
            'quantidade'     => $item['quantidade'],
            'preco_unitario' => number_format($item['preco_unitario_final'], 2, ',', '.'),
            'subtotal'       => number_format($item['subtotal'], 2, ',', '.'),
            'desconto'       => ($item['valor_desconto'] > 0)
                                    ? number_format($item['valor_desconto'], 2, ',', '.')
                                    : null
        ];
    }, $itens);

    // ========================================
    // HISTÓRICO DE STATUS
    // ========================================
    $stmt = $db->prepare("
        SELECT
            DATE_FORMAT(hsp.data_alteracao, '%d/%m/%Y %H:%i') AS data_alteracao,
            sp.descricao AS status_descricao,
            sp.cor       AS status_cor,
            hsp.observacao,
            u.nome       AS usuario_nome
        FROM historico_status_pedido hsp
        INNER JOIN status_pedido sp ON hsp.status_pedido_id = sp.id
        LEFT JOIN  usuarios      u  ON hsp.usuario_alteracao = u.id
        WHERE hsp.venda_id = :pedido_id
        ORDER BY hsp.data_alteracao ASC
    ");
    $stmt->execute([':pedido_id' => $pedidoId]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'pedido'  => [
            'id'               => $pedido['id'],
            'cliente_nome'     => $pedido['cliente_nome'],
            'cliente_email'    => $pedido['cliente_email'],
            'cliente_telefone' => $pedido['cliente_telefone'] ?? 'Não informado',
            'data_venda'       => $pedido['data_venda'],
            'valor_total'      => number_format($pedido['valor_total'], 2, ',', '.'),
            'status_descricao' => $pedido['status_descricao'],
            'status_cor'       => $pedido['status_cor'],
            'status_pagamento' => $pedido['status_pagamento'] ?? 'Não informado'
        ],
        'itens'    => $itensFormatados,
        'historico' => $historico
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar detalhes: ' . $e->getMessage()
    ]);
}