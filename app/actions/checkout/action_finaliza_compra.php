<?php
// ============================================================
// ADICIONE ESTE BLOCO AO SEU api.php
// action=finalizar_compra  +  action=abacatepay_webhook
// ============================================================

define('ABACATEPAY_API_KEY', 'abc_dev_jQdZwuPPw2byEBaGN5SLEg1S'); // 🔑 troque pela chave real
define('ABACATEPAY_API_URL', 'https://api.abacatepay.com/v1');
define('STORE_URL',          'https://rs-beauty-store.com');

// ── Mapeamento método local → status_pagamento_id do banco ──
// Ajuste os IDs conforme os registros da sua tabela status_pagamento
const METODO_STATUS_ID = [
    'pix'            => 1,
    'cartao_credito' => 2,
    'cartao_debito'  => 3,
    'boleto'         => 4,
];

// ── HELPER: POST para AbacatePay ────────────────────────────
function abacatePost(string $endpoint, array $payload): array
{
    $ch = curl_init(ABACATEPAY_API_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . ABACATEPAY_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $body  = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) throw new RuntimeException('cURL error: ' . $error);

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Resposta inválida da AbacatePay: ' . $body);
    }

    return $decoded;
}

// ============================================================
// ACTION: finalizar_compra
// ============================================================
if ($action === 'finalizar_compra') {

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    $carrinho        = $input['carrinho']         ?? [];
    $metodoPagamento = $input['metodo_pagamento'] ?? null; // ex: "pix"
    $abacateMethods  = $input['abacate_methods']  ?? ['PIX', 'CARD']; // ex: ["PIX"]
    $valorTotal      = (float)($input['valor_total'] ?? 0);
    $taxId           = preg_replace('/\D/', '', $input['taxId'] ?? '');
    $end             = $input['endereco'] ?? [];

    if (empty($carrinho) || !$metodoPagamento || $valorTotal <= 0) {
        echo json_encode(['success' => false, 'message' => 'Carrinho, método ou valor inválido']);
        exit;
    }

    // Resolver status_pagamento_id a partir do método escolhido
    $statusPagamentoId = METODO_STATUS_ID[$metodoPagamento] ?? null;
    if (!$statusPagamentoId) {
        // Fallback: buscar pelo nome no banco
        $db = getPDO();
        $row = $db->query("SELECT id FROM status_pagamento LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $statusPagamentoId = $row['id'] ?? 1;
    }

    $db = getPDO();

    try {
        $db->beginTransaction();

        // ── 1. Separar rua e número ──────────────────────────
        $enderecoCompleto = $end['endereco'] ?? '';
        $partes  = explode(',', $enderecoCompleto, 2);
        $rua     = trim($partes[0] ?? $enderecoCompleto);
        $numero  = trim($partes[1] ?? 'S/N');

        // ── 2. Inserir endereço ──────────────────────────────
        $db->prepare("
            INSERT INTO enderecos
                (cep, rua, numero, complemento, bairro, cidade, estado,
                 tipo_endereco_id, usuario_id)
            VALUES
                (:cep, :rua, :numero, :complemento, :bairro, :cidade, :estado,
                 :tipo_endereco_id, :usuario_id)
        ")->execute([
            ':cep'             => $end['cep']              ?? '',
            ':rua'             => $rua,
            ':numero'          => $numero,
            ':complemento'     => $end['complemento']      ?? null,
            ':bairro'          => $end['bairro']           ?? '',
            ':cidade'          => $end['cidade']           ?? '',
            ':estado'          => $end['estado']           ?? 'ES',
            ':tipo_endereco_id'=> $end['tipo_endereco_id'] ?? 1,
            ':usuario_id'      => $_SESSION['user_id'],
        ]);

        $enderecoId = $db->lastInsertId();

        // ── 3. Status inicial do pedido ──────────────────────
        $statusPedidoInicial = $db->query(
            "SELECT id FROM status_pedido ORDER BY ordem LIMIT 1"
        )->fetchColumn();

        // ── 4. Inserir venda ─────────────────────────────────
        $db->prepare("
            INSERT INTO vendas
                (valor_total, status_pagamento_id, usuario_id,
                 status_pedido_id, endereco_id)
            VALUES
                (:valor_total, :status_pagamento_id, :usuario_id,
                 :status_pedido_id, :endereco_id)
        ")->execute([
            ':valor_total'        => $valorTotal,
            ':status_pagamento_id'=> $statusPagamentoId,
            ':usuario_id'         => $_SESSION['user_id'],
            ':status_pedido_id'   => $statusPedidoInicial,
            ':endereco_id'        => $enderecoId,
        ]);

        $vendaId = $db->lastInsertId();

        // ── 5. Inserir itens ─────────────────────────────────
        $stmtItem = $db->prepare("
            INSERT INTO vendas_itens
                (venda_id, quantidade, preco_unitario_original,
                 valor_desconto, preco_unitario_final, produto_variacao_id)
            VALUES
                (:venda_id, :quantidade, :preco_original,
                 0, :preco_final, :produto_variacao_id)
        ");

        foreach ($carrinho as $item) {
            $preco = (float)($item['preco'] ?? 0);
            $stmtItem->execute([
                ':venda_id'           => $vendaId,
                ':quantidade'         => (int)($item['quantidade'] ?? 1),
                ':preco_original'     => $preco,
                ':preco_final'        => $preco,
                ':produto_variacao_id'=> $item['variacao_id'] ?? null,
            ]);
        }

        // ── 6. Montar payload AbacatePay ─────────────────────
        // Passa apenas os métodos compatíveis com o que o cliente escolheu
        // AbacatePay aceita: "PIX" e/ou "CARD"
        $products = array_map(function ($item) {
            return [
                'externalId'  => STORE_URL . '/produto.php?id=' . ($item['id'] ?? 0),
                'name'        => $item['nome'] ?? 'Produto',
                'description' => $item['variacoesTexto'] ?? ($item['nome'] ?? ''),
                'quantity'    => (int)($item['quantidade'] ?? 1),
                'price'       => (int) round((float)($item['preco'] ?? 0) * 100), // centavos
            ];
        }, $carrinho);

        $abacatePayload = [
            'frequency'     => 'ONE_TIME',
            'methods'       => $abacateMethods,   // ["PIX"] ou ["CARD"]
            'products'      => $products,
            'returnUrl'     => STORE_URL . '/carrinho.php',
            'completionUrl' => STORE_URL . '/pedido_confirmado.php?id=' . $vendaId,
            'customer'      => [
                'name'      => $end['nome']     ?? '',
                'cellphone' => $end['telefone'] ?? '',
                'email'     => $end['email']    ?? '',
                'taxId'     => $taxId,
            ],
            'externalId'    => 'venda_' . $vendaId,
            'metadata'      => [
                'venda_id'        => (string) $vendaId,
                'usuario_id'      => (string) ($_SESSION['user_id'] ?? ''),
                'metodo_escolhido'=> $metodoPagamento,
            ],
            'allowCoupons'  => false,
        ];

        // ── 7. Criar cobrança na AbacatePay ──────────────────
        $resp = abacatePost('/billing/create', $abacatePayload);

        $paymentUrl = $resp['data']['url'] ?? null;
        $billingId  = $resp['data']['id']  ?? null;

        if (!$paymentUrl) {
            throw new RuntimeException(
                'AbacatePay não retornou URL de pagamento. Resposta: ' . json_encode($resp)
            );
        }

        // ── 8. Salvar billing_id ──────────────────────────────
        $db->prepare(
            "UPDATE vendas SET abacatepay_billing_id = :bid WHERE id = :id"
        )->execute([':bid' => $billingId, ':id' => $vendaId]);

        // ── 9. Registrar histórico inicial ───────────────────
        $db->prepare("
            INSERT INTO historico_status_pedido
                (venda_id, status_pedido_id, observacao)
            VALUES
                (:venda_id, :status_id, :obs)
        ")->execute([
            ':venda_id'  => $vendaId,
            ':status_id' => $statusPedidoInicial,
            ':obs'       => 'Pedido criado — aguardando pagamento via ' . strtoupper($metodoPagamento),
        ]);

        $db->commit();

        echo json_encode([
            'success'     => true,
            'venda_id'    => $vendaId,
            'payment_url' => $paymentUrl,
            'billing_id'  => $billingId,
        ]);

    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[finalizar_compra] ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno ao processar pedido: ' . $e->getMessage(),
        ]);
    }

    exit;
}

// ============================================================
// ACTION: abacatepay_webhook
// URL para registrar no painel AbacatePay:
//   https://rs-beauty-store.com/api.php?action=abacatepay_webhook
// ============================================================
if ($action === 'abacatepay_webhook') {

    $payload    = json_decode(file_get_contents('php://input'), true);
    $event      = $payload['event']                         ?? '';
    $externalId = $payload['data']['billing']['externalId'] ?? '';
    $billingId  = $payload['data']['billing']['id']         ?? '';

    if ($event === 'BILLING_PAID' && $externalId) {
        $vendaId = (int) str_replace('venda_', '', $externalId);
        $db = getPDO();

        $statusPago = $db->query(
            "SELECT id FROM status_pagamento
             WHERE LOWER(descricao) LIKE '%pago%'
                OR LOWER(descricao) LIKE '%aprovado%'
             LIMIT 1"
        )->fetchColumn();

        $statusPedidoPago = $db->query(
            "SELECT id FROM status_pedido ORDER BY ordem LIMIT 1 OFFSET 1"
        )->fetchColumn();

        if ($vendaId && $statusPago) {
            $db->prepare("
                UPDATE vendas
                SET status_pagamento_id = :sp,
                    status_pedido_id    = :sped
                WHERE id = :id
            ")->execute([
                ':sp'   => $statusPago,
                ':sped' => $statusPedidoPago ?: null,
                ':id'   => $vendaId,
            ]);

            $db->prepare("
                INSERT INTO historico_status_pedido
                    (venda_id, status_pedido_id, observacao)
                VALUES
                    (:venda_id, :status_id, 'Pagamento confirmado via AbacatePay')
            ")->execute([
                ':venda_id'  => $vendaId,
                ':status_id' => $statusPedidoPago,
            ]);
        }

        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Evento não reconhecido: ' . $event]);
    exit;
}