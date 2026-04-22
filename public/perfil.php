<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'perfil.php';
    header('Location: login.php');
    exit;
}

$db = getPDO();
$erro = '';
$sucesso = '';

$stmt = $db->prepare("SELECT id, nome, email, telefone FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');

    if ($nome === '' || $email === '') {
        $erro = 'Nome e e-mail são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Digite um e-mail válido.';
    } else {
        $check = $db->prepare("SELECT id FROM usuarios WHERE email = :email AND id <> :id");
        $check->execute([':email' => $email, ':id' => $usuario['id']]);
        if ($check->fetch()) {
            $erro = 'Este e-mail já está em uso por outra conta.';
        } else {
            $upd = $db->prepare("UPDATE usuarios SET nome = :n, email = :e, telefone = :t WHERE id = :id");
            $upd->execute([
                ':n'  => $nome,
                ':e'  => $email,
                ':t'  => $telefone ?: null,
                ':id' => $usuario['id']
            ]);
            $_SESSION['user_nome']  = $nome;
            $_SESSION['user_email'] = $email;
            $usuario['nome']  = $nome;
            $usuario['email'] = $email;
            $usuario['telefone'] = $telefone;
            $sucesso = 'Perfil atualizado com sucesso.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil — RS Beauty Store</title>
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
        }
        .alert {
            padding: 0.9rem 1.1rem; border-radius: 10px; font-size: 0.88rem;
            margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;
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
            color: var(--rs-white); text-decoration: none; border: none;
            font-size: 0.78rem; font-weight: 600; letter-spacing: 1.5px;
            text-transform: uppercase; border-radius: 50px; cursor: pointer;
            transition: all 0.3s; box-shadow: 0 8px 24px rgba(198,123,136,0.3);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(155,126,189,0.4); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } .card { padding: 1.8rem 1.3rem; } }
    </style>
</head>
<body>

<div class="page-wrap">
    <div class="page-head">
        <div class="page-eyebrow">Minha conta</div>
        <h1 class="page-title">Meu <em>Perfil</em></h1>
    </div>

    <div class="card">
        <?php if ($erro): ?>
            <div class="alert alert-err"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="alert alert-ok"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Nome completo</label>
                <input type="text" name="nome" class="form-input"
                       value="<?= htmlspecialchars($usuario['nome']) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-input"
                           value="<?= htmlspecialchars($usuario['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Telefone</label>
                    <input type="tel" name="telefone" class="form-input"
                           value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>"
                           placeholder="(00) 00000-0000">
                </div>
            </div>

            <button type="submit" class="btn">Salvar alterações</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
