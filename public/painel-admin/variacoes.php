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

// BUSCAR TIPOS DE VARIAÇÃO
$tipos = $db->query("
    SELECT id, nome, slug
    FROM tipos_variacao
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

// BUSCAR VALORES POR TIPO
$valoresStmt = $db->prepare("
    SELECT id, valor, slug
    FROM valores_variacao
    WHERE id_tipo_variacao = :tipo_id
    ORDER BY valor
");
?>

<div class="main pagina-variacoes">

    <!-- HEADER -->
    <div class="pagina-header">
        <h1 class="pagina-titulo">Tipos e Valores de Variação</h1>
        <button class="btn-primary" onclick="abrirModalTipo()">
            ➕ Novo Tipo
        </button>
    </div>

    <!-- GRID DE TIPOS -->
    <div class="tipos-grid">

        <?php foreach ($tipos as $tipo): ?>
            
            <?php
                $valoresStmt->execute(['tipo_id' => $tipo['id']]);
                $valores = $valoresStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="tipo-card">
                <div class="tipo-header">
                    <h3>
                        🏷️ <?= htmlspecialchars($tipo['nome']) ?>
                        <span class="tipo-slug"><?= htmlspecialchars($tipo['slug']) ?></span>
                    </h3>
                    <div class="tipo-acoes">
                        <button 
                            class="btn-secondary" 
                            onclick='editarTipo(<?= json_encode($tipo) ?>)'
                            title="Editar tipo"
                        >
                            ✏️
                        </button>
                        <button 
                            class="btn-danger" 
                            onclick="excluirTipo(<?= $tipo['id'] ?>, '<?= htmlspecialchars($tipo['nome'], ENT_QUOTES) ?>')"
                            title="Excluir tipo"
                        >
                            🗑️
                        </button>
                    </div>
                </div>

                <!-- VALORES -->
                <div class="valores-lista">
                    <?php if ($valores): ?>
                        <?php foreach ($valores as $valor): ?>
                            <div class="valor-item">
                                <span class="valor-texto"><?= htmlspecialchars($valor['valor']) ?></span>
                                <div class="valor-acoes">
                                    <button 
                                        class="btn-icon" 
                                        onclick='editarValor(<?= json_encode($valor) ?>, <?= $tipo['id'] ?>)'
                                        title="Editar"
                                    >
                                        ✏️
                                    </button>
                                    <button 
                                        class="btn-icon btn-icon-danger" 
                                        onclick="excluirValor(<?= $valor['id'] ?>)"
                                        title="Excluir"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="sem-valores">Nenhum valor cadastrado</p>
                    <?php endif; ?>
                </div>

                <!-- BOTÃO ADICIONAR VALOR -->
                <button 
                    class="btn-add-valor" 
                    onclick="abrirModalValor(<?= $tipo['id'] ?>, '<?= htmlspecialchars($tipo['nome'], ENT_QUOTES) ?>')"
                >
                    ➕ Adicionar valor
                </button>
            </div>

        <?php endforeach; ?>

        <?php if (empty($tipos)): ?>
            <div class="sem-dados-grid">
                <p>📦 Nenhum tipo de variação cadastrado</p>
                <button class="btn-primary" onclick="abrirModalTipo()">
                    ➕ Criar primeiro tipo
                </button>
            </div>
        <?php endif; ?>

    </div>

</div>

<!-- MODAL TIPO -->
<div id="modalTipo" class="modal hidden">
    <div class="modal-box">
        <h2 id="modalTipoTitulo">Novo Tipo de Variação</h2>

        <input type="hidden" id="tipo_id">

        <label>Nome <span class="obrigatorio">*</span></label>
        <input type="text" id="tipo_nome" placeholder="Ex: Cor, Tamanho, Sabor">

        <label>Slug <span class="obrigatorio">*</span></label>
        <input type="text" id="tipo_slug" placeholder="Ex: cor, tamanho, sabor">
        <small style="color: #6c757d; font-size: 12px;">Identificador único (sem espaços, apenas letras minúsculas)</small>

        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModalTipo()">Cancelar</button>
            <button class="btn-primary" onclick="salvarTipo()">Salvar</button>
        </div>
    </div>
</div>

<!-- MODAL VALOR -->
<div id="modalValor" class="modal hidden">
    <div class="modal-box">
        <h2 id="modalValorTitulo">Novo Valor</h2>

        <input type="hidden" id="valor_id">
        <input type="hidden" id="valor_tipo_id">

        <label>Valor <span class="obrigatorio">*</span></label>
        <input type="text" id="valor_valor" placeholder="Ex: Vermelho, P, Morango">

        <label>Slug <span class="obrigatorio">*</span></label>
        <input type="text" id="valor_slug" placeholder="Ex: vermelho, p, morango">
        <small style="color: #6c757d; font-size: 12px;">Identificador único (sem espaços, apenas letras minúsculas)</small>

        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModalValor()">Cancelar</button>
            <button class="btn-primary" onclick="salvarValor()">Salvar</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<script>
// ========================================
// MODAL TIPO
// ========================================
const modalTipo = document.getElementById('modalTipo');

function abrirModalTipo(tipo = null) {
    modalTipo.classList.remove('hidden');

    if (tipo) {
        document.getElementById('modalTipoTitulo').innerText = 'Editar Tipo';
        document.getElementById('tipo_id').value = tipo.id;
        document.getElementById('tipo_nome').value = tipo.nome;
        document.getElementById('tipo_slug').value = tipo.slug;
    } else {
        document.getElementById('modalTipoTitulo').innerText = 'Novo Tipo de Variação';
        document.getElementById('tipo_id').value = '';
        document.getElementById('tipo_nome').value = '';
        document.getElementById('tipo_slug').value = '';
    }
}

function editarTipo(tipo) {
    abrirModalTipo(tipo);
}

function fecharModalTipo() {
    modalTipo.classList.add('hidden');
}

async function salvarTipo() {
    const id = document.getElementById('tipo_id').value;
    const nome = document.getElementById('tipo_nome').value.trim();
    const slug = document.getElementById('tipo_slug').value.trim();

    if (!nome || !slug) {
        alert('Preencha todos os campos obrigatórios');
        return;
    }

    const formData = new FormData();
    if (id) formData.append('id', id);
    formData.append('nome', nome);
    formData.append('slug', slug);

    const url = id
        ? '../api.php?action=update_tipo_variacao'
        : '../api.php?action=create_tipo_variacao';

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
            alert(data.message || 'Erro ao salvar tipo');
        }
    } catch (error) {
        console.error('Erro ao salvar tipo:', error);
        alert('Erro ao salvar tipo');
    }
}

async function excluirTipo(id, nome) {
    if (!confirm(`Tem certeza que deseja excluir o tipo "${nome}"?\n\nTodos os valores vinculados também serão excluídos.`)) {
        return;
    }

    const formData = new FormData();
    formData.append('id', id);

    try {
        const response = await fetch('../api.php?action=delete_tipo_variacao', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'Erro ao excluir tipo');
        }
    } catch (error) {
        console.error(error);
        alert('Erro ao excluir tipo');
    }
}

// ========================================
// MODAL VALOR
// ========================================
const modalValor = document.getElementById('modalValor');

function abrirModalValor(tipoId, tipoNome, valor = null) {
    modalValor.classList.remove('hidden');
    document.getElementById('valor_tipo_id').value = tipoId;

    if (valor) {
        document.getElementById('modalValorTitulo').innerText = `Editar Valor - ${tipoNome}`;
        document.getElementById('valor_id').value = valor.id;
        document.getElementById('valor_valor').value = valor.valor;
        document.getElementById('valor_slug').value = valor.slug;
    } else {
        document.getElementById('modalValorTitulo').innerText = `Novo Valor - ${tipoNome}`;
        document.getElementById('valor_id').value = '';
        document.getElementById('valor_valor').value = '';
        document.getElementById('valor_slug').value = '';
    }
}

function editarValor(valor, tipoId) {
    // Buscar nome do tipo
    const tipoNome = document.querySelector(`[onclick*="excluirTipo(${tipoId}"]`)
        ?.closest('.tipo-card')
        ?.querySelector('h3')
        ?.textContent
        ?.split('🏷️')[1]
        ?.split('\n')[0]
        ?.trim() || 'Tipo';
    
    abrirModalValor(tipoId, tipoNome, valor);
}

function fecharModalValor() {
    modalValor.classList.add('hidden');
}

async function salvarValor() {
    const id = document.getElementById('valor_id').value;
    const tipoId = document.getElementById('valor_tipo_id').value;
    const valor = document.getElementById('valor_valor').value.trim();
    const slug = document.getElementById('valor_slug').value.trim();

    if (!valor || !slug) {
        alert('Preencha todos os campos obrigatórios');
        return;
    }

    const formData = new FormData();
    if (id) formData.append('id', id);
    formData.append('id_tipo_variacao', tipoId);
    formData.append('valor', valor);
    formData.append('slug', slug);

    const url = id
        ? '../api.php?action=update_valor_variacao'
        : '../api.php?action=create_valor_variacao';

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
            alert(data.message || 'Erro ao salvar valor');
        }
    } catch (error) {
        console.error('Erro ao salvar valor:', error);
        alert('Erro ao salvar valor');
    }
}

async function excluirValor(id) {
    if (!confirm('Tem certeza que deseja excluir este valor?')) {
        return;
    }

    const formData = new FormData();
    formData.append('id', id);

    try {
        const response = await fetch('../api.php?action=delete_valor_variacao', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'Erro ao excluir valor');
        }
    } catch (error) {
        console.error(error);
        alert('Erro ao excluir valor');
    }
}

// Auto-gerar slug
document.getElementById('tipo_nome')?.addEventListener('input', function() {
    const slug = this.value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    document.getElementById('tipo_slug').value = slug;
});

document.getElementById('valor_valor')?.addEventListener('input', function() {
    const slug = this.value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    document.getElementById('valor_slug').value = slug;
});
</script>