<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/database.php';
$db = getPDO();

$itemId    = $_GET['id']   ?? null;
$tipoParam = $_GET['tipo'] ?? null;

if (!$itemId) {
    header('Location: produtos.php');
    exit;
}

// =============================================
// DETECTAR SE É KIT OU PRODUTO
// =============================================
$tipoItem = 'produto';

if ($tipoParam === 'kit') {
    $tipoItem = 'kit';
} else {
    $checkKit = $db->prepare("
        SELECT id FROM composicoes
        WHERE id = :id AND tipo = 'venda' AND ativo = 1
    ");
    $checkKit->execute([':id' => $itemId]);
    if ($checkKit->fetch()) {
        $tipoItem = 'kit';
    }
}

// =============================================
// KIT
// =============================================
if ($tipoItem === 'kit') {
    $stmt = $db->prepare("
        SELECT
            comp.id,
            comp.nome,
            comp.descricao,
            comp.preco_venda AS preco,
            NULL            AS categoria_id,
            'Kit'           AS categoria_nome,
            'kit'           AS tipo,
            COALESCE((
                SELECT FLOOR(MIN(COALESCE(ea.quantidade, 0) / ci.quantidade))
                FROM composicoes_itens ci
                LEFT JOIN estoque_atual ea ON ea.id_produto_variacao = ci.produto_variacao_id
                WHERE ci.composicao_id = comp.id
                AND ci.produto_variacao_id IS NOT NULL
            ), 0) AS estoque_total
        FROM composicoes comp
        WHERE comp.id = :id AND comp.ativo = 1 AND comp.tipo = 'venda'
    ");
    $stmt->execute([':id' => $itemId]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        header('Location: produtos.php');
        exit;
    }

    // Imagens do kit (pega dos produtos do kit)
    $stmt = $db->prepare("
        SELECT DISTINCT pi.url, pi.ordem
        FROM composicoes_itens ci
        INNER JOIN produtos_imagens pi ON pi.produto_id = ci.produto_id
        WHERE ci.composicao_id = :id
        ORDER BY pi.ordem
        LIMIT 5
    ");
    $stmt->execute([':id' => $itemId]);
    $imagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Itens do kit
    $stmt = $db->prepare("
        SELECT
            ci.quantidade,
            p.nome AS produto_nome,
            GROUP_CONCAT(DISTINCT vv.valor ORDER BY vv.valor SEPARATOR ', ') AS variacao_texto,
            (SELECT url FROM produtos_imagens WHERE produto_id = ci.produto_id ORDER BY ordem LIMIT 1) AS imagem
        FROM composicoes_itens ci
        INNER JOIN produtos p ON p.id = ci.produto_id
        LEFT JOIN produto_variacao_valores pvv ON pvv.id_produto_variacao = ci.produto_variacao_id
        LEFT JOIN valores_variacao vv ON vv.id = pvv.id_valor_variacao
        WHERE ci.composicao_id = :id
        GROUP BY ci.id, ci.quantidade, p.nome, ci.produto_id
        ORDER BY p.nome
    ");
    $stmt->execute([':id' => $itemId]);
    $itensKit = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $variacoesOrganizadas = [];
    $combinacoes = [];

// =============================================
// PRODUTO NORMAL
// =============================================
} else {
    $stmt = $db->prepare("
        SELECT
            p.id,
            p.nome,
            p.descricao,
            p.preco,
            p.categoria_id,
            c.nome AS categoria_nome,
            'produto' AS tipo,
            COALESCE(SUM(ea.quantidade), 0) AS estoque_total
        FROM produtos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        LEFT JOIN produto_variacoes pv ON pv.id_produto = p.id
        LEFT JOIN estoque_atual ea ON ea.id_produto_variacao = pv.id
        WHERE p.id = :id AND p.ativo = 1
        GROUP BY p.id, c.nome
    ");
    $stmt->execute([':id' => $itemId]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        header('Location: produtos.php');
        exit;
    }

    // Imagens
    $stmt = $db->prepare("
        SELECT url, ordem
        FROM produtos_imagens
        WHERE produto_id = :id
        ORDER BY ordem
    ");
    $stmt->execute([':id' => $itemId]);
    $imagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Variações
    $stmt = $db->prepare("
        SELECT DISTINCT
            tv.id   AS tipo_id,
            tv.nome AS tipo_nome,
            tv.slug AS tipo_slug,
            vv.id   AS valor_id,
            vv.valor,
            vv.slug AS valor_slug
        FROM produto_variacoes pv
        INNER JOIN produto_variacao_valores pvv ON pvv.id_produto_variacao = pv.id
        INNER JOIN tipos_variacao   tv ON tv.id = pvv.id_tipo_variacao
        INNER JOIN valores_variacao vv ON vv.id = pvv.id_valor_variacao
        WHERE pv.id_produto = :id
        ORDER BY tv.id, vv.valor
    ");
    $stmt->execute([':id' => $itemId]);
    $variacoesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizar por tipo
    $variacoesOrganizadas = [];
    foreach ($variacoesRaw as $var) {
        if (!isset($variacoesOrganizadas[$var['tipo_nome']])) {
            $variacoesOrganizadas[$var['tipo_nome']] = [
                'tipo_id' => $var['tipo_id'],
                'valores' => []
            ];
        }
        $existe = false;
        foreach ($variacoesOrganizadas[$var['tipo_nome']]['valores'] as $v) {
            if ($v['valor_id'] == $var['valor_id']) { $existe = true; break; }
        }
        if (!$existe) {
            $variacoesOrganizadas[$var['tipo_nome']]['valores'][] = [
                'valor_id' => $var['valor_id'],
                'valor'    => $var['valor'],
                'slug'     => $var['valor_slug']
            ];
        }
    }

    // Combinações com estoque
    // IMPORTANTE: ORDER BY tv.id garante mesma ordem que o JS usa (sort numérico por tipo_id)
    $stmt = $db->prepare("
        SELECT
            pv.id AS variacao_id,
            COALESCE(ea.quantidade, 0) AS estoque,
            GROUP_CONCAT(pvv.id_valor_variacao ORDER BY tv.id SEPARATOR ',') AS valores_ids,
            GROUP_CONCAT(vv.valor          ORDER BY tv.id SEPARATOR ' / ')  AS variacao_texto
        FROM produto_variacoes pv
        INNER JOIN produto_variacao_valores pvv ON pvv.id_produto_variacao = pv.id
        INNER JOIN valores_variacao         vv  ON vv.id  = pvv.id_valor_variacao
        INNER JOIN tipos_variacao           tv  ON tv.id  = pvv.id_tipo_variacao
        LEFT JOIN  estoque_atual            ea  ON ea.id_produto_variacao = pv.id
        WHERE pv.id_produto = :id
        GROUP BY pv.id, ea.quantidade
    ");
    $stmt->execute([':id' => $itemId]);
    $combinacoesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Garante que valores_ids está sempre ordenado por tipo_id
    // (mesmo que o banco retorne outra ordem por algum motivo)
    $combinacoes = array_map(function($c) use ($db, $itemId) {
        // Re-busca os pares (tipo_id, valor_id) desta variação e ordena por tipo_id
        $stmt2 = $db->prepare("
            SELECT pvv.id_tipo_variacao, pvv.id_valor_variacao
            FROM produto_variacao_valores pvv
            WHERE pvv.id_produto_variacao = :var_id
            ORDER BY pvv.id_tipo_variacao ASC
        ");
        $stmt2->execute([':var_id' => $c['variacao_id']]);
        $pares = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $c['valores_ids'] = implode(',', array_column($pares, 'id_valor_variacao'));
        return $c;
    }, $combinacoesRaw);

    $itensKit = [];
}

$imagensJson    = json_encode($imagens);
$variacoesJson  = json_encode($variacoesOrganizadas);
$combinacoesJson = json_encode($combinacoes);
$isKit = ($tipoItem === 'kit');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produto['nome']) ?> - RS BEAUTY STORE</title>
    <?php include __DIR__ . '/partials/navbar.php'; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --black: #0a0a0a;
            --white: #fefefe;
            --gray-light: #f5f5f5;
            --gray-mid: #e0e0e0;
            --gray-dark: #666;
            --accent: #d4af37;
            --rose-gold: #E8B4B8;
            --deep-rose: #C67B88;
            --soft-pink: #FFF5F7;
            --luxury-purple: #9B7EBD;
            --champagne: #F7E7CE;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--white) 0%, var(--soft-pink) 50%, var(--white) 100%);
            color: var(--black);
            line-height: 1.6;
        }

        .icon { width: 22px; height: 22px; stroke: currentColor; fill: none; stroke-width: 2; }

        /* ===== BREADCRUMB ===== */
        .breadcrumb {
            margin-top: 1.5rem;
            padding: 1rem 2rem;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        .breadcrumb a { color: var(--gray-dark); text-decoration: none; font-size: 0.9rem; transition: color 0.3s; }
        .breadcrumb a:hover { color: var(--deep-rose); }
        .breadcrumb span { margin: 0 0.5rem; color: var(--gray-mid); }

        /* ===== MAIN LAYOUT ===== */
        .product-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem 4rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
        }

        /* ===== GALLERY ===== */
        .product-gallery { position: sticky; top: 120px; height: fit-content; }

        .main-image {
            width: 100%; height: 600px;
            background: linear-gradient(135deg, var(--soft-pink), var(--champagne));
            border-radius: 20px; overflow: hidden; position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .main-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .main-image:hover img { transform: scale(1.05); }

        .main-image-placeholder {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
        }

        .main-image-placeholder svg { width: 150px; height: 150px; opacity: 0.2; }

        .thumbnail-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 1rem;
        }

        .thumbnail {
            width: 100%; height: 100px;
            background: var(--gray-light);
            border-radius: 12px; overflow: hidden;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s;
        }

        .thumbnail:hover { border-color: var(--rose-gold); transform: translateY(-3px); }
        .thumbnail.active { border-color: var(--deep-rose); box-shadow: 0 4px 15px rgba(198,123,136,0.3); }
        .thumbnail img { width: 100%; height: 100%; object-fit: cover; }

        /* ===== PRODUCT INFO ===== */
        .product-info { animation: fadeIn 0.6s ease-out; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .product-category-badge {
            display: inline-block;
            font-size: 0.75rem; letter-spacing: 2px; text-transform: uppercase;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700; margin-bottom: 1rem;
        }

        .product-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3rem; font-weight: 600; letter-spacing: 1px;
            margin-bottom: 1.5rem; line-height: 1.2;
            background: linear-gradient(135deg, var(--black) 0%, var(--deep-rose) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .product-rating {
            display: flex; align-items: center; gap: 0.5rem;
            margin-bottom: 2rem; padding-bottom: 2rem;
            border-bottom: 1px solid var(--gray-mid);
        }

        .stars { color: var(--accent); font-size: 1.2rem; }
        .reviews { color: var(--gray-dark); font-size: 0.9rem; }

        .product-price {
            font-size: 3rem; font-weight: 700;
            background: linear-gradient(135deg, var(--deep-rose) 0%, var(--luxury-purple) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 2rem;
        }

        .product-description { color: var(--gray-dark); font-size: 1rem; line-height: 1.8; margin-bottom: 2.5rem; }

        /* ===== KIT ITEMS ===== */
        .kit-items-section {
            margin-bottom: 2.5rem; padding: 1.5rem;
            background: linear-gradient(135deg, var(--soft-pink), rgba(232,180,184,0.1));
            border-radius: 15px;
            border: 1px solid rgba(232,180,184,0.3);
        }

        .kit-items-list { display: grid; gap: 1rem; margin-top: 1rem; }

        .kit-item {
            display: flex; align-items: center; gap: 1rem;
            padding: 1rem; background: var(--white);
            border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .kit-item-image { width: 60px; height: 60px; border-radius: 8px; overflow: hidden; flex-shrink: 0; background: var(--gray-light); }
        .kit-item-image img { width: 100%; height: 100%; object-fit: cover; }
        .kit-item-info { flex: 1; display: flex; align-items: center; gap: 0.8rem; }
        .kit-item-quantity { font-weight: 700; font-size: 1.1rem; color: var(--deep-rose); min-width: 35px; }
        .kit-item-details { flex: 1; }
        .kit-item-name { font-size: 0.95rem; color: var(--black); font-weight: 600; margin-bottom: 0.2rem; }
        .kit-item-variation { font-size: 0.8rem; color: var(--gray-dark); font-style: italic; }

        /* ===== VARIATIONS ===== */
        .variation-section { margin-bottom: 2rem; }

        .variation-label {
            font-size: 0.9rem; font-weight: 600; letter-spacing: 1px;
            text-transform: uppercase; margin-bottom: 1rem; color: var(--black);
            display: flex; align-items: center; gap: 0.5rem;
        }

        .variation-options { display: flex; flex-wrap: wrap; gap: 0.8rem; }

        .variation-option {
            padding: 0.8rem 1.5rem;
            border: 2px solid var(--gray-mid);
            border-radius: 12px; cursor: pointer;
            transition: all 0.3s; font-size: 0.9rem; font-weight: 500;
            background: var(--white);
        }

        .variation-option:hover {
            border-color: var(--rose-gold); transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(232,180,184,0.2);
        }

        .variation-option.active {
            border-color: var(--deep-rose);
            background: linear-gradient(135deg, var(--soft-pink), rgba(232,180,184,0.3));
            font-weight: 700;
        }

        .variation-option.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }

        /* ===== QUANTITY ===== */
        .quantity-section { margin-bottom: 2rem; }

        .quantity-selector { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }

        .quantity-btn {
            width: 40px; height: 40px;
            border: 2px solid var(--gray-mid);
            background: var(--white); border-radius: 50%;
            cursor: pointer; transition: all 0.3s;
            font-size: 1.2rem; font-weight: 700;
        }

        .quantity-btn:hover { border-color: var(--deep-rose); background: var(--soft-pink); transform: scale(1.1); }

        .quantity-input {
            width: 80px; height: 40px; text-align: center;
            border: 2px solid var(--gray-mid); border-radius: 12px;
            font-size: 1.1rem; font-weight: 600;
        }

        .stock-info { font-size: 0.85rem; color: var(--gray-dark); }
        .stock-info.low { color: #ff9800; font-weight: 600; }
        .stock-info.out { color: #f44336; font-weight: 600; }

        /* ===== ACTION BUTTONS ===== */
        .action-buttons { display: flex; gap: 1rem; margin-bottom: 2rem; }

        .btn-add-cart {
            flex: 1; padding: 1.2rem;
            background: linear-gradient(135deg, var(--black) 0%, var(--deep-rose) 100%);
            color: var(--white); border: none; border-radius: 12px;
            font-size: 1rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
            cursor: pointer; transition: all 0.4s;
            box-shadow: 0 8px 25px rgba(198,123,136,0.3);
            position: relative; overflow: hidden;
        }

        .btn-add-cart::before {
            content: ''; position: absolute; top: 50%; left: 50%;
            width: 0; height: 0; border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-add-cart:hover:not(:disabled)::before { width: 300px; height: 300px; }

        .btn-add-cart:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--deep-rose) 0%, var(--luxury-purple) 100%);
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(155,126,189,0.4);
        }

        .btn-add-cart:disabled { background: var(--gray-mid); cursor: not-allowed; opacity: 0.6; }

        .btn-favorite {
            width: 60px; height: 60px;
            border: 2px solid var(--gray-mid); background: var(--white);
            border-radius: 12px; cursor: pointer; transition: all 0.3s;
        }

        .btn-favorite:hover { border-color: var(--deep-rose); background: var(--soft-pink); }

        /* ===== PRODUCT DETAILS ===== */
        .product-details {
            background: linear-gradient(135deg, var(--white), var(--soft-pink));
            padding: 2rem; border-radius: 20px; margin-top: 2rem;
        }

        .detail-item { display: flex; justify-content: space-between; padding: 1rem 0; border-bottom: 1px solid var(--gray-mid); }
        .detail-item:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: var(--black); }
        .detail-value { color: var(--gray-dark); }

        /* ===== TOAST ===== */
        .toast {
            position: fixed; bottom: 2rem; right: 2rem;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white; padding: 1rem 1.5rem;
            border-radius: 12px; font-weight: 600;
            transform: translateY(100px); opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 9999; box-shadow: 0 8px 25px rgba(76,175,80,0.4);
        }

        .toast.show { transform: translateY(0); opacity: 1; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 968px) {
            .product-container { grid-template-columns: 1fr; gap: 2rem; }
            .product-gallery { position: static; }
            .main-image { height: 400px; }
            .product-title { font-size: 2rem; }
            .product-price { font-size: 2rem; }
        }
    </style>
</head>
<body>

    <div class="breadcrumb">
        <a href="index.php">Início</a>
        <span>›</span>
        <a href="produtos.php">Produtos</a>
        <?php if (!empty($produto['categoria_nome']) && $produto['categoria_nome'] !== 'Kit'): ?>
            <span>›</span>
            <a href="produtos.php?categoria=<?= $produto['categoria_id'] ?>">
                <?= htmlspecialchars($produto['categoria_nome']) ?>
            </a>
        <?php endif; ?>
        <span>›</span>
        <span><?= htmlspecialchars($produto['nome']) ?></span>
    </div>

    <div class="product-container">

        <!-- ===== GALLERY ===== -->
        <div class="product-gallery">
            <div class="main-image" id="mainImage">
                <?php if (!empty($imagens)): ?>
                    <img src="<?= htmlspecialchars($imagens[0]['url']) ?>"
                         alt="<?= htmlspecialchars($produto['nome']) ?>"
                         id="mainImg">
                <?php else: ?>
                    <div class="main-image-placeholder">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <path d="M21 15l-5-5L5 21"/>
                        </svg>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($imagens) > 1): ?>
                <div class="thumbnail-container">
                    <?php foreach ($imagens as $index => $img): ?>
                        <div class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                             onclick="changeImage('<?= htmlspecialchars($img['url']) ?>', this)">
                            <img src="<?= htmlspecialchars($img['url']) ?>"
                                 alt="<?= htmlspecialchars($produto['nome']) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== PRODUCT INFO ===== -->
        <div class="product-info">

            <?php if (!empty($produto['categoria_nome'])): ?>
                <div class="product-category-badge"><?= htmlspecialchars($produto['categoria_nome']) ?></div>
            <?php endif; ?>

            <h1 class="product-title"><?= htmlspecialchars($produto['nome']) ?></h1>

            <div class="product-rating">
                <div class="stars">★★★★★</div>
                <span class="reviews">(4.8) • 5 mil avaliações</span>
            </div>

            <div class="product-price">R$ <?= number_format($produto['preco'], 2, ',', '.') ?></div>

            <?php if (!empty($produto['descricao'])): ?>
                <div class="product-description">
                    <?= nl2br(htmlspecialchars($produto['descricao'])) ?>
                </div>
            <?php endif; ?>

            <!-- KIT ITEMS -->
            <?php if ($isKit && !empty($itensKit)): ?>
                <div class="kit-items-section">
                    <div class="variation-label">
                        <svg class="icon" viewBox="0 0 24 24" style="width:16px;height:16px;">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        </svg>
                        Este kit contém:
                    </div>
                    <div class="kit-items-list">
                        <?php foreach ($itensKit as $kit): ?>
                            <div class="kit-item">
                                <?php if ($kit['imagem']): ?>
                                    <div class="kit-item-image">
                                        <img src="<?= htmlspecialchars($kit['imagem']) ?>"
                                             alt="<?= htmlspecialchars($kit['produto_nome']) ?>">
                                    </div>
                                <?php endif; ?>
                                <div class="kit-item-info">
                                    <div class="kit-item-quantity"><?= $kit['quantidade'] ?>x</div>
                                    <div class="kit-item-details">
                                        <div class="kit-item-name"><?= htmlspecialchars($kit['produto_nome']) ?></div>
                                        <?php if (!empty($kit['variacao_texto'])): ?>
                                            <div class="kit-item-variation">
                                                <?= htmlspecialchars($kit['variacao_texto']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- VARIATIONS (apenas produto normal) -->
            <?php if (!$isKit): ?>
                <div id="variations-container">
                    <?php foreach ($variacoesOrganizadas as $tipoNome => $tipoData): ?>
                        <div class="variation-section">
                            <div class="variation-label"><?= htmlspecialchars($tipoNome) ?></div>
                            <div class="variation-options">
                                <?php foreach ($tipoData['valores'] as $valor): ?>
                                    <div class="variation-option"
                                         data-tipo-id="<?= $tipoData['tipo_id'] ?>"
                                         data-tipo-nome="<?= htmlspecialchars($tipoNome) ?>"
                                         data-valor-id="<?= $valor['valor_id'] ?>"
                                         onclick="selectVariation(this)">
                                        <?= htmlspecialchars($valor['valor']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- QUANTITY -->
            <div class="quantity-section">
                <div class="variation-label">Quantidade</div>
                <div class="quantity-selector">
                    <button class="quantity-btn" onclick="changeQuantity(-1)">−</button>
                    <input type="number" id="quantity" class="quantity-input" value="1" min="1" readonly>
                    <button class="quantity-btn" onclick="changeQuantity(1)">+</button>
                </div>
                <div class="stock-info" id="stockInfo">
                    Estoque: <span id="stockAmount"><?= $produto['estoque_total'] ?></span> unidades disponíveis
                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="action-buttons">
                <button class="btn-add-cart" id="addToCartBtn" onclick="adicionarAoCarrinho()">
                    Adicionar ao Carrinho
                </button>
                <button class="btn-favorite" title="Favoritar">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </button>
            </div>

            <!-- PRODUCT DETAILS -->
            <div class="product-details">
                <div class="detail-item">
                    <span class="detail-label">Tipo</span>
                    <span class="detail-value"><?= $isKit ? 'Kit' : 'Produto' ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Categoria</span>
                    <span class="detail-value"><?= htmlspecialchars($produto['categoria_nome'] ?? 'Geral') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Disponibilidade</span>
                    <span class="detail-value" style="color: <?= $produto['estoque_total'] > 0 ? '#27ae60' : '#e74c3c' ?>; font-weight: 600;">
                        <?= $produto['estoque_total'] > 0 ? 'Em Estoque' : 'Esgotado' ?>
                    </span>
                </div>
            </div>

        </div>
    </div>

    <!-- TOAST -->
    <div class="toast" id="toast">✓ Adicionado ao carrinho!</div>

    <script>
    const combinacoes          = <?= $combinacoesJson ?>;
    const itemId               = <?= (int)$itemId ?>;
    const precoBase            = <?= (float)$produto['preco'] ?>;
    const isKit                = <?= $isKit ? 'true' : 'false' ?>;
    const totalTiposVariacao   = <?= count($variacoesOrganizadas) ?>;

    let selectedVariations = {};
    let currentStock       = <?= (int)$produto['estoque_total'] ?>;

    // Estado inicial do botão
    if (!isKit && totalTiposVariacao > 0) {
        document.getElementById('addToCartBtn').disabled = true;
    }

    // ===== GALERIA =====
    function changeImage(url, el) {
        const img = document.getElementById('mainImg');
        if (img) img.src = url;
        document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
    }

    // ===== VARIAÇÕES =====
    function selectVariation(el) {
        const tipoId  = el.dataset.tipoId;
        const valorId = el.dataset.valorId;

        el.parentElement.querySelectorAll('.variation-option').forEach(s => s.classList.remove('active'));
        el.classList.add('active');
        selectedVariations[tipoId] = valorId;

        updateStock();
    }

    function updateStock() {
        if (Object.keys(selectedVariations).length < totalTiposVariacao) {
            document.getElementById('addToCartBtn').disabled = true;
            return;
        }

        // Monta os pares [tipo_id, valor_id] e ordena por tipo_id numérico
        const tipoIds = Object.keys(selectedVariations).map(Number).sort((a,b) => a-b);
        const selectedIds = tipoIds.map(k => selectedVariations[k]).join(',');

        // O banco já entrega valores_ids ordenado por tv.id (tipo_id)
        // então a comparação direta funciona
        const match = combinacoes.find(c => c.valores_ids === selectedIds);

        const stockInfo = document.getElementById('stockInfo');

        if (match) {
            currentStock = parseInt(match.estoque) || 0;
            stockInfo.classList.remove('low', 'out');

            if (currentStock === 0) {
                stockInfo.classList.add('out');
                stockInfo.innerHTML = '<span style="color:#f44336">✕ Produto Esgotado</span>';
                document.getElementById('addToCartBtn').disabled = true;
            } else if (currentStock <= 10) {
                stockInfo.classList.add('low');
                stockInfo.innerHTML = `<span style="color:#ff9800">⚠ Últimas ${currentStock} unidades</span>`;
                document.getElementById('addToCartBtn').disabled = false;
            } else {
                stockInfo.innerHTML = `Estoque: <span>${currentStock}</span> unidades disponíveis`;
                document.getElementById('addToCartBtn').disabled = false;
            }

            const qty = document.getElementById('quantity');
            if (parseInt(qty.value) > currentStock) qty.value = Math.max(1, currentStock);
        } else {
            stockInfo.innerHTML = '<span style="color:#f44336">✕ Combinação não disponível</span>';
            document.getElementById('addToCartBtn').disabled = true;
        }
    }

    // ===== QUANTIDADE =====
    function changeQuantity(delta) {
        const input = document.getElementById('quantity');
        let val = parseInt(input.value) + delta;
        if (val < 1) val = 1;
        if (val > currentStock && currentStock > 0) val = currentStock;
        input.value = val;
    }

    // ===== CARRINHO =====
    function adicionarAoCarrinho() {
        const quantidade = parseInt(document.getElementById('quantity').value);

        if (!isKit && Object.keys(selectedVariations).length < totalTiposVariacao) {
            alert('Por favor, selecione todas as opções do produto');
            return;
        }

        const variacoesTexto = [];
        if (!isKit) {
            document.querySelectorAll('.variation-option.active').forEach(el => {
                variacoesTexto.push(`${el.dataset.tipoNome}: ${el.textContent.trim()}`);
            });
        }

        const item = {
            id:           itemId,
            nome:         '<?= addslashes(htmlspecialchars($produto['nome'])) ?>',
            preco:        precoBase,
            quantidade:   quantidade,
            imagem:       '<?= !empty($imagens) ? addslashes($imagens[0]['url']) : '' ?>',
            is_kit:       isKit,
            variacoes:    isKit ? {} : selectedVariations,
            variacoesTexto: isKit
                ? 'Kit com <?= count($itensKit ?? []) ?> itens'
                : variacoesTexto.join(' | ')
        };

        if (isKit) item.composicao_id = itemId;

        let carrinho = JSON.parse(localStorage.getItem('carrinho') || '[]');
        carrinho.push(item);
        localStorage.setItem('carrinho', JSON.stringify(carrinho));

        atualizarBadge();
        mostrarToast();
    }

    function mostrarToast() {
        const toast = document.getElementById('toast');
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2500);
    }

    function atualizarBadge() {
        if (typeof rsUpdateCartBadge === 'function') rsUpdateCartBadge();
    }

    // Init
    atualizarBadge();
    </script>

    <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>