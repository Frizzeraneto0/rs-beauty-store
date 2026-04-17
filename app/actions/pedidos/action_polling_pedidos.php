<?php
/**
 * ACTION: polling_pedidos
 * Retorna status atualizado dos pedidos para o painel admin.
 * Usado pelo JS do pedidos.php para detectar mudanças em tempo real
 * sem necessidade de recarregar a página.
 *
 * GET /api.php?action=polling_pedidos&ids=1,2,3,42
 * ou
 * GET /api.php?action=polling_pedidos   → retorna os 50 pedidos mais recentes
 */

$ids = isset($_GET['ids']) ? array_filter(array_map('intval', explode(',', $_GET['ids']))) : [];

try {
    if (!empty($ids)) {
        // Polling específico: checar apenas os pedidos enviados
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $db->prepare("
            SELECT
                v.id,
                v.status_pedido_id,
                v.status_pagamento_id,
                sp.descricao AS status_descricao,
                sp.cor       AS status_cor,
                sp.icone     AS status_icone,
                spg.descricao AS status_pagamento
            FROM vendas v
            LEFT JOIN status_pedido    sp  ON v.status_pedido_id    = sp.id
            LEFT JOIN status_pagamento spg ON v.status_pagamento_id = spg.id
            WHERE v.id IN ($placeholders)
        ");
        $stmt->execute($ids);

    } else {
        // Polling geral: 50 pedidos mais recentes (para badge de notificação)
        $stmt = $db->query("
            SELECT
                v.id,
                v.status_pedido_id,
                v.status_pagamento_id,
                sp.descricao AS status_descricao,
                sp.cor       AS status_cor,
                sp.icone     AS status_icone,
                spg.descricao AS status_pagamento
            FROM vendas v
            LEFT JOIN status_pedido    sp  ON v.status_pedido_id    = sp.id
            LEFT JOIN status_pagamento spg ON v.status_pagamento_id = spg.id
            ORDER BY v.data_venda DESC
            LIMIT 50
        ");
    }

    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'  => true,
        'pedidos'  => $pedidos,
        'timestamp'=> time(),
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}