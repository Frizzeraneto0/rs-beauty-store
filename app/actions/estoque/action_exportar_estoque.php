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

$filtroCategoria = $_GET['categoria'] ?? '';
$filtroProduto   = $_GET['produto']   ?? '';
$filtroEstoque   = $_GET['estoque']   ?? '';

try {
    $sqlEstoque = "
        SELECT
            p.nome AS produto,
            (
                SELECT GROUP_CONCAT(vv.valor ORDER BY vv.valor SEPARATOR ' / ')
                FROM produto_variacao_valores pvv
                INNER JOIN valores_variacao vv ON pvv.id_valor_variacao = vv.id
                WHERE pvv.id_produto_variacao = pv.id
            ) AS variacao,
            c.nome         AS categoria,
            ea.quantidade,
            p.preco        AS preco_unitario,
            (ea.quantidade * p.preco) AS valor_total,
            CASE
                WHEN ea.quantidade = 0       THEN 'Zerado'
                WHEN ea.quantidade <= 10     THEN 'Baixo'
                ELSE                              'Disponível'
            END AS situacao
        FROM estoque_atual ea
        INNER JOIN produto_variacoes pv ON ea.id_produto_variacao = pv.id
        INNER JOIN produtos          p  ON pv.id_produto          = p.id
        LEFT JOIN  categorias        c  ON p.categoria_id         = c.id
        WHERE 1=1
    ";

    $params = [];

    if ($filtroCategoria) {
        $sqlEstoque .= " AND p.categoria_id = :categoria";
        $params[':categoria'] = $filtroCategoria;
    }

    if ($filtroProduto) {
        $sqlEstoque .= " AND p.nome LIKE :produto";
        $params[':produto'] = '%' . $filtroProduto . '%';
    }

    if ($filtroEstoque === 'zerado') {
        $sqlEstoque .= " AND ea.quantidade = 0";
    } elseif ($filtroEstoque === 'baixo') {
        $sqlEstoque .= " AND ea.quantidade > 0 AND ea.quantidade <= 10";
    } elseif ($filtroEstoque === 'disponivel') {
        $sqlEstoque .= " AND ea.quantidade > 10";
    }

    $sqlEstoque .= " ORDER BY p.nome, variacao";

    $stmt = $db->prepare($sqlEstoque);
    $stmt->execute($params);
    $estoques = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========================================
    // GERAR CSV
    // ========================================
    $filename = 'estoque_atual_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // BOM UTF-8 para Excel
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Cabeçalho
    fputcsv($output, [
        'Produto',
        'Variação',
        'Categoria',
        'Quantidade',
        'Preço Unitário',
        'Valor Total',
        'Situação'
    ], ';');

    // Dados
    foreach ($estoques as $e) {
        fputcsv($output, [
            $e['produto'],
            $e['variacao'] ?: 'Padrão',
            $e['categoria'] ?: '',
            $e['quantidade'],
            'R$ ' . number_format($e['preco_unitario'], 2, ',', '.'),
            'R$ ' . number_format($e['valor_total'],    2, ',', '.'),
            $e['situacao']
        ], ';');
    }

    // Linha de totais
    fputcsv($output, [], ';');
    fputcsv($output, [
        'TOTAIS',
        '',
        '',
        array_sum(array_column($estoques, 'quantidade')),
        '',
        'R$ ' . number_format(array_sum(array_column($estoques, 'valor_total')), 2, ',', '.'),
        ''
    ], ';');

    fclose($output);
    exit;

} catch (Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao exportar: ' . $e->getMessage()
    ]);
}