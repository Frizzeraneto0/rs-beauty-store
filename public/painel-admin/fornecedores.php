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
 * FORNECEDORES
 */
$fornecedores = $db->query("
    SELECT id, nome, cnpj, telefone, email, criado_em
    FROM fornecedores
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="main pagina-fornecedores">

    <div class="pagina-header">
        <h1 class="pagina-titulo">Fornecedores</h1>

        <button class="btn-primary" onclick="abrirModal()">
            <i class="fa-solid fa-plus"></i> Novo Fornecedor
        </button>
    </div>

    <table class="tabela tabela-fornecedores">
        <thead>
            <tr>
                <th>Nome</th>
                <th>CNPJ</th>
                <th>Telefone</th>
                <th>Email</th>
                <th>Ações</th>
            </tr>
        </thead>

        <tbody>
        <?php if (empty($fornecedores)): ?>
            <tr>
                <td colspan="5" class="sem-dados">Nenhum fornecedor cadastrado</td>
            </tr>
        <?php else: ?>
            <?php foreach ($fornecedores as $f): ?>
            <tr>
                <td class="fornec-nome">
                    <?= htmlspecialchars($f['nome']) ?>
                </td>

                <td class="fornec-cnpj">
                    <?= htmlspecialchars($f['cnpj'] ?: '—') ?>
                </td>

                <td class="fornec-telefone">
                    <?= htmlspecialchars($f['telefone'] ?: '—') ?>
                </td>

                <td class="fornec-email">
                    <?= htmlspecialchars($f['email'] ?: '—') ?>
                </td>

                <td class="fornec-acoes">
                    <button
                        class="btn-secondary"
                        onclick='abrirModal(<?= json_encode($f) ?>)'
                        title="Editar fornecedor"
                    >
                        <i class="fa-solid fa-pen"></i>
                    </button>

                    <button
                        class="btn-danger"
                        onclick="excluir(<?= $f['id'] ?>, '<?= htmlspecialchars($f['nome'], ENT_QUOTES) ?>')"
                        title="Excluir fornecedor"
                    >
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- MODAL FORNECEDOR -->
<div id="modalFornecedor" class="modal hidden">
    <div class="modal-box">
        <h2 id="modalTitulo">Novo Fornecedor</h2>

        <input type="hidden" id="fornec_id">

        <label>Nome <span class="obrigatorio">*</span></label>
        <input type="text" id="fornec_nome" placeholder="Nome do fornecedor">

        <label>CNPJ</label>
        <input type="text" id="fornec_cnpj" placeholder="00.000.000/0000-00">

        <label>Telefone</label>
        <input type="text" id="fornec_telefone" placeholder="(00) 00000-0000">

        <label>Email</label>
        <input type="email" id="fornec_email" placeholder="email@exemplo.com">

        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModal()">Cancelar</button>
            <button class="btn-primary" onclick="salvar()">
                <i class="fa-solid fa-floppy-disk"></i> Salvar
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<script>
// ========================================
// SCRIPT DE FORNECEDORES
// ========================================

const modal = document.getElementById('modalFornecedor');

function abrirModal(fornec = null) {
    modal.classList.remove('hidden');

    if (fornec) {
        document.getElementById('modalTitulo').innerText = 'Editar Fornecedor';
        document.getElementById('fornec_id').value = fornec.id;
        document.getElementById('fornec_nome').value = fornec.nome;
        document.getElementById('fornec_cnpj').value = fornec.cnpj || '';
        document.getElementById('fornec_telefone').value = fornec.telefone || '';
        document.getElementById('fornec_email').value = fornec.email || '';

    } else {
        document.getElementById('modalTitulo').innerText = 'Novo Fornecedor';
        modal.querySelectorAll('input').forEach(el => el.value = '');
    }
}

function fecharModal() {
    modal.classList.add('hidden');
}

async function salvar() {
    const id = document.getElementById('fornec_id').value;
    const nome = document.getElementById('fornec_nome').value.trim();

    if (!nome) {
        alert('Nome é obrigatório');
        return;
    }

    const formData = new FormData();
    if (id) formData.append('id', id);
    formData.append('nome', nome);
    formData.append('cnpj', document.getElementById('fornec_cnpj').value.trim());
    formData.append('telefone', document.getElementById('fornec_telefone').value.trim());
    formData.append('email', document.getElementById('fornec_email').value.trim());

    const url = id
        ? '../api.php?action=update_fornecedor'
        : '../api.php?action=create_fornecedor';

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
            alert(resp.message || 'Erro ao salvar fornecedor');
            return;
        }

        fecharModal();
        location.reload();
    })
    .catch(err => {
        console.error(err);
        alert('Erro ao salvar fornecedor');
    });
}

async function excluir(id, nome) {
    if (!confirm(`Excluir fornecedor "${nome}"?`)) return;

    const formData = new FormData();
    formData.append('id', id);

    fetch('../api.php?action=delete_fornecedor', {
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
            alert(resp.message || 'Erro ao excluir fornecedor');
            return;
        }

        location.reload();
    })
    .catch(err => {
        console.error(err);
        alert('Erro ao excluir fornecedor');
    });
}
</script>