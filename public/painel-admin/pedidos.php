<?php
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

$filtroStatus     = $_GET['status']      ?? '';
$filtroDataInicio = $_GET['data_inicio'] ?? '';
$filtroDataFim    = $_GET['data_fim']    ?? '';
$filtroBusca      = $_GET['busca']       ?? '';

$statusPedido = $db->query("
    SELECT id, descricao, cor, icone
    FROM status_pedido
    WHERE id < 90
    ORDER BY ordem
")->fetchAll(PDO::FETCH_ASSOC);

$sqlPedidos = "
    SELECT
        v.id,
        v.valor_total,
        v.data_venda,
        v.status_pedido_id,
        sp.descricao  AS status_descricao,
        sp.cor        AS status_cor,
        sp.icone      AS status_icone,
        spg.descricao AS status_pagamento,
        u.nome        AS cliente_nome,
        u.email       AS cliente_email,
        (SELECT COUNT(*) FROM vendas_itens WHERE venda_id = v.id) AS qtd_itens
    FROM vendas v
    LEFT JOIN status_pedido    sp  ON v.status_pedido_id    = sp.id
    LEFT JOIN status_pagamento spg ON v.status_pagamento_id = spg.id
    LEFT JOIN usuarios         u   ON v.usuario_id          = u.id
    WHERE 1=1
";

$params = [];

if ($filtroStatus) {
    $sqlPedidos .= " AND v.status_pedido_id = :status";
    $params['status'] = $filtroStatus;
}
if ($filtroDataInicio) {
    $sqlPedidos .= " AND DATE(v.data_venda) >= :data_inicio";
    $params['data_inicio'] = $filtroDataInicio;
}
if ($filtroDataFim) {
    $sqlPedidos .= " AND DATE(v.data_venda) <= :data_fim";
    $params['data_fim'] = $filtroDataFim;
}
if ($filtroBusca) {
    $sqlPedidos .= " AND (u.nome LIKE :busca OR u.email LIKE :busca OR CAST(v.id AS CHAR) LIKE :busca)";
    $params['busca'] = '%' . $filtroBusca . '%';
}

$sqlPedidos .= " ORDER BY v.data_venda DESC LIMIT 100";

$stmt = $db->prepare($sqlPedidos);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = $db->query("
    SELECT
        COUNT(*)                                                    AS total_pedidos,
        SUM(CASE WHEN status_pedido_id = 1 THEN 1 ELSE 0 END)     AS aguardando_pagamento,
        SUM(CASE WHEN status_pedido_id = 3 THEN 1 ELSE 0 END)     AS preparando,
        SUM(CASE WHEN status_pedido_id IN (4,5) THEN 1 ELSE 0 END) AS em_transito,
        COALESCE(SUM(valor_total), 0)                              AS valor_total
    FROM vendas
    WHERE data_venda >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch(PDO::FETCH_ASSOC);

// IDs dos pedidos atuais para passar ao polling JS
$idsPedidos = array_column($pedidos, 'id');
?>

<div class="main pagina-pedidos">

    <!-- TOAST de notificação em tempo real -->
    <div id="toastPagamento" class="toast-pagamento hidden">
        <span class="toast-icone">🥑</span>
        <div class="toast-texto">
            <strong id="toastTitulo">Pagamento confirmado!</strong>
            <span id="toastSub">Pedido #000000 foi pago via AbacatePay</span>
        </div>
        <button class="toast-fechar" onclick="this.closest('.toast-pagamento').classList.add('hidden')">✕</button>
    </div>

    <div class="pagina-header">
        <div style="display:flex;align-items:center;gap:12px">
            <h1 class="pagina-titulo">Pedidos</h1>
            <!-- Badge de novos pagamentos -->
            <span id="badgeNovos" class="badge-novos hidden">0 novo(s)</span>
        </div>
        <div class="header-acoes">
            <button class="btn-secondary" onclick="exportarPedidos()">
                <i class="fa-solid fa-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icone"><i class="fa-solid fa-box"></i></div>
            <div class="stat-info">
                <div class="stat-valor"><?= number_format($stats['total_pedidos']) ?></div>
                <div class="stat-label">Pedidos (30 dias)</div>
            </div>
        </div>
        <div class="stat-card card-alerta">
            <div class="stat-icone"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-valor" id="statAguardando"><?= number_format($stats['aguardando_pagamento']) ?></div>
                <div class="stat-label">Aguardando Pagamento</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icone"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="stat-info">
                <div class="stat-valor"><?= number_format($stats['preparando']) ?></div>
                <div class="stat-label">Preparando</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icone"><i class="fa-solid fa-truck"></i></div>
            <div class="stat-info">
                <div class="stat-valor"><?= number_format($stats['em_transito']) ?></div>
                <div class="stat-label">Em Trânsito</div>
            </div>
        </div>
        <div class="stat-card card-sucesso">
            <div class="stat-icone"><i class="fa-solid fa-dollar-sign"></i></div>
            <div class="stat-info">
                <div class="stat-valor">R$ <?= number_format($stats['valor_total'], 2, ',', '.') ?></div>
                <div class="stat-label">Valor Total</div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filtros-container">
        <form method="GET" class="filtros-form">
            <div class="filtro-item">
                <label>Status</label>
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach ($statusPedido as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $filtroStatus == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['descricao']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-item">
                <label>Buscar</label>
                <input type="text" name="busca" placeholder="Nome, email ou #pedido..." value="<?= htmlspecialchars($filtroBusca) ?>">
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
                <a href="pedidos.php" class="btn-secondary">
                    <i class="fa-solid fa-rotate"></i> Limpar
                </a>
            </div>
        </form>
    </div>

    <!-- GRID DE PEDIDOS -->
    <div class="pedidos-grid" id="pedidosGrid">
        <?php if (empty($pedidos)): ?>
            <div class="sem-dados-grid"><p>Nenhum pedido encontrado</p></div>
        <?php else: ?>
            <?php foreach ($pedidos as $p): ?>
                <div class="pedido-card" id="card-<?= $p['id'] ?>" data-status-id="<?= $p['status_pedido_id'] ?>">
                    <div class="pedido-card-header">
                        <div class="pedido-numero">
                            <span class="label">Pedido</span>
                            <span class="numero">#<?= str_pad($p['id'], 6, '0', STR_PAD_LEFT) ?></span>
                        </div>
                        <span class="pedido-badge badge-status-<?= $p['id'] ?>"
                              style="background:<?= htmlspecialchars($p['status_cor']) ?>">
                            <?= htmlspecialchars($p['status_icone'] ?? '') ?>
                            <?= htmlspecialchars($p['status_descricao']) ?>
                        </span>
                    </div>
                    <div class="pedido-card-body">
                        <div class="pedido-info-item">
                            <span class="info-label">Cliente</span>
                            <span class="info-valor"><?= htmlspecialchars($p['cliente_nome'] ?? '—') ?></span>
                        </div>
                        <div class="pedido-info-item">
                            <span class="info-label">Email</span>
                            <span class="info-valor"><?= htmlspecialchars($p['cliente_email'] ?? '—') ?></span>
                        </div>
                        <div class="pedido-info-item">
                            <span class="info-label">Data</span>
                            <span class="info-valor"><?= date('d/m/Y H:i', strtotime($p['data_venda'])) ?></span>
                        </div>
                        <div class="pedido-info-item">
                            <span class="info-label">Itens</span>
                            <span class="info-valor"><?= $p['qtd_itens'] ?> produto(s)</span>
                        </div>
                        <div class="pedido-info-item">
                            <span class="info-label">Pagamento</span>
                            <span class="info-valor pagamento-status-<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['status_pagamento'] ?? 'Não informado') ?>
                            </span>
                        </div>
                        <div class="pedido-valor-total">
                            <span class="label">Valor Total</span>
                            <span class="valor">R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></span>
                        </div>
                    </div>
                    <div class="pedido-card-footer">
                        <button class="btn-secondary btn-sm" onclick="verDetalhes(<?= $p['id'] ?>)">
                            <i class="fa-solid fa-eye"></i> Ver Detalhes
                        </button>
                        <button class="btn-primary btn-sm" onclick="alterarStatus(<?= $p['id'] ?>, '<?= htmlspecialchars($p['status_descricao'], ENT_QUOTES) ?>')">
                            <i class="fa-solid fa-rotate"></i> Alterar Status
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- MODAL DETALHES -->
<div id="modalDetalhes" class="modal hidden">
    <div class="modal-box modal-lg">
        <h2 id="tituloDetalhes">Detalhes do Pedido</h2>
        <div id="conteudoDetalhes"></div>
        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModalDetalhes()">Fechar</button>
        </div>
    </div>
</div>

<!-- MODAL ALTERAR STATUS -->
<div id="modalStatus" class="modal hidden">
    <div class="modal-box">
        <h2>Alterar Status do Pedido</h2>
        <input type="hidden" id="pedido_id">
        <label>Status Atual</label>
        <input type="text" id="status_atual" disabled>
        <label>Novo Status <span class="obrigatorio">*</span></label>
        <select id="novo_status">
            <option value="">Selecione...</option>
            <?php foreach ($statusPedido as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['descricao']) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Observação</label>
        <textarea id="status_observacao" placeholder="Motivo da alteração (opcional)"></textarea>
        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModalStatus()">Cancelar</button>
            <button class="btn-primary" onclick="salvarStatus()">
                <i class="fa-solid fa-floppy-disk"></i> Salvar
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<style>
/* ===== STATS ===== */
.stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 16px; border-left: 4px solid #3498db; }
.stat-card.card-alerta { border-left-color: #f39c12; }
.stat-card.card-sucesso { border-left-color: #27ae60; }
.stat-icone { font-size: 28px; color: #3498db; flex-shrink: 0; }
.stat-card.card-alerta .stat-icone { color: #f39c12; }
.stat-card.card-sucesso .stat-icone { color: #27ae60; }
.stat-valor { font-size: 26px; font-weight: 700; color: #2c3e50; line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 13px; color: #6c757d; font-weight: 500; }

/* ===== BADGE NOVOS ===== */
.badge-novos {
    background: #e74c3c; color: white;
    padding: 4px 12px; border-radius: 20px;
    font-size: 12px; font-weight: 700;
    animation: pulsar 1.5s infinite;
}
.badge-novos.hidden { display: none; }
@keyframes pulsar {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.8; transform: scale(1.05); }
}

/* ===== TOAST ===== */
.toast-pagamento {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    background: white; border-radius: 14px;
    padding: 16px 20px;
    display: flex; align-items: center; gap: 14px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    border-left: 4px solid #27ae60;
    min-width: 320px; max-width: 420px;
    animation: slideIn 0.4s ease;
}
.toast-pagamento.hidden { display: none; }
@keyframes slideIn {
    from { transform: translateX(120%); opacity: 0; }
    to   { transform: translateX(0);   opacity: 1; }
}
.toast-icone { font-size: 2rem; flex-shrink: 0; }
.toast-texto { flex: 1; }
.toast-texto strong { display: block; font-size: 14px; color: #2c3e50; margin-bottom: 2px; }
.toast-texto span   { font-size: 13px; color: #6c757d; }
.toast-fechar {
    background: none; border: none; font-size: 16px;
    color: #adb5bd; cursor: pointer; padding: 4px;
    line-height: 1; flex-shrink: 0;
}
.toast-fechar:hover { color: #495057; }

/* ===== CARD ATUALIZADO (flash visual) ===== */
.pedido-card.atualizado {
    animation: flashVerde 1.5s ease;
}
@keyframes flashVerde {
    0%   { box-shadow: 0 0 0 3px #27ae6033; }
    50%  { box-shadow: 0 0 0 6px #27ae6066; background: #f0fff4; }
    100% { box-shadow: 0 2px 8px rgba(0,0,0,0.06); background: white; }
}

/* ===== FILTROS ===== */
.filtros-container { background: white; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.filtros-form { display: grid; grid-template-columns: repeat(4, 1fr) auto; gap: 16px; align-items: end; }
.filtro-item { display: flex; flex-direction: column; }
.filtro-item label { font-size: 13px; font-weight: 600; color: #2c3e50; margin-bottom: 6px; }
.filtro-item input, .filtro-item select { width: 100%; padding: 10px 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; font-family: inherit; color: #2c3e50; }
.filtro-item input:focus, .filtro-item select:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 3px rgba(52,152,219,0.1); }
.filtro-acoes { display: flex; flex-direction: column; gap: 8px; }

/* ===== GRID ===== */
.pedidos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 24px; }
.pedido-card { background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #f1f3f5; transition: box-shadow 0.3s, transform 0.3s; }
.pedido-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.12); transform: translateY(-4px); }

.pedido-card-header { padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; justify-content: space-between; align-items: center; }
.pedido-numero { display: flex; flex-direction: column; }
.pedido-numero .label { font-size: 11px; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.5px; }
.pedido-numero .numero { font-size: 24px; font-weight: 700; }
.pedido-badge { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; box-shadow: 0 2px 8px rgba(0,0,0,0.2); color: white; }

.pedido-card-body { padding: 20px; }
.pedido-info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f3f5; }
.pedido-info-item:last-of-type { border-bottom: none; }
.info-label { font-size: 13px; color: #6c757d; font-weight: 500; }
.info-valor  { font-size: 13px; color: #2c3e50; font-weight: 600; text-align: right; }
.pedido-valor-total { margin-top: 16px; padding-top: 16px; border-top: 2px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
.pedido-valor-total .label { font-size: 14px; color: #6c757d; font-weight: 600; }
.pedido-valor-total .valor { font-size: 20px; color: #27ae60; font-weight: 700; }
.pedido-card-footer { padding: 16px 20px; background: #f8f9fa; border-top: 1px solid #e9ecef; display: flex; gap: 8px; }
.btn-sm { padding: 8px 16px; font-size: 13px; }
.pedido-card-footer .btn-sm { flex: 1; }

.sem-dados-grid { grid-column: 1/-1; text-align: center; padding: 60px 20px; background: white; border-radius: 14px; border: 2px dashed #cbd5e0; color: #6c757d; font-size: 16px; }

/* ===== MODAL DETALHES ===== */
.detalhes-pedido .secao { margin-bottom: 30px; }
.detalhes-pedido .secao h3 { font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #f1f3f5; }
.tabela-detalhes { width: 100%; border-collapse: collapse; }
.tabela-detalhes td { padding: 12px 0; border-bottom: 1px solid #f1f3f5; }
.tabela-detalhes td:first-child { width: 150px; color: #6c757d; }
.tabela-itens-pedido { width: 100%; border-collapse: collapse; margin-top: 12px; }
.tabela-itens-pedido thead { background: #f8f9fa; }
.tabela-itens-pedido th { text-align: left; padding: 12px; font-size: 12px; text-transform: uppercase; color: #6c757d; font-weight: 600; letter-spacing: 0.5px; border-bottom: 2px solid #e9ecef; }
.tabela-itens-pedido td { padding: 12px; border-bottom: 1px solid #f1f3f5; font-size: 14px; color: #2c3e50; }
.tabela-itens-pedido tbody tr:last-child td { border-bottom: none; }
.tabela-itens-pedido small { color: #6c757d; font-size: 12px; }
.timeline { position: relative; padding-left: 30px; }
.timeline::before { content: ''; position: absolute; left: 8px; top: 0; bottom: 0; width: 2px; background: #e9ecef; }
.timeline-item { position: relative; padding-bottom: 20px; }
.timeline-item::before { content: ''; position: absolute; left: -26px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: #3498db; border: 3px solid white; box-shadow: 0 0 0 2px #e9ecef; }
.timeline-item:last-child { padding-bottom: 0; }
.timeline-data { font-size: 12px; color: #6c757d; margin-bottom: 4px; }
.timeline-status { font-size: 14px; font-weight: 600; color: #2c3e50; margin-bottom: 4px; }
.timeline-obs { font-size: 13px; color: #5a6c7d; font-style: italic; }

@media (max-width: 768px) {
    .filtros-form { grid-template-columns: 1fr; }
    .filtro-acoes { flex-direction: row; }
    .pedidos-grid { grid-template-columns: 1fr; }
    .pedido-card-header { flex-direction: column; gap: 12px; align-items: flex-start; }
    .pedido-card-footer { flex-direction: column; }
    .pedido-card-footer .btn-sm { width: 100%; }
    .stats-container { grid-template-columns: repeat(2, 1fr); }
    .toast-pagamento { left: 16px; right: 16px; min-width: unset; bottom: 16px; }
}
</style>

<script>
// ============================================================
// IDs dos pedidos visíveis na tela (PHP → JS)
// ============================================================
const IDS_PEDIDOS_VISIVEIS = <?= json_encode($idsPedidos) ?>;

// Estado local: status_pedido_id de cada pedido ao carregar
const estadoAtual = {};
<?php foreach ($pedidos as $p): ?>
estadoAtual[<?= $p['id'] ?>] = <?= (int)$p['status_pedido_id'] ?>;
<?php endforeach; ?>

// ============================================================
// POLLING EM TEMPO REAL
// Intervalo: 15 segundos
// ============================================================
const POLL_INTERVAL = 15000;

async function pollStatus() {
    if (IDS_PEDIDOS_VISIVEIS.length === 0) return;

    try {
        const ids = IDS_PEDIDOS_VISIVEIS.join(',');
        const resp = await fetch(`../api.php?action=polling_pedidos&ids=${ids}`);
        const data = await resp.json();

        if (!data.success || !data.pedidos) return;

        let novosCount = 0;

        data.pedidos.forEach(p => {
            const anterior = estadoAtual[p.id];

            // Detectar mudança de status
            if (anterior !== undefined && anterior !== p.status_pedido_id) {
                estadoAtual[p.id] = p.status_pedido_id;
                novosCount++;

                // Atualizar badge de status no card
                atualizarCardStatus(p);

                // Mostrar toast se for pagamento confirmado
                // (detectamos pelo nome do status)
                const desc = (p.status_descricao || '').toLowerCase();
                if (desc.includes('pago') || desc.includes('aprovado') || desc.includes('processamento')) {
                    mostrarToast(p);
                }
            }
        });

        // Badge no topo
        if (novosCount > 0) {
            const badge = document.getElementById('badgeNovos');
            badge.textContent = `${novosCount} atualização(ões)`;
            badge.classList.remove('hidden');
            setTimeout(() => badge.classList.add('hidden'), 8000);
        }

    } catch (e) {
        // Silencioso — não interrompe o painel se a rede falhar
        console.warn('[Polling] Falha:', e.message);
    }
}

// ── Atualizar visualmente o card ──────────────────────────────
function atualizarCardStatus(p) {
    const card  = document.getElementById(`card-${p.id}`);
    if (!card) return;

    // Atualizar badge de status
    const badge = card.querySelector(`.badge-status-${p.id}`);
    if (badge) {
        badge.style.background = p.status_cor || '#6c757d';
        badge.textContent      = `${p.status_icone || ''} ${p.status_descricao}`.trim();
    }

    // Atualizar status de pagamento
    const pagEl = card.querySelector(`.pagamento-status-${p.id}`);
    if (pagEl && p.status_pagamento) {
        pagEl.textContent = p.status_pagamento;
    }

    // Atualizar data-status-id
    card.dataset.statusId = p.status_pedido_id;

    // Flash visual verde
    card.classList.remove('atualizado');
    void card.offsetWidth; // reflow para reiniciar animação
    card.classList.add('atualizado');
    setTimeout(() => card.classList.remove('atualizado'), 1600);
}

// ── Toast de notificação ───────────────────────────────────────
let toastTimer = null;

function mostrarToast(p) {
    const toast = document.getElementById('toastPagamento');
    const num   = String(p.id).padStart(6, '0');

    document.getElementById('toastTitulo').textContent = '✅ Pagamento confirmado!';
    document.getElementById('toastSub').textContent    = `Pedido #${num} — ${p.status_descricao}`;

    toast.classList.remove('hidden');

    // Auto-fechar após 7s
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.add('hidden'), 7000);
}

// Iniciar polling
setInterval(pollStatus, POLL_INTERVAL);
pollStatus(); // Disparar imediatamente ao carregar

// ============================================================
// MODAIS
// ============================================================
const modalDetalhes = document.getElementById('modalDetalhes');
const modalStatus   = document.getElementById('modalStatus');

function fecharModalDetalhes() { modalDetalhes.classList.add('hidden'); }
function fecharModalStatus()   { modalStatus.classList.add('hidden'); }

// ============================================================
// VER DETALHES
// ============================================================
async function verDetalhes(pedidoId) {
    document.getElementById('tituloDetalhes').innerText = `Pedido #${String(pedidoId).padStart(6, '0')}`;
    document.getElementById('conteudoDetalhes').innerHTML = '<p class="muted">Carregando...</p>';
    modalDetalhes.classList.remove('hidden');

    try {
        const response = await fetch(`../api.php?action=detalhes_pedido&id=${pedidoId}`);
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            document.getElementById('conteudoDetalhes').innerHTML = '<p class="muted">Erro ao carregar detalhes</p>';
            return;
        }

        if (!data.success) {
            document.getElementById('conteudoDetalhes').innerHTML = '<p class="muted">Erro ao carregar detalhes</p>';
            return;
        }

        let html = '<div class="detalhes-pedido">';

        html += `<div class="secao"><h3>Informações do Pedido</h3>
            <table class="tabela-detalhes">
                <tr><td><strong>Cliente</strong></td><td>${data.pedido.cliente_nome}</td></tr>
                <tr><td><strong>Email</strong></td><td>${data.pedido.cliente_email}</td></tr>
                <tr><td><strong>Data</strong></td><td>${data.pedido.data_venda}</td></tr>
                <tr><td><strong>Status</strong></td><td>
                    <span style="background:${data.pedido.status_cor};padding:4px 12px;border-radius:12px;color:white;font-size:12px;font-weight:600;">
                        ${data.pedido.status_descricao}
                    </span>
                </td></tr>
            </table></div>`;

        html += `<div class="secao"><h3>Itens do Pedido</h3>
            <table class="tabela-itens-pedido">
                <thead><tr><th>Produto</th><th>Qtd</th><th>Preço Unit.</th><th>Subtotal</th></tr></thead>
                <tbody>`;
        data.itens.forEach(item => {
            html += `<tr>
                <td>${item.produto_nome}${item.variacao ? '<br><small>' + item.variacao + '</small>' : ''}</td>
                <td>${item.quantidade}</td>
                <td>R$ ${item.preco_unitario}</td>
                <td>R$ ${item.subtotal}</td>
            </tr>`;
        });
        html += `</tbody></table></div>`;

        html += `<div class="secao"><h3>Histórico de Status</h3><div class="timeline">`;
        data.historico.forEach(h => {
            html += `<div class="timeline-item">
                <div class="timeline-data">${h.data_alteracao}</div>
                <div class="timeline-status">${h.status_descricao}</div>
                ${h.observacao ? `<div class="timeline-obs">${h.observacao}</div>` : ''}
            </div>`;
        });
        html += `</div></div></div>`;

        document.getElementById('conteudoDetalhes').innerHTML = html;

    } catch (error) {
        document.getElementById('conteudoDetalhes').innerHTML = '<p class="muted">Erro ao carregar</p>';
    }
}

// ============================================================
// ALTERAR STATUS
// ============================================================
function alterarStatus(pedidoId, statusAtual) {
    document.getElementById('pedido_id').value         = pedidoId;
    document.getElementById('status_atual').value      = statusAtual;
    document.getElementById('novo_status').value       = '';
    document.getElementById('status_observacao').value = '';
    modalStatus.classList.remove('hidden');
}

async function salvarStatus() {
    const pedidoId   = document.getElementById('pedido_id').value;
    const novoStatus = document.getElementById('novo_status').value;
    const observacao = document.getElementById('status_observacao').value;

    if (!novoStatus) { alert('Selecione o novo status'); return; }

    try {
        const response = await fetch('../api.php?action=alterar_status_pedido', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ pedido_id: pedidoId, status_pedido_id: novoStatus, observacao })
        });
        const data = await response.json();

        if (data.success) {
            fecharModalStatus();
            // Forçar poll imediato para atualizar o card
            await pollStatus();
            location.reload();
        } else {
            alert(data.message || 'Erro ao alterar status');
        }
    } catch (error) {
        alert('Erro ao alterar status');
    }
}

// ============================================================
// EXPORTAR
// ============================================================
function exportarPedidos() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = '../api.php?action=exportar_pedidos&' + params.toString();
}
</script>