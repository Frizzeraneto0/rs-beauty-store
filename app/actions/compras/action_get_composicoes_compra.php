<?php
ini_set('display_errors', 0);
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

try {
    // Tenta buscar com filtros de tipo e ativo
    try {
        $composicoes = $db->query("
            SELECT id, nome, descricao, preco_compra
            FROM composicoes
            WHERE tipo = 'compra' AND ativo = 1
            ORDER BY nome
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e1) {
        // Se falhar (coluna não existe), busca sem filtro
        $composicoes = $db->query("
            SELECT id, nome, descricao, preco_compra
            FROM composicoes
            ORDER BY nome
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // Se não retornou nada com filtros, tenta sem
    if (empty($composicoes)) {
        $composicoes = $db->query("
            SELECT id, nome, descricao, preco_compra
            FROM composicoes
            ORDER BY nome
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'composicoes' => $composicoes]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar composições',
        'error'   => $e->getMessage()
    ]);
}