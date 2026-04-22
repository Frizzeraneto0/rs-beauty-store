<style>
    .rs-footer {
        background: linear-gradient(135deg, #0a0a0a 0%, #1a0810 100%);
        color: rgba(255,255,255,0.5);
        padding: 3rem 2rem 1.8rem;
        font-family: 'Montserrat', sans-serif;
    }

    .rs-footer-inner {
        max-width: 1400px;
        margin: 0 auto;
    }

    .rs-footer-top {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 2.5rem;
        padding-bottom: 2rem;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        margin-bottom: 1.3rem;
    }

    .rs-footer-logo {
        font-family: 'Cormorant Garamond', serif;
        font-size: 1.8rem;
        font-weight: 600;
        letter-spacing: 3px;
        text-decoration: none;
        background: linear-gradient(135deg, var(--rs-white), var(--rs-rose-gold));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        display: block;
        margin-bottom: 1rem;
    }

    .rs-footer-desc {
        font-size: 0.82rem;
        line-height: 1.8;
        color: rgba(255,255,255,0.35);
        max-width: 340px;
    }

    .rs-footer-social {
        display: flex;
        gap: 0.6rem;
        margin-top: 1.4rem;
    }

    .rs-footer-social a {
        width: 36px; height: 36px;
        border-radius: 50%;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: rgba(255,255,255,0.5);
        text-decoration: none;
        transition: all 0.3s;
    }
    .rs-footer-social a:hover {
        background: linear-gradient(135deg, var(--rs-deep-rose), var(--rs-luxury-purple));
        color: var(--rs-white);
        border-color: transparent;
        transform: translateY(-2px);
    }
    .rs-footer-social svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; }

    .rs-footer-col h4 {
        color: var(--rs-white);
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-bottom: 1.4rem;
    }

    .rs-footer-col ul { list-style: none; padding: 0; margin: 0; }
    .rs-footer-col ul li { margin-bottom: 0.7rem; }
    .rs-footer-col ul li a {
        color: rgba(255,255,255,0.4);
        text-decoration: none;
        font-size: 0.82rem;
        transition: color 0.3s;
    }
    .rs-footer-col ul li a:hover { color: var(--rs-rose-gold); }

    .rs-footer-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.75rem;
        color: rgba(255,255,255,0.25);
        gap: 1rem;
        flex-wrap: wrap;
    }

    @media (max-width: 900px) {
        .rs-footer-top { grid-template-columns: 1fr 1fr; gap: 2rem; }
        .rs-footer-bottom { flex-direction: column; gap: 0.5rem; text-align: center; }
    }
    @media (max-width: 500px) {
        .rs-footer-top { grid-template-columns: 1fr; }
    }
</style>

<footer class="rs-footer">
    <div class="rs-footer-inner">
        <div class="rs-footer-top">
            <div>
                <a href="index.php" class="rs-footer-logo">RS BEAUTY STORE</a>
                <p class="rs-footer-desc">
                    Beleza premium para quem merece o melhor.
                    Curadoria exclusiva de produtos selecionados com carinho.
                </p>
                <div class="rs-footer-social">
                    <a href="#" aria-label="Instagram">
                        <svg viewBox="0 0 24 24">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                        </svg>
                    </a>
                    <a href="#" aria-label="Facebook">
                        <svg viewBox="0 0 24 24">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                        </svg>
                    </a>
                    <a href="#" aria-label="WhatsApp">
                        <svg viewBox="0 0 24 24">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <div class="rs-footer-col">
                <h4>Loja</h4>
                <ul>
                    <li><a href="index.php">Início</a></li>
                    <li><a href="produtos.php">Produtos</a></li>
                    <li><a href="index.php#categorias">Categorias</a></li>
                    <li><a href="carrinho.php">Carrinho</a></li>
                </ul>
            </div>

            <div class="rs-footer-col">
                <h4>Minha conta</h4>
                <ul>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <li><a href="perfil.php">Meu perfil</a></li>
                        <li><a href="meus_pedidos.php">Meus pedidos</a></li>
                        <li><a href="configuracoes.php">Configurações</a></li>
                        <li><a href="logout.php">Sair</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Entrar</a></li>
                        <li><a href="cadastro.php">Criar conta</a></li>
                        <li><a href="meus_pedidos.php">Meus pedidos</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="rs-footer-col">
                <h4>Informações</h4>
                <ul>
                    <li><a href="index.php#sobre">Sobre nós</a></li>
                    <li><a href="#">Política de trocas</a></li>
                    <li><a href="#">Privacidade</a></li>
                    <li><a href="#">Contato</a></li>
                </ul>
            </div>
        </div>

        <div class="rs-footer-bottom">
            <span>© <?= date('Y') ?> RS Beauty Store. Todos os direitos reservados.</span>
            <span>Feito com &#9825; para você</span>
        </div>
    </div>
</footer>
