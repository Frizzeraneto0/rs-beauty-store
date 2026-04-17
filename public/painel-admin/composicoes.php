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

// BUSCAR COMPOSIÇÕES
$composicoes = $db->query("
    SELECT 
        c.id,
        c.nome,
        c.descricao,
        c.tipo,
        c.ativo,
        c.preco_compra,
        c.preco_venda,
        COUNT(ci.id) as total_itens
    FROM composicoes c
    LEFT JOIN composicoes_itens ci ON ci.composicao_id = c.id
    GROUP BY c.id
    ORDER BY c.nome
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main pagina-composicoes">

    <!-- HEADER -->
    <div class="pagina-header">
        <h1 class="pagina-titulo">Composições</h1>
        <button class="btn-primary" onclick="abrirModalComposicao()">
            ➕ Nova Composição
        </button>
    </div>

    <!-- TABELA -->
    <table class="tabela tabela-composicoes">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Descrição</th>
                <th>Tipo</th>
                <th>Itens</th>
                <th>Preço Compra</th>
                <th>Preço Venda</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($composicoes)): ?>
                <tr>
                    <td colspan="8" class="sem-dados">
                        Nenhuma composição cadastrada
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($composicoes as $comp): ?>
                    <tr>
                        <td class="comp-nome">
                            <?= htmlspecialchars($comp['nome']) ?>
                        </td>
                        <td class="comp-descricao">
                            <?= htmlspecialchars($comp['descricao'] ?: '—') ?>
                        </td>
                        <td class="comp-tipo">
                            <span class="badge-<?= $comp['tipo'] ?>">
                                <?= $comp['tipo'] == 'compra' ? '🛒 Compra' : '💰 Venda' ?>
                            </span>
                        </td>
                        <td class="comp-itens">
                            <span class="badge-itens">
                                <?= $comp['total_itens'] ?> <?= $comp['total_itens'] == 1 ? 'item' : 'itens' ?>
                            </span>
                        </td>
                        <td class="comp-preco-compra">
                            <?= $comp['preco_compra'] ? 'R$ ' . number_format($comp['preco_compra'], 2, ',', '.') : '—' ?>
                        </td>
                        <td class="comp-preco-venda">
                            <?= $comp['preco_venda'] ? 'R$ ' . number_format($comp['preco_venda'], 2, ',', '.') : '—' ?>
                        </td>
                        <td class="comp-status">
                            <span class="<?= $comp['ativo'] ? 'status-ativo' : 'status-inativo' ?>">
                                <?= $comp['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="comp-acoes">
                            <button 
                                class="btn-secondary" 
                                onclick="gerenciarItens(<?= $comp['id'] ?>, '<?= htmlspecialchars($comp['nome'], ENT_QUOTES) ?>')"
                                title="Gerenciar itens"
                            >
                                📦 Itens
                            </button>
                            <button 
                                class="btn-secondary" 
                                onclick='editarComposicao(<?= json_encode($comp) ?>)'
                                title="Editar"
                            >
                                ✏️
                            </button>
                            <button 
                                class="btn-danger" 
                                onclick="excluirComposicao(<?= $comp['id'] ?>, '<?= htmlspecialchars($comp['nome'], ENT_QUOTES) ?>')"
                                title="Excluir"
                            >
                                🗑️
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- MODAL COMPOSIÇÃO -->
<div id="modalComposicao" class="modal hidden">
    <div class="modal-box">
        <h2 id="modalComposicaoTitulo">Nova Composição</h2>

        <input type="hidden" id="comp_id">

        <label>Nome <span class="obrigatorio">*</span></label>
        <input type="text" id="comp_nome" placeholder="Ex: Kit Presente">

        <label>Descrição</label>
        <textarea id="comp_descricao" placeholder="Descrição da composição (opcional)"></textarea>

        <label>Tipo <span class="obrigatorio">*</span></label>
        <select id="comp_tipo">
            <option value="">Selecione...</option>
            <option value="compra">🛒 Compra</option>
            <option value="venda">💰 Venda</option>
        </select>

        <label>Preço de Compra</label>
        <input type="number" step="0.01" id="comp_preco_compra" placeholder="0.00">

        <label>Preço de Venda</label>
        <input type="number" step="0.01" id="comp_preco_venda" placeholder="0.00">

        <label>Status</label>
        <select id="comp_ativo">
            <option value="1">Ativo</option>
            <option value="0">Inativo</option>
        </select>

        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModalComposicao()">Cancelar</button>
            <button class="btn-primary" onclick="salvarComposicao()">Salvar</button>
        </div>
    </div>
</div>

<!-- MODAL ITENS -->
<div id="modalItens" class="modal hidden">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <h2 id="modalItensTitulo">Itens da Composição</h2>
            <button class="btn-primary" onclick="abrirFormNovoItem()">
                ➕ Adicionar Item
            </button>
        </div>

        <div id="listaItens">
            <!-- carregado via JS -->
        </div>

        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModalItens()">Fechar</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<script>
// ========================================
// MODAL COMPOSIÇÃO
// ========================================
const modalComposicao = document.getElementById('modalComposicao');

function abrirModalComposicao(comp = null) {
    modalComposicao.classList.remove('hidden');

    if (comp) {
        document.getElementById('modalComposicaoTitulo').innerText = 'Editar Composição';
        document.getElementById('comp_id').value = comp.id;
        document.getElementById('comp_nome').value = comp.nome;
        document.getElementById('comp_descricao').value = comp.descricao || '';
        document.getElementById('comp_tipo').value = comp.tipo;
        document.getElementById('comp_preco_compra').value = comp.preco_compra || '';
        document.getElementById('comp_preco_venda').value = comp.preco_venda || '';
        document.getElementById('comp_ativo').value = comp.ativo ? 1 : 0;
    } else {
        document.getElementById('modalComposicaoTitulo').innerText = 'Nova Composição';
        document.getElementById('comp_id').value = '';
        document.getElementById('comp_nome').value = '';
        document.getElementById('comp_descricao').value = '';
        document.getElementById('comp_tipo').value = '';
        document.getElementById('comp_preco_compra').value = '';
        document.getElementById('comp_preco_venda').value = '';
        document.getElementById('comp_ativo').value = 1;
    }
}

function editarComposicao(comp) {
    abrirModalComposicao(comp);
}

function fecharModalComposicao() {
    modalComposicao.classList.add('hidden');
}

async function salvarComposicao() {
    const id = document.getElementById('comp_id').value;
    const nome = document.getElementById('comp_nome').value.trim();
    const descricao = document.getElementById('comp_descricao').value.trim();
    const tipo = document.getElementById('comp_tipo').value;
    const precoCompra = document.getElementById('comp_preco_compra').value;
    const precoVenda = document.getElementById('comp_preco_venda').value;
    const ativo = document.getElementById('comp_ativo').value;

    if (!nome || !tipo) {
        alert('Preencha todos os campos obrigatórios');
        return;
    }

    const formData = new FormData();
    if (id) formData.append('id', id);
    formData.append('nome', nome);
    formData.append('descricao', descricao);
    formData.append('tipo', tipo);
    formData.append('preco_compra', precoCompra);
    formData.append('preco_venda', precoVenda);
    formData.append('ativo', ativo);

    const url = id
        ? '../api.php?action=update_composicao'
        : '../api.php?action=create_composicao'; 

    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            console.error('Erro:', data.message);
            alert(data.message || 'Erro ao salvar composição');
        }
    } catch (error) {
        console.error('Erro ao salvar composição:', error);
        alert('Erro ao salvar composição');
    }
}

async function excluirComposicao(id, nome) {
    if (!confirm(`Tem certeza que deseja excluir a composição "${nome}"?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('id', id);

    try {
        const response = await fetch('../api.php?action=delete_composicao', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            console.error('Erro:', data.message);
            alert(data.message || 'Erro ao excluir composição');
        }
    } catch (error) {
        console.error('Erro ao excluir composição:', error);
        alert('Erro ao excluir composição');
    }
}

// Fechar modal ao clicar fora
modalComposicao.addEventListener('click', (e) => {
    if (e.target === modalComposicao) {
        fecharModalComposicao();
    }
});
</script>

<script>
// ========================================
// MODAL ITENS
// ========================================
const modalItens = document.getElementById('modalItens');
let composicaoAtual = null;
let produtosDisponiveis = [];

async function gerenciarItens(composicaoId, composicaoNome) {
    composicaoAtual = composicaoId;

    document.getElementById('modalItensTitulo').innerText =
        `Itens — ${composicaoNome}`;

    modalItens.classList.remove('hidden');

    await carregarProdutos();
    await carregarItens(composicaoId);
}

function fecharModalItens() {
    modalItens.classList.add('hidden');
    document.getElementById('listaItens').innerHTML = '';
}

async function carregarProdutos() {
    try {
        const response = await fetch('../api.php?action=get_produtos_variacoes');
        const data = await response.json();

        if (data.success) {
            produtosDisponiveis = data.produtos || [];
        } else {
            console.error('Erro ao carregar produtos:', data.message);
        }
    } catch (error) {
        console.error('Erro ao carregar produtos:', error);
    }
}

async function carregarItens(composicaoId) {
    try {
        const response = await fetch('../api.php?action=listar_itens_composicao&composicao=' + composicaoId);
        const data = await response.json();

        if (!data.success) {
            document.getElementById('listaItens').innerHTML =
                '<p class="muted">Erro ao carregar itens</p>';
            return;
        }

        const itens = data.itens || [];

        if (!itens.length) {
            document.getElementById('listaItens').innerHTML =
                '<p class="muted">Nenhum item adicionado.</p>';
            return;
        }

        let html = '<table class="tabela-itens">';
        html += '<thead><tr><th>Produto</th><th>Variação</th><th>Quantidade</th><th>Ações</th></tr></thead>';
        html += '<tbody>';

        itens.forEach(item => {
            html += `
                <tr>
                    <td>${item.produto_nome}</td>
                    <td>${item.variacao_descricao || 'Padrão'}</td>
                    <td>${item.quantidade}</td>
                    <td>
                        <button class="btn-secondary" onclick='editarItem(${JSON.stringify(item)})'>✏️</button>
                        <button class="btn-danger" onclick="excluirItem(${item.id})">🗑️</button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';

        document.getElementById('listaItens').innerHTML = html;

    } catch (error) {
        console.error('Erro ao carregar itens:', error);
        document.getElementById('listaItens').innerHTML =
            '<p class="muted">Erro ao carregar itens</p>';
    }
}

function abrirFormNovoItem() {
    if (!produtosDisponiveis.length) {
        alert('Nenhum produto disponível');
        return;
    }

    let html = `
        <div class="form-item">
            <h3>Novo Item</h3>
            
            <label>Produto <span class="obrigatorio">*</span></label>
            <select id="item_produto" onchange="carregarVariacoesProduto()">
                <option value="">Selecione um produto...</option>
                ${produtosDisponiveis.map(p => `<option value="${p.id}">${p.nome}</option>`).join('')}
            </select>
            
            <div id="variacoes-container" style="display: none;">
                <label>Variação <span class="obrigatorio">*</span></label>
                <select id="item_variacao">
                    <option value="">Carregando...</option>
                </select>
            </div>
            
            <label>Quantidade <span class="obrigatorio">*</span></label>
            <input type="number" id="item_quantidade" value="1" min="1">
            
            <div class="modal-actions">
                <button class="btn-secondary" onclick="carregarItens(${composicaoAtual})">Cancelar</button>
                <button class="btn-primary" onclick="salvarItem()">Salvar</button>
            </div>
        </div>
    `;
    
    document.getElementById('listaItens').innerHTML = html;
}

async function carregarVariacoesProduto() {
    const produtoId = document.getElementById('item_produto').value;
    const container = document.getElementById('variacoes-container');
    const selectVariacao = document.getElementById('item_variacao');

    if (!produtoId) {
        container.style.display = 'none';
        return;
    }

    selectVariacao.innerHTML = '<option value="">Carregando...</option>';
    container.style.display = 'block';

    try {
        const response = await fetch('../api.php?action=listar_variacoes_produto&produto=' + produtoId);
        const data = await response.json();

        if (data.success && data.variacoes && data.variacoes.length > 0) {
            selectVariacao.innerHTML = '<option value="">Selecione uma variação...</option>' +
                data.variacoes.map(v => {
                    const desc = v.descricao || 'Variação #' + v.id;
                    return `<option value="${v.id}">${desc}</option>`;
                }).join('');
        } else {
            container.style.display = 'none';
        }
    } catch (error) {
        console.error('Erro ao carregar variações:', error);
        container.style.display = 'none';
    }
}

async function salvarItem() {
    const produtoId = document.getElementById('item_produto').value;
    const selectVariacao = document.getElementById('item_variacao');
    const variacaoId = selectVariacao && selectVariacao.offsetParent !== null 
        ? selectVariacao.value 
        : '';
    const quantidade = document.getElementById('item_quantidade').value;

    if (!produtoId) {
        alert('Selecione um produto');
        return;
    }

    if (selectVariacao && selectVariacao.offsetParent !== null && !variacaoId) {
        alert('Selecione uma variação');
        return;
    }

    if (!quantidade || quantidade < 1) {
        alert('Informe uma quantidade válida');
        return;
    }

    const formData = new FormData();
    formData.append('composicao_id', composicaoAtual);
    formData.append('produto_id', produtoId);
    if (variacaoId) {
        formData.append('produto_variacao_id', variacaoId);
    }
    formData.append('quantidade', quantidade);

    try {
        const response = await fetch('../api.php?action=create_item_composicao', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message);
            carregarItens(composicaoAtual);
        } else {
            console.error('Erro:', data.message);
            alert(data.message || 'Erro ao salvar item');
        }
    } catch (error) {
        console.error('Erro ao salvar item:', error);
        alert('Erro ao salvar item');
    }
}

function editarItem(item) {
    const html = `
        <div class="form-item">
            <h3>Editar Item</h3>
            <p class="info-texto"><strong>Produto:</strong> ${item.produto_nome} ${item.variacao_descricao ? `- ${item.variacao_descricao}` : ''}</p>
            
            <input type="hidden" id="item_id" value="${item.id}">
            
            <label>Quantidade <span class="obrigatorio">*</span></label>
            <input type="number" id="item_quantidade" value="${item.quantidade}" min="1">
            
            <div class="modal-actions">
                <button class="btn-secondary" onclick="carregarItens(${composicaoAtual})">Cancelar</button>
                <button class="btn-primary" onclick="atualizarItem()">Atualizar</button>
            </div>
        </div>
    `;
    
    document.getElementById('listaItens').innerHTML = html;
}

async function atualizarItem() {
    const id = document.getElementById('item_id').value;
    const quantidade = document.getElementById('item_quantidade').value;

    if (!quantidade || quantidade < 1) {
        alert('Quantidade inválida');
        return;
    }

    const formData = new FormData();
    formData.append('id', id);
    formData.append('quantidade', quantidade);

    try {
        const response = await fetch('../api.php?action=update_item_composicao', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message);
            carregarItens(composicaoAtual);
        } else {
            console.error('Erro:', data.message);
            alert(data.message || 'Erro ao atualizar item');
        }
    } catch (error) {
        console.error('Erro ao atualizar item:', error);
        alert('Erro ao atualizar item');
    }
}

async function excluirItem(id) {
    if (!confirm('Tem certeza que deseja excluir este item?')) {
        return;
    }

    const formData = new FormData();
    formData.append('id', id);

    try {
        const response = await fetch('../api.php?action=delete_item_composicao', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message);
            carregarItens(composicaoAtual);
        } else {
            console.error('Erro:', data.message);
            alert(data.message || 'Erro ao excluir item');
        }
    } catch (error) {
        console.error('Erro ao excluir item:', error);
        alert('Erro ao excluir item');
    }
}
</script>