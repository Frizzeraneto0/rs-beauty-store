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
$filtroTipo       = $_GET['tipo']        ?? '';
$filtroProduto    = $_GET['produto']     ?? '';
$filtroDataInicio = $_GET['data_inicio'] ?? '';
$filtroDataFim    = $_GET['data_fim']    ?? '';

/**
 * TIPOS DE MOVIMENTAÇÃO
 */
$tiposMovimentacao = $db->query("
    SELECT id, descricao
    FROM tipo_movimentacao
    ORDER BY descricao
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * TODOS OS PRODUTOS (para o modal)
 */
$todosProdutos = $db->query("
    SELECT id, nome
    FROM produtos
    WHERE ativo = 1
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * MOVIMENTAÇÕES
 */
$sqlMovimentacoes = "
    SELECT
        me.id,
        me.produto_variacao_id,
        me.tipo_id,
        me.quantidade,
        me.motivo,
        me.criado_em,
        me.venda_item_id,
        me.compra_itens_variacoes_id,
        p.nome AS produto_nome,
        (
            SELECT GROUP_CONCAT(vv.valor ORDER BY vv.valor SEPARATOR ' / ')
            FROM produto_variacao_valores pvv
            INNER JOIN valores_variacao vv ON pvv.id_valor_variacao = vv.id
            WHERE pvv.id_produto_variacao = pv.id
        ) AS variacao_descricao,
        tm.descricao        AS tipo_nome,
        LOWER(tm.descricao) AS tipo_operacao
    FROM movimentacoes_estoque me
    INNER JOIN produto_variacoes  pv ON me.produto_variacao_id = pv.id
    INNER JOIN produtos           p  ON pv.id_produto          = p.id
    INNER JOIN tipo_movimentacao  tm ON me.tipo_id             = tm.id
    WHERE 1=1
";

$params = [];

if ($filtroTipo) {
    $sqlMovimentacoes .= " AND me.tipo_id = :tipo";
    $params[':tipo'] = $filtroTipo;
}

if ($filtroProduto) {
    $sqlMovimentacoes .= " AND p.nome LIKE :produto";
    $params[':produto'] = '%' . $filtroProduto . '%';
}

if ($filtroDataInicio) {
    $sqlMovimentacoes .= " AND DATE(me.criado_em) >= :data_inicio";
    $params[':data_inicio'] = $filtroDataInicio;
}

if ($filtroDataFim) {
    $sqlMovimentacoes .= " AND DATE(me.criado_em) <= :data_fim";
    $params[':data_fim'] = $filtroDataFim;
}

$sqlMovimentacoes .= " ORDER BY me.criado_em DESC LIMIT 200";

$stmt = $db->prepare($sqlMovimentacoes);
$stmt->execute($params);
$movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main pagina-movimentacoes">

    <div class="pagina-header">
        <h1 class="pagina-titulo">Movimentações de Estoque</h1>
        <button class="btn-primary" onclick="abrirModalNova()">
            <i class="fa-solid fa-plus"></i> Nova Movimentação
        </button>
    </div>

    <!-- FILTROS -->
    <div class="filtros-container">
        <form method="GET" class="filtros-form">
            <div class="filtro-item">
                <label>Tipo de Movimentação</label>
                <select name="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tiposMovimentacao as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $filtroTipo == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['descricao']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-item">
                <label>Produto</label>
                <input type="text" name="produto" placeholder="Digite o nome do produto..."
                       value="<?= htmlspecialchars($filtroProduto) ?>">
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
                <a href="movimentacoes_estoque.php" class="btn-secondary">
                    <i class="fa-solid fa-rotate"></i> Limpar
                </a>
            </div>
        </form>
    </div>

    <!-- TABELA -->
    <table class="tabela-movimentacoes">
        <thead>
            <tr>
                <th>Data/Hora</th>
                <th>Produto</th>
                <th>Variação</th>
                <th>Tipo</th>
                <th>Quantidade</th>
                <th>Motivo</th>
                <th>Referência</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movimentacoes)): ?>
                <tr>
                    <td colspan="7" class="sem-dados">Nenhuma movimentação encontrada</td>
                </tr>
            <?php else: ?>
                <?php foreach ($movimentacoes as $m): ?>
                    <tr>
                        <td class="mov-data">
                            <?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?>
                        </td>
                        <td class="mov-produto">
                            <?= htmlspecialchars($m['produto_nome']) ?>
                        </td>
                        <td class="mov-variacao">
                            <?= $m['variacao_descricao']
                                ? htmlspecialchars($m['variacao_descricao'])
                                : '<span class="muted-text">Padrão</span>' ?>
                        </td>
                        <td class="mov-tipo">
                            <span class="badge-tipo badge-tipo-<?= htmlspecialchars($m['tipo_operacao']) ?>">
                                <?= htmlspecialchars($m['tipo_nome']) ?>
                            </span>
                        </td>
                        <td class="mov-quantidade">
                            <span class="qtd-<?= htmlspecialchars($m['tipo_operacao']) ?>">
                                <?php
                                if ($m['tipo_operacao'] === 'entrada') echo '+';
                                elseif ($m['tipo_operacao'] === 'saída' || $m['tipo_operacao'] === 'saida') echo '−';
                                ?>
                                <?= abs($m['quantidade']) ?>
                            </span>
                        </td>
                        <td class="mov-motivo">
                            <?= $m['motivo']
                                ? htmlspecialchars($m['motivo'])
                                : '<span class="muted-text">—</span>' ?>
                        </td>
                        <td class="mov-referencia">
                            <?php if ($m['venda_item_id']): ?>
                                <span class="ref-badge">Venda #<?= $m['venda_item_id'] ?></span>
                            <?php elseif ($m['compra_itens_variacoes_id']): ?>
                                <span class="ref-badge">Compra #<?= $m['compra_itens_variacoes_id'] ?></span>
                            <?php else: ?>
                                <span class="muted-text">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- MODAL NOVA MOVIMENTAÇÃO -->
<div id="modalMovimentacao" class="modal hidden">
    <div class="modal-box">
        <h2>Nova Movimentação Manual</h2>

        <label>Produto <span class="obrigatorio">*</span></label>
        <select id="mov_produto" onchange="carregarVariacoesProduto()">
            <option value="">Selecione o produto...</option>
            <?php foreach ($todosProdutos as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Variação <span class="obrigatorio">*</span></label>
        <select id="mov_variacao">
            <option value="">Primeiro selecione o produto</option>
        </select>

        <label>Tipo de Movimentação <span class="obrigatorio">*</span></label>
        <select id="mov_tipo">
            <option value="">Selecione o tipo...</option>
            <?php foreach ($tiposMovimentacao as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['descricao']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Quantidade <span class="obrigatorio">*</span></label>
        <input type="number" id="mov_quantidade" min="1" value="1">

        <label>Motivo</label>
        <textarea id="mov_motivo" placeholder="Descreva o motivo desta movimentação..."></textarea>

        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModal()">Cancelar</button>
            <button class="btn-primary" onclick="salvarMovimentacao()">
                <i class="fa-solid fa-floppy-disk"></i> Salvar
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<style>
/* ===== FILTROS ===== */
.filtros-container { background: white; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.filtros-form { display: grid; grid-template-columns: 1fr 2fr 1fr 1fr auto; gap: 16px; align-items: end; }
.filtro-item { display: flex; flex-direction: column; }
.filtro-item label { font-size: 13px; font-weight: 600; color: #2c3e50; margin-bottom: 6px; }
.filtro-item input,
.filtro-item select { width: 100%; padding: 10px 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; font-family: inherit; color: #2c3e50; }
.filtro-item input:focus,
.filtro-item select:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 3px rgba(52,152,219,0.1); }
.filtro-acoes { display: flex; flex-direction: column; gap: 8px; }

/* ===== TABELA ===== */
.tabela-movimentacoes { width: 100%; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-collapse: collapse; }
.tabela-movimentacoes thead { background: #f8f9fa; border-bottom: 2px solid #e9ecef; }
.tabela-movimentacoes thead th { text-align: left; font-size: 12px; text-transform: uppercase; color: #6c757d; font-weight: 600; padding: 16px; letter-spacing: 0.5px; }
.tabela-movimentacoes tbody tr { border-bottom: 1px solid #f1f3f5; transition: background 0.2s; }
.tabela-movimentacoes tbody tr:hover { background: #f8f9fa; }
.tabela-movimentacoes tbody tr:last-child { border-bottom: none; }
.tabela-movimentacoes tbody td { padding: 14px 16px; font-size: 14px; color: #2c3e50; vertical-align: middle; }

.mov-data      { white-space: nowrap; color: #6c757d; font-size: 13px; min-width: 130px; }
.mov-produto   { font-weight: 600; min-width: 160px; }
.mov-variacao  { font-size: 13px; color: #5a6c7d; min-width: 140px; }
.mov-tipo      { white-space: nowrap; }
.mov-quantidade { font-weight: 700; font-size: 15px; white-space: nowrap; }
.mov-motivo    { max-width: 240px; font-size: 13px; line-height: 1.4; }
.mov-referencia { white-space: nowrap; }

.badge-tipo { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.badge-tipo-entrada { background: #d4edda; color: #155724; }
.badge-tipo-saida, .badge-tipo-saída { background: #f8d7da; color: #721c24; }
.badge-tipo-ajuste { background: #fff3cd; color: #856404; }

.qtd-entrada { color: #27ae60; }
.qtd-saida, .qtd-saída { color: #e74c3c; }
.qtd-ajuste { color: #f39c12; }

.ref-badge { background: #e9ecef; color: #495057; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.muted-text { color: #adb5bd; font-style: italic; }
.sem-dados { text-align: center; padding: 40px 20px; color: #95a5a6; font-style: italic; font-size: 14px; }

/* ===== RESPONSIVIDADE ===== */
@media (max-width: 1024px) {
    .filtros-form { grid-template-columns: repeat(2, 1fr); }
    .filtro-acoes { grid-column: 1/-1; flex-direction: row; }
}
@media (max-width: 768px) {
    .filtros-form { grid-template-columns: 1fr; }
    .filtro-acoes { flex-direction: row; }
    .tabela-movimentacoes { display: block; overflow-x: auto; }
    .tabela-movimentacoes thead th,
    .tabela-movimentacoes tbody td { padding: 10px 8px; white-space: nowrap; }
}
</style>

<script>
// ========================================
// MODAL
// ========================================
const modal = document.getElementById('modalMovimentacao');

function abrirModalNova() {
    modal.querySelectorAll('input, textarea, select').forEach(el => {
        if (el.id !== 'mov_quantidade') el.value = '';
    });
    document.getElementById('mov_quantidade').value = 1;
    document.getElementById('mov_variacao').innerHTML = '<option value="">Primeiro selecione o produto</option>';
    modal.classList.remove('hidden');
}

function fecharModal() { modal.classList.add('hidden'); }

// ========================================
// CARREGAR VARIAÇÕES
// ========================================
async function carregarVariacoesProduto() {
    const produtoId = document.getElementById('mov_produto').value;
    const selectVariacao = document.getElementById('mov_variacao');

    if (!produtoId) {
        selectVariacao.innerHTML = '<option value="">Primeiro selecione o produto</option>';
        return;
    }

    try {
        const response = await fetch(`../api.php?action=listar_variacoes_produto&produto=${produtoId}`);
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            console.error('JSON inválido:', text.substring(0, 200));
            selectVariacao.innerHTML = '<option value="">Erro ao carregar variações</option>';
            return;
        }

        if (!data.success || !data.variacoes || data.variacoes.length === 0) {
            selectVariacao.innerHTML = '<option value="">Nenhuma variação disponível</option>';
            return;
        }

        selectVariacao.innerHTML = '<option value="">Selecione a variação...</option>';
        data.variacoes.forEach(v => {
            selectVariacao.innerHTML += `<option value="${v.id}">${v.descricao || 'Padrão'}</option>`;
        });

    } catch (error) {
        console.error(error);
        selectVariacao.innerHTML = '<option value="">Erro ao carregar variações</option>';
    }
}

// ========================================
// SALVAR MOVIMENTAÇÃO
// ========================================
async function salvarMovimentacao() {
    const variacaoId = document.getElementById('mov_variacao').value;
    const tipoId     = document.getElementById('mov_tipo').value;
    const quantidade = document.getElementById('mov_quantidade').value;
    const motivo     = document.getElementById('mov_motivo').value;

    if (!variacaoId || !tipoId || !quantidade) {
        alert('Preencha todos os campos obrigatórios');
        return;
    }

    try {
        const response = await fetch('../api.php?action=create_movimentacao', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                produto_variacao_id: variacaoId,
                tipo_id:             tipoId,
                quantidade:          quantidade,
                motivo:              motivo
            })
        });
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            console.error('JSON inválido:', text.substring(0, 200));
            alert('Erro ao salvar movimentação');
            return;
        }

        if (data.success) {
            alert('Movimentação registrada com sucesso!');
            location.reload();
        } else {
            alert(data.message || 'Erro ao salvar movimentação');
        }
    } catch (error) {
        console.error(error);
        alert('Erro ao salvar movimentação');
    }
}
</script>