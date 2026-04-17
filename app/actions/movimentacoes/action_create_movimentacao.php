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

// Aceita tanto JSON quanto POST form
$json = file_get_contents('php://input');
$jsonData = json_decode($json, true);

$produto_variacao_id = $jsonData['produto_variacao_id'] ?? $_POST['produto_variacao_id'] ?? null;
$tipo_id             = $jsonData['tipo_id']             ?? $_POST['tipo_id']             ?? null;
$quantidade          = $jsonData['quantidade']          ?? $_POST['quantidade']          ?? null;
$motivo              = $jsonData['motivo']              ?? $_POST['motivo']              ?? null;

if (!$produto_variacao_id || !$tipo_id || !$quantidade) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Campos obrigatórios não preenchidos']);
    exit;
}

if (!is_numeric($quantidade) || $quantidade <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Quantidade deve ser maior que zero']);
    exit;
}

try {
    // ========================================
    // VERIFICAR TIPO DE MOVIMENTAÇÃO
    // ========================================
    $stmt = $db->prepare("
        SELECT id, LOWER(descricao) AS descricao
        FROM tipo_movimentacao
        WHERE id = :tipo_id
    ");
    $stmt->execute([':tipo_id' => $tipo_id]);
    $tipoMovimentacao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tipoMovimentacao) {
        echo json_encode(['success' => false, 'message' => 'Tipo de movimentação inválido']);
        exit;
    }

    // ========================================
    // VALIDAR ESTOQUE PARA SAÍDAS
    // ========================================
    $isSaida = in_array($tipoMovimentacao['descricao'], ['saída', 'saida']);

    if ($isSaida) {
        $stmt = $db->prepare("
            SELECT quantidade
            FROM estoque_atual
            WHERE id_produto_variacao = :id_produto_variacao
        ");
        $stmt->execute([':id_produto_variacao' => $produto_variacao_id]);
        $estoqueAtual = $stmt->fetch(PDO::FETCH_ASSOC);

        $quantidadeDisponivel = $estoqueAtual['quantidade'] ?? 0;

        if ($quantidadeDisponivel < $quantidade) {
            echo json_encode([
                'success' => false,
                'message' => "Estoque insuficiente. Disponível: {$quantidadeDisponivel} unidades"
            ]);
            exit;
        }
    }

    // ========================================
    // INSERIR MOVIMENTAÇÃO
    // ========================================
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO movimentacoes_estoque
            (produto_variacao_id, tipo_id, quantidade, motivo, criado_em)
        VALUES
            (:produto_variacao_id, :tipo_id, :quantidade, :motivo, NOW())
    ");
    $stmt->execute([
        ':produto_variacao_id' => $produto_variacao_id,
        ':tipo_id'             => $tipo_id,
        ':quantidade'          => (int)$quantidade,
        ':motivo'              => $motivo ?: null
    ]);

    $movimentacaoId = $db->lastInsertId();

    // ========================================
    // ATUALIZAR estoque_atual MANUALMENTE
    // (MySQL não tem trigger automático como Supabase)
    // ========================================
    if ($isSaida) {
        $stmt = $db->prepare("
            UPDATE estoque_atual
            SET quantidade = quantidade - :quantidade
            WHERE id_produto_variacao = :id_produto_variacao
        ");
    } else {
        // entrada ou ajuste: soma
        $stmt = $db->prepare("
            INSERT INTO estoque_atual (id_produto_variacao, quantidade)
            VALUES (:id_produto_variacao, :quantidade)
            ON DUPLICATE KEY UPDATE quantidade = quantidade + :quantidade
        ");
    }
    $stmt->execute([
        ':id_produto_variacao' => $produto_variacao_id,
        ':quantidade'          => (int)$quantidade
    ]);

    // Buscar estoque atualizado
    $stmt = $db->prepare("
        SELECT quantidade
        FROM estoque_atual
        WHERE id_produto_variacao = :id_produto_variacao
    ");
    $stmt->execute([':id_produto_variacao' => $produto_variacao_id]);
    $estoqueNovo = $stmt->fetch(PDO::FETCH_ASSOC);

    $db->commit();

    echo json_encode([
        'success'       => true,
        'message'       => 'Movimentação registrada com sucesso!',
        'id'            => $movimentacaoId,
        'estoque_atual' => $estoqueNovo['quantidade'] ?? 0
    ]);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao registrar movimentação: ' . $e->getMessage()
    ]);
}