<?php
/**
 * Buscar produtos com suas variações para composições
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

/*
if (!isset($_SESSION['access_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}
*/

try {
    $db = getPDO();
    
    // Buscar produtos ativos
    $stmt = $db->query("
        SELECT id, nome
        FROM produtos
        WHERE ativo = 1
        ORDER BY nome
    ");
    
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'produtos' => $produtos
    ]);
    
} catch (Throwable $e) {
    error_log("Erro ao buscar produtos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar produtos']);
}