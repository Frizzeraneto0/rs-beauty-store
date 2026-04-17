<?php
/**
 * ACTION: webhook_abacatepay
 * Pasta: app/actions/pedidos/action_webhook_abacatepay.php
 *
 * Correções aplicadas:
 *  1. Evento é "billing.paid" (minúsculo com ponto), não "BILLING_PAID"
 *  2. externalId vem vazio — buscar venda pelo billing_id (data.billing.id)
 *     que já é salvo na coluna abacatepay_billing_id ao criar a cobrança
 */

$logFile = $_SERVER['DOCUMENT_ROOT'] . '/webhook_debug.log';

function wlog(string $msg): void {
    global $logFile;
    file_put_contents(
        $logFile,
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

$rawBody = file_get_contents('php://input');

wlog("=== WEBHOOK RECEBIDO ===");
wlog("Raw body: " . $rawBody);

$payload = json_decode($rawBody, true);

if (!$payload || !isset($payload['event'])) {
    wlog("ERRO: Payload inválido. JSON error: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido']);
    exit;
}

$event     = $payload['event']               ?? '';
$billingId = $payload['data']['billing']['id'] ?? '';

wlog("event={$event} | billingId={$billingId}");

if (empty($billingId)) {
    wlog("ERRO: billingId vazio no payload");
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'billingId ausente']);
    exit;
}

// ── Buscar venda pelo billing_id salvo no banco ──────────────
// (salvo em action_finaliza_compra.php após criar a cobrança)
$stmtVenda = $db->prepare("
    SELECT id, status_pedido_id, status_pagamento_id
    FROM vendas
    WHERE abacatepay_billing_id = ?
    LIMIT 1
");
$stmtVenda->execute([$billingId]);
$venda = $stmtVenda->fetch(PDO::FETCH_ASSOC);

if (!$venda) {
    wlog("ERRO: Nenhuma venda encontrada para billing_id={$billingId}");
    // Retornar 200 para AbacatePay não retentar indefinidamente
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Venda não encontrada para este billing']);
    exit;
}

$vendaId = (int) $venda['id'];
wlog("Venda encontrada: id={$vendaId} status_pagamento_id={$venda['status_pagamento_id']} status_pedido_id={$venda['status_pedido_id']}");

try {
    // ── Eventos: AbacatePay usa letras minúsculas com ponto ──
    // billing.paid / billing.expired / billing.cancelled
    switch ($event) {

        case 'billing.paid': {

            // Buscar status_pagamento "Confirmado" (id=2 na sua tabela)
            $rowPago = $db->query("
                SELECT id, descricao FROM status_pagamento
                WHERE LOWER(descricao) LIKE '%confirm%'
                   OR LOWER(descricao) LIKE '%pago%'
                   OR LOWER(descricao) LIKE '%aprovado%'
                   OR LOWER(descricao) LIKE '%conclu%'
                ORDER BY id LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC);

            $statusPagoId = $rowPago ? (int)$rowPago['id'] : 2;
            wlog("status_pagamento: id={$statusPagoId} desc=" . ($rowPago['descricao'] ?? 'fallback=2'));

            // Próximo status_pedido (ordem 2 = "Em Processamento")
            $rowPedido = $db->query("
                SELECT id, descricao FROM status_pedido ORDER BY ordem LIMIT 1 OFFSET 1
            ")->fetch(PDO::FETCH_ASSOC);

            if (!$rowPedido) {
                $rowPedido = $db->query(
                    "SELECT id, descricao FROM status_pedido ORDER BY ordem LIMIT 1"
                )->fetch(PDO::FETCH_ASSOC);
            }

            $statusPedidoPagoId = $rowPedido ? (int)$rowPedido['id'] : 2;
            wlog("status_pedido para pago: id={$statusPedidoPagoId} desc=" . ($rowPedido['descricao'] ?? '?'));

            // UPDATE da venda
            $stmtUpdate = $db->prepare("
                UPDATE vendas
                SET status_pagamento_id   = :sp,
                    status_pedido_id      = :sped
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':sp'   => $statusPagoId,
                ':sped' => $statusPedidoPagoId,
                ':id'   => $vendaId,
            ]);

            $rowsAffected = $stmtUpdate->rowCount();
            wlog("UPDATE executado. Rows affected: {$rowsAffected}");

            // Histórico
            $db->prepare("
                INSERT INTO historico_status_pedido (venda_id, status_pedido_id, observacao)
                VALUES (?, ?, ?)
            ")->execute([
                $vendaId,
                $statusPedidoPagoId,
                'Pagamento confirmado via AbacatePay. Billing ID: ' . $billingId,
            ]);

            wlog("Venda #{$vendaId} → PAGA com sucesso.");
            wlog("=== FIM ===");

            http_response_code(200);
            echo json_encode([
                'success'             => true,
                'venda_id'            => $vendaId,
                'status_pagamento_id' => $statusPagoId,
                'status_pedido_id'    => $statusPedidoPagoId,
                'rows_affected'       => $rowsAffected,
            ]);
            break;
        }

        case 'billing.expired':
        case 'billing.cancelled': {

            wlog("Evento cancelamento/expiração.");

            $rowCancelado = $db->query("
                SELECT id FROM status_pedido
                WHERE LOWER(descricao) LIKE '%cancelad%'
                   OR LOWER(slug)      LIKE '%cancelad%'
                LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC);

            if ($rowCancelado) {
                $db->prepare("UPDATE vendas SET status_pedido_id = ? WHERE id = ?")
                   ->execute([$rowCancelado['id'], $vendaId]);

                $db->prepare("
                    INSERT INTO historico_status_pedido (venda_id, status_pedido_id, observacao)
                    VALUES (?, ?, ?)
                ")->execute([
                    $vendaId,
                    $rowCancelado['id'],
                    'Cobrança expirada/cancelada via AbacatePay. Billing ID: ' . $billingId,
                ]);

                wlog("Venda #{$vendaId} cancelada (status_pedido_id={$rowCancelado['id']}).");
            } else {
                wlog("AVISO: status 'cancelado' não encontrado em status_pedido.");
            }

            wlog("=== FIM ===");
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Expiração registrada']);
            break;
        }

        default: {
            wlog("Evento não tratado: {$event}");
            wlog("=== FIM ===");
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Evento ignorado: ' . $event]);
            break;
        }
    }

} catch (Throwable $e) {
    wlog("EXCEÇÃO: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    wlog("=== FIM (erro) ===");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}