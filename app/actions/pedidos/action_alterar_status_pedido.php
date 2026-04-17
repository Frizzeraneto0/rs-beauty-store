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

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$pedidoId    = $data['pedido_id']       ?? null;
$novoStatusId = $data['status_pedido_id'] ?? null;
$observacao  = $data['observacao']      ?? null;

if (!$pedidoId || !$novoStatusId) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

try {
    // ========================================
    // VERIFICAR PEDIDO
    // ========================================
    $stmt = $db->prepare("
        SELECT
            v.id,
            v.status_pedido_id,
            sp.descricao          AS status_atual,
            sp.permite_cancelamento
        FROM vendas v
        INNER JOIN status_pedido sp ON v.status_pedido_id = sp.id
        WHERE v.id = :pedido_id
    ");
    $stmt->execute([':pedido_id' => $pedidoId]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
        exit;
    }

    // ========================================
    // VERIFICAR NOVO STATUS
    // ========================================
    $stmt = $db->prepare("
        SELECT id, descricao
        FROM status_pedido
        WHERE id = :status_id
    ");
    $stmt->execute([':status_id' => $novoStatusId]);
    $novoStatus = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$novoStatus) {
        echo json_encode(['success' => false, 'message' => 'Status inválido']);
        exit;
    }

    // ========================================
    // VALIDAÇÕES DE NEGÓCIO
    // ========================================
    $statusAtualId = $pedido['status_pedido_id'];

    if ($statusAtualId == $novoStatusId) {
        echo json_encode(['success' => false, 'message' => 'O pedido já está neste status']);
        exit;
    }

    // Não pode sair de "Entregue" (6) a não ser para "Devolvido" (8)
    if ($statusAtualId == 6 && $novoStatusId != 8) {
        echo json_encode(['success' => false, 'message' => 'Não é possível alterar status de um pedido já entregue. Apenas devolução é permitida.']);
        exit;
    }

    // Não pode sair de "Cancelado" (7)
    if ($statusAtualId == 7) {
        echo json_encode(['success' => false, 'message' => 'Não é possível alterar status de um pedido cancelado']);
        exit;
    }

    // Cancelamento só se permitido
    if ($novoStatusId == 7 && !$pedido['permite_cancelamento']) {
        echo json_encode(['success' => false, 'message' => 'Este pedido não pode mais ser cancelado no status atual']);
        exit;
    }

    // ========================================
    // ATUALIZAR STATUS
    // ========================================
    $db->beginTransaction();

    $stmt = $db->prepare("
        UPDATE vendas
        SET status_pedido_id = :novo_status
        WHERE id = :pedido_id
    ");
    $stmt->execute([
        ':novo_status' => $novoStatusId,
        ':pedido_id'   => $pedidoId
    ]);

    // ========================================
    // REGISTRAR HISTÓRICO
    // ========================================
    $stmt = $db->prepare("
        INSERT INTO historico_status_pedido
            (venda_id, status_pedido_id, observacao, usuario_alteracao, data_alteracao)
        VALUES
            (:venda_id, :status_id, :observacao, :usuario_id, NOW())
    ");
    $stmt->execute([
        ':venda_id'   => $pedidoId,
        ':status_id'  => $novoStatusId,
        ':observacao' => $observacao ?: null,
        ':usuario_id' => $_SESSION['user_id'] ?? null
    ]);

    $db->commit();

    echo json_encode([
        'success'    => true,
        'message'    => 'Status alterado com sucesso!',
        'novo_status' => $novoStatus['descricao']
    ]);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao alterar status: ' . $e->getMessage()
    ]);
}