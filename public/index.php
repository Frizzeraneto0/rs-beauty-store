<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$db = getPDO();

// Produtos em destaque (maior estoque, ativos)
$produtosDestaque = $db->query("
    SELECT
        p.id,
        p.nome,
        p.descricao,
        p.preco,
        p.preco_promocional,
        c.nome AS categoria_nome,
        (
            SELECT url FROM produtos_imagens
            WHERE produto_id = p.id
            ORDER BY ordem LIMIT 1
        ) AS imagem,
        COALESCE(SUM(ea.quantidade), 0) AS estoque_total
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    LEFT JOIN produto_variacoes pv ON pv.id_produto = p.id
    LEFT JOIN estoque_atual ea ON ea.id_produto_variacao = pv.id
    WHERE p.ativo = 1
    GROUP BY p.id, p.nome, p.descricao, p.preco, p.preco_promocional, c.nome
    HAVING COALESCE(SUM(ea.quantidade), 0) > 0
    ORDER BY estoque_total DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Categorias com contagem
$categorias = $db->query("
    SELECT c.id, c.nome, c.descricao, COUNT(p.id) AS total_produtos
    FROM categorias c
    LEFT JOIN produtos p ON p.categoria_id = c.id AND p.ativo = 1
    GROUP BY c.id, c.nome, c.descricao
    HAVING COUNT(p.id) > 0
    ORDER BY total_produtos DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RS Beauty Store — Beleza Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

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

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--white);
            color: var(--black);
            overflow-x: hidden;
        }

        /* ========================
           SCROLL PROGRESS
        ======================== */
        #scroll-bar {
            position: fixed;
            top: 0; left: 0;
            height: 3px;
            width: 0%;
            background: linear-gradient(90deg, var(--rose-gold), var(--luxury-purple), var(--accent));
            z-index: 10001;
            transition: width 0.1s ease-out;
            box-shadow: 0 0 10px rgba(232, 180, 184, 0.6);
        }

        /* ========================
           HEADER / NAV
        ======================== */
        .header {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: rgba(254, 254, 254, 0.97);
            backdrop-filter: blur(20px);
            z-index: 1000;
            border-bottom: 1px solid rgba(232, 180, 184, 0.25);
            transition: box-shadow 0.3s ease;
        }

        .header.scrolled {
            box-shadow: 0 4px 30px rgba(198, 123, 136, 0.12);
        }

        .nav {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.4rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.9rem;
            font-weight: 600;
            letter-spacing: 3px;
            text-decoration: none;
            background: linear-gradient(135deg, var(--black) 0%, var(--deep-rose) 55%, var(--luxury-purple) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            gap: 2.5rem;
            list-style: none;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--black);
            font-size: 0.8rem;
            font-weight: 500;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            position: relative;
            padding-bottom: 4px;
            transition: color 0.3s;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0;
            width: 0; height: 1px;
            background: linear-gradient(90deg, var(--deep-rose), var(--luxury-purple));
            transition: width 0.3s ease;
        }

        .nav-links a:hover { color: var(--deep-rose); }
        .nav-links a:hover::after { width: 100%; }

        .nav-icons {
            display: flex;
            gap: 1.2rem;
            align-items: center;
        }

        .nav-icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--black);
            position: relative;
            padding: 0.3rem;
            transition: transform 0.3s, color 0.3s;
            text-decoration: none;
            display: flex;
        }

        .nav-icon-btn:hover { transform: scale(1.1); color: var(--deep-rose); }

        .icon {
            width: 20px; height: 20px;
            stroke: currentColor; fill: none; stroke-width: 2;
        }

        .cart-badge {
            position: absolute;
            top: -6px; right: -6px;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            color: var(--white);
            border-radius: 50%;
            width: 18px; height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: 700;
        }

        /* ========================
           HERO
        ======================== */
        .hero {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            position: relative;
            overflow: hidden;
            margin-top: 0;
        }

        .hero-left {
            background: linear-gradient(160deg, #0a0a0a 0%, #1a0a0f 50%, #2a0f1a 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 8rem 5rem 6rem 6rem;
            position: relative;
            z-index: 2;
        }

        .hero-left::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background:
                radial-gradient(ellipse at 30% 40%, rgba(232, 180, 184, 0.12) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 80%, rgba(155, 126, 189, 0.1) 0%, transparent 50%);
            animation: heroGlow 8s ease-in-out infinite;
        }

        @keyframes heroGlow {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }

        .hero-eyebrow {
            font-size: 0.7rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--rose-gold);
            margin-bottom: 1.5rem;
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
            opacity: 0;
            animation: fadeUp 0.8s ease 0.2s forwards;
        }

        .hero-eyebrow::before {
            content: '';
            display: block;
            width: 40px; height: 1px;
            background: var(--rose-gold);
        }

        .hero-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(3.5rem, 6vw, 6rem);
            font-weight: 300;
            line-height: 1.05;
            color: var(--white);
            margin-bottom: 2rem;
            position: relative;
            opacity: 0;
            animation: fadeUp 0.9s ease 0.4s forwards;
        }

        .hero-title em {
            font-style: italic;
            background: linear-gradient(135deg, var(--rose-gold), var(--luxury-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-desc {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.6);
            line-height: 1.8;
            max-width: 380px;
            margin-bottom: 3rem;
            font-weight: 300;
            letter-spacing: 0.3px;
            opacity: 0;
            animation: fadeUp 0.9s ease 0.6s forwards;
        }

        .hero-actions {
            display: flex;
            gap: 1.2rem;
            align-items: center;
            opacity: 0;
            animation: fadeUp 0.9s ease 0.8s forwards;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            padding: 1rem 2.2rem;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            color: var(--white);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            border-radius: 50px;
            transition: all 0.4s ease;
            box-shadow: 0 8px 30px rgba(198, 123, 136, 0.4);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s ease;
        }

        .btn-primary:hover::before { left: 100%; }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 40px rgba(155, 126, 189, 0.5);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            transition: color 0.3s;
            padding: 0.3rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .btn-secondary:hover { color: var(--rose-gold); border-color: var(--rose-gold); }

        .hero-stats {
            position: absolute;
            bottom: 3rem; left: 6rem;
            display: flex;
            gap: 3rem;
            opacity: 0;
            animation: fadeUp 0.9s ease 1s forwards;
        }

        .stat {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .stat-number {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--white);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.65rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
        }

        .hero-right {
            position: relative;
            background: linear-gradient(160deg, var(--soft-pink) 0%, var(--champagne) 50%, rgba(232, 180, 184, 0.3) 100%);
            overflow: hidden;
        }

        .hero-right::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background:
                radial-gradient(circle at 70% 30%, rgba(155, 126, 189, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 20% 80%, rgba(212, 175, 55, 0.15) 0%, transparent 50%);
        }

        .hero-visual {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-circle-1 {
            position: absolute;
            width: 500px; height: 500px;
            border: 1px solid rgba(198, 123, 136, 0.2);
            border-radius: 50%;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            animation: rotateSlow 30s linear infinite;
        }

        .hero-circle-2 {
            position: absolute;
            width: 350px; height: 350px;
            border: 1px solid rgba(155, 126, 189, 0.25);
            border-radius: 50%;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            animation: rotateSlow 20s linear infinite reverse;
        }

        .hero-circle-3 {
            position: absolute;
            width: 200px; height: 200px;
            background: linear-gradient(135deg, rgba(232, 180, 184, 0.3), rgba(155, 126, 189, 0.3));
            border-radius: 50%;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes rotateSlow {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.6; }
            50% { transform: translate(-50%, -50%) scale(1.1); opacity: 1; }
        }

        .hero-tagline {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 2rem;
        }

        .hero-tagline-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2rem, 4vw, 3.5rem);
            font-style: italic;
            font-weight: 300;
            line-height: 1.2;
            background: linear-gradient(160deg, var(--black) 0%, var(--deep-rose) 50%, var(--luxury-purple) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-dots {
            position: absolute;
            right: 3rem; top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .hero-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: rgba(198, 123, 136, 0.3);
            cursor: pointer;
            transition: all 0.3s;
        }

        .hero-dot.active {
            background: var(--deep-rose);
            transform: scale(1.4);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(25px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ========================
           CATEGORIAS
        ======================== */
        .section {
            padding: 7rem 2rem;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-eyebrow {
            font-size: 0.7rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--deep-rose);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .section-eyebrow::before,
        .section-eyebrow::after {
            content: '';
            display: block;
            width: 40px; height: 1px;
            background: linear-gradient(90deg, transparent, var(--rose-gold));
        }

        .section-eyebrow::after {
            background: linear-gradient(90deg, var(--rose-gold), transparent);
        }

        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.5rem, 4vw, 3.8rem);
            font-weight: 300;
            line-height: 1.1;
            color: var(--black);
        }

        .section-title em {
            font-style: italic;
            color: var(--deep-rose);
        }

        .categories-bg {
            background: linear-gradient(180deg, var(--white) 0%, var(--soft-pink) 50%, var(--white) 100%);
        }

        .categories-grid {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .category-card {
            position: relative;
            background: var(--white);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-decoration: none;
            color: var(--black);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(232, 180, 184, 0.2);
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(232, 180, 184, 0.08), rgba(155, 126, 189, 0.08));
            opacity: 0;
            transition: opacity 0.4s;
        }

        .category-card:hover::before { opacity: 1; }

        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(198, 123, 136, 0.2);
            border-color: rgba(232, 180, 184, 0.5);
        }

        .category-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .category-card-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.6rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .category-card-desc {
            font-size: 0.82rem;
            color: var(--gray-dark);
            line-height: 1.6;
            flex-grow: 1;
        }

        .category-card-count {
            font-size: 0.7rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--deep-rose);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .category-arrow {
            width: 32px; height: 32px;
            border: 1px solid rgba(198, 123, 136, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .category-card:hover .category-arrow {
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            border-color: transparent;
            transform: translateX(4px);
        }

        .category-card:hover .category-arrow svg { stroke: white; }

        /* ========================
           PRODUTOS EM DESTAQUE
        ======================== */
        .featured-section {
            background: var(--white);
        }

        .featured-grid {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
        }

        .product-card {
            background: var(--white);
            border-radius: 18px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.45s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--gray-mid);
            opacity: 0;
            transform: translateY(20px);
        }

        .product-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(198, 123, 136, 0.2);
            border-color: rgba(232, 180, 184, 0.5);
        }

        .product-img {
            width: 100%;
            height: 280px;
            background: linear-gradient(135deg, var(--soft-pink), var(--champagne));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .product-card:hover .product-img img {
            transform: scale(1.1);
        }

        .product-img-placeholder {
            opacity: 0.25;
            width: 60px; height: 60px;
        }

        .promo-badge {
            position: absolute;
            top: 14px; left: 14px;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            color: var(--white);
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 0.35rem 0.8rem;
            border-radius: 20px;
        }

        .product-info {
            padding: 1.2rem 1.4rem 0;
        }

        .product-cat {
            font-size: 0.65rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--deep-rose);
            margin-bottom: 0.4rem;
            font-weight: 600;
        }

        .product-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.6rem;
            color: var(--black);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-pricing {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0;
        }

        .price-final {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .price-original {
            font-size: 0.85rem;
            color: var(--gray-dark);
            text-decoration: line-through;
        }

        .product-btn {
            display: block;
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            color: var(--white);
            border: none;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1.2rem;
            border-radius: 0 0 18px 18px;
        }

        .product-btn:hover {
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
        }

        .product-btn:disabled {
            background: var(--gray-mid);
            color: var(--gray-dark);
            cursor: not-allowed;
        }

        .ver-todos-wrap {
            text-align: center;
            margin-top: 4rem;
        }

        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            padding: 1rem 2.5rem;
            border: 1.5px solid var(--deep-rose);
            color: var(--deep-rose);
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            border-radius: 50px;
            transition: all 0.3s;
        }

        .btn-outline:hover {
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            border-color: transparent;
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(198, 123, 136, 0.3);
        }

        /* ========================
           BANNER INTERMEDIÁRIO
        ======================== */
        .banner-section {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0810 40%, #2a0f1a 70%, #1a0a2a 100%);
            padding: 6rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .banner-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(232, 180, 184, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 50%, rgba(155, 126, 189, 0.1) 0%, transparent 50%);
        }

        .banner-inner {
            max-width: 700px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .banner-inner .section-eyebrow { color: var(--rose-gold); }
        .banner-inner .section-eyebrow::before { background: linear-gradient(90deg, transparent, var(--rose-gold)); }
        .banner-inner .section-eyebrow::after { background: linear-gradient(90deg, var(--rose-gold), transparent); }

        .banner-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 300;
            color: var(--white);
            line-height: 1.15;
            margin-bottom: 1.5rem;
        }

        .banner-title em {
            font-style: italic;
            background: linear-gradient(135deg, var(--rose-gold), var(--luxury-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .banner-desc {
            color: rgba(255,255,255,0.55);
            font-size: 0.9rem;
            line-height: 1.8;
            margin-bottom: 2.5rem;
            font-weight: 300;
        }

        /* ========================
           SOBRE NÓS
        ======================== */
        .about-section {
            background: linear-gradient(180deg, var(--white) 0%, var(--soft-pink) 100%);
        }

        .about-grid {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6rem;
            align-items: center;
        }

        .about-visual {
            position: relative;
        }

        .about-card {
            background: linear-gradient(135deg, var(--soft-pink), var(--champagne));
            border-radius: 30px;
            padding: 4rem 3rem;
            position: relative;
            overflow: hidden;
        }

        .about-card::before {
            content: '';
            position: absolute;
            top: -50%; right: -30%;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(155, 126, 189, 0.2), transparent 70%);
            border-radius: 50%;
        }

        .about-card::after {
            content: '';
            position: absolute;
            bottom: -30%; left: -20%;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(232, 180, 184, 0.25), transparent 70%);
            border-radius: 50%;
        }

        .about-quote {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem;
            font-style: italic;
            font-weight: 300;
            line-height: 1.5;
            color: var(--black);
            position: relative;
            z-index: 2;
        }

        .about-quote::before {
            content: '"';
            font-size: 6rem;
            line-height: 0;
            position: absolute;
            top: 2.5rem; left: -1rem;
            background: linear-gradient(135deg, var(--rose-gold), var(--luxury-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-style: normal;
        }

        .about-floater {
            position: absolute;
            bottom: -1.5rem; right: 2rem;
            background: var(--white);
            border-radius: 16px;
            padding: 1.2rem 1.8rem;
            box-shadow: 0 10px 30px rgba(198, 123, 136, 0.15);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .floater-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .floater-text strong {
            display: block;
            font-size: 1.1rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .floater-text span {
            font-size: 0.72rem;
            color: var(--gray-dark);
            letter-spacing: 0.5px;
        }

        .about-content .section-header {
            text-align: left;
            margin-bottom: 2rem;
        }

        .about-content .section-eyebrow {
            justify-content: flex-start;
        }

        .about-content .section-eyebrow::before { display: none; }

        .about-text {
            font-size: 0.9rem;
            color: #444;
            line-height: 1.9;
            margin-bottom: 1.2rem;
            font-weight: 300;
        }

        .about-values {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        .value-item {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
        }

        .value-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            margin-top: 5px;
            flex-shrink: 0;
        }

        .value-text {
            font-size: 0.82rem;
            color: var(--gray-dark);
            line-height: 1.5;
        }

        .value-text strong {
            display: block;
            color: var(--black);
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
        }

        /* ========================
           FOOTER
        ======================== */
        footer {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0810 100%);
            color: rgba(255,255,255,0.5);
            padding: 4rem 2rem 2rem;
        }

        .footer-inner {
            max-width: 1400px;
            margin: 0 auto;
        }

        .footer-top {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 4rem;
            padding-bottom: 3rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 2rem;
        }

        .footer-logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem;
            font-weight: 600;
            letter-spacing: 3px;
            text-decoration: none;
            background: linear-gradient(135deg, var(--white), var(--rose-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: block;
            margin-bottom: 1rem;
        }

        .footer-desc {
            font-size: 0.82rem;
            line-height: 1.8;
            color: rgba(255,255,255,0.35);
        }

        .footer-col h4 {
            color: var(--white);
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
        }

        .footer-col ul { list-style: none; }

        .footer-col ul li { margin-bottom: 0.8rem; }

        .footer-col ul li a {
            color: rgba(255,255,255,0.4);
            text-decoration: none;
            font-size: 0.82rem;
            transition: color 0.3s;
        }

        .footer-col ul li a:hover { color: var(--rose-gold); }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.25);
        }

        /* ========================
           RESPONSIVO
        ======================== */
        @media (max-width: 1100px) {
            .featured-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 900px) {
            .hero { grid-template-columns: 1fr; min-height: auto; }
            .hero-left { padding: 10rem 2.5rem 5rem; }
            .hero-right { height: 50vh; }
            .hero-stats { left: 2.5rem; }
            .categories-grid { grid-template-columns: repeat(2, 1fr); }
            .about-grid { grid-template-columns: 1fr; gap: 3rem; }
            .footer-top { grid-template-columns: 1fr; gap: 2rem; }
            .footer-bottom { flex-direction: column; gap: 0.5rem; text-align: center; }
        }

        @media (max-width: 600px) {
            .nav-links { display: none; }
            .categories-grid { grid-template-columns: 1fr; }
            .featured-grid { grid-template-columns: 1fr; }
            .about-values { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div id="scroll-bar"></div>

    <!-- ========================
         HEADER
    ======================== -->
    <header class="header" id="header">
        <nav class="nav">
            <a href="index.php" class="logo">RS BEAUTY STORE</a>

            <ul class="nav-links">
                <li><a href="index.php">Início</a></li>
                <li><a href="produtos.php">Produtos</a></li>
                <li><a href="#categorias">Categorias</a></li>
                <li><a href="#sobre">Sobre</a></li>
            </ul>

            <div class="nav-icons">
                <a href="produtos.php" class="nav-icon-btn" title="Produtos">
                    <svg class="icon" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                </a>
                <a href="carrinho.php" class="nav-icon-btn" title="Carrinho">
                    <svg class="icon" viewBox="0 0 24 24">
                        <circle cx="9" cy="21" r="1"/>
                        <circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <span class="cart-badge" id="cart-count">0</span>
                </a>
                <?php if (isset($_SESSION['access_token'])): ?>
                    <a href="/admin/dashboard.php" class="nav-icon-btn" title="Admin">
                        <svg class="icon" viewBox="0 0 24 24">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- ========================
         HERO
    ======================== -->
    <section class="hero">
        <div class="hero-left">
            <p class="hero-eyebrow">Nova Coleção 2025</p>
            <h1 class="hero-title">
                Beleza que<br>
                <em>transforma</em><br>
                momentos
            </h1>
            <p class="hero-desc">
                Produtos premium selecionados para realçar sua beleza natural. 
                Curadoria exclusiva com o que há de melhor no universo beauty.
            </p>
            <div class="hero-actions">
                <a href="produtos.php" class="btn-primary">
                    Ver Coleção
                    <svg style="width:16px;height:16px;stroke:white;fill:none;stroke-width:2;" viewBox="0 0 24 24">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
                <a href="#sobre" class="btn-secondary">Nossa história</a>
            </div>

            <div class="hero-stats">
                <div class="stat">
                    <span class="stat-number"><?= count($produtosDestaque) ?>+</span>
                    <span class="stat-label">Produtos</span>
                </div>
                <div class="stat">
                    <span class="stat-number"><?= count($categorias) ?></span>
                    <span class="stat-label">Categorias</span>
                </div>
                <div class="stat">
                    <span class="stat-number">100%</span>
                    <span class="stat-label">Premium</span>
                </div>
            </div>
        </div>

        <div class="hero-right">
            <div class="hero-visual">
                <div class="hero-circle-1"></div>
                <div class="hero-circle-2"></div>
                <div class="hero-circle-3"></div>
                <div class="hero-tagline">
                    <p class="hero-tagline-text">
                        Luxo ao<br>alcance de<br>todas
                    </p>
                </div>
            </div>
            <div class="hero-dots">
                <div class="hero-dot active"></div>
                <div class="hero-dot"></div>
                <div class="hero-dot"></div>
            </div>
        </div>
    </section>

    <!-- ========================
         CATEGORIAS
    ======================== -->
    <section class="section categories-bg" id="categorias">
        <div class="section-header">
            <p class="section-eyebrow">Explore</p>
            <h2 class="section-title">Nossas <em>Categorias</em></h2>
        </div>

        <div class="categories-grid">
            <?php
            $icons = ['💄', '✨', '🧴', '💅', '🌸', '🪞'];
            foreach ($categorias as $i => $cat):
            ?>
                <a href="produtos.php?categoria=<?= $cat['id'] ?>" class="category-card">
                    <div class="category-icon"><?= $icons[$i % count($icons)] ?></div>
                    <div class="category-card-name"><?= htmlspecialchars($cat['nome']) ?></div>
                    <?php if ($cat['descricao']): ?>
                        <p class="category-card-desc"><?= htmlspecialchars($cat['descricao']) ?></p>
                    <?php else: ?>
                        <p class="category-card-desc">Explore nossa seleção exclusiva de produtos desta categoria.</p>
                    <?php endif; ?>
                    <div class="category-card-count">
                        <span><?= $cat['total_produtos'] ?> <?= $cat['total_produtos'] == 1 ? 'produto' : 'produtos' ?></span>
                        <div class="category-arrow">
                            <svg style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;" viewBox="0 0 24 24">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>

            <?php if (empty($categorias)): ?>
                <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--gray-dark);">
                    Nenhuma categoria disponível no momento.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ========================
         PRODUTOS EM DESTAQUE
    ======================== -->
    <section class="section featured-section">
        <div class="section-header">
            <p class="section-eyebrow">Destaques</p>
            <h2 class="section-title">Mais <em>Desejados</em></h2>
        </div>

        <div class="featured-grid" id="featured-grid">
            <?php if (empty($produtosDestaque)): ?>
                <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--gray-dark);">
                    Nenhum produto disponível no momento.
                </div>
            <?php else: ?>
                <?php foreach ($produtosDestaque as $p): ?>
                    <div class="product-card" onclick="window.location.href='compra_produto.php?id=<?= $p['id'] ?>'">
                        <div class="product-img">
                            <?php if ($p['imagem']): ?>
                                <img src="<?= htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>">
                            <?php else: ?>
                                <svg class="product-img-placeholder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <path d="M21 15l-5-5L5 21"/>
                                </svg>
                            <?php endif; ?>
                            <?php if ($p['preco_promocional']): ?>
                                <span class="promo-badge">Promoção</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <?php if ($p['categoria_nome']): ?>
                                <p class="product-cat"><?= htmlspecialchars($p['categoria_nome']) ?></p>
                            <?php endif; ?>
                            <h3 class="product-name"><?= htmlspecialchars($p['nome']) ?></h3>
                            <div class="product-pricing">
                                <span class="price-final">
                                    R$ <?= number_format((float)($p['preco_promocional'] ?? $p['preco']), 2, ',', '.') ?>
                                </span>
                                <?php if ($p['preco_promocional']): ?>
                                    <span class="price-original">R$ <?= number_format((float)$p['preco'], 2, ',', '.') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button
                            class="product-btn"
                            onclick="event.stopPropagation(); adicionarAoCarrinho(<?= $p['id'] ?>, this)"
                        >
                            Adicionar ao Carrinho
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="ver-todos-wrap">
            <a href="produtos.php" class="btn-outline">
                Ver todos os produtos
                <svg style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;" viewBox="0 0 24 24">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </section>

    <!-- ========================
         BANNER INTERMEDIÁRIO
    ======================== -->
    <section class="banner-section">
        <div class="banner-inner">
            <p class="section-eyebrow">Exclusividade</p>
            <h2 class="banner-title">
                Cada produto conta<br>uma <em>história de beleza</em>
            </h2>
            <p class="banner-desc">
                Selecionamos com cuidado cada item da nossa coleção para garantir 
                qualidade, elegância e resultados que você pode sentir.
            </p>
            <a href="produtos.php" class="btn-primary">
                Explorar coleção completa
                <svg style="width:16px;height:16px;stroke:white;fill:none;stroke-width:2;" viewBox="0 0 24 24">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </section>

    <!-- ========================
         SOBRE NÓS
    ======================== -->
    <section class="section about-section" id="sobre">
        <div class="about-grid">
            <div class="about-visual">
                <div class="about-card">
                    <p class="about-quote">
                        Beleza não é um padrão a seguir — é uma expressão única de quem você é.
                    </p>
                </div>
                <div class="about-floater">
                    <div class="floater-icon">✨</div>
                    <div class="floater-text">
                        <strong>Premium</strong>
                        <span>Qualidade garantida</span>
                    </div>
                </div>
            </div>

            <div class="about-content">
                <div class="section-header">
                    <p class="section-eyebrow">Nossa história</p>
                    <h2 class="section-title">Sobre a <em>RS Beauty</em></h2>
                </div>
                <p class="about-text">
                    A RS Beauty Store nasceu da paixão pela beleza e do desejo de tornar produtos 
                    premium acessíveis a todas. Acreditamos que cada pessoa merece se sentir 
                    especial e confiante todos os dias.
                </p>
                <p class="about-text">
                    Nossa curadoria é feita com muito carinho, selecionando apenas produtos que 
                    passam pelos nossos critérios de qualidade, eficácia e segurança. Aqui, você 
                    encontra o melhor do universo beauty em um só lugar.
                </p>

                <div class="about-values">
                    <div class="value-item">
                        <div class="value-dot"></div>
                        <div class="value-text">
                            <strong>Qualidade Premium</strong>
                            Produtos selecionados com rigor
                        </div>
                    </div>
                    <div class="value-item">
                        <div class="value-dot"></div>
                        <div class="value-text">
                            <strong>Entrega Rápida</strong>
                            Seu pedido com agilidade
                        </div>
                    </div>
                    <div class="value-item">
                        <div class="value-dot"></div>
                        <div class="value-text">
                            <strong>Atendimento</strong>
                            Suporte humanizado
                        </div>
                    </div>
                    <div class="value-item">
                        <div class="value-dot"></div>
                        <div class="value-text">
                            <strong>Exclusividade</strong>
                            Coleções únicas
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================
         FOOTER
    ======================== -->
    <footer>
        <div class="footer-inner">
            <div class="footer-top">
                <div>
                    <a href="index.php" class="footer-logo">RS BEAUTY STORE</a>
                    <p class="footer-desc">
                        Beleza premium para quem merece o melhor. 
                        Curadoria exclusiva de produtos selecionados com carinho.
                    </p>
                </div>
                <div class="footer-col">
                    <h4>Navegação</h4>
                    <ul>
                        <li><a href="index.php">Início</a></li>
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="#categorias">Categorias</a></li>
                        <li><a href="carrinho.php">Carrinho</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Informações</h4>
                    <ul>
                        <li><a href="#sobre">Sobre nós</a></li>
                        <li><a href="#">Política de trocas</a></li>
                        <li><a href="#">Privacidade</a></li>
                        <li><a href="#">Contato</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© <?= date('Y') ?> RS Beauty Store. Todos os direitos reservados.</span>
                <span>Feito com ♥ para você</span>
            </div>
        </div>
    </footer>

    <script>
        // Scroll progress bar
        window.addEventListener('scroll', () => {
            const scrollTop = window.pageYOffset;
            const docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            document.getElementById('scroll-bar').style.width = (scrollTop / docHeight * 100) + '%';

            // Header shadow on scroll
            document.getElementById('header').classList.toggle('scrolled', scrollTop > 50);
        });

        // Animate product cards on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, i) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('visible');
                        entry.target.style.transition = `opacity 0.5s ease ${i * 0.08}s, transform 0.5s ease ${i * 0.08}s`;
                    }, i * 80);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.product-card').forEach(card => observer.observe(card));

        // Carrinho
        function atualizarCarrinho() {
            const carrinho = JSON.parse(localStorage.getItem('carrinho') || '[]');
            document.getElementById('cart-count').textContent = carrinho.length;
        }

        function adicionarAoCarrinho(produtoId, btn) {
            const original = btn.textContent;
            btn.textContent = 'Adicionando...';
            btn.disabled = true;

            setTimeout(() => {
                const carrinho = JSON.parse(localStorage.getItem('carrinho') || '[]');
                carrinho.push({ id: produtoId });
                localStorage.setItem('carrinho', JSON.stringify(carrinho));
                atualizarCarrinho();

                btn.textContent = '✓ Adicionado!';
                btn.style.background = 'linear-gradient(135deg, #4CAF50, #45a049)';

                setTimeout(() => {
                    btn.textContent = original;
                    btn.style.background = '';
                    btn.disabled = false;
                }, 1800);
            }, 600);
        }

        atualizarCarrinho();
    </script>
</body>
</html>