<?php
// painel-admin/layout/sidebar.php
$current = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
  @import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap');

  .sidebar {
    width: 240px;
    min-height: 100vh;
    background: #1e2a3a;
    display: flex;
    flex-direction: column;
    padding: 0;
    font-family: 'Nunito', sans-serif;
    position: fixed;
    top: 0;
    left: 0;
    overflow-y: auto;
    z-index: 100;
  }

  .sidebar-header {
    padding: 22px 20px 18px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
  }

  .sidebar-header h2 {
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    margin: 0;
    letter-spacing: 0.4px;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .sidebar-logo-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #4e9af1 0%, #2563eb 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(78,154,241,0.35);
    flex-shrink: 0;
  }

  .sidebar-logo-icon i {
    color: #fff;
    font-size: 16px;
  }

  .sidebar-section {
    padding: 16px 12px 6px;
  }

  .sidebar-section-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: rgba(255,255,255,0.3);
    padding: 0 8px;
    margin-bottom: 6px;
  }

  .sidebar nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 6px 12px;
  }

  .sidebar nav a {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 9px 12px;
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.18s ease;
    position: relative;
  }

  .sidebar nav a i {
    width: 18px;
    text-align: center;
    font-size: 14px;
    flex-shrink: 0;
    transition: color 0.18s ease;
  }

  .sidebar nav a:hover {
    background: rgba(255,255,255,0.07);
    color: #fff;
  }

  .sidebar nav a:hover i {
    color: #4e9af1;
  }

  .sidebar nav a.active {
    background: rgba(78,154,241,0.15);
    color: #fff;
  }

  .sidebar nav a.active i {
    color: #4e9af1;
  }

  .sidebar nav a.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 6px;
    bottom: 6px;
    width: 3px;
    background: #4e9af1;
    border-radius: 0 3px 3px 0;
  }

  .sidebar-divider {
    height: 1px;
    background: rgba(255,255,255,0.07);
    margin: 8px 20px;
  }

  .sidebar nav a.logout {
    color: rgba(239, 68, 68, 0.7);
    margin-top: 4px;
  }

  .sidebar nav a.logout:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
  }

  .sidebar nav a.logout i {
    color: inherit;
  }
</style>

<div class="sidebar">
  <div class="sidebar-header">
    <h2>
      <div class="sidebar-logo-icon">
        <i class="fa-solid fa-gauge-high"></i>
      </div>
      Admin Painel
    </h2>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Catálogo</div>
  </div>
  <nav>
    <a href="/painel-admin/produtos.php" class="<?= $current == 'produtos.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-box"></i> Produtos
    </a>
    <a href="/painel-admin/produto_imagens.php" class="<?= $current == 'produto_imagens.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-images"></i> Imagens dos Produtos
    </a>
    <a href="/painel-admin/categorias.php" class="<?= $current == 'categorias.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-folder-open"></i> Categorias
    </a>
    <a href="/painel-admin/variacoes.php" class="<?= $current == 'variacoes.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-palette"></i> Variações
    </a>
    <a href="/painel-admin/composicoes.php" class="<?= $current == 'composicoes.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-layer-group"></i> Composições
    </a>
  </nav>

  <div class="sidebar-divider"></div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Compras & Vendas</div>
  </div>
  <nav>
    <a href="/painel-admin/fornecedores.php" class="<?= $current == 'fornecedores.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-building"></i> Fornecedores
    </a>
    <a href="/painel-admin/compras.php" class="<?= $current == 'compras.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-cart-shopping"></i> Compras
    </a>
    <a href="/painel-admin/pedidos.php" class="<?= $current == 'pedidos.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-clipboard-list"></i> Pedidos
    </a>
    <a href="/painel-admin/clientes.php" class="<?= $current == 'clientes.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-users"></i> Clientes
    </a>
  </nav>

  <div class="sidebar-divider"></div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Estoque</div>
  </div>
  <nav>
    <a href="/painel-admin/estoque_atual.php" class="<?= $current == 'estoque_atual.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-warehouse"></i> Estoque Atual
    </a>
    <a href="/painel-admin/movimentacoes_estoque.php" class="<?= $current == 'movimentacoes_estoque.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-chart-bar"></i> Movimentações
    </a>
  </nav>

  <div class="sidebar-divider"></div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Sistema</div>
  </div>
  <nav>
    <a href="/painel-admin/administradores.php" class="<?= $current == 'administradores.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-user-shield"></i> Administradores
    </a>
    <a href="/painel-admin/configuracoes.php" class="<?= $current == 'configuracoes.php' ? 'active' : '' ?>">
      <i class="fa-solid fa-gear"></i> Configurações
    </a>
    <a href="/painel-admin/logout.php" class="logout">
      <i class="fa-solid fa-right-from-bracket"></i> Sair
    </a>
  </nav>
</div>