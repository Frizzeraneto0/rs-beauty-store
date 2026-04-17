<?php
/**
 * Excluir tipo de variação
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
    
    // Verificar se existem valores vinculados
    $stmt = $db->prepare("SELECT COUNT(*) FROM valores_variacao WHERE id_tipo_variacao = :id");
    $stmt->execute([':id' => $id]);
    $qtdValores = $stmt->fetchColumn();
    
    // Verificar se existem variações de produtos usando este tipo
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM produto_variacao_valores 
        WHERE id_tipo_variacao = :id
    ");
    $stmt->execute([':id' => $id]);
    $qtdUsos = $stmt->fetchColumn();
    
    if ($qtdUsos > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "Não é possível excluir. Este tipo está sendo usado em {$qtdUsos} variação(ões) de produto."
        ]);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    try {
        // Deletar valores vinculados
        if ($qtdValores > 0) {
            $stmt = $db->prepare("DELETE FROM valores_variacao WHERE id_tipo_variacao = :id");
            $stmt->execute([':id' => $id]);
        }
        
        // Deletar tipo
        $stmt = $db->prepare("DELETE FROM tipos_variacao WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => $qtdValores > 0 
                ? "Tipo e {$qtdValores} valor(es) excluídos com sucesso" 
                : 'Tipo excluído com sucesso'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Throwable $e) {
    error_log("Erro ao excluir tipo de variação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir tipo', 'error' => $e->getMessage()]);
}