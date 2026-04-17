<?php
// ==========================
// EXEMPLO DE USO - database.php
// ==========================

require_once __DIR__ . '/../config/database.php';

// ==========================
// 1. CONECTAR NO BANCO
// ==========================
try {
    $pdo = getPDO();
    echo "✅ Conectado ao MySQL!\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    die();
}


// ==========================
// 2. FAZER UMA QUERY
// ==========================
try {
    $stmt = $pdo->query("SELECT * FROM produtos LIMIT 5");
    $produtos = $stmt->fetchAll();
    
    echo "\n📦 Produtos encontrados:\n";
    foreach ($produtos as $produto) {
        echo "- ID: {$produto['id']} | Nome: {$produto['nome']}\n";
    }
} catch (Exception $e) {
    echo "❌ Erro na query: " . $e->getMessage() . "\n";
}


// ==========================
// 3. FAZER UPLOAD DE IMAGEM
// ==========================
// Simulando um upload de arquivo
if (isset($_FILES['imagem'])) {
    try {
        $tmpFile = $_FILES['imagem']['tmp_name'];
        $fileName = uniqid() . '_' . $_FILES['imagem']['name'];
        
        $url = uploadToLocal($tmpFile, $fileName);
        
        echo "\n✅ Upload realizado!\n";
        echo "URL da imagem: $url\n";
        
        // Salvar no banco
        $stmt = $pdo->prepare("INSERT INTO produtos_imagens (produto_id, url) VALUES (?, ?)");
        $stmt->execute([1, $url]); // produto_id = 1 como exemplo
        
    } catch (Exception $e) {
        echo "❌ Erro no upload: " . $e->getMessage() . "\n";
    }
}


// ==========================
// 4. DELETAR IMAGEM
// ==========================
$urlParaDeletar = '/uploads/produtos/exemplo.jpg';

if (deleteLocalFile($urlParaDeletar)) {
    echo "\n✅ Imagem deletada!\n";
} else {
    echo "\n⚠️ Imagem não encontrada\n";
}