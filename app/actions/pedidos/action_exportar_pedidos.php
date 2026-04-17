<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

/*
session_start();
if (!isset($_SESSION['access_token'])) {
    header('Location: /admin/login.php');
    exit;
}
*/

$filtroStatus     = $_GET['status']      ?? '';
$filtroDataInicio = $_GET['data_inicio'] ?? '';
$filtroDataFim    = $_GET['data_fim']    ?? '';
$filtroBusca      = $_GET['busca']       ?? '';

try {
    $sqlPedidos = "
        SELECT
            v.id,
            DATE_FORMAT(v.data_venda, '%d/%m/%Y %H:%i') AS data_venda,
            v.valor_total,
            sp.descricao  AS status_pedido,
            spg.descricao AS status_pagamento,
            u.nome        AS cliente_nome,
            u.email       AS cliente_email,
            u.telefone    AS cliente_telefone,
            (SELECT COUNT(*) FROM vendas_itens WHERE venda_id = v.id) AS qtd_itens,
            (
                SELECT GROUP_CONCAT(
                    COALESCE(p.nome, c.nome, 'Produto') ,
                    ' (x', vi2.quantidade, ')'
                    ORDER BY vi2.id
                    SEPARATOR ', '
                )
                FROM vendas_itens vi2
                LEFT JOIN produto_variacoes pv2 ON vi2.produto_variacao_id = pv2.id
                LEFT JOIN produtos          p   ON pv2.id_produto          = p.id
                LEFT JOIN composicoes       c   ON vi2.composicao_id       = c.id
                WHERE vi2.venda_id = v.id
            ) AS itens_resumo
        FROM vendas v
        LEFT JOIN status_pedido    sp  ON v.status_pedido_id    = sp.id
        LEFT JOIN status_pagamento spg ON v.status_pagamento_id = spg.id
        LEFT JOIN usuarios         u   ON v.usuario_id          = u.id
        WHERE 1=1
    ";

    $params = [];

    if ($filtroStatus) {
        $sqlPedidos .= " AND v.status_pedido_id = :status";
        $params[':status'] = $filtroStatus;
    }

    if ($filtroDataInicio) {
        $sqlPedidos .= " AND DATE(v.data_venda) >= :data_inicio";
        $params[':data_inicio'] = $filtroDataInicio;
    }

    if ($filtroDataFim) {
        $sqlPedidos .= " AND DATE(v.data_venda) <= :data_fim";
        $params[':data_fim'] = $filtroDataFim;
    }

    if ($filtroBusca) {
        $sqlPedidos .= " AND (u.nome LIKE :busca OR u.email LIKE :busca OR CAST(v.id AS CHAR) LIKE :busca)";
        $params[':busca'] = '%' . $filtroBusca . '%';
    }

    $sqlPedidos .= " ORDER BY v.data_venda DESC";

    $stmt = $db->prepare($sqlPedidos);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========================================
    // GERAR CSV
    // ========================================
    $filename = 'pedidos_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // BOM UTF-8 (para Excel abrir corretamente)
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Cabeçalho
    fputcsv($output, [
        'Pedido',
        'Cliente',
        'Email',
        'Telefone',
        'Data/Hora',
        'Status Pedido',
        'Status Pagamento',
        'Qtd Itens',
        'Itens',
        'Valor Total'
    ], ';');

    // Dados
    foreach ($pedidos as $p) {
        fputcsv($output, [
            '#' . str_pad($p['id'], 6, '0', STR_PAD_LEFT),
            $p['cliente_nome']     ?? '',
            $p['cliente_email']    ?? '',
            $p['cliente_telefone'] ?? '',
            $p['data_venda'],
            $p['status_pedido']    ?? '',
            $p['status_pagamento'] ?? '',
            $p['qtd_itens'],
            $p['itens_resumo']     ?? '',
            'R$ ' . number_format($p['valor_total'], 2, ',', '.')
        ], ';');
    }

    // Linha de totais
    if (!empty($pedidos)) {
        $totalPedidos = count($pedidos);
        $valorTotal   = array_sum(array_column($pedidos, 'valor_total'));
        $totalItens   = array_sum(array_column($pedidos, 'qtd_itens'));

        fputcsv($output, [], ';');
        fputcsv($output, [
            'TOTAIS',
            '',
            '',
            '',
            '',
            '',
            '',
            $totalItens . ' itens',
            $totalPedidos . ' pedidos',
            'R$ ' . number_format($valorTotal, 2, ',', '.')
        ], ';');
    }

    fclose($output);
    exit;

} catch (Throwable $e) {
    // Se falhar depois de iniciar o CSV, retorna JSON de erro
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao exportar: ' . $e->getMessage()
    ]);
}