<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$db = getPDO();

// Filtros
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroBusca     = $_GET['busca'] ?? '';
$filtroOrdenacao = $_GET['ordenacao'] ?? 'nome_asc';

// Categorias
$categorias = $db->query("
    SELECT c.id, c.nome, COUNT(p.id) AS total_produtos
    FROM categorias c
    LEFT JOIN produtos p
        ON p.categoria_id = c.id AND p.ativo = 1
    GROUP BY c.id, c.nome
    ORDER BY c.nome
")->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// PRODUTOS NORMAIS
// ==========================
$sqlProdutos = "
    SELECT DISTINCT
        p.id,
        p.nome,
        p.descricao,
        p.preco,
        p.categoria_id,
        c.nome AS categoria_nome,
        (
            SELECT url
            FROM produtos_imagens
            WHERE produto_id = p.id
            ORDER BY ordem
            LIMIT 1
        ) AS imagem,
        COALESCE(SUM(ea.quantidade), 0) AS estoque_total,
        (
            SELECT COUNT(DISTINCT pv.id)
            FROM produto_variacoes pv
            WHERE pv.id_produto = p.id
        ) AS total_variacoes,
        'produto' AS tipo_item,
        NULL AS composicao_id
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    LEFT JOIN produto_variacoes pv ON pv.id_produto = p.id
    LEFT JOIN estoque_atual ea ON ea.id_produto_variacao = pv.id
    WHERE p.ativo = 1
";

$params = [];

if ($filtroCategoria) {
    $sqlProdutos .= " AND p.categoria_id = :categoria";
    $params['categoria'] = $filtroCategoria;
}

if ($filtroBusca) {
    $sqlProdutos .= " AND (p.nome LIKE :busca OR p.descricao LIKE :busca)";
    $params['busca'] = '%' . $filtroBusca . '%';
}

$sqlProdutos .= " GROUP BY p.id, p.nome, p.descricao, p.preco, p.categoria_id, c.nome";

// ==========================
// COMPOSIÇÕES / KITS DE VENDA
// ==========================
$sqlKits = "
    SELECT
        comp.id,
        comp.nome,
        comp.descricao,
        comp.preco_venda AS preco,
        NULL AS categoria_id,
        'Kit' AS categoria_nome,
        (
            SELECT pi.url
            FROM composicoes_itens ci
            INNER JOIN produtos_imagens pi ON pi.produto_id = ci.produto_id
            WHERE ci.composicao_id = comp.id
            ORDER BY pi.ordem
            LIMIT 1
        ) AS imagem,
        COALESCE((
            SELECT FLOOR(MIN(COALESCE(ea.quantidade, 0) / ci.quantidade))
            FROM composicoes_itens ci
            LEFT JOIN estoque_atual ea ON ea.id_produto_variacao = ci.produto_variacao_id
            WHERE ci.composicao_id = comp.id
            AND ci.produto_variacao_id IS NOT NULL
        ), 0) AS estoque_total,
        (
            SELECT COUNT(DISTINCT ci.produto_id)
            FROM composicoes_itens ci
            WHERE ci.composicao_id = comp.id
        ) AS total_variacoes,
        'kit' AS tipo_item,
        comp.id AS composicao_id
    FROM composicoes comp
    WHERE comp.ativo = 1
    AND comp.tipo = 'venda'
    AND comp.preco_venda IS NOT NULL
";

if ($filtroBusca) {
    $sqlKits .= " AND (comp.nome LIKE :busca OR comp.descricao LIKE :busca)";
}

$sqlKits .= " GROUP BY comp.id, comp.nome, comp.descricao, comp.preco_venda";

// ==========================
// UNION + ORDENAÇÃO
// ==========================
$sqlFinal = "SELECT * FROM (" . $sqlProdutos . " UNION ALL " . $sqlKits . ") AS todos_itens";

switch ($filtroOrdenacao) {
    case 'preco_asc':
        $sqlFinal .= " ORDER BY preco ASC";
        break;
    case 'preco_desc':
        $sqlFinal .= " ORDER BY preco DESC";
        break;
    case 'nome_desc':
        $sqlFinal .= " ORDER BY nome DESC";
        break;
    default:
        $sqlFinal .= " ORDER BY nome ASC";
}

$stmt = $db->prepare($sqlFinal);
$stmt->execute($params);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalProdutos = count($produtos);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - RS BEAUTY STORE</title>
    <?php include __DIR__ . '/partials/navbar.php'; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(232, 180, 184, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(155, 126, 189, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(247, 231, 206, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
            animation: floatingBg 20s ease-in-out infinite;
        }

        @keyframes floatingBg {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.1); }
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--rose-gold), var(--luxury-purple), var(--accent));
            z-index: 10000;
            width: var(--scroll-progress, 0%);
            transition: width 0.1s ease-out;
            box-shadow: 0 0 10px rgba(232, 180, 184, 0.5);
        }

        .icon {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .page-header {
            margin-top: 0;
            background: linear-gradient(135deg, #0a0a0a 0%, var(--deep-rose) 50%, var(--luxury-purple) 100%);
            padding: 5rem 2rem 4rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(232, 180, 184, 0.2) 0%, transparent 50%);
            animation: floatPattern 20s ease-in-out infinite;
        }

        @keyframes floatPattern {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(20px, -20px) scale(1.1); }
        }

        .page-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 4rem;
            font-weight: 300;
            letter-spacing: 3px;
            margin-bottom: 1rem;
            color: var(--white);
            position: relative;
            z-index: 2;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            letter-spacing: 2px;
            position: relative;
            z-index: 2;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 3rem;
        }

        .filters-sidebar {
            position: sticky;
            top: 120px;
            height: fit-content;
            background: linear-gradient(135deg, var(--white) 0%, var(--soft-pink) 100%);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(198, 123, 136, 0.1);
            border: 1px solid rgba(232, 180, 184, 0.2);
        }

        .filter-section { margin-bottom: 2.5rem; }

        .filter-section h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .search-box {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .search-box input {
            width: 100%;
            padding: 1rem 2.5rem 1rem 1.2rem;
            border: 2px solid transparent;
            background: var(--white);
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--rose-gold);
            box-shadow: 0 8px 25px rgba(232, 180, 184, 0.2);
            transform: translateY(-2px);
        }

        .search-box button {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
        }

        .category-list { list-style: none; }

        .category-item { margin-bottom: 0.8rem; }

        .category-item a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 1rem;
            color: var(--black);
            text-decoration: none;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }

        .category-item a::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(232, 180, 184, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .category-item a:hover::before { left: 100%; }

        .category-item a:hover,
        .category-item a.active {
            background: linear-gradient(135deg, var(--soft-pink), rgba(232, 180, 184, 0.3));
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(198, 123, 136, 0.15);
        }

        .category-item a.active {
            font-weight: 600;
            border-left: 3px solid var(--deep-rose);
        }

        .category-count { color: var(--gray-dark); font-size: 0.85rem; }

        .sort-select {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid transparent;
            background: var(--white);
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23C67B88' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        .sort-select:focus {
            outline: none;
            border-color: var(--rose-gold);
            box-shadow: 0 8px 25px rgba(232, 180, 184, 0.2);
            transform: translateY(-2px);
        }

        .sort-select:hover { border-color: var(--rose-gold); }

        .products-content { min-height: 400px; }

        .products-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-mid);
        }

        .results-count {
            font-size: 0.9rem;
            color: var(--gray-dark);
            letter-spacing: 0.5px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2.5rem;
        }

        .product-card {
            background: var(--white);
            border-radius: 20px;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            overflow: hidden;
            position: relative;
            opacity: 0;
            animation: fadeIn 0.6s ease-out forwards;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .product-card:nth-child(1) { animation-delay: 0.1s; }
        .product-card:nth-child(2) { animation-delay: 0.15s; }
        .product-card:nth-child(3) { animation-delay: 0.2s; }
        .product-card:nth-child(4) { animation-delay: 0.25s; }
        .product-card:nth-child(5) { animation-delay: 0.3s; }
        .product-card:nth-child(6) { animation-delay: 0.35s; }
        .product-card:nth-child(7) { animation-delay: 0.4s; }
        .product-card:nth-child(8) { animation-delay: 0.45s; }
        .product-card:nth-child(9) { animation-delay: 0.5s; }

        .product-card::before {
            content: '';
            position: absolute;
            top: -2px; left: -2px; right: -2px; bottom: -2px;
            background: linear-gradient(135deg, var(--rose-gold), var(--luxury-purple), var(--accent));
            border-radius: 20px;
            opacity: 0;
            transition: opacity 0.5s ease;
            z-index: -1;
        }

        .product-card:hover::before { opacity: 1; }

        .product-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--white);
            border-radius: 18px;
            z-index: 0;
        }

        .product-card > * { position: relative; z-index: 1; }

        .product-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 20px 60px rgba(198, 123, 136, 0.25), 0 0 0 1px rgba(232, 180, 184, 0.3);
        }

        .product-image-container {
            width: 100%;
            height: 350px;
            background: linear-gradient(135deg, var(--soft-pink) 0%, var(--champagne) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            border-radius: 20px 20px 0 0;
        }

        .product-image-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(circle at 30% 30%, rgba(232, 180, 184, 0.2), transparent 70%),
                radial-gradient(circle at 70% 70%, rgba(155, 126, 189, 0.15), transparent 70%);
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .product-card:hover .product-image-container::before { opacity: 1; }

        .product-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
            filter: brightness(1) contrast(1.05);
        }

        .product-card:hover .product-image-container img {
            transform: scale(1.15) rotate(2deg);
            filter: brightness(1.1) contrast(1.1);
        }

        .product-placeholder { width: 80px; height: 80px; opacity: 0.3; }

        .product-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, var(--accent) 0%, #f4d03f 100%);
            color: var(--black);
            padding: 0.5rem 1rem;
            font-size: 0.7rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 700;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 6px 25px rgba(212, 175, 55, 0.6); }
        }

        .stock-badge {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            font-weight: 600;
        }

        .stock-badge.low {
            background: linear-gradient(135deg, rgba(255, 200, 0, 0.95), rgba(255, 170, 0, 0.95));
            color: var(--black);
            box-shadow: 0 4px 15px rgba(255, 170, 0, 0.3);
        }

        .stock-badge.out {
            background: linear-gradient(135deg, rgba(200, 0, 0, 0.95), rgba(150, 0, 0, 0.95));
            color: var(--white);
            box-shadow: 0 4px 15px rgba(200, 0, 0, 0.3);
        }

        .product-info { padding: 1.5rem; }

        .product-category {
            font-size: 0.7rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .product-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
            color: var(--black);
            background: linear-gradient(90deg, var(--black) 0%, var(--deep-rose) 50%, var(--black) 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: background-position 0.5s ease;
        }

        .product-card:hover .product-name { background-position: -100% center; }

        .product-description {
            font-size: 0.85rem;
            color: var(--gray-dark);
            margin-bottom: 1rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--black) 0%, var(--deep-rose) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            display: inline-block;
        }

        .product-price::after {
            content: '';
            position: absolute;
            bottom: -4px; left: 0;
            width: 0; height: 2px;
            background: linear-gradient(90deg, var(--rose-gold), var(--accent));
            transition: width 0.4s ease;
        }

        .product-card:hover .product-price::after { width: 100%; }

        .product-variations {
            font-size: 0.8rem;
            color: var(--gray-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .add-to-cart-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--black) 0%, var(--deep-rose) 100%);
            color: var(--white);
            border: none;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 0 0 20px 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(198, 123, 136, 0.3);
        }

        .add-to-cart-btn::before {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            width: 0; height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .add-to-cart-btn:hover:not(:disabled)::before { width: 300px; height: 300px; }

        .add-to-cart-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--deep-rose) 0%, var(--luxury-purple) 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(155, 126, 189, 0.4);
        }

        .add-to-cart-btn:active:not(:disabled) { transform: translateY(0); }

        .add-to-cart-btn:disabled {
            background: linear-gradient(135deg, var(--gray-dark), var(--gray-mid));
            opacity: 0.6;
            cursor: not-allowed;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 5rem 2rem;
            background: linear-gradient(135deg, var(--soft-pink), rgba(255, 255, 255, 0.5));
            border-radius: 20px;
            animation: fadeIn 0.6s ease-out;
        }

        .empty-state svg {
            width: 100px; height: 100px;
            margin-bottom: 2rem;
            opacity: 0.3;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        .empty-state h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            margin-bottom: 0.8rem;
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .empty-state p { color: var(--gray-dark); font-size: 1rem; }

        @media (max-width: 968px) {
            .main-container { grid-template-columns: 1fr; }

            .filters-sidebar {
                position: static;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1.5rem;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 1.5rem;
            }

            .page-header h1 { font-size: 2.5rem; }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1>Nossa Coleção</h1>
        <p>Descubra produtos premium de beleza</p>
    </div>

    <div class="main-container">
        <aside class="filters-sidebar">
            <div class="filter-section">
                <h3>
                    <svg class="icon" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>
                    Buscar
                </h3>
                <form method="GET" class="search-box">
                    <input type="hidden" name="categoria" value="<?= htmlspecialchars($filtroCategoria) ?>">
                    <input type="hidden" name="ordenacao" value="<?= htmlspecialchars($filtroOrdenacao) ?>">
                    <input
                        type="text"
                        name="busca"
                        placeholder="Nome do produto..."
                        value="<?= htmlspecialchars($filtroBusca) ?>"
                    >
                    <button type="submit">
                        <svg class="icon" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21l-4.35-4.35"/>
                        </svg>
                    </button>
                </form>
            </div>

            <div class="filter-section">
                <h3>
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    Categorias
                </h3>
                <ul class="category-list">
                    <li class="category-item">
                        <a href="?busca=<?= urlencode($filtroBusca) ?>&ordenacao=<?= urlencode($filtroOrdenacao) ?>"
                           class="<?= empty($filtroCategoria) ? 'active' : '' ?>">
                            <span>Todas</span>
                            <span class="category-count"><?= $totalProdutos ?></span>
                        </a>
                    </li>
                    <?php foreach ($categorias as $cat): ?>
                        <li class="category-item">
                            <a href="?categoria=<?= $cat['id'] ?>&busca=<?= urlencode($filtroBusca) ?>&ordenacao=<?= urlencode($filtroOrdenacao) ?>"
                               class="<?= $filtroCategoria == $cat['id'] ? 'active' : '' ?>">
                                <span><?= htmlspecialchars($cat['nome']) ?></span>
                                <span class="category-count"><?= $cat['total_produtos'] ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="filter-section">
                <h3>
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    Ordenar
                </h3>
                <form method="GET">
                    <input type="hidden" name="categoria" value="<?= htmlspecialchars($filtroCategoria) ?>">
                    <input type="hidden" name="busca" value="<?= htmlspecialchars($filtroBusca) ?>">
                    <select name="ordenacao" class="sort-select" onchange="this.form.submit()">
                        <option value="nome_asc"  <?= $filtroOrdenacao === 'nome_asc'   ? 'selected' : '' ?>>Nome (A-Z)</option>
                        <option value="nome_desc" <?= $filtroOrdenacao === 'nome_desc'  ? 'selected' : '' ?>>Nome (Z-A)</option>
                        <option value="preco_asc" <?= $filtroOrdenacao === 'preco_asc'  ? 'selected' : '' ?>>Menor Preço</option>
                        <option value="preco_desc"<?= $filtroOrdenacao === 'preco_desc' ? 'selected' : '' ?>>Maior Preço</option>
                    </select>
                </form>
            </div>
        </aside>

        <div class="products-content">
            <div class="products-header-bar">
                <span class="results-count">
                    <?= $totalProdutos ?> <?= $totalProdutos === 1 ? 'produto encontrado' : 'produtos encontrados' ?>
                </span>
            </div>

            <div class="products-grid">
                <?php if (empty($produtos)): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M16 16s-1.5-2-4-2-4 2-4 2"/>
                            <path d="M9 9h.01M15 9h.01"/>
                        </svg>
                        <h3>Nenhum produto encontrado</h3>
                        <p>Tente ajustar seus filtros de busca</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($produtos as $produto): ?>
                        <?php $estoque = (int)$produto['estoque_total']; ?>
                        <div class="product-card" onclick="verProduto(<?= $produto['id'] ?>, '<?= $produto['tipo_item'] ?>')">
                            <div class="product-image-container">
                                <?php if ($produto['imagem']): ?>
                                    <img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>">
                                <?php else: ?>
                                    <svg class="product-placeholder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <path d="M21 15l-5-5L5 21"/>
                                    </svg>
                                <?php endif; ?>

                                <?php if ($produto['tipo_item'] === 'kit'): ?>
                                    <span class="product-badge" style="left: 20px; right: auto; background: linear-gradient(135deg, var(--luxury-purple), var(--deep-rose));">
                                        <svg class="icon" viewBox="0 0 24 24" style="width: 14px; height: 14px; stroke: white;">
                                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                        </svg>
                                        KIT
                                    </span>
                                <?php endif; ?>

                                <?php if ($estoque === 0): ?>
                                    <span class="stock-badge out">
                                        <svg class="icon" viewBox="0 0 24 24" style="width: 14px; height: 14px;">
                                            <circle cx="12" cy="12" r="10"/>
                                            <path d="M15 9l-6 6M9 9l6 6"/>
                                        </svg>
                                        Esgotado
                                    </span>
                                <?php elseif ($estoque <= 10): ?>
                                    <span class="stock-badge low">
                                        <svg class="icon" viewBox="0 0 24 24" style="width: 14px; height: 14px;">
                                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                            <path d="M12 9v4M12 17h.01"/>
                                        </svg>
                                        Últimas unidades
                                    </span>
                                <?php else: ?>
                                    <span class="stock-badge">
                                        <svg class="icon" viewBox="0 0 24 24" style="width: 14px; height: 14px;">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                            <path d="M22 4L12 14.01l-3-3"/>
                                        </svg>
                                        Em estoque
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="product-info">
                                <?php if ($produto['categoria_nome']): ?>
                                    <div class="product-category"><?= htmlspecialchars($produto['categoria_nome']) ?></div>
                                <?php endif; ?>

                                <h3 class="product-name"><?= htmlspecialchars($produto['nome']) ?></h3>

                                <?php if ($produto['descricao']): ?>
                                    <p class="product-description"><?= htmlspecialchars($produto['descricao']) ?></p>
                                <?php endif; ?>

                                <?php if ($produto['total_variacoes'] > 1): ?>
                                    <div class="product-variations">
                                        <svg class="icon" viewBox="0 0 24 24" style="width: 14px; height: 14px;">
                                            <circle cx="12" cy="12" r="3"/>
                                            <path d="M12 1v6m0 6v6"/>
                                            <path d="M17 7l-10 10"/>
                                        </svg>
                                        <?= $produto['total_variacoes'] ?> variações disponíveis
                                    </div>
                                <?php endif; ?>

                                <div class="product-price">R$ <?= number_format((float)$produto['preco'], 2, ',', '.') ?></div>

                                <button
                                    class="add-to-cart-btn"
                                    onclick="event.stopPropagation(); adicionarAoCarrinho(<?= $produto['id'] ?>)"
                                    <?= $estoque === 0 ? 'disabled' : '' ?>
                                >
                                    <svg class="icon" viewBox="0 0 24 24" style="width: 16px; height: 16px;">
                                        <circle cx="9" cy="21" r="1"/>
                                        <circle cx="20" cy="21" r="1"/>
                                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                    </svg>
                                    <?= $estoque === 0 ? 'Indisponível' : 'Adicionar ao Carrinho' ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function verProduto(produtoId, tipoItem) {
            if (tipoItem === 'kit') {
                window.location.href = `compra_produto.php?id=${produtoId}&tipo=kit`;
            } else {
                window.location.href = `compra_produto.php?id=${produtoId}`;
            }
        }

        function adicionarAoCarrinho(produtoId) {
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;

            button.innerHTML = '<svg class="icon" style="animation: spin 1s linear infinite;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="60" stroke-dashoffset="20"/></svg>';

            setTimeout(() => {
                button.innerHTML = '✓ Adicionado!';
                button.style.background = 'linear-gradient(135deg, #4CAF50, #45a049)';

                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.style.background = '';
                }, 1500);
            }, 800);
        }

        function atualizarCarrinho() {
            if (typeof rsUpdateCartBadge === 'function') rsUpdateCartBadge();
        }

        window.addEventListener('scroll', () => {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const progress = (scrollTop / scrollHeight) * 100;
            document.documentElement.style.setProperty('--scroll-progress', `${progress}%`);
        });

        const style = document.createElement('style');
        style.textContent = `@keyframes spin { to { transform: rotate(360deg); } }`;
        document.head.appendChild(style);

        atualizarCarrinho();
    </script>
    <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>