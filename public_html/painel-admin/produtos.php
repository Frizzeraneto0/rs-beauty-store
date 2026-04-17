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
 * CATEGORIAS
 */
$categorias = $db->query("
    SELECT id, nome
    FROM categorias
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * PRODUTOS
 */
$produtos = $db->query("
    SELECT
        p.id,
        p.nome,
        p.descricao,
        p.categoria_id,
        c.nome AS categoria_nome,
        p.preco,
        p.preco_promocional,
        p.ativo,
        (
            SELECT url
            FROM produtos_imagens
            WHERE produto_id = p.id
            ORDER BY ordem
            LIMIT 1
        ) AS imagem
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="main pagina-produtos">

    <div class="pagina-header">
        <h1 class="pagina-titulo">Produtos</h1>

        <button class="btn-primary" onclick="abrirModal()">
            <i class="fa-solid fa-plus"></i> Novo Produto
        </button>
    </div>

    <table class="tabela tabela-produtos">
        <thead>
            <tr>
                <th>Imagem</th>
                <th>Produto</th>
                <th>Descrição</th>
                <th>Categoria</th>
                <th>Variações</th>
                <th>Preço</th>
                <th>Promo</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($produtos as $p): ?>

            <?php
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM produto_variacoes 
                WHERE id_produto = :produto
            ");
            $stmt->execute(['produto' => $p['id']]);
            $qtdVariacoes = (int) $stmt->fetchColumn();
            ?>

            <tr>
                <td>
                    <img
                        src="<?= $p['imagem'] ?? '/img/no-image.png' ?>"
                        class="produto-imagem"
                    >
                </td>

                <td class="produto-nome">
                    <?= htmlspecialchars($p['nome']) ?>
                </td>

                <td class="produto-descricao">
                    <?= htmlspecialchars($p['descricao']) ?>
                </td>

                <td class="produto-categoria">
                    <?= htmlspecialchars($p['categoria_nome'] ?? '') ?>
                </td>
                
                <td class="produto-variacoes">
                    <button
                        class="btn-link"
                        onclick="abrirModalVariacoes(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nome'], ENT_QUOTES) ?>')"
                    >
                        <?= $qtdVariacoes ? "Ver variações ($qtdVariacoes)" : "Gerenciar variações" ?>
                    </button>
                </td>

                <td class="produto-preco">
                    R$ <?= number_format($p['preco'], 2, ',', '.') ?>
                </td>

                <td class="produto-promo">
                    <?= $p['preco_promocional']
                        ? 'R$ '.number_format($p['preco_promocional'],2,',','.')
                        : '—'
                    ?>
                </td>

                <td class="produto-status">
                    <span class="<?= $p['ativo'] ? 'status-ativo' : 'status-inativo' ?>">
                        <?= $p['ativo'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                </td>

                <td class="produto-acoes">
                    <button
                        class="btn-secondary"
                        onclick='abrirModal(<?= json_encode($p) ?>)'
                        title="Editar produto"
                    >
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </td>
            </tr>

        <?php endforeach; ?>
        </tbody>
    </table>

</div>

<!-- MODAL PRODUTO -->
<div id="modalProduto" class="modal hidden">
    <div class="modal-box">
        <h2 id="modalTitulo">Novo Produto</h2>

        <input type="hidden" id="produto_id">

        <label>Nome</label>
        <input type="text" id="produto_nome">

        <label>Descrição</label>
        <textarea id="produto_descricao"></textarea>

        <label>Preço</label>
        <input type="number" step="0.01" id="produto_preco">

        <label>Preço promocional</label>
        <input type="number" step="0.01" id="produto_preco_promocional">

        <label>Categoria</label>
        <input
            list="listaCategorias"
            id="produto_categoria_nome"
            placeholder="Pesquisar categoria..."
        >

        <datalist id="listaCategorias">
            <?php foreach ($categorias as $c): ?>
                <option
                    value="<?= htmlspecialchars($c['nome']) ?>"
                    data-id="<?= $c['id'] ?>">
                </option>
            <?php endforeach; ?>
        </datalist>

        <input type="hidden" id="produto_categoria_id">

        <label>Status</label>
        <select id="produto_ativo">
            <option value="1">Ativo</option>
            <option value="0">Inativo</option>
        </select>

        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModal()">Cancelar</button>
            <button class="btn-primary" onclick="salvarProduto()">
                <i class="fa-solid fa-floppy-disk"></i> Salvar
            </button>
        </div>
    </div>
</div>

<!-- MODAL VARIAÇÕES -->
<div id="modalVariacoes" class="modal hidden">
    <div class="modal-box modal-lg">

        <div class="modal-header">
            <h2 id="tituloVariacoes">Variações</h2>
            <button class="btn-primary" onclick="abrirFormNovaVariacao()">
                <i class="fa-solid fa-plus"></i> Criar variação
            </button>
        </div>

        <div id="listaVariacoes">
            <!-- carregado via JS -->
        </div>

        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModalVariacoes()">Fechar</button>
        </div>

    </div>
</div>


<?php include __DIR__ . '/layout/footer.php'; ?>

<script>
// ========================================
// SCRIPT DE PRODUTOS
// ========================================

const modal = document.getElementById('modalProduto');

function abrirModal(produto = null) {
    modal.classList.remove('hidden');

    if (produto) {
        document.getElementById('modalTitulo').innerText = 'Editar Produto';
        document.getElementById('produto_id').value = produto.id;
        document.getElementById('produto_nome').value = produto.nome;
        document.getElementById('produto_descricao').value = produto.descricao;
        document.getElementById('produto_preco').value = produto.preco;
        document.getElementById('produto_preco_promocional').value = produto.preco_promocional ?? '';
        document.getElementById('produto_ativo').value = produto.ativo ? 1 : 0;
        document.getElementById('produto_categoria_nome').value = produto.categoria_nome ?? '';
        document.getElementById('produto_categoria_id').value = produto.categoria_id ?? '';
    
    } else {
        document.getElementById('modalTitulo').innerText = 'Novo Produto';
        modal.querySelectorAll('input, textarea').forEach(el => el.value = '');
        document.getElementById('produto_ativo').value = 1;
    }
    
}

function fecharModal() {
    modal.classList.add('hidden');
}

function salvarProduto() {

    const formData = new FormData();
    const id = document.getElementById('produto_id').value;

    if (id) {
        formData.append('id', id);
    }

    formData.append('nome', document.getElementById('produto_nome').value);
    formData.append('descricao', document.getElementById('produto_descricao').value);
    formData.append('preco', document.getElementById('produto_preco').value);
    formData.append('preco_promocional',document.getElementById('produto_preco_promocional').value);
    formData.append('ativo', document.getElementById('produto_ativo').value);

    formData.append(
        'categoria_id',
        document.getElementById('produto_categoria_id').value
    );

    const url = id
        ? '../api.php?action=update_produto'
        : '../api.php?action=create_produto';

    fetch(url, {
        method: 'POST',
        body: formData
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
            alert(resp.message || 'Erro ao salvar produto');
            return;
        }

        fecharModal();
        location.reload();
    })
    .catch(err => {
        console.error(err);
        alert('Erro ao salvar produto');
    });
}


function verVariacoes(produtoId) {
    window.location.href = '/admin/variacoes.php?produto=' + produtoId;
}

function criarVariacao(produtoId) {
    window.location.href = '/admin/variacoes.php?produto=' + produtoId + '&nova=1';
}

const categoriaInput = document.getElementById('produto_categoria_nome');
const categoriaIdInput = document.getElementById('produto_categoria_id');

categoriaInput.addEventListener('change', () => {
    categoriaIdInput.value = '';

    document
        .querySelectorAll('#listaCategorias option')
        .forEach(option => {
            if (option.value === categoriaInput.value) {
                categoriaIdInput.value = option.dataset.id;
            }
        });
});
</script>

<script>
// ========================================
// SCRIPT DE VARIAÇÕES
// ========================================

const modalVariacoes = document.getElementById('modalVariacoes');
let produtoVariacaoAtual = null;
let tiposValores = [];
let tiposSelecionados = [];

async function abrirModalVariacoes(produtoId, produtoNome) {
    produtoVariacaoAtual = produtoId;

    document.getElementById('tituloVariacoes').innerText =
        `Variações — ${produtoNome}`;

    modalVariacoes.classList.remove('hidden');

    await carregarTiposValores();
    await carregarVariacoes(produtoId);
}

function fecharModalVariacoes() {
    modalVariacoes.classList.add('hidden');
    document.getElementById('listaVariacoes').innerHTML = '';
}

async function carregarTiposValores() {
    try {
        const response = await fetch('/api.php?action=get_tipos_valores');
        const data = await response.json();

        if (data.success) {
            tiposValores = data.tipos || [];
        }
    } catch (error) {
        console.error('Erro ao carregar tipos:', error);
    }
}

async function carregarVariacoes(produtoId) {
    try {
        const response = await fetch('/api.php?action=listar_variacoes_produto&produto=' + produtoId);
        const data = await response.json();

        if (!data.success) {
            document.getElementById('listaVariacoes').innerHTML =
                '<p class="muted">Erro ao carregar variações</p>';
            return;
        }

        const variacoes = data.variacoes || [];

        if (!variacoes.length) {
            document.getElementById('listaVariacoes').innerHTML =
                '<p class="muted">Nenhuma variação cadastrada.</p>';
            return;
        }

        const variacaoDefault = variacoes.find(v => !v.descricao || v.descricao === '');
        const variacoesComAtributos = variacoes.filter(v => v.descricao && v.descricao !== '');

        if (variacaoDefault && variacoesComAtributos.length === 0) {
            let html = `
                <div class="alert-variacao-default">
                    <div class="alert-variacao-icon">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h4>Variação Padrão</h4>
                    <p>Este produto possui apenas a <strong>variação padrão</strong> (sem atributos específicos).</p>
                    <p>Para criar variações com atributos (Cor, Tamanho, etc.), clique no botão abaixo.</p>
                    <button class="btn-primary" onclick="editarVariacaoDefault(${variacaoDefault.id})">
                        <i class="fa-solid fa-pen"></i> Adicionar Atributos à Variação
                    </button>
                </div>
            `;
            document.getElementById('listaVariacoes').innerHTML = html;
            return;
        }

        let html = '<table class="tabela-variacoes">';
        html += '<thead><tr><th>Variação</th><th>Ações</th></tr></thead>';
        html += '<tbody>';

        variacoes.forEach(v => {
            const descricao = v.descricao || 'Padrão (sem atributos)';
            const temAtributos = v.descricao && v.descricao !== '';
            
            html += `
                <tr>
                    <td>${descricao}</td>
                    <td>
                        ${temAtributos ? 
                            `<button class="btn-secondary" onclick="editarVariacaoComAtributos(${v.id})" title="Editar">
                                <i class="fa-solid fa-pen"></i> Editar
                             </button>
                             <button class="btn-danger" onclick="excluirVariacaoProduto(${v.id})" title="Excluir">
                                <i class="fa-solid fa-trash"></i> Excluir
                             </button>` 
                            : 
                            `<button class="btn-secondary" onclick="editarVariacaoDefault(${v.id})">
                                <i class="fa-solid fa-pen"></i> Adicionar Atributos
                             </button>`
                        }
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';

        document.getElementById('listaVariacoes').innerHTML = html;

    } catch (error) {
        console.error(error);
        document.getElementById('listaVariacoes').innerHTML =
            '<p class="muted">Erro ao carregar variações</p>';
    }
}

function editarVariacaoComAtributos(variacaoId) {
    alert('Funcionalidade de editar variação com atributos em desenvolvimento.\n\nPor enquanto, você pode excluir e criar uma nova variação.');
}

function editarVariacaoDefault(variacaoId) {
    if (!tiposValores.length) {
        alert('Cadastre primeiro os tipos de variação em "Variações" no menu');
        return;
    }

    tiposSelecionados = [];

    let html = `
        <div class="form-variacao">
            <h3>Editar Variação Padrão</h3>
            <p class="info-texto">
                Esta é a variação padrão do produto (sem atributos). 
                Adicione atributos para transformá-la em uma variação comum.
            </p>
            
            <input type="hidden" id="variacao_default_id" value="${variacaoId}">
            
            <div id="tipos-selecionados"></div>
            
            <button type="button" class="btn-add-tipo" onclick="adicionarTipoVariacao()">
                <i class="fa-solid fa-plus"></i> Adicionar tipo de variação
            </button>
            
            <div class="modal-actions">
                <button class="btn-secondary" onclick="carregarVariacoes(${produtoVariacaoAtual})">Cancelar</button>
                <button class="btn-primary" onclick="salvarVariacaoDefault()">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar Atributos
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('listaVariacoes').innerHTML = html;
}

async function salvarVariacaoDefault() {
    const variacaoId = document.getElementById('variacao_default_id').value;
    
    const valores = [];
    document.querySelectorAll('.select-valor').forEach(select => {
        if (select.value) {
            valores.push(select.value);
        }
    });

    if (valores.length === 0) {
        alert('Selecione pelo menos um atributo de variação');
        return;
    }

    const formData = new FormData();
    formData.append('variacao_id', variacaoId);
    valores.forEach(v => formData.append('valores[]', v));

    try {
        const response = await fetch('/api.php?action=update_variacao_default', {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        console.log('Response:', text);

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Erro ao parsear JSON:', text);
            alert('Erro no servidor. Verifique o console.');
            return;
        }

        if (data.success) {
            alert(data.message);
            carregarVariacoes(produtoVariacaoAtual);
        } else {
            alert(data.message || 'Erro ao salvar');
        }
    } catch (error) {
        console.error(error);
        alert('Erro ao salvar');
    }
}

function abrirFormNovaVariacao() {
    if (!tiposValores.length) {
        alert('Cadastre primeiro os tipos de variação em "Variações" no menu');
        return;
    }

    tiposSelecionados = [];

    let html = `
        <div class="form-variacao">
            <h3>Nova Variação</h3>
            <p class="info-texto">Selecione os tipos de variação para este produto:</p>
            
            <div id="tipos-selecionados"></div>
            
            <button type="button" class="btn-add-tipo" onclick="adicionarTipoVariacao()">
                <i class="fa-solid fa-plus"></i> Adicionar tipo de variação
            </button>
            
            <div class="modal-actions">
                <button class="btn-secondary" onclick="carregarVariacoes(${produtoVariacaoAtual})">Cancelar</button>
                <button class="btn-primary" onclick="salvarVariacaoProduto()">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('listaVariacoes').innerHTML = html;
}

function adicionarTipoVariacao() {
    const tiposDisponiveis = tiposValores.filter(t => 
        !tiposSelecionados.includes(t.id) && t.valores && t.valores.length > 0
    );

    if (!tiposDisponiveis.length) {
        alert('Todos os tipos disponíveis já foram adicionados');
        return;
    }

    const container = document.getElementById('tipos-selecionados');
    const index = tiposSelecionados.length;

    const html = `
        <div class="tipo-variacao-item" data-index="${index}">
            <label>Tipo de Variação</label>
            <select class="select-tipo" data-index="${index}" onchange="carregarValoresTipo(${index})">
                <option value="">Selecione o tipo...</option>
                ${tiposDisponiveis.map(t => `<option value="${t.id}">${t.nome}</option>`).join('')}
            </select>
            
            <div id="valores-container-${index}" style="display: none;">
                <label>Valor</label>
                <select class="select-valor" data-index="${index}">
                    <option value="">Selecione...</option>
                </select>
            </div>
            
            <button type="button" class="btn-remove-tipo" onclick="removerTipoVariacao(${index})">
                <i class="fa-solid fa-trash"></i> Remover
            </button>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', html);
}

function carregarValoresTipo(index) {
    const selectTipo = document.querySelector(`.select-tipo[data-index="${index}"]`);
    const tipoId = selectTipo.value;

    if (!tipoId) {
        document.getElementById(`valores-container-${index}`).style.display = 'none';
        return;
    }

    if (!tiposSelecionados.includes(parseInt(tipoId))) {
        tiposSelecionados.push(parseInt(tipoId));
    }

    const tipo = tiposValores.find(t => t.id == tipoId);
    
    if (!tipo || !tipo.valores) return;

    const selectValor = document.querySelector(`.select-valor[data-index="${index}"]`);
    selectValor.innerHTML = '<option value="">Selecione...</option>' +
        tipo.valores.map(v => `<option value="${v.id}">${v.valor}</option>`).join('');

    document.getElementById(`valores-container-${index}`).style.display = 'block';
}

function removerTipoVariacao(index) {
    const item = document.querySelector(`.tipo-variacao-item[data-index="${index}"]`);
    
    const selectTipo = item.querySelector('.select-tipo');
    const tipoId = parseInt(selectTipo.value);
    tiposSelecionados = tiposSelecionados.filter(id => id !== tipoId);
    
    item.remove();
}

async function salvarVariacaoProduto() {
    const valores = [];
    document.querySelectorAll('.select-valor').forEach(select => {
        if (select.value) {
            valores.push(select.value);
        }
    });

    if (valores.length === 0) {
        alert('Selecione pelo menos um valor de variação');
        return;
    }

    const formData = new FormData();
    formData.append('id_produto', produtoVariacaoAtual);
    valores.forEach(v => formData.append('valores[]', v));

    try {
        const response = await fetch('/api.php?action=create_variacao_produto', {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        console.log('Response:', text);

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Erro ao parsear JSON:', text);
            alert('Erro no servidor. Verifique o console.');
            return;
        }

        if (data.success) {
            alert(data.message);
            carregarVariacoes(produtoVariacaoAtual);
        } else {
            alert(data.message || 'Erro ao salvar variação');
        }
    } catch (error) {
        console.error(error);
        alert('Erro ao salvar variação');
    }
}

async function excluirVariacaoProduto(id) {
    if (!confirm('Tem certeza que deseja excluir esta variação?')) {
        return;
    }

    const formData = new FormData();
    formData.append('id', id);

    try {
        const response = await fetch('/api.php?action=delete_variacao_produto', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message);
            carregarVariacoes(produtoVariacaoAtual);
        } else {
            alert(data.message || 'Erro ao excluir variação');
        }
    } catch (error) {
        console.error(error);
        alert('Erro ao excluir variação');
    }
}
</script>