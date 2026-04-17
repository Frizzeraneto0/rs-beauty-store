<?php
/**
 * Retorna todos os tipos de variação com seus valores
 */

/*
if (!isset($_SESSION['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}
*/

try {
    $db = getPDO();
    
    // Buscar todos os tipos de variação
    $stmt = $db->query("
        SELECT id, nome, slug
        FROM tipos_variacao
        ORDER BY nome
    ");
    
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada tipo, buscar seus valores
    foreach ($tipos as &$tipo) {
        $stmtValores = $db->prepare("
            SELECT id, valor, slug
            FROM valores_variacao
            WHERE id_tipo_variacao = :tipo_id
            ORDER BY valor
        ");
        
        $stmtValores->execute(['tipo_id' => $tipo['id']]);
        $tipo['valores'] = $stmtValores->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'tipos' => $tipos
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar tipos/valores: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar tipos de variação']);
}