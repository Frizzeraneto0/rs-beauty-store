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

/**
 * COMPRAS
 */
$compras = $db->query("
    SELECT 
        c.id,
        c.data_compra,
        c.valor_total,
        f.nome AS fornecedor_nome,
        COUNT(ci.id) AS total_itens
    FROM compras c
    LEFT JOIN fornecedores f ON f.id = c.fornecedor_id
    LEFT JOIN compras_itens ci ON ci.compra_id = c.id
    GROUP BY c.id, f.nome
    ORDER BY c.data_compra DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* =====================================================
   PÁGINA: COMPRAS
===================================================== */

/* --- TABELA --- */
.compra-data       { white-space: nowrap; color: #2c3e50; }
.compra-fornecedor { font-weight: 500; color: #2c3e50; }
.compra-itens      { white-space: nowrap; }
.compra-acoes      { text-align: right; white-space: nowrap; }

.compra-valor {
    font-weight: 600;
    color: #2c3e50;
    white-space: nowrap;
}

.badge-itens {
    display: inline-block;
    background: #eef2ff;
    color: #667eea;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
}

/* --- ITEM DA COMPRA --- */
.item-compra {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f3f5;
}

.item-header h4 {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.item-compra label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #5a6c7d;
    margin-top: 12px;
    margin-bottom: 5px;
}

.item-compra select,
.item-tipo,
.item-produto,
.item-composicao,
.item-qtd-comp,
.item-preco {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    color: #2c3e50;
    background: white;
    transition: border-color 0.2s;
}

.item-compra select:focus,
.item-preco:focus,
.item-qtd-comp:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}

.item-subtotal {
    text-align: right;
    font-size: 13px;
    color: #6c757d;
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid #f1f3f5;
    font-weight: 500;
}

.item-subtotal span {
    font-weight: 700;
    color: #2c3e50;
}

/* --- VARIAÇÕES --- */
.variacao-qtd {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #f1f3f5;
}

.variacao-qtd:last-child {
    border-bottom: none;
}

.variacao-qtd label {
    font-size: 13px;
    color: #2c3e50;
    font-weight: 500;
    margin: 0 !important;
    flex: 1;
}

.variacao-qtd input.var-qtd {
    width: 80px;
    padding: 8px 10px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 14px;
    text-align: center;
    flex-shrink: 0;
}

.variacao-qtd input.var-qtd:focus {
    outline: none;
    border-color: #3498db;
}

.info-texto {
    font-size: 13px;
    color: #6c757d;
    font-style: italic;
    margin: 8px 0 0;
}

/* --- BOTÃO ADICIONAR ITEM --- */
.btn-add-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 12px;
    background: #f8f9fa;
    border: 2px dashed #cbd5e0;
    border-radius: 10px;
    color: #6c757d;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 4px;
    margin-bottom: 20px;
}

.btn-add-item:hover {
    background: white;
    border-color: #3498db;
    color: #3498db;
}

/* --- TOTAL --- */
.total-compra {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
    font-size: 18px;
    color: #2c3e50;
    padding: 16px 0;
    border-top: 2px solid #e9ecef;
    margin-bottom: 4px;
}

.total-compra strong {
    font-weight: 600;
}

.total-compra span {
    font-weight: 700;
    font-size: 20px;
    color: #27ae60;
}
</style>

<div class="main pagina-compras">

    <div class="pagina-header">
        <h1 class="pagina-titulo">Compras</h1>

        <button class="btn-primary" onclick="abrirModalNovaCompra()">
            <i class="fa-solid fa-plus"></i> Nova Compra
        </button>
    </div>

    <table class="tabela tabela-compras">
        <thead>
            <tr>
                <th>Data</th>
                <th>Fornecedor</th>
                <th>Itens</th>
                <th>Valor Total</th>
                <th>Ações</th>
            </tr>
        </thead>

        <tbody>
        <?php if (empty($compras)): ?>
            <tr>
                <td colspan="5" class="sem-dados">Nenhuma compra cadastrada</td>
            </tr>
        <?php else: ?>
            <?php foreach ($compras as $c): ?>
            <tr>
                <td class="compra-data">
                    <?= date('d/m/Y H:i', strtotime($c['data_compra'])) ?>
                </td>

                <td class="compra-fornecedor">
                    <?= htmlspecialchars($c['fornecedor_nome'] ?: 'Sem fornecedor') ?>
                </td>

                <td class="compra-itens">
                    <span class="badge badge-itens"><?= $c['total_itens'] ?> itens</span>
                </td>

                <td class="compra-valor">
                    R$ <?= number_format($c['valor_total'], 2, ',', '.') ?>
                </td>

                <td class="compra-acoes">
                    <button
                        class="btn-secondary"
                        onclick="verDetalhes(<?= $c['id'] ?>)"
                        title="Ver detalhes"
                    >
                        <i class="fa-solid fa-eye"></i> Ver
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- MODAL NOVA COMPRA -->
<div id="modalCompra" class="modal hidden">
    <div class="modal-box modal-lg">
        <h2>Nova Compra</h2>

        <label>Fornecedor</label>
        <select id="compra_fornecedor_id">
            <option value="">Selecione...</option>
        </select>

        <label>Data da Compra</label>
        <input type="datetime-local" id="compra_data" value="<?= date('Y-m-d\TH:i') ?>">

        <h3 style="margin-top: 20px; margin-bottom: 10px;">Itens da Compra</h3>

        <div id="itens-container"></div>

        <button type="button" class="btn-add-item" onclick="adicionarItem()">
            <i class="fa-solid fa-plus"></i> Adicionar Item
        </button>

        <div class="total-compra">
            <strong>Total:</strong> R$ <span id="valor-total">0,00</span>
        </div>

        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModalCompra()">Cancelar</button>
            <button class="btn-primary" onclick="salvarCompra()">
                <i class="fa-solid fa-floppy-disk"></i> Finalizar Compra
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<script>
// ========================================
// SCRIPT DE COMPRAS
// ========================================

const modalCompra = document.getElementById('modalCompra');
let itensCount = 0;
let fornecedores = [];
let produtos = [];
let composicoes = [];

async function abrirModalNovaCompra() {
    modalCompra.classList.remove('hidden');
    await carregarDados();
    document.getElementById('itens-container').innerHTML = '';
    itensCount = 0;
    adicionarItem();
}

function fecharModalCompra() {
    modalCompra.classList.add('hidden');
}

async function carregarDados() {
    try {
        const [resForn, resProd, resComp] = await Promise.all([
            fetch('/api.php?action=get_fornecedores'),
            fetch('/api.php?action=get_produtos_variacoes'),
            fetch('/api.php?action=get_composicoes_compra')
        ]);

        const [dataForn, dataProd, dataComp] = await Promise.all([
            resForn.json(),
            resProd.json(),
            resComp.json()
        ]);

        if (dataForn.success) {
            fornecedores = dataForn.fornecedores;
            const select = document.getElementById('compra_fornecedor_id');
            select.innerHTML = '<option value="">Selecione...</option>' +
                fornecedores.map(f => `<option value="${f.id}">${f.nome}</option>`).join('');
        }

        if (dataProd.success) produtos = dataProd.produtos;
        if (dataComp.success) composicoes = dataComp.composicoes;

    } catch (error) {
        console.error('Erro ao carregar dados:', error);
    }
}

function adicionarItem() {
    const id = itensCount++;

    const html = `
        <div class="item-compra" data-item-id="${id}">
            <div class="item-header">
                <h4>Item ${id + 1}</h4>
                <button type="button" class="btn-danger" onclick="removerItem(${id})">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>

            <label>Tipo</label>
            <select class="item-tipo" data-id="${id}" onchange="mudarTipo(${id})">
                <option value="">Selecione...</option>
                <option value="produto">Produto</option>
                <option value="composicao">Composição/Kit</option>
            </select>

            <div id="produto-container-${id}" style="display: none;">
                <label>Produto</label>
                <select class="item-produto" data-id="${id}" onchange="carregarVariacoesProdutoCompra(${id})">
                    <option value="">Selecione...</option>
                </select>

                <div id="variacoes-container-${id}"></div>
            </div>

            <div id="composicao-container-${id}" style="display: none;">
                <label>Composição/Kit</label>
                <select class="item-composicao" data-id="${id}">
                    <option value="">Selecione...</option>
                </select>

                <label>Quantidade de Kits</label>
                <input type="number" class="item-qtd-comp" data-id="${id}" value="1" min="1" onchange="calcularTotal()">
            </div>

            <label>Preço Unitário</label>
            <input type="number" step="0.01" class="item-preco" data-id="${id}" value="0" min="0" onchange="calcularTotal()">

            <div class="item-subtotal">Subtotal: R$ <span id="subtotal-${id}">0,00</span></div>
        </div>
    `;

    document.getElementById('itens-container').insertAdjacentHTML('beforeend', html);
}

function mudarTipo(id) {
    const tipo = document.querySelector(`.item-tipo[data-id="${id}"]`).value;

    document.getElementById(`produto-container-${id}`).style.display    = tipo === 'produto'    ? 'block' : 'none';
    document.getElementById(`composicao-container-${id}`).style.display = tipo === 'composicao' ? 'block' : 'none';

    if (tipo === 'produto') {
        const select = document.querySelector(`.item-produto[data-id="${id}"]`);
        select.innerHTML = '<option value="">Selecione...</option>' +
            produtos.map(p => `<option value="${p.id}">${p.nome}</option>`).join('');

    } else if (tipo === 'composicao') {
        const select = document.querySelector(`.item-composicao[data-id="${id}"]`);
        select.innerHTML = '<option value="">Selecione...</option>' +
            composicoes.map(c => `<option value="${c.id}">${c.nome}</option>`).join('');
    }

    calcularTotal();
}

async function carregarVariacoesProdutoCompra(id) {
    const produtoId = document.querySelector(`.item-produto[data-id="${id}"]`).value;
    const container = document.getElementById(`variacoes-container-${id}`);

    if (!produtoId) {
        container.innerHTML = '';
        return;
    }

    try {
        const response = await fetch(`/api.php?action=get_variacoes_produto&produto=${produtoId}`);
        const text = await response.text();

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Resposta inválida do servidor:', text);
            container.innerHTML = '';
            return;
        }

        if (data.success && data.variacoes && data.variacoes.length > 0) {
            let html = '<label>Distribuição por Variação</label>';
            data.variacoes.forEach(v => {
                html += `
                    <div class="variacao-qtd">
                        <label>${v.descricao || 'Padrão'}</label>
                        <input type="number" class="var-qtd"
                            data-item-id="${id}"
                            data-var-id="${v.id}"
                            value="0" min="0"
                            onchange="calcularTotal()">
                    </div>
                `;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="info-texto">Produto sem variações</p>';
        }

    } catch (error) {
        console.error(error);
        container.innerHTML = '';
    }
}

function removerItem(id) {
    document.querySelector(`.item-compra[data-item-id="${id}"]`).remove();
    calcularTotal();
}

function calcularTotal() {
    let total = 0;

    document.querySelectorAll('.item-compra').forEach(item => {
        const id    = item.dataset.itemId;
        const preco = parseFloat(document.querySelector(`.item-preco[data-id="${id}"]`).value) || 0;
        const tipo  = document.querySelector(`.item-tipo[data-id="${id}"]`).value;

        let qtd = 0;

        if (tipo === 'produto') {
            const varQtds = item.querySelectorAll('.var-qtd');
            if (varQtds.length > 0) {
                varQtds.forEach(input => qtd += parseInt(input.value) || 0);
            } else {
                qtd = 1;
            }
        } else if (tipo === 'composicao') {
            qtd = parseInt(document.querySelector(`.item-qtd-comp[data-id="${id}"]`)?.value) || 0;
        }

        const subtotal = qtd * preco;
        document.getElementById(`subtotal-${id}`).textContent = subtotal.toFixed(2).replace('.', ',');
        total += subtotal;
    });

    document.getElementById('valor-total').textContent = total.toFixed(2).replace('.', ',');
}

async function salvarCompra() {
    const fornecedorId = document.getElementById('compra_fornecedor_id').value;
    const data         = document.getElementById('compra_data').value;

    const itens = [];
    let valido = true;

    document.querySelectorAll('.item-compra').forEach(item => {
        const id    = item.dataset.itemId;
        const tipo  = document.querySelector(`.item-tipo[data-id="${id}"]`).value;
        const preco = parseFloat(document.querySelector(`.item-preco[data-id="${id}"]`).value) || 0;

        if (!tipo || preco <= 0) {
            valido = false;
            return;
        }

        const itemData = { tipo, preco_unitario: preco };

        if (tipo === 'produto') {
            const produtoId = document.querySelector(`.item-produto[data-id="${id}"]`).value;
            if (!produtoId) { valido = false; return; }

            itemData.produto_id = produtoId;
            itemData.variacoes  = [];

            const varQtds = item.querySelectorAll('.var-qtd');
            if (varQtds.length > 0) {
                varQtds.forEach(input => {
                    const qtd = parseInt(input.value) || 0;
                    if (qtd > 0) {
                        itemData.variacoes.push({
                            produto_variacao_id: input.dataset.varId,
                            quantidade: qtd
                        });
                    }
                });

                if (itemData.variacoes.length === 0) { valido = false; return; }
            } else {
                itemData.quantidade = 1;
            }

        } else if (tipo === 'composicao') {
            const composicaoId = document.querySelector(`.item-composicao[data-id="${id}"]`).value;
            const qtd          = parseInt(document.querySelector(`.item-qtd-comp[data-id="${id}"]`).value) || 0;

            if (!composicaoId || qtd <= 0) { valido = false; return; }

            itemData.composicao_id = composicaoId;
            itemData.quantidade    = qtd;
        }

        itens.push(itemData);
    });

    if (!valido) {
        alert('Preencha todos os campos corretamente');
        return;
    }

    if (itens.length === 0) {
        alert('Adicione pelo menos um item');
        return;
    }

    const compraData = {
        fornecedor_id: fornecedorId || null,
        data_compra:   data,
        itens:         itens
    };

    fetch('/api.php?action=create_compra', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(compraData)
    })
    .then(async response => {
        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Resposta inválida do servidor:', text);
            throw new Error('Resposta inválida');
        }
    })
    .then(resp => {
        if (!resp.success) {
            alert(resp.message || 'Erro ao salvar compra');
            return;
        }

        fecharModalCompra();
        location.reload();
    })
    .catch(err => {
        console.error(err);
        alert('Erro ao salvar compra');
    });
}

async function verDetalhes(id) {
    alert('Funcionalidade em desenvolvimento');
}
</script>