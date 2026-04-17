<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

/*
session_start();
if (!isset($_SESSION['access_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}
*/

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$fornecedorId = $data['fornecedor_id'] ?? null;
$dataCompra   = $data['data_compra']   ?? date('Y-m-d H:i:s');
$itens        = $data['itens']         ?? [];

if (empty($itens)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Adicione pelo menos um item']);
    exit;
}

try {
    $db->beginTransaction();

    // ========================================
    // 1. CALCULAR VALOR TOTAL
    // ========================================
    $valorTotal = 0;
    foreach ($itens as $item) {
        $qtd = 0;
        if (isset($item['variacoes'])) {
            foreach ($item['variacoes'] as $var) {
                $qtd += $var['quantidade'];
            }
        } else {
            $qtd = $item['quantidade'] ?? 0;
        }
        $valorTotal += ($qtd * $item['preco_unitario']);
    }

    // ========================================
    // 2. CRIAR COMPRA
    // ========================================
    $stmt = $db->prepare("
        INSERT INTO compras (fornecedor_id, data_compra, valor_total)
        VALUES (:fornecedor, :data, :total)
    ");
    $stmt->execute([
        ':fornecedor' => $fornecedorId,
        ':data'       => $dataCompra,
        ':total'      => $valorTotal
    ]);

    $compraId = $db->lastInsertId();

    // ========================================
    // 3. PROCESSAR CADA ITEM
    // ========================================
    foreach ($itens as $item) {
        $tipo = $item['tipo'];

        if ($tipo === 'produto') {
            $produtoId = $item['produto_id'];
            $precoUnit = $item['preco_unitario'];
            $variacoes = $item['variacoes'] ?? [];

            if (!empty($variacoes)) {
                // Produto COM variações especificadas
                $qtdTotal = 0;
                foreach ($variacoes as $var) {
                    $qtdTotal += $var['quantidade'];
                }

                $stmt = $db->prepare("
                    INSERT INTO compras_itens (compra_id, produto_id, quantidade, preco_unitario)
                    VALUES (:compra, :produto, :qtd, :preco)
                ");
                $stmt->execute([
                    ':compra'   => $compraId,
                    ':produto'  => $produtoId,
                    ':qtd'      => $qtdTotal,
                    ':preco'    => $precoUnit
                ]);

                $compraItemId = $db->lastInsertId();

                foreach ($variacoes as $var) {
                    $variacaoId = $var['produto_variacao_id'];
                    $qtd        = $var['quantidade'];

                    $stmt = $db->prepare("
                        INSERT INTO compra_itens_variacoes (compra_item_id, produto_variacao_id, quantidade, status)
                        VALUES (:item, :variacao, :qtd, 'processado')
                    ");
                    $stmt->execute([
                        ':item'     => $compraItemId,
                        ':variacao' => $variacaoId,
                        ':qtd'      => $qtd
                    ]);

                    $compraItemVariacaoId = $db->lastInsertId();

                    // Movimentação de estoque (ENTRADA = tipo_id 1)
                    $stmt = $db->prepare("
                        INSERT INTO movimentacoes_estoque
                            (produto_variacao_id, tipo_id, quantidade, motivo, compra_itens_variacoes_id)
                        VALUES (:variacao, 1, :qtd, :motivo, :compra_var_id)
                    ");
                    $stmt->execute([
                        ':variacao'      => $variacaoId,
                        ':qtd'           => $qtd,
                        ':motivo'        => "Compra #$compraId",
                        ':compra_var_id' => $compraItemVariacaoId
                    ]);

                    // Atualizar estoque_atual
                    $stmt = $db->prepare("
                        INSERT INTO estoque_atual (id_produto_variacao, quantidade)
                        VALUES (:variacao, :qtd_insert)
                        ON DUPLICATE KEY UPDATE quantidade = quantidade + :qtd_update
                    ");
                    $stmt->execute([
                        ':variacao'    => $variacaoId,
                        ':qtd_insert'  => $qtd,
                        ':qtd_update'  => $qtd
                    ]);
                }

            } else {
                // Produto SEM variações → usar variação padrão
                $stmt = $db->prepare("
                    SELECT id FROM produto_variacoes
                    WHERE id_produto = :produto
                    LIMIT 1
                ");
                $stmt->execute([':produto' => $produtoId]);
                $variacaoPadrao = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$variacaoPadrao) {
                    throw new Exception("Produto sem variação cadastrada. ID: $produtoId");
                }

                $qtd = $item['quantidade'] ?? 1;

                $stmt = $db->prepare("
                    INSERT INTO compras_itens (compra_id, produto_id, quantidade, preco_unitario)
                    VALUES (:compra, :produto, :qtd, :preco)
                ");
                $stmt->execute([
                    ':compra'  => $compraId,
                    ':produto' => $produtoId,
                    ':qtd'     => $qtd,
                    ':preco'   => $precoUnit
                ]);

                $compraItemId = $db->lastInsertId();

                $stmt = $db->prepare("
                    INSERT INTO compra_itens_variacoes (compra_item_id, produto_variacao_id, quantidade, status)
                    VALUES (:item, :variacao, :qtd, 'processado')
                ");
                $stmt->execute([
                    ':item'     => $compraItemId,
                    ':variacao' => $variacaoPadrao['id'],
                    ':qtd'      => $qtd
                ]);

                $compraItemVariacaoId = $db->lastInsertId();

                $stmt = $db->prepare("
                    INSERT INTO movimentacoes_estoque
                        (produto_variacao_id, tipo_id, quantidade, motivo, compra_itens_variacoes_id)
                    VALUES (:variacao, 1, :qtd, :motivo, :compra_var_id)
                ");
                $stmt->execute([
                    ':variacao'      => $variacaoPadrao['id'],
                    ':qtd'           => $qtd,
                    ':motivo'        => "Compra #$compraId",
                    ':compra_var_id' => $compraItemVariacaoId
                ]);

                // Atualizar estoque_atual
                $stmt = $db->prepare("
                    INSERT INTO estoque_atual (id_produto_variacao, quantidade)
                    VALUES (:variacao, :qtd_insert)
                    ON DUPLICATE KEY UPDATE quantidade = quantidade + :qtd_update
                ");
                $stmt->execute([
                    ':variacao'   => $variacaoPadrao['id'],
                    ':qtd_insert' => $qtd,
                    ':qtd_update' => $qtd
                ]);
            }

        } elseif ($tipo === 'composicao') {
            $composicaoId = $item['composicao_id'];
            $qtdKits      = $item['quantidade'];
            $precoUnit    = $item['preco_unitario'];

            $stmt = $db->prepare("
                INSERT INTO compras_itens (compra_id, composicao_id, quantidade, preco_unitario)
                VALUES (:compra, :composicao, :qtd, :preco)
            ");
            $stmt->execute([
                ':compra'     => $compraId,
                ':composicao' => $composicaoId,
                ':qtd'        => $qtdKits,
                ':preco'      => $precoUnit
            ]);

            $compraItemId = $db->lastInsertId();

            // Buscar itens da composição
            $stmt = $db->prepare("
                SELECT produto_id, produto_variacao_id, quantidade
                FROM composicoes_itens
                WHERE composicao_id = :composicao
            ");
            $stmt->execute([':composicao' => $composicaoId]);
            $itensComposicao = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($itensComposicao as $itemComp) {
                $produtoId  = $itemComp['produto_id'];
                $variacaoId = $itemComp['produto_variacao_id'];
                $qtdPorKit  = $itemComp['quantidade'];
                $qtdTotal   = $qtdPorKit * $qtdKits;

                // Se não tem variação específica, usar padrão
                if (!$variacaoId) {
                    $stmt = $db->prepare("
                        SELECT id FROM produto_variacoes
                        WHERE id_produto = :produto
                        LIMIT 1
                    ");
                    $stmt->execute([':produto' => $produtoId]);
                    $varPadrao  = $stmt->fetch(PDO::FETCH_ASSOC);
                    $variacaoId = $varPadrao['id'];
                }

                $stmt = $db->prepare("
                    INSERT INTO compra_itens_variacoes (compra_item_id, produto_variacao_id, quantidade, status)
                    VALUES (:item, :variacao, :qtd, 'processado')
                ");
                $stmt->execute([
                    ':item'     => $compraItemId,
                    ':variacao' => $variacaoId,
                    ':qtd'      => $qtdTotal
                ]);

                $compraItemVariacaoId = $db->lastInsertId();

                $stmt = $db->prepare("
                    INSERT INTO movimentacoes_estoque
                        (produto_variacao_id, tipo_id, quantidade, motivo, compra_itens_variacoes_id)
                    VALUES (:variacao, 1, :qtd, :motivo, :compra_var_id)
                ");
                $stmt->execute([
                    ':variacao'      => $variacaoId,
                    ':qtd'           => $qtdTotal,
                    ':motivo'        => "Compra #$compraId - Kit",
                    ':compra_var_id' => $compraItemVariacaoId
                ]);

                // Atualizar estoque_atual
                $stmt = $db->prepare("
                    INSERT INTO estoque_atual (id_produto_variacao, quantidade)
                    VALUES (:variacao, :qtd_insert)
                    ON DUPLICATE KEY UPDATE quantidade = quantidade + :qtd_update
                ");
                $stmt->execute([
                    ':variacao'   => $variacaoId,
                    ':qtd_insert' => $qtdTotal,
                    ':qtd_update' => $qtdTotal
                ]);
            }
        }
    }

    $db->commit();

    echo json_encode([
        'success'   => true,
        'message'   => 'Compra registrada com sucesso',
        'compra_id' => $compraId
    ]);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao registrar compra: ' . $e->getMessage(),
        'error'   => $e->getMessage(),
        'line'    => $e->getLine()
    ]);
}