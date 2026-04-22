<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rsIsLoggedIn = isset($_SESSION['user_id']);
$rsUserName   = $_SESSION['user_nome']  ?? '';
$rsUserEmail  = $_SESSION['user_email'] ?? '';
$rsTipoUser   = (int)($_SESSION['tipo_usuario'] ?? 1);
$rsIsAdmin    = $rsTipoUser >= 2;

$rsFirstName = '';
$rsInitials  = '';
if ($rsUserName !== '') {
    $parts = preg_split('/\s+/', trim($rsUserName));
    $rsFirstName = $parts[0] ?? '';
    $rsInitials  = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $rsInitials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
}

$rsCurrent = basename($_SERVER['PHP_SELF']);
function rs_is_current($file) {
    return basename($_SERVER['PHP_SELF']) === $file;
}
?>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --rs-black: #0a0a0a;
        --rs-white: #fefefe;
        --rs-gray-light: #f5f5f5;
        --rs-gray-mid: #e0e0e0;
        --rs-gray-dark: #666;
        --rs-accent: #d4af37;
        --rs-rose-gold: #E8B4B8;
        --rs-deep-rose: #C67B88;
        --rs-soft-pink: #FFF5F7;
        --rs-luxury-purple: #9B7EBD;
        --rs-champagne: #F7E7CE;
    }

    body { font-family: 'Montserrat', sans-serif; }

    .rs-header {
        position: fixed;
        top: 0; left: 0; right: 0;
        background: rgba(254, 254, 254, 0.97);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        z-index: 10000;
        border-bottom: 1px solid rgba(232, 180, 184, 0.25);
        transition: box-shadow 0.3s ease;
    }
    .rs-header.scrolled { box-shadow: 0 4px 30px rgba(198, 123, 136, 0.12); }

    .rs-nav {
        max-width: 1400px;
        margin: 0 auto;
        padding: 1.1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
    }

    .rs-logo {
        font-family: 'Cormorant Garamond', serif;
        font-size: 1.7rem;
        font-weight: 600;
        letter-spacing: 3px;
        text-decoration: none;
        background: linear-gradient(135deg, var(--rs-black) 0%, var(--rs-deep-rose) 55%, var(--rs-luxury-purple) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .rs-links {
        display: flex;
        gap: 2.2rem;
        list-style: none;
        align-items: center;
        margin: 0;
        padding: 0;
    }

    .rs-links a {
        text-decoration: none;
        color: var(--rs-black);
        font-size: 0.78rem;
        font-weight: 500;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        position: relative;
        padding: 4px 0;
        transition: color 0.3s;
    }
    .rs-links a::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0;
        width: 0; height: 1px;
        background: linear-gradient(90deg, var(--rs-deep-rose), var(--rs-luxury-purple));
        transition: width 0.3s ease;
    }
    .rs-links a:hover { color: var(--rs-deep-rose); }
    .rs-links a:hover::after,
    .rs-links a.active::after { width: 100%; }
    .rs-links a.active { color: var(--rs-deep-rose); }

    .rs-actions {
        display: flex;
        gap: 0.8rem;
        align-items: center;
    }

    .rs-icon-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--rs-black);
        position: relative;
        padding: 0.45rem;
        border-radius: 50%;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .rs-icon-btn:hover {
        color: var(--rs-deep-rose);
        background: rgba(232, 180, 184, 0.12);
    }

    .rs-icon {
        width: 20px; height: 20px;
        stroke: currentColor; fill: none; stroke-width: 2;
        stroke-linecap: round; stroke-linejoin: round;
    }

    .rs-cart-badge {
        position: absolute;
        top: -2px; right: -2px;
        background: linear-gradient(135deg, var(--rs-deep-rose), var(--rs-luxury-purple));
        color: var(--rs-white);
        border-radius: 999px;
        min-width: 18px; height: 18px;
        padding: 0 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.62rem;
        font-weight: 700;
        font-family: 'Montserrat', sans-serif;
        letter-spacing: 0;
        box-shadow: 0 2px 8px rgba(198, 123, 136, 0.4);
    }

    .rs-btn-login {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.55rem 1.25rem;
        background: linear-gradient(135deg, var(--rs-deep-rose), var(--rs-luxury-purple));
        color: var(--rs-white) !important;
        text-decoration: none;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        border-radius: 50px;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(198, 123, 136, 0.3);
        font-family: 'Montserrat', sans-serif;
        border: none;
    }
    .rs-btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(155, 126, 189, 0.4);
    }

    /* ===== USER DROPDOWN ===== */
    .rs-user { position: relative; }

    .rs-user-trigger {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.35rem 0.9rem 0.35rem 0.35rem;
        background: rgba(232, 180, 184, 0.1);
        border: 1px solid rgba(232, 180, 184, 0.35);
        border-radius: 50px;
        cursor: pointer;
        font-family: 'Montserrat', sans-serif;
        color: var(--rs-black);
        transition: all 0.3s;
    }
    .rs-user-trigger:hover {
        background: rgba(232, 180, 184, 0.2);
        border-color: var(--rs-deep-rose);
    }

    .rs-avatar {
        width: 30px; height: 30px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--rs-deep-rose), var(--rs-luxury-purple));
        color: var(--rs-white);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        flex-shrink: 0;
    }

    .rs-user-name {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--rs-black);
    }

    .rs-chev {
        width: 12px; height: 12px;
        stroke: var(--rs-gray-dark); fill: none; stroke-width: 2.5;
        transition: transform 0.3s;
    }
    .rs-user.open .rs-chev { transform: rotate(180deg); }

    .rs-user-menu {
        position: absolute;
        top: calc(100% + 0.6rem);
        right: 0;
        min-width: 260px;
        background: var(--rs-white);
        border: 1px solid rgba(232, 180, 184, 0.25);
        border-radius: 14px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(232, 180, 184, 0.08);
        padding: 0.5rem;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 10001;
    }
    .rs-user.open .rs-user-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .rs-user-info {
        padding: 0.9rem 1rem 0.8rem;
        border-bottom: 1px solid var(--rs-gray-mid);
        margin-bottom: 0.5rem;
    }
    .rs-user-info strong {
        display: block;
        font-size: 0.88rem;
        color: var(--rs-black);
        font-weight: 600;
        margin-bottom: 0.15rem;
    }
    .rs-user-info span {
        font-size: 0.72rem;
        color: var(--rs-gray-dark);
        word-break: break-all;
    }

    .rs-menu-item {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.7rem 1rem;
        color: var(--rs-black);
        text-decoration: none;
        font-size: 0.82rem;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.2s;
    }
    .rs-menu-item:hover {
        background: linear-gradient(135deg, rgba(232, 180, 184, 0.12), rgba(155, 126, 189, 0.08));
        color: var(--rs-deep-rose);
        transform: translateX(2px);
    }
    .rs-menu-item svg {
        width: 17px; height: 17px;
        stroke: currentColor; fill: none; stroke-width: 1.8;
        flex-shrink: 0;
        opacity: 0.85;
    }

    .rs-menu-divider {
        height: 1px;
        background: var(--rs-gray-mid);
        margin: 0.4rem 0.5rem;
    }

    .rs-menu-item.rs-danger { color: #c0392b; }
    .rs-menu-item.rs-danger:hover {
        background: rgba(192, 57, 43, 0.08);
        color: #c0392b;
    }

    .rs-menu-item.rs-admin { color: var(--rs-luxury-purple); }

    /* ===== MOBILE ===== */
    .rs-mobile-toggle {
        display: none;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0.4rem;
        color: var(--rs-black);
    }

    @media (max-width: 960px) {
        .rs-links { display: none; }
        .rs-mobile-toggle { display: inline-flex; }
        .rs-user-name { display: none; }
        .rs-nav { padding: 1rem 1.2rem; }
        .rs-logo { font-size: 1.35rem; letter-spacing: 2px; }
    }

    .rs-mobile-menu {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(25px);
        z-index: 9999;
        padding: 5rem 2rem 2rem;
        display: none;
        flex-direction: column;
        gap: 0.5rem;
    }
    .rs-mobile-menu.open { display: flex; }
    .rs-mobile-menu a {
        padding: 1rem 0.5rem;
        color: var(--rs-black);
        text-decoration: none;
        font-size: 1rem;
        font-weight: 500;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        border-bottom: 1px solid var(--rs-gray-mid);
    }
    .rs-mobile-menu a:hover { color: var(--rs-deep-rose); }
    .rs-mobile-close {
        position: absolute;
        top: 1.2rem; right: 1.2rem;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0.4rem;
    }

    /* Spacer under fixed navbar — pages can override */
    .rs-nav-spacer { height: 72px; }
    @media (max-width: 960px) { .rs-nav-spacer { height: 62px; } }
</style>

<header class="rs-header" id="rsHeader">
    <nav class="rs-nav">
        <a href="index.php" class="rs-logo">RS BEAUTY STORE</a>

        <ul class="rs-links">
            <li><a href="index.php" class="<?= rs_is_current('index.php') ? 'active' : '' ?>">Início</a></li>
            <li><a href="produtos.php" class="<?= rs_is_current('produtos.php') ? 'active' : '' ?>">Produtos</a></li>
            <li><a href="index.php#categorias">Categorias</a></li>
            <li><a href="index.php#sobre">Sobre</a></li>
            <?php if ($rsIsLoggedIn): ?>
                <li><a href="meus_pedidos.php" class="<?= rs_is_current('meus_pedidos.php') ? 'active' : '' ?>">Meus Pedidos</a></li>
            <?php endif; ?>
        </ul>

        <div class="rs-actions">
            <a href="produtos.php" class="rs-icon-btn" title="Buscar produtos" aria-label="Buscar">
                <svg class="rs-icon" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                </svg>
            </a>

            <a href="carrinho.php" class="rs-icon-btn" title="Carrinho" aria-label="Carrinho">
                <svg class="rs-icon" viewBox="0 0 24 24">
                    <circle cx="9" cy="21" r="1"/>
                    <circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <span class="rs-cart-badge" id="rsCartBadge">0</span>
            </a>

            <?php if (!$rsIsLoggedIn): ?>
                <a href="login.php" class="rs-btn-login">
                    <svg class="rs-icon" style="width:14px;height:14px;stroke-width:2.3;" viewBox="0 0 24 24">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Entrar
                </a>
            <?php else: ?>
                <div class="rs-user" id="rsUser">
                    <button class="rs-user-trigger" type="button" onclick="rsToggleUserMenu(event)" aria-haspopup="true">
                        <span class="rs-avatar"><?= htmlspecialchars($rsInitials) ?></span>
                        <span class="rs-user-name"><?= htmlspecialchars($rsFirstName) ?></span>
                        <svg class="rs-chev" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="rs-user-menu" role="menu">
                        <div class="rs-user-info">
                            <strong><?= htmlspecialchars($rsUserName) ?></strong>
                            <span><?= htmlspecialchars($rsUserEmail) ?></span>
                        </div>

                        <a href="perfil.php" class="rs-menu-item">
                            <svg viewBox="0 0 24 24">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            Meu perfil
                        </a>

                        <a href="meus_pedidos.php" class="rs-menu-item">
                            <svg viewBox="0 0 24 24">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                <line x1="3" y1="6" x2="21" y2="6"/>
                                <path d="M16 10a4 4 0 0 1-8 0"/>
                            </svg>
                            Minhas compras
                        </a>

                        <a href="carrinho.php" class="rs-menu-item">
                            <svg viewBox="0 0 24 24">
                                <circle cx="9" cy="21" r="1"/>
                                <circle cx="20" cy="21" r="1"/>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                            </svg>
                            Meu carrinho
                        </a>

                        <a href="configuracoes.php" class="rs-menu-item">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                            Configurações
                        </a>

                        <?php if ($rsIsAdmin): ?>
                            <div class="rs-menu-divider"></div>
                            <a href="painel-admin/produtos.php" class="rs-menu-item rs-admin">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                                    <path d="M2 17l10 5 10-5"/>
                                    <path d="M2 12l10 5 10-5"/>
                                </svg>
                                Painel Admin
                            </a>
                        <?php endif; ?>

                        <div class="rs-menu-divider"></div>
                        <a href="logout.php" class="rs-menu-item rs-danger">
                            <svg viewBox="0 0 24 24">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            Sair
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <button class="rs-mobile-toggle" type="button" onclick="rsToggleMobileMenu()" aria-label="Menu">
                <svg class="rs-icon" viewBox="0 0 24 24">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
        </div>
    </nav>
</header>

<div class="rs-mobile-menu" id="rsMobileMenu">
    <button class="rs-mobile-close" type="button" onclick="rsToggleMobileMenu()" aria-label="Fechar">
        <svg class="rs-icon" viewBox="0 0 24 24">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </button>
    <a href="index.php">Início</a>
    <a href="produtos.php">Produtos</a>
    <a href="index.php#categorias">Categorias</a>
    <a href="index.php#sobre">Sobre</a>
    <a href="carrinho.php">Carrinho</a>
    <?php if ($rsIsLoggedIn): ?>
        <a href="meus_pedidos.php">Meus Pedidos</a>
        <a href="perfil.php">Meu Perfil</a>
        <a href="configuracoes.php">Configurações</a>
        <?php if ($rsIsAdmin): ?>
            <a href="painel-admin/produtos.php" style="color: var(--rs-luxury-purple);">Painel Admin</a>
        <?php endif; ?>
        <a href="logout.php" style="color:#c0392b;">Sair</a>
    <?php else: ?>
        <a href="login.php" style="color: var(--rs-deep-rose);">Entrar</a>
        <a href="cadastro.php">Criar conta</a>
    <?php endif; ?>
</div>

<div class="rs-nav-spacer"></div>

<script>
    (function () {
        const header = document.getElementById('rsHeader');
        const userWrap = document.getElementById('rsUser');
        const mobileMenu = document.getElementById('rsMobileMenu');

        window.rsToggleUserMenu = function (e) {
            if (e) e.stopPropagation();
            if (userWrap) userWrap.classList.toggle('open');
        };

        window.rsToggleMobileMenu = function () {
            if (mobileMenu) mobileMenu.classList.toggle('open');
        };

        document.addEventListener('click', function (e) {
            if (userWrap && !userWrap.contains(e.target)) userWrap.classList.remove('open');
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (userWrap) userWrap.classList.remove('open');
                if (mobileMenu) mobileMenu.classList.remove('open');
            }
        });

        window.addEventListener('scroll', function () {
            if (!header) return;
            header.classList.toggle('scrolled', window.pageYOffset > 20);
        });

        window.rsUpdateCartBadge = function () {
            try {
                const carrinho = JSON.parse(localStorage.getItem('carrinho') || '[]');
                const el = document.getElementById('rsCartBadge');
                if (el) el.textContent = carrinho.length;
            } catch (err) {}
        };
        rsUpdateCartBadge();
        window.addEventListener('storage', rsUpdateCartBadge);
    })();
</script>
