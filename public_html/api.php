<?php
/**
 * API ROUTER
 * Roteador central para todas as requisições da API
 * Chama actions protegidas em /app/actions/
 */

session_start();

/*
temporario
*/
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

$db = getPDO();

header('Content-Type: application/json');

// Pega a action da URL ou POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Action não especificada']);
    exit;
}

// Mapeamento de actions permitidas
$allowedActions = [
    // Produtos
    'create_produto'    => '../app/actions/produtos/action_create_produto.php',
    'update_produto'    => '../app/actions/produtos/action_update_produto.php',
    'delete_produto'    => '../app/actions/produtos/action_delete_produto.php',

    // Tipos e valores de variação
    'get_tipos_valores'  => '../app/actions/variacoes/action_get_tipos_valores.php',

    'create_tipo_variacao' => '../app/actions/variacoes/action_create_tipo_variacao.php',
    'update_tipo_variacao' => '../app/actions/variacoes/action_update_tipo_variacao.php',
    'delete_tipo_variacao' => '../app/actions/variacoes/action_delete_tipo_variacao.php',

    'create_valor_variacao' => '../app/actions/variacoes/action_create_valor_variacao.php',
    'update_valor_variacao' => '../app/actions/variacoes/action_update_valor_variacao.php',
    'delete_valor_variacao' => '../app/actions/variacoes/action_delete_valor_variacao.php',

    // Variações de produto
    'listar_variacoes_produto' => '../app/actions/variacoes/action_listar_variacoes_produto.php',
    'create_variacao_produto'  => '../app/actions/variacoes/action_create_variacao_produto.php',
    'update_variacao_default'  => '../app/actions/variacoes/action_update_variacao_default.php',
    'delete_variacao_produto'  => '../app/actions/variacoes/action_delete_variacao_produto.php',

    // Categorias
    'create_categoria' => '../app/actions/categorias/action_create_categoria.php',
    'update_categoria' => '../app/actions/categorias/action_update_categoria.php',
    'delete_categoria' => '../app/actions/categorias/action_delete_categoria.php',

    // Imagens
    'upload_imagem_produto' => '../app/actions/imagens/action_upload_imagem_produto.php',
    'delete_imagem_produto' => '../app/actions/imagens/action_delete_imagem_produto.php',
    'reordenar_imagens'     => '../app/actions/imagens/action_reordenar_imagens_produto.php',

    // Composições
    'create_composicao'      => '../app/actions/composicoes/action_create_composicao.php',
    'update_composicao'      => '../app/actions/composicoes/action_update_composicao.php',
    'delete_composicao'      => '../app/actions/composicoes/action_delete_composicao.php',
    'get_produtos_variacoes' => '../app/actions/composicoes/action_get_produtos_variacoes.php',
    'listar_itens_composicao' => '../app/actions/composicoes/action_listar_itens_composicao.php',
    'create_item_composicao' => '../app/actions/composicoes/action_create_item_composicao.php',
    'update_item_composicao' => '../app/actions/composicoes/action_update_item_composicao.php',
    'delete_item_composicao' => '../app/actions/composicoes/action_delete_item_composicao.php',

    // Fornecedores
    'create_fornecedor' => '../app/actions/fornecedores/action_create_fornecedor.php',
    'update_fornecedor' => '../app/actions/fornecedores/action_update_fornecedor.php',
    'delete_fornecedor' => '../app/actions/fornecedores/action_delete_fornecedor.php',

    // Compras
    'create_compra' => '../app/actions/compras/action_create_compra.php',
    'get_produtos_variacoes' => '../app/actions/compras/action_get_produtos_variacoes.php',
    'get_composicoes_compra' => '../app/actions/compras/action_get_composicoes_compra.php',
    'get_variacoes_produto' => '../app/actions/compras/action_get_variacoes_produto.php',
    'get_fornecedores'  => '../app/actions/compras/action_get_fornecedores.php',
    
    // Estoque
    'historico_movimentacoes' => '../app/actions/estoque/action_historico_movimentacoes.php',
    'exportar_estoque'        => '../app/actions/estoque/action_exportar_estoque.php',
    
    // Pedidos
    'detalhes_pedido'       => '../app/actions/pedidos/action_detalhes_pedido.php',
    'alterar_status_pedido' => '../app/actions/pedidos/action_alterar_status_pedido.php',
    'exportar_pedido'       => '../app/actions/pedidos/action_exportar_pedidos.php',
    'polling_pedidos'       => '../app/actions/pedidos/action_polling_pedidos.php',
    'webhook_abacatepay'    => '../app/actions/pedidos/action_webhook_abacatepay.php',
    
    // Movimentações
    'create_movimentacao'  => '../app/actions/movimentacoes/action_create_movimentacao.php',
    
    //Checkout
    'finalizar_compra'  => '../app/actions/checkout/action_finaliza_compra.php',
];

// Verifica se a action existe
if (!isset($allowedActions[$action])) {
    echo json_encode(['success' => false, 'message' => 'Action inválida']);
    exit;
}

$actionFile = __DIR__ . '/' . $allowedActions[$action];

// Verifica se o arquivo existe
if (!file_exists($actionFile)) {
    echo json_encode(['success' => false, 'message' => 'Action não implementada: ' . $actionFile]);
    exit;
}

// Executa a action
require_once $actionFile;