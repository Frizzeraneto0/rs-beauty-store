<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/database.php';

// Se já está logado, redirecionar
if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['redirect_after_login'] ?? 'produtos.php';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
    exit;
}

$db = getPDO();
$erro = '';
$email_preenchido = '';

// =============================================
// PROCESSAR LOGIN
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $email_preenchido = htmlspecialchars($email);

    if (empty($email) || empty($senha)) {
        $erro = 'Preencha e-mail e senha.';
    } else {
        $stmt = $db->prepare("
            SELECT id, nome, email, senha, tipo_usuario_id
            FROM usuarios
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($senha, $user['senha'])) {
            $erro = 'E-mail ou senha incorretos.';
        } else {
            $_SESSION['user_id']        = $user['id'];
            $_SESSION['user_nome']      = $user['nome'];
            $_SESSION['user_email']     = $user['email'];
            $_SESSION['tipo_usuario']   = $user['tipo_usuario_id'];

            $redirect = $_SESSION['redirect_after_login'] ?? 'produtos.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — RS BEAUTY STORE</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --black: #0a0a0a;
            --white: #fefefe;
            --gray-light: #f5f5f5;
            --gray-mid: #e0e0e0;
            --gray-dark: #666;
            --rose-gold: #E8B4B8;
            --deep-rose: #C67B88;
            --soft-pink: #FFF5F7;
            --luxury-purple: #9B7EBD;
            --accent: #d4af37;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0a10 40%, #1a0a1f 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        /* ===== LOGO ===== */
        .logo-wrap {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem;
            font-weight: 600;
            letter-spacing: 3px;
            background: linear-gradient(135deg, var(--white) 0%, var(--rose-gold) 50%, var(--luxury-purple) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            display: inline-block;
        }

        .logo-tagline {
            font-size: 0.75rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
            margin-top: 0.3rem;
        }

        /* ===== CARD ===== */
        .card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 2.5rem 2.5rem;
            width: 100%;
            max-width: 440px;
            backdrop-filter: blur(20px);
            box-shadow: 0 30px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(232,180,184,0.1);
        }

        .card-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 400;
            color: var(--white);
            margin-bottom: 0.4rem;
            letter-spacing: 1px;
        }

        .card-subtitle {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.45);
            margin-bottom: 2rem;
        }

        /* ===== ALERTA DE ERRO ===== */
        .alert-erro {
            background: rgba(198,50,50,0.15);
            border: 1px solid rgba(198,50,50,0.4);
            border-left: 3px solid #e74c3c;
            color: #ff8a80;
            font-size: 0.85rem;
            padding: 0.9rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        /* ===== FORM ===== */
        .form-group { margin-bottom: 1.2rem; position: relative; }

        .form-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.5);
            margin-bottom: 0.5rem;
        }

        .input-wrap { position: relative; }

        .input-icon {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.25);
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.8rem;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--white);
            transition: all 0.3s;
            outline: none;
        }

        .form-input::placeholder { color: rgba(255,255,255,0.2); }

        .form-input:focus {
            border-color: var(--rose-gold);
            background: rgba(232,180,184,0.06);
            box-shadow: 0 0 0 3px rgba(232,180,184,0.12);
        }

        .toggle-senha {
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: rgba(255,255,255,0.3);
            cursor: pointer; padding: 0;
            transition: color 0.2s;
        }

        .toggle-senha:hover { color: var(--rose-gold); }

        /* ===== LEMBRAR / ESQUECI ===== */
        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.8rem;
            margin-top: -0.2rem;
        }

        .checkbox-label {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.82rem; color: rgba(255,255,255,0.45); cursor: pointer;
        }

        .checkbox-label input { accent-color: var(--deep-rose); }

        .link-esqueci {
            font-size: 0.82rem;
            color: var(--rose-gold);
            text-decoration: none;
            transition: color 0.2s;
        }

        .link-esqueci:hover { color: var(--white); }

        /* ===== BTN ENTRAR ===== */
        .btn-entrar {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--deep-rose) 0%, var(--luxury-purple) 100%);
            color: var(--white);
            border: none; border-radius: 10px;
            font-size: 0.95rem; font-weight: 700;
            letter-spacing: 1px; text-transform: uppercase;
            cursor: pointer; transition: all 0.3s;
            box-shadow: 0 8px 24px rgba(198,123,136,0.35);
            position: relative; overflow: hidden;
        }

        .btn-entrar:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(155,126,189,0.45);
        }

        .btn-entrar:active { transform: translateY(0); }

        /* ===== DIVISOR ===== */
        .divisor {
            display: flex; align-items: center; gap: 1rem;
            margin: 1.5rem 0; color: rgba(255,255,255,0.2); font-size: 0.78rem;
        }

        .divisor::before, .divisor::after {
            content: ''; flex: 1;
            height: 1px; background: rgba(255,255,255,0.1);
        }

        /* ===== BTN CADASTRAR ===== */
        .btn-cadastrar {
            width: 100%;
            padding: 0.95rem;
            background: transparent;
            color: var(--white);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px;
            font-size: 0.9rem; font-weight: 600;
            cursor: pointer; transition: all 0.3s;
            text-decoration: none;
            display: block; text-align: center;
        }

        .btn-cadastrar:hover {
            border-color: var(--rose-gold);
            color: var(--rose-gold);
            background: rgba(232,180,184,0.05);
        }

        /* ===== VOLTAR ===== */
        .link-voltar {
            display: inline-flex; align-items: center; gap: 0.4rem;
            margin-top: 2rem;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.3);
            text-decoration: none;
            transition: color 0.2s;
        }

        .link-voltar:hover { color: rgba(255,255,255,0.6); }

        /* ===== BENEFÍCIOS ===== */
        .beneficios {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            gap: 2rem;
        }

        .beneficio-item {
            display: flex; flex-direction: column; align-items: center; gap: 0.3rem;
            font-size: 0.72rem; color: rgba(255,255,255,0.25); text-align: center;
        }

        .beneficio-item svg { color: rgba(232,180,184,0.4); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            .card { padding: 2rem 1.5rem; }
            .beneficios { gap: 1.2rem; }
        }
    </style>
</head>
<body>

    <div class="logo-wrap">
        <a href="produtos.php" class="logo">RS BEAUTY STORE</a>
        <div class="logo-tagline">Beleza & Elegância</div>
    </div>

    <div class="card">
        <h1 class="card-title">Bem-vinda de volta</h1>
        <p class="card-subtitle">Entre com sua conta para continuar</p>

        <?php if ($erro): ?>
            <div class="alert-erro">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?= $erro ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">E-mail</label>
                <div class="input-wrap">
                    <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <input
                        type="email"
                        name="email"
                        class="form-input"
                        placeholder="seu@email.com"
                        value="<?= $email_preenchido ?>"
                        required autocomplete="email" autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Senha</label>
                <div class="input-wrap">
                    <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input
                        type="password"
                        name="senha"
                        id="senhaInput"
                        class="form-input"
                        placeholder="••••••••"
                        required autocomplete="current-password">
                    <button type="button" class="toggle-senha" onclick="toggleSenha()" id="toggleBtn">
                        <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-footer">
                <label class="checkbox-label">
                    <input type="checkbox" name="lembrar"> Lembrar de mim
                </label>
                <a href="esqueci_senha.php" class="link-esqueci">Esqueceu a senha?</a>
            </div>

            <button type="submit" class="btn-entrar">Entrar na minha conta</button>
        </form>

        <div class="divisor">ou</div>

        <a href="cadastro.php<?= isset($_SESSION['redirect_after_login']) ? '?redirect=' . urlencode($_SESSION['redirect_after_login']) : '' ?>" class="btn-cadastrar">
            Criar conta grátis
        </a>
    </div>

    <a href="produtos.php" class="link-voltar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Continuar sem entrar
    </a>

    <div class="beneficios">
        <div class="beneficio-item">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
            </svg>
            Rastreie seus pedidos
        </div>
        <div class="beneficio-item">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            Lista de desejos
        </div>
        <div class="beneficio-item">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            Compra segura
        </div>
        <div class="beneficio-item">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            Histórico de compras
        </div>
    </div>

    <script>
    function toggleSenha() {
        const input = document.getElementById('senhaInput');
        const icon  = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
        } else {
            input.type = 'password';
            icon.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
        }
    }
    </script>

</body>
</html>