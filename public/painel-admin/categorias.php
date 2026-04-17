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

// BUSCAR CATEGORIAS
$categorias = $db->query("
    SELECT 
        c.id,
        c.nome,
        c.descricao,
        COUNT(p.id) AS total_produtos
    FROM categorias c
    LEFT JOIN produtos p ON p.categoria_id = c.id
    GROUP BY c.id, c.nome, c.descricao
    ORDER BY c.id
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main pagina-categorias">

    <!-- HEADER -->
    <div class="pagina-header">
        <h1 class="pagina-titulo">Categorias</h1>
        <button class="btn-primary" onclick="abrirModal()">
            ➕ Nova Categoria
        </button>
    </div>

    <!-- TABELA -->
    <table class="tabela tabela-categorias">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Descrição</th>
                <th>Produtos</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categorias)): ?>
                <tr>
                    <td colspan="5" class="sem-dados">
                        Nenhuma categoria cadastrada
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($categorias as $cat): ?>
                    <tr>
                        <td class="categoria-id">
                            #<?= $cat['id'] ?>
                        </td>
                        <td class="categoria-nome">
                            <?= htmlspecialchars($cat['nome']) ?>
                        </td>
                        <td class="categoria-descricao">
                            <?= htmlspecialchars($cat['descricao'] ?: '—') ?>
                        </td>
                        <td class="categoria-produtos">
                            <span class="badge-produtos">
                                <?= $cat['total_produtos'] ?> 
                                <?= $cat['total_produtos'] == 1 ? 'produto' : 'produtos' ?>
                            </span>
                        </td>
                        <td class="categoria-acoes">
                            <button 
                                class="btn-secondary" 
                                onclick='abrirModal(<?= json_encode($cat) ?>)'
                                title="Editar"
                            >
                                ✏️
                            </button>
                            <button 
                                class="btn-danger" 
                                onclick="confirmarExclusao(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['nome'], ENT_QUOTES) ?>')"
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

<!-- MODAL CATEGORIA -->
<div id="modalCategoria" class="modal hidden">
    <div class="modal-box">
        <h2 id="modalTitulo">Nova Categoria</h2>

        <input type="hidden" id="categoria_id">

        <label>Nome <span class="obrigatorio">*</span></label>
        <input type="text" id="categoria_nome" placeholder="Ex: Eletrônicos" required>

        <label>Descrição</label>
        <textarea id="categoria_descricao" placeholder="Descrição da categoria (opcional)"></textarea>

        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModal()">Cancelar</button>
            <button class="btn-primary" onclick="salvarCategoria()">Salvar</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<script>
const modal = document.getElementById('modalCategoria');

function abrirModal(categoria = null) {
    modal.classList.remove('hidden');

    if (categoria) {
        document.getElementById('modalTitulo').innerText = 'Editar Categoria';
        document.getElementById('categoria_id').value = categoria.id;
        document.getElementById('categoria_nome').value = categoria.nome;
        document.getElementById('categoria_descricao').value = categoria.descricao || '';
    } else {
        document.getElementById('modalTitulo').innerText = 'Nova Categoria';
        document.getElementById('categoria_id').value = '';
        document.getElementById('categoria_nome').value = '';
        document.getElementById('categoria_descricao').value = '';
    }
}

function fecharModal() {
    modal.classList.add('hidden');
}

async function salvarCategoria() {
    const id = document.getElementById('categoria_id').value;
    const nome = document.getElementById('categoria_nome').value.trim();
    const descricao = document.getElementById('categoria_descricao').value.trim();

    if (!nome) {
        alert('Nome é obrigatório');
        return;
    }

    const formData = new FormData();
    if (id) formData.append('id', id);
    formData.append('nome', nome);
    formData.append('descricao', descricao);

    const url = id
        ? '../api.php?action=update_categoria'
        : '../api.php?action=create_categoria';

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
            alert(data.message || 'Erro ao salvar categoria');
        }
    } catch (error) {
        console.error('Erro ao salvar categoria:', error);
        alert('Erro ao salvar categoria');
    }
}

async function confirmarExclusao(id, nome) {
    if (!confirm(`Tem certeza que deseja excluir a categoria "${nome}"?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('id', id);

    try {
        const response = await fetch('../api.php?action=delete_categoria', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            console.error('Erro:', data.message);
            alert(data.message || 'Erro ao excluir categoria');
        }
    } catch (error) {
        console.error('Erro ao excluir categoria:', error);
        alert('Erro ao excluir categoria');
    }
}

// Fechar modal ao clicar fora
modal.addEventListener('click', (e) => {
    if (e.target === modal) {
        fecharModal();
    }
});
</script>