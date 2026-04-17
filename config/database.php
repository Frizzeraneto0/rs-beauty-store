<?php
// ==========================
// CREDENCIAIS DO BANCO MYSQL
// ==========================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'u260427495_bd_system');
define('DB_USER', 'u260427495_admin_system');
define('DB_PASSWORD', 'ZfwP43Fo6T^d');
define('DB_CHARSET', 'utf8mb4');


// ==========================
// CONEXÃO COM MYSQL
// ==========================
function getPDO(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        } catch (PDOException $e) {
            error_log("Erro de conexão MySQL: " . $e->getMessage());
            throw new PDOException("Não foi possível conectar ao banco de dados.");
        }
    }
    
    return $pdo;
}


// ==========================
// CONFIGURAÇÕES DE UPLOAD LOCAL
// ==========================
// CAMINHO FÍSICO (SERVIDOR)
define('UPLOAD_DIR', dirname(__DIR__) . '/public_html/uploads/produtos');

// URL PÚBLICA (NAVEGADOR)
define('UPLOAD_URL', '/uploads/produtos');


// ==========================
// FUNÇÃO DE UPLOAD LOCAL
// ==========================
function uploadToLocal(string $tmpFile, string $fileName): string
{
    // Cria o diretório se não existir
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    $targetPath = UPLOAD_DIR . '/' . $fileName;
    
    // Move o arquivo
    if (!move_uploaded_file($tmpFile, $targetPath)) {
        if (!copy($tmpFile, $targetPath)) {
            throw new Exception("Erro ao salvar arquivo: $fileName");
        }
    }
    
    chmod($targetPath, 0644);
    
    // Retorna a URL
    return UPLOAD_URL . '/' . $fileName;
}


// ==========================
// FUNÇÃO DE EXCLUSÃO
// ==========================
function deleteLocalFile(string $fileUrl): bool
{
    $fileName = basename($fileUrl);
    $filePath = UPLOAD_DIR . '/' . $fileName;
    
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    
    return false;
}