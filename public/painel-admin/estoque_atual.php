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
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroProduto   = $_GET['produto']   ?? '';
$filtroEstoque   = $_GET['estoque']   ?? '';

/**
 * CATEGORIAS
 */
$categorias = $db->query("
    SELECT id, nome
    FROM categorias
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * ESTOQUE ATUAL
 */
$sqlEstoque = "
    SELECT
        ea.id_produto_variacao,
        ea.quantidade,
        p.id   AS produto_id,
        p.nome AS produto_nome,
        p.categoria_id,
        p.preco,
        c.nome AS categoria_nome,
        (
            SELECT GROUP_CONCAT(vv.valor ORDER BY vv.valor SEPARATOR ' / ')
            FROM produto_variacao_valores pvv
            INNER JOIN valores_variacao vv ON pvv.id_valor_variacao = vv.id
            WHERE pvv.id_produto_variacao = pv.id
        ) AS variacao_descricao,
        (
            SELECT url
            FROM produtos_imagens
            WHERE produto_id = p.id
            ORDER BY ordem
            LIMIT 1
        ) AS imagem
    FROM estoque_atual ea
    INNER JOIN produto_variacoes pv ON ea.id_produto_variacao = pv.id
    INNER JOIN produtos          p  ON pv.id_produto          = p.id
    LEFT JOIN  categorias        c  ON p.categoria_id         = c.id
    WHERE 1=1
";

$params = [];

if ($filtroCategoria) {
    $sqlEstoque .= " AND p.categoria_id = :categoria";
    $params[':categoria'] = $filtroCategoria;
}

if ($filtroProduto) {
    $sqlEstoque .= " AND p.nome LIKE :produto";
    $params[':produto'] = '%' . $filtroProduto . '%';
}

if ($filtroEstoque === 'zerado') {
    $sqlEstoque .= " AND ea.quantidade = 0";
} elseif ($filtroEstoque === 'baixo') {
    $sqlEstoque .= " AND ea.quantidade > 0 AND ea.quantidade <= 10";
} elseif ($filtroEstoque === 'disponivel') {
    $sqlEstoque .= " AND ea.quantidade > 10";
}

$sqlEstoque .= " ORDER BY p.nome, variacao_descricao";

$stmt = $db->prepare($sqlEstoque);
$stmt->execute($params);
$estoques = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ESTATÍSTICAS
 */
$stats = $db->query("
    SELECT
        COUNT(*)           AS total_itens,
        SUM(ea.quantidade) AS quantidade_total
    FROM estoque_atual ea
")->fetch(PDO::FETCH_ASSOC);
?>

<div class="main pagina-estoque">

    <div class="pagina-header">
        <h1 class="pagina-titulo">Estoque Atual</h1>
        <div class="header-acoes">
            <a href="movimentacoes_estoque.php" class="btn-secondary">
                <i class="fa-solid fa-chart-bar"></i> Ver Movimentações
            </a>
            <button class="btn-primary" onclick="exportarEstoque()">
                <i class="fa-solid fa-download"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icone"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="stat-info">
                <div class="stat-valor"><?= number_format($stats['total_itens']) ?></div>
                <div class="stat-label">Variações Cadastradas</div>
            </div>
        </div>

        <div class="stat-card card-sucesso">
            <div class="stat-icone"><i class="fa-solid fa-circle-check"></i></div>
            <div class="stat-info">
                <div class="stat-valor"><?= number_format($stats['quantidade_total']) ?></div>
                <div class="stat-label">Unidades em Estoque</div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filtros-container">
        <form method="GET" class="filtros-form">
            <div class="filtro-item">
                <label>Categoria</label>
                <select name="categoria">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filtroCategoria == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-item">
                <label>Produto</label>
                <input type="text" name="produto" placeholder="Nome do produto..." value="<?= htmlspecialchars($filtroProduto) ?>">
            </div>

            <div class="filtro-item">
                <label>Situação</label>
                <select name="estoque">
                    <option value="">Todas</option>
                    <option value="zerado"    <?= $filtroEstoque === 'zerado'    ? 'selected' : '' ?>>Zerado</option>
                    <option value="baixo"     <?= $filtroEstoque === 'baixo'     ? 'selected' : '' ?>>Baixo (≤ 10)</option>
                    <option value="disponivel"<?= $filtroEstoque === 'disponivel'? 'selected' : '' ?>>Disponível (&gt; 10)</option>
                </select>
            </div>

            <div class="filtro-acoes">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                </button>
                <a href="estoque_atual.php" class="btn-secondary">
                    <i class="fa-solid fa-rotate"></i> Limpar
                </a>
            </div>
        </form>
    </div>

    <!-- GRID DE ESTOQUE -->
    <div class="estoque-grid">
        <?php if (empty($estoques)): ?>
            <div class="sem-dados-grid">
                <p>Nenhum item em estoque</p>
            </div>
        <?php else: ?>
            <?php foreach ($estoques as $e): ?>
                <?php
                $quantidadeClass = 'qtd-ok';
                if ($e['quantidade'] == 0) {
                    $quantidadeClass = 'qtd-zero';
                } elseif ($e['quantidade'] <= 10) {
                    $quantidadeClass = 'qtd-baixo';
                }
                $valorTotal = $e['quantidade'] * $e['preco'];
                ?>
                <div class="estoque-card">
                    <div class="estoque-card-imagem">
                        <img src="<?= htmlspecialchars($e['imagem'] ?? '/img/no-image.png') ?>"
                             alt="<?= htmlspecialchars($e['produto_nome']) ?>">
                        <div class="estoque-card-badge <?= $quantidadeClass ?>">
                            <?= number_format($e['quantidade']) ?> un
                        </div>
                    </div>

                    <div class="estoque-card-info">
                        <h3 class="estoque-card-titulo"><?= htmlspecialchars($e['produto_nome']) ?></h3>

                        <?php if ($e['variacao_descricao']): ?>
                            <p class="estoque-card-variacao">
                                <i class="fa-solid fa-palette"></i>
                                <?= htmlspecialchars($e['variacao_descricao']) ?>
                            </p>
                        <?php else: ?>
                            <p class="estoque-card-variacao muted-text">Variação padrão</p>
                        <?php endif; ?>

                        <?php if ($e['categoria_nome']): ?>
                            <p class="estoque-card-categoria">
                                <i class="fa-solid fa-folder"></i>
                                <?= htmlspecialchars($e['categoria_nome']) ?>
                            </p>
                        <?php endif; ?>

                        <div class="estoque-card-precos">
                            <div class="preco-item">
                                <span class="preco-label">Preço Unit.:</span>
                                <span class="preco-valor">R$ <?= number_format($e['preco'], 2, ',', '.') ?></span>
                            </div>
                            <div class="preco-item">
                                <span class="preco-label">Valor Total:</span>
                                <span class="preco-valor destaque">R$ <?= number_format($valorTotal, 2, ',', '.') ?></span>
                            </div>
                        </div>

                        <button class="btn-historico" onclick="verHistorico(<?= $e['id_produto_variacao'] ?>, '<?= htmlspecialchars($e['produto_nome'] . ' - ' . ($e['variacao_descricao'] ?? 'Padrão'), ENT_QUOTES) ?>')">
                            <i class="fa-solid fa-chart-line"></i> Ver Histórico
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- MODAL HISTÓRICO -->
<div id="modalHistorico" class="modal hidden">
    <div class="modal-box modal-lg">
        <h2 id="tituloHistorico">Histórico de Movimentações</h2>
        <div id="conteudoHistorico"></div>
        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModalHistorico()">Fechar</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<style>
/* ===== STATS ===== */
.stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 16px; border-left: 4px solid #3498db; }
.stat-card.card-sucesso { border-left-color: #27ae60; }
.stat-icone { font-size: 26px; color: #3498db; flex-shrink: 0; }
.stat-card.card-sucesso .stat-icone { color: #27ae60; }
.stat-valor { font-size: 26px; font-weight: 700; color: #2c3e50; line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 13px; color: #6c757d; font-weight: 500; }

/* ===== FILTROS ===== */
.filtros-container { background: white; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.filtros-form { display: grid; grid-template-columns: 1fr 2fr 1fr auto; gap: 16px; align-items: end; }
.filtro-item { display: flex; flex-direction: column; }
.filtro-item label { font-size: 13px; font-weight: 600; color: #2c3e50; margin-bottom: 6px; }
.filtro-item input, .filtro-item select { width: 100%; padding: 10px 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; font-family: inherit; color: #2c3e50; }
.filtro-item input:focus, .filtro-item select:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 3px rgba(52,152,219,0.1); }
.filtro-acoes { display: flex; flex-direction: column; gap: 8px; }

/* ===== GRID DE ESTOQUE ===== */
.estoque-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; }

.estoque-card { background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #f1f3f5; transition: all 0.3s; }
.estoque-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.12); transform: translateY(-4px); }

.estoque-card-imagem { position: relative; width: 100%; height: 200px; overflow: hidden; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); }
.estoque-card-imagem img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
.estoque-card:hover .estoque-card-imagem img { transform: scale(1.05); }

.estoque-card-badge { position: absolute; top: 12px; right: 12px; padding: 8px 14px; border-radius: 20px; font-weight: 700; font-size: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); color: white; }
.qtd-ok    { background: linear-gradient(135deg, #27ae60 0%, #229954 100%); }
.qtd-baixo { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
.qtd-zero  { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }

.estoque-card-info { padding: 20px; }
.estoque-card-titulo { font-size: 16px; font-weight: 600; color: #2c3e50; margin: 0 0 10px 0; line-height: 1.3; }
.estoque-card-variacao, .estoque-card-categoria { font-size: 13px; color: #6c757d; margin: 6px 0; display: flex; align-items: center; gap: 6px; }
.muted-text { color: #adb5bd; font-style: italic; }

.estoque-card-precos { margin: 14px 0; padding: 14px; background: #f8f9fa; border-radius: 10px; }
.preco-item { display: flex; justify-content: space-between; align-items: center; margin: 6px 0; }
.preco-item:first-child { margin-top: 0; }
.preco-item:last-child { margin-bottom: 0; }
.preco-label { font-size: 13px; color: #6c757d; font-weight: 500; }
.preco-valor { font-size: 14px; font-weight: 600; color: #2c3e50; }
.preco-valor.destaque { font-size: 15px; color: #27ae60; }

.btn-historico { width: 100%; padding: 11px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-historico:hover { background: linear-gradient(135deg, #2980b9 0%, #21618c 100%); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(52,152,219,0.3); }

/* ===== SEM DADOS ===== */
.sem-dados-grid { grid-column: 1/-1; text-align: center; padding: 80px 20px; background: white; border-radius: 14px; border: 2px dashed #cbd5e0; color: #6c757d; font-size: 16px; }

/* ===== MODAL HISTÓRICO ===== */
.tabela-historico { width: 100%; border-collapse: collapse; margin-top: 8px; }
.tabela-historico thead { background: #f8f9fa; }
.tabela-historico th { text-align: left; padding: 12px 14px; font-size: 12px; text-transform: uppercase; color: #6c757d; font-weight: 600; letter-spacing: 0.5px; border-bottom: 2px solid #e9ecef; }
.tabela-historico td { padding: 12px 14px; border-bottom: 1px solid #f1f3f5; font-size: 14px; color: #2c3e50; }
.tabela-historico tbody tr:last-child td { border-bottom: none; }
.tabela-historico tbody tr:hover { background: #f8f9fa; }

.badge-tipo { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.badge-tipo-entrada { background: #d4edda; color: #155724; }
.badge-tipo-saida, .badge-tipo-saída { background: #f8d7da; color: #721c24; }
.badge-tipo-ajuste { background: #fff3cd; color: #856404; }
.qtd-entrada { color: #27ae60; font-weight: 700; }
.qtd-saida, .qtd-saída { color: #e74c3c; font-weight: 700; }
.qtd-ajuste { color: #f39c12; font-weight: 700; }
.muted { color: #95a5a6; font-size: 14px; text-align: center; padding: 40px 20px; font-style: italic; }

/* ===== RESPONSIVIDADE ===== */
@media (max-width: 1024px) {
    .filtros-form { grid-template-columns: repeat(2, 1fr); }
    .filtro-acoes { grid-column: 1/-1; flex-direction: row; }
    .estoque-grid { grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
}
@media (max-width: 768px) {
    .filtros-form { grid-template-columns: 1fr; }
    .filtro-acoes { flex-direction: row; }
    .estoque-grid { grid-template-columns: 1fr; }
    .stats-container { grid-template-columns: 1fr 1fr; }
}
</style>

<script>
// ========================================
// MODAL HISTÓRICO
// ========================================
const modalHistorico = document.getElementById('modalHistorico');

function fecharModalHistorico() { modalHistorico.classList.add('hidden'); }

async function verHistorico(variacaoId, nomeProduto) {
    document.getElementById('tituloHistorico').innerText = `Histórico — ${nomeProduto}`;
    document.getElementById('conteudoHistorico').innerHTML = '<p class="muted">Carregando...</p>';
    modalHistorico.classList.remove('hidden');

    try {
        const response = await fetch(`../api.php?action=historico_movimentacoes&variacao=${variacaoId}`);
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            console.error('JSON inválido:', text.substring(0, 200));
            document.getElementById('conteudoHistorico').innerHTML = '<p class="muted">Erro ao carregar histórico</p>';
            return;
        }

        if (!data.success || !data.movimentacoes || data.movimentacoes.length === 0) {
            document.getElementById('conteudoHistorico').innerHTML = '<p class="muted">Nenhuma movimentação encontrada</p>';
            return;
        }

        let html = '<table class="tabela-historico">';
        html += '<thead><tr><th>Data/Hora</th><th>Tipo</th><th>Qtd</th><th>Motivo</th></tr></thead><tbody>';

        data.movimentacoes.forEach(m => {
            let sinal = '', classe = 'qtd-ajuste';
            if (m.tipo_operacao === 'entrada') { sinal = '+'; classe = 'qtd-entrada'; }
            else if (m.tipo_operacao === 'saída' || m.tipo_operacao === 'saida') { sinal = '−'; classe = 'qtd-saida'; }

            html += `<tr>
                <td>${m.criado_em}</td>
                <td><span class="badge-tipo badge-tipo-${m.tipo_operacao}">${m.tipo_nome}</span></td>
                <td><span class="${classe}">${sinal}${Math.abs(m.quantidade)}</span></td>
                <td>${m.motivo || '—'}</td>
            </tr>`;
        });

        html += '</tbody></table>';
        document.getElementById('conteudoHistorico').innerHTML = html;

    } catch (error) {
        console.error(error);
        document.getElementById('conteudoHistorico').innerHTML = '<p class="muted">Erro ao carregar histórico</p>';
    }
}

// ========================================
// EXPORTAR
// ========================================
function exportarEstoque() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = '../api.php?action=exportar_estoque&' + params.toString();
}
</script>