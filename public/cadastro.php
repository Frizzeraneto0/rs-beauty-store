<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: produtos.php');
    exit;
}

$db = getPDO();
$erro   = '';
$sucesso = '';
$dados  = ['nome' => '', 'email' => '', 'telefone' => ''];

// =============================================
// PROCESSAR CADASTRO
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $senha    = $_POST['senha']    ?? '';
    $confirma = $_POST['confirma'] ?? '';

    $dados = ['nome' => htmlspecialchars($nome), 'email' => htmlspecialchars($email), 'telefone' => htmlspecialchars($telefone)];

    // Validações
    if (empty($nome) || empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Digite um e-mail válido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirma) {
        $erro = 'As senhas não coincidem.';
    } else {
        // Verificar e-mail duplicado
        $check = $db->prepare("SELECT id FROM usuarios WHERE email = :email");
        $check->execute([':email' => $email]);

        if ($check->fetch()) {
            $erro = 'Este e-mail já está cadastrado. <a href="login.php" style="color:var(--rose-gold)">Entrar na conta</a>';
        } else {
            // Criar usuário
            $id    = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            $hash  = password_hash($senha, PASSWORD_DEFAULT);

            $stmt  = $db->prepare("
                INSERT INTO usuarios (id, nome, email, telefone, senha, tipo_usuario_id, criado_em)
                VALUES (:id, :nome, :email, :telefone, :senha, 1, NOW())
            ");
            $stmt->execute([
                ':id'       => $id,
                ':nome'     => $nome,
                ':email'    => $email,
                ':telefone' => $telefone ?: null,
                ':senha'    => $hash
            ]);

            // Login automático
            $_SESSION['user_id']      = $id;
            $_SESSION['user_nome']    = $nome;
            $_SESSION['user_email']   = $email;
            $_SESSION['tipo_usuario'] = 1;

            $redirect = $_GET['redirect'] ?? $_SESSION['redirect_after_login'] ?? 'produtos.php';
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
    <title>Criar Conta — RS BEAUTY STORE</title>
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
        .logo-wrap { text-align: center; margin-bottom: 2rem; }

        .logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem; font-weight: 600; letter-spacing: 3px;
            background: linear-gradient(135deg, var(--white) 0%, var(--rose-gold) 50%, var(--luxury-purple) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; text-decoration: none; display: inline-block;
        }

        .logo-tagline {
            font-size: 0.75rem; letter-spacing: 2px; text-transform: uppercase;
            color: rgba(255,255,255,0.4); margin-top: 0.3rem;
        }

        /* ===== CARD ===== */
        .card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 2.5rem;
            width: 100%; max-width: 480px;
            backdrop-filter: blur(20px);
            box-shadow: 0 30px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(232,180,184,0.1);
        }

        .card-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem; font-weight: 400;
            color: var(--white); margin-bottom: 0.4rem; letter-spacing: 1px;
        }

        .card-subtitle {
            font-size: 0.85rem; color: rgba(255,255,255,0.45); margin-bottom: 2rem;
        }

        /* ===== PERKS ===== */
        .perks {
            display: flex; gap: 0.8rem; margin-bottom: 2rem; flex-wrap: wrap;
        }

        .perk {
            display: flex; align-items: center; gap: 0.4rem;
            font-size: 0.75rem; color: rgba(255,255,255,0.35);
        }

        .perk svg { color: var(--rose-gold); opacity: 0.7; }

        /* ===== ALERTA ===== */
        .alert-erro {
            background: rgba(198,50,50,0.15);
            border: 1px solid rgba(198,50,50,0.4);
            border-left: 3px solid #e74c3c;
            color: #ff8a80; font-size: 0.85rem;
            padding: 0.9rem 1rem; border-radius: 8px; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 0.6rem;
        }

        /* ===== FORM ===== */
        .form-group { margin-bottom: 1.1rem; position: relative; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .form-label {
            display: block; font-size: 0.75rem; font-weight: 600;
            letter-spacing: 0.8px; text-transform: uppercase;
            color: rgba(255,255,255,0.5); margin-bottom: 0.45rem;
        }

        .form-label .optional {
            font-weight: 400; text-transform: none; letter-spacing: 0;
            color: rgba(255,255,255,0.25); margin-left: 0.3rem;
        }

        .input-wrap { position: relative; }

        .input-icon {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.25); pointer-events: none;
        }

        .form-input {
            width: 100%; padding: 0.9rem 1rem 0.9rem 2.8rem;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px; font-size: 0.95rem; font-family: inherit;
            color: var(--white); transition: all 0.3s; outline: none;
        }

        .form-input.no-icon { padding-left: 1rem; }
        .form-input::placeholder { color: rgba(255,255,255,0.2); }

        .form-input:focus {
            border-color: var(--rose-gold);
            background: rgba(232,180,184,0.06);
            box-shadow: 0 0 0 3px rgba(232,180,184,0.12);
        }

        .form-input.error { border-color: #e74c3c; }
        .form-input.ok { border-color: #4caf50; }

        .toggle-senha {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: rgba(255,255,255,0.3); cursor: pointer; padding: 0;
            transition: color 0.2s;
        }

        .toggle-senha:hover { color: var(--rose-gold); }

        /* ===== FORÇA DA SENHA ===== */
        .senha-forca {
            display: flex; gap: 4px; margin-top: 6px;
        }

        .forca-bar {
            flex: 1; height: 3px; border-radius: 2px;
            background: rgba(255,255,255,0.1); transition: background 0.3s;
        }

        .forca-bar.fraca { background: #e74c3c; }
        .forca-bar.media { background: #f39c12; }
        .forca-bar.forte { background: #27ae60; }

        .senha-dica { font-size: 0.72rem; color: rgba(255,255,255,0.3); margin-top: 4px; }

        /* ===== TERMOS ===== */
        .termos-label {
            display: flex; align-items: flex-start; gap: 0.6rem;
            font-size: 0.8rem; color: rgba(255,255,255,0.4);
            cursor: pointer; margin-bottom: 1.5rem; margin-top: 0.5rem;
            line-height: 1.4;
        }

        .termos-label input { accent-color: var(--deep-rose); margin-top: 2px; flex-shrink: 0; }

        .termos-label a { color: var(--rose-gold); text-decoration: none; }
        .termos-label a:hover { text-decoration: underline; }

        /* ===== BTN ===== */
        .btn-criar {
            width: 100%; padding: 1rem;
            background: linear-gradient(135deg, var(--deep-rose) 0%, var(--luxury-purple) 100%);
            color: var(--white); border: none; border-radius: 10px;
            font-size: 0.95rem; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; cursor: pointer; transition: all 0.3s;
            box-shadow: 0 8px 24px rgba(198,123,136,0.35);
        }

        .btn-criar:hover {
            transform: translateY(-2px); box-shadow: 0 12px 30px rgba(155,126,189,0.45);
        }

        .btn-criar:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* ===== DIVISOR ===== */
        .divisor {
            display: flex; align-items: center; gap: 1rem;
            margin: 1.5rem 0; color: rgba(255,255,255,0.2); font-size: 0.78rem;
        }

        .divisor::before, .divisor::after {
            content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.1);
        }

        /* ===== JÁ TENHO CONTA ===== */
        .link-login {
            width: 100%; padding: 0.95rem;
            background: transparent; color: var(--white);
            border: 1px solid rgba(255,255,255,0.15); border-radius: 10px;
            font-size: 0.9rem; font-weight: 600;
            cursor: pointer; transition: all 0.3s;
            text-decoration: none; display: block; text-align: center;
        }

        .link-login:hover {
            border-color: var(--rose-gold); color: var(--rose-gold);
            background: rgba(232,180,184,0.05);
        }

        /* ===== VOLTAR ===== */
        .link-voltar {
            display: inline-flex; align-items: center; gap: 0.4rem;
            margin-top: 2rem; font-size: 0.82rem;
            color: rgba(255,255,255,0.3); text-decoration: none; transition: color 0.2s;
        }

        .link-voltar:hover { color: rgba(255,255,255,0.6); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            .card { padding: 2rem 1.5rem; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="logo-wrap">
        <a href="produtos.php" class="logo">RS BEAUTY STORE</a>
        <div class="logo-tagline">Beleza & Elegância</div>
    </div>

    <div class="card">
        <h1 class="card-title">Criar sua conta</h1>
        <p class="card-subtitle">Rápido, grátis e seguro</p>

        <div class="perks">
            <div class="perk">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Rastreio de pedidos
            </div>
            <div class="perk">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Ofertas exclusivas
            </div>
            <div class="perk">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Checkout mais rápido
            </div>
        </div>

        <?php if ($erro): ?>
            <div class="alert-erro">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?= $erro ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="formCadastro">

            <div class="form-group">
                <label class="form-label">Nome completo</label>
                <div class="input-wrap">
                    <svg class="input-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <input type="text" name="nome" class="form-input"
                           placeholder="Seu nome completo"
                           value="<?= $dados['nome'] ?>"
                           required autofocus autocomplete="name">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <div class="input-wrap">
                        <svg class="input-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <input type="email" name="email" id="emailInput" class="form-input"
                               placeholder="seu@email.com"
                               value="<?= $dados['email'] ?>"
                               required autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Telefone <span class="optional">(opcional)</span></label>
                    <div class="input-wrap">
                        <svg class="input-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.5 19.79 19.79 0 0 1 1.61 4.9 2 2 0 0 1 3.58 2.72h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 10.09a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7a2 2 0 0 1 1.72 2.01z"/>
                        </svg>
                        <input type="tel" name="telefone" id="telefoneInput" class="form-input"
                               placeholder="(00) 00000-0000"
                               value="<?= $dados['telefone'] ?>"
                               autocomplete="tel">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Senha</label>
                    <div class="input-wrap">
                        <svg class="input-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" name="senha" id="senhaInput" class="form-input"
                               placeholder="Mínimo 6 caracteres"
                               required autocomplete="new-password"
                               oninput="verificarForca(this.value)">
                        <button type="button" class="toggle-senha" onclick="toggleSenha('senhaInput', 'eye1')">
                            <svg id="eye1" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <div class="senha-forca">
                        <div class="forca-bar" id="bar1"></div>
                        <div class="forca-bar" id="bar2"></div>
                        <div class="forca-bar" id="bar3"></div>
                        <div class="forca-bar" id="bar4"></div>
                    </div>
                    <div class="senha-dica" id="senhaDica">Use letras, números e símbolos</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirmar senha</label>
                    <div class="input-wrap">
                        <svg class="input-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" name="confirma" id="confirmaInput" class="form-input"
                               placeholder="Repita a senha"
                               required autocomplete="new-password"
                               oninput="verificarConfirma()">
                        <button type="button" class="toggle-senha" onclick="toggleSenha('confirmaInput', 'eye2')">
                            <svg id="eye2" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <label class="termos-label">
                <input type="checkbox" id="termosCheck" required>
                Li e concordo com os <a href="#">Termos de Uso</a> e a <a href="#">Política de Privacidade</a>
            </label>

            <button type="submit" class="btn-criar" id="btnCriar">
                Criar minha conta
            </button>
        </form>

        <div class="divisor">já tenho conta</div>

        <a href="login.php" class="link-login">Entrar na minha conta</a>
    </div>

    <a href="produtos.php" class="link-voltar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Voltar para a loja
    </a>

    <script>
    // ===== TOGGLE SENHA =====
    function toggleSenha(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
        } else {
            input.type = 'password';
            icon.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
        }
    }

    // ===== FORÇA DA SENHA =====
    function verificarForca(senha) {
        const bars = [document.getElementById('bar1'), document.getElementById('bar2'),
                      document.getElementById('bar3'), document.getElementById('bar4')];
        const dica = document.getElementById('senhaDica');

        bars.forEach(b => { b.className = 'forca-bar'; });

        if (!senha) { dica.textContent = 'Use letras, números e símbolos'; return; }

        let score = 0;
        if (senha.length >= 6)  score++;
        if (senha.length >= 10) score++;
        if (/[A-Z]/.test(senha) && /[0-9]/.test(senha)) score++;
        if (/[^A-Za-z0-9]/.test(senha)) score++;

        const classes  = ['fraca', 'media', 'media', 'forte'];
        const textos   = ['Muito fraca', 'Fraca', 'Boa', 'Forte'];
        const cls      = score <= 1 ? 'fraca' : score <= 2 ? 'media' : 'forte';

        for (let i = 0; i < score; i++) bars[i].classList.add(cls);
        dica.textContent = textos[score - 1] || 'Muito fraca';
        dica.style.color = cls === 'fraca' ? '#e74c3c' : cls === 'media' ? '#f39c12' : '#27ae60';
    }

    // ===== CONFIRMAR SENHA =====
    function verificarConfirma() {
        const s1 = document.getElementById('senhaInput').value;
        const s2 = document.getElementById('confirmaInput');
        if (s2.value && s1 !== s2.value) {
            s2.classList.add('error'); s2.classList.remove('ok');
        } else if (s2.value) {
            s2.classList.add('ok'); s2.classList.remove('error');
        }
    }

    // ===== MÁSCARA TELEFONE =====
    document.getElementById('telefoneInput').addEventListener('input', function() {
        let v = this.value.replace(/\D/g, '');
        if (v.length <= 10) {
            v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        } else {
            v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
        }
        this.value = v;
    });
    </script>

</body>
</html>