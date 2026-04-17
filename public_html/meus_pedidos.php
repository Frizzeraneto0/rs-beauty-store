<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'meus_pedidos.php';
    header('Location: login.php');
    exit;
}

$db = getPDO();

$usuario = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
$usuario->execute([':id' => $_SESSION['user_id']]);
$usuario = $usuario->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ── Pedidos do usuário ──────────────────────────────────────
$pedidos = $db->prepare("
    SELECT
        v.id,
        v.valor_total,
        v.data_venda,
        v.status_pedido_id,
        sp.descricao  AS status_pedido,
        sp.cor        AS status_cor,
        sp.icone      AS status_icone,
        sp.slug       AS status_slug,
        spg.descricao AS status_pagamento,
        (SELECT COUNT(*) FROM vendas_itens WHERE venda_id = v.id) AS qtd_itens,
        (
            SELECT pi.url
            FROM vendas_itens vi2
            LEFT JOIN produto_variacoes pv2 ON vi2.produto_variacao_id = pv2.id
            LEFT JOIN produtos_imagens  pi  ON pv2.id_produto = pi.produto_id AND pi.ordem = 1
            WHERE vi2.venda_id = v.id
            LIMIT 1
        ) AS primeira_imagem,
        (
            SELECT GROUP_CONCAT(p2.nome ORDER BY vi3.id SEPARATOR ', ')
            FROM vendas_itens vi3
            LEFT JOIN produto_variacoes pv3 ON vi3.produto_variacao_id = pv3.id
            LEFT JOIN produtos          p2  ON pv3.id_produto = p2.id
            WHERE vi3.venda_id = v.id
            LIMIT 3
        ) AS nomes_produtos
    FROM vendas v
    LEFT JOIN status_pedido    sp  ON v.status_pedido_id    = sp.id
    LEFT JOIN status_pagamento spg ON v.status_pagamento_id = spg.id
    WHERE v.usuario_id = :uid
    ORDER BY v.data_venda DESC
");
$pedidos->execute([':uid' => $_SESSION['user_id']]);
$pedidos = $pedidos->fetchAll(PDO::FETCH_ASSOC);

// ── Estatísticas rápidas ────────────────────────────────────
$stats = $db->prepare("
    SELECT
        COUNT(*)                                               AS total,
        COALESCE(SUM(valor_total), 0)                         AS gasto_total,
        SUM(CASE WHEN status_pedido_id = 1 THEN 1 ELSE 0 END) AS aguardando,
        SUM(CASE WHEN status_pedido_id IN (4,5) THEN 1 ELSE 0 END) AS em_transito
    FROM vendas
    WHERE usuario_id = :uid
");
$stats->execute([':uid' => $_SESSION['user_id']]);
$stats = $stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Pedidos — RS BEAUTY STORE</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,400&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --black:         #0a0a0a;
            --white:         #fefefe;
            --gray-light:    #f7f5f3;
            --gray-mid:      #e8e4e0;
            --gray-dark:     #888;
            --accent:        #d4af37;
            --rose-gold:     #E8B4B8;
            --deep-rose:     #C67B88;
            --soft-pink:     #FFF5F7;
            --luxury-purple: #9B7EBD;
            --champagne:     #F7E7CE;
            --ink:           #1a1614;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--gray-light);
            color: var(--ink);
            min-height: 100vh;
        }

        /* ── HEADER ── */
        .header {
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
            background: rgba(254,254,254,0.97);
            backdrop-filter: blur(24px);
            border-bottom: 1px solid var(--gray-mid);
        }
        .nav {
            max-width: 1100px; margin: 0 auto;
            padding: 1.2rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
        }
        .logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem; font-weight: 600; letter-spacing: 3px;
            background: linear-gradient(135deg, var(--black) 0%, var(--deep-rose) 50%, var(--luxury-purple) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; text-decoration: none;
        }
        .nav-links { display: flex; gap: 1.5rem; align-items: center; }
        .nav-link {
            font-size: 0.82rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 1px; color: var(--gray-dark);
            text-decoration: none; transition: color 0.2s;
        }
        .nav-link:hover { color: var(--deep-rose); }
        .nav-link.active { color: var(--deep-rose); }

        /* ── PAGE WRAPPER ── */
        .page {
            max-width: 1100px; margin: 0 auto;
            padding: 110px 2rem 4rem;
        }

        /* ── HERO HEADER ── */
        .page-hero {
            margin-bottom: 2.5rem;
            display: flex; justify-content: space-between; align-items: flex-end;
            flex-wrap: wrap; gap: 1rem;
        }
        .page-hero-left {}
        .page-eyebrow {
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 2.5px; color: var(--deep-rose);
            margin-bottom: 0.4rem;
        }
        .page-titulo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3rem; font-weight: 600; line-height: 1.05;
            color: var(--ink);
        }
        .page-titulo em {
            font-style: italic;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .page-subtitulo {
            margin-top: 0.5rem;
            font-size: 0.87rem; color: var(--gray-dark); font-weight: 400;
        }

        .btn-comprar {
            padding: 0.85rem 1.8rem;
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            color: white; border: none; border-radius: 50px;
            font-family: inherit; font-size: 0.82rem; font-weight: 700;
            letter-spacing: 1px; text-transform: uppercase;
            text-decoration: none; cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 6px 20px rgba(198,123,136,0.3);
            white-space: nowrap;
        }
        .btn-comprar:hover {
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(155,126,189,0.4);
        }

        /* ── STATS FAIXA ── */
        .stats-faixa {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 1px; background: var(--gray-mid);
            border-radius: 16px; overflow: hidden;
            margin-bottom: 2.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .stat-item {
            background: white;
            padding: 1.4rem 1.6rem;
            display: flex; flex-direction: column; gap: 0.3rem;
        }
        .stat-item:first-child { border-radius: 16px 0 0 16px; }
        .stat-item:last-child  { border-radius: 0 16px 16px 0; }
        .stat-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem; font-weight: 700; line-height: 1;
            color: var(--ink);
        }
        .stat-num.rosa {
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-lbl { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: var(--gray-dark); }

        /* ── FILTRO TABS ── */
        .filtro-tabs {
            display: flex; gap: 0.5rem; flex-wrap: wrap;
            margin-bottom: 1.8rem;
        }
        .tab {
            padding: 0.55rem 1.2rem;
            background: white; border: 1.5px solid var(--gray-mid);
            border-radius: 50px; font-size: 0.8rem; font-weight: 600;
            color: var(--gray-dark); cursor: pointer; transition: all 0.25s;
            font-family: inherit;
        }
        .tab:hover { border-color: var(--rose-gold); color: var(--deep-rose); }
        .tab.ativo { background: var(--ink); border-color: var(--ink); color: white; }

        /* ── LISTA DE PEDIDOS ── */
        .pedidos-lista {
            display: flex; flex-direction: column; gap: 1rem;
        }

        /* ── CARD PEDIDO ── */
        .pedido-card {
            background: white;
            border-radius: 18px;
            border: 1.5px solid var(--gray-mid);
            overflow: hidden;
            transition: all 0.3s;
            cursor: pointer;
            animation: entrar 0.4s ease both;
        }
        .pedido-card:hover {
            border-color: var(--rose-gold);
            box-shadow: 0 8px 32px rgba(198,123,136,0.12);
            transform: translateY(-2px);
        }
        @keyframes entrar {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* linha principal */
        .card-main {
            padding: 1.4rem 1.6rem;
            display: grid;
            grid-template-columns: 64px 1fr auto;
            gap: 1.2rem;
            align-items: center;
        }

        /* thumbnail mosaico */
        .thumb-wrap {
            width: 64px; height: 64px;
            border-radius: 12px; overflow: hidden;
            background: var(--gray-light); flex-shrink: 0;
            position: relative;
        }
        .thumb-wrap img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .thumb-placeholder {
            width: 100%; height: 100%;
            background: linear-gradient(135deg, var(--soft-pink), var(--champagne));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem;
        }

        /* info central */
        .card-info {}
        .card-info-top {
            display: flex; align-items: center; gap: 0.7rem;
            margin-bottom: 0.3rem; flex-wrap: wrap;
        }
        .pedido-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.25rem; font-weight: 700; color: var(--ink);
        }
        .status-chip {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 3px 10px; border-radius: 50px;
            font-size: 0.7rem; font-weight: 700; color: white;
            white-space: nowrap;
        }
        .card-produtos {
            font-size: 0.82rem; color: var(--gray-dark);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            max-width: 420px;
        }
        .card-meta {
            display: flex; gap: 1.2rem; margin-top: 0.3rem;
            font-size: 0.75rem; color: var(--gray-dark);
        }
        .card-meta span { display: flex; align-items: center; gap: 0.3rem; }

        /* lado direito */
        .card-right {
            text-align: right; flex-shrink: 0;
        }
        .card-valor {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.6rem; font-weight: 700;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; white-space: nowrap;
        }
        .card-itens-count {
            font-size: 0.75rem; color: var(--gray-dark); margin-top: 0.2rem;
        }
        .card-chevron {
            margin-top: 0.5rem; font-size: 1rem; color: var(--gray-mid);
            transition: transform 0.3s;
        }
        .pedido-card.aberto .card-chevron { transform: rotate(180deg); }

        /* ── DETALHE EXPANDIDO ── */
        .card-detalhe {
            display: none;
            border-top: 1.5px solid var(--gray-light);
            padding: 1.4rem 1.6rem;
            background: var(--gray-light);
            animation: expandir 0.3s ease;
        }
        .card-detalhe.visivel { display: block; }
        @keyframes expandir {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .detalhe-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.4rem;
        }

        /* itens */
        .detalhe-secao-titulo {
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1.5px; color: var(--deep-rose);
            margin-bottom: 0.8rem;
        }

        .item-mini {
            display: flex; gap: 0.8rem; align-items: center;
            padding: 0.6rem 0;
            border-bottom: 1px solid var(--gray-mid);
        }
        .item-mini:last-child { border-bottom: none; }
        .item-mini-img {
            width: 44px; height: 44px; border-radius: 8px;
            overflow: hidden; flex-shrink: 0; background: var(--gray-mid);
        }
        .item-mini-img img { width: 100%; height: 100%; object-fit: cover; }
        .item-mini-info { flex: 1; }
        .item-mini-nome { font-size: 0.82rem; font-weight: 600; color: var(--ink); }
        .item-mini-var  { font-size: 0.72rem; color: var(--gray-dark); }
        .item-mini-preco { font-size: 0.85rem; font-weight: 700; color: var(--deep-rose); white-space: nowrap; }

        /* endereço + pagamento */
        .detalhe-info-bloco { margin-bottom: 1rem; }
        .detalhe-info-bloco:last-child { margin-bottom: 0; }
        .detalhe-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--gray-dark); margin-bottom: 0.3rem; }
        .detalhe-valor { font-size: 0.87rem; font-weight: 500; color: var(--ink); line-height: 1.5; }

        /* histórico */
        .timeline-mini { padding-left: 18px; position: relative; }
        .timeline-mini::before {
            content: ''; position: absolute; left: 5px; top: 6px; bottom: 6px;
            width: 1.5px; background: var(--gray-mid);
        }
        .tl-item { position: relative; padding-bottom: 0.8rem; }
        .tl-item:last-child { padding-bottom: 0; }
        .tl-item::before {
            content: ''; position: absolute; left: -17px; top: 5px;
            width: 9px; height: 9px; border-radius: 50%;
            background: var(--deep-rose);
            border: 2px solid white;
            box-shadow: 0 0 0 1.5px var(--rose-gold);
        }
        .tl-data   { font-size: 0.7rem; color: var(--gray-dark); margin-bottom: 1px; }
        .tl-status { font-size: 0.82rem; font-weight: 600; color: var(--ink); }
        .tl-obs    { font-size: 0.75rem; color: var(--gray-dark); font-style: italic; }

        /* ações do card */
        .card-acoes {
            display: flex; gap: 0.8rem; margin-top: 1.2rem; flex-wrap: wrap;
        }
        .btn-sm-acao {
            padding: 0.6rem 1.2rem; border-radius: 8px;
            font-size: 0.78rem; font-weight: 700; font-family: inherit;
            cursor: pointer; transition: all 0.25s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 0.4rem;
            border: 1.5px solid transparent;
        }
        .btn-sm-outline {
            background: white; border-color: var(--gray-mid); color: var(--ink);
        }
        .btn-sm-outline:hover { border-color: var(--deep-rose); color: var(--deep-rose); }
        .btn-sm-filled {
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            color: white; border: none;
            box-shadow: 0 4px 14px rgba(198,123,136,0.3);
        }
        .btn-sm-filled:hover {
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            transform: translateY(-1px);
        }

        /* loading spinner no detalhe */
        .loading-detalhe {
            text-align: center; padding: 2rem;
            color: var(--gray-dark); font-size: 0.85rem;
        }
        .spin {
            display: inline-block; width: 24px; height: 24px;
            border: 3px solid var(--gray-mid); border-top-color: var(--deep-rose);
            border-radius: 50%; animation: girar 0.8s linear infinite;
            margin-bottom: 0.5rem;
        }
        @keyframes girar { to { transform: rotate(360deg); } }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 5rem 2rem;
            background: white; border-radius: 20px;
            border: 2px dashed var(--gray-mid);
        }
        .empty-icone { font-size: 4rem; margin-bottom: 1.2rem; }
        .empty-titulo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem; font-weight: 600;
            color: var(--ink); margin-bottom: 0.5rem;
        }
        .empty-sub { font-size: 0.9rem; color: var(--gray-dark); margin-bottom: 1.8rem; }

        /* ── RESPONSIVO ── */
        @media (max-width: 720px) {
            .page-titulo { font-size: 2.2rem; }
            .stats-faixa { grid-template-columns: repeat(2, 1fr); }
            .stats-faixa .stat-item:nth-child(2) { border-radius: 0 16px 0 0; }
            .stats-faixa .stat-item:nth-child(3) { border-radius: 0 0 0 16px; }
            .card-main { grid-template-columns: 52px 1fr; }
            .card-right { display: none; }
            .detalhe-grid { grid-template-columns: 1fr; }
            .card-produtos { max-width: 200px; }
        }
        @media (max-width: 480px) {
            .page { padding: 100px 1rem 3rem; }
            .nav  { padding: 1.2rem 1rem; }
            .stats-faixa { grid-template-columns: 1fr 1fr; border-radius: 12px; }
        }
    </style>
</head>
<body>

<header class="header">
    <nav class="nav">
        <a href="produtos.php" class="logo">RS BEAUTY STORE</a>
        <div class="nav-links">
            <a href="produtos.php" class="nav-link">Loja</a>
            <a href="carrinho.php" class="nav-link">Carrinho</a>
            <a href="meus_pedidos.php" class="nav-link active">Meus Pedidos</a>
        </div>
    </nav>
</header>

<div class="page">

    <!-- HERO -->
    <div class="page-hero">
        <div class="page-hero-left">
            <div class="page-eyebrow">Área do cliente</div>
            <h1 class="page-titulo">
                Olá, <em><?= htmlspecialchars(explode(' ', $usuario['nome'])[0]) ?></em>
            </h1>
            <p class="page-subtitulo">Acompanhe todos os seus pedidos em um só lugar.</p>
        </div>
        <a href="produtos.php" class="btn-comprar">+ Continuar Comprando</a>
    </div>

    <!-- STATS -->
    <div class="stats-faixa">
        <div class="stat-item">
            <div class="stat-num"><?= (int)$stats['total'] ?></div>
            <div class="stat-lbl">Pedidos no total</div>
        </div>
        <div class="stat-item">
            <div class="stat-num rosa">R$&nbsp;<?= number_format((float)$stats['gasto_total'], 2, ',', '.') ?></div>
            <div class="stat-lbl">Total investido</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?= (int)$stats['aguardando'] ?></div>
            <div class="stat-lbl">Aguardando pagamento</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?= (int)$stats['em_transito'] ?></div>
            <div class="stat-lbl">Em trânsito</div>
        </div>
    </div>

    <!-- FILTRO TABS -->
    <div class="filtro-tabs" id="filtroTabs">
        <button class="tab ativo" onclick="filtrar('todos', this)">Todos</button>
        <button class="tab" onclick="filtrar('aguardando', this)">Aguardando pagamento</button>
        <button class="tab" onclick="filtrar('preparando', this)">Preparando</button>
        <button class="tab" onclick="filtrar('transito', this)">Em trânsito</button>
        <button class="tab" onclick="filtrar('entregue', this)">Entregues</button>
    </div>

    <!-- LISTA DE PEDIDOS -->
    <?php if (empty($pedidos)): ?>
        <div class="empty-state">
            <div class="empty-icone">🛍️</div>
            <div class="empty-titulo">Nenhum pedido ainda</div>
            <p class="empty-sub">Você ainda não realizou nenhuma compra. Que tal explorar nossos produtos?</p>
            <a href="produtos.php" class="btn-comprar">Explorar produtos</a>
        </div>
    <?php else: ?>

        <div class="pedidos-lista" id="pedidosLista">
            <?php foreach ($pedidos as $i => $p):
                $slugStatus = strtolower($p['status_slug'] ?? $p['status_pedido'] ?? '');
                $dataFmt    = date('d/m/Y', strtotime($p['data_venda']));
                $horaFmt    = date('H:i', strtotime($p['data_venda']));
            ?>
            <div class="pedido-card"
                 id="card-<?= $p['id'] ?>"
                 data-status-slug="<?= htmlspecialchars($slugStatus) ?>"
                 data-status-id="<?= (int)$p['status_pedido_id'] ?>"
                 style="animation-delay: <?= $i * 0.06 ?>s"
                 onclick="toggleCard(<?= $p['id'] ?>, this)">

                <!-- LINHA PRINCIPAL -->
                <div class="card-main">
                    <!-- thumbnail -->
                    <div class="thumb-wrap">
                        <?php if ($p['primeira_imagem']): ?>
                            <img src="<?= htmlspecialchars($p['primeira_imagem']) ?>" alt="produto">
                        <?php else: ?>
                            <div class="thumb-placeholder">🌸</div>
                        <?php endif; ?>
                    </div>

                    <!-- info -->
                    <div class="card-info">
                        <div class="card-info-top">
                            <span class="pedido-num">#<?= str_pad($p['id'], 6, '0', STR_PAD_LEFT) ?></span>
                            <span class="status-chip badge-status-<?= $p['id'] ?>"
                                  style="background:<?= htmlspecialchars($p['status_cor'] ?? '#888') ?>">
                                <?= htmlspecialchars($p['status_icone'] ?? '') ?>
                                <?= htmlspecialchars($p['status_pedido'] ?? '—') ?>
                            </span>
                        </div>
                        <div class="card-produtos">
                            <?= htmlspecialchars($p['nomes_produtos'] ?? 'Produtos') ?>
                            <?php if ((int)$p['qtd_itens'] > 3): ?> e mais...<?php endif; ?>
                        </div>
                        <div class="card-meta">
                            <span>📅 <?= $dataFmt ?> às <?= $horaFmt ?>h</span>
                            <span>📦 <?= (int)$p['qtd_itens'] ?> item(s)</span>
                            <span>💳 <?= htmlspecialchars($p['status_pagamento'] ?? '—') ?></span>
                        </div>
                    </div>

                    <!-- valor + chevron -->
                    <div class="card-right">
                        <div class="card-valor">R$ <?= number_format((float)$p['valor_total'], 2, ',', '.') ?></div>
                        <div class="card-itens-count"><?= (int)$p['qtd_itens'] ?> produto(s)</div>
                        <div class="card-chevron">▾</div>
                    </div>
                </div>

                <!-- DETALHE (expandido ao clicar) -->
                <div class="card-detalhe" id="detalhe-<?= $p['id'] ?>">
                    <div class="loading-detalhe">
                        <div class="spin"></div><br>Carregando detalhes…
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<script>
// ── IDs e status iniciais para polling ────────────────────────
const TODOS_IDS = <?= json_encode(array_column($pedidos, 'id')) ?>;
const estadoLocal = {};
<?php foreach ($pedidos as $p): ?>
estadoLocal[<?= $p['id'] ?>] = <?= (int)$p['status_pedido_id'] ?>;
<?php endforeach; ?>

// ── Filtros ────────────────────────────────────────────────────
const FILTRO_MAP = {
    todos:      () => true,
    aguardando: el => el.dataset.statusSlug?.includes('aguardando') || el.dataset.statusSlug?.includes('pendente'),
    preparando: el => el.dataset.statusSlug?.includes('preparando') || el.dataset.statusSlug?.includes('processamento'),
    transito:   el => el.dataset.statusSlug?.includes('transito') || el.dataset.statusSlug?.includes('enviado') || el.dataset.statusSlug?.includes('entrega'),
    entregue:   el => el.dataset.statusSlug?.includes('entregue') || el.dataset.statusSlug?.includes('concluido'),
};

function filtrar(chave, btn) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('ativo'));
    btn.classList.add('ativo');

    const fn = FILTRO_MAP[chave] || FILTRO_MAP.todos;
    document.querySelectorAll('.pedido-card').forEach((card, i) => {
        const visivel = fn(card);
        card.style.display = visivel ? '' : 'none';
        if (visivel) card.style.animationDelay = (i * 0.04) + 's';
    });
}

// ── Expand/collapse card ──────────────────────────────────────
const detalhesCarregados = {};

async function toggleCard(id, cardEl) {
    // Não propagar se clicou num botão interno
    if (event.target.closest('.btn-sm-acao')) return;

    const detalheEl = document.getElementById(`detalhe-${id}`);
    const aberto    = cardEl.classList.contains('aberto');

    // Fechar todos os outros
    document.querySelectorAll('.pedido-card.aberto').forEach(c => {
        c.classList.remove('aberto');
        document.getElementById(`detalhe-${c.id.replace('card-','')}`).classList.remove('visivel');
    });

    if (aberto) return; // toggle: se já estava aberto, só fecha

    cardEl.classList.add('aberto');
    detalheEl.classList.add('visivel');

    // Carregar detalhes via API (apenas uma vez)
    if (!detalhesCarregados[id]) {
        await carregarDetalhe(id, detalheEl);
        detalhesCarregados[id] = true;
    }
}

async function carregarDetalhe(id, container) {
    try {
        const resp = await fetch(`../api.php?action=detalhes_pedido&id=${id}`);
        const data = await resp.json();

        if (!data.success) {
            container.innerHTML = '<p style="color:#c0392b;font-size:.85rem;padding:1rem">Erro ao carregar detalhes.</p>';
            return;
        }

        const p = data.pedido;

        // Itens
        let itensHtml = (data.itens || []).map(item => `
            <div class="item-mini">
                <div class="item-mini-img">
                    ${item.imagem ? `<img src="${item.imagem}" alt="${item.produto_nome}">` : ''}
                </div>
                <div class="item-mini-info">
                    <div class="item-mini-nome">${item.produto_nome || 'Produto'}</div>
                    ${item.variacao ? `<div class="item-mini-var">${item.variacao}</div>` : ''}
                    <div class="item-mini-var">${item.quantidade}x R$ ${item.preco_unitario}</div>
                </div>
                <div class="item-mini-preco">R$ ${item.subtotal}</div>
            </div>
        `).join('');

        // Histórico
        let histHtml = (data.historico || []).map(h => `
            <div class="tl-item">
                <div class="tl-data">${h.data_alteracao}</div>
                <div class="tl-status">${h.status_descricao}</div>
                ${h.observacao ? `<div class="tl-obs">${h.observacao}</div>` : ''}
            </div>
        `).join('');

        // Endereço
        const endHtml = p.endereco
            ? `${p.endereco.rua}, ${p.endereco.numero || 'S/N'}
               ${p.endereco.complemento ? '— ' + p.endereco.complemento : ''}<br>
               ${p.endereco.bairro} — ${p.endereco.cidade}/${p.endereco.estado}<br>
               CEP ${p.endereco.cep}`
            : '—';

        container.innerHTML = `
            <div class="detalhe-grid">
                <!-- COLUNA ESQUERDA: itens -->
                <div>
                    <div class="detalhe-secao-titulo">Produtos</div>
                    ${itensHtml || '<p style="font-size:.82rem;color:#888">Nenhum item encontrado.</p>'}

                    <div class="card-acoes" style="margin-top:1.2rem">
                        <a href="pedido_confirmado.php?id=${id}" class="btn-sm-acao btn-sm-outline">
                            🧾 Ver resumo completo
                        </a>
                        ${isPedidoPendente(id) ? `
                        <a href="#" onclick="retomarPagamento(${id}); return false;" class="btn-sm-acao btn-sm-filled">
                            💳 Retomar pagamento
                        </a>` : ''}
                    </div>
                </div>

                <!-- COLUNA DIREITA: info + histórico -->
                <div>
                    <div class="detalhe-secao-titulo" style="margin-top:0">Entrega</div>
                    <div class="detalhe-info-bloco">
                        <div class="detalhe-label">Endereço</div>
                        <div class="detalhe-valor">${endHtml}</div>
                    </div>
                    <div class="detalhe-info-bloco">
                        <div class="detalhe-label">Pagamento</div>
                        <div class="detalhe-valor">${p.status_pagamento || '—'}</div>
                    </div>

                    <div class="detalhe-secao-titulo" style="margin-top:1.2rem">Histórico</div>
                    <div class="timeline-mini">
                        ${histHtml || '<div class="tl-item"><div class="tl-status" style="color:#888">Sem histórico.</div></div>'}
                    </div>
                </div>
            </div>
        `;

    } catch(e) {
        container.innerHTML = '<p style="color:#c0392b;font-size:.85rem;padding:1rem">Erro de conexão.</p>';
    }
}

function isPedidoPendente(id) {
    // status_id 1 = aguardando pagamento
    return estadoLocal[id] === 1;
}

async function retomarPagamento(id) {
    alert('Para retomar o pagamento, acesse o link que foi enviado ao seu email ou entre em contato conosco.');
}

// ── Polling de status em tempo real ──────────────────────────
const POLL_INTERVAL = 12000;

async function pollStatus() {
    if (!TODOS_IDS.length) return;
    try {
        const resp = await fetch(`../api.php?action=polling_pedidos&ids=${TODOS_IDS.join(',')}`);
        const data = await resp.json();
        if (!data.success || !data.pedidos) return;

        data.pedidos.forEach(p => {
            const anterior = estadoLocal[p.id];
            if (anterior !== undefined && anterior !== p.status_pedido_id) {
                estadoLocal[p.id] = p.status_pedido_id;
                atualizarChip(p);
            }
        });
    } catch(e) {}
}

function atualizarChip(p) {
    const chip = document.querySelector(`.badge-status-${p.id}`);
    if (chip) {
        chip.style.background = p.status_cor || '#888';
        chip.textContent = `${p.status_icone || ''} ${p.status_descricao}`.trim();
    }
    // Atualizar data-status-id do card
    const card = document.getElementById(`card-${p.id}`);
    if (card) card.dataset.statusId = p.status_pedido_id;

    // Invalidar cache do detalhe para recarregar na próxima abertura
    detalhesCarregados[p.id] = false;

    // Flash suave no card
    if (card) {
        card.style.transition = 'box-shadow 0.3s, border-color 0.3s';
        card.style.borderColor = '#27ae60';
        card.style.boxShadow = '0 0 0 3px rgba(39,174,96,0.15)';
        setTimeout(() => {
            card.style.borderColor = '';
            card.style.boxShadow = '';
        }, 2000);
    }
}

setInterval(pollStatus, POLL_INTERVAL);
</script>

</body>
</html>