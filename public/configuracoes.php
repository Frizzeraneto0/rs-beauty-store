<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'configuracoes.php';
    header('Location: login.php');
    exit;
}

$db = getPDO();
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atual    = $_POST['senha_atual'] ?? '';
    $nova     = $_POST['senha_nova']  ?? '';
    $confirma = $_POST['senha_conf']  ?? '';

    $stmt = $db->prepare("SELECT senha FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($atual, $row['senha'])) {
        $erro = 'Senha atual incorreta.';
    } elseif (strlen($nova) < 6) {
        $erro = 'A nova senha precisa ter no mínimo 6 caracteres.';
    } elseif ($nova !== $confirma) {
        $erro = 'As senhas não coincidem.';
    } else {
        $hash = password_hash($nova, PASSWORD_DEFAULT);
        $upd = $db->prepare("UPDATE usuarios SET senha = :s WHERE id = :id");
        $upd->execute([':s' => $hash, ':id' => $_SESSION['user_id']]);
        $sucesso = 'Senha alterada com sucesso.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações — RS Beauty Store</title>
    <?php include __DIR__ . '/partials/navbar.php'; ?>
    <style>
        body {
            background: linear-gradient(135deg, var(--rs-white) 0%, var(--rs-soft-pink) 50%, var(--rs-white) 100%);
            color: var(--rs-black);
            min-height: 100vh;
        }
        .page-wrap { max-width: 800px; margin: 2rem auto 5rem; padding: 0 1.5rem; }
        .page-head { margin-bottom: 2rem; }
        .page-eyebrow {
            font-size: 0.72rem; letter-spacing: 3px; text-transform: uppercase;
            color: var(--rs-deep-rose); margin-bottom: 0.7rem; font-weight: 600;
        }
        .page-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.8rem; font-weight: 300; line-height: 1.1;
        }
        .page-title em {
            font-style: italic;
            background: linear-gradient(135deg, var(--rs-deep-rose), var(--rs-luxury-purple));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .card {
            background: var(--rs-white);
            border: 1px solid rgba(232, 180, 184, 0.25);
            border-radius: 18px;
            padding: 2.5rem;
            box-shadow: 0 10px 40px rgba(198, 123, 136, 0.08);
            margin-bottom: 1.5rem;
        }
        .card h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.6rem; font-weight: 600; margin-bottom: 0.4rem;
        }
        .card p.card-sub { color: var(--rs-gray-dark); font-size: 0.88rem; margin-bottom: 1.5rem; }
        .alert {
            padding: 0.9rem 1.1rem; border-radius: 10px; font-size: 0.88rem;
            margin-bottom: 1.5rem;
        }
        .alert-ok  { background: rgba(39, 174, 96, 0.08); color: #1e8449; border-left: 3px solid #27ae60; }
        .alert-err { background: rgba(231, 76, 60, 0.08); color: #c0392b; border-left: 3px solid #e74c3c; }
        .form-group { margin-bottom: 1.3rem; }
        .form-label {
            display: block; font-size: 0.72rem; font-weight: 600;
            letter-spacing: 0.8px; text-transform: uppercase;
            color: var(--rs-gray-dark); margin-bottom: 0.5rem;
        }
        .form-input {
            width: 100%; padding: 0.9rem 1rem;
            background: var(--rs-white);
            border: 1px solid var(--rs-gray-mid);
            border-radius: 10px; font-size: 0.95rem; font-family: inherit;
            color: var(--rs-black); transition: all 0.3s; outline: none;
        }
        .form-input:focus {
            border-color: var(--rs-deep-rose);
            box-shadow: 0 0 0 3px rgba(232, 180, 184, 0.2);
        }
        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.9rem 1.8rem;
            background: linear-gradient(135deg, var(--rs-deep-rose), var(--rs-luxury-purple));
            color: var(--rs-white); border: none;
            font-size: 0.78rem; font-weight: 600; letter-spacing: 1.5px;
            text-transform: uppercase; border-radius: 50px; cursor: pointer;
            transition: all 0.3s; box-shadow: 0 8px 24px rgba(198,123,136,0.3);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(155,126,189,0.4); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .danger-zone {
            background: rgba(231, 76, 60, 0.04);
            border: 1px solid rgba(231, 76, 60, 0.25);
        }
        .danger-zone h2 { color: #c0392b; }
        .btn-danger {
            background: transparent;
            color: #c0392b;
            border: 1px solid #c0392b;
            box-shadow: none;
            text-decoration: none;
        }
        .btn-danger:hover {
            background: #c0392b; color: var(--rs-white);
            transform: translateY(-2px); box-shadow: 0 8px 24px rgba(231,76,60,0.25);
        }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } .card { padding: 1.8rem 1.3rem; } }
    </style>
</head>
<body>

<div class="page-wrap">
    <div class="page-head">
        <div class="page-eyebrow">Minha conta</div>
        <h1 class="page-title"><em>Configurações</em></h1>
    </div>

    <div class="card">
        <h2>Alterar senha</h2>
        <p class="card-sub">Escolha uma senha forte para proteger sua conta.</p>

        <?php if ($erro): ?><div class="alert alert-err"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
        <?php if ($sucesso): ?><div class="alert alert-ok"><?= htmlspecialchars($sucesso) ?></div><?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Senha atual</label>
                <input type="password" name="senha_atual" class="form-input" required autocomplete="current-password">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nova senha</label>
                    <input type="password" name="senha_nova" class="form-input" required minlength="6" autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label class="form-label">Confirmar nova senha</label>
                    <input type="password" name="senha_conf" class="form-input" required minlength="6" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="btn">Salvar nova senha</button>
        </form>
    </div>

    <div class="card danger-zone">
        <h2>Sair da conta</h2>
        <p class="card-sub">Encerre sua sessão neste dispositivo.</p>
        <a href="logout.php" class="btn btn-danger">Sair da conta</a>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
