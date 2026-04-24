<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'configuracoes.php';
    header('Location: login.php');
    exit;
}

$db = getPDO();
$erroSenha = '';
$okSenha   = '';
$erroEnd   = '';
$okEnd     = '';

// --------------------------------------------------------------
// Tipos de endereço (Residencial, Comercial)
// --------------------------------------------------------------
$tiposEndereco = $db->query("SELECT id, descricao FROM tipo_endereco ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

function carregarEnderecos(PDO $db, string $userId): array {
    $stmt = $db->prepare("
        SELECT e.*
        FROM enderecos e
        INNER JOIN (
            SELECT tipo_endereco_id, MAX(id) AS id
            FROM enderecos
            WHERE usuario_id = :uid
            GROUP BY tipo_endereco_id
        ) ult ON ult.id = e.id
        WHERE e.usuario_id = :uid
    ");
    $stmt->execute([':uid' => $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $byType = [];
    foreach ($rows as $r) {
        $byType[(int)$r['tipo_endereco_id']] = $r;
    }
    return $byType;
}

// --------------------------------------------------------------
// POST: ação de senha ou endereço
// --------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'alterar_senha') {
        $atual    = $_POST['senha_atual'] ?? '';
        $nova     = $_POST['senha_nova']  ?? '';
        $confirma = $_POST['senha_conf']  ?? '';

        $stmt = $db->prepare("SELECT senha FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($atual, $row['senha'])) {
            $erroSenha = 'Senha atual incorreta.';
        } elseif (strlen($nova) < 6) {
            $erroSenha = 'A nova senha precisa ter no mínimo 6 caracteres.';
        } elseif ($nova !== $confirma) {
            $erroSenha = 'As senhas não coincidem.';
        } else {
            $hash = password_hash($nova, PASSWORD_DEFAULT);
            $upd = $db->prepare("UPDATE usuarios SET senha = :s WHERE id = :id");
            $upd->execute([':s' => $hash, ':id' => $_SESSION['user_id']]);
            $okSenha = 'Senha alterada com sucesso.';
        }
    }

    if ($acao === 'salvar_endereco') {
        $tipoId      = (int)($_POST['tipo_endereco_id'] ?? 0);
        $cep         = trim($_POST['cep']         ?? '');
        $rua         = trim($_POST['rua']         ?? '');
        $numero      = trim($_POST['numero']      ?? '');
        $complemento = trim($_POST['complemento'] ?? '');
        $bairro      = trim($_POST['bairro']      ?? '');
        $cidade      = trim($_POST['cidade']      ?? '');
        $estado      = strtoupper(trim($_POST['estado'] ?? ''));

        $valido = array_column($tiposEndereco, 'id');
        if (!in_array($tipoId, array_map('intval', $valido), true)) {
            $erroEnd = 'Tipo de endereço inválido.';
        } elseif ($cep === '' || $rua === '' || $bairro === '' || $cidade === '' || $estado === '') {
            $erroEnd = 'Preencha CEP, rua, bairro, cidade e estado.';
        } else {
            // Dedup: se houver endereço existente deste tipo, atualiza; senão insere.
            $find = $db->prepare("
                SELECT id FROM enderecos
                WHERE usuario_id = :uid AND tipo_endereco_id = :tid
                ORDER BY id DESC LIMIT 1
            ");
            $find->execute([':uid' => $_SESSION['user_id'], ':tid' => $tipoId]);
            $existente = $find->fetchColumn();

            if ($existente) {
                $upd = $db->prepare("
                    UPDATE enderecos
                    SET cep = :cep, rua = :rua, numero = :numero, complemento = :comp,
                        bairro = :bairro, cidade = :cidade, estado = :estado
                    WHERE id = :id AND usuario_id = :uid
                ");
                $upd->execute([
                    ':cep' => $cep, ':rua' => $rua, ':numero' => $numero ?: null,
                    ':comp' => $complemento ?: null, ':bairro' => $bairro,
                    ':cidade' => $cidade, ':estado' => $estado,
                    ':id' => $existente, ':uid' => $_SESSION['user_id'],
                ]);
            } else {
                $ins = $db->prepare("
                    INSERT INTO enderecos
                        (cep, rua, numero, complemento, bairro, cidade, estado, tipo_endereco_id, usuario_id)
                    VALUES
                        (:cep, :rua, :numero, :comp, :bairro, :cidade, :estado, :tid, :uid)
                ");
                $ins->execute([
                    ':cep' => $cep, ':rua' => $rua, ':numero' => $numero ?: null,
                    ':comp' => $complemento ?: null, ':bairro' => $bairro,
                    ':cidade' => $cidade, ':estado' => $estado,
                    ':tid' => $tipoId, ':uid' => $_SESSION['user_id'],
                ]);
            }
            $okEnd = 'Endereço salvo com sucesso.';
        }
    }

    if ($acao === 'remover_endereco') {
        $tipoId = (int)($_POST['tipo_endereco_id'] ?? 0);
        $del = $db->prepare("
            DELETE FROM enderecos
            WHERE usuario_id = :uid AND tipo_endereco_id = :tid
        ");
        $del->execute([':uid' => $_SESSION['user_id'], ':tid' => $tipoId]);
        $okEnd = 'Endereço removido.';
    }
}

$enderecos = carregarEnderecos($db, $_SESSION['user_id']);

$iconesTipo = ['Residencial' => '🏠', 'Comercial' => '🏢'];
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
        .page-wrap { max-width: 960px; margin: 2rem auto 5rem; padding: 0 1.5rem; }
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
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(198, 123, 136, 0.08);
            margin-bottom: 1.3rem;
        }
        .card h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.55rem; font-weight: 600; margin-bottom: 0.35rem;
            display: flex; align-items: center; gap: 0.6rem;
        }
        .card p.card-sub { color: var(--rs-gray-dark); font-size: 0.85rem; margin-bottom: 1.2rem; }
        .alert {
            padding: 0.85rem 1rem; border-radius: 10px; font-size: 0.85rem;
            margin-bottom: 1.2rem;
        }
        .alert-ok  { background: rgba(39, 174, 96, 0.08); color: #1e8449; border-left: 3px solid #27ae60; }
        .alert-err { background: rgba(231, 76, 60, 0.08); color: #c0392b; border-left: 3px solid #e74c3c; }

        .form-group { margin-bottom: 1.1rem; }
        .form-label {
            display: block; font-size: 0.7rem; font-weight: 600;
            letter-spacing: 0.8px; text-transform: uppercase;
            color: var(--rs-gray-dark); margin-bottom: 0.4rem;
        }
        .form-input {
            width: 100%; padding: 0.8rem 0.95rem;
            background: var(--rs-white);
            border: 1px solid var(--rs-gray-mid);
            border-radius: 10px; font-size: 0.92rem; font-family: inherit;
            color: var(--rs-black); transition: all 0.3s; outline: none;
        }
        .form-input:focus {
            border-color: var(--rs-deep-rose);
            box-shadow: 0 0 0 3px rgba(232, 180, 184, 0.2);
        }
        .form-row { display: grid; gap: 1rem; }
        .form-row.cols-2 { grid-template-columns: 1fr 1fr; }
        .form-row.cols-3 { grid-template-columns: 2fr 1fr 1fr; }

        .btn {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.8rem 1.4rem;
            background: linear-gradient(135deg, var(--rs-deep-rose), var(--rs-luxury-purple));
            color: var(--rs-white); border: none;
            font-size: 0.75rem; font-weight: 600; letter-spacing: 1.3px;
            text-transform: uppercase; border-radius: 50px; cursor: pointer;
            transition: all 0.3s; box-shadow: 0 6px 20px rgba(198,123,136,0.25);
            text-decoration: none; font-family: 'Montserrat', sans-serif;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(155,126,189,0.35); }
        .btn-ghost {
            background: transparent; color: var(--rs-gray-dark); box-shadow: none;
            border: 1px solid var(--rs-gray-mid);
        }
        .btn-ghost:hover {
            background: rgba(231, 76, 60, 0.06); color: #c0392b; border-color: #c0392b;
        }

        /* Address cards */
        .enderecos-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
        .endereco-card {
            background: var(--rs-soft-pink);
            border: 1px solid rgba(232, 180, 184, 0.35);
            border-radius: 14px;
            padding: 1.4rem;
        }
        .endereco-card.vazio {
            background: repeating-linear-gradient(
                45deg,
                rgba(232, 180, 184, 0.05),
                rgba(232, 180, 184, 0.05) 10px,
                transparent 10px,
                transparent 20px
            );
            border: 1px dashed rgba(198, 123, 136, 0.35);
        }
        .endereco-card h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.78rem; letter-spacing: 2px; text-transform: uppercase;
            color: var(--rs-deep-rose); font-weight: 700;
            margin-bottom: 0.9rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .endereco-card .icon-box {
            width: 30px; height: 30px; border-radius: 8px;
            background: linear-gradient(135deg, var(--rs-deep-rose), var(--rs-luxury-purple));
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.95rem;
        }
        .endereco-card .form-input { background: var(--rs-white); font-size: 0.86rem; padding: 0.7rem 0.85rem; }
        .endereco-card .form-label { font-size: 0.66rem; }
        .endereco-actions { display: flex; gap: 0.6rem; margin-top: 0.9rem; flex-wrap: wrap; }
        .endereco-actions .btn { padding: 0.65rem 1.1rem; font-size: 0.7rem; }

        .danger-zone { background: rgba(231, 76, 60, 0.03); border-color: rgba(231, 76, 60, 0.25); }
        .danger-zone h2 { color: #c0392b; }
        .btn-danger-link {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.75rem 1.3rem;
            background: transparent; color: #c0392b;
            border: 1px solid #c0392b;
            font-size: 0.72rem; font-weight: 600; letter-spacing: 1.3px;
            text-transform: uppercase; border-radius: 50px;
            text-decoration: none; transition: all 0.3s;
        }
        .btn-danger-link:hover {
            background: #c0392b; color: var(--rs-white);
            transform: translateY(-2px); box-shadow: 0 8px 24px rgba(231,76,60,0.25);
        }

        @media (max-width: 780px) {
            .enderecos-grid { grid-template-columns: 1fr; }
            .form-row.cols-2, .form-row.cols-3 { grid-template-columns: 1fr; }
            .card { padding: 1.4rem 1.2rem; }
        }
    </style>
</head>
<body>

<div class="page-wrap">
    <div class="page-head">
        <div class="page-eyebrow">Minha conta</div>
        <h1 class="page-title"><em>Configurações</em></h1>
    </div>

    <!-- ===================== ENDEREÇOS ===================== -->
    <div class="card">
        <h2>📍 Meus endereços</h2>
        <p class="card-sub">Cadastre um endereço residencial e um comercial. No checkout, você só precisa confirmar.</p>

        <?php if ($okEnd): ?><div class="alert alert-ok"><?= htmlspecialchars($okEnd) ?></div><?php endif; ?>
        <?php if ($erroEnd): ?><div class="alert alert-err"><?= htmlspecialchars($erroEnd) ?></div><?php endif; ?>

        <div class="enderecos-grid">
            <?php foreach ($tiposEndereco as $tipo):
                $tid    = (int)$tipo['id'];
                $desc   = $tipo['descricao'];
                $end    = $enderecos[$tid] ?? null;
                $icone  = $iconesTipo[$desc] ?? '📍';
            ?>
                <div class="endereco-card <?= $end ? '' : 'vazio' ?>">
                    <h3><span class="icon-box"><?= $icone ?></span> <?= htmlspecialchars($desc) ?></h3>

                    <form method="POST" action="">
                        <input type="hidden" name="acao" value="salvar_endereco">
                        <input type="hidden" name="tipo_endereco_id" value="<?= $tid ?>">

                        <div class="form-row cols-2">
                            <div class="form-group">
                                <label class="form-label">CEP</label>
                                <input type="text" name="cep" class="form-input cep-input"
                                       value="<?= htmlspecialchars($end['cep'] ?? '') ?>"
                                       placeholder="00000-000" maxlength="9"
                                       data-target="<?= $tid ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Estado</label>
                                <input type="text" name="estado" class="form-input" data-field="estado-<?= $tid ?>"
                                       value="<?= htmlspecialchars($end['estado'] ?? '') ?>"
                                       maxlength="2" style="text-transform:uppercase" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Rua</label>
                            <input type="text" name="rua" class="form-input" data-field="rua-<?= $tid ?>"
                                   value="<?= htmlspecialchars($end['rua'] ?? '') ?>" required>
                        </div>

                        <div class="form-row cols-2">
                            <div class="form-group">
                                <label class="form-label">Número</label>
                                <input type="text" name="numero" class="form-input"
                                       value="<?= htmlspecialchars($end['numero'] ?? '') ?>"
                                       placeholder="S/N">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Complemento</label>
                                <input type="text" name="complemento" class="form-input"
                                       value="<?= htmlspecialchars($end['complemento'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-row cols-2">
                            <div class="form-group">
                                <label class="form-label">Bairro</label>
                                <input type="text" name="bairro" class="form-input" data-field="bairro-<?= $tid ?>"
                                       value="<?= htmlspecialchars($end['bairro'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Cidade</label>
                                <input type="text" name="cidade" class="form-input" data-field="cidade-<?= $tid ?>"
                                       value="<?= htmlspecialchars($end['cidade'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="endereco-actions">
                            <button type="submit" class="btn">
                                <?= $end ? 'Atualizar' : 'Salvar endereço' ?>
                            </button>
                            <?php if ($end): ?>
                                <button type="submit" name="acao" value="remover_endereco" class="btn btn-ghost"
                                        onclick="return confirm('Remover este endereço?');">
                                    Remover
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===================== ALTERAR SENHA ===================== -->
    <div class="card">
        <h2>🔒 Alterar senha</h2>
        <p class="card-sub">Escolha uma senha forte para proteger sua conta.</p>

        <?php if ($erroSenha): ?><div class="alert alert-err"><?= htmlspecialchars($erroSenha) ?></div><?php endif; ?>
        <?php if ($okSenha): ?><div class="alert alert-ok"><?= htmlspecialchars($okSenha) ?></div><?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="acao" value="alterar_senha">

            <div class="form-group">
                <label class="form-label">Senha atual</label>
                <input type="password" name="senha_atual" class="form-input" required autocomplete="current-password">
            </div>

            <div class="form-row cols-2">
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

    <!-- ===================== SAIR ===================== -->
    <div class="card danger-zone">
        <h2>🚪 Sair da conta</h2>
        <p class="card-sub">Encerre sua sessão neste dispositivo.</p>
        <a href="logout.php" class="btn-danger-link">Sair da conta</a>
    </div>
</div>

<script>
    // Máscara CEP + ViaCEP
    document.querySelectorAll('.cep-input').forEach(inp => {
        inp.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 8);
            if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
            this.value = v;

            if (v.replace('-', '').length === 8) {
                const tid = this.dataset.target;
                fetch(`https://viacep.com.br/ws/${v.replace('-', '')}/json/`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.erro) return;
                        const set = (field, val) => {
                            const el = document.querySelector(`[data-field="${field}-${tid}"]`);
                            if (el && !el.value) el.value = val || '';
                        };
                        set('rua', data.logradouro);
                        set('bairro', data.bairro);
                        set('cidade', data.localidade);
                        set('estado', data.uf);
                    })
                    .catch(() => {});
            }
        });
    });
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
