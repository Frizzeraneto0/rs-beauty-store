<?php
/**
 * Excluir valor de variação
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

// DADOS
$id = $_POST['id'] ?? null;

// VALIDAÇÃO
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

// DELETE
try {
    $db = getPDO();
    
    // Verificar se está sendo usado em variações de produtos
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM produto_variacao_valores 
        WHERE id_valor_variacao = :id
    ");
    $stmt->execute([':id' => $id]);
    $qtdUsos = $stmt->fetchColumn();
    
    if ($qtdUsos > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "Não é possível excluir. Este valor está sendo usado em {$qtdUsos} variação(ões) de produto."
        ]);
        exit;
    }
    
    // Deletar valor
    $stmt = $db->prepare("DELETE FROM valores_variacao WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Valor excluído com sucesso']);
    
} catch (Throwable $e) {
    error_log("Erro ao excluir valor de variação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir valor', 'error' => $e->getMessage()]);
}