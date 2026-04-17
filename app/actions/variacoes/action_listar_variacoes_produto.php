<?php
/**
 * Lista todas as variações de um produto
 */

/*
if (!isset($_SESSION['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}
*/

try {
    $db = getPDO();
    
    $produtoId = $_GET['produto'] ?? null;
    
    if (empty($produtoId)) {
        echo json_encode(['success' => false, 'message' => 'ID do produto não informado']);
        exit;
    }
    
    // Buscar variações do produto
    $stmt = $db->prepare("
        SELECT 
            pv.id,
            pv.id_produto,
            GROUP_CONCAT(
                CONCAT(tv.nome, ': ', vv.valor)
                ORDER BY tv.nome
                SEPARATOR ' | '
            ) AS descricao
        FROM produto_variacoes pv
        LEFT JOIN produto_variacao_valores pvv ON pvv.id_produto_variacao = pv.id
        LEFT JOIN valores_variacao vv ON vv.id = pvv.id_valor_variacao
        LEFT JOIN tipos_variacao tv ON tv.id = pvv.id_tipo_variacao
        WHERE pv.id_produto = :produto_id
        GROUP BY pv.id, pv.id_produto
        ORDER BY pv.id
    ");
    
    $stmt->execute(['produto_id' => $produtoId]);
    $variacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'variacoes' => $variacoes
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao listar variações: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao listar variações']);
}