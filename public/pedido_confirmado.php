<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$vendaId = (int)($_GET['id'] ?? 0);
if (!$vendaId) {
    header('Location: produtos.php');
    exit;
}

$db = getPDO();

// Buscar dados da venda + status
$venda = $db->prepare("
    SELECT
        v.id,
        v.valor_total,
        v.data_venda,
        v.usuario_id,
        sp.descricao  AS status_pedido,
        sp.cor        AS status_cor,
        sp.icone      AS status_icone,
        spg.descricao AS status_pagamento,
        u.nome        AS cliente_nome,
        u.email       AS cliente_email,
        e.rua, e.numero, e.complemento, e.bairro, e.cidade, e.estado, e.cep
    FROM vendas v
    LEFT JOIN status_pedido    sp  ON v.status_pedido_id    = sp.id
    LEFT JOIN status_pagamento spg ON v.status_pagamento_id = spg.id
    LEFT JOIN usuarios         u   ON v.usuario_id          = u.id
    LEFT JOIN enderecos        e   ON v.endereco_id         = e.id
    WHERE v.id = :id AND v.usuario_id = :uid
");
$venda->execute([':id' => $vendaId, ':uid' => $_SESSION['user_id']]);
$venda = $venda->fetch(PDO::FETCH_ASSOC);

// Segurança: pedido não existe ou não pertence ao usuário
if (!$venda) {
    header('Location: produtos.php');
    exit;
}

// Buscar itens do pedido
$itens = $db->prepare("
    SELECT
        vi.quantidade,
        vi.preco_unitario_final,
        vi.subtotal,
        p.nome AS produto_nome,
        pi.url AS produto_imagem,
        GROUP_CONCAT(vv.valor ORDER BY tv.nome SEPARATOR ' · ') AS variacoes
    FROM vendas_itens vi
    LEFT JOIN produto_variacoes      pv  ON vi.produto_variacao_id = pv.id
    LEFT JOIN produtos               p   ON pv.id_produto          = p.id
    LEFT JOIN produtos_imagens       pi  ON p.id = pi.produto_id AND pi.ordem = 1
    LEFT JOIN produto_variacao_valores pvv ON pv.id = pvv.id_produto_variacao
    LEFT JOIN valores_variacao       vv  ON pvv.id_valor_variacao  = vv.id
    LEFT JOIN tipos_variacao         tv  ON pvv.id_tipo_variacao   = tv.id
    WHERE vi.venda_id = :venda_id
    GROUP BY vi.id
");
$itens->execute([':venda_id' => $vendaId]);
$itens = $itens->fetchAll(PDO::FETCH_ASSOC);

$isPago = str_contains(strtolower($venda['status_pagamento'] ?? ''), 'pago')
       || str_contains(strtolower($venda['status_pagamento'] ?? ''), 'aprovado')
       || str_contains(strtolower($venda['status_pedido'] ?? ''), 'processamento');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Confirmado — RS BEAUTY STORE</title>
    <?php include __DIR__ . '/partials/navbar.php'; ?>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --black:          #0a0a0a;
            --white:          #fefefe;
            --gray-light:     #f5f5f5;
            --gray-mid:       #e0e0e0;
            --gray-dark:      #666;
            --accent:         #d4af37;
            --rose-gold:      #E8B4B8;
            --deep-rose:      #C67B88;
            --soft-pink:      #FFF5F7;
            --luxury-purple:  #9B7EBD;
            --green-success:  #27ae60;
            --green-light:    #f0faf5;
            --green-pix:      #32bcad;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--white) 0%, var(--soft-pink) 60%, var(--white) 100%);
            min-height: 100vh;
            color: var(--black);
        }

        /* ── HERO CONFIRMAÇÃO ── */
        .hero {
            margin-top: 0;
            padding: 3rem 2rem 2rem;
            text-align: center;
        }

        .checkmark-wrapper {
            width: 100px; height: 100px;
            margin: 0 auto 1.5rem;
            position: relative;
        }

        .checkmark-circle {
            width: 100px; height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--green-success), #2ecc71);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 12px 40px rgba(39,174,96,0.35);
            animation: popIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }

        .checkmark-circle svg {
            width: 50px; height: 50px;
            stroke: white; stroke-width: 3;
            fill: none; stroke-linecap: round; stroke-linejoin: round;
            animation: drawCheck 0.5s 0.4s ease forwards;
            stroke-dasharray: 80;
            stroke-dashoffset: 80;
        }

        /* Pétalas decorativas ao redor */
        .petala {
            position: absolute;
            width: 12px; height: 12px;
            border-radius: 50%;
            animation: explodir 0.8s 0.3s cubic-bezier(0.165, 0.84, 0.44, 1) both;
            opacity: 0;
        }
        .petala:nth-child(2)  { background: var(--rose-gold);     top: -8px;   left: 44px;  --dx: 0px;    --dy: -30px; }
        .petala:nth-child(3)  { background: var(--accent);        top: 10px;   right: -8px; --dx: 28px;   --dy: -18px; }
        .petala:nth-child(4)  { background: var(--luxury-purple); bottom: 10px;right: -8px; --dx: 28px;   --dy: 18px;  }
        .petala:nth-child(5)  { background: var(--rose-gold);     bottom: -8px;left: 44px;  --dx: 0px;    --dy: 30px;  }
        .petala:nth-child(6)  { background: var(--green-pix);     bottom: 10px;left: -8px;  --dx: -28px;  --dy: 18px;  }
        .petala:nth-child(7)  { background: var(--accent);        top: 10px;   left: -8px;  --dx: -28px;  --dy: -18px; }

        @keyframes popIn {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }
        @keyframes drawCheck {
            to { stroke-dashoffset: 0; }
        }
        @keyframes explodir {
            0%   { opacity: 0; transform: translate(0,0) scale(0); }
            60%  { opacity: 1; }
            100% { opacity: 0; transform: translate(var(--dx), var(--dy)) scale(1); }
        }

        .hero-titulo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.8rem; font-weight: 700;
            background: linear-gradient(135deg, var(--green-success), var(--green-pix));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.6rem;
            animation: fadeUp 0.5s 0.7s ease both;
        }

        .hero-sub {
            font-size: 1rem; color: var(--gray-dark);
            font-weight: 400; max-width: 480px; margin: 0 auto;
            animation: fadeUp 0.5s 0.85s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── PEDIDO NÚMERO ── */
        .pedido-numero-badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            margin: 1.5rem auto 0;
            padding: 0.7rem 1.6rem;
            background: white;
            border: 2px solid var(--gray-mid);
            border-radius: 50px;
            font-size: 1rem; font-weight: 700; color: var(--black);
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            animation: fadeUp 0.5s 1s ease both;
        }

        .pedido-numero-badge span { color: var(--deep-rose); }

        /* ── STATUS PILL ── */
        .status-pill {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-size: 0.82rem; font-weight: 700; color: white;
            margin-top: 1rem;
            animation: fadeUp 0.5s 1.1s ease both;
        }

        /* ── LAYOUT PRINCIPAL ── */
        .page-body {
            max-width: 860px; margin: 2.5rem auto 4rem;
            padding: 0 1.5rem;
            display: flex; flex-direction: column; gap: 1.5rem;
            animation: fadeUp 0.5s 1.1s ease both;
        }

        /* ── CARDS ── */
        .card {
            background: white;
            border-radius: 18px;
            padding: 1.8rem 2rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        }

        .card-titulo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem; font-weight: 600;
            margin-bottom: 1.2rem;
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
            padding-bottom: 0.8rem;
            border-bottom: 1.5px solid var(--gray-mid);
        }

        /* ── ITENS ── */
        .item-lista { display: flex; flex-direction: column; gap: 1rem; }

        .item {
            display: flex; gap: 1rem; align-items: center;
            padding: 0.8rem 0;
            border-bottom: 1px solid var(--gray-light);
        }
        .item:last-child { border-bottom: none; padding-bottom: 0; }

        .item-img {
            width: 64px; height: 64px; border-radius: 10px;
            overflow: hidden; flex-shrink: 0;
            background: var(--gray-light);
        }
        .item-img img { width: 100%; height: 100%; object-fit: cover; }

        .item-info { flex: 1; }
        .item-nome { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.2rem; }
        .item-variacao { font-size: 0.78rem; color: var(--gray-dark); margin-bottom: 0.2rem; }
        .item-qty { font-size: 0.8rem; color: var(--gray-dark); }

        .item-preco {
            font-weight: 700; font-size: 1rem;
            color: var(--deep-rose); white-space: nowrap;
        }

        /* ── TOTAIS ── */
        .totais { margin-top: 1.2rem; }
        .total-row {
            display: flex; justify-content: space-between;
            padding: 0.6rem 0; font-size: 0.9rem; color: var(--gray-dark);
            border-bottom: 1px solid var(--gray-light);
        }
        .total-row:last-child { border-bottom: none; }
        .total-row.destaque {
            font-size: 1.1rem; font-weight: 700;
            color: var(--black); padding-top: 1rem; margin-top: 0.4rem;
            border-top: 2px solid var(--deep-rose); border-bottom: none;
        }
        .total-row.destaque .valor-total {
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; font-size: 1.4rem;
        }

        /* ── INFOS GRID ── */
        .infos-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 1.2rem;
        }
        .info-bloco { }
        .info-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gray-dark); font-weight: 600; margin-bottom: 0.3rem; }
        .info-valor { font-size: 0.95rem; font-weight: 600; color: var(--black); }
        .info-valor small { display: block; font-weight: 400; font-size: 0.8rem; color: var(--gray-dark); margin-top: 0.1rem; }

        /* ── AVISO AGUARDANDO (se não estiver pago ainda) ── */
        .aviso-aguardando {
            background: linear-gradient(135deg, #fffbf0, #fff8e1);
            border: 1.5px solid var(--accent);
            border-radius: 14px;
            padding: 1.4rem 1.6rem;
            display: flex; gap: 1rem; align-items: flex-start;
        }
        .aviso-aguardando .aviso-icone { font-size: 1.8rem; flex-shrink: 0; }
        .aviso-aguardando .aviso-texto strong { display: block; font-size: 0.95rem; margin-bottom: 0.3rem; }
        .aviso-aguardando .aviso-texto p { font-size: 0.82rem; color: var(--gray-dark); line-height: 1.6; }

        /* ── BOTÕES ── */
        .acoes {
            display: flex; gap: 1rem; flex-wrap: wrap;
        }

        .btn-primario {
            flex: 1; min-width: 200px;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            color: white; border: none; border-radius: 12px;
            font-family: inherit; font-size: 0.95rem; font-weight: 700;
            letter-spacing: 0.5px; cursor: pointer;
            text-decoration: none; text-align: center;
            transition: all 0.3s;
            box-shadow: 0 6px 20px rgba(198,123,136,0.3);
        }
        .btn-primario:hover {
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(155,126,189,0.4);
        }

        .btn-secundario {
            flex: 1; min-width: 180px;
            padding: 1rem 1.5rem;
            background: white;
            color: var(--black); border: 2px solid var(--gray-mid);
            border-radius: 12px; font-family: inherit;
            font-size: 0.95rem; font-weight: 600;
            text-decoration: none; text-align: center;
            transition: all 0.3s; cursor: pointer;
        }
        .btn-secundario:hover {
            border-color: var(--rose-gold);
            background: var(--soft-pink);
            transform: translateY(-2px);
        }

        /* ── POLLING STATUS ── */
        .status-realtime {
            display: flex; align-items: center; gap: 0.7rem;
            font-size: 0.8rem; color: var(--gray-dark);
            margin-top: 0.8rem;
        }
        .dot-pulse {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--green-success);
            animation: pulse 1.8s infinite;
            flex-shrink: 0;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(0.7); }
        }

        @media (max-width: 640px) {
            .hero-titulo { font-size: 2rem; }
            .infos-grid  { grid-template-columns: 1fr; }
            .acoes       { flex-direction: column; }
            .card        { padding: 1.4rem; }
        }
    </style>
</head>
<body>

<!-- HERO -->
<div class="hero">
    <div class="checkmark-wrapper">
        <div class="petala"></div>
        <div class="petala"></div>
        <div class="petala"></div>
        <div class="petala"></div>
        <div class="petala"></div>
        <div class="petala"></div>
        <div class="checkmark-circle">
            <svg viewBox="0 0 52 52">
                <polyline points="14,27 22,35 38,18"/>
            </svg>
        </div>
    </div>

    <h1 class="hero-titulo">Pedido realizado!</h1>
    <p class="hero-sub">
        Obrigada pela sua compra, <strong><?= htmlspecialchars(explode(' ', $venda['cliente_nome'])[0]) ?></strong>!
        Acompanhe os detalhes abaixo.
    </p>

    <div class="pedido-numero-badge">
        🛍️ Pedido <span>#<?= str_pad($vendaId, 6, '0', STR_PAD_LEFT) ?></span>
    </div>

    <div>
        <span class="status-pill" style="background: <?= htmlspecialchars($venda['status_cor'] ?? '#6c757d') ?>">
            <?= htmlspecialchars($venda['status_icone'] ?? '📦') ?>
            <?= htmlspecialchars($venda['status_pedido'] ?? 'Aguardando') ?>
        </span>
    </div>
</div>

<div class="page-body">

    <?php if (!$isPago): ?>
    <!-- Aviso aguardando confirmação de pagamento -->
    <div class="aviso-aguardando" id="avisoAguardando">
        <div class="aviso-icone">⏳</div>
        <div class="aviso-texto">
            <strong>Aguardando confirmação do pagamento</strong>
            <p>Se você já realizou o pagamento, a confirmação pode levar alguns instantes.
               Esta página atualiza automaticamente assim que recebermos a confirmação.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ITENS DO PEDIDO -->
    <div class="card">
        <div class="card-titulo">Itens do Pedido</div>
        <div class="item-lista">
            <?php foreach ($itens as $item): ?>
            <div class="item">
                <div class="item-img">
                    <?php if ($item['produto_imagem']): ?>
                        <img src="<?= htmlspecialchars($item['produto_imagem']) ?>"
                             alt="<?= htmlspecialchars($item['produto_nome'] ?? '') ?>">
                    <?php endif; ?>
                </div>
                <div class="item-info">
                    <div class="item-nome"><?= htmlspecialchars($item['produto_nome'] ?? 'Produto') ?></div>
                    <?php if ($item['variacoes']): ?>
                        <div class="item-variacao"><?= htmlspecialchars($item['variacoes']) ?></div>
                    <?php endif; ?>
                    <div class="item-qty">Quantidade: <?= (int)$item['quantidade'] ?></div>
                </div>
                <div class="item-preco">
                    R$ <?= number_format((float)$item['subtotal'], 2, ',', '.') ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="totais">
            <div class="total-row">
                <span>Subtotal</span>
                <span>R$ <?= number_format((float)$venda['valor_total'], 2, ',', '.') ?></span>
            </div>
            <div class="total-row">
                <span>Frete</span>
                <span style="color: var(--green-success); font-weight: 600;">Grátis</span>
            </div>
            <div class="total-row destaque">
                <span>Total</span>
                <span class="valor-total">R$ <?= number_format((float)$venda['valor_total'], 2, ',', '.') ?></span>
            </div>
        </div>
    </div>

    <!-- INFORMAÇÕES GERAIS -->
    <div class="card">
        <div class="card-titulo">Informações do Pedido</div>
        <div class="infos-grid">
            <div class="info-bloco">
                <div class="info-label">Cliente</div>
                <div class="info-valor">
                    <?= htmlspecialchars($venda['cliente_nome'] ?? '—') ?>
                    <small><?= htmlspecialchars($venda['cliente_email'] ?? '') ?></small>
                </div>
            </div>
            <div class="info-bloco">
                <div class="info-label">Data do Pedido</div>
                <div class="info-valor">
                    <?= date('d/m/Y', strtotime($venda['data_venda'])) ?>
                    <small><?= date('H:i', strtotime($venda['data_venda'])) ?>h</small>
                </div>
            </div>
            <div class="info-bloco">
                <div class="info-label">Forma de Pagamento</div>
                <div class="info-valor" id="statusPagamentoTexto">
                    <?= htmlspecialchars($venda['status_pagamento'] ?? 'Não informado') ?>
                </div>
            </div>
            <div class="info-bloco">
                <div class="info-label">Status</div>
                <div class="info-valor">
                    <span id="statusPedidoPill" class="status-pill"
                          style="background:<?= htmlspecialchars($venda['status_cor'] ?? '#6c757d') ?>;margin-top:0;padding:3px 10px;font-size:0.75rem">
                        <?= htmlspecialchars($venda['status_icone'] ?? '') ?>
                        <?= htmlspecialchars($venda['status_pedido'] ?? '—') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ENDEREÇO DE ENTREGA -->
    <?php if ($venda['rua']): ?>
    <div class="card">
        <div class="card-titulo">Endereço de Entrega</div>
        <div class="infos-grid">
            <div class="info-bloco" style="grid-column: 1/-1">
                <div class="info-label">Endereço</div>
                <div class="info-valor">
                    <?= htmlspecialchars($venda['rua']) ?>, <?= htmlspecialchars($venda['numero'] ?? 'S/N') ?>
                    <?php if ($venda['complemento']): ?> — <?= htmlspecialchars($venda['complemento']) ?><?php endif; ?>
                    <small>
                        <?= htmlspecialchars($venda['bairro']) ?> —
                        <?= htmlspecialchars($venda['cidade']) ?>/<?= htmlspecialchars($venda['estado']) ?> —
                        CEP <?= htmlspecialchars($venda['cep']) ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- INDICADOR TEMPO REAL -->
    <?php if (!$isPago): ?>
    <div class="status-realtime">
        <div class="dot-pulse"></div>
        <span id="textoRealtime">Aguardando confirmação de pagamento…</span>
    </div>
    <?php endif; ?>

    <!-- AÇÕES -->
    <div class="acoes">
        <a href="produtos.php" class="btn-secundario">← Continuar Comprando</a>
        <a href="meus_pedidos.php" class="btn-primario">Ver Meus Pedidos</a>
    </div>

</div>

<script>
// ============================================================
// POLLING: verificar status do pagamento a cada 8s
// Para quando a AbacatePay confirmar o pagamento via webhook,
// o cliente já verá a atualização sem precisar recarregar.
// ============================================================
const VENDA_ID       = <?= $vendaId ?>;
const JA_PAGO_INIT   = <?= $isPago ? 'true' : 'false' ?>;
const POLL_INTERVAL  = 8000;

let jaPago = JA_PAGO_INIT;
let pollTimer;

async function checarStatus() {
    if (jaPago) return;

    try {
        const resp = await fetch(`../api.php?action=polling_pedidos&ids=${VENDA_ID}`);
        const data = await resp.json();

        if (!data.success || !data.pedidos?.length) return;

        const pedido = data.pedidos[0];
        const desc   = (pedido.status_descricao || '').toLowerCase();
        const pagDesc= (pedido.status_pagamento  || '').toLowerCase();

        const foiPago = desc.includes('pago')
                     || desc.includes('aprovado')
                     || desc.includes('processamento')
                     || pagDesc.includes('pago')
                     || pagDesc.includes('aprovado');

        if (foiPago && !jaPago) {
            jaPago = true;
            clearInterval(pollTimer);
            confirmarPagamentoNaTela(pedido);
        }

    } catch(e) {
        // silencioso
    }
}

function confirmarPagamentoNaTela(pedido) {
    // Esconder aviso de aguardando
    const aviso = document.getElementById('avisoAguardando');
    if (aviso) aviso.remove();

    // Esconder indicador pulsante
    const rt = document.querySelector('.status-realtime');
    if (rt) rt.remove();

    // Atualizar pill de status no hero
    const heroSpan = document.querySelector('.hero .status-pill');
    if (heroSpan) {
        heroSpan.style.background = pedido.status_cor || '#27ae60';
        heroSpan.textContent = `${pedido.status_icone || '✅'} ${pedido.status_descricao}`;
    }

    // Atualizar pill dentro do card info
    const pill = document.getElementById('statusPedidoPill');
    if (pill) {
        pill.style.background = pedido.status_cor || '#27ae60';
        pill.textContent = `${pedido.status_icone || '✅'} ${pedido.status_descricao}`;
    }

    // Atualizar texto de pagamento
    const pagEl = document.getElementById('statusPagamentoTexto');
    if (pagEl && pedido.status_pagamento) pagEl.textContent = pedido.status_pagamento;

    // Mostrar banner de sucesso
    const banner = document.createElement('div');
    banner.style.cssText = `
        position: fixed; bottom: 24px; right: 24px; z-index: 999;
        background: white; border-radius: 14px; padding: 16px 20px;
        display: flex; align-items: center; gap: 14px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        border-left: 4px solid #27ae60;
        min-width: 300px;
        animation: slideIn 0.4s ease;
    `;
    banner.innerHTML = `
        <span style="font-size:2rem">✅</span>
        <div>
            <strong style="display:block;font-size:14px;color:#2c3e50;margin-bottom:3px">Pagamento confirmado!</strong>
            <span style="font-size:13px;color:#6c757d">Seu pedido está sendo preparado 🎉</span>
        </div>
    `;
    document.body.appendChild(banner);

    // Animação CSS inline
    const style = document.createElement('style');
    style.textContent = `@keyframes slideIn { from { transform:translateX(120%); opacity:0 } to { transform:translateX(0); opacity:1 } }`;
    document.head.appendChild(style);

    // Auto-remover após 8s
    setTimeout(() => banner.remove(), 8000);
}

// Iniciar apenas se não estiver pago
if (!jaPago) {
    pollTimer = setInterval(checarStatus, POLL_INTERVAL);
    checarStatus(); // checar imediatamente
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>