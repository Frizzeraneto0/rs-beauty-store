<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../../config/database.php';

/*
if (!isset($_SESSION['access_token'])) {
    header('Location: login.php');
    exit;
}
*/

$db = getPDO();

include __DIR__ . '/layout/header.php';
include __DIR__ . '/layout/sidebar.php';

/**
 * FILTROS
 */
$filtroBusca      = $_GET['busca']       ?? '';
$filtroDataInicio = $_GET['data_inicio'] ?? '';
$filtroDataFim    = $_GET['data_fim']    ?? '';

/**
 * CLIENTES
 */
$sqlClientes = "
    SELECT
        u.id,
        u.nome,
        u.email,
        u.telefone,
        DATE_FORMAT(u.criado_em, '%d/%m/%Y') AS data_cadastro,
        (SELECT COUNT(*) FROM vendas WHERE usuario_id = u.id) AS total_pedidos,
        COALESCE((SELECT SUM(valor_total) FROM vendas WHERE usuario_id = u.id), 0) AS valor_total_compras,
        (
            SELECT GROUP_CONCAT(CONCAT(e2.cidade, ' - ', e2.estado) SEPARATOR ', ')
            FROM enderecos e2
            WHERE e2.usuario_id = u.id
            LIMIT 3
        ) AS enderecos
    FROM usuarios u
    WHERE u.tipo_usuario_id = 1
";

$params = [];

if ($filtroBusca) {
    $sqlClientes .= " AND (u.nome LIKE :busca OR u.email LIKE :busca OR u.telefone LIKE :busca)";
    $params[':busca'] = '%' . $filtroBusca . '%';
}

if ($filtroDataInicio) {
    $sqlClientes .= " AND DATE(u.criado_em) >= :data_inicio";
    $params[':data_inicio'] = $filtroDataInicio;
}

if ($filtroDataFim) {
    $sqlClientes .= " AND DATE(u.criado_em) <= :data_fim";
    $params[':data_fim'] = $filtroDataFim;
}

$sqlClientes .= " ORDER BY u.criado_em DESC LIMIT 100";

$stmt = $db->prepare($sqlClientes);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ESTATÍSTICAS
 */
$stats = $db->query("
    SELECT
        COUNT(*)                                                                                  AS total_clientes,
        SUM(CASE WHEN DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS novos_mes
    FROM usuarios
    WHERE tipo_usuario_id = 1
")->fetch(PDO::FETCH_ASSOC);

$statsVendas = $db->query("
    SELECT
        COUNT(DISTINCT usuario_id)    AS clientes_compraram,
        COALESCE(SUM(valor_total), 0) AS total_vendas
    FROM vendas
")->fetch(PDO::FETCH_ASSOC);

$stats['clientes_compraram'] = $statsVendas['clientes_compraram'];
$stats['total_vendas']       = $statsVendas['total_vendas'];
?>

<div class="main pagina-clientes-admin">

    <div class="pagina-header">
        <h1 class="pagina-titulo">Clientes</h1>
        <div class="header-acoes">
            <button class="btn-secondary" onclick="exportarClientes()">
                <i class="fa-solid fa-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icone"><i class="fa-solid fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-valor"><?= number_format($stats['total_clientes']) ?></div>
                <div class="stat-label">Total de Clientes</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icone"><i class="fa-solid fa-user-plus"></i></div>
            <div class="stat-info">
                <div class="stat-valor"><?= number_format($stats['novos_mes'] ?? 0) ?></div>
                <div class="stat-label">Novos (30 dias)</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icone"><i class="fa-solid fa-bag-shopping"></i></div>
            <div class="stat-info">
                <div class="stat-valor"><?= number_format($stats['clientes_compraram']) ?></div>
                <div class="stat-label">Já Compraram</div>
            </div>
        </div>

        <div class="stat-card card-sucesso">
            <div class="stat-icone"><i class="fa-solid fa-dollar-sign"></i></div>
            <div class="stat-info">
                <div class="stat-valor">R$ <?= number_format($stats['total_vendas'], 2, ',', '.') ?></div>
                <div class="stat-label">Total em Vendas</div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filtros-container">
        <form method="GET" class="filtros-form">
            <div class="filtro-item">
                <label>Buscar</label>
                <input type="text" name="busca" placeholder="Nome, email ou telefone..." value="<?= htmlspecialchars($filtroBusca) ?>">
            </div>

            <div class="filtro-item">
                <label>Data Início</label>
                <input type="date" name="data_inicio" value="<?= htmlspecialchars($filtroDataInicio) ?>">
            </div>

            <div class="filtro-item">
                <label>Data Fim</label>
                <input type="date" name="data_fim" value="<?= htmlspecialchars($filtroDataFim) ?>">
            </div>

            <div class="filtro-acoes">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                </button>
                <a href="clientes.php" class="btn-secondary">
                    <i class="fa-solid fa-rotate"></i> Limpar
                </a>
            </div>
        </form>
    </div>

    <!-- TABELA -->
    <table class="tabela-clientes">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Email</th>
                <th>Telefone</th>
                <th>Cadastro</th>
                <th>Pedidos</th>
                <th>Total Compras</th>
                <th>Endereços</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clientes)): ?>
                <tr>
                    <td colspan="8" class="sem-dados">Nenhum cliente encontrado</td>
                </tr>
            <?php else: ?>
                <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td class="cliente-nome">
                            <?= htmlspecialchars($c['nome']) ?>
                        </td>
                        <td class="cliente-email">
                            <?= htmlspecialchars($c['email']) ?>
                        </td>
                        <td class="cliente-telefone">
                            <?= $c['telefone'] ? htmlspecialchars($c['telefone']) : '<span class="muted-text">—</span>' ?>
                        </td>
                        <td class="cliente-data">
                            <?= $c['data_cadastro'] ?>
                        </td>
                        <td class="cliente-pedidos">
                            <span class="badge-pedidos"><?= $c['total_pedidos'] ?></span>
                        </td>
                        <td class="cliente-valor">
                            <strong class="valor-destaque">R$ <?= number_format($c['valor_total_compras'], 2, ',', '.') ?></strong>
                        </td>
                        <td class="cliente-enderecos">
                            <?= $c['enderecos'] ? htmlspecialchars($c['enderecos']) : '<span class="muted-text">Sem endereço</span>' ?>
                        </td>
                        <td class="cliente-acoes">
                            <button class="btn-secondary btn-sm" onclick="verDetalhes('<?= $c['id'] ?>')">
                                <i class="fa-solid fa-eye"></i> Ver
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- MODAL DETALHES -->
<div id="modalDetalhes" class="modal hidden">
    <div class="modal-box modal-lg">
        <h2 id="tituloDetalhes">Detalhes do Cliente</h2>
        <div id="conteudoDetalhes"></div>
        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModalDetalhes()">Fechar</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<style>
/* ===== STATS ===== */
.stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 16px; border-left: 4px solid #3498db; }
.stat-card.card-sucesso { border-left-color: #27ae60; }
.stat-icone { font-size: 26px; color: #3498db; flex-shrink: 0; }
.stat-card.card-sucesso .stat-icone { color: #27ae60; }
.stat-valor { font-size: 26px; font-weight: 700; color: #2c3e50; line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 13px; color: #6c757d; font-weight: 500; }

/* ===== FILTROS ===== */
.filtros-container { background: white; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.filtros-form { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 16px; align-items: end; }
.filtro-item { display: flex; flex-direction: column; }
.filtro-item label { font-size: 13px; font-weight: 600; color: #2c3e50; margin-bottom: 6px; }
.filtro-item input, .filtro-item select { width: 100%; padding: 10px 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; font-family: inherit; color: #2c3e50; }
.filtro-item input:focus, .filtro-item select:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 3px rgba(52,152,219,0.1); }
.filtro-acoes { display: flex; flex-direction: column; gap: 8px; }

/* ===== TABELA ===== */
.tabela-clientes { width: 100%; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-collapse: collapse; }
.tabela-clientes thead { background: #f8f9fa; border-bottom: 2px solid #e9ecef; }
.tabela-clientes thead th { text-align: left; font-size: 12px; text-transform: uppercase; color: #6c757d; font-weight: 600; padding: 16px 12px; letter-spacing: 0.5px; }
.tabela-clientes tbody tr { border-bottom: 1px solid #f1f3f5; transition: background 0.2s; }
.tabela-clientes tbody tr:hover { background: #f8f9fa; }
.tabela-clientes tbody tr:last-child { border-bottom: none; }
.tabela-clientes tbody td { padding: 16px 12px; font-size: 14px; color: #2c3e50; vertical-align: middle; }

.cliente-nome { font-weight: 600; min-width: 160px; }
.cliente-email { color: #5a6c7d; font-size: 13px; }
.cliente-telefone { white-space: nowrap; font-size: 13px; }
.cliente-data { white-space: nowrap; font-size: 13px; color: #6c757d; }
.cliente-pedidos { text-align: center; }
.badge-pedidos { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.cliente-valor { white-space: nowrap; }
.valor-destaque { color: #27ae60; font-size: 15px; }
.cliente-enderecos { max-width: 220px; font-size: 13px; color: #5a6c7d; line-height: 1.4; }
.cliente-acoes { text-align: right; white-space: nowrap; }
.btn-sm { padding: 8px 16px; font-size: 13px; }
.sem-dados { text-align: center; padding: 40px 20px; color: #95a5a6; font-style: italic; font-size: 14px; }
.muted-text { color: #adb5bd; font-style: italic; }

/* ===== MODAL DETALHES ===== */
.detalhes-cliente { padding: 10px 0; }
.detalhes-cliente .secao { margin-bottom: 30px; }
.detalhes-cliente .secao h3 { font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #f1f3f5; }

.tabela-detalhes { width: 100%; border-collapse: collapse; }
.tabela-detalhes td { padding: 12px 0; border-bottom: 1px solid #f1f3f5; font-size: 14px; }
.tabela-detalhes td:first-child { width: 150px; color: #6c757d; }
.tabela-detalhes td:last-child { color: #2c3e50; }

.lista-enderecos { display: grid; gap: 12px; }
.endereco-card { padding: 16px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #3498db; }
.endereco-tipo { font-size: 12px; font-weight: 600; color: #3498db; text-transform: uppercase; margin-bottom: 8px; }
.endereco-texto { font-size: 14px; color: #2c3e50; line-height: 1.6; }

.tabela-mini { width: 100%; border-collapse: collapse; margin-top: 12px; }
.tabela-mini thead { background: #f8f9fa; }
.tabela-mini th { text-align: left; padding: 10px 12px; font-size: 12px; text-transform: uppercase; color: #6c757d; font-weight: 600; border-bottom: 2px solid #e9ecef; }
.tabela-mini td { padding: 10px 12px; border-bottom: 1px solid #f1f3f5; font-size: 13px; color: #2c3e50; }
.tabela-mini tbody tr:last-child td { border-bottom: none; }
.badge-mini { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; color: white; }

.resumo-pedidos { display: flex; gap: 24px; margin-bottom: 16px; }
.resumo-item { background: #f8f9fa; padding: 12px 20px; border-radius: 8px; text-align: center; }
.resumo-item strong { display: block; font-size: 20px; color: #2c3e50; }
.resumo-item span { font-size: 12px; color: #6c757d; }

/* ===== RESPONSIVIDADE ===== */
@media (max-width: 768px) {
    .filtros-form { grid-template-columns: 1fr; }
    .filtro-acoes { flex-direction: row; }
    .tabela-clientes { display: block; overflow-x: auto; font-size: 13px; }
    .tabela-clientes thead th, .tabela-clientes tbody td { padding: 10px 8px; }
    .cliente-enderecos { max-width: 140px; font-size: 12px; }
    .stats-container { grid-template-columns: repeat(2, 1fr); }
}
</style>

<script>
// ========================================
// MODAL
// ========================================
const modalDetalhes = document.getElementById('modalDetalhes');

function fecharModalDetalhes() { modalDetalhes.classList.add('hidden'); }

// ========================================
// VER DETALHES
// ========================================
async function verDetalhes(clienteId) {
    document.getElementById('tituloDetalhes').innerText = 'Detalhes do Cliente';
    document.getElementById('conteudoDetalhes').innerHTML = '<p class="muted">Carregando...</p>';
    modalDetalhes.classList.remove('hidden');

    try {
        const response = await fetch(`../api.php?action=detalhes_cliente&id=${clienteId}`);
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            console.error('JSON inválido:', text.substring(0, 200));
            document.getElementById('conteudoDetalhes').innerHTML = '<p class="muted">Erro ao carregar detalhes</p>';
            return;
        }

        if (!data.success) {
            document.getElementById('conteudoDetalhes').innerHTML = '<p class="muted">Erro ao carregar detalhes</p>';
            return;
        }

        let html = '<div class="detalhes-cliente">';

        // Informações pessoais
        html += `<div class="secao"><h3>Informações Pessoais</h3>
            <table class="tabela-detalhes">
                <tr><td>Nome</td><td>${data.cliente.nome}</td></tr>
                <tr><td>Email</td><td>${data.cliente.email}</td></tr>
                <tr><td>Telefone</td><td>${data.cliente.telefone || 'Não informado'}</td></tr>
                <tr><td>Cadastrado em</td><td>${data.cliente.criado_em}</td></tr>
            </table></div>`;

        // Endereços
        html += `<div class="secao"><h3>Endereços</h3>`;
        if (data.enderecos.length === 0) {
            html += '<p class="muted-text">Nenhum endereço cadastrado</p>';
        } else {
            html += '<div class="lista-enderecos">';
            data.enderecos.forEach(e => {
                html += `<div class="endereco-card">
                    <div class="endereco-tipo">${e.tipo}</div>
                    <div class="endereco-texto">
                        ${e.rua}, ${e.numero || 'S/N'}
                        ${e.complemento ? '<br>' + e.complemento : ''}
                        <br>${e.bairro} - ${e.cidade}/${e.estado}
                        <br>CEP: ${e.cep}
                    </div>
                </div>`;
            });
            html += '</div>';
        }
        html += '</div>';

        // Resumo de pedidos
        html += `<div class="secao"><h3>Histórico de Pedidos</h3>
            <div class="resumo-pedidos">
                <div class="resumo-item">
                    <strong>${data.resumo_pedidos.total}</strong>
                    <span>Pedidos</span>
                </div>
                <div class="resumo-item">
                    <strong>R$ ${data.resumo_pedidos.valor_total}</strong>
                    <span>Total gasto</span>
                </div>
            </div>`;

        if (data.pedidos.length > 0) {
            html += `<table class="tabela-mini">
                <thead><tr><th>Pedido</th><th>Data</th><th>Status</th><th>Valor</th></tr></thead>
                <tbody>`;
            data.pedidos.forEach(p => {
                html += `<tr>
                    <td>#${String(p.id).padStart(6, '0')}</td>
                    <td>${p.data}</td>
                    <td><span class="badge-mini" style="background:${p.status_cor}">${p.status}</span></td>
                    <td>R$ ${p.valor}</td>
                </tr>`;
            });
            html += '</tbody></table>';
        }
        html += '</div></div>';

        document.getElementById('conteudoDetalhes').innerHTML = html;

    } catch (error) {
        console.error(error);
        document.getElementById('conteudoDetalhes').innerHTML = '<p class="muted">Erro ao carregar</p>';
    }
}

// ========================================
// EXPORTAR
// ========================================
function exportarClientes() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = '../api.php?action=exportar_clientes&' + params.toString();
}
</script>