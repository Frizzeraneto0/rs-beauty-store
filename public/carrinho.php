<?php
require_once __DIR__ . '/../config/database.php';
$db = getPDO();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho - RS BEAUTY STORE</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            min-height: 100vh;
        }

        /* ===== HEADER ===== */
        .header {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.98), rgba(255,245,247,0.98));
            backdrop-filter: blur(20px);
            z-index: 1000;
            border-bottom: 2px solid transparent;
            border-image: linear-gradient(90deg, var(--rose-gold), var(--luxury-purple), var(--accent)) 1;
            box-shadow: 0 4px 30px rgba(0,0,0,0.05);
        }

        .nav {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem; font-weight: 600; letter-spacing: 2px;
            background: linear-gradient(135deg, var(--black) 0%, var(--deep-rose) 50%, var(--luxury-purple) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
        }

        .nav-icons { display: flex; gap: 1.5rem; align-items: center; }
        .nav-icons a { color: var(--black); text-decoration: none; transition: transform 0.3s; position: relative; }
        .nav-icons a:hover { transform: translateY(-3px); }
        .icon { width: 22px; height: 22px; stroke: currentColor; fill: none; stroke-width: 2; }

        /* ===== PAGE HEADER ===== */
        .page-header {
            margin-top: 90px;
            background: linear-gradient(135deg, #0a0a0a 0%, var(--deep-rose) 50%, var(--luxury-purple) 100%);
            padding: 4rem 2rem 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background-image:
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(232,180,184,0.2) 0%, transparent 50%);
        }

        .page-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3.5rem; font-weight: 300; letter-spacing: 3px;
            color: var(--white); position: relative; z-index: 2;
            text-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        /* ===== MAIN LAYOUT ===== */
        .cart-container {
            max-width: 1400px;
            margin: 3rem auto;
            padding: 0 2rem 4rem;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 3rem;
        }

        /* ===== CART ITEMS ===== */
        .cart-items {
            background: var(--white);
            border-radius: 20px; padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }

        .cart-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 2rem; padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-mid);
        }

        .cart-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem; font-weight: 600;
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .cart-count { color: var(--gray-dark); font-size: 0.9rem; }

        /* ===== EMPTY ===== */
        .empty-cart { text-align: center; padding: 4rem 2rem; }

        .empty-cart-icon { width: 120px; height: 120px; margin: 0 auto 2rem; opacity: 0.3; }

        .empty-cart h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem; margin-bottom: 1rem; color: var(--gray-dark);
        }

        .empty-cart p { color: var(--gray-dark); margin-bottom: 2rem; }

        .btn-continue-shopping {
            display: inline-block; padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            color: var(--white); text-decoration: none; border-radius: 12px;
            font-weight: 600; letter-spacing: 1px; transition: all 0.3s;
        }

        .btn-continue-shopping:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(198,123,136,0.4); }

        /* ===== CART ITEM ===== */
        .cart-item {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 1.5rem; padding: 1.5rem;
            border-bottom: 1px solid var(--gray-mid);
            animation: slideIn 0.4s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .cart-item:last-child { border-bottom: none; }

        .item-image {
            width: 120px; height: 120px;
            background: var(--gray-light); border-radius: 12px; overflow: hidden;
        }

        .item-image img { width: 100%; height: 100%; object-fit: cover; }

        .item-image-placeholder {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center; opacity: 0.3;
        }

        .item-details { display: flex; flex-direction: column; gap: 0.5rem; }

        .item-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.3rem; font-weight: 600; color: var(--black);
        }

        .item-variations { font-size: 0.85rem; color: var(--gray-dark); }

        .item-price {
            font-size: 1.1rem; font-weight: 600;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .item-quantity { display: flex; align-items: center; gap: 0.8rem; margin-top: 0.5rem; }

        .qty-btn {
            width: 32px; height: 32px;
            border: 2px solid var(--gray-mid); background: var(--white);
            border-radius: 8px; cursor: pointer; transition: all 0.3s;
            font-weight: 700; display: flex; align-items: center; justify-content: center;
        }

        .qty-btn:hover { border-color: var(--deep-rose); background: var(--soft-pink); }

        .qty-value { font-weight: 600; min-width: 30px; text-align: center; }

        .item-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 1rem; }

        .item-total { font-size: 1.3rem; font-weight: 700; color: var(--black); }

        .btn-remove {
            background: none; border: none; color: var(--gray-dark);
            cursor: pointer; padding: 0.5rem; transition: all 0.3s; border-radius: 8px;
        }

        .btn-remove:hover { color: #f44336; background: rgba(244,67,54,0.1); }

        /* ===== SUMMARY ===== */
        .cart-summary { position: sticky; top: 120px; height: fit-content; }

        .summary-card {
            background: linear-gradient(135deg, var(--white), var(--soft-pink));
            border-radius: 20px; padding: 2rem;
            box-shadow: 0 10px 40px rgba(198,123,136,0.15);
        }

        .summary-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem; font-weight: 600; margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .summary-row {
            display: flex; justify-content: space-between;
            padding: 1rem 0; border-bottom: 1px solid var(--gray-mid);
        }

        .summary-row:last-of-type { border-bottom: none; }
        .summary-label { color: var(--gray-dark); font-size: 0.95rem; }
        .summary-value { font-weight: 600; color: var(--black); }

        .summary-total {
            margin-top: 1rem; padding-top: 1.5rem;
            border-top: 2px solid var(--deep-rose) !important;
        }

        .summary-total .summary-label { font-size: 1.1rem; font-weight: 600; color: var(--black); }

        .summary-total .summary-value {
            font-size: 1.8rem; font-weight: 700;
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn-checkout {
            width: 100%; padding: 1.2rem; margin-top: 1.5rem;
            background: linear-gradient(135deg, var(--black) 0%, var(--deep-rose) 100%);
            color: var(--white); border: none; border-radius: 12px;
            font-size: 1rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
            cursor: pointer; transition: all 0.4s;
            box-shadow: 0 8px 25px rgba(198,123,136,0.3);
        }

        .btn-checkout:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--deep-rose) 0%, var(--luxury-purple) 100%);
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(155,126,189,0.4);
        }

        .btn-checkout:disabled { background: var(--gray-mid); cursor: not-allowed; opacity: 0.6; }

        .btn-continue-link {
            width: 100%; padding: 1rem; margin-top: 1rem;
            background: var(--white); color: var(--black);
            border: 2px solid var(--gray-mid); border-radius: 12px;
            font-weight: 600; cursor: pointer; transition: all 0.3s;
            text-decoration: none; text-align: center; display: block;
        }

        .btn-continue-link:hover { border-color: var(--deep-rose); background: var(--soft-pink); }

        /* ===== FRETE ===== */
        .frete-section {
            margin-top: 1.5rem; padding-top: 1.5rem;
            border-top: 1px solid var(--gray-mid);
        }

        .frete-label {
            font-size: 0.85rem; font-weight: 600; color: var(--black);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.8rem;
        }

        .frete-input { display: flex; gap: 0.5rem; }

        .frete-input input {
            flex: 1; padding: 0.8rem; border: 2px solid var(--gray-mid);
            border-radius: 8px; font-size: 0.9rem; font-family: inherit;
        }

        .frete-input input:focus { outline: none; border-color: var(--rose-gold); }

        .frete-input button {
            padding: 0.8rem 1.2rem; background: var(--black); color: var(--white);
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
            transition: all 0.3s; font-family: inherit; font-size: 0.85rem;
        }

        .frete-input button:hover { background: var(--deep-rose); }

        /* ===== CUPOM ===== */
        .coupon-section {
            margin-top: 1rem; padding-top: 1rem;
            border-top: 1px solid var(--gray-mid);
        }

        .coupon-input { display: flex; gap: 0.5rem; }

        .coupon-input input {
            flex: 1; padding: 0.8rem; border: 2px solid var(--gray-mid);
            border-radius: 8px; font-size: 0.9rem; font-family: inherit;
        }

        .coupon-input input:focus { outline: none; border-color: var(--rose-gold); }

        .coupon-input button {
            padding: 0.8rem 1.2rem; background: var(--black); color: var(--white);
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
            transition: all 0.3s; font-family: inherit; font-size: 0.85rem;
        }

        .coupon-input button:hover { background: var(--deep-rose); }

        .desconto-aplicado {
            margin-top: 0.5rem; font-size: 0.85rem;
            color: #27ae60; font-weight: 600; display: none;
        }

        /* ===== TRUST BADGES ===== */
        .trust-badges {
            display: flex; flex-direction: column; gap: 0.8rem;
            margin-top: 1.5rem; padding-top: 1.5rem;
            border-top: 1px solid var(--gray-mid);
        }

        .trust-item {
            display: flex; align-items: center; gap: 0.8rem;
            font-size: 0.8rem; color: var(--gray-dark);
        }

        .trust-item svg { width: 18px; height: 18px; color: var(--deep-rose); flex-shrink: 0; }

        /* ===== TOAST ===== */
        .toast {
            position: fixed; bottom: 2rem; right: 2rem;
            background: #f44336; color: white;
            padding: 1rem 1.5rem; border-radius: 12px; font-weight: 600;
            transform: translateY(100px); opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 9999; box-shadow: 0 8px 25px rgba(244,67,54,0.4);
        }

        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.success { background: #27ae60; box-shadow: 0 8px 25px rgba(39,174,96,0.4); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 968px) {
            .cart-container { grid-template-columns: 1fr; }
            .cart-summary { position: static; }
            .cart-item { grid-template-columns: 100px 1fr; gap: 1rem; }
            .item-actions { grid-column: 1/-1; flex-direction: row; justify-content: space-between; align-items: center; }
            .page-header h1 { font-size: 2.5rem; }
        }
    </style>
</head>
<body>

    <header class="header">
        <nav class="nav">
            <a href="produtos.php" class="logo">RS BEAUTY STORE</a>
            <div class="nav-icons">
                <a href="produtos.php" title="Produtos">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                </a>
                <a href="carrinho.php" title="Carrinho">
                    <svg class="icon" viewBox="0 0 24 24">
                        <circle cx="9" cy="21" r="1"/>
                        <circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                </a>
            </div>
        </nav>
    </header>

    <div class="page-header">
        <h1>Seu Carrinho</h1>
    </div>

    <div class="cart-container">

        <!-- CART ITEMS -->
        <div class="cart-items">
            <div class="cart-header">
                <h2 class="cart-title">Itens do Carrinho</h2>
                <span class="cart-count" id="itemCount">0 itens</span>
            </div>
            <div id="cartItemsContainer"></div>
        </div>

        <!-- SUMMARY -->
        <div class="cart-summary">
            <div class="summary-card">
                <h3 class="summary-title">Resumo do Pedido</h3>

                <div class="summary-row">
                    <span class="summary-label">Subtotal</span>
                    <span class="summary-value" id="subtotal">R$ 0,00</span>
                </div>

                <div class="summary-row" id="descontoRow" style="display:none;">
                    <span class="summary-label">Desconto</span>
                    <span class="summary-value" style="color:#27ae60;" id="descontoValor">− R$ 0,00</span>
                </div>

                <div class="summary-row">
                    <span class="summary-label">Frete</span>
                    <span class="summary-value" id="freteValor">Grátis</span>
                </div>

                <div class="summary-row summary-total">
                    <span class="summary-label">Total</span>
                    <span class="summary-value" id="total">R$ 0,00</span>
                </div>

                <button class="btn-checkout" id="btnCheckout" onclick="finalizarCompra()">
                    Finalizar Compra
                </button>

                <a href="produtos.php" class="btn-continue-link">Continuar Comprando</a>

                <!-- FRETE -->
                <div class="frete-section">
                    <div class="frete-label">Calcular Frete</div>
                    <div class="frete-input">
                        <input type="text" id="cepInput" placeholder="Digite seu CEP" maxlength="9">
                        <button onclick="calcularFrete()">Calcular</button>
                    </div>
                </div>

                <!-- CUPOM -->
                <div class="coupon-section">
                    <div class="frete-label">Cupom de Desconto</div>
                    <div class="coupon-input">
                        <input type="text" id="couponInput" placeholder="Código do cupom">
                        <button onclick="aplicarCupom()">Aplicar</button>
                    </div>
                    <div class="desconto-aplicado" id="descontoMsg"></div>
                </div>

                <!-- TRUST BADGES -->
                <div class="trust-badges">
                    <div class="trust-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        Compra 100% segura
                    </div>
                    <div class="trust-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        Entrega em todo o Brasil
                    </div>
                    <div class="trust-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                            <polyline points="17 6 23 6 23 12"/>
                        </svg>
                        Troca e devolução fácil
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
    let carrinho       = [];
    let desconto       = 0;
    let freteValor     = 0;
    let cupomAplicado  = null;

    // ========================================
    // INICIALIZAÇÃO
    // ========================================
    function carregarCarrinho() {
        carrinho = JSON.parse(localStorage.getItem('carrinho') || '[]');
        renderizarCarrinho();
    }

    // ========================================
    // RENDERIZAR
    // ========================================
    function renderizarCarrinho() {
        const container  = document.getElementById('cartItemsContainer');
        const itemCount  = document.getElementById('itemCount');
        const btnCheckout = document.getElementById('btnCheckout');

        if (carrinho.length === 0) {
            container.innerHTML = `
                <div class="empty-cart">
                    <svg class="empty-cart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="9" cy="21" r="1"/>
                        <circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <h3>Seu carrinho está vazio</h3>
                    <p>Adicione produtos incríveis e comece sua jornada de beleza</p>
                    <a href="produtos.php" class="btn-continue-shopping">Explorar Produtos</a>
                </div>`;
            itemCount.textContent = '0 itens';
            btnCheckout.disabled = true;
            atualizarResumo();
            return;
        }

        btnCheckout.disabled = false;
        itemCount.textContent = `${carrinho.length} ${carrinho.length === 1 ? 'item' : 'itens'}`;

        container.innerHTML = carrinho.map((item, index) => `
            <div class="cart-item">
                <div class="item-image">
                    ${item.imagem
                        ? `<img src="${item.imagem}" alt="${item.nome}">`
                        : `<div class="item-image-placeholder">
                                <svg class="icon" viewBox="0 0 24 24">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <path d="M21 15l-5-5L5 21"/>
                                </svg>
                           </div>`
                    }
                </div>
                <div class="item-details">
                    <h3 class="item-name">${item.nome}</h3>
                    ${item.variacoesTexto ? `<div class="item-variations">${item.variacoesTexto}</div>` : ''}
                    <div class="item-price">R$ ${parseFloat(item.preco).toFixed(2).replace('.', ',')}</div>
                    <div class="item-quantity">
                        <button class="qty-btn" onclick="atualizarQuantidade(${index}, -1)">−</button>
                        <span class="qty-value">${item.quantidade}</span>
                        <button class="qty-btn" onclick="atualizarQuantidade(${index}, 1)">+</button>
                    </div>
                </div>
                <div class="item-actions">
                    <div class="item-total">
                        R$ ${(parseFloat(item.preco) * item.quantidade).toFixed(2).replace('.', ',')}
                    </div>
                    <button class="btn-remove" onclick="removerItem(${index})" title="Remover">
                        <svg class="icon" viewBox="0 0 24 24">
                            <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </div>
        `).join('');

        atualizarResumo();
    }

    // ========================================
    // QUANTIDADE
    // ========================================
    function atualizarQuantidade(index, delta) {
        carrinho[index].quantidade = Math.max(1, carrinho[index].quantidade + delta);
        salvarCarrinho();
        renderizarCarrinho();
    }

    // ========================================
    // REMOVER
    // ========================================
    function removerItem(index) {
        carrinho.splice(index, 1);
        salvarCarrinho();
        renderizarCarrinho();
        mostrarToast('Item removido do carrinho', false);
    }

    // ========================================
    // RESUMO
    // ========================================
    function atualizarResumo() {
        const subtotal = carrinho.reduce((acc, item) => acc + parseFloat(item.preco) * item.quantidade, 0);
        const total    = Math.max(0, subtotal - desconto + freteValor);

        document.getElementById('subtotal').textContent = formatBRL(subtotal);
        document.getElementById('total').textContent    = formatBRL(total);

        const descontoRow = document.getElementById('descontoRow');
        if (desconto > 0) {
            descontoRow.style.display = 'flex';
            document.getElementById('descontoValor').textContent = `− ${formatBRL(desconto)}`;
        } else {
            descontoRow.style.display = 'none';
        }

        document.getElementById('freteValor').textContent =
            freteValor > 0 ? formatBRL(freteValor) : 'Grátis';
    }

    function formatBRL(value) {
        return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',');
    }

    // ========================================
    // CALCULAR FRETE
    // ========================================
    function calcularFrete() {
        const cep = document.getElementById('cepInput').value.replace(/\D/g, '');
        if (cep.length !== 8) {
            mostrarToast('Digite um CEP válido com 8 dígitos', false);
            return;
        }
        // Simulação de frete
        freteValor = 0; // frete grátis
        document.getElementById('freteValor').textContent = 'Grátis';
        mostrarToast('Frete grátis para este CEP! ✓', true);
        atualizarResumo();
    }

    // Máscara CEP
    document.getElementById('cepInput').addEventListener('input', function () {
        let val = this.value.replace(/\D/g, '');
        if (val.length > 5) val = val.slice(0, 5) + '-' + val.slice(5, 8);
        this.value = val;
    });

    // ========================================
    // CUPOM
    // ========================================
    function aplicarCupom() {
        const cupom = document.getElementById('couponInput').value.trim().toUpperCase();
        const msg   = document.getElementById('descontoMsg');

        if (!cupom) { mostrarToast('Digite um código de cupom', false); return; }
        if (cupomAplicado) { mostrarToast('Já existe um cupom aplicado', false); return; }

        // Cupons de exemplo — substitua pela lógica real
        const cupons = {
            'BEAUTY10': 0.10,
            'PROMO20':  0.20,
            'VELAS15':  0.15
        };

        if (cupons[cupom]) {
            const subtotal = carrinho.reduce((acc, item) => acc + parseFloat(item.preco) * item.quantidade, 0);
            desconto = subtotal * cupons[cupom];
            cupomAplicado = cupom;
            msg.textContent = `✓ Cupom ${cupom} aplicado — ${(cupons[cupom] * 100)}% de desconto!`;
            msg.style.display = 'block';
            atualizarResumo();
            mostrarToast('Cupom aplicado com sucesso!', true);
        } else {
            mostrarToast('Cupom inválido ou expirado', false);
        }
    }

    // ========================================
    // FINALIZAR COMPRA
    // ========================================
    function finalizarCompra() {
        if (carrinho.length === 0) {
            mostrarToast('Seu carrinho está vazio', false);
            return;
        }
        window.location.href = 'checkout.php';
    }

    // ========================================
    // SALVAR
    // ========================================
    function salvarCarrinho() {
        localStorage.setItem('carrinho', JSON.stringify(carrinho));
    }

    // ========================================
    // TOAST
    // ========================================
    function mostrarToast(msg, sucesso = true) {
        const toast = document.getElementById('toast');
        toast.textContent = msg;
        toast.className = 'toast' + (sucesso ? ' success' : '') + ' show';
        setTimeout(() => { toast.className = 'toast'; }, 3000);
    }

    // Init
    carregarCarrinho();
    </script>

</body>
</html>